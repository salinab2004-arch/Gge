<?php
// Advanced utility functions for FTP system

// Log activity
function logActivity($user_id, $action, $file_id = null, $details = null) {
    try {
        $conn = getDBConnection();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, file_id, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $file_id, $details, $ip_address]);
    } catch(PDOException $e) {
        // Silently fail to not disrupt user experience
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Generate share token
function generateShareToken() {
    return bin2hex(random_bytes(32));
}

// Get file icon based on mime type
function getFileIcon($mime_type) {
    $icon_map = [
        // Images
        'image/jpeg' => '🖼️',
        'image/png' => '🖼️',
        'image/gif' => '🖼️',
        'image/svg+xml' => '🖼️',
        'image/webp' => '🖼️',
        // Documents
        'application/pdf' => '📕',
        'application/msword' => '📄',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '📄',
        'application/vnd.ms-excel' => '📊',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '📊',
        'application/vnd.ms-powerpoint' => '📊',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => '📊',
        // Archives
        'application/zip' => '📦',
        'application/x-rar-compressed' => '📦',
        'application/x-7z-compressed' => '📦',
        'application/x-tar' => '📦',
        'application/gzip' => '📦',
        // Text
        'text/plain' => '📝',
        'text/html' => '🌐',
        'text/css' => '🎨',
        'text/javascript' => '⚙️',
        'application/json' => '📋',
        'application/xml' => '📋',
        // Video
        'video/mp4' => '🎬',
        'video/mpeg' => '🎬',
        'video/quicktime' => '🎬',
        'video/x-msvideo' => '🎬',
        // Audio
        'audio/mpeg' => '🎵',
        'audio/wav' => '🎵',
        'audio/ogg' => '🎵',
        'audio/mp4' => '🎵',
    ];
    
    return $icon_map[$mime_type] ?? '📄';
}

// Check if file can be previewed
function canPreviewFile($mime_type) {
    $previewable = [
        'image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp',
        'application/pdf',
        'text/plain', 'text/html', 'text/css', 'text/javascript',
        'application/json', 'application/xml'
    ];
    
    return in_array($mime_type, $previewable);
}

// Sanitize filename
function sanitizeFilename($filename) {
    // Remove any path components
    $filename = basename($filename);
    // Replace spaces with underscores
    $filename = str_replace(' ', '_', $filename);
    // Remove any characters that aren't alphanumeric, dash, underscore, or dot
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    return $filename;
}

// Copy file
function copyFile($source_file_id, $user_id, $new_directory = null) {
    try {
        $conn = getDBConnection();
        
        // Get source file info
        $stmt = $conn->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
        $stmt->execute([$source_file_id, $user_id]);
        $source_file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$source_file) {
            return false;
        }
        
        // Determine destination directory
        $dest_dir = $new_directory ?? $source_file['directory'];
        
        // Generate new filename
        $path_info = pathinfo($source_file['filename']);
        $new_filename = $path_info['filename'] . '_copy.' . $path_info['extension'];
        
        // Copy physical file
        $user_upload_dir = UPLOAD_DIR . $user_id . '/';
        $dest_dir_path = $user_upload_dir;
        if ($dest_dir !== '/') {
            $dest_dir_path .= trim($dest_dir, '/') . '/';
        }
        
        if (!file_exists($dest_dir_path)) {
            mkdir($dest_dir_path, 0755, true);
        }
        
        $new_file_path = $dest_dir_path . $new_filename;
        
        if (!copy($source_file['file_path'], $new_file_path)) {
            return false;
        }
        
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO files (user_id, filename, original_filename, file_path, file_size, mime_type, directory) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $new_filename,
            'Copy of ' . $source_file['original_filename'],
            $new_file_path,
            $source_file['file_size'],
            $source_file['mime_type'],
            $dest_dir
        ]);
        
        logActivity($user_id, 'FILE_COPY', $conn->lastInsertId(), "Copied file: {$source_file['original_filename']}");
        
        return true;
    } catch(Exception $e) {
        error_log("Copy file error: " . $e->getMessage());
        return false;
    }
}

