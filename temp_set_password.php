<?php
require_once 'config/db.php';
$hash = password_hash('password123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare('UPDATE users SET password = ? WHERE email = ?');
$stmt->execute([$hash, 'kylebradley05@gmail.com']);
echo $hash;
