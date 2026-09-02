<?php
/**
 * RBAC — Role-Based Access Control
 * Feliciano Restaurant
 *
 * Usage:
 *   require_once '../config/rbac.php';
 *   rbac_init($conn);
 *   rbac_require_permission('orders');          // dies with 403 if denied
 *   $ok = rbac_can('menu-management');          // returns bool
 *   $sections = rbac_allowed_sections();         // returns array of allowed section keys
 */

// ── Default permissions per role ─────────────────────────────────────────────
// key   = section id (matches admin panel section IDs)
// value = array of roles that have access BY DEFAULT
define('RBAC_SECTIONS', [
    'dashboard'          => ['admin','manager','chef','waiter','cashier'],
    'category-management'=> ['admin','manager'],
    'menu-management'    => ['admin','manager','chef'],
    'combo-meals'        => ['admin','manager'],
    'orders'             => ['admin','manager','chef','waiter','cashier'],
    'reservations'       => ['admin','manager','waiter'],
    'tables'             => ['admin','manager','waiter'],
    'kitchen'            => ['admin','manager','chef'],
    'delivery'           => ['admin','manager'],
    'coupons'            => ['admin','manager'],
    'billing'            => ['admin','manager','cashier'],
    'expenses'           => ['admin','manager'],
    'inventory'          => ['admin','manager'],
    'suppliers'          => ['admin','manager'],
    'purchases'          => ['admin','manager'],
    'website-content'    => ['admin'],
    'gallery'            => ['admin','manager'],
    'events'             => ['admin','manager'],
    'reviews'            => ['admin','manager'],
    'customers'          => ['admin','manager','cashier'],
    'team'               => ['admin','manager'],
    'branches'           => ['admin'],
    'analytics'          => ['admin','manager'],
    'notifications'      => ['admin','manager'],
    'activity-log'       => ['admin'],
    'settings'           => ['admin'],
    'rbac'               => ['admin'],   // Role Permission Editor
]);

// Human-readable section labels (for Permission Editor UI)
define('RBAC_SECTION_LABELS', [
    'dashboard'          => 'Dashboard',
    'category-management'=> 'Category Management',
    'menu-management'    => 'Menu Management',
    'combo-meals'        => 'Combo Meals',
    'orders'             => 'Orders',
    'reservations'       => 'Reservations',
    'tables'             => 'Table Management',
    'kitchen'            => 'Kitchen',
    'delivery'           => 'Delivery',
    'coupons'            => 'Coupons & Offers',
    'billing'            => 'Billing & Payment',
    'expenses'           => 'Expenses',
    'inventory'          => 'Inventory',
    'suppliers'          => 'Suppliers',
    'purchases'          => 'Purchases',
    'website-content'    => 'Website Content',
    'gallery'            => 'Gallery',
    'events'             => 'Events',
    'reviews'            => 'Reviews',
    'customers'          => 'Customers',
    'team'               => 'Staff Management',
    'branches'           => 'Branches',
    'analytics'          => 'Reports & Analytics',
    'notifications'      => 'Notifications',
    'activity-log'       => 'System Logs',
    'settings'           => 'Settings',
    'rbac'               => 'Role Permissions',
]);

// All manageable roles (excluding admin — admin always has full access)
define('RBAC_ROLES', [
    'manager' => 'Manager',
    'chef'    => 'Chef',
    'waiter'  => 'Waiter',
    'cashier' => 'Cashier',
]);

// Roles that get redirected to admin panel (not manager panel)
define('RBAC_STAFF_ROLES', ['chef','waiter','cashier']);

// ── Runtime permission cache ──────────────────────────────────────────────────
$_rbac_permissions = null;   // loaded once per request

/**
 * Load permissions from DB (or fall back to defaults).
 * Must be called once after DB connection is available.
 */
function rbac_init($conn) {
    global $_rbac_permissions;

    // Seed defaults if table is empty
    $cnt = $conn->query("SELECT COUNT(*) as c FROM role_permissions")->fetch_assoc()['c'];
    if ($cnt == 0) {
        rbac_seed_defaults($conn);
    }

    // Load into cache
    $_rbac_permissions = [];
    $res = $conn->query("SELECT role, section, allowed FROM role_permissions");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $_rbac_permissions[$row['role']][$row['section']] = (bool)$row['allowed'];
        }
    }
}

/**
 * Seed the role_permissions table with RBAC_SECTIONS defaults.
 */
function rbac_seed_defaults($conn) {
    $sections = RBAC_SECTIONS;
    $roles    = array_keys(RBAC_ROLES);
    $stmt = $conn->prepare("INSERT IGNORE INTO role_permissions (role, section, allowed) VALUES (?,?,?)");
    foreach ($roles as $role) {
        foreach ($sections as $section => $allowed_roles) {
            $allowed = in_array($role, $allowed_roles) ? 1 : 0;
            $stmt->bind_param("ssi", $role, $section, $allowed);
            $stmt->execute();
        }
    }
}

/**
 * Check if current session user can access a section.
 * Admin always returns true.
 */
function rbac_can($section) {
    global $_rbac_permissions;
    $role = $_SESSION['user_role'] ?? '';

    // Admin always has full access
    if ($role === 'admin') return true;

    // Check DB-loaded permissions first
    if (isset($_rbac_permissions[$role][$section])) {
        return (bool)$_rbac_permissions[$role][$section];
    }

    // Fall back to RBAC_SECTIONS defaults
    $defaults = RBAC_SECTIONS[$section] ?? [];
    return in_array($role, $defaults);
}

/**
 * Hard-gate: redirect to dashboard with error if not allowed.
 */
function rbac_require_permission($section) {
    if (!rbac_can($section)) {
        // For API calls, return JSON error
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['REQUEST_URI'] ?? '', 'api')) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Access denied. Insufficient permissions.']);
            exit;
        }
        // For page requests, redirect to dashboard
        header('Location: admin.php#dashboard');
        exit;
    }
}

/**
 * Returns array of section keys the current user can access.
 */
function rbac_allowed_sections() {
    $sections = array_keys(RBAC_SECTIONS);
    return array_filter($sections, 'rbac_can');
}

/**
 * Save a single permission change.
 * Used by the Permission Editor API.
 */
function rbac_save_permission($conn, $role, $section, $allowed) {
    // Never allow editing admin permissions through this
    if ($role === 'admin') return false;
    // Validate inputs
    if (!array_key_exists($role, RBAC_ROLES)) return false;
    if (!array_key_exists($section, RBAC_SECTIONS)) return false;
    $allowed = $allowed ? 1 : 0;
    $stmt = $conn->prepare("INSERT INTO role_permissions (role, section, allowed)
                            VALUES (?,?,?)
                            ON DUPLICATE KEY UPDATE allowed=?");
    $stmt->bind_param("ssii", $role, $section, $allowed, $allowed);
    return $stmt->execute();
}

/**
 * Get all permissions for Permission Editor UI.
 * Returns [ role => [ section => bool ] ]
 */
function rbac_get_all($conn) {
    $result = [];
    $roles    = array_keys(RBAC_ROLES);
    $sections = array_keys(RBAC_SECTIONS);

    // Initialize with defaults
    foreach ($roles as $role) {
        foreach ($sections as $section) {
            $result[$role][$section] = in_array($role, RBAC_SECTIONS[$section]);
        }
    }

    // Override with DB values
    $res = $conn->query("SELECT role, section, allowed FROM role_permissions");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if (isset($result[$row['role']][$row['section']])) {
                $result[$row['role']][$row['section']] = (bool)$row['allowed'];
            }
        }
    }
    return $result;
}
