<?php
if (!isset($_GET['edit']) || !League_Capabilities::check_player_access(get_the_ID(), get_current_user_id())) {
    wp_redirect(get_permalink());
    exit;
}

get_header();
$post = get_post();
?>

<div class="league-profile-edit">
    <h1>Edit Profile</h1>

    <form method="post" action="" enctype="multipart/form-data" id="profile-edit-form">
        <?php wp_nonce_field('update_player_profile', 'profile_nonce'); ?>
        
        <div class="form-group">
            <label for="profile_name">Name</label>
            <input type="text" 
                   id="profile_name" 
                   name="profile_name" 
                   value="<?php echo esc_attr($post->post_title); ?>" 
                   required>
        </div>

        <div class="form-group">
            <label for="profile_bio">Biography</label>
            <textarea id="profile_bio" 
                      name="profile_bio" 
                      rows="5"><?php echo esc_textarea($post->post_content); ?></textarea>
        </div>

        <div class="form-group">
            <label for="profile_photo">Profile Photo</label>
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
            <button type="submit" class="button button-primary">Save Changes</button>
            <a href="<?php echo esc_url(get_permalink()); ?>" class="button">Cancel</a>
        </div>
    </form>
</div>

<?php get_footer(); ?> 