<?php
require_once 'config.php';
require_once 'utils.php';
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

if (!$file || !file_exists($file['file_path'])) {
    header('Location: index.php');
    exit();
}

$can_preview = canPreviewFile($file['mime_type']);
$file_icon = getFileIcon($file['mime_type']);

logActivity($user_id, 'FILE_PREVIEW', $file_id, $file['original_filename']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: <?php echo htmlspecialchars($file['original_filename']); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .preview-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
        }
        .preview-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .preview-header h2 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .file-meta {
            color: #666;
            font-size: 14px;
        }
        .file-meta span {
            margin-right: 20px;
        }
        .preview-actions {
            margin-top: 15px;
        }
        .preview-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            min-height: 400px;
        }
        .preview-content img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        .preview-content iframe {
            width: 100%;
            height: 800px;
            border: none;
        }
        .preview-content pre {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            max-height: 600px;
        }
        .no-preview {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .no-preview-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <header class="dashboard-header">
            <div class="header-left">
                <h1>📁 File Preview</h1>
            </div>
            <div class="header-right">
                <a href="index.php" class="btn btn-secondary">Back to Files</a>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </header>

        <div class="preview-container">
            <div class="preview-header">
                <h2><?php echo $file_icon; ?> <?php echo htmlspecialchars($file['original_filename']); ?></h2>
                <div class="file-meta">
                    <span><strong>Size:</strong> <?php echo formatBytes($file['file_size']); ?></span>
                    <span><strong>Type:</strong> <?php echo htmlspecialchars($file['mime_type']); ?></span>
                    <span><strong>Uploaded:</strong> <?php echo date('Y-m-d H:i:s', strtotime($file['uploaded_at'])); ?></span>
                </div>
                <div class="preview-actions">
                    <a href="download.php?id=<?php echo $file['id']; ?>" class="btn btn-success">
                        ⬇️ Download
                    </a>
                    <a href="share.php?id=<?php echo $file['id']; ?>" class="btn btn-primary">
                        🔗 Share
                    </a>
                </div>
            </div>

            <div class="preview-content">
                <?php if ($can_preview): ?>
                    <?php if (strpos($file['mime_type'], 'image/') === 0): ?>
                        <img src="data:<?php echo $file['mime_type']; ?>;base64,<?php echo base64_encode(file_get_contents($file['file_path'])); ?>" 
                             alt="<?php echo htmlspecialchars($file['original_filename']); ?>">
                    
                    <?php elseif ($file['mime_type'] === 'application/pdf'): ?>
                        <iframe src="data:application/pdf;base64,<?php echo base64_encode(file_get_contents($file['file_path'])); ?>"></iframe>
                    
                    <?php elseif (strpos($file['mime_type'], 'text/') === 0 || in_array($file['mime_type'], ['application/json', 'application/xml'])): ?>
                        <pre><code><?php echo htmlspecialchars(file_get_contents($file['file_path'])); ?></code></pre>
                    
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-preview">
                        <div class="no-preview-icon"><?php echo $file_icon; ?></div>
                        <h3>Preview not available</h3>
                        <p>This file type cannot be previewed in the browser.</p>
                        <p>Please download the file to view it.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
