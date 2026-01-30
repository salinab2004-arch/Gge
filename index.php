<?php
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$user_id = getCurrentUserId();
$username = getCurrentUsername();
$current_dir = $_GET['dir'] ?? '/';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $upload_error = '';
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_size = $file['size'];
        $original_filename = basename($file['name']);
        $file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        
        if ($file_size > MAX_FILE_SIZE) {
            $upload_error = 'File size exceeds maximum allowed size.';
        } elseif (!in_array($file_ext, ALLOWED_EXTENSIONS)) {
            $upload_error = 'File type not allowed.';
        } elseif (!canUploadFile($user_id, $file_size)) {
            $upload_error = 'Storage limit exceeded. Please delete some files or contact administrator.';
        } else {
            // Generate unique filename
            $unique_filename = uniqid() . '_' . $original_filename;
            $user_upload_dir = UPLOAD_DIR . $user_id . '/';
            
            // Create user directory if not exists
            if (!file_exists($user_upload_dir)) {
                mkdir($user_upload_dir, 0755, true);
            }
            
            // Handle subdirectories
            $target_dir = $user_upload_dir;
            if ($current_dir !== '/') {
                $target_dir .= trim($current_dir, '/') . '/';
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }
            }
            
            $target_file = $target_dir . $unique_filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                // Save to database
                $stmt = $conn->prepare("INSERT INTO files (user_id, filename, original_filename, file_path, file_size, mime_type, directory) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $unique_filename, $original_filename, $target_file, $file_size, $file['type'], $current_dir]);
                
                header('Location: index.php?dir=' . urlencode($current_dir));
                exit();
            } else {
                $upload_error = 'Failed to upload file.';
            }
        }
    } else {
        $upload_error = 'Upload error occurred.';
    }
}

