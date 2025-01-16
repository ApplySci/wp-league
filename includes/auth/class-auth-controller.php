<?php
declare(strict_types=1);

class League_Auth_Controller {
    private array $providers;

    public function __construct() {
        error_log('Auth controller initialized');
        $this->providers = [
            'google' => new League_Google_Provider(),
            'apple' => new League_Apple_Provider()
        ];

        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('admin_post_league_auth', [$this, 'handle_auth_redirect']);
        add_action('admin_post_nopriv_league_auth', [$this, 'handle_auth_redirect']);
    }

    public function register_routes(): void {
        register_rest_route('league/v1', '/auth/callback', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'handle_callback'],
            'permission_callback' => '__return_true'
        ]);
    }

    public function handle_callback(WP_REST_Request $request): void {
        $code = $request->get_param('code');
        $state = $request->get_param('state');
        
        if (!$code) {
            error_log('OAuth callback: Missing code parameter');
            wp_redirect(home_url('/login/?error=missing_code'));
            exit;
        }

        try {
            // Decode state first
            $decoded_state = json_decode(base64_decode($state), true);
            error_log('OAuth callback: Decoded state: ' . print_r($decoded_state, true));
            
            if (!$decoded_state || 
                !isset($decoded_state['p']) || 
                !isset($this->providers[$decoded_state['p']]) ||
                !wp_verify_nonce($decoded_state['nonce'], $decoded_state['p'] . '_auth')) {
                error_log('OAuth callback: Invalid state parameter');
                throw new Exception('Invalid state parameter');
            }

            $provider = $this->providers[$decoded_state['p']];
            error_log('OAuth callback: Getting token...');
            $token_data = $provider->get_token($code);
            error_log('OAuth callback: Token response: ' . print_r($token_data, true));
            
            if (!$token_data || !isset($token_data['access_token'])) {
                error_log('OAuth callback: Failed to get access token');
                throw new Exception('Failed to get access token');
            }

            error_log('OAuth callback: Getting user data...');
            $user_info = $provider->get_user_data($token_data['access_token']);
            error_log('OAuth callback: User info: ' . print_r($user_info, true));
            
            if (!$user_info || !isset($user_info['email'])) {
                error_log('OAuth callback: Failed to get user data');
                throw new Exception('Failed to get user data');
            }

            // Get token from state
            $invite_token = $decoded_state['token'] ?? '';
            error_log('OAuth callback: Invite token: ' . $invite_token);
            
            if (!$invite_token) {
                error_log('OAuth callback: Missing invite token');
                throw new Exception('Missing invite token');
            }

            // Verify invite data
            $invite_data = get_transient("league_invite_$invite_token");
            error_log('OAuth callback: Invite data: ' . print_r($invite_data, true));
            
            if (!$invite_data) {
                error_log('OAuth callback: Invalid or expired invitation');
                throw new Exception('Invalid or expired invitation');
            }

            // Decode the stored invite data
            $invite_data = json_decode($invite_data, true);
            if (!$invite_data) {
                error_log('OAuth callback: Failed to decode invite data');
                throw new Exception('Invalid invitation data format');
            }

            // Verify email matches
            if ($invite_data['email'] !== $user_info['email']) {
                error_log('OAuth callback: Email mismatch - invite: ' . $invite_data['email'] . ', google: ' . $user_info['email']);
                throw new Exception('Email address does not match invitation');
            }

            // Get or create player profile
            global $wpdb;
            $profile_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id 
                 FROM {$wpdb->postmeta} 
                 WHERE meta_key = 'trr_id' 
                 AND meta_value = %s 
                 LIMIT 1",
                $invite_data['trr_id']
            ));

            if (!$profile_id) {
                // Create new player profile
                $profile_id = wp_insert_post([
                    'post_title' => $invite_data['name'],
                    'post_type' => 'league_player',
                    'post_status' => 'publish',
                    'meta_input' => [
                        'trr_id' => $invite_data['trr_id'],
                        'auth_email' => $user_info['email'],
                        'auth_provider' => $decoded_state['p']
                    ]
                ]);

                if (is_wp_error($profile_id)) {
                    throw new Exception('Failed to create player profile');
                }
            } else {
                // Update existing profile with auth details
                update_post_meta($profile_id, 'auth_email', $user_info['email']);
                update_post_meta($profile_id, 'auth_provider', $decoded_state['p']);
            }

            // Set secure cookie for auth
            $cookie_data = [
                'trr_id' => $invite_data['trr_id'],
                'timestamp' => time()
            ];
            
            setcookie(
                'league_auth',
                base64_encode(json_encode($cookie_data)),
                [
                    'expires' => time() + (30 * DAY_IN_SECONDS),
                    'path' => '/',
                    'domain' => parse_url(home_url(), PHP_URL_HOST),
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );

            // Update registration status in postmeta
            update_post_meta($profile_id, 'registration_status', 'complete');
            update_post_meta($profile_id, 'registration_date', current_time('mysql'));

            // Redirect to profile edit page
            $redirect_url = add_query_arg([
                'trr_id' => $invite_data['trr_id'],
                'auth_state' => wp_create_nonce('auth_complete')
            ], home_url('/player/edit/'));

            wp_safe_redirect($redirect_url);
            exit;

        } catch (Exception $e) {
            error_log('OAuth error: ' . $e->getMessage());
            error_log('OAuth error trace: ' . $e->getTraceAsString());
            wp_safe_redirect(home_url('/login/?error=' . urlencode($e->getMessage())));
            exit;
        }
    }

    public function handle_auth_redirect(): void {
        try {
            $name = sanitize_text_field($_REQUEST['name'] ?? '');
            $provider = sanitize_text_field($_REQUEST['provider'] ?? 'google');
            
            if (!isset($this->providers[$provider])) {
                throw new Exception('Invalid provider');
            }
            
            $auth_url = $this->providers[$provider]->get_auth_url($name);
            wp_redirect($auth_url);
            exit;
        } catch (Exception $e) {
            error_log('Auth redirect error: ' . $e->getMessage());
            wp_die('Authentication error occurred');
        }
    }
} 