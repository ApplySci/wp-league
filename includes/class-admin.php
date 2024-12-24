<?php
class League_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('manage_league_player_posts_columns', [$this, 'set_custom_columns']);
        add_action('manage_league_player_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);
        add_filter('manage_edit-league_player_sortable_columns', [$this, 'set_sortable_columns']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function add_menu_pages(): void {
        add_submenu_page(
            'edit.php?post_type=league_player',
            'Invite Player',
            'Invite Player',
            'edit_others_league_players',
            'invite-player',
            [$this, 'render_invite_page']
        );

        add_submenu_page(
            'edit.php?post_type=league_player',
            'League Settings',
            'Settings',
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

    public function set_custom_columns($columns): array {
        $columns = [
            'cb' => $columns['cb'],
            'title' => __('Name'),
            'email' => __('Email'),
            'last_login' => __('Last Login'),
            'rating' => __('Rating'),
            'games_played' => __('Games Played'),
            'date' => __('Join Date')
        ];
        return $columns;
    }

    public function render_custom_columns($column, $post_id): void {
        $player_user_id = get_post_meta($post_id, 'player_user_id', true);
        $user = get_user_by('id', $player_user_id);

        switch ($column) {
            case 'email':
                echo $user ? esc_html($user->user_email) : '-';
                break;
            case 'last_login':
                $last_login = get_post_meta($post_id, 'last_login', true);
                echo $last_login ? esc_html(date('Y-m-d H:i', strtotime($last_login))) : '-';
                break;
            case 'rating':
                echo esc_html(get_post_meta($post_id, 'player_rating', true) ?: '-');
                break;
            case 'games_played':
                $game_history = new League_Game_History();
                $count = $game_history->get_player_games_count($player_user_id);
                echo esc_html($count);
                break;
        }
    }

    public function set_sortable_columns($columns): array {
        $columns['last_login'] = 'last_login';
        $columns['rating'] = 'rating';
        return $columns;
    }

    public function render_invite_page(): void {
        if (!current_user_can('edit_others_league_players')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_email'])) {
            $this->process_invitation();
        }

        include LEAGUE_PLUGIN_DIR . 'templates/admin/invite-player.php';
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        include LEAGUE_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    private function process_invitation(): void {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'invite_player')) {
            wp_die(__('Security check failed.'));
        }

        $email = sanitize_email($_POST['invite_email']);
        if (!is_email($email)) {
            add_settings_error('league_invite', 'invalid_email', 'Invalid email address.');
            return;
        }

        // Generate invitation token
        $token = wp_generate_password(32, false);
        $expiry = time() + (7 * DAY_IN_SECONDS);

        update_option("league_invitation_$token", [
            'email' => $email,
            'expiry' => $expiry
        ]);

        // Send invitation email
        $invite_url = add_query_arg([
            'action' => 'accept_invite',
            'token' => $token
        ], home_url());

        wp_mail(
            $email,
            'Invitation to Join League',
            sprintf(
                "You've been invited to join the league. Click here to accept: %s",
                esc_url($invite_url)
            ),
            ['Content-Type: text/html; charset=UTF-8']
        );

        add_settings_error(
            'league_invite',
            'invite_sent',
            'Invitation sent successfully.',
            'updated'
        );
    }

    public function enqueue_admin_assets($hook): void {
        if (!in_array($hook, ['league_player_page_invite-player', 'league_player_page_league-settings'])) {
            return;
        }

        wp_enqueue_style(
            'league-admin',
            LEAGUE_PLUGIN_URL . 'assets/css/admin.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'league-admin',
            LEAGUE_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            '1.0.0',
            true
        );
    }
} 