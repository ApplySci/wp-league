<?php
/**
 * Plugin Name: League Profiles
 * Description: Player profiles and game histories for a league system
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 8.0
 * Author: Your Name
 * Text Domain: league-profiles
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('LEAGUE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LEAGUE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LEAGUE_GAME_DB_PATH', WP_CONTENT_DIR . '/database/league.db');

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'League_';
    $base_dir = LEAGUE_PLUGIN_DIR . 'includes/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $file = $base_dir . str_replace('_', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize plugin
function league_profiles_init(): void {
    new League_Admin();
    new League_Post_Types();
    new League_Roles();
    new League_Auth_Controller();
}
add_action('plugins_loaded', 'league_profiles_init');

// Activation hook
register_activation_hook(__FILE__, function(): void {
    require_once LEAGUE_PLUGIN_DIR . 'includes/class-roles.php';
    League_Roles::register_roles();
    
    if (!file_exists(LEAGUE_GAME_DB_PATH)) {
        wp_die('SQLite database not found at: ' . LEAGUE_GAME_DB_PATH);
    }

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
});

// Deactivation hook
register_deactivation_hook(__FILE__, function(): void {
    require_once LEAGUE_PLUGIN_DIR . 'includes/class-roles.php';
    League_Roles::remove_roles();
    wp_clear_scheduled_hook('league_daily_cleanup');
}); 