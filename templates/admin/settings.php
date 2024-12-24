<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="options.php">
        <?php
        settings_fields('league_settings');
        do_settings_sections('league_settings');
        ?>

        <h2>OAuth Settings</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Google Client ID</th>
                <td>
                    <input type="text" 
                           name="league_google_client_id" 
                           value="<?php echo esc_attr(get_option('league_google_client_id')); ?>" 
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">Google Client Secret</th>
                <td>
                    <input type="password" 
                           name="league_google_client_secret" 
                           value="<?php echo esc_attr(get_option('league_google_client_secret')); ?>" 
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">Apple Client ID</th>
                <td>
                    <input type="text" 
                           name="league_apple_client_id" 
                           value="<?php echo esc_attr(get_option('league_apple_client_id')); ?>" 
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">Apple Client Secret</th>
                <td>
                    <input type="password" 
                           name="league_apple_client_secret" 
                           value="<?php echo esc_attr(get_option('league_apple_client_secret')); ?>" 
                           class="regular-text">
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div> 