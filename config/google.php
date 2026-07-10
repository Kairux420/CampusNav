<?php
// config/google.php

// Replace these with your actual Google Client ID and Secret
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');

// Dynamically determine the redirect URI so it works locally and on the live site
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
// If on localhost, it usually includes /campusnav. On live, it's at the root.
$base_dir = (strpos($host, 'localhost') !== false) ? '/campusnav' : ''; 

define('GOOGLE_REDIRECT_URI', $protocol . '://' . $host . $base_dir . '/google_callback.php');
