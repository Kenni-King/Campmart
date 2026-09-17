<?php
// API endpoint for tracking sponsored ad clicks
header('Content-Type: application/json');
require_once '../includes/constant.php';

$data = json_decode(file_get_contents('php://input'), true);
$ad_id = intval($data['ad_id'] ?? 0);

if ($ad_id <= 0) {
    echo json_encode(['success' => false]);
    exit;
}

try {
    // Update ad clicks count
    $query = "UPDATE sponsored_content SET total_clicks = total_clicks + 1, last_clicked_at = NOW() WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $ad_id);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
