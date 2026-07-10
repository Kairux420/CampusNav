<?php
require_once 'config/db.php';
require_once 'includes/auth_check.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized: Admin access required.");
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['publish'])) {
        $title = trim($_POST['title']);
        $message = trim($_POST['message']);
        
        if ($title && $message) {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, alert_type, title, message) VALUES (NULL, 'global', ?, ?)");
            $stmt->execute([$title, $message]);
        }
    } elseif (isset($_POST['delete'])) {
        $id = (int)$_POST['notification_id'];
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE notification_id = ?");
        $stmt->execute([$id]);
    }
    
    header('Location: admin_alerts.php');
    exit;
}

// Fetch all global alerts
$stmt = $pdo->query("SELECT * FROM notifications WHERE user_id IS NULL ORDER BY created_at DESC");
$alerts = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<section class="admin-page" style="max-width: 800px; margin: 0 auto; padding: 20px;">
    <h1 style="color: var(--text-main); margin-bottom: 20px;">Manage Campus Alerts</h1>

    <div class="profile-card" style="background: var(--bg-card); padding: 30px; border-radius: 12px; box-shadow: var(--shadow-md); margin-bottom: 30px; border: 1px solid var(--border-color);">
        <h2 style="color: var(--text-main); margin-bottom: 15px;">Publish New Alert</h2>
        <form method="POST" class="auth-form" style="display: flex; flex-direction: column; gap: 15px;">
            <div>
                <label style="display: block; margin-bottom: 5px; color: var(--text-muted);">Alert Title</label>
                <input type="text" name="title" required placeholder="e.g. Staircase C Blocked" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-main); color: var(--text-main);" value="<?php echo htmlspecialchars($_GET['title'] ?? ''); ?>">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; color: var(--text-muted);">Message</label>
                <textarea name="message" required placeholder="Detailed message about the alert..." style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-main); color: var(--text-main); min-height: 100px; font-family: inherit;"><?php echo htmlspecialchars($_GET['msg'] ?? ''); ?></textarea>
            </div>
            <button type="submit" name="publish" class="btn-primary" style="width: auto; align-self: flex-start;">Publish Alert</button>
        </form>
    </div>

    <div class="profile-card" style="background: var(--bg-card); padding: 30px; border-radius: 12px; box-shadow: var(--shadow-md); border: 1px solid var(--border-color);">
        <h2 style="color: var(--text-main); margin-bottom: 15px;">Active Alerts</h2>
        
        <?php if (count($alerts) > 0): ?>
            <ul style="list-style: none; padding: 0;">
                <?php foreach ($alerts as $alert): ?>
                    <li style="border-bottom: 1px solid var(--border-color); padding: 15px 0; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: var(--text-main); display: block; font-size: 1.1rem;"><?php echo htmlspecialchars($alert['title']); ?></strong>
                            <p style="color: var(--text-muted); font-size: 0.95rem; margin: 5px 0;"><?php echo htmlspecialchars($alert['message']); ?></p>
                            <small style="color: #aaa;"><?php echo date('F j, Y, g:i a', strtotime($alert['created_at'])); ?></small>
                        </div>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="notification_id" value="<?php echo (int)$alert['notification_id']; ?>">
                            <button type="submit" name="delete" style="background: #e53935; color: #fff; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-weight: bold;">Delete</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="color: var(--text-muted);">No active campus alerts.</p>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
