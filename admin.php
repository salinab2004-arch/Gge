<?php
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();
$success = '';
$error = '';

// Handle user deletion
if (isset($_GET['delete_user'])) {
    $user_id = (int)$_GET['delete_user'];
    
    // Prevent admin from deleting themselves
    if ($user_id === getCurrentUserId()) {
        $error = 'You cannot delete your own account.';
    } else {
        try {
            // Get user's files to delete
            $stmt = $conn->prepare("SELECT file_path FROM files WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Delete physical files
            foreach ($files as $file) {
                if (file_exists($file['file_path'])) {
                    unlink($file['file_path']);
                }
            }
            
            // Delete user directory
            $user_dir = UPLOAD_DIR . $user_id . '/';
            if (file_exists($user_dir)) {
                deleteDirectory($user_dir);
            }
            
            // Delete user (cascade will handle files and directories in DB)
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $success = 'User deleted successfully.';
        } catch(PDOException $e) {
            $error = 'Failed to delete user.';
        }
    }
}

// Handle file deletion (admin can delete any file)
if (isset($_GET['delete_file'])) {
    $file_id = (int)$_GET['delete_file'];
    
    try {
        $stmt = $conn->prepare("SELECT file_path FROM files WHERE id = ?");
        $stmt->execute([$file_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file) {
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
            $stmt = $conn->prepare("DELETE FROM files WHERE id = ?");
            $stmt->execute([$file_id]);
            
            $success = 'File deleted successfully.';
        }
    } catch(PDOException $e) {
        $error = 'Failed to delete file.';
    }
}

// Handle role change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $user_id = (int)$_POST['user_id'];
    $new_role = $_POST['role'];
    
    if ($user_id === getCurrentUserId()) {
        $error = 'You cannot change your own role.';
    } elseif (in_array($new_role, ['user', 'admin'])) {
        try {
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$new_role, $user_id]);
            $success = 'User role updated successfully.';
        } catch(PDOException $e) {
            $error = 'Failed to update user role.';
        }
    }
}

// Handle storage limit change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_storage'])) {
    $user_id = (int)$_POST['user_id'];
    $storage_limit = $_POST['storage_limit'];
    
    // Validate input
    if ($storage_limit === 'unlimited') {
        $storage_limit_bytes = 0;
    } else {
        $storage_value = (float)$_POST['storage_value'];
        $storage_unit = $_POST['storage_unit'];
        
        // Convert to bytes
        switch ($storage_unit) {
            case 'GB':
                $storage_limit_bytes = $storage_value * 1073741824;
                break;
            case 'MB':
                $storage_limit_bytes = $storage_value * 1048576;
                break;
            default:
                $storage_limit_bytes = $storage_value * 1073741824;
        }
    }
    
    try {
        $stmt = $conn->prepare("UPDATE users SET storage_limit = ? WHERE id = ?");
        $stmt->execute([$storage_limit_bytes, $user_id]);
        $success = 'Storage limit updated successfully.';
    } catch(PDOException $e) {
        $error = 'Failed to update storage limit.';
    }
}

