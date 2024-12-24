<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors('league_invite'); ?>

    <form method="post" action="">
        <?php wp_nonce_field('invite_player'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="invite_email">Email Address</label>
                </th>
                <td>
                    <input type="email" 
                           name="invite_email" 
                           id="invite_email" 
                           class="regular-text" 
                           required>
                </td>
            </tr>
        </table>

        <?php submit_button('Send Invitation'); ?>
    </form>
</div> 