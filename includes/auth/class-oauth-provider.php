<?php
declare(strict_types=1);

abstract class League_OAuth_Provider {
    protected string $client_id;
    protected string $client_secret;
    protected string $redirect_uri;

    public function __construct() {
        $this->redirect_uri = site_url('wp-json/league/v1/auth/callback');
    }

    abstract public function get_auth_url(): string;
    abstract public function get_token(string $code): ?array;
    abstract public function get_user_data(string $access_token): ?array;

    protected function generate_state(): string {
        $state = bin2hex(random_bytes(16));
        set_transient('league_oauth_state', $state, 5 * MINUTE_IN_SECONDS);
        return $state;
    }

    protected function verify_state(string $state): bool {
        try {
            error_log('Verifying state: ' . $state);
            $decoded = json_decode(base64_decode(strtr($state, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($state)) % 4)), true);
            error_log('Decoded state in verify: ' . print_r($decoded, true));
            $result = is_array($decoded) && isset($decoded['p']);
            error_log('State verification result: ' . ($result ? 'true' : 'false'));
            return $result;
        } catch (Exception $e) {
            error_log('State verification error: ' . $e->getMessage());
            return false;
        }
    }

    protected function make_request(string $url, array $args = []): ?array {
        $response = wp_remote_request($url, array_merge([
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Encoding' => 'gzip',
                'Content-Type' => 'application/json; charset=utf-8'
            ]
        ], $args));

        if (is_wp_error($response)) {
            error_log('OAuth request failed: ' . $response->get_error_message());
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return null;
        }

        return json_decode($body, true);
    }
} 