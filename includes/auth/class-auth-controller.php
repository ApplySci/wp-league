<?php
class League_Auth_Controller {
    private $providers = [];

    public function __construct() {
        $this->providers = [
            'google' => new League_Google_Provider(),
            'apple' => new League_Apple_Provider()
        ];

        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('league/v1', '/auth/(?P<provider>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_auth_redirect'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('league/v1', '/auth/callback', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_callback'],
            'permission_callback' => '__return_true'
        ]);
    }

    public function handle_auth_redirect(WP_REST_Request $request) {
        $provider_name = $request->get_param('provider');
        if (!isset($this->providers[$provider_name])) {
            return new WP_Error('invalid_provider', 'Invalid provider');
        }

        $provider = $this->providers[$provider_name];
        wp_redirect($provider->get_auth_url());
        exit;
    }

    public function handle_callback(WP_REST_Request $request) {
        $code = $request->get_param('code');
        $state = $request->get_param('state');
        $provider_name = $request->get_param('provider');

        if (!isset($this->providers[$provider_name])) {
            return new WP_Error('invalid_provider', 'Invalid provider');
        }

        $provider = $this->providers[$provider_name];
        
        if (!$provider->verify_state($state)) {
            return new WP_Error('invalid_state', 'Invalid state parameter');
        }

        $token_data = $provider->get_token($code);
        if (!$token_data) {
            return new WP_Error('token_error', 'Failed to get access token');
        }

        $user_data = $provider->get_user_data($token_data['access_token']);
        if (!$user_data) {
            return new WP_Error('user_data_error', 'Failed to get user data');
        }

        // Create or update WordPress user
        $user_id = $this->create_or_update_user($user_data, $provider_name);
        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // Log the user in
        wp_set_auth_cookie($user_id, true);
        wp_redirect(home_url('/profile'));
        exit;
    }

    private function create_or_update_user(array $user_data, string $provider): int {
        $email = $user_data['email'];
        $user = get_user_by('email', $email);

        if (!$user) {
            $username = $this->generate_unique_username($email);
            $user_id = wp_create_user($username, wp_generate_password(), $email);
            
            if (is_wp_error($user_id)) {
                return $user_id;
            }

            $user = get_user_by('id', $user_id);
            $user->set_role('league_player');
        }

        update_user_meta($user->ID, 'league_oauth_provider', $provider);
        return $user->ID;
    }

    private function generate_unique_username(string $email): string {
        $username = strstr($email, '@', true);
        $base_username = $username;
        $counter = 1;

        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }

        return $username;
    }
} 