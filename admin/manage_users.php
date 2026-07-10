<?php
session_start();
require_once '../config/db.php';

// Auth check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized: Admin access required.");
}

$message = '';
$messageType = '';

// Handle Role Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $targetUserId = (int)$_POST['user_id'];
    $newRole = $_POST['new_role'];
    
    // Prevent self-demotion
    if ($targetUserId === (int)$_SESSION['user_id']) {
        $message = "You cannot change your own role.";
        $messageType = "error";
    } elseif (in_array($newRole, ['student', 'admin'])) {
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE user_id = ?");
        if ($stmt->execute([$newRole, $targetUserId])) {
            $message = "User role updated successfully.";
            $messageType = "success";
        } else {
            $message = "Database error: Could not update user role.";
            $messageType = "error";
        }
    } else {
        $message = "Invalid role specified.";
        $messageType = "error";
    }
}

// Fetch all users
$stmt = $pdo->query("SELECT user_id, name, email, role, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container" style="max-width: 1200px; margin: 40px auto; padding: 20px;">
    
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px;">
        <div>
            <h1 style="font-size: 2rem; color: var(--text-main); margin: 0 0 10px 0;">User Management</h1>
            <p style="color: var(--text-muted); margin: 0;">View registered users and manage their access roles.</p>
        </div>
        <a href="index.php" class="btn-secondary" style="padding: 10px 20px; border-radius: 8px; text-decoration: none; border: 1px solid var(--border-color); color: var(--text-main);">← Back to Hub</a>
    </div>

    <?php if ($message): ?>
        <div style="padding: 15px; margin-bottom: 25px; border-radius: 8px; font-weight: 500; <?php echo $messageType === 'success' ? 'background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid #10b981;' : 'background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444;'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div style="background: var(--bg-card); padding: 0; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead style="background: rgba(0,0,0,0.02);">
                <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                    <th style="padding: 15px 20px; color: var(--text-muted); font-weight: 600;">ID</th>
                    <th style="padding: 15px 20px; color: var(--text-muted); font-weight: 600;">Name</th>
                    <th style="padding: 15px 20px; color: var(--text-muted); font-weight: 600;">Email</th>
                    <th style="padding: 15px 20px; color: var(--text-muted); font-weight: 600;">Role</th>
                    <th style="padding: 15px 20px; color: var(--text-muted); font-weight: 600;">Joined</th>
                    <th style="padding: 15px 20px; color: var(--text-muted); font-weight: 600; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 15px 20px; color: var(--text-muted);">#<?php echo (int)$user['user_id']; ?></td>
                        <td style="padding: 15px 20px; font-weight: 500; color: var(--text-main);"><?php echo htmlspecialchars($user['name']); ?></td>
                        <td style="padding: 15px 20px; color: var(--text-muted);"><?php echo htmlspecialchars($user['email']); ?></td>
                        <td style="padding: 15px 20px;">
                            <?php if ($user['role'] === 'admin'): ?>
                                <span style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 4px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: bold;">Admin</span>
                            <?php else: ?>
                                <span style="background: rgba(99, 102, 241, 0.1); color: #6366f1; padding: 4px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: bold;">Student</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px 20px; color: var(--text-muted); font-size: 0.9em;"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        <td style="padding: 15px 20px; text-align: right;">
                            <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                <form method="POST" style="display: inline-block; margin: 0;">
                                    <input type="hidden" name="update_role" value="1">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <input type="hidden" name="new_role" value="student">
                                        <button type="submit" style="background: transparent; border: 1px solid #ef4444; color: #ef4444; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; transition: background 0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.1)'" onmouseout="this.style.background='transparent'">Demote to Student</button>
                                    <?php else: ?>
                                        <input type="hidden" name="new_role" value="admin">
                                        <button type="submit" style="background: transparent; border: 1px solid #10b981; color: #10b981; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; transition: background 0.2s;" onmouseover="this.style.background='rgba(16,185,129,0.1)'" onmouseout="this.style.background='transparent'">Make Admin</button>
                                    <?php endif; ?>
                                </form>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-size: 0.85rem; font-style: italic;">(You)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>
