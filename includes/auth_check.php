<?php
// Include this at the top of any page that requires a logged-in user
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) && !isset($_SESSION['guest'])) {
    header('Location: /campusnav/index.php');
    exit;
}
