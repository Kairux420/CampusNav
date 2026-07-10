<?php
require_once 'config/db.php';
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute(['kylebradley05@gmail.com']);
$user = $stmt->fetch();
if ($user) {
    echo 'user_found' . PHP_EOL;
    echo $user['password'] . PHP_EOL;
    var_dump(password_verify('password123', $user['password']));
    var_dump(password_verify('Password123', $user['password']));
    var_dump(password_verify('password', $user['password']));
} else {
    echo 'no_user';
}
