<?php
/**
 * Plugin Name: League Profiles
 * Description: Player profiles and game histories for the World Riichi League
 * Version: 1.0.32
 * Requires at least: 5.0
 * Requires PHP: 8.0
 * Author: Andrew ZP Smith / ZAPS / ApplySci
 * Text Domain: league-profiles
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('LEAGUE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LEAGUE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Update the database path constant to use the uploaded path if available
$upload_dir = wp_upload_dir();
$database_dir = $upload_dir['basedir'] . '/league-profiles';
$database_path = $database_dir . '/league.db';

// Allow override through WordPress options
$custom_path = get_option('league_database_path');
if ($custom_path && file_exists($custom_path)) {
    define('LEAGUE_GAME_DB_PATH', $custom_path);
} else {
    define('LEAGUE_GAME_DB_PATH', $database_path);
}

// Autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'League_';
    $base_dir = LEAGUE_PLUGIN_DIR . 'includes/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    
    // Handle auth subdirectory classes
    if (str_starts_with($relative_class, 'Auth_')) {
        $file = $base_dir . 'auth/class-' . strtolower(str_replace('_', '-', substr($relative_class, 5))) . '.php';
    } else {
        $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';
    }

    if (file_exists($file)) {
        require_once $file;
    }
});

// Load OAuth related classes in correct order
require_once LEAGUE_PLUGIN_DIR . 'includes/auth/class-oauth-provider.php';
require_once LEAGUE_PLUGIN_DIR . 'includes/auth/class-google-provider.php';
require_once LEAGUE_PLUGIN_DIR . 'includes/auth/class-apple-provider.php';
require_once LEAGUE_PLUGIN_DIR . 'includes/auth/class-auth-controller.php';

// Initialize plugin
function league_profiles_init(): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }
    $initialized = true;

    new League_Plugin();
    new League_Admin();
    new League_Post_Types();
    new League_Roles();
    
    // Only initialize auth controller once and store instance
    global $league_auth_controller;
    if (!isset($league_auth_controller)) {
        $league_auth_controller = new League_Auth_Controller();
    }
}
add_action('plugins_loaded', 'league_profiles_init');

// Activation hook
register_activation_hook(__FILE__, function(): void {
    require_once LEAGUE_PLUGIN_DIR . 'includes/class-roles.php';
    League_Roles::register_roles();
    
    // Schedule cleanup tasks
    if (!wp_next_scheduled('league_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'league_daily_cleanup');
    }

    add_action('league_daily_cleanup', function(): void {
        League_Security::cleanup_rate_limits();
        
        // Cleanup old logs (keep last 30 days)
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/league-profiles.log';
        if (file_exists($log_file) && filesize($log_file) > 5 * MB_IN_BYTES) {
            rename($log_file, $log_file . '.old');
        }
    });

    // Flush rewrite rules
    flush_rewrite_rules();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function(): void {
    require_once LEAGUE_PLUGIN_DIR . 'includes/class-roles.php';
    League_Roles::remove_roles();
    wp_clear_scheduled_hook('league_daily_cleanup');
}); 