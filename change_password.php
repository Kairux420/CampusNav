<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current_password, $user['password'])) {
        $error = 'Incorrect current password.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters.';
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $updateStmt->execute([$hashed, $_SESSION['user_id']]);
        $success = 'Password changed successfully.';
    }
}

require_once 'includes/header.php';
?>

<div class="home-dashboard">
    <div class="dashboard-header">
        <h1>Change Password</h1>
        <p>Update your account security.</p>
    </div>

    <div class="search-card" style="max-width: 450px; margin: 0 auto;">
        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 12px 16px; border-radius: 10px; font-size: 0.9rem; margin-bottom: 16px; border: 1px solid rgba(16, 185, 129, 0.2); font-weight: 500;">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password" required>

            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" required minlength="6">

            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">

            <button type="submit" name="change_password">Update Password</button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
