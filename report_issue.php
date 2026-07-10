<?php
session_start();
require_once 'config/db.php';

// Check if user is logged in AND is not a guest
if (!isset($_SESSION['user_id']) || (isset($_SESSION['guest']) && $_SESSION['guest'] === true)) {
    header("Location: index.php");
    exit;
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $node_id = $_POST['node_id'] ?? null;
    $issue_type = $_POST['issue_type'] ?? '';
    $description = $_POST['description'] ?? '';
    $user_id = $_SESSION['user_id'];

    if ($node_id && $issue_type && $description) {
        try {
            $stmt = $pdo->prepare("INSERT INTO reports (user_id, node_id, issue_type, description, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$user_id, $node_id, $issue_type, $description]);
            $successMessage = "Your issue report has been submitted successfully.";
        } catch (PDOException $e) {
            $errorMessage = "Error submitting report: " . $e->getMessage();
        }
    } else {
        $errorMessage = "Please fill in all required fields.";
    }
}

// Fetch all nodes to populate the dropdown
$nodes = [];
try {
    $stmt = $pdo->query("
        SELECT n.node_id, n.room_code, n.node_name, f.floor_name, f.building 
        FROM nodes n 
        JOIN floors f ON n.floor_id = f.floor_id 
        ORDER BY f.building, f.floor_order, n.node_name
    ");
    $nodes = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handle error silently or log it
}
?>
<?php include 'includes/header.php'; ?>

<div class="container" style="max-width: 600px; margin: 40px auto; padding: 20px;">
    <h2 style="margin-bottom: 10px;">Report an Issue</h2>
    <p style="margin-bottom: 25px; color: var(--text-secondary);">Notice a locked door, blocked path, or incorrect map data? Let us know.</p>

    <?php if ($successMessage): ?>
        <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="report_issue.php" class="report-form glass-panel" style="padding: 25px; border-radius: 12px; border: 1px solid var(--glass-border); background: var(--glass-bg); backdrop-filter: blur(10px);">
        <div class="form-group" style="margin-bottom: 20px;">
            <label for="node_id" style="display: block; margin-bottom: 8px; font-weight: 500;">Location</label>
            <select name="node_id" id="node_id" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); font-family: inherit;">
                <option value="">-- Select a Location --</option>
                <?php foreach ($nodes as $node): ?>
                    <option value="<?php echo htmlspecialchars($node['node_id']); ?>">
                        <?php $displayName = ($node['room_code'] ? '['.$node['room_code'].'] ' : '') . ($node['node_name'] ?: 'Unnamed'); ?>
                        <?php echo htmlspecialchars($displayName . ' (' . $node['floor_name'] . ', ' . $node['building'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
            <label for="issue_type" style="display: block; margin-bottom: 8px; font-weight: 500;">Issue Type</label>
            <select name="issue_type" id="issue_type" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); font-family: inherit;">
                <option value="">-- Select Issue Type --</option>
                <option value="Door is locked">Door is locked</option>
                <option value="Path blocked by construction">Path blocked by construction</option>
                <option value="Map error (Incorrect Location)">Map error (Incorrect Location)</option>
                <option value="Facility broken/out of order">Facility broken/out of order</option>
                <option value="Other">Other</option>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 25px;">
            <label for="description" style="display: block; margin-bottom: 8px; font-weight: 500;">Description</label>
            <textarea name="description" id="description" rows="5" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); font-family: inherit; resize: vertical;" placeholder="Please describe the issue in detail..."></textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; background: var(--accent-primary); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.3s ease;">Submit Report</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
