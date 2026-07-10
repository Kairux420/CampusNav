<?php
require_once 'config/db.php';
$stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE email = 'kylebradley05@gmail.com'");
$stmt->execute();
echo "Successfully updated Kyle Bradley to admin!\n";
?>
