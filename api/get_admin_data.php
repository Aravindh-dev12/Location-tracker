<?php
/**
 * api/get_admin_data.php
 * Get all employees and their latest locations for the admin map
 */

header('Content-Type: application/json');
session_start();

require_once '../config.php';

    try {
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            throw new Exception('Unauthorized: Admin access required');
        }

    // 1. Get all employees, their latest location, and their work summary
    $sql = "SELECT u.id, u.name, u.email, u.phone, u.department, u.status, u.created_at,
                   ll.latitude, ll.longitude, ll.accuracy, ll.location_method, ll.timestamp,
                   ews.total_reports, ews.total_hours, ews.approved_reports
            FROM users u
            LEFT JOIN (
                SELECT user_id, latitude, longitude, accuracy, location_method, timestamp
                FROM location_logs
                WHERE (user_id, timestamp) IN (
                    SELECT user_id, MAX(timestamp)
                    FROM location_logs
                    GROUP BY user_id
                )
            ) ll ON u.id = ll.user_id
            LEFT JOIN employee_work_summary ews ON u.id = ews.id
            WHERE u.role = 'employee'";
    
    $result = $conn->query($sql);
    $employees = [];
    
    while ($row = $result->fetch_assoc()) {
        $employees[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'department' => $row['department'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'total_reports' => (int)($row['total_reports'] ?? 0),
            'total_hours' => (float)($row['total_hours'] ?? 0),
            'approved_reports' => (int)($row['approved_reports'] ?? 0),
            'location' => $row['latitude'] ? [
                'lat' => (float)$row['latitude'],
                'lng' => (float)$row['longitude'],
                'accuracy' => (int)$row['accuracy'],
                'method' => $row['location_method'],
                'timestamp' => $row['timestamp'],
                'time_ago' => formatTime($row['timestamp']),
                'is_online' => (strtotime($row['timestamp']) > (time() - 300))
            ] : null
        ];
    }

    // 2. Get recent work reports with images
    $sql_reports = "SELECT wr.id, u.name as employee_name, wr.report_date, wr.work_description, wr.hours_worked, wr.status,
                           (SELECT file_path FROM work_images WHERE report_id = wr.id LIMIT 1) as image_path
                    FROM work_reports wr
                    JOIN users u ON wr.user_id = u.id
                    ORDER BY wr.created_at DESC
                    LIMIT 20";
    
    $result_reports = $conn->query($sql_reports);
    $reports = [];
    
    while ($row = $result_reports->fetch_assoc()) {
        $reports[] = $row;
    }

    // 3. Stats
    $pending_count = 0;
    $sql_pending = "SELECT COUNT(*) as count FROM work_reports WHERE status = 'pending'";
    if ($res = $conn->query($sql_pending)) {
        $pending_count = (int)$res->fetch_assoc()['count'];
    }

    echo json_encode([
        'success' => true,
        'employees' => $employees,
        'recent_reports' => $reports,
        'stats' => [
            'total_employees' => count($employees),
            'active_now' => count(array_filter($employees, fn($e) => $e['location'] && $e['location']['is_online'])),
            'pending_reports' => $pending_count
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'session' => $_SESSION
        ]
    ]);
}
?>
