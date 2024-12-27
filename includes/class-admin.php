<?php
declare(strict_types=1);

class League_Admin {
    private League_Logger $logger;

    public function __construct() {
        $this->logger = League_Logger::get_instance();
        
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_search_unregistered_players', [$this, 'handle_ajax_search_players']);
        add_action('admin_post_add_player', [$this, 'handle_add_player']);
        add_action('admin_post_bulk_add_players', [$this, 'handle_bulk_add_players']);
        add_action('admin_post_upload_database', [$this, 'handle_database_upload']);
    }

    public function register_admin_menu(): void {
        add_menu_page(
            __('League', 'league-profiles'),
            __('League', 'league-profiles'),
            'manage_options',
            'league-profiles',
            [$this, 'render_settings_page'],
            'dashicons-groups',
            30
        );

        add_submenu_page(
            'league-profiles',
            __('Settings', 'league-profiles'),
            __('Settings', 'league-profiles'),
            'manage_options',
            'league-profiles'
        );

        add_submenu_page(
            'league-profiles',
            __('Players', 'league-profiles'),
            __('Players', 'league-profiles'),
            'edit_others_league_players',
            'edit.php?post_type=league_player'
        );

        add_submenu_page(
            'league-profiles',
            __('Add New Player', 'league-profiles'),
            __('Add New Player', 'league-profiles'),
            'edit_others_league_players',
            'league-add-player',
            [$this, 'render_add_player_page']
        );

        add_submenu_page(
            'league-profiles',
            __('Bulk Add Players', 'league-profiles'),
            __('Bulk Add Players', 'league-profiles'),
            'edit_others_league_players',
            'league-bulk-add',
            [$this, 'render_bulk_add_page']
        );
    }

    public function register_settings(): void {
        register_setting('league_settings', 'league_google_client_id');
        register_setting('league_settings', 'league_google_client_secret');
        register_setting('league_settings', 'league_apple_client_id');
        register_setting('league_settings', 'league_apple_client_secret');
    }

    public function handle_player_invitation(): void {
        try {
            if (!League_Security::verify_request()) {
                throw new Exception('Security verification failed');
            }

            if (!current_user_can('edit_others_league_players')) {
                throw new Exception('Insufficient permissions');
            }

            $email = League_Security::sanitize_input($_POST['invite_email'] ?? '', 'email');
            if (!$email) {
                throw new Exception('Invalid email address');
            }

            $token = bin2hex(random_bytes(32));
            set_transient("league_invite_$token", $email, DAY_IN_SECONDS);

            $invite_url = add_query_arg([
                'action' => 'accept_invite',
                'token' => $token
            ], home_url());

            $sent = wp_mail(
                $email,
                League_Security::encode_utf8(__('Invitation to Join League', 'league-profiles')),
                sprintf(
                    League_Security::encode_utf8(
                        __("You've been invited to join the league. Click here to accept: %s", 'league-profiles')
                    ),
                    esc_url($invite_url)
                ),
                [
                    'Content-Type: text/html; charset=UTF-8',
                    'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>'
                ]
            );

            if (!$sent) {
                throw new Exception('Failed to send invitation email');
            }

            $this->logger->info("Invitation sent to $email");
            
            add_settings_error(
                'league_invite',
                'invite_sent',
                __('Invitation sent successfully.', 'league-profiles'),
                'updated'
            );

        } catch (Exception $e) {
            $this->logger->error('Invitation error', $e);
            add_settings_error(
                'league_invite',
                'invite_error',
                $e->getMessage(),
                'error'
            );
        }

        wp_safe_redirect(wp_get_referer());
        exit;
    }

    public function enqueue_admin_assets(string $hook): void {
        if (!in_array($hook, ['league_page_league-add-player', 'league_page_league-bulk-add'])) {
            return;
        }

        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery']);
    }

