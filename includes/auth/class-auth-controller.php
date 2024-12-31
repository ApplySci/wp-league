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
            'callback' => [$this, 'handle_auth_redirect'],
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

    public function handle_callback(WP_REST_Request $request): WP_Error|WP_REST_Response {
        $code = $request->get_param('code');
        $state = $request->get_param('state');
        
        error_log('Callback received - Code: ' . $code . ', State: ' . $state);
        
        if (!$code) {
            return new WP_Error('invalid_request', 'Missing authorization code');
        }

        try {
            $decoded_state = json_decode(base64_decode($state), true);
            $provider_name = $decoded_state['p'] ?? '';
            
            if (!isset($this->providers[$provider_name])) {
                throw new Exception('Invalid provider');
            }

            $provider = $this->providers[$provider_name];
            $token_data = $provider->get_token($code);
            
            if (!$token_data || !isset($token_data['access_token'])) {
                throw new Exception('Failed to get access token');
            }

            $user_data = $provider->get_user_data($token_data['access_token']);
            if (!$user_data || !isset($user_data['email'])) {
                throw new Exception('Failed to get user data');
            }

            $user = get_user_by('email', $user_data['email']);
            if ($user) {
                wp_set_auth_cookie($user->ID, true);
                wp_redirect(home_url('/profile/'));
                exit;
            }

            wp_redirect(home_url('/register/'));
            exit;

        } catch (Exception $e) {
            error_log('OAuth error: ' . $e->getMessage());
            wp_redirect(home_url('/login/?error=' . urlencode($e->getMessage())));
            exit;
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
        error_log('Starting auth redirect...');
        
        $provider = sanitize_key($_REQUEST['provider'] ?? '');
        if (empty($provider)) {
            $current_url = $_SERVER['REQUEST_URI'] ?? '';
            
            // Check for Google domains in the URL
            if (strpos($current_url, 'google.com') !== false || 
                strpos($current_url, 'googleapis.com') !== false) {
                $provider = 'google';
            } elseif (preg_match('#/auth/([^/]+)#', $current_url, $matches)) {
                $provider = sanitize_key($matches[1]);
            }
        }
        
        $token = sanitize_text_field($_REQUEST['token'] ?? '');
        
        error_log('Provider: ' . $provider . ', Token: ' . $token);
        
        if (empty($provider) || !isset($this->providers[$provider])) {
            error_log('Invalid or missing provider: ' . $provider);
            wp_die('Invalid or missing provider');
        }

        try {
            $auth_url = $this->providers[$provider]->get_auth_url();
            $state = json_encode(['p' => $provider, 't' => $token]);
            $encoded_state = rtrim(strtr(base64_encode($state), '+/', '-_'), '=');
            $final_url = add_query_arg('state', $encoded_state, $auth_url);
            
            wp_redirect($final_url);
            exit;
        } catch (Exception $e) {
            error_log('Auth redirect error: ' . $e->getMessage());
            wp_die('Authentication error occurred');
        }
    }
} 