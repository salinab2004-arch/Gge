<?php
require_once 'config.php';
require_once 'utils.php';

// Check if it's a public share link
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $conn = getDBConnection();
    
    // Get share link info
    $stmt = $conn->prepare("SELECT sl.*, f.* FROM shared_links sl 
        JOIN files f ON sl.file_id = f.id 
        WHERE sl.share_token = ?");
    $stmt->execute([$token]);
    $share = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$share) {
        die('Invalid or expired share link.');
    }
    
    // Check expiration
    if ($share['expires_at'] && strtotime($share['expires_at']) < time()) {
        die('This share link has expired.');
    }
    
    // Check download limit
    if ($share['max_downloads'] > 0 && $share['download_count'] >= $share['max_downloads']) {
        die('Download limit reached for this file.');
    }
    
    // Check password if set
    if ($share['password']) {
        session_start();
        if (!isset($_SESSION['share_verified_' . $token])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
                if (password_verify($_POST['password'], $share['password'])) {
                    $_SESSION['share_verified_' . $token] = true;
                } else {
                    $error = 'Invalid password.';
                }
            }
            
            if (!isset($_SESSION['share_verified_' . $token])) {
                ?>
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Password Required</title>
                    <link rel="stylesheet" href="style.css">
                </head>
                <body>
                    <div class="auth-container">
                        <div class="auth-box">
                            <h1>🔒 Password Required</h1>
                            <?php if (isset($error)): ?>
                                <div class="alert alert-error"><?php echo $error; ?></div>
                            <?php endif; ?>
                            <p>This file is password protected.</p>
                            <form method="POST">
                                <div class="form-group">
                                    <label for="password">Enter Password</label>
                                    <input type="password" id="password" name="password" required autofocus>
                                </div>
                                <button type="submit" class="btn btn-primary">Access File</button>
                            </form>
                        </div>
                    </div>
                </body>
                </html>
                <?php
                exit();
            }
        }
    }
    
    // Handle download
    if (isset($_GET['download'])) {
        // Update download count
        $stmt = $conn->prepare("UPDATE shared_links SET download_count = download_count + 1 WHERE id = ?");
        $stmt->execute([$share['id']]);
        
        // Log activity
        logActivity($share['user_id'], 'SHARED_FILE_DOWNLOAD', $share['file_id'], 'Downloaded via share link');
        
        // Send file
        header('Content-Type: ' . $share['mime_type']);
        header('Content-Disposition: attachment; filename="' . $share['original_filename'] . '"');
        header('Content-Length: ' . $share['file_size']);
        readfile($share['file_path']);
        exit();
    }
    
    // Display share page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Shared File: <?php echo htmlspecialchars($share['original_filename']); ?></title>
        <link rel="stylesheet" href="style.css">
        <style>
            .share-container {
                max-width: 600px;
                margin: 100px auto;
                padding: 40px;
                background: white;
                border-radius: 10px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
                text-align: center;
            }
            .file-icon-large {
                font-size: 64px;
                margin-bottom: 20px;
            }
            .share-info {
                margin: 20px 0;
                padding: 20px;
                background: #f7fafc;
                border-radius: 8px;
                text-align: left;
            }
            .share-info p {
                margin: 8px 0;
                color: #555;
            }
        </style>
    </head>
    <body>
        <div class="share-container">
            <div class="file-icon-large"><?php echo getFileIcon($share['mime_type']); ?></div>
            <h1><?php echo htmlspecialchars($share['original_filename']); ?></h1>
            
            <div class="share-info">
                <p><strong>Size:</strong> <?php echo formatBytes($share['file_size']); ?></p>
                <p><strong>Type:</strong> <?php echo htmlspecialchars($share['mime_type']); ?></p>
                <?php if ($share['expires_at']): ?>
                    <p><strong>Expires:</strong> <?php echo date('Y-m-d H:i', strtotime($share['expires_at'])); ?></p>
                <?php endif; ?>
                <?php if ($share['max_downloads'] > 0): ?>
                    <p><strong>Downloads:</strong> <?php echo $share['download_count']; ?> / <?php echo $share['max_downloads']; ?></p>
                <?php endif; ?>
            </div>
            
            <a href="?token=<?php echo $token; ?>&download=1" class="btn btn-primary">
                ⬇️ Download File
            </a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Regular user share page