    public function render_invite_page(): void {
        if (!current_user_can('edit_others_league_players')) {
            wp_die(__('You do not have permission to access this page.', 'league-profiles'));
        }
        require_once LEAGUE_PLUGIN_DIR . 'templates/admin/invite-player.php';
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'league-profiles'));
        }
        require_once LEAGUE_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    public function handle_database_upload(): void {
        $this->debug_upload();
        try {
            if (!current_user_can('manage_options')) {
                throw new Exception(__('Insufficient permissions', 'league-profiles'));
            }

            if (!isset($_FILES['database_file']) || !wp_verify_nonce($_POST['database_nonce'], 'upload_database')) {
                throw new Exception(__('Invalid request', 'league-profiles'));
            }

            $file = $_FILES['database_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception(sprintf(
                    __('Upload failed with error code: %d', 'league-profiles'),
                    $file['error']
                ));
            }

            // Create upload directory if it doesn't exist
            $upload_dir = wp_upload_dir();
            $database_dir = $upload_dir['basedir'] . '/league-profiles';
            
            if (!file_exists($database_dir)) {
                if (!wp_mkdir_p($database_dir)) {
                    throw new Exception(__('Failed to create upload directory', 'league-profiles'));
                }
            }

            // Verify it's a SQLite database
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime_type, ['application/x-sqlite3', 'application/vnd.sqlite3', 'application/sqlite3', 'application/x-sqlite', 'application/sqlite'])) {
                throw new Exception(__('Invalid file type. Please upload a SQLite database file.', 'league-profiles'));
            }

            $target_path = $database_dir . '/league.db';

            // Backup existing database if it exists
            if (file_exists($target_path)) {
                $backup_path = $target_path . '.backup-' . date('Y-m-d-H-i-s');
                if (!rename($target_path, $backup_path)) {
                    throw new Exception(__('Failed to backup existing database', 'league-profiles'));
                }
            }

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                throw new Exception(__('Failed to move uploaded file', 'league-profiles'));
            }

            // Verify we can open the database
            try {
                $db = new SQLite3($target_path);
                $db->close();
            } catch (Exception $e) {
                unlink($target_path); // Remove invalid database
                throw new Exception(__('Invalid SQLite database file', 'league-profiles'));
            }

            // Update success message
            add_settings_error(
                'league_settings',
                'database_uploaded',
                __('Database uploaded successfully', 'league-profiles'),
                'updated'
            );

        } catch (Exception $e) {
            add_settings_error(
                'league_settings',
                'upload_error',
                $e->getMessage(),
                'error'
            );
        }

        // Redirect back to settings page
        wp_safe_redirect(add_query_arg(
            ['page' => 'league-profiles'],
            admin_url('admin.php')
        ));
        exit;
    }

    public function handle_ajax_search_players(): void {
        check_ajax_referer('search_players');
        
        if (!current_user_can('edit_others_league_players')) {
            wp_die(-1);
        }

        $search = sanitize_text_field($_GET['search'] ?? '');
        if (empty($search)) {
            wp_send_json([]);
        }

        $game_history = new League_Game_History();
        $players = $game_history->search_unregistered_players($search);
        wp_send_json($players);
    }

    public function handle_bulk_add_players(): void {
        try {
            if (!current_user_can('edit_others_league_players')) {
                throw new Exception(__('Insufficient permissions', 'league-profiles'));
            }

            if (!isset($_FILES['players_csv']) || !wp_verify_nonce($_POST['bulk_nonce'], 'bulk_add_players')) {
                throw new Exception(__('Invalid request', 'league-profiles'));
            }

            $file = $_FILES['players_csv'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception(__('File upload failed', 'league-profiles'));
            }

            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) {
                throw new Exception(__('Could not read file', 'league-profiles'));
            }

            $game_history = new League_Game_History();
            $success_count = 0;
            $failed_count = 0;
            $row = 1;

            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) !== 2) {
                    continue;
                }

                $trr_id = trim($data[0]);
                $email = sanitize_email(trim($data[1]));

                if (!$email || !$game_history->is_unregistered_player($trr_id)) {
                    $failed_count++;
                    continue;
                }

                // Send invitation
                $token = bin2hex(random_bytes(32));
                set_transient("league_invite_$token", json_encode(['email' => $email, 'trr_id' => $trr_id]), DAY_IN_SECONDS);

                $invite_url = add_query_arg([
                    'action' => 'accept_invite',
                    'token' => $token
                ], home_url());

                if (wp_mail($email, 
                    __('Invitation to Join League', 'league-profiles'),
                    sprintf(__("You've been invited to join the league. Click here to accept: %s", 'league-profiles'), esc_url($invite_url))
                )) {
                    $success_count++;
                } else {
                    $failed_count++;
                }
            }

            fclose($handle);

            add_settings_error(
                'league_bulk_invite',
                'bulk_invite_success',
                sprintf(
                    __('Processed invitations: %d successful, %d failed', 'league-profiles'),
                    $success_count,
                    $failed_count
                ),
                $success_count > 0 ? 'updated' : 'error'
            );

        } catch (Exception $e) {
            add_settings_error(
                'league_bulk_invite',
                'bulk_invite_error',
                $e->getMessage(),
                'error'
            );
        }

        wp_safe_redirect(wp_get_referer());
        exit;
    }

    public function render_add_player_page(): void {
        if (!current_user_can('edit_others_league_players')) {
            wp_die(__('You do not have permission to access this page.', 'league-profiles'));
        }
        require_once LEAGUE_PLUGIN_DIR . 'templates/admin/add-player.php';
    }

    public function render_bulk_add_page(): void {
        if (!current_user_can('edit_others_league_players')) {
            wp_die(__('You do not have permission to access this page.', 'league-profiles'));
        }
        require_once LEAGUE_PLUGIN_DIR . 'templates/admin/bulk-add.php';
    }

    private function debug_upload(): void {
        $upload_dir = wp_upload_dir();
        error_log("Upload base dir: " . $upload_dir['basedir']);
        error_log("Trying to create: " . $upload_dir['basedir'] . '/league-profiles');
        
        if (!file_exists($upload_dir['basedir'])) {
            error_log("Base uploads directory doesn't exist!");
            return;
        }
        
        error_log("Base upload permissions: " . substr(sprintf('%o', fileperms($upload_dir['basedir'])), -4));
        error_log("Base upload owner: " . posix_getpwuid(fileowner($upload_dir['basedir']))['name']);
        
        if (!is_writable($upload_dir['basedir'])) {
            error_log("Base uploads directory not writable!");
            return;
        }
        
        $result = wp_mkdir_p($upload_dir['basedir'] . '/league-profiles');
        error_log("mkdir result: " . ($result ? "success" : "failure"));
    }
} 