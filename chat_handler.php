<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/gemini_helper.php';

header('Content-Type: application/json');

function isCurrentLocationQuery($message)
{
    return preg_match('/\b(where am i|i am near|i\'m near|im near|near here|around here|nearby|i am at|i\'m at|at the)\b/i', $message);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

$message = trim($_POST['message'] ?? '');

if ($message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter a message.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT node_id, node_name, node_type FROM nodes WHERE node_type IN ('room', 'stairs', 'lift', 'entrance', 'junction') ORDER BY node_name ASC");
    $stmt->execute();
    $rooms = $stmt->fetchAll();
    $availableRooms = array_map(function ($room) {
        return $room['node_name'];
    }, $rooms);

    $result = askGemini($message, $availableRooms);

    if (!$result['success']) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Unable to process your request.']);
        exit;
    }

    $intent = $result['intent'] ?? 'info';
    $destination = isset($result['destination']) ? trim((string) $result['destination']) : null;

    if ($intent === 'navigate' && $destination !== null && $destination !== '') {
        $matchedNodeId = null;

        foreach ($rooms as $room) {
            if (strtolower(trim((string) $room['node_name'])) === strtolower($destination)) {
                $matchedNodeId = (int) $room['node_id'];
                break;
            }
        }

        if ($matchedNodeId !== null) {
            echo json_encode(['success' => true, 'redirect' => 'navigate.php?to=' . urlencode($matchedNodeId)]);
            exit;
        }
    }

    echo json_encode(['success' => true, 'reply' => $result['reply'] ?? 'I can help with directions or building information.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
