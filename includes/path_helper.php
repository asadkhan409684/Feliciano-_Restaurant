<?php
/**
 * Path Helper Functions
 * 
 * This file provides reusable path calculation functions for the restaurant website.
 * It helps maintain consistent and correct relative paths across all pages.
 */

/**
 * Calculate the correct path to an asset file based on current file depth
 * 
 * @param int $depth Current file depth (0 for root, 1 for pages/, 2 for pages/Profile/)
 * @param string $type Asset type ('css', 'js', 'images')
 * @param string $file Asset filename
 * @return string Correct relative path to the asset
 */
function getAssetPath($depth, $type, $file) {
    $prefix = str_repeat('../', $depth);
    return $prefix . "assets/" . $type . "/" . $file;
}

/**
 * Calculate the correct path to a page based on current file depth
 * 
 * @param int $currentDepth Current file depth (0 for root, 1 for pages/auth/admin, 2 for pages/Profile/)
 * @param string $targetPage Target page identifier ('home', 'menu', 'about', 'contact', 'profile', 'login')
 * @return string Correct relative path to the target page
 */
function getPagePath($currentDepth, $targetPage) {
    $paths = [
        'home' => [
            0 => 'index.php',
            1 => '../index.php',
            2 => '../../index.php'
        ],
        'menu' => [
            0 => 'pages/menu.php',
            1 => 'menu.php',  // from pages/ directory
            2 => '../menu.php'  // from pages/Profile/
        ],
        'about' => [
            0 => 'pages/about.php',
            1 => 'about.php',  // from pages/ directory
            2 => '../about.php'  // from pages/Profile/
        ],
        'contact' => [
            0 => 'pages/contact.php',
            1 => 'contact.php',  // from pages/ directory
            2 => '../contact.php'  // from pages/Profile/
        ],
        'profile' => [
            0 => 'pages/Profile/profile.php',
            1 => 'Profile/profile.php',  // from pages/ directory
            2 => 'profile.php'  // from pages/Profile/ directory
        ],
        'login' => [
            0 => 'auth/login.php',
            1 => 'login.php',  // from auth/ directory
            2 => '../../auth/login.php'  // from pages/Profile/
        ]
    ];
    
    // Handle special case for pages/ directory navigating to auth/
    if ($targetPage === 'login' && $currentDepth === 1) {
        // Need to determine if we're in pages/ or auth/
        // Default to assuming pages/ directory
        return '../auth/login.php';
    }
    
    if (isset($paths[$targetPage][$currentDepth])) {
        return $paths[$targetPage][$currentDepth];
    }
    
    // Fallback
    return '#';
}

/**
 * Calculate the correct path to the database configuration file
 * 
 * @param int $depth Current file depth (0 for root, 1 for pages/auth/admin, 2 for pages/Profile/)
 * @return string Correct relative path to config/database.php
 */
function getDatabasePath($depth) {
    $prefix = str_repeat('../', $depth);
    return $prefix . "config/database.php";
}

/**
 * Generate navigation links array based on current file depth
 * 
 * @param int $currentDepth Current file depth (0 for root, 1 for pages/auth/admin, 2 for pages/Profile/)
 * @return array Array of navigation links with 'text' and 'href' keys
 */
function getNavigationLinks($currentDepth) {
    return [
        [
            'text' => 'Home',
            'href' => getPagePath($currentDepth, 'home')
        ],
        [
            'text' => 'Menu',
            'href' => getPagePath($currentDepth, 'menu')
        ],
        [
            'text' => 'About',
            'href' => getPagePath($currentDepth, 'about')
        ],
        [
            'text' => 'Contact',
            'href' => getPagePath($currentDepth, 'contact')
        ],
        [
            'text' => 'Profile',
            'href' => getPagePath($currentDepth, 'profile')
        ],
        [
            'text' => 'Login',
            'href' => getPagePath($currentDepth, 'login')
        ]
    ];
}
