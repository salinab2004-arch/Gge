<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ftp_system');

// Application configuration
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip', 'rar', 'mp4', 'mp3']);

// Create database connection
function getDBConnection() {
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Get current username
function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

// Get current user role
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? 'user';
}

// Check if current user is admin
function isAdmin() {
    return getCurrentUserRole() === 'admin';
}

// Redirect if not admin
function requireAdmin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
    if (!isAdmin()) {
        header('Location: index.php');
        exit();
    }
}

// Get user's storage limit (0 = unlimited)
function getUserStorageLimit($user_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT storage_limit FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['storage_limit'] : 1073741824; // Default 1GB
}

// Get user's current storage usage
function getUserStorageUsage($user_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT SUM(file_size) as total FROM files WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

// Check if user can upload file
function canUploadFile($user_id, $file_size) {
    $storage_limit = getUserStorageLimit($user_id);
    
    // 0 means unlimited storage
    if ($storage_limit === 0 || $storage_limit === '0') {
        return true;
    }
    
    $current_usage = getUserStorageUsage($user_id);
    return ($current_usage + $file_size) <= $storage_limit;
}

// Get storage limit as formatted string
function getStorageLimitFormatted($limit) {
    if ($limit === 0 || $limit === '0') {
        return 'Unlimited';
    }
    return formatBytes($limit);
}

// Format bytes to human readable
function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
?>
