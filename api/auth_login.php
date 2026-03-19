<?php
/**
 * api/auth_login.php
 * User authentication endpoint
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

session_start();

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['email']) || !isset($data['password'])) {
        throw new Exception('Email and password required');
    }
    
    $email = sanitize($data['email']);
    $password = $data['password'];
    
    // Get user from database
    $stmt = $conn->prepare('SELECT id, name, email, password, role, status FROM users WHERE email = ?');
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
        $stmt->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Check password
    if (hash('sha256', $password) !== $user['password']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
        exit;
    }
    
    // Check if active
    if ($user['status'] !== 'active') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'User account is inactive']);
        exit;
    }
    
    // Create session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();
    
    // Log login
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare(
        'INSERT INTO user_sessions (user_id, login_time, status) VALUES (?, NOW(), "active")'
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $session_id = $conn->insert_id;
    $stmt->close();
    
    // Get current location (if available)
    $stmt = $conn->prepare(
        'SELECT latitude, longitude, accuracy, location_method, timestamp 
         FROM location_logs WHERE user_id = ? ORDER BY timestamp DESC LIMIT 1'
    );
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $loc_result = $stmt->get_result();
    $location = $loc_result->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'session_id' => $session_id,
            'location' => $location
        ],
        'message' => 'Login successful'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
