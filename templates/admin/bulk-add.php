<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php 
    settings_errors('league_invite');
    require_once LEAGUE_PLUGIN_DIR . 'templates/admin/database-status.php';
    ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
        <?php wp_nonce_field('bulk_add_players', 'bulk_nonce'); ?>
        <input type="hidden" name="action" value="bulk_add_players">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="players_csv"><?php esc_html_e('CSV File', 'league-profiles'); ?></label>
                </th>
                <td>
                    <input type="file" 
                           name="players_csv" 
                           id="players_csv" 
                           accept=".csv"
                           required>
                    <p class="description">
                        <?php esc_html_e('Upload CSV file with columns: trr_id, email', 'league-profiles'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Upload and Send Invitations', 'league-profiles')); ?>
    </form>
</div> 