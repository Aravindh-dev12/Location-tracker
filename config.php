<?php
/**
 * config.php - Configuration File
 * Database and app settings
 */

// ============================================
// DATABASE CONFIGURATION (DEVELOPMENT / LOCAL)
// ============================================

define('DB_HOST', 'localhost:3307');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'solar_maintenance');

/**
 * GOOGIEHOST DEPLOYMENT SETTINGS (Enable when hosting live)
 * 1. Create a DB in GoogieHost cPanel
 * 2. Paste your credentials below
 * 3. Comment out the LOCAL block above
 */
/*
define('DB_HOST', 'localhost');
define('DB_USER', 'your_googiehost_user');
define('DB_PASSWORD', 'your_googiehost_pass');
define('DB_NAME', 'your_googiehost_dbname');
*/

// ============================================
// APP CONFIGURATION
// ============================================

define('APP_NAME', 'Solar Maintenance Tracker');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'https://yoursite.googiehost.com'); // Update with your actual domain

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/uploads/work_images/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Location settings
define('OFFICE_CENTER_LAT', 11.3411);
define('OFFICE_CENTER_LNG', 77.7172);
define('OFFICE_RADIUS_METERS', 500); // Consider user at office if within 500m

// Session timeout (in minutes)
define('SESSION_TIMEOUT', 30);

// ============================================
// DATABASE CONNECTION
// ============================================

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Set charset
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Log activity
 */
function logActivity($conn, $admin_id, $action, $details, $target_user_id = null, $ip_address = null) {
    $ip = $ip_address ?? $_SERVER['REMOTE_ADDR'];
    
    $stmt = $conn->prepare(
        "INSERT INTO admin_logs (admin_id, action, details, target_user_id, ip_address) 
         VALUES (?, ?, ?, ?, ?)"
    );
    
    $stmt->bind_param('issss', $admin_id, $action, $details, $target_user_id, $ip);
    $stmt->execute();
    $stmt->close();
}

/**
 * Calculate distance between two coordinates
 */
function getDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371000; // Earth radius in meters
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $R * $c; // Distance in meters
}

/**
 * Check if user is at office
 */
function isUserAtOffice($lat, $lon) {
    $distance = getDistance(OFFICE_CENTER_LAT, OFFICE_CENTER_LNG, $lat, $lon);
    return $distance <= OFFICE_RADIUS_METERS;
}

/**
 * Format timestamp
 */
function formatTime($timestamp) {
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hrs ago';
    return date('M d, Y', $time);
}
?>
