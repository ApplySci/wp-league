<?php
declare(strict_types=1);

class League_Post_Types {
    public function __construct() {
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_meta']);
        add_filter('map_meta_cap', [$this, 'map_player_capabilities'], 10, 4);
    }

    public function register_post_types(): void {
        register_post_type('league_player', [
            'labels' => [
                'name' => __('Players', 'league-profiles'),
                'singular_name' => __('Player', 'league-profiles'),
            ],
            'public' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
            'rewrite' => ['slug' => 'players'],
            'capability_type' => ['league_player', 'league_players'],
            'map_meta_cap' => true,
            'show_in_admin_bar' => true,
            'menu_icon' => 'dashicons-groups',
            'menu_position' => 30
        ]);
    }

    public function register_meta(): void {
        $meta_fields = [
            'player_user_id' => ['type' => 'integer'],
            'last_login' => ['type' => 'string'],
            'player_rating' => ['type' => 'integer'],
            'trr_id' => ['type' => 'string'],
            'games_played' => ['type' => 'integer'],
            'country_code' => ['type' => 'string'],
            'club_id' => ['type' => 'integer']
        ];

        foreach ($meta_fields as $key => $field) {
            register_post_meta('league_player', $key, [
                'type' => $field['type'],
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => [$this, 'can_edit_player_meta'],
                'sanitize_callback' => function($meta_value) use ($field) {
                    return $this->sanitize_meta_value($meta_value, $field['type']);
                }
            ]);
        }
    }

    private function sanitize_meta_value(mixed $value, string $type): mixed {
        return match($type) {
            'string' => sanitize_text_field($value),
            'integer' => (int) $value,
            'number' => (float) $value,
            default => $value
        };
    }

    public function can_edit_player_meta(bool $allowed, string $meta_key, int $post_id, int $user_id): bool {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'league_player') {
            return false;
        }

        // Allow admins
        if (user_can($user_id, 'edit_others_league_players')) {
            return true;
        }

        // Allow players to edit their own profile
        $player_user_id = (int) get_post_meta($post_id, 'player_user_id', true);
        return $user_id === $player_user_id;
    }

    public function map_player_capabilities(array $caps, string $cap, int $user_id, array $args): array {
        $primitive_caps = [
            'edit_league_player' => 'edit_posts',
            'read_league_player' => 'read',
            'delete_league_player' => 'delete_posts'
        ];

        if (!isset($primitive_caps[$cap])) {
            return $caps;
        }

        $post_id = $args[0] ?? 0;
        if (!$post_id) {
            return $caps;
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'league_player') {
            return $caps;
        }

        // Check if user is editing their own profile
        $player_user_id = (int) get_post_meta($post_id, 'player_user_id', true);
        if ($user_id === $player_user_id) {
            return [$primitive_caps[$cap]];
        }

        // Otherwise, require admin capabilities
        return $caps;
    }
} 