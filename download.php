<?php
require_once 'config.php';
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

// Set headers for file download
header('Content-Type: ' . $file['mime_type']);
header('Content-Disposition: attachment; filename="' . $file['original_filename'] . '"');
header('Content-Length: ' . $file['file_size']);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');

// Output file
readfile($file['file_path']);
exit();
?>
