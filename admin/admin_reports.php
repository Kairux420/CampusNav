<?php
session_start();
// Use correct path to db.php since this is in /admin folder
require_once '../config/db.php';

// Auth check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized: Admin access required.");
}

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $report_id = (int)$_POST['report_id'];
    $new_status = $_POST['status'];
    if (in_array($new_status, ['pending', 'reviewed', 'resolved'])) {
        $stmt = $pdo->prepare("UPDATE reports SET status = ? WHERE report_id = ?");
        $stmt->execute([$new_status, $report_id]);
    }
    header("Location: admin_reports.php");
    exit;
}

// Fetch Reports
$stmt = $pdo->query("
    SELECT r.*, u.name as reporter_name, n.node_name, f.building, f.floor_name
    FROM reports r
    LEFT JOIN users u ON r.user_id = u.user_id
    LEFT JOIN nodes n ON r.node_id = n.node_id
    LEFT JOIN floors f ON n.floor_id = f.floor_id
    ORDER BY r.created_at DESC
");
$reports = $stmt->fetchAll();
?>
<?php include '../includes/header.php'; ?>

<div class="container" style="max-width: 1100px; margin: 40px auto; padding: 20px;">
    <h2 style="margin-bottom: 10px;">Manage Issue Reports</h2>
    <p style="margin-bottom: 25px; color: var(--text-secondary);">Review and resolve user-submitted issue reports below.</p>

    <div class="glass-panel" style="padding: 25px; border-radius: 12px; border: 1px solid var(--glass-border); background: var(--glass-bg); backdrop-filter: blur(10px);">
        <?php if (count($reports) > 0): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; min-width: 800px; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color);">
                            <th style="padding: 15px 10px; color: var(--text-secondary);">ID</th>
                            <th style="padding: 15px 10px; color: var(--text-secondary);">Reporter</th>
                            <th style="padding: 15px 10px; color: var(--text-secondary);">Location</th>
                            <th style="padding: 15px 10px; color: var(--text-secondary);">Issue Type</th>
                            <th style="padding: 15px 10px; color: var(--text-secondary);">Description</th>
                            <th style="padding: 15px 10px; color: var(--text-secondary);">Status</th>
                            <th style="padding: 15px 10px; color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                            <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.2s ease;">
                                <td style="padding: 15px 10px; font-weight: 600;">#<?php echo $report['report_id']; ?></td>
                                <td style="padding: 15px 10px;"><?php echo htmlspecialchars($report['reporter_name'] ?? 'Unknown'); ?></td>
                                <td style="padding: 15px 10px;"><?php echo htmlspecialchars($report['node_name'] . ' (' . $report['floor_name'] . ')'); ?></td>
                                <td style="padding: 15px 10px;">
                                    <span style="background: rgba(220, 53, 69, 0.1); color: #dc3545; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; font-weight: 600;">
                                        <?php echo htmlspecialchars($report['issue_type']); ?>
                                    </span>
                                </td>
                                <td style="padding: 15px 10px; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($report['description']); ?>">
                                    <?php echo htmlspecialchars($report['description']); ?>
                                </td>
                                <td style="padding: 15px 10px;">
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                        <select name="status" onchange="this.form.submit()" style="padding: 6px; border-radius: 5px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); font-family: inherit;">
                                            <option value="pending" <?php echo $report['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="reviewed" <?php echo $report['status'] === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                            <option value="resolved" <?php echo $report['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                </td>
                                <td style="padding: 15px 10px;">
                                    <?php 
                                        $alertTitle = urlencode("Issue: " . $report['issue_type']);
                                        $alertMsg = urlencode("Reported at " . $report['node_name'] . " - " . $report['description']);
                                    ?>
                                    <a href="/admin_alerts.php?title=<?php echo $alertTitle; ?>&msg=<?php echo $alertMsg; ?>" class="btn btn-sm" style="background: var(--accent-secondary); color: var(--ink); padding: 6px 12px; border-radius: 5px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-block;">Create Alert</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: var(--text-secondary); padding: 20px;">No reports found.</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
