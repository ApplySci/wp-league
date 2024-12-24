<?php
declare(strict_types=1);

class League_Security {
    private const RATE_LIMIT_OPTION = 'league_rate_limits';
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 900; // 15 minutes

    public static function verify_request(): bool {
        if (!self::check_rate_limit()) {
            return false;
        }

        // Verify nonce if present
        if (!empty($_REQUEST['_wpnonce'])) {
            $nonce_action = $_REQUEST['action'] ?? 'league_default_action';
            if (!wp_verify_nonce($_REQUEST['_wpnonce'], $nonce_action)) {
                self::log_security_event('Invalid nonce');
                return false;
            }
        }

        // Check referrer for POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $referer = wp_get_referer();
            if (!$referer || parse_url($referer, PHP_URL_HOST) !== $_SERVER['HTTP_HOST']) {
                self::log_security_event('Invalid referrer');
                return false;
            }
        }

        return true;
    }

    public static function sanitize_input(mixed $input, string $type = 'text'): mixed {
        return match($type) {
            'text' => sanitize_text_field($input),
            'html' => wp_kses_post($input),
            'email' => sanitize_email($input),
            'url' => esc_url_raw($input),
            'int' => (int) $input,
            'float' => (float) $input,
            'array' => array_map(fn($item) => self::sanitize_input($item), (array) $input),
            default => sanitize_text_field($input)
        };
    }

    public static function encode_utf8(string $string): string {
        return mb_convert_encoding($string, 'UTF-8', 'UTF-8');
    }

    private static function check_rate_limit(): bool {
        $ip = self::get_client_ip();
        $limits = get_option(self::RATE_LIMIT_OPTION, []);
        
        if (isset($limits[$ip])) {
            $limit = $limits[$ip];
            if ($limit['count'] >= self::MAX_ATTEMPTS) {
                if (time() - $limit['timestamp'] < self::LOCKOUT_DURATION) {
                    self::log_security_event('Rate limit exceeded', $ip);
                    return false;
                }
                unset($limits[$ip]);
            }
        }

        $limits[$ip] = [
            'count' => ($limits[$ip]['count'] ?? 0) + 1,
            'timestamp' => time()
        ];

        update_option(self::RATE_LIMIT_OPTION, $limits);
        return true;
    }

    private static function get_client_ip(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return '';
        }
        return $ip;
    }

    private static function log_security_event(string $message, string $ip = ''): void {
        $logger = League_Logger::get_instance();
        $ip = $ip ?: self::get_client_ip();
        $logger->warning("Security Event: $message - IP: $ip");
    }

    public static function cleanup_rate_limits(): void {
        $limits = get_option(self::RATE_LIMIT_OPTION, []);
        $current_time = time();
        
        foreach ($limits as $ip => $data) {
            if ($current_time - $data['timestamp'] > self::LOCKOUT_DURATION) {
                unset($limits[$ip]);
            }
        }
        
        update_option(self::RATE_LIMIT_OPTION, $limits);
    }
} 