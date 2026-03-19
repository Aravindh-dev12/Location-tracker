<?php
/**
 * api/location_history.php
 * Get location history
 */

header('Content-Type: application/json');
session_start();

require_once '../config.php';

try {
    $user_id = $_GET['user_id'] ?? $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        throw new Exception('User ID required');
    }
    
    $days = (int)($_GET['days'] ?? 7);
    $limit = (int)($_GET['limit'] ?? 100);
    
    // Get location history
    $stmt = $conn->prepare(
        'SELECT id, latitude, longitude, accuracy, location_method, timestamp
         FROM location_logs 
         WHERE user_id = ? AND timestamp > DATE_SUB(NOW(), INTERVAL ? DAY)
         ORDER BY timestamp DESC
         LIMIT ?'
    );
    
    $stmt->bind_param('iii', $user_id, $days, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $locations = [];
    while ($row = $result->fetch_assoc()) {
        $locations[] = [
            'id' => (int)$row['id'],
            'latitude' => (float)$row['latitude'],
            'longitude' => (float)$row['longitude'],
            'accuracy' => (int)$row['accuracy'],
            'method' => $row['location_method'],
            'timestamp' => $row['timestamp']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'user_id' => (int)$user_id,
        'days' => $days,
        'count' => count($locations),
        'locations' => $locations
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
