<?php
class League_Post_Types {
    public function __construct() {
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_meta']);
        add_filter('single_template', [$this, 'load_player_template']);
        add_filter('archive_template', [$this, 'load_players_archive_template']);
    }

    public function register_post_types(): void {
        register_post_type('league_player', [
            'labels' => [
                'name' => 'Players',
                'singular_name' => 'Player',
                'add_new' => 'Add New Player',
                'add_new_item' => 'Add New Player',
                'edit_item' => 'Edit Player',
                'view_item' => 'View Player',
                'search_items' => 'Search Players',
                'not_found' => 'No players found',
                'not_found_in_trash' => 'No players found in trash'
            ],
            'public' => true,
            'has_archive' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
            'menu_icon' => 'dashicons-groups',
            'rewrite' => ['slug' => 'players'],
            'capability_type' => ['league_player', 'league_players'],
            'map_meta_cap' => true
        ]);
    }

    public function register_meta(): void {
        register_post_meta('league_player', 'player_user_id', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [$this, 'can_edit_player_meta']
        ]);

        register_post_meta('league_player', 'last_login', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [$this, 'can_edit_player_meta']
        ]);

        // Add any additional custom fields here
        register_post_meta('league_player', 'player_rating', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => [$this, 'can_edit_player_meta']
        ]);
    }

    public function can_edit_player_meta($allowed, $meta_key, $post_id, $user_id): bool {
        $post = get_post($post_id);
        return current_user_can('edit_post', $post_id);
    }

    public function load_player_template($template): string {
        if (is_singular('league_player')) {
            $custom_template = LEAGUE_PLUGIN_DIR . 'templates/profile-page.php';
            return file_exists($custom_template) ? $custom_template : $template;
        }
        return $template;
    }

    public function load_players_archive_template($template): string {
        if (is_post_type_archive('league_player')) {
            $custom_template = LEAGUE_PLUGIN_DIR . 'templates/player-list.php';
            return file_exists($custom_template) ? $custom_template : $template;
        }
        return $template;
    }
} 