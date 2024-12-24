<?php
class League_Google_Provider extends League_OAuth_Provider {
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USER_INFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    public function __construct() {
        parent::__construct();
        $this->client_id = defined('LEAGUE_GOOGLE_CLIENT_ID') ? LEAGUE_GOOGLE_CLIENT_ID : '';
        $this->client_secret = defined('LEAGUE_GOOGLE_CLIENT_SECRET') ? LEAGUE_GOOGLE_CLIENT_SECRET : '';
    }

    public function get_auth_url(): string {
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => 'email profile',
            'state' => $this->generate_state(),
            'access_type' => 'online'
        ];
        return self::AUTH_URL . '?' . http_build_query($params);
    }

    public function get_token(string $code): ?array {
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
            return null;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
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