-- Database schema for FTP-like file management system
-- Create database
CREATE DATABASE IF NOT EXISTS ftp_system;
USE ftp_system;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    storage_limit BIGINT DEFAULT 1073741824 COMMENT 'Storage limit in bytes (default 1GB, 0 = unlimited)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Files table
CREATE TABLE IF NOT EXISTS files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    mime_type VARCHAR(100),
    directory VARCHAR(500) DEFAULT '/',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_directory (directory)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Directories table
CREATE TABLE IF NOT EXISTS directories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    directory_name VARCHAR(255) NOT NULL,
    directory_path VARCHAR(500) NOT NULL,
    parent_path VARCHAR(500) DEFAULT '/',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_dir (user_id, directory_path),
    INDEX idx_user_id (user_id),
    INDEX idx_parent_path (parent_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create default admin user (password: admin123)
-- Password is hashed using PHP password_hash()
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON DUPLICATE KEY UPDATE username=username;