// Handle file deletion
if (isset($_GET['delete'])) {
    $file_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("SELECT file_path FROM files WHERE id = ? AND user_id = ?");
    $stmt->execute([$file_id, $user_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($file) {
        if (file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
        $stmt = $conn->prepare("DELETE FROM files WHERE id = ? AND user_id = ?");
        $stmt->execute([$file_id, $user_id]);
    }
    
    header('Location: index.php?dir=' . urlencode($current_dir));
    exit();
}

// Handle directory creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_dir'])) {
    $dir_name = trim($_POST['dir_name']);
    if (!empty($dir_name)) {
        $new_dir_path = $current_dir === '/' ? '/' . $dir_name : $current_dir . '/' . $dir_name;
        
        // Create physical directory
        $physical_dir = UPLOAD_DIR . $user_id . '/' . trim($new_dir_path, '/');
        if (!file_exists($physical_dir)) {
            mkdir($physical_dir, 0755, true);
            
            // Save to database
            $stmt = $conn->prepare("INSERT INTO directories (user_id, directory_name, directory_path, parent_path) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $dir_name, $new_dir_path, $current_dir]);
        }
    }
    
    header('Location: index.php?dir=' . urlencode($current_dir));
    exit();
}

// Handle directory deletion
if (isset($_GET['deldir'])) {
    $dir_id = (int)$_GET['deldir'];
    $stmt = $conn->prepare("SELECT directory_path FROM directories WHERE id = ? AND user_id = ?");
    $stmt->execute([$dir_id, $user_id]);
    $directory = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($directory) {
        $physical_dir = UPLOAD_DIR . $user_id . '/' . trim($directory['directory_path'], '/');
        
        // Delete all files in directory
        $stmt = $conn->prepare("SELECT file_path FROM files WHERE user_id = ? AND directory LIKE ?");
        $stmt->execute([$user_id, $directory['directory_path'] . '%']);
        while ($file = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
        }
        
        // Delete files from database
        $stmt = $conn->prepare("DELETE FROM files WHERE user_id = ? AND directory LIKE ?");
        $stmt->execute([$user_id, $directory['directory_path'] . '%']);
        
        // Delete physical directory
        if (file_exists($physical_dir)) {
            rmdir($physical_dir);
        }
        
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM directories WHERE id = ? AND user_id = ?");
        $stmt->execute([$dir_id, $user_id]);
    }
    
    header('Location: index.php?dir=' . urlencode($current_dir));
    exit();
}

// Get files in current directory
$stmt = $conn->prepare("SELECT * FROM files WHERE user_id = ? AND directory = ? ORDER BY uploaded_at DESC");
$stmt->execute([$user_id, $current_dir]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get subdirectories
$stmt = $conn->prepare("SELECT * FROM directories WHERE user_id = ? AND parent_path = ? ORDER BY directory_name ASC");
$stmt->execute([$user_id, $current_dir]);
$directories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total storage used
$stmt = $conn->prepare("SELECT SUM(file_size) as total FROM files WHERE user_id = ?");
$stmt->execute([$user_id]);
$storage = $stmt->fetch(PDO::FETCH_ASSOC);
$total_storage = $storage['total'] ?? 0;

// Get storage limit
$storage_limit = getUserStorageLimit($user_id);
$storage_limit_formatted = getStorageLimitFormatted($storage_limit);

// Calculate percentage used
if ($storage_limit > 0) {
    $storage_percentage = ($total_storage / $storage_limit) * 100;
} else {
    $storage_percentage = 0;
}

// Format file size
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
    <title>FTP File Manager - Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard">
        <header class="dashboard-header">
            <div class="header-left">
                <h1>📁 FTP File Manager</h1>
            </div>
            <div class="header-right">
                <?php if (isAdmin()): ?>
                    <a href="admin.php" class="btn btn-secondary">🛡️ Admin Panel</a>
                <?php endif; ?>
                <span class="user-info">👤 <?php echo htmlspecialchars($username); ?></span>
                <span class="storage-info" title="Storage: <?php echo formatFileSize($total_storage); ?> / <?php echo $storage_limit_formatted; ?>">
                    💾 <?php echo formatFileSize($total_storage); ?> / <?php echo $storage_limit_formatted; ?>
                    <?php if ($storage_limit > 0): ?>
                        <span class="storage-bar">
                            <span class="storage-bar-fill" style="width: <?php echo min($storage_percentage, 100); ?>%"></span>
                        </span>
                    <?php endif; ?>
                </span>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </header>

        <div class="dashboard-content">
            <!-- Breadcrumb Navigation -->
            <div class="breadcrumb">
                <a href="index.php">🏠 Home</a>
                <?php
                if ($current_dir !== '/') {
                    $parts = explode('/', trim($current_dir, '/'));
                    $path = '';
                    foreach ($parts as $part) {
                        $path .= '/' . $part;
                        echo ' / <a href="index.php?dir=' . urlencode($path) . '">' . htmlspecialchars($part) . '</a>';
                    }
                }
                ?>
            </div>

            <!-- Action Buttons -->
            <div class="actions-bar">
                <button class="btn btn-primary" onclick="document.getElementById('uploadForm').style.display='block'">
                    ⬆️ Upload File
                </button>
                <button class="btn btn-secondary" onclick="document.getElementById('createDirForm').style.display='block'">
                    📁 Create Folder
                </button>
            </div>

            <!-- Upload Form -->
            <div id="uploadForm" class="modal" style="display: none;">
                <div class="modal-content">
                    <span class="close" onclick="document.getElementById('uploadForm').style.display='none'">&times;</span>
                    <h2>Upload File</h2>
                    <?php if (isset($upload_error)): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($upload_error); ?></div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="file">Choose File</label>
                            <input type="file" id="file" name="file" required>
                            <small>Max size: <?php echo formatFileSize(MAX_FILE_SIZE); ?></small>
                        </div>
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </form>
                </div>
            </div>

            <!-- Create Directory Form -->
            <div id="createDirForm" class="modal" style="display: none;">
                <div class="modal-content">
                    <span class="close" onclick="document.getElementById('createDirForm').style.display='none'">&times;</span>
                    <h2>Create New Folder</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label for="dir_name">Folder Name</label>
                            <input type="text" id="dir_name" name="dir_name" required>
                        </div>
                        <button type="submit" name="create_dir" class="btn btn-primary">Create</button>
                    </form>
                </div>
            </div>

            <!-- Directories List -->
            <?php if (count($directories) > 0): ?>
            <div class="file-list">
                <h3>📂 Folders</h3>
                <table class="files-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($directories as $dir): ?>
                        <tr>
                            <td>
                                <a href="index.php?dir=<?php echo urlencode($dir['directory_path']); ?>" class="folder-link">
                                    📁 <?php echo htmlspecialchars($dir['directory_name']); ?>
                                </a>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($dir['created_at'])); ?></td>
                            <td>
                                <a href="?deldir=<?php echo $dir['id']; ?>&dir=<?php echo urlencode($current_dir); ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Delete this folder and all its contents?')">
                                    🗑️ Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Files List -->
            <div class="file-list">
                <h3>📄 Files</h3>
                <?php if (count($files) > 0): ?>
                <table class="files-table">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Size</th>
                            <th>Type</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($files as $file): ?>
                        <tr>
                            <td>
                                <span class="file-icon">📄</span>
                                <?php echo htmlspecialchars($file['original_filename']); ?>
                            </td>
                            <td><?php echo formatFileSize($file['file_size']); ?></td>
                            <td><?php echo htmlspecialchars($file['mime_type']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($file['uploaded_at'])); ?></td>
                            <td>
                                <a href="download.php?id=<?php echo $file['id']; ?>" class="btn btn-success btn-sm">
                                    ⬇️ Download
                                </a>
                                <a href="?delete=<?php echo $file['id']; ?>&dir=<?php echo urlencode($current_dir); ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to delete this file?')">
                                    🗑️ Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="empty-message">No files in this directory. Upload your first file!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
