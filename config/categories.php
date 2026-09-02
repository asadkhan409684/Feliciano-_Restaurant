<?php
/**
 * Centralized Menu Categories — Static Fallback
 * 
 * NOTE: এই ফাইলটি এখন শুধু fallback হিসেবে ব্যবহার হয়।
 * Primary category management এখন database-driven:
 *   Table: menu_categories
 *   API: admin_api.php?action=get_categories
 * 
 * এই array টি পুরনো menu items-এর জন্য backward compatibility রক্ষা করে।
 */
$category_labels = [
    'breakfast'            => 'Breakfast',
    'platter'              => 'Platters',
    'meal-deal'            => 'Meal Deals',
    'signature'            => 'Signature Dishes',
    'pizza'                => 'Pizza',
    'burger'               => 'Burger',
    'pasta & chowmein'     => 'Pasta & Chowmein',
    'sandwiches'           => 'Sandwiches',
    'savory waffle'        => 'Savory Waffle',
    'soup & ramen'         => 'Soup & Ramen',
    'fresh salad'          => 'Fresh Salad',
    'dessert'              => 'Dessert',
    'sugary waffle'        => 'Sugary Waffle',
    'frappuccino'          => 'Frappuccino',
    'mocktail'             => 'Mocktail',
    'milk shake'           => 'Milk Shake',
    'fresh juice'          => 'Fresh Juice',
    'hot coffee'           => 'Hot Coffee',
    'iced coffee'          => 'Iced Coffee',
    'side dishes'          => 'Side Dishes',
    'early meal add ons'   => 'Early Meal Add-ons',
    'uncategorized'        => 'Uncategorized',
];
?>
