<?php
require_once 'config/db.php';

try {
    // 1. Add room_code column if it doesn't exist
    $pdo->exec("ALTER TABLE nodes ADD COLUMN room_code VARCHAR(50) AFTER floor_id");
    echo "Added room_code column.\n";
} catch (PDOException $e) {
    echo "Column might already exist: " . $e->getMessage() . "\n";
}

try {
    // 2. Move existing node_name into room_code for rooms, and clear node_name
    $stmt = $pdo->prepare("UPDATE nodes SET room_code = node_name, node_name = '' WHERE node_type = 'room' AND room_code IS NULL");
    $stmt->execute();
    echo "Migrated " . $stmt->rowCount() . " nodes.\n";
} catch (PDOException $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}

echo "Done.\n";
?>
