<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$post_id = get_the_ID();
$user_id = get_current_user_id();

if (!League_Capabilities::check_player_access($post_id, $user_id)) {
    wp_die(__('You do not have permission to edit this profile.', 'league-profiles'));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_nonce'])) {
    if (!wp_verify_nonce($_POST['profile_nonce'], 'update_player_profile')) {
        wp_die(__('Security check failed.', 'league-profiles'));
    }

    try {
        $updates = [
            'ID' => $post_id,
            'post_title' => sanitize_text_field($_POST['profile_name']),
            'post_content' => wp_kses_post($_POST['profile_bio'])
        ];

        wp_update_post($updates);

        // Handle profile photo upload
        if (!empty($_FILES['profile_photo']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $attachment_id = media_handle_upload('profile_photo', $post_id);
            if (!is_wp_error($attachment_id)) {
                set_post_thumbnail($post_id, $attachment_id);
            }
        }

        wp_safe_redirect(get_permalink($post_id));
        exit;

    } catch (Exception $e) {
        error_log('Profile update error: ' . $e->getMessage());
        wp_die(__('Error updating profile. Please try again.', 'league-profiles'));
    }
}

get_header();
$post = get_post();
?>

<div class="league-profile-edit">
    <h1><?php esc_html_e('Edit Profile', 'league-profiles'); ?></h1>

    <form method="post" action="" enctype="multipart/form-data" id="profile-edit-form">
        <?php wp_nonce_field('update_player_profile', 'profile_nonce'); ?>
        
        <div class="form-group">
            <label for="profile_name"><?php esc_html_e('Name', 'league-profiles'); ?></label>
            <input type="text" 
                   id="profile_name" 
                   name="profile_name" 
                   value="<?php echo esc_attr($post->post_title); ?>" 
                   required>
        </div>

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
            <?php if (has_post_thumbnail($post->ID)): ?>
                <div class="current-photo">
                    <?php echo get_the_post_thumbnail($post->ID, 'thumbnail'); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <?php submit_button(__('Save Changes', 'league-profiles'), 'primary', 'submit'); ?>
            <a href="<?php echo esc_url(get_permalink()); ?>" class="button">
                <?php esc_html_e('Cancel', 'league-profiles'); ?>
            </a>
        </div>
    </form>
</div>

<?php get_footer(); ?> 