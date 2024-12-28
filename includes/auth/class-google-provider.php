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
            throw new Exception('Google client ID not configured');
        }

        error_log('Google OAuth redirect URI: ' . $this->redirect_uri);
        
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => 'email profile',
            'access_type' => 'online',
            'prompt' => 'select_account'
        ];
        return self::AUTH_URL . '?' . http_build_query($params);
    }

    public function get_token(string $code): ?array {
        error_log('Getting token with code: ' . $code);
        error_log('Using redirect URI: ' . $this->redirect_uri);
        
        $response = wp_remote_post(self::TOKEN_URL, [
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
        error_log('Token response: ' . $body);
        return json_decode($body, true);
    }

    public function get_user_data(string $access_token): ?array {
        $response = wp_remote_get(self::USER_INFO_URL, [
            'headers' => [
                'Authorization' => "Bearer $access_token"
            ]
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }
} 