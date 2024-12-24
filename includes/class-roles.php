<?php
class League_Roles {
    private const PLAYER_CAPABILITIES = [
        'read' => true,
        'edit_league_player' => true,
        'edit_published_league_player' => true,
        'upload_files' => true
    ];

    private const ADMIN_CAPABILITIES = [
        'edit_league_player' => true,
        'edit_league_players' => true,
        'edit_others_league_players' => true,
        'publish_league_players' => true,
        'read_league_player' => true,
        'read_private_league_players' => true,
        'delete_league_player' => true,
        'delete_league_players' => true,
        'delete_others_league_players' => true,
        'delete_published_league_players' => true,
        'delete_private_league_players' => true,
        'edit_private_league_players' => true,
        'edit_published_league_players' => true
    ];

    public function __construct() {
        add_action('init', [$this, 'register_roles']);
        add_action('admin_init', [$this, 'add_admin_capabilities']);
        add_filter('map_meta_cap', [$this, 'map_player_capabilities'], 10, 4);
    }

    public function register_roles(): void {
        add_role('league_player', 'League Player', self::PLAYER_CAPABILITIES);
    }

    public function add_admin_capabilities(): void {
        $admin_role = get_role('administrator');
        foreach (self::ADMIN_CAPABILITIES as $cap => $grant) {
            $admin_role->add_cap($cap, $grant);
        }
    }

    public function map_player_capabilities($caps, $cap, $user_id, $args): array {
        $post_type = get_post_type_object('league_player');
        
        if (!$post_type) {
            return $caps;
        }

        // Handle player-specific capabilities
        if ('edit_league_player' === $cap) {
            $post_id = $args[0] ?? 0;
            $post = get_post($post_id);
            
            if (!$post) {
                return $caps;
            }

            // Allow players to edit their own profile
            $player_user_id = get_post_meta($post_id, 'player_user_id', true);
            if ($user_id == $player_user_id) {
                return ['read'];
            }

            // Admins can edit all profiles
            if (user_can($user_id, 'edit_others_league_players')) {
                return ['edit_others_league_players'];
            }

            return ['do_not_allow'];
        }

        return $caps;
    }

    public static function remove_roles(): void {
        remove_role('league_player');
        
        // Remove admin capabilities
        $admin_role = get_role('administrator');
        foreach (self::ADMIN_CAPABILITIES as $cap => $grant) {
            $admin_role->remove_cap($cap);
        }
    }
} 