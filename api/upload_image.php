<?php
/**
 * api/upload_image.php
 * Handle image uploads
 */

header('Content-Type: application/json');
session_start();

require_once '../config.php';

try {
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        throw new Exception('User not authenticated');
    }
    
    $report_id = (int)($_POST['report_id'] ?? 0);
    if (!$report_id) {
        throw new Exception('Report ID required');
    }
    
    // Check if report belongs to user (or user is admin)
    $stmt = $conn->prepare('SELECT user_id FROM work_reports WHERE id = ?');
    $stmt->bind_param('i', $report_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_assoc();
    $stmt->close();
    
    if (!$report || ($report['user_id'] !== $user_id && $_SESSION['user_role'] !== 'admin')) {
        throw new Exception('Access denied');
    }
    
    if (!isset($_FILES['image'])) {
        throw new Exception('No image file provided');
    }
    
    $file = $_FILES['image'];
    $tmp_name = $file['tmp_name'];
    $filename = $file['name'];
    $filesize = $file['size'];
    $fileerror = $file['error'];
    
    // Check for errors
    if ($fileerror !== 0) {
        throw new Exception('File upload error: ' . $fileerror);
    }
    
    // Check file size
    if ($filesize > MAX_FILE_SIZE) {
        throw new Exception('File too large. Maximum size: 5MB');
    }
    
    // Get file extension
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        throw new Exception('File type not allowed. Allowed: jpg, jpeg, png, gif');
    }
    
    // Create unique filename
    $new_filename = 'img_' . $report_id . '_' . time() . '.' . $ext;
    $filepath = UPLOAD_DIR . $new_filename;
    
    // Create directory if not exists
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    
    // Move file
    if (!move_uploaded_file($tmp_name, $filepath)) {
        throw new Exception('Failed to save image');
    }
    
    // Save to database
    $stmt = $conn->prepare(
        'INSERT INTO work_images (report_id, user_id, image_filename, image_path, upload_time, created_at)
         VALUES (?, ?, ?, ?, NOW(), NOW())'
    );
    
    if (!$stmt) {
        @unlink($filepath);
        throw new Exception('Database error');
    }
    
    $stmt->bind_param('iiss', $report_id, $user_id, $new_filename, $filepath);
    
    if (!$stmt->execute()) {
        @unlink($filepath);
        throw new Exception('Failed to save image info');
    }
    
    $image_id = $conn->insert_id;
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'image_id' => $image_id,
        'filename' => $new_filename,
        'filepath' => $filepath,
        'message' => 'Image uploaded successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'post' => $_POST,
            'files' => $_FILES,
            'session_user' => $_SESSION['user_id'] ?? 'not_set'
        ]
    ]);
}
?>
