<?php
/**
 * Database Configuration for ClubMaster
 * This file handles database connection and initialization
 */

// Database configuration
$host = 'localhost';
$dbname = 'clubmaster_db';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

// Data Source Name (DSN)
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// PDO options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Create PDO instance
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Set character set
    $pdo->exec("SET NAMES utf8mb4");
    
} catch (PDOException $e) {
    // Log error and show user-friendly message
    error_log("Database Connection Error: " . $e->getMessage());
    
    // Check if database exists, if not create it
    try {
        $tempDsn = "mysql:host=$host;charset=$charset";
        $tempPdo = new PDO($tempDsn, $username, $password, $options);
        
        // Create database if it doesn't exist
        $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Reconnect with the created database
        $pdo = new PDO($dsn, $username, $password, $options);
        $pdo->exec("SET NAMES utf8mb4");
        
        // Create tables if they don't exist
        createTables($pdo);
        
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

/**
 * Create necessary tables for the club management system
 */
function createTables($pdo) {
    try {
        // Users table
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            phone VARCHAR(20),
            role ENUM('admin', 'manager', 'member') DEFAULT 'member',
            status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
            profile_image VARCHAR(255),
            course VARCHAR(100),
            year VARCHAR(20),
            department VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            email_verified BOOLEAN DEFAULT FALSE,
            verification_token VARCHAR(100),
            reset_token VARCHAR(100),
            reset_expires TIMESTAMP NULL,
            INDEX idx_email (email),
            INDEX idx_username (username),
            INDEX idx_role (role),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Add new columns to existing users table if they don't exist
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS course VARCHAR(100)");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS year VARCHAR(20)");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS department VARCHAR(100)");

        // Clubs table
        $pdo->exec("CREATE TABLE IF NOT EXISTS clubs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            category VARCHAR(50),
            location VARCHAR(100),
            contact_email VARCHAR(100),
            contact_phone VARCHAR(20),
            website VARCHAR(255),
            logo VARCHAR(255),
            banner_image VARCHAR(255),
            established_date DATE,
            member_count INT DEFAULT 0,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_name (name),
            INDEX idx_category (category),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Club members table
        $pdo->exec("CREATE TABLE IF NOT EXISTS club_members (
            id INT AUTO_INCREMENT PRIMARY KEY,
            club_id INT NOT NULL,
            user_id INT NOT NULL,
            role ENUM('president', 'vice_president', 'secretary', 'treasurer', 'member') DEFAULT 'member',
            status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
            joined_date DATE,
            membership_fee DECIMAL(10,2) DEFAULT 0.00,
            membership_expires DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_member (club_id, user_id),
            INDEX idx_club (club_id),
            INDEX idx_user (user_id),
            INDEX idx_role (role),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Events table
        $pdo->exec("CREATE TABLE IF NOT EXISTS events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            club_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            event_type ENUM('meeting', 'workshop', 'social', 'competition', 'fundraiser', 'other') DEFAULT 'meeting',
            start_datetime DATETIME NOT NULL,
            end_datetime DATETIME NOT NULL,
            location VARCHAR(200),
            max_attendees INT,
            registration_required BOOLEAN DEFAULT FALSE,
            registration_deadline DATETIME,
            fee DECIMAL(10,2) DEFAULT 0.00,
            status ENUM('draft', 'published', 'ongoing', 'completed', 'cancelled') DEFAULT 'draft',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_club (club_id),
            INDEX idx_start_date (start_datetime),
            INDEX idx_status (status),
            INDEX idx_type (event_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Event attendees table
        $pdo->exec("CREATE TABLE IF NOT EXISTS event_attendees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            user_id INT NOT NULL,
            status ENUM('registered', 'attended', 'no_show', 'cancelled') DEFAULT 'registered',
            registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            attendance_marked_at TIMESTAMP NULL,
            payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
            payment_amount DECIMAL(10,2) DEFAULT 0.00,
            notes TEXT,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_attendee (event_id, user_id),
            INDEX idx_event (event_id),
            INDEX idx_user (user_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Announcements table
        $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            club_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            content TEXT NOT NULL,
            type ENUM('general', 'urgent', 'event', 'reminder') DEFAULT 'general',
            priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
            target_audience ENUM('all_members', 'active_members', 'specific_role') DEFAULT 'all_members',
            target_role ENUM('president', 'vice_president', 'secretary', 'treasurer', 'member') NULL,
            expires_at DATETIME,
            status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_club (club_id),
            INDEX idx_status (status),
            INDEX idx_type (type),
            INDEX idx_priority (priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Finances table
        $pdo->exec("CREATE TABLE IF NOT EXISTS finances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            club_id INT NOT NULL,
            transaction_type ENUM('income', 'expense') NOT NULL,
            category VARCHAR(50) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            description TEXT,
            transaction_date DATE NOT NULL,
            payment_method ENUM('cash', 'bank_transfer', 'card', 'cheque', 'online') DEFAULT 'cash',
            reference_number VARCHAR(100),
            receipt_file VARCHAR(255),
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_by INT,
            approved_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_club (club_id),
            INDEX idx_type (transaction_type),
            INDEX idx_date (transaction_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Sessions table for session management
        $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
            session_id VARCHAR(128) PRIMARY KEY,
            user_id INT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Insert default admin user
        $adminExists = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        
        if ($adminExists == 0) {
            $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (username, email, password, first_name, last_name, role, status, email_verified) 
                          VALUES (?, ?, ?, ?, ?, 'admin', 'active', TRUE)")
                ->execute(['admin', 'admin@clubmaster.com', $hashedPassword, 'System', 'Administrator']);
        }

        // Insert sample data for demonstration
        insertSampleData($pdo);
        
        error_log("Database tables created successfully");
        
    } catch (PDOException $e) {
        error_log("Table creation error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Insert sample data for demonstration purposes
 */
function insertSampleData($pdo) {
    try {
        // Check if sample data already exists
        $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        
        if ($userCount <= 1) { // Only admin user exists
            // Sample users
            $users = [
                ['john_doe', 'john@example.com', 'John', 'Doe', 'member'],
                ['jane_smith', 'jane@example.com', 'Jane', 'Smith', 'manager'],
                ['mike_wilson', 'mike@example.com', 'Mike', 'Wilson', 'member']
            ];

            foreach ($users as $user) {
                $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (username, email, password, first_name, last_name, role, status, email_verified) 
                              VALUES (?, ?, ?, ?, ?, ?, 'active', TRUE)")
                    ->execute([$user[0], $user[1], $hashedPassword, $user[2], $user[3], $user[4]]);
            }

            // Sample club
            $pdo->prepare("INSERT INTO clubs (name, description, category, location, contact_email, member_count, created_by) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([
                    'Tech Innovation Club', 
                    'A community for technology enthusiasts and innovators',
                    'Technology',
                    'Main Campus',
                    'tech@clubmaster.com',
                    25,
                    1
                ]);

            // Add sample club members
            $clubId = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO club_members (club_id, user_id, role, status, joined_date) 
                          VALUES (?, ?, 'member', 'active', CURDATE())")
                ->execute([$clubId, 2]); // john_doe
            
            $pdo->prepare("INSERT INTO club_members (club_id, user_id, role, status, joined_date) 
                          VALUES (?, ?, 'president', 'active', CURDATE())")
                ->execute([$clubId, 3]); // jane_smith
            
            $pdo->prepare("INSERT INTO club_members (club_id, user_id, role, status, joined_date) 
                          VALUES (?, ?, 'member', 'active', CURDATE())")
                ->execute([$clubId, 4]); // mike_wilson

            error_log("Sample data inserted successfully");
        }
    } catch (PDOException $e) {
        error_log("Sample data insertion error: " . $e->getMessage());
    }
}

/**
 * Clean up expired sessions
 */
function cleanupExpiredSessions($pdo) {
    try {
        $pdo->exec("DELETE FROM sessions WHERE expires_at < NOW()");
    } catch (PDOException $e) {
        error_log("Session cleanup error: " . $e->getMessage());
    }
}

// Clean up expired sessions on each request (you might want to do this periodically instead)
if (isset($pdo)) {
    cleanupExpiredSessions($pdo);
}

/**
 * Helper function to get database statistics
 */
function getDatabaseStats($pdo) {
    try {
        $stats = [];
        $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['total_clubs'] = $pdo->query("SELECT COUNT(*) FROM clubs")->fetchColumn();
        $stats['total_events'] = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
        $stats['active_members'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
        return $stats;
    } catch (PDOException $e) {
        error_log("Database stats error: " . $e->getMessage());
        return [];
    }
}
?>