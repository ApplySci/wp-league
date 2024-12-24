<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options
delete_option('league_google_client_id');
delete_option('league_google_client_secret');
delete_option('league_apple_client_id');
delete_option('league_apple_client_secret');

// Remove all transients
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_league_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_league_%'");

// Remove custom post type posts
$posts = get_posts([
    'post_type' => 'league_player',
    'numberposts' => -1,
    'post_status' => 'any'
]);

foreach ($posts as $post) {
    wp_delete_post($post->ID, true);
}

// Remove custom capabilities from admin role
$admin_role = get_role('administrator');
if ($admin_role) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-roles.php';
    League_Roles::remove_roles();
} 