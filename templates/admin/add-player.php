<?php
if (!defined('ABSPATH')) {
    exit;
}

$game_history = new League_Game_History();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php 
    settings_errors('league_invite');
    require_once LEAGUE_PLUGIN_DIR . 'templates/admin/database-status.php';
    ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="add-player-form">
        <?php wp_nonce_field('add_player', 'player_nonce'); ?>
        <input type="hidden" name="action" value="add_player">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="player_search"><?php esc_html_e('Player Name', 'league-profiles'); ?></label>
                </th>
                <td>
                    <select name="trr_id" id="player_search" class="player-select" required>
                        <option value=""><?php esc_html_e('Search for player...', 'league-profiles'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="player_email"><?php esc_html_e('Email Address', 'league-profiles'); ?></label>
                </th>
                <td>
                    <input type="email" 
                           name="player_email" 
                           id="player_email" 
                           class="regular-text" 
                           required>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Send Invitation', 'league-profiles')); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#player_search').select2({
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    action: 'search_unregistered_players',
                    search: params.term,
                    _ajax_nonce: '<?php echo wp_create_nonce("search_players"); ?>'
                };
            },
            processResults: function(data) {
                return {
                    results: data.map(function(player) {
                        return {
                            id: player.trr_id,
                            text: player.name
                        };
                    })
                };
            },
            cache: true
        },
        minimumInputLength: 2
    });
});
</script> 