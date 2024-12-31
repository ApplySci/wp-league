<?php
declare(strict_types=1);

class League_Plugin {
    public function __construct() {
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('template_include', [$this, 'load_player_template']);
    }

    public function add_rewrite_rules(): void {
        add_rewrite_rule(
            '^register/?$',
            'index.php?league_register=1',
            'top'
        );
        add_rewrite_rule(
            '^player/edit/?$',
            'index.php?league_action=edit_profile',
            'top'
        );
        
        add_rewrite_tag('%league_register%', '([^&]+)');
        add_rewrite_tag('%league_action%', '([^&]+)');
    }

    public function load_player_template(string $template): string {
        if (get_query_var('league_register')) {
            $register_template = LEAGUE_PLUGIN_DIR . 'templates/register.php';
            if (file_exists($register_template)) {
                return $register_template;
            }
        }

        if (get_query_var('league_action') === 'edit_profile') {
            $edit_template = LEAGUE_PLUGIN_DIR . 'templates/profile-edit.php';
            if (file_exists($edit_template)) {
                return $edit_template;
            }
        }

        if (is_singular('league_player')) {
            $custom_template = LEAGUE_PLUGIN_DIR . 'templates/single-league_player.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        
        return $template;
    }
} 