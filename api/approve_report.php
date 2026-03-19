<?php
/**
 * api/approve_report.php
 * Admin approve/reject report
 */

header('Content-Type: application/json');
session_start();

require_once '../config.php';

try {
    $admin_id = $_SESSION['user_id'] ?? null;
    if ($_SESSION['user_role'] !== 'admin') {
        throw new Exception('Admin access required');
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $report_id = (int)($data['report_id'] ?? 0);
    $status = $data['status'] ?? 'pending';
    $comments = $data['comments'] ?? '';
    
    if (!in_array($status, ['approved', 'rejected', 'pending'])) {
        throw new Exception('Invalid status');
    }
    
    // Update report
    $stmt = $conn->prepare(
        'UPDATE work_reports SET status = ?, updated_at = NOW() WHERE id = ?'
    );
    $stmt->bind_param('si', $status, $report_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update report');
    }
    
    $stmt->close();
    
    // Log activity
    logActivity($conn, $admin_id, 'approve_report', 
        'Report #' . $report_id . ' marked as ' . $status, null);
    
    echo json_encode([
        'success' => true,
        'message' => 'Report ' . $status,
        'report_id' => $report_id,
        'status' => $status
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
