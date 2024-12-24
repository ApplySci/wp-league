<?php
declare(strict_types=1);

class League_Admin {
    private League_Logger $logger;

    public function __construct() {
        $this->logger = League_Logger::get_instance();
        
        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_invite_player', [$this, 'handle_player_invitation']);
    }

    public function add_menu_pages(): void {
        add_submenu_page(
            'edit.php?post_type=league_player',
            __('Invite Player', 'league-profiles'),
            __('Invite Player', 'league-profiles'),
            'edit_others_league_players',
            'invite-player',
            [$this, 'render_invite_page']
        );

        add_submenu_page(
            'edit.php?post_type=league_player',
            __('League Settings', 'league-profiles'),
            __('Settings', 'league-profiles'),
            'manage_options',
            'league-settings',
            [$this, 'render_settings_page']
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
        if (!in_array($hook, ['league_player_page_invite-player', 'league_player_page_league-settings'])) {
            return;
        }

        $version = defined('WP_DEBUG') && WP_DEBUG ? time() : '1.0.0';

        wp_enqueue_style(
            'league-admin',
            LEAGUE_PLUGIN_URL . 'assets/css/admin.css',
            [],
            $version
        );

        wp_enqueue_script(
            'league-admin',
            LEAGUE_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            $version,
            true
        );

        wp_localize_script('league-admin', 'leagueAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('league_admin_nonce')
        ]);
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
} 