// Move file
function moveFile($file_id, $user_id, $new_directory) {
    try {
        $conn = getDBConnection();
        
        // Get file info
        $stmt = $conn->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
        $stmt->execute([$file_id, $user_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            return false;
        }
        
        // Create new directory path
        $user_upload_dir = UPLOAD_DIR . $user_id . '/';
        $new_dir_path = $user_upload_dir;
        if ($new_directory !== '/') {
            $new_dir_path .= trim($new_directory, '/') . '/';
        }
        
        if (!file_exists($new_dir_path)) {
            mkdir($new_dir_path, 0755, true);
        }
        
        $new_file_path = $new_dir_path . $file['filename'];
        
        // Move physical file
        if (!rename($file['file_path'], $new_file_path)) {
            return false;
        }
        
        // Update database
        $stmt = $conn->prepare("UPDATE files SET file_path = ?, directory = ? WHERE id = ?");
        $stmt->execute([$new_file_path, $new_directory, $file_id]);
        
        logActivity($user_id, 'FILE_MOVE', $file_id, "Moved to: {$new_directory}");
        
        return true;
    } catch(Exception $e) {
        error_log("Move file error: " . $e->getMessage());
        return false;
    }
}

// Rename file
function renameFile($file_id, $user_id, $new_name) {
    try {
        $conn = getDBConnection();
        
        // Get file info
        $stmt = $conn->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
        $stmt->execute([$file_id, $user_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            return false;
        }
        
        $new_name = sanitizeFilename($new_name);
        
        // Keep the same extension
        $old_ext = pathinfo($file['filename'], PATHINFO_EXTENSION);
        $new_name_base = pathinfo($new_name, PATHINFO_FILENAME);
        $new_filename = $new_name_base . '.' . $old_ext;
        
        // Generate unique filename if needed
        $unique_filename = uniqid() . '_' . $new_filename;
        $dir_path = dirname($file['file_path']) . '/';
        $new_file_path = $dir_path . $unique_filename;
        
        // Rename physical file
        if (!rename($file['file_path'], $new_file_path)) {
            return false;
        }
        
        // Update database
        $stmt = $conn->prepare("UPDATE files SET filename = ?, original_filename = ?, file_path = ? WHERE id = ?");
        $stmt->execute([$unique_filename, $new_name, $new_file_path, $file_id]);
        
        logActivity($user_id, 'FILE_RENAME', $file_id, "Renamed to: {$new_name}");
        
        return true;
    } catch(Exception $e) {
        error_log("Rename file error: " . $e->getMessage());
        return false;
    }
}

// Search files
function searchFiles($user_id, $search_term, $directory = null) {
    try {
        $conn = getDBConnection();
        
        $search_pattern = '%' . $search_term . '%';
        
        if ($directory !== null) {
            $stmt = $conn->prepare("SELECT * FROM files WHERE user_id = ? AND original_filename LIKE ? AND directory = ? ORDER BY uploaded_at DESC");
            $stmt->execute([$user_id, $search_pattern, $directory]);
        } else {
            $stmt = $conn->prepare("SELECT * FROM files WHERE user_id = ? AND original_filename LIKE ? ORDER BY uploaded_at DESC");
            $stmt->execute([$user_id, $search_pattern]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        error_log("Search files error: " . $e->getMessage());
        return [];
    }
}

// Get recent activities
function getRecentActivities($user_id, $limit = 20) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT al.*, f.original_filename FROM activity_logs al 
            LEFT JOIN files f ON al.file_id = f.id 
            WHERE al.user_id = ? 
            ORDER BY al.created_at DESC 
            LIMIT ?");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        return [];
    }
}

// Get all activities for admin
function getAllActivities($limit = 100) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT al.*, u.username, f.original_filename 
            FROM activity_logs al 
            LEFT JOIN users u ON al.user_id = u.id
            LEFT JOIN files f ON al.file_id = f.id 
            ORDER BY al.created_at DESC 
            LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        return [];
    }
}
?>
