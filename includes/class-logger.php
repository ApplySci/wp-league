<?php
declare(strict_types=1);

class League_Logger {
    private const LOG_FILE = 'league-profiles.log';
    private static ?League_Logger $instance = null;
    private string $log_path;

    private function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_path = $upload_dir['basedir'] . '/' . self::LOG_FILE;
    }

    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function log(string $message, string $level = 'INFO', ?Throwable $exception = null): void {
        if (!$this->should_log()) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $user_id = get_current_user_id();
        
        $log_entry = sprintf(
            "[%s] [%s] [User:%d] %s",
            $timestamp,
            $level,
            $user_id,
            $message
        );

        if ($exception) {
            $log_entry .= sprintf(
                "\nException: %s\nStack trace:\n%s",
                $exception->getMessage(),
                $exception->getTraceAsString()
            );
        }

        $log_entry .= "\n";

        error_log($log_entry, 3, $this->log_path);
    }

    public function error(string $message, ?Throwable $exception = null): void {
        $this->log($message, 'ERROR', $exception);
    }

    public function warning(string $message): void {
        $this->log($message, 'WARNING');
    }

    public function info(string $message): void {
        $this->log($message, 'INFO');
    }

    private function should_log(): bool {
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    public function get_recent_logs(int $lines = 100): array {
        if (!file_exists($this->log_path)) {
            return [];
        }

        $logs = [];
        $file = new SplFileObject($this->log_path, 'r');
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key();

        $start_line = max(0, $total_lines - $lines);
        $file->seek($start_line);

        while (!$file->eof()) {
            $line = $file->fgets();
            if ($line !== false) {
                $logs[] = mb_convert_encoding($line, 'UTF-8', 'UTF-8');
            }
        }

        return $logs;
    }
} 