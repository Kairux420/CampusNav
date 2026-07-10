<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';

$isGuest = isset($_SESSION['guest']) && $_SESSION['guest'] === true;
$user = null;

if (!$isGuest) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}

require_once 'includes/header.php';
?>

<section class="profile-page" style="max-width: 600px; margin: 0 auto; padding: 20px;">
    <h1 style="color: #1e3a5f; margin-bottom: 20px;">Your Profile</h1>

    <?php if ($isGuest): ?>
        <div class="profile-card" style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); text-align: center;">
            <div style="font-size: 3rem; margin-bottom: 10px;">👤</div>
            <h2 style="color: #1e3a5f; margin-bottom: 10px;">Guest User</h2>
            <p style="color: #666; margin-bottom: 20px;">You are currently browsing the map as a guest.</p>
            <a href="logout.php" class="btn-primary" style="display: inline-block; text-decoration: none;">Create an Account / Log In</a>
        </div>
    <?php elseif ($user): ?>
        <div class="profile-card" style="background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
            <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
                <div style="font-size: 3rem; background: #e8f1fb; width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; border-radius: 50%;">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <h2 style="color: #1e3a5f; margin-bottom: 5px;"><?php echo htmlspecialchars($user['name']); ?></h2>
                    <span style="background: #1e3a5f; color: #fff; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase;">
                        <?php echo htmlspecialchars($user['role']); ?>
                    </span>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #888; font-size: 0.9rem; margin-bottom: 5px;">Email Address</label>
                <div style="font-size: 1.1rem; color: #333;"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>

            <div style="margin-bottom: 30px;">
                <label style="display: block; color: #888; font-size: 0.9rem; margin-bottom: 5px;">Account Created</label>
                <div style="font-size: 1.1rem; color: #333;"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 20px;">
                <a href="change_password.php" style="color: var(--accent-primary); text-decoration: none; font-weight: bold;">Change Password</a>
                <a href="logout.php" style="color: #ef4444; text-decoration: none; font-weight: bold;">Sign Out</a>
            </div>
        </div>
    <?php else: ?>
        <p>User profile not found.</p>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>
