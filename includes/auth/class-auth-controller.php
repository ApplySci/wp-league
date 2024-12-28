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
        error_log('Registering auth routes');
        
        register_rest_route('league/v1', '/auth/test', [
            'methods' => 'GET',
            'callback' => function() {
                error_log('Test endpoint called');
                return new WP_REST_Response(['status' => 'ok'], 200);
            },
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('league/v1', '/auth/(?P<provider>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_auth'],
            'permission_callback' => '__return_true',
            'args' => [
                'provider' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_key'
                ]
            ]
        ]);

        register_rest_route('league/v1', '/auth/callback', [
            'methods' => ['GET', 'POST'],
            'callback' => function($request) {
                error_log('Callback route hit');
                return $this->handle_callback($request);
            },
            'permission_callback' => '__return_true'
        ]);

        error_log('REST routes registered for league/v1/auth/*');
    }

    public function handle_auth(WP_REST_Request $request): WP_Error|WP_HTTP_Response {
        $provider_name = $request->get_param('provider');
        
        if (!isset($this->providers[$provider_name])) {
            return new WP_Error(
                'invalid_provider',
                'Invalid provider',
                ['status' => 400]
            );
        }

        $provider = $this->providers[$provider_name];
        return new WP_HTTP_Response([
            'redirect_url' => $provider->get_auth_url()
        ], 200);
    }

    public function handle_callback(WP_REST_Request $request): WP_Error|WP_REST_Response {
        error_log('Raw callback request: ' . print_r($request, true));
        $code = $request->get_param('code');
        $state = $request->get_param('state');
        
        try {
            error_log('OAuth callback - State: ' . $state);
            
            $decoded_state = json_decode(base64_decode(strtr($state, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($state)) % 4)), true);
            error_log('OAuth callback - Decoded state: ' . print_r($decoded_state, true));
            
            if (!$decoded_state) {
                error_log('OAuth callback - Invalid state format');
                throw new Exception('Invalid state format');
            }
            
            $provider_name = $decoded_state['p'] ?? '';
            $token = $decoded_state['t'] ?? '';
            
            error_log('OAuth callback - Provider: ' . $provider_name);
            
            if (!isset($this->providers[$provider_name])) {
                error_log('OAuth callback - Invalid provider: ' . $provider_name);
                return new WP_Error('invalid_provider', 'Invalid provider', ['status' => 400]);
            }

            $provider = $this->providers[$provider_name];
            
            if (!$provider->verify_state($state)) {
                error_log('OAuth callback - State verification failed');
                return new WP_Error('invalid_state', 'Invalid state parameter', ['status' => 400]);
            }

            $token_data = $provider->get_token($code);
            if (!$token_data) {
                throw new Exception('Failed to get access token');
            }

            $user_data = $provider->get_user_data($token_data['access_token']);
            if (!$user_data) {
                throw new Exception('Failed to get user data');
            }

            // If this is a registration flow
            if ($token) {
                $invite_data = get_transient("league_invite_$token");
                if (!$invite_data) {
                    throw new Exception('Invalid or expired invitation');
                }
                $invite_data = json_decode($invite_data, true);
                
                // Create the player profile
                $admin = new League_Admin();
                $post_id = $admin->create_player_profile($invite_data['trr_id']);
                
                // Create or update user
                $user_id = $this->create_or_update_user($user_data, $provider_name);
                if (is_wp_error($user_id)) {
                    return $user_id;
                }

                // Link user to profile
                update_post_meta($post_id, 'player_user_id', $user_id);
                
                // Delete the invitation token
                delete_transient("league_invite_$token");
                
                wp_set_auth_cookie($user_id, true);
                
                return new WP_REST_Response([
                    'success' => true,
                    'redirect_url' => get_edit_post_link($post_id, 'raw')
                ], 200);
            }

            // Regular login flow
            $user_id = $this->create_or_update_user($user_data, $provider_name);
            if (is_wp_error($user_id)) {
                return $user_id;
            }

            wp_set_auth_cookie($user_id, true);
            
            return new WP_REST_Response([
                'success' => true,
                'redirect_url' => home_url('/profile/')
            ], 200);

        } catch (Exception $e) {
            error_log('OAuth error: ' . $e->getMessage());
            return new WP_Error('oauth_error', 'Authentication failed', ['status' => 500]);
        }
    }

    private function create_or_update_user(array $user_data, string $provider): int|WP_Error {
        $email = sanitize_email($user_data['email']);
        if (!is_email($email)) {
            return new WP_Error('invalid_email', 'Invalid email address provided');
        }

        $user = get_user_by('email', $email);
        if ($user) {
            return $user->ID;
        }

        $username = sanitize_user(mb_substr($email, 0, strpos($email, '@')));
        $unique_username = $this->generate_unique_username($username);

        $user_id = wp_insert_user([
            'user_login' => $unique_username,
            'user_email' => $email,
            'user_pass' => wp_generate_password(),
            'display_name' => sanitize_text_field($user_data['name'] ?? $username),
            'role' => 'league_player'
        ]);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        update_user_meta($user_id, 'oauth_provider', $provider);
        return $user_id;
    }

    private function generate_unique_username(string $base_username): string {
        $username = $base_username;
        $counter = 1;

        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }

        return $username;
    }

    public function handle_auth_redirect(): void {
        $provider = sanitize_key($_GET['provider'] ?? '');
        $token = sanitize_text_field($_GET['token'] ?? '');
        
        if (!isset($this->providers[$provider])) {
            wp_die('Invalid provider');
        }

        $auth_url = $this->providers[$provider]->get_auth_url();
        $state = json_encode(['p' => $provider, 't' => $token]);
        $auth_url = add_query_arg('state', rtrim(strtr(base64_encode($state), '+/', '-_'), '='), $auth_url);
        
        wp_redirect($auth_url);
        exit;
    }
} 