// Get all users
$stmt = $conn->query("SELECT id, username, email, role, storage_limit, created_at,
    (SELECT COUNT(*) FROM files WHERE user_id = users.id) as file_count,
    (SELECT SUM(file_size) FROM files WHERE user_id = users.id) as total_size
    FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all files
$stmt = $conn->query("SELECT f.*, u.username FROM files f 
    JOIN users u ON f.user_id = u.id 
    ORDER BY f.uploaded_at DESC LIMIT 100");
$all_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $conn->query("SELECT 
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM users WHERE role = 'admin') as total_admins,
    (SELECT COUNT(*) FROM files) as total_files,
    (SELECT SUM(file_size) FROM files) as total_storage,
    (SELECT COUNT(*) FROM directories) as total_directories");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Helper function to delete directory recursively
function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    
    return rmdir($dir);
}

function formatFileSize($bytes) {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - FTP File Manager</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard">
        <header class="dashboard-header">
            <div class="header-left">
                <h1>🛡️ Admin Panel</h1>
            </div>
            <div class="header-right">
                <a href="index.php" class="btn btn-secondary">My Files</a>
                <span class="user-info">👤 <?php echo htmlspecialchars(getCurrentUsername()); ?> (Admin)</span>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </header>

        <div class="dashboard-content">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>👥 Total Users</h3>
                    <p class="stat-number"><?php echo $stats['total_users']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>🛡️ Admins</h3>
                    <p class="stat-number"><?php echo $stats['total_admins']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>📄 Total Files</h3>
                    <p class="stat-number"><?php echo $stats['total_files']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>💾 Total Storage</h3>
                    <p class="stat-number"><?php echo formatFileSize($stats['total_storage'] ?? 0); ?></p>
                </div>
                <div class="stat-card">
                    <h3>📁 Directories</h3>
                    <p class="stat-number"><?php echo $stats['total_directories']; ?></p>
                </div>
            </div>

            <!-- User Management -->
            <div class="file-list">
                <h3>👥 User Management</h3>
                <table class="files-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Files</th>
                            <th>Storage Used</th>
                            <th>Storage Limit</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo $user['file_count']; ?></td>
                            <td><?php echo formatFileSize($user['total_size'] ?? 0); ?></td>
                            <td>
                                <?php echo getStorageLimitFormatted($user['storage_limit']); ?>
                                <button class="btn-link" onclick="document.getElementById('storage-modal-<?php echo $user['id']; ?>').style.display='block'">✏️</button>
                            </td>
                            <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php if ($user['id'] !== getCurrentUserId()): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <select name="role" onchange="this.form.submit()">
                                            <option value="">Change Role</option>
                                            <option value="user">User</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                        <input type="hidden" name="change_role" value="1">
                                    </form>
                                    <a href="?delete_user=<?php echo $user['id']; ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Delete this user and all their files?')">
                                        🗑️ Delete
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">You</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <!-- Storage Limit Modal -->
                        <tr>
                            <td colspan="9">
                                <div id="storage-modal-<?php echo $user['id']; ?>" class="modal" style="display: none;">
                                    <div class="modal-content">
                                        <span class="close" onclick="document.getElementById('storage-modal-<?php echo $user['id']; ?>').style.display='none'">&times;</span>
                                        <h3>Set Storage Limit for <?php echo htmlspecialchars($user['username']); ?></h3>
                                        <form method="POST">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <div class="form-group">
                                                <label>
                                                    <input type="radio" name="storage_limit" value="limited" checked>
                                                    Set Custom Limit:
                                                </label>
                                                <div style="display: flex; gap: 10px; margin-top: 10px;">
                                                    <input type="number" name="storage_value" value="1" min="0.1" step="0.1" style="width: 100px;">
                                                    <select name="storage_unit">
                                                        <option value="MB">MB</option>
                                                        <option value="GB" selected>GB</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label>
                                                    <input type="radio" name="storage_limit" value="unlimited">
                                                    Unlimited Storage
                                                </label>
                                            </div>
                                            <button type="submit" name="update_storage" class="btn btn-primary">Update Limit</button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Files -->
            <div class="file-list">
                <h3>📄 Recent Files (All Users)</h3>
                <table class="files-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Filename</th>
                            <th>Owner</th>
                            <th>Size</th>
                            <th>Type</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_files as $file): ?>
                        <tr>
                            <td><?php echo $file['id']; ?></td>
                            <td><?php echo htmlspecialchars($file['original_filename']); ?></td>
                            <td><?php echo htmlspecialchars($file['username']); ?></td>
                            <td><?php echo formatFileSize($file['file_size']); ?></td>
                            <td><?php echo htmlspecialchars($file['mime_type']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($file['uploaded_at'])); ?></td>
                            <td>
                                <a href="download.php?id=<?php echo $file['id']; ?>" class="btn btn-success btn-sm">
                                    ⬇️ Download
                                </a>
                                <a href="?delete_file=<?php echo $file['id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Delete this file?')">
                                    🗑️ Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
