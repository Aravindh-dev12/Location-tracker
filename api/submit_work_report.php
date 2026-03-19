<?php
/**
 * api/submit_work_report.php
 * Employee work report submission
 */

header('Content-Type: application/json');
session_start();

require_once '../config.php';

try {
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        throw new Exception('User not authenticated');
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $report_date = $data['report_date'] ?? date('Y-m-d');
    $description = sanitize($data['description'] ?? '');
    $hours = (float)($data['hours'] ?? 0);
    $latitude = (float)($data['latitude'] ?? 0);
    $longitude = (float)($data['longitude'] ?? 0);
    $method = $data['method'] ?? 'unknown';
    
    if (empty($description)) {
        throw new Exception('Work description required');
    }
    
    if ($hours <= 0) {
        throw new Exception('Hours must be greater than 0');
    }
    
    // Check if report already exists for this date
    $stmt = $conn->prepare(
        'SELECT id FROM work_reports WHERE user_id = ? AND report_date = ?'
    );
    $stmt->bind_param('is', $user_id, $report_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result->num_rows > 0) {
        // Update existing report
        $stmt = $conn->prepare(
            'UPDATE work_reports 
             SET work_description = ?, hours_worked = ?, location_latitude = ?, 
                 location_longitude = ?, location_method = ?, updated_at = NOW()
             WHERE user_id = ? AND report_date = ?'
        );
        
        $stmt->bind_param(
            'sdddsis',
            $description,
            $hours,
            $latitude,
            $longitude,
            $method,
            $user_id,
            $report_date
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update report');
        }
        
        // Get report ID
        $stmt2 = $conn->prepare(
            'SELECT id FROM work_reports WHERE user_id = ? AND report_date = ?'
        );
        $stmt2->bind_param('is', $user_id, $report_date);
        $stmt2->execute();
        $id_result = $stmt2->get_result();
        $row = $id_result->fetch_assoc();
        $report_id = $row['id'];
        $stmt2->close();
        
        $stmt->close();
        $action = 'updated';
    } else {
        // Create new report
        $stmt = $conn->prepare(
            'INSERT INTO work_reports 
            (user_id, report_date, work_description, hours_worked, location_latitude, 
             location_longitude, location_method, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, "pending", NOW())'
        );
        
        $stmt->bind_param(
            'issddds',
            $user_id,
            $report_date,
            $description,
            $hours,
            $latitude,
            $longitude,
            $method
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to submit report');
        }
        
        $report_id = $conn->insert_id;
        $stmt->close();
        $action = 'created';
    }
    
    echo json_encode([
        'success' => true,
        'report_id' => $report_id,
        'action' => $action,
        'message' => 'Work report ' . $action . ' successfully',
        'data' => [
            'report_date' => $report_date,
            'description' => $description,
            'hours' => $hours,
            'location' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'method' => $method
            ],
            'status' => 'pending',
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
