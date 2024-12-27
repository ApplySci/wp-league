<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(
        message: __('You do not have permission to access this page.', 'league-profiles'),
        title: 'Permission Error',
        args: ['response' => 403]
    );
}

// Get any existing OAuth errors
$oauth_error = get_transient('league_oauth_error');
if ($oauth_error) {
    delete_transient('league_oauth_error');
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if ($oauth_error): ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($oauth_error); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php" class="league-settings-form">
        <?php
        settings_fields('league_settings');
        do_settings_sections('league_settings');
        ?>

        <h2><?php esc_html_e('OAuth Settings', 'league-profiles'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Google Client ID', 'league-profiles'); ?></th>
                <td>
                    <input type="text" 
                           name="league_google_client_id" 
                           value="<?php echo esc_attr(get_option('league_google_client_id')); ?>" 
                           class="regular-text"
                           pattern="^[0-9]+[-][a-zA-Z0-9_\.]+\.apps\.googleusercontent\.com$"
                           title="<?php esc_attr_e('Please enter a valid Google Client ID', 'league-profiles'); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Google Client Secret', 'league-profiles'); ?></th>
                <td>
                    <input type="password" 
                           name="league_google_client_secret" 
                           value="<?php echo esc_attr(get_option('league_google_client_secret')); ?>" 
                           class="regular-text"
                           autocomplete="new-password">
                    <p class="description">
                        <?php esc_html_e('Store this securely. It will be encrypted before saving.', 'league-profiles'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Apple Client ID', 'league-profiles'); ?></th>
                <td>
                    <input type="text" 
                           name="league_apple_client_id" 
                           value="<?php echo esc_attr(get_option('league_apple_client_id')); ?>" 
                           class="regular-text"
                           pattern="^[a-zA-Z0-9\.]+$"
                           title="<?php esc_attr_e('Please enter a valid Apple Client ID', 'league-profiles'); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Apple Client Secret', 'league-profiles'); ?></th>
                <td>
                    <input type="password" 
                           name="league_apple_client_secret" 
                           value="<?php echo esc_attr(get_option('league_apple_client_secret')); ?>" 
                           class="regular-text"
                           autocomplete="new-password">
                    <p class="description">
                        <?php esc_html_e('Store this securely. It will be encrypted before saving.', 'league-profiles'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Security Settings', 'league-profiles'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Rate Limiting', 'league-profiles'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="league_enable_rate_limiting" 
                               value="1" 
                               <?php checked(get_option('league_enable_rate_limiting', '1')); ?>>
                        <?php esc_html_e('Enable rate limiting for API requests', 'league-profiles'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Debug Logging', 'league-profiles'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="league_enable_logging" 
                               value="1" 
                               <?php checked(get_option('league_enable_logging', '0')); ?>>
                        <?php esc_html_e('Enable debug logging', 'league-profiles'); ?>
                    </label>
                    <?php if (League_Logger::get_instance()->get_recent_logs()): ?>
                        <p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=league-logs')); ?>" 
                               class="button">
                                <?php esc_html_e('View Logs', 'league-profiles'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>

    <form method="post" 
          action="<?php echo esc_url(admin_url('admin-post.php')); ?>" 
          enctype="multipart/form-data"
          class="league-database-upload">
        <h2><?php esc_html_e('Database Settings', 'league-profiles'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('SQLite Database', 'league-profiles'); ?></th>
                <td>
                    <?php
                    $database_path = LEAGUE_GAME_DB_PATH;
                    if (file_exists($database_path)):
                    ?>
                        <p class="description">
                            <?php echo esc_html(sprintf(
                                __('Current database: %s', 'league-profiles'),
                                $database_path
                            )); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <input type="hidden" name="action" value="upload_database">
        <?php wp_nonce_field('upload_database', 'database_nonce'); ?>
        
        <input type="file" 
               name="database_file"
               accept=".db,.sqlite,.sqlite3"
               required>
        
        <?php submit_button(__('Upload Database', 'league-profiles'), 'secondary'); ?>
        
        <p class="description">
            <?php esc_html_e('Upload a SQLite database file containing league data.', 'league-profiles'); ?>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Warn before leaving if form has been modified
    let formModified = false;
    $('.league-settings-form :input').on('change', function() {
        formModified = true;
    });

    $(window).on('beforeunload', function() {
        if (formModified) {
            return '<?php esc_js(__('You have unsaved changes. Are you sure you want to leave?', 'league-profiles')); ?>';
        }
    });

    $('.league-settings-form').on('submit', function() {
        formModified = false;
    });
});
</script> 