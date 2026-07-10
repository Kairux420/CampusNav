<?php
// google_callback.php
session_start();
require_once 'config/db.php';
require_once 'config/google.php';

// Validate State to prevent CSRF
if (!isset($_GET['state']) || !isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    die("Invalid OAuth state. Possible CSRF attack.");
}

// Handle errors from Google
if (isset($_GET['error'])) {
    die("Google Login Error: " . htmlspecialchars($_GET['error']));
}

// Ensure code is present
if (!isset($_GET['code'])) {
    header("Location: index.php");
    exit;
}

// Exchange authorization code for access token
$token_url = 'https://oauth2.googleapis.com/token';
$post_fields = [
    'code'          => $_GET['code'],
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code'
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
$response = curl_exec($ch);
curl_close($ch);

$token_data = json_decode($response, true);

if (!isset($token_data['access_token'])) {
    die("Failed to obtain access token from Google. Response: " . htmlspecialchars($response));
}

// Get user info from Google
$userinfo_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init($userinfo_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token_data['access_token']]);
$userinfo_response = curl_exec($ch);
curl_close($ch);

$google_user = json_decode($userinfo_response, true);

if (!isset($google_user['email'])) {
    die("Failed to fetch user email from Google.");
}

$email = $google_user['email'];
$name = $google_user['name'] ?? 'Google User';

// Check if user exists in database
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    // User exists, log them in
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['role']    = $user['role'];
} else {
    // New user, create account
    // Generate a secure random password since they logged in via Google
    $random_password = bin2hex(random_bytes(16));
    $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);
    
    $insertStmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'student')");
    $insertStmt->execute([$name, $email, $hashed_password]);
    
    $_SESSION['user_id'] = $pdo->lastInsertId();
    $_SESSION['name']    = $name;
    $_SESSION['role']    = 'student';
}

// Redirect to home dashboard
header("Location: home.php");
exit;
