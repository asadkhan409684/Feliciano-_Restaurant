<?php
/**
 * settings_helper.php
 * Restaurant settings কে database থেকে load করে একটি array-তে রাখে।
 * সব public frontend page এ include করা হয়।
 * 
 * Usage: require_once 'path/to/config/settings_helper.php';
 * তারপর: $settings['restaurant_name'], $settings['restaurant_logo'] ইত্যাদি ব্যবহার করো।
 */

function get_restaurant_settings($conn) {
    static $cached = null;
    if ($cached !== null) return $cached;

    $defaults = [
        'restaurant_name'    => 'Feliciano Restaurant',
        'restaurant_phone'   => '+8801772-353298',
        'restaurant_email'   => 'info@feliciano.com',
        'restaurant_address' => '123 Gourmet Street, Food City, FC 10001',
        'restaurant_about'   => 'Experience culinary excellence at Feliciano, where every dish tells a story of passion, quality, and tradition.',
        'restaurant_logo'    => '',
        'restaurant_cover'   => '',
        'google_map_url'     => '',
        'social_facebook'    => 'https://www.facebook.com',
        'social_instagram'   => 'https://www.instagram.com',
        'social_twitter'     => 'https://www.twitter.com',
        'social_whatsapp'    => '',
        'social_youtube'     => '',
        'social_tiktok'      => '',
        'open_monday'        => '11:00',
        'close_monday'       => '22:00',
        'open_tuesday'       => '11:00',
        'close_tuesday'      => '22:00',
        'open_wednesday'     => '11:00',
        'close_wednesday'    => '22:00',
        'open_thursday'      => '11:00',
        'close_thursday'     => '22:00',
        'open_friday'        => '11:00',
        'close_friday'       => '23:00',
        'open_saturday'      => '11:00',
        'close_saturday'     => '23:00',
        'open_sunday'        => '12:00',
        'close_sunday'       => '21:00',
        'closed_monday'      => '0',
        'closed_tuesday'     => '0',
        'closed_wednesday'   => '0',
        'closed_thursday'    => '0',
        'closed_friday'      => '0',
        'closed_saturday'    => '0',
        'closed_sunday'      => '0',
        'currency'           => 'TK',
        'tax_percentage'     => '0',
        'terms_conditions'   => '',
        'privacy_policy'     => '',
    ];

    $settings = $defaults;

    // restaurant_settings টেবিল আছে কিনা চেক করো
    $check = $conn->query("SHOW TABLES LIKE 'restaurant_settings'");
    if ($check && $check->num_rows > 0) {
        $res = $conn->query("SELECT setting_key, setting_value FROM restaurant_settings");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    }

    $cached = $settings;
    return $settings;
}

/**
 * Settings থেকে logo URL বের করে। না থাকলে favicon দেয়।
 * $base_path = root থেকে assets/ folder এর relative path (e.g. '' for root, '../' for subdir)
 */
function get_logo_url($settings, $base_path = '') {
    if (!empty($settings['restaurant_logo'])) {
        return $base_path . htmlspecialchars($settings['restaurant_logo']);
    }
    return $base_path . 'assets/images/favicon.png';
}

/**
 * Settings থেকে cover image URL বের করে।
 */
function get_cover_url($settings, $base_path = '') {
    if (!empty($settings['restaurant_cover'])) {
        return $base_path . htmlspecialchars($settings['restaurant_cover']);
    }
    return '';
}

/**
 * Operating hours HTML generate করে।
 */
function get_hours_html($settings) {
    $days = [
        'monday'    => 'Monday',
        'tuesday'   => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday'  => 'Thursday',
        'friday'    => 'Friday',
        'saturday'  => 'Saturday',
        'sunday'    => 'Sunday',
    ];

    // Group consecutive days with same hours
    $groups = [];
    foreach ($days as $key => $label) {
        if (!empty($settings['closed_' . $key]) && $settings['closed_' . $key] === '1') {
            $groups[] = ['label' => $label, 'hours' => 'Closed'];
        } else {
            $open  = $settings['open_' . $key] ?? '11:00';
            $close = $settings['close_' . $key] ?? '22:00';
            // Format time to 12-hour
            $open_fmt  = date('g:i A', strtotime($open));
            $close_fmt = date('g:i A', strtotime($close));
            $groups[] = ['label' => $label, 'hours' => "$open_fmt – $close_fmt"];
        }
    }

    // Merge consecutive days with same hours
    $merged = [];
    foreach ($groups as $g) {
        $last = count($merged) - 1;
        if ($last >= 0 && $merged[$last]['hours'] === $g['hours']) {
            $merged[$last]['end'] = $g['label'];
        } else {
            $merged[] = ['start' => $g['label'], 'end' => null, 'hours' => $g['hours']];
        }
    }

    $html = '';
    foreach ($merged as $m) {
        $range = $m['end'] ? $m['start'] . ' – ' . $m['end'] : $m['start'];
        $html .= '<p><strong>' . htmlspecialchars($range) . ':</strong> ' . htmlspecialchars($m['hours']) . '</p>';
    }
    return $html;
}
