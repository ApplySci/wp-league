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

    public function get_auth_url(string $name = ''): string {
        if (empty($this->client_id)) {
            error_log('Google client ID is empty!');
            throw new Exception('Google client ID not configured');
        }

        $token = sanitize_text_field($_REQUEST['token'] ?? '');
        error_log('Google auth: Using token: ' . $token);

        $state = base64_encode(json_encode([
            'p' => 'google',
            'name' => $name,
            'token' => $token,
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
        
        return self::AUTH_URL . '?' . http_build_query($params);
    }

    public function get_token(string $code): ?array {
        return $this->make_request(self::TOKEN_URL, [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => http_build_query([
                'code' => $code,
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'redirect_uri' => $this->redirect_uri,
                'grant_type' => 'authorization_code'
            ])
        ]);
    }

    public function get_user_data(string $access_token): ?array {
        return $this->make_request(self::USER_INFO_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token
            ]
        ]);
    }
} 