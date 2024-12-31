<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$token = sanitize_text_field($_GET['token'] ?? '');
$auth_state = sanitize_text_field($_GET['auth_state'] ?? '');

// Check if this is a post-authentication redirect
if ($auth_state && wp_verify_nonce($auth_state, 'auth_complete') && session_id()) {
    // Set the authentication cookie now that we're on the same domain
    $auth_token = $_SESSION['auth_token'] ?? '';
    if ($auth_token) {
        setcookie('auth_token', $auth_token, [
            'expires' => time() + WEEK_IN_SECONDS,
            'path' => COOKIEPATH,
            'domain' => COOKIE_DOMAIN,
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax'  // Changed from Strict to Lax
        ]);
    }
    
    $player_name = $_SESSION['player_name'] ?? '';
} else {
    // Normal invitation flow
    $invite_data = get_transient("league_invite_$token");
    if (!$invite_data) {
        wp_die(__('Invalid or expired invitation link.', 'league-profiles'));
    }
    $invite_data = json_decode($invite_data, true);
    $player_name = esc_html($invite_data['name'] ?? '');
}

?>

<div class="league-register-page">
    <h1><?php printf(
        esc_html__('Welcome to World Riichi League, %s', 'league-profiles'),
        $player_name
    ); ?></h1>
    
    <p class="register-intro">
        <?php esc_html_e('Please login', 'league-profiles'); ?>
    </p>

    <div class="oauth-buttons">
        <a href="<?php echo esc_url(add_query_arg([
            'action' => 'league_auth',
            'provider' => 'google',
            'token' => $token
        ], admin_url('admin-post.php'))); ?>" class="oauth-button google">
            <?php esc_html_e('Google', 'league-profiles'); ?>
        </a>

        <a href="<?php echo esc_url(add_query_arg([
            'action' => 'league_auth',
            'provider' => 'apple',
            'token' => $token
        ], admin_url('admin-post.php'))); ?>" class="oauth-button apple">
            <?php esc_html_e('Apple', 'league-profiles'); ?>
        </a>
    </div>
</div>

<style>
.league-register-page {
    max-width: 400px;
    margin: 4rem auto;
    text-align: center;
    padding: 2rem;
}

.oauth-buttons {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 2rem;
}

.oauth-button {
    display: inline-block;
    padding: 1rem;
    border-radius: 4px;
    text-decoration: none;
    color: white;
    font-weight: bold;
}

.oauth-button.google {
    background: #4285f4;
}

.oauth-button.google:hover {
    color: #000;
}

.oauth-button.apple {
    background: #000;
}

.oauth-button.apple:hover {
    color: #ff0;
}
</style> 