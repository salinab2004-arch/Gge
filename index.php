<?php
require_once 'config.php';
require_once 'utils.php';
requireLogin();

$conn = getDBConnection();
$user_id = getCurrentUserId();
$username = getCurrentUsername();
$current_dir = $_GET['dir'] ?? '/';

// Handle messages from actions
$action_message = $_SESSION['action_message'] ?? '';
$action_success = $_SESSION['action_success'] ?? false;
unset($_SESSION['action_message'], $_SESSION['action_success']);

// Handle search
$search_query = $_GET['search'] ?? '';
$is_searching = !empty($search_query);

// Handle sorting
$sort_by = $_GET['sort'] ?? 'uploaded_at';
$sort_order = $_GET['order'] ?? 'DESC';
$valid_sorts = ['original_filename', 'file_size', 'uploaded_at', 'mime_type'];
if (!in_array($sort_by, $valid_sorts)) $sort_by = 'uploaded_at';
if (!in_array($sort_order, ['ASC', 'DESC'])) $sort_order = 'DESC';

// Handle view mode
$view_mode = $_GET['view'] ?? $_COOKIE['view_mode'] ?? 'list';
if ($view_mode === 'grid') {
    setcookie('view_mode', 'grid', time() + (86400 * 30), '/');
} else {
    setcookie('view_mode', 'list', time() + (86400 * 30), '/');
}

// Handle file upload with activity logging
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    $upload_errors = [];
    $upload_success = 0;
    
    foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
            $file_size = $_FILES['files']['size'][$key];
            $original_filename = basename($_FILES['files']['name'][$key]);
            $file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
            
            if ($file_size > MAX_FILE_SIZE) {
                $upload_errors[] = "$original_filename: File size exceeds maximum.";
            } elseif (!in_array($file_ext, ALLOWED_EXTENSIONS)) {
                $upload_errors[] = "$original_filename: File type not allowed.";
            } elseif (!canUploadFile($user_id, $file_size)) {
                $upload_errors[] = "$original_filename: Storage limit exceeded.";
            } else {
                $unique_filename = uniqid() . '_' . sanitizeFilename($original_filename);
                $user_upload_dir = UPLOAD_DIR . $user_id . '/';
                
                if (!file_exists($user_upload_dir)) {
                    mkdir($user_upload_dir, 0755, true);
                }
                
                $target_dir = $user_upload_dir;
                if ($current_dir !== '/') {
                    $target_dir .= trim($current_dir, '/') . '/';
                    if (!file_exists($target_dir)) {
                        mkdir($target_dir, 0755, true);
                    }
                }
                
                $target_file = $target_dir . $unique_filename;
                
                if (move_uploaded_file($tmp_name, $target_file)) {
                    $stmt = $conn->prepare("INSERT INTO files (user_id, filename, original_filename, file_path, file_size, mime_type, directory) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $unique_filename, $original_filename, $target_file, $file_size, $_FILES['files']['type'][$key], $current_dir]);
                    
                    logActivity($user_id, 'FILE_UPLOAD', $conn->lastInsertId(), $original_filename);
                    $upload_success++;
                } else {
                    $upload_errors[] = "$original_filename: Failed to upload.";
                }
            }
        }
    }
    
    if ($upload_success > 0) {
        $action_message = "$upload_success file(s) uploaded successfully.";
        $action_success = true;
    }
    if (count($upload_errors) > 0) {
        $action_message .= ' Errors: ' . implode(', ', $upload_errors);
    }
    
    header('Location: index_advanced.php?dir=' . urlencode($current_dir));
    exit();
}

