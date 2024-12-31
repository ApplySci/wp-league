<?php
session_start();

$client_id = 'YOUR_CLIENT_ID';
$client_secret = 'YOUR_CLIENT_SECRET';
$redirect_uri = 'https://hg.energynumbers.info/wp-content/plugins/wp-league/auth.php';

if (!isset($_GET['code'])) {
    $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'response_type' => 'code',
        'scope' => 'email profile',
        'access_type' => 'online'
    ]);
    
    header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
    exit;
} else {
    // Exchange code for token
    $token_url = 'https://oauth2.googleapis.com/token';
    $token_data = [
        'code' => $_GET['code'],
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
    curl_setopt($ch, CURLOPT_POST, true);
    
    $token_response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    // Get user info with access token
    $user_info_url = 'https://www.googleapis.com/oauth2/v3/userinfo';
    $ch = curl_init($user_info_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token_response['access_token']
    ]);
    
    $user_info = json_decode(curl_exec($ch), true);
    curl_close($ch);

    // Store in session
    $_SESSION['user_email'] = $user_info['email'];
    $_SESSION['user_picture'] = $user_info['picture'];
    
    // Set secure cookie
    $token_data = [
        'email' => $user_info['email'],
        'timestamp' => time()
    ];
    
    $encrypted = openssl_encrypt(
        json_encode($token_data),
        'AES-256-CBC',
        'YOUR_SECRET_KEY',
        0,
        'YOUR_IV_HERE'
    );
    
    setcookie('auth_token', $encrypted, [
        'expires' => time() + (7 * 24 * 60 * 60),
        'path' => '/',
        'domain' => 'your-domain.com',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    header('Location: /');
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Authentication</title>
</head>
<body>
    <p>Processing authentication...</p>
</body>
</html> 