requireLogin();

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$file_id = (int)$_GET['id'];
$user_id = getCurrentUserId();

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
$stmt->execute([$file_id, $user_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    header('Location: index.php');
    exit();
}

$success = '';
$error = '';

// Handle share link creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_share'])) {
    $expires_hours = (int)$_POST['expires_hours'];
    $max_downloads = (int)$_POST['max_downloads'];
    $password = $_POST['share_password'] ?? '';
    
    $expires_at = null;
    if ($expires_hours > 0) {
        $expires_at = date('Y-m-d H:i:s', time() + ($expires_hours * 3600));
    }
    
    $hashed_password = null;
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    }
    
    $share_token = generateShareToken();
    
    $stmt = $conn->prepare("INSERT INTO shared_links (file_id, user_id, share_token, expires_at, max_downloads, password) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$file_id, $user_id, $share_token, $expires_at, $max_downloads, $hashed_password])) {
        $success = 'Share link created successfully!';
        logActivity($user_id, 'CREATE_SHARE_LINK', $file_id, $file['original_filename']);
    } else {
        $error = 'Failed to create share link.';
    }
}

// Handle share link deletion
if (isset($_GET['delete_share'])) {
    $share_id = (int)$_GET['delete_share'];
    $stmt = $conn->prepare("DELETE FROM shared_links WHERE id = ? AND user_id = ?");
    $stmt->execute([$share_id, $user_id]);
    $success = 'Share link deleted.';
}

// Get existing share links
$stmt = $conn->prepare("SELECT * FROM shared_links WHERE file_id = ? AND user_id = ? ORDER BY created_at DESC");
$stmt->execute([$file_id, $user_id]);
$shares = $stmt->fetchAll(PDO::FETCH_ASSOC);

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Share File: <?php echo htmlspecialchars($file['original_filename']); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard">
        <header class="dashboard-header">
            <div class="header-left">
                <h1>🔗 Share File</h1>
            </div>
            <div class="header-right">
                <a href="index.php" class="btn btn-secondary">Back to Files</a>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </header>

        <div class="dashboard-content">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="file-list">
                <h3>File: <?php echo getFileIcon($file['mime_type']); ?> <?php echo htmlspecialchars($file['original_filename']); ?></h3>
                
                <h4 style="margin-top: 30px;">Create New Share Link</h4>
                <form method="POST" class="share-form">
                    <div class="form-group">
                        <label for="expires_hours">Expires In (hours, 0 = never)</label>
                        <input type="number" id="expires_hours" name="expires_hours" value="24" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="max_downloads">Max Downloads (0 = unlimited)</label>
                        <input type="number" id="max_downloads" name="max_downloads" value="0" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="share_password">Password Protection (optional)</label>
                        <input type="password" id="share_password" name="share_password" placeholder="Leave empty for no password">
                    </div>
                    
                    <button type="submit" name="create_share" class="btn btn-primary">Create Share Link</button>
                </form>

                <?php if (count($shares) > 0): ?>
                    <h4 style="margin-top: 30px;">Existing Share Links</h4>
                    <table class="files-table">
                        <thead>
                            <tr>
                                <th>Link</th>
                                <th>Created</th>
                                <th>Expires</th>
                                <th>Downloads</th>
                                <th>Protected</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shares as $share): ?>
                                <?php 
                                $is_expired = $share['expires_at'] && strtotime($share['expires_at']) < time();
                                $is_maxed = $share['max_downloads'] > 0 && $share['download_count'] >= $share['max_downloads'];
                                ?>
                                <tr <?php echo ($is_expired || $is_maxed) ? 'style="opacity: 0.5;"' : ''; ?>>
                                    <td>
                                        <input type="text" value="<?php echo $base_url; ?>/share.php?token=<?php echo $share['share_token']; ?>" 
                                               readonly onclick="this.select();" style="width: 100%; padding: 5px;">
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($share['created_at'])); ?></td>
                                    <td><?php echo $share['expires_at'] ? date('Y-m-d H:i', strtotime($share['expires_at'])) : 'Never'; ?></td>
                                    <td><?php echo $share['download_count']; ?><?php echo $share['max_downloads'] > 0 ? ' / ' . $share['max_downloads'] : ''; ?></td>
                                    <td><?php echo $share['password'] ? '🔒 Yes' : '🔓 No'; ?></td>
                                    <td>
                                        <a href="?id=<?php echo $file_id; ?>&delete_share=<?php echo $share['id']; ?>" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Delete this share link?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
