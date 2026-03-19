<?php
/**
 * api/add_employee.php
 * Admin action to create a new employee account
 */

header('Content-Type: application/json');
session_start();

require_once '../config.php';

try {
    // 1. Check if user is admin
    $admin_id = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['user_role'] ?? null;
    
    // For demo/simplicity, we check role. In production, we'd verify against DB
    if (!$admin_id || $role !== 'admin') {
        throw new Exception('Unauthorized: Admin access required');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    $name = sanitize($data['name'] ?? '');
    $email = sanitize($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $phone = sanitize($data['phone'] ?? '');
    $department = sanitize($data['department'] ?? 'Field Operations');

    if (empty($name) || empty($email) || empty($password)) {
        throw new Exception('Name, Email and Password are required');
    }

    // 2. Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('Email already registered');
    }
    $stmt->close();

    // 3. Hash password (using SHA2-256 to match database.sql defaults)
    $hashed_pass = hash('sha256', $password);

    // 4. Insert User
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, role, department, status) VALUES (?, ?, ?, ?, 'employee', ?, 'active')");
    $stmt->bind_param("sssss", $name, $email, $hashed_pass, $phone, $department);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create employee account');
    }

    $new_user_id = $conn->insert_id;
    $stmt->close();

    // 5. Log Action
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, target_user_id) VALUES (?, 'create_user', ?, ?)");
    $details = "Created employee: $name ($email)";
    $stmt->bind_param("isi", $admin_id, $details, $new_user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Employee account created successfully',
        'user_id' => $new_user_id
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
