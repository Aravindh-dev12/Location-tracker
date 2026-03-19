<?php
/**
 * api/save_location.php
 * Save user location data
 */

header('Content-Type: application/json');
session_start();

require_once '../config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $user_id = $_SESSION['user_id'] ?? $data['user_id'] ?? null;
    if (!$user_id) {
        throw new Exception('User not authenticated');
    }
    
    $latitude = (float)($data['latitude'] ?? 0);
    $longitude = (float)($data['longitude'] ?? 0);
    $accuracy = (int)($data['accuracy'] ?? 100);
    $method = $data['method'] ?? 'unknown';
    $wifi_ssid = $data['wifi_ssid'] ?? null;
    $wifi_signal = (int)($data['wifi_signal'] ?? 0);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    // Validate coordinates
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        throw new Exception('Invalid coordinates');
    }
    
    // Insert location log
    $stmt = $conn->prepare(
        'INSERT INTO location_logs 
        (user_id, latitude, longitude, accuracy, location_method, wifi_ssid, wifi_signal_strength, ip_address, timestamp)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param(
        'iddissis',
        $user_id,
        $latitude,
        $longitude,
        $accuracy,
        $method,
        $wifi_ssid,
        $wifi_signal,
        $ip_address
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to save location');
    }
    
    $location_id = $conn->insert_id;
    $stmt->close();
    
    // Update session location
    $stmt = $conn->prepare(
        'UPDATE user_sessions SET latitude = ?, longitude = ? 
         WHERE user_id = ? AND status = "active" ORDER BY login_time DESC LIMIT 1'
    );
    $stmt->bind_param('ddi', $latitude, $longitude, $user_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'location_id' => $location_id,
        'message' => 'Location saved successfully',
        'data' => [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => $accuracy,
            'method' => $method,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
