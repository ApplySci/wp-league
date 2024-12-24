<?php
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

    public static function check_player_access($post_id, $user_id): bool {
        if (!$post_id || !$user_id) {
            return false;
        }

        // Admins always have access
        if (user_can($user_id, 'edit_others_league_players')) {
            return true;
        }

        // Check if the user is the owner of the profile
        $player_user_id = get_post_meta($post_id, 'player_user_id', true);
        return $user_id == $player_user_id;
    }
} 