// Handle single file deletion with logging
if (isset($_GET['delete'])) {
    $file_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
    $stmt->execute([$file_id, $user_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($file) {
        if (file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
        $stmt = $conn->prepare("DELETE FROM files WHERE id = ? AND user_id = ?");
        $stmt->execute([$file_id, $user_id]);
        
        logActivity($user_id, 'FILE_DELETE', $file_id, $file['original_filename']);
    }
    
    header('Location: index_advanced.php?dir=' . urlencode($current_dir));
    exit();
}

// Handle directory operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_dir'])) {
    $dir_name = trim($_POST['dir_name']);
    if (!empty($dir_name)) {
        $dir_name = sanitizeFilename($dir_name);
        $new_dir_path = $current_dir === '/' ? '/' . $dir_name : $current_dir . '/' . $dir_name;
        
        $physical_dir = UPLOAD_DIR . $user_id . '/' . trim($new_dir_path, '/');
        if (!file_exists($physical_dir)) {
            mkdir($physical_dir, 0755, true);
            
            $stmt = $conn->prepare("INSERT INTO directories (user_id, directory_name, directory_path, parent_path) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $dir_name, $new_dir_path, $current_dir]);
            
            logActivity($user_id, 'DIR_CREATE', null, "Created directory: $dir_name");
        }
    }
    
    header('Location: index_advanced.php?dir=' . urlencode($current_dir));
    exit();
}

if (isset($_GET['deldir'])) {
    $dir_id = (int)$_GET['deldir'];
    $stmt = $conn->prepare("SELECT * FROM directories WHERE id = ? AND user_id = ?");
    $stmt->execute([$dir_id, $user_id]);
    $directory = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($directory) {
        $physical_dir = UPLOAD_DIR . $user_id . '/' . trim($directory['directory_path'], '/');
        
        $stmt = $conn->prepare("SELECT file_path FROM files WHERE user_id = ? AND directory LIKE ?");
        $stmt->execute([$user_id, $directory['directory_path'] . '%']);
        while ($file = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
        }
        
        $stmt = $conn->prepare("DELETE FROM files WHERE user_id = ? AND directory LIKE ?");
        $stmt->execute([$user_id, $directory['directory_path'] . '%']);
        
        if (file_exists($physical_dir)) {
            rmdir($physical_dir);
        }
        
        $stmt = $conn->prepare("DELETE FROM directories WHERE id = ? AND user_id = ?");
        $stmt->execute([$dir_id, $user_id]);
        
        logActivity($user_id, 'DIR_DELETE', null, "Deleted directory: {$directory['directory_name']}");
    }
    
    header('Location: index_advanced.php?dir=' . urlencode($current_dir));
    exit();
}

// Get files - with search or normal listing
if ($is_searching) {
    $files = searchFiles($user_id, $search_query, $current_dir);
} else {
    $stmt = $conn->prepare("SELECT * FROM files WHERE user_id = ? AND directory = ? ORDER BY $sort_by $sort_order");
    $stmt->execute([$user_id, $current_dir]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get subdirectories
$stmt = $conn->prepare("SELECT * FROM directories WHERE user_id = ? AND parent_path = ? ORDER BY directory_name ASC");
$stmt->execute([$user_id, $current_dir]);
$directories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all directories for move/copy modals
$stmt = $conn->prepare("SELECT * FROM directories WHERE user_id = ? ORDER BY directory_path ASC");
$stmt->execute([$user_id]);
$all_directories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate storage
$stmt = $conn->prepare("SELECT SUM(file_size) as total FROM files WHERE user_id = ?");
$stmt->execute([$user_id]);
$storage = $stmt->fetch(PDO::FETCH_ASSOC);
$total_storage = $storage['total'] ?? 0;

$storage_limit = getUserStorageLimit($user_id);
$storage_limit_formatted = getStorageLimitFormatted($storage_limit);

if ($storage_limit > 0) {
    $storage_percentage = ($total_storage / $storage_limit) * 100;
} else {
    $storage_percentage = 0;
}

function formatFileSize($bytes) {
    return formatBytes($bytes);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FTP File Manager - Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .toolbar {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        .search-box {
            flex: 1;
            min-width: 200px;
        }
        .search-box input {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
        }
        .view-toggle {
            display: flex;
            gap: 5px;
        }
        .view-toggle button {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            background: white;
            cursor: pointer;
            border-radius: 5px;
        }
        .view-toggle button.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .grid-view {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        .grid-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            cursor: pointer;
            transition: transform 0.2s;
        }
        .grid-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .grid-item-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .grid-item-name {
            font-size: 14px;
            word-break: break-word;
        }
        .grid-item-size {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        .file-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .file-actions button, .file-actions a {
            padding: 4px 8px;
            font-size: 11px;
        }
        .bulk-actions {
            background: #f7fafc;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: none;
        }
        .bulk-actions.active {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .file-checkbox {
            margin-right: 10px;
        }
        .drag-drop-area {
            border: 3px dashed #667eea;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background: #f7fafc;
            margin-bottom: 20px;
            cursor: pointer;
        }
        .drag-drop-area.dragover {
            background: #e6f3ff;
            border-color: #5568d3;
        }
    </style>
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
                <a href="profile.php" class="btn btn-secondary">👤 Profile</a>
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
            <?php if ($action_message): ?>
                <div class="alert alert-<?php echo $action_success ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($action_message); ?>
                </div>
            <?php endif; ?>

            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="index_advanced.php">🏠 Home</a>
                <?php
                if ($current_dir !== '/') {
                    $parts = explode('/', trim($current_dir, '/'));
                    $path = '';
                    foreach ($parts as $part) {
                        $path .= '/' . $part;
                        echo ' / <a href="index_advanced.php?dir=' . urlencode($path) . '">' . htmlspecialchars($part) . '</a>';
                    }
                }
                ?>
            </div>

            <!-- Toolbar -->
            <div class="toolbar">
                <div class="search-box">
                    <form method="GET" style="margin: 0;">
                        <input type="hidden" name="dir" value="<?php echo htmlspecialchars($current_dir); ?>">
                        <input type="text" name="search" placeholder="🔍 Search files..." 
                               value="<?php echo htmlspecialchars($search_query); ?>">
                    </form>
                </div>
                
                <select onchange="location.href='?dir=<?php echo urlencode($current_dir); ?>&sort=' + this.value + '&order=<?php echo $sort_order; ?>&view=<?php echo $view_mode; ?>'">
                    <option value="uploaded_at" <?php echo $sort_by === 'uploaded_at' ? 'selected' : ''; ?>>Sort by Date</option>
                    <option value="original_filename" <?php echo $sort_by === 'original_filename' ? 'selected' : ''; ?>>Sort by Name</option>
                    <option value="file_size" <?php echo $sort_by === 'file_size' ? 'selected' : ''; ?>>Sort by Size</option>
                    <option value="mime_type" <?php echo $sort_by === 'mime_type' ? 'selected' : ''; ?>>Sort by Type</option>
                </select>
                
                <button onclick="location.href='?dir=<?php echo urlencode($current_dir); ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>&view=<?php echo $view_mode; ?>'" class="btn btn-secondary btn-sm">
                    <?php echo $sort_order === 'ASC' ? '⬆️' : '⬇️'; ?>
                </button>
                
                <div class="view-toggle">
                    <button class="<?php echo $view_mode === 'list' ? 'active' : ''; ?>" 
                            onclick="location.href='?dir=<?php echo urlencode($current_dir); ?>&view=list'">📋 List</button>
                    <button class="<?php echo $view_mode === 'grid' ? 'active' : ''; ?>" 
                            onclick="location.href='?dir=<?php echo urlencode($current_dir); ?>&view=grid'">⊞ Grid</button>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div class="bulk-actions" id="bulkActions">
                <span id="selectedCount">0 files selected</span>
                <button onclick="bulkDownload()" class="btn btn-success btn-sm">⬇️ Download</button>
                <button onclick="bulkDelete()" class="btn btn-danger btn-sm">🗑️ Delete</button>
                <button onclick="clearSelection()" class="btn btn-secondary btn-sm">Clear</button>
            </div>

            <!-- Action Buttons -->
            <div class="actions-bar">
                <button class="btn btn-primary" onclick="document.getElementById('uploadArea').click()">
                    ⬆️ Upload Files
                </button>
                <button class="btn btn-secondary" onclick="document.getElementById('createDirForm').style.display='block'">
                    📁 Create Folder
                </button>
            </div>

            <!-- Drag & Drop Upload Area -->
            <div class="drag-drop-area" id="dropArea" onclick="document.getElementById('uploadArea').click()">
                <p>📤 Drag & Drop files here or click to upload</p>
                <small>Supports multiple files</small>
            </div>
            
            <form id="uploadForm" method="POST" enctype="multipart/form-data" style="display: none;">
                <input type="file" id="uploadArea" name="files[]" multiple onchange="this.form.submit()">
            </form>

            <!-- Create Directory Modal -->
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

            <?php if ($view_mode === 'list'): ?>
                <!-- List View -->
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
                                    <a href="index_advanced.php?dir=<?php echo urlencode($dir['directory_path']); ?>" class="folder-link">
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
                    <h3>📄 Files <?php echo $is_searching ? '(Search Results)' : ''; ?></h3>
                    <?php if (count($files) > 0): ?>
                    <table class="files-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
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
                                <td><input type="checkbox" class="file-checkbox" value="<?php echo $file['id']; ?>" onchange="updateBulkActions()"></td>
                                <td>
                                    <?php echo getFileIcon($file['mime_type']); ?>
                                    <?php echo htmlspecialchars($file['original_filename']); ?>
                                </td>
                                <td><?php echo formatFileSize($file['file_size']); ?></td>
                                <td><?php echo htmlspecialchars($file['mime_type']); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($file['uploaded_at'])); ?></td>
                                <td class="file-actions">
                                    <?php if (canPreviewFile($file['mime_type'])): ?>
                                        <a href="preview.php?id=<?php echo $file['id']; ?>" class="btn btn-secondary btn-sm">👁️ Preview</a>
                                    <?php endif; ?>
                                    <a href="download.php?id=<?php echo $file['id']; ?>" class="btn btn-success btn-sm">⬇️</a>
                                    <a href="share.php?id=<?php echo $file['id']; ?>" class="btn btn-primary btn-sm">🔗</a>
                                    <button onclick="showRenameModal(<?php echo $file['id']; ?>, '<?php echo addslashes($file['original_filename']); ?>')" class="btn btn-secondary btn-sm">✏️</button>
                                    <a href="?delete=<?php echo $file['id']; ?>&dir=<?php echo urlencode($current_dir); ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Delete this file?')">🗑️</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p class="empty-message">No files found. Upload your first file!</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Grid View -->
                <div class="grid-view">
                    <?php foreach ($directories as $dir): ?>
                        <div class="grid-item" onclick="location.href='index_advanced.php?dir=<?php echo urlencode($dir['directory_path']); ?>'">
                            <div class="grid-item-icon">📁</div>
                            <div class="grid-item-name"><?php echo htmlspecialchars($dir['directory_name']); ?></div>
                            <div class="grid-item-size"><?php echo date('Y-m-d', strtotime($dir['created_at'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php foreach ($files as $file): ?>
                        <div class="grid-item" onclick="event.target.tagName !== 'A' && event.target.tagName !== 'BUTTON' && location.href='preview.php?id=<?php echo $file['id']; ?>'">
                            <div class="grid-item-icon"><?php echo getFileIcon($file['mime_type']); ?></div>
                            <div class="grid-item-name"><?php echo htmlspecialchars($file['original_filename']); ?></div>
                            <div class="grid-item-size"><?php echo formatFileSize($file['file_size']); ?></div>
                            <div class="file-actions">
                                <a href="download.php?id=<?php echo $file['id']; ?>" class="btn btn-success btn-sm">⬇️</a>
                                <a href="share.php?id=<?php echo $file['id']; ?>" class="btn btn-primary btn-sm">🔗</a>
                                <a href="?delete=<?php echo $file['id']; ?>&dir=<?php echo urlencode($current_dir); ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Delete?')">🗑️</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($files) === 0 && count($directories) === 0): ?>
                    <p class="empty-message">No files or folders found.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rename Modal -->
    <div id="renameModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('renameModal').style.display='none'">&times;</span>
            <h2>Rename File</h2>
            <form action="actions.php" method="POST">
                <input type="hidden" name="action" value="rename">
                <input type="hidden" name="file_id" id="renameFileId">
                <div class="form-group">
                    <label for="new_name">New Name</label>
                    <input type="text" id="new_name" name="new_name" required>
                </div>
                <button type="submit" class="btn btn-primary">Rename</button>
            </form>
        </div>
    </div>

    <script>
        // Drag and drop functionality
        const dropArea = document.getElementById('dropArea');
        const uploadArea = document.getElementById('uploadArea');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => dropArea.classList.add('dragover'), false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => dropArea.classList.remove('dragover'), false);
        });
        
        dropArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            uploadArea.files = files;
            document.getElementById('uploadForm').submit();
        }
        
        // Bulk operations
        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.file-checkbox');
            const selectAll = document.getElementById('selectAll');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.file-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const count = checkboxes.length;
            
            if (count > 0) {
                bulkActions.classList.add('active');
                document.getElementById('selectedCount').textContent = count + ' file(s) selected';
            } else {
                bulkActions.classList.remove('active');
            }
        }
        
        function bulkDownload() {
            const ids = Array.from(document.querySelectorAll('.file-checkbox:checked')).map(cb => cb.value);
            if (ids.length === 0) return;
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'actions.php?action=bulk_download';
            
            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'file_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function bulkDelete() {
            if (!confirm('Delete selected files?')) return;
            
            const ids = Array.from(document.querySelectorAll('.file-checkbox:checked')).map(cb => cb.value);
            if (ids.length === 0) return;
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'actions.php?action=bulk_delete';
            
            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'file_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function clearSelection() {
            document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
            updateBulkActions();
        }
        
        function showRenameModal(fileId, currentName) {
            document.getElementById('renameFileId').value = fileId;
            document.getElementById('new_name').value = currentName;
            document.getElementById('renameModal').style.display = 'block';
        }
        
        // Close modals on outside click
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
