<?php
/**
 * api/auth_logout.php
 * User logout endpoint
 */

header('Content-Type: application/json');
session_start();

require_once '../config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $data['user_id'] ?? $_SESSION['user_id'] ?? null;
    
    if (!$user_id) {
        throw new Exception('User ID not found');
    }
    
    // Get current location before logout
    $location = null;
    if (isset($data['latitude']) && isset($data['longitude'])) {
        $latitude = (float)$data['latitude'];
        $longitude = (float)$data['longitude'];
        $accuracy = (int)($data['accuracy'] ?? 100);
        $method = $data['method'] ?? 'unknown';
        
        // Save final location
        $stmt = $conn->prepare(
            'INSERT INTO location_logs (user_id, latitude, longitude, accuracy, location_method, timestamp)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->bind_param('iddis', $user_id, $latitude, $longitude, $accuracy, $method);
        $stmt->execute();
        $stmt->close();
        
        $location = ['latitude' => $latitude, 'longitude' => $longitude];
    }
    
    // Update session
    $stmt = $conn->prepare(
        'UPDATE user_sessions SET logout_time = NOW(), status = "closed", 
         latitude = ?, longitude = ?, logout_location_method = ?
         WHERE user_id = ? AND status = "active" ORDER BY login_time DESC LIMIT 1'
    );
    
    $lat = $location ? $location['latitude'] : null;
    $lon = $location ? $location['longitude'] : null;
    $meth = $data['method'] ?? null;
    
    $stmt->bind_param('ddsi', $lat, $lon, $meth, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Clear session
    $_SESSION = [];
    session_destroy();
    
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully',
        'location' => $location
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
