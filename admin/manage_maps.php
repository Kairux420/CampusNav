<?php
session_start();
require_once '../config/db.php';

// Auth check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized: Admin access required.");
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_map'])) {
    $floor_name = trim($_POST['floor_name']);
    $floor_order = (int)$_POST['floor_order'];
    $building = trim($_POST['building']);
    $wing = trim($_POST['wing']);
    
    if (isset($_FILES['map_image']) && $_FILES['map_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['map_image']['tmp_name'];
        $fileName = $_FILES['map_image']['name'];
        
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($fileExtension, $allowedExts)) {
            // Generate a safe unique name
            $safePrefix = preg_replace('/[^a-z0-9]/', '_', strtolower($building . '_' . $floor_name . '_' . $wing));
            $newFileName = $safePrefix . '_' . time() . '.' . $fileExtension;
            $uploadFileDir = '../assets/maps/';
            $dest_path = $uploadFileDir . $newFileName;
            
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $stmt = $pdo->prepare("INSERT INTO floors (floor_name, floor_order, building, map_image, wing) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$floor_name, $floor_order, $building, $newFileName, $wing])) {
                    $message = 'Successfully uploaded and registered the new floor map!';
                    $messageType = 'success';
                } else {
                    $message = 'Database error: Could not save the map record.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Error moving the uploaded file to the maps directory. Check folder permissions.';
                $messageType = 'error';
            }
        } else {
            $message = 'Upload failed. Allowed file types: ' . implode(',', $allowedExts);
            $messageType = 'error';
        }
    } else {
        $message = 'Please select a valid image file to upload.';
        $messageType = 'error';
    }
}

// Fetch existing floors
$stmt = $pdo->query("SELECT * FROM floors ORDER BY building, floor_order");
$floors = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container" style="max-width: 1200px; margin: 40px auto; padding: 20px;">
    
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px;">
        <div>
            <h1 style="font-size: 2rem; color: var(--text-main); margin: 0 0 10px 0;">Map Manager</h1>
            <p style="color: var(--text-muted); margin: 0;">Upload new map floor plans and manage existing ones.</p>
        </div>
        <a href="index.php" class="btn-secondary" style="padding: 10px 20px; border-radius: 8px; text-decoration: none; border: 1px solid var(--border-color); color: var(--text-main);">← Back to Hub</a>
    </div>

    <?php if ($message): ?>
        <div style="padding: 15px; margin-bottom: 25px; border-radius: 8px; font-weight: 500; <?php echo $messageType === 'success' ? 'background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid #10b981;' : 'background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444;'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
        
        <!-- Upload Form -->
        <div style="background: var(--bg-card); padding: 25px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); height: fit-content;">
            <h2 style="margin-top: 0; color: var(--text-main); font-size: 1.3rem; margin-bottom: 20px;">Add New Floor Map</h2>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="add_map" value="1">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-muted);">Building Name</label>
                    <input type="text" name="building" required placeholder="e.g. Main Building" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-muted);">Floor Name</label>
                    <input type="text" name="floor_name" required placeholder="e.g. Ground Floor, Level 1" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); box-sizing: border-box;">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-muted);">Wing (Optional)</label>
                    <input type="text" name="wing" placeholder="e.g. Left Wing, Central" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-muted);">Sort Order (Number)</label>
                    <input type="number" name="floor_order" required placeholder="e.g. 0, 1, 2" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-main); box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 25px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-muted);">Upload Map Image</label>
                    <input type="file" name="map_image" required accept=".jpg,.jpeg,.png,.webp" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px dashed var(--border-color); background: var(--bg-main); color: var(--text-main); box-sizing: border-box;">
                    <small style="color: var(--text-muted); display: block; margin-top: 5px;">Supported formats: PNG, JPG, WEBP.</small>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; padding: 12px; border-radius: 8px; border: none; font-weight: 600; font-size: 1rem; cursor: pointer;">Upload & Register Map</button>
            </form>
        </div>

        <!-- Current Maps List -->
        <div style="background: var(--bg-card); padding: 25px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
            <h2 style="margin-top: 0; color: var(--text-main); font-size: 1.3rem; margin-bottom: 20px;">Registered Floors</h2>
            
            <?php if (count($floors) > 0): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border-color);">
                                <th style="padding: 12px 10px; color: var(--text-muted); font-weight: 500;">Building</th>
                                <th style="padding: 12px 10px; color: var(--text-muted); font-weight: 500;">Floor (Wing)</th>
                                <th style="padding: 12px 10px; color: var(--text-muted); font-weight: 500;">Order</th>
                                <th style="padding: 12px 10px; color: var(--text-muted); font-weight: 500;">Image File</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($floors as $f): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 12px 10px; color: var(--text-main);"><?php echo htmlspecialchars($f['building']); ?></td>
                                    <td style="padding: 12px 10px; font-weight: 600; color: var(--text-main);">
                                        <?php echo htmlspecialchars($f['floor_name']); ?> 
                                        <?php if (!empty($f['wing'])) echo '<span style="font-weight: 400; color: var(--text-muted);">('.htmlspecialchars($f['wing']).')</span>'; ?>
                                    </td>
                                    <td style="padding: 12px 10px; color: var(--text-muted);"><?php echo htmlspecialchars($f['floor_order']); ?></td>
                                    <td style="padding: 12px 10px; color: var(--text-muted); font-size: 0.9rem; word-break: break-all;"><?php echo htmlspecialchars($f['map_image']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: var(--text-muted);">No maps registered yet.</p>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
