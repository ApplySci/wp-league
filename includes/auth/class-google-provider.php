<?php
class League_Google_Provider extends League_OAuth_Provider {
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USER_INFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    public function __construct() {
        parent::__construct();
        $this->client_id = get_option('league_google_client_id', '');
        $this->client_secret = get_option('league_google_client_secret', '');
    }

    public function get_auth_url(): string {
        if (empty($this->client_id)) {
            error_log('Google client ID is empty!');
            throw new Exception('Google client ID not configured');
        }

        $state = base64_encode(json_encode([
            'p' => 'google',
            't' => '',
            'nonce' => wp_create_nonce('google_auth')
        ]));
        
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => 'email profile openid',
            'access_type' => 'online',
            'state' => $state
        ];
        
        $query = http_build_query($params);
        $url = self::AUTH_URL . '?' . $query;
        error_log('Final encoded URL: ' . $url);
        return $url;
    }

    public function get_token(string $code): ?array {
        error_log('Getting token with code: ' . substr($code, 0, 10) . '...');
        
        $response = wp_remote_post(self::TOKEN_URL, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'code' => $code,
                'redirect_uri' => $this->redirect_uri,
                'grant_type' => 'authorization_code'
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('Token request error: ' . $response->get_error_message());
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['access_token'])) {
            error_log('Token response error: ' . $body);
            return null;
        }
        
        return $data;
    }

    public function get_user_data(string $access_token): ?array {
        $response = wp_remote_get(self::USER_INFO_URL, [
            'headers' => [
                'Authorization' => "Bearer $access_token",
                'Accept' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            error_log('User data request error: ' . $response->get_error_message());
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($data['email'])) {
            error_log('User data response error: ' . wp_remote_retrieve_body($response));
            return null;
        }

        return $data;
    }
} 