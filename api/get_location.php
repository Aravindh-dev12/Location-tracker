<?php
/**
 * api/get_location.php
 * Get user current location
 */

header('Content-Type: application/json');
session_start();

require_once '../config.php';

try {
    $user_id = $_GET['user_id'] ?? $_SESSION['user_id'] ?? null;
    
    if (!$user_id) {
        throw new Exception('User ID required');
    }
    
    // Get latest location
    $stmt = $conn->prepare(
        'SELECT id, latitude, longitude, accuracy, location_method, wifi_ssid, 
                wifi_signal_strength, ip_address, timestamp
         FROM location_logs 
         WHERE user_id = ? 
         ORDER BY timestamp DESC 
         LIMIT 1'
    );
    
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'No location found'
        ]);
        $stmt->close();
        exit;
    }
    
    $location = $result->fetch_assoc();
    $stmt->close();
    
    // Get user info
    $stmt = $conn->prepare(
        'SELECT name, email, role FROM users WHERE id = ?'
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user = $user_result->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => (int)$user_id,
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ],
        'location' => [
            'id' => (int)$location['id'],
            'latitude' => (float)$location['latitude'],
            'longitude' => (float)$location['longitude'],
            'accuracy' => (int)$location['accuracy'],
            'method' => $location['location_method'],
            'wifi_ssid' => $location['wifi_ssid'],
            'wifi_signal' => (int)$location['wifi_signal_strength'],
            'timestamp' => $location['timestamp'],
            'time_ago' => formatTime($location['timestamp'])
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
