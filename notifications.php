<?php
require_once 'config/db.php';
require_once 'includes/auth_check.php';

// Auto-migrate schema if needed
try {
    // Check if user_id is nullable or if alert_type exists
    $stmt = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'alert_type'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE notifications MODIFY user_id INT NULL");
        $pdo->exec("ALTER TABLE notifications ADD COLUMN alert_type VARCHAR(20) DEFAULT 'global'");
        $pdo->exec("ALTER TABLE notifications ADD COLUMN title VARCHAR(100) DEFAULT 'Campus Update'");
    }
} catch (PDOException $e) {
    // Ignore if already done or fails
}

// Fetch global alerts
$stmt = $pdo->query("SELECT * FROM notifications WHERE user_id IS NULL ORDER BY created_at DESC");
$alerts = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<section class="notifications-page" style="max-width: 800px; margin: 0 auto; padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="color: var(--text-main); margin: 0;">Campus Notifications</h1>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="admin_alerts.php" class="btn-primary" style="text-decoration: none; padding: 10px 15px; font-size: 0.9rem;">Manage Alerts</a>
        <?php endif; ?>
    </div>

    <div class="profile-card" style="background: var(--bg-card); padding: 30px; border-radius: 12px; box-shadow: var(--shadow-md); border: 1px solid var(--border-color);">
        <?php if (count($alerts) > 0): ?>
            <ul style="list-style: none; padding: 0;">
                <?php foreach ($alerts as $alert): ?>
                    <li style="border-bottom: 1px solid var(--border-color); padding: 20px 0;">
                        <div style="display: flex; align-items: flex-start; gap: 15px;">
                            <div style="font-size: 1.5rem; background: var(--bg-card-hover); padding: 10px; border-radius: 50%; color: var(--accent-secondary);">
                                🚨
                            </div>
                            <div>
                                <strong style="color: var(--text-main); display: block; font-size: 1.2rem; margin-bottom: 5px;"><?php echo htmlspecialchars($alert['title']); ?></strong>
                                <p style="color: var(--text-muted); line-height: 1.5; margin-bottom: 10px;"><?php echo nl2br(htmlspecialchars($alert['message'])); ?></p>
                                <small style="color: #aaa; font-weight: 500;"><?php echo date('F j, Y, g:i a', strtotime($alert['created_at'])); ?></small>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div style="text-align: center; padding: 40px 0;">
                <div style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;">🔔</div>
                <h3 style="color: var(--text-main); margin-bottom: 10px;">You're all caught up!</h3>
                <p style="color: var(--text-muted);">There are no active campus alerts at this time.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
