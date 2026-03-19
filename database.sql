-- Solar Maintenance Tracking Application
-- Complete Production Database Schema

-- Create Database
CREATE DATABASE IF NOT EXISTS solar_maintenance;
USE solar_maintenance;

-- ============================================
-- 1. USERS & AUTHENTICATION
-- ============================================

CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  phone VARCHAR(20),
  role ENUM('admin', 'employee') DEFAULT 'employee',
  status ENUM('active', 'inactive') DEFAULT 'active',
  department VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_role (role),
  INDEX idx_status (status)
);

-- ============================================
-- 2. WIFI ROUTERS (FOR TRIANGULATION)
-- ============================================

CREATE TABLE wifi_routers (
  id INT PRIMARY KEY AUTO_INCREMENT,
  router_name VARCHAR(100) NOT NULL,
  wifi_ssid VARCHAR(100) NOT NULL,
  latitude DECIMAL(10, 8) NOT NULL,
  longitude DECIMAL(11, 8) NOT NULL,
  height_meters DECIMAL(4, 2),
  location_name VARCHAR(100),
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ssid (wifi_ssid),
  INDEX idx_active (is_active)
);

-- ============================================
-- 3. USER SESSIONS & LOGIN TRACKING
-- ============================================

CREATE TABLE user_sessions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  login_time DATETIME NOT NULL,
  logout_time DATETIME,
  latitude DECIMAL(10, 8),
  longitude DECIMAL(11, 8),
  login_location_method VARCHAR(50),
  logout_location_method VARCHAR(50),
  status ENUM('active', 'closed') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_status (status),
  INDEX idx_login_time (login_time)
);

-- ============================================
-- 4. LOCATION TRACKING (REAL-TIME)
-- ============================================

CREATE TABLE location_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  latitude DECIMAL(10, 8) NOT NULL,
  longitude DECIMAL(11, 8) NOT NULL,
  accuracy INT,
  location_method ENUM('wifi_triangulation', 'ip_geolocation') NOT NULL,
  wifi_ssid VARCHAR(100),
  wifi_signal_strength INT,
  ip_address VARCHAR(45),
  city VARCHAR(100),
  region VARCHAR(100),
  country VARCHAR(100),
  location_name VARCHAR(100),
  timestamp DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_timestamp (timestamp),
  INDEX idx_user_timestamp (user_id, timestamp),
  INDEX idx_method (location_method)
);

-- ============================================
-- 5. WORK REPORTS (EMPLOYEE SUBMISSIONS)
-- ============================================

CREATE TABLE work_reports (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  report_date DATE NOT NULL,
  work_description TEXT NOT NULL,
  hours_worked DECIMAL(5, 2) NOT NULL,
  location_latitude DECIMAL(10, 8),
  location_longitude DECIMAL(11, 8),
  location_method VARCHAR(50),
  status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_date (report_date),
  INDEX idx_status (status),
  UNIQUE KEY unique_report (user_id, report_date)
);

-- ============================================
-- 6. WORK IMAGES (UPLOADED BY EMPLOYEES)
-- ============================================

CREATE TABLE work_images (
  id INT PRIMARY KEY AUTO_INCREMENT,
  report_id INT NOT NULL,
  user_id INT NOT NULL,
  image_filename VARCHAR(255) NOT NULL,
  image_path VARCHAR(500) NOT NULL,
  upload_time DATETIME NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (report_id) REFERENCES work_reports(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_report_id (report_id),
  INDEX idx_user_id (user_id)
);

-- ============================================
-- 7. ADMIN LOGS (FOR AUDIT)
-- ============================================

CREATE TABLE admin_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  admin_id INT NOT NULL,
  action VARCHAR(100),
  details TEXT,
  target_user_id INT,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_admin_id (admin_id),
  INDEX idx_created_at (created_at)
);

-- ============================================
-- 8. INSERT DEFAULT DATA
-- ============================================

-- Default Admin User
INSERT INTO users (name, email, password, phone, role, department) VALUES
('Admin User', 'admin@solar.com', SHA2('admin123', 256), '+1234567890', 'admin', 'Administration');

-- Default Employees
INSERT INTO users (name, email, password, phone, role, department) VALUES
('John Doe', 'john@solar.com', SHA2('john123', 256), '+1111111111', 'employee', 'Field Operations'),
('Sarah Smith', 'sarah@solar.com', SHA2('sarah123', 256), '+2222222222', 'employee', 'Field Operations'),
('Mike Johnson', 'mike@solar.com', SHA2('mike123', 256), '+3333333333', 'employee', 'Field Operations');

-- WiFi Routers (Company Locations)
INSERT INTO wifi_routers (router_name, wifi_ssid, latitude, longitude, height_meters, location_name) VALUES
('Router-Reception', 'SolarTech-Office', 11.341001, 77.717101, 1.5, 'Reception Desk'),
('Router-MainOffice', 'SolarTech-Office', 11.341201, 77.717301, 1.5, 'Main Office'),
('Router-Server', 'SolarTech-Office', 11.340801, 77.716901, 2.0, 'Server Room'),
('Router-Meeting', 'SolarTech-Office', 11.341401, 77.716801, 1.5, 'Meeting Room');

-- ============================================
-- 9. VIEWS FOR ANALYTICS
-- ============================================

-- Latest location for each user
CREATE VIEW latest_user_locations AS
SELECT 
  u.id,
  u.name,
  u.email,
  ll.latitude,
  ll.longitude,
  ll.accuracy,
  ll.location_method,
  ll.timestamp,
  TIMEDIFF(NOW(), ll.timestamp) as time_diff
FROM users u
LEFT JOIN location_logs ll ON u.id = ll.user_id 
  AND ll.timestamp = (
    SELECT MAX(timestamp) 
    FROM location_logs 
    WHERE user_id = u.id
  )
WHERE u.role = 'employee'
ORDER BY ll.timestamp DESC;

-- Employee work summary
CREATE VIEW employee_work_summary AS
SELECT 
  u.id,
  u.name,
  u.email,
  COUNT(wr.id) as total_reports,
  SUM(wr.hours_worked) as total_hours,
  SUM(CASE WHEN wr.status = 'approved' THEN 1 ELSE 0 END) as approved_reports,
  SUM(CASE WHEN wr.status = 'pending' THEN 1 ELSE 0 END) as pending_reports,
  MAX(wr.report_date) as last_report_date
FROM users u
LEFT JOIN work_reports wr ON u.id = wr.user_id
WHERE u.role = 'employee'
GROUP BY u.id, u.name, u.email;

-- ============================================
-- 10. INDEXES FOR PERFORMANCE
-- ============================================

CREATE INDEX idx_location_user_time ON location_logs(user_id, timestamp DESC);
CREATE INDEX idx_report_user_date ON work_reports(user_id, report_date DESC);
CREATE INDEX idx_image_report ON work_images(report_id);
CREATE INDEX idx_session_user ON user_sessions(user_id, login_time DESC);
