<?php
if (!defined('ABSPATH')) {
    exit;
}

$game_history = new League_Game_History();
$unregistered_players = $game_history->get_unregistered_players();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php
    // Display admin notices
    if (isset($_GET['message'])) {
        $message = '';
        $type = 'success';
        
        switch ($_GET['message']) {
            case 'sent':
                $player_name = sanitize_text_field($_GET['name'] ?? '');
                $message = $player_name ? 
                    sprintf(__('Invitation email sent to %s successfully.', 'league-profiles'), $player_name) :
                    __('Invitation email sent successfully.', 'league-profiles');
                break;
            case 'error':
                $message = __('Failed to send invitation email. Please try again.', 'league-profiles');
                $type = 'error';
                break;
        }

        if ($message) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        }
    }

    require_once LEAGUE_PLUGIN_DIR . 'templates/admin/database-status.php';
    ?>

    <style>
        .notice {
            margin: 1rem 0;
        }
        .notice.updated {
            border-left-color: #46b450;
        }
    </style>

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
                        <option value=""><?php esc_html_e('Select player...', 'league-profiles'); ?></option>
                        <?php foreach ($unregistered_players as $player): ?>
                            <option value="<?php echo esc_attr($player['trr_id']); ?>" 
                                    data-name="<?php echo esc_attr($player['name']); ?>">
                                <?php echo esc_html($player['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="player_name" id="player_name">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="player_email"><?php esc_html_e('Email Address', 'league-profiles'); ?></label>
                </th>
                <td>
                    <input type="email" name="player_email" id="player_email" class="regular-text" required>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Send Invitation', 'league-profiles')); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#player_search').select2({
        width: '100%',
        placeholder: '<?php esc_attr_e('Search and select player...', 'league-profiles'); ?>'
    }).on('change', function() {
        var selectedOption = $(this).find('option:selected');
        $('#player_name').val(selectedOption.data('name'));
    });
});
</script> 