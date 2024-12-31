<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Verify auth state and cookie
$auth_state = sanitize_text_field($_GET['auth_state'] ?? '');
$auth_cookie = $_COOKIE['league_auth'] ?? '';

if (!$auth_cookie) {
    wp_die(__('Authentication required.', 'league-profiles'));
}

$auth_data = json_decode(base64_decode($auth_cookie), true);
$trr_id = $auth_data['trr_id'] ?? '';

if (!$trr_id) {
    wp_die(__('Invalid authentication.', 'league-profiles')); 
}

// Get player profile
global $wpdb;
$profile_id = $wpdb->get_var($wpdb->prepare(
    "SELECT post_id 
     FROM {$wpdb->postmeta} 
     WHERE meta_key = 'trr_id' 
     AND meta_value = %s 
     LIMIT 1",
    $trr_id
));

if (!$profile_id) {
    wp_die(__('Profile not found.', 'league-profiles'));
}

$post = get_post($profile_id);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_nonce'])) {
    if (!wp_verify_nonce($_POST['profile_nonce'], 'update_player_profile')) {
        wp_die(__('Security check failed.', 'league-profiles'));
    }

    try {
        $updates = [
            'ID' => $profile_id,
            'post_content' => wp_kses_post($_POST['profile_bio'])
        ];

        wp_update_post($updates);

        // Handle profile photo upload
        if (!empty($_FILES['profile_photo']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $attachment_id = media_handle_upload('profile_photo', $profile_id);
            if (!is_wp_error($attachment_id)) {
                set_post_thumbnail($profile_id, $attachment_id);
            }
        }

        wp_safe_redirect(home_url('/player/' . $trr_id));
        exit;

    } catch (Exception $e) {
        error_log('Profile update error: ' . $e->getMessage());
        wp_die(__('Error updating profile. Please try again.', 'league-profiles'));
    }
}

get_header();
?>

<div class="league-profile-edit">
    <h1><?php echo esc_html($post->post_title); ?></h1>

    <form method="post" action="" enctype="multipart/form-data">
        <?php wp_nonce_field('update_player_profile', 'profile_nonce'); ?>

        <div class="form-group">
            <label for="profile_bio"><?php esc_html_e('Biography', 'league-profiles'); ?></label>
            <textarea id="profile_bio" 
                      name="profile_bio" 
                      rows="5"><?php echo esc_textarea($post->post_content); ?></textarea>
        </div>

        <div class="form-group">
            <label for="profile_photo"><?php esc_html_e('Profile Photo', 'league-profiles'); ?></label>
            <input type="file" 
                   id="profile_photo" 
                   name="profile_photo" 
                   accept="image/*">
            <?php if (has_post_thumbnail($profile_id)): ?>
                <div class="current-photo">
                    <?php echo get_the_post_thumbnail($profile_id, 'thumbnail'); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <?php submit_button(__('Save Changes', 'league-profiles'), 'primary', 'submit'); ?>
            <a href="<?php echo esc_url(home_url('/player/' . $trr_id)); ?>" class="button">
                <?php esc_html_e('Cancel', 'league-profiles'); ?>
            </a>
        </div>
    </form>
</div>

<?php get_footer(); ?> 