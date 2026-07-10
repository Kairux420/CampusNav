<?php
// google_login.php
session_start();
require_once 'config/google.php';

// Generate a random state parameter to prevent CSRF
$_SESSION['oauth_state'] = bin2hex(random_bytes(16));

$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'email profile',
    'state'         => $_SESSION['oauth_state'],
    'access_type'   => 'online',
    'prompt'        => 'select_account'
];

$auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

header("Location: $auth_url");
exit;
