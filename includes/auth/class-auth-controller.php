<?php
declare(strict_types=1);

class League_Auth_Controller {
    private array $providers;

    public function __construct() {
        $this->providers = [
            'google' => new League_Google_Provider(),
            'apple' => new League_Apple_Provider()
        ];

        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
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
            'methods' => 'POST',
            'callback' => [$this, 'handle_callback'],
            'permission_callback' => '__return_true'
        ]);
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
        $code = $request->get_param('code');
        $state = $request->get_param('state');
        $provider_name = $request->get_param('provider');

        if (!isset($this->providers[$provider_name])) {
            return new WP_Error(
                'invalid_provider',
                'Invalid provider',
                ['status' => 400]
            );
        }

        $provider = $this->providers[$provider_name];
        
        if (!$provider->verify_state($state)) {
            return new WP_Error(
                'invalid_state',
                'Invalid state parameter',
                ['status' => 400]
            );
        }

        try {
            $token_data = $provider->get_token($code);
            if (!$token_data) {
                throw new Exception('Failed to get access token');
            }

            $user_data = $provider->get_user_data($token_data['access_token']);
            if (!$user_data) {
                throw new Exception('Failed to get user data');
            }

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
            return new WP_Error(
                'oauth_error',
                'Authentication failed',
                ['status' => 500]
            );
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
} 