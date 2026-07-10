<?php
require_once 'config/db.php';

$stmt = $pdo->query("SELECT node_id, room_code, node_name FROM nodes WHERE node_type = 'room'");
$nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updateStmt = $pdo->prepare("UPDATE nodes SET category = ? WHERE node_id = ?");
$updated = 0;

foreach ($nodes as $node) {
    $name = strtolower($node['node_name'] ?? '');
    $code = strtolower($node['room_code'] ?? '');
    $combined = $name . ' ' . $code;
    
    $category = null;

    if (strpos($combined, 'pl ') !== false || strpos($combined, 'pw ') !== false || strpos($combined, 'pl,') !== false || strpos($combined, 'pw,') !== false || strpos($combined, 'pensyarah') !== false) {
        $category = 'Lecturer Office';
    } elseif (strpos($combined, 'makmal') !== false || strpos($combined, ' lab') !== false || strpos($code, 'mk') !== false) {
        $category = 'Computer Lab';
    } elseif (strpos($combined, 'tandas') !== false || strpos($combined, 'restroom') !== false || strpos($combined, 'toilet') !== false) {
        $category = 'Restroom';
    } elseif (strpos($combined, 'cafeteria') !== false || strpos($combined, 'cafe') !== false || strpos($combined, 'kantin') !== false) {
        $category = 'Cafeteria';
    } elseif (strpos($combined, 'pejabat') !== false || strpos($combined, 'pentadbiran') !== false || strpos($combined, 'dekan') !== false || strpos($combined, 'office') !== false) {
        $category = 'Office / Admin';
    } elseif (strpos($combined, 'mesyuarat') !== false || strpos($combined, 'meeting') !== false || preg_match('/\bmr\s*\d/', $combined)) {
        // "mr 1", "mr 2" often Meeting Room or Makmal Rangkaian. We'll group as Meeting Room / Lab
        $category = 'Meeting Room';
    } elseif (strpos($combined, 'classroom') !== false || strpos($combined, 'tutorial') !== false || preg_match('/\btr\s*\d/', $combined)) {
        $category = 'Classroom / Tutorial';
    } elseif (strpos($combined, 'stor') !== false || strpos($combined, 'fail') !== false) {
        $category = 'Storage';
    } elseif (strpos($combined, 'surau') !== false) {
        $category = 'Surau';
    } elseif (strpos($combined, 'pelaras') !== false || strpos($combined, 'penyelaras') !== false) {
        $category = 'Office / Admin';
    }

    if ($category !== null) {
        $updateStmt->execute([$category, $node['node_id']]);
        $updated++;
    }
}

echo "Successfully categorized $updated rooms.\n";
?>
