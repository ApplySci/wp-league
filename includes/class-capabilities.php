<?php
declare(strict_types=1);

class League_Capabilities {
    public static function register_capabilities(): void {
        // Register custom capabilities for the player post type
        $post_type = get_post_type_object('league_player');
        if (!$post_type) {
            return;
        }

        $post_type->cap = (object) [
            'edit_post' => 'edit_league_player',
            'edit_posts' => 'edit_league_players',
            'edit_others_posts' => 'edit_others_league_players',
            'publish_posts' => 'publish_league_players',
            'read_post' => 'read_league_player',
            'read_private_posts' => 'read_private_league_players',
            'delete_post' => 'delete_league_player',
            'delete_posts' => 'delete_league_players'
        ];
    }

    public static function check_player_access(int $post_id, int $user_id): bool {
        // Check for session-based auth first
        if (session_id() && isset($_SESSION['trr_id'])) {
            $post_trr_id = get_post_meta($post_id, 'trr_id', true);
            return $post_trr_id === $_SESSION['trr_id'];
        }

        // Fallback to WordPress permissions
        return current_user_can('edit_post', $post_id);
    }
} 