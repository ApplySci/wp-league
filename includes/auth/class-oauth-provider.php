<?php
abstract class League_OAuth_Provider {
    protected $client_id;
    protected $client_secret;
    protected $redirect_uri;

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
        $stored_state = get_transient('league_oauth_state');
        delete_transient('league_oauth_state');
        return $stored_state === $state;
    }
} 