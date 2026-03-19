<?php
/**
 * api/get_work_reports.php
 * Get work reports (admin or employee)
 */

header('Content-Type: application/json');
session_start();

require_once '../config.php';

try {
    $user_id = $_SESSION['user_id'] ?? null;
    $user_role = $_SESSION['user_role'] ?? null;
    
    if (!$user_id) {
        throw new Exception('User not authenticated');
    }
    
    $filter = $_GET['filter'] ?? 'all';
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    
    // Build query based on role
    if ($user_role === 'admin') {
        // Admin sees all reports
        $query = 'SELECT wr.id, wr.user_id, wr.report_date, wr.work_description, 
                         wr.hours_worked, wr.location_latitude, wr.location_longitude,
                         wr.location_method, wr.status, wr.created_at, wr.updated_at,
                         u.name, u.email
                  FROM work_reports wr
                  JOIN users u ON wr.user_id = u.id';
    } else {
        // Employees see only their reports
        $query = 'SELECT wr.id, wr.user_id, wr.report_date, wr.work_description, 
                         wr.hours_worked, wr.location_latitude, wr.location_longitude,
                         wr.location_method, wr.status, wr.created_at, wr.updated_at,
                         u.name, u.email
                  FROM work_reports wr
                  JOIN users u ON wr.user_id = u.id
                  WHERE wr.user_id = ' . $user_id;
    }
    
    // Add filter
    if ($filter !== 'all') {
        $query .= ' AND wr.status = "' . sanitize($filter) . '"';
    }
    
    $query .= ' ORDER BY wr.report_date DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $reports = [];
    while ($row = $result->fetch_assoc()) {
        $reports[] = [
            'id' => (int)$row['id'],
            'user_id' => (int)$row['user_id'],
            'user_name' => $row['name'],
            'user_email' => $row['email'],
            'report_date' => $row['report_date'],
            'description' => $row['work_description'],
            'hours' => (float)$row['hours_worked'],
            'location' => [
                'latitude' => (float)$row['location_latitude'],
                'longitude' => (float)$row['location_longitude'],
                'method' => $row['location_method']
            ],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($reports),
        'reports' => $reports
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
