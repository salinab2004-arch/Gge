<?php
require_once 'config.php';
require_once 'utils.php';
requireLogin();

$user_id = getCurrentUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'message' => ''];

// Handle different actions
switch ($action) {
    case 'rename':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $file_id = (int)$_POST['file_id'];
            $new_name = trim($_POST['new_name']);
            
            if (empty($new_name)) {
                $response['message'] = 'Filename cannot be empty.';
            } elseif (renameFile($file_id, $user_id, $new_name)) {
                $response['success'] = true;
                $response['message'] = 'File renamed successfully.';
            } else {
                $response['message'] = 'Failed to rename file.';
            }
        }
        break;
    
    case 'move':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $file_id = (int)$_POST['file_id'];
            $new_directory = $_POST['new_directory'];
            
            if (moveFile($file_id, $user_id, $new_directory)) {
                $response['success'] = true;
                $response['message'] = 'File moved successfully.';
            } else {
                $response['message'] = 'Failed to move file.';
            }
        }
        break;
    
    case 'copy':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $file_id = (int)$_POST['file_id'];
            $new_directory = $_POST['new_directory'] ?? null;
            
            if (copyFile($file_id, $user_id, $new_directory)) {
                $response['success'] = true;
                $response['message'] = 'File copied successfully.';
            } else {
                $response['message'] = 'Failed to copy file.';
            }
        }
        break;
    
    case 'search':
        $search_term = $_GET['q'] ?? '';
        $directory = $_GET['dir'] ?? null;
        
        if (!empty($search_term)) {
            $files = searchFiles($user_id, $search_term, $directory);
            $response['success'] = true;
            $response['files'] = $files;
        } else {
            $response['message'] = 'Search term required.';
        }
        break;
    
    case 'bulk_download':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $file_ids = $_POST['file_ids'] ?? [];
            
            if (empty($file_ids)) {
                $response['message'] = 'No files selected.';
                break;
            }
            
            // Create ZIP file
            $conn = getDBConnection();
            $zip = new ZipArchive();
            $zip_filename = 'download_' . time() . '.zip';
            $zip_path = sys_get_temp_dir() . '/' . $zip_filename;
            
            if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
                $response['message'] = 'Could not create ZIP file.';
                break;
            }
            
            $placeholders = implode(',', array_fill(0, count($file_ids), '?'));
            $params = array_merge([$user_id], $file_ids);
            
            $stmt = $conn->prepare("SELECT * FROM files WHERE user_id = ? AND id IN ($placeholders)");
            $stmt->execute($params);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($files as $file) {
                if (file_exists($file['file_path'])) {
                    $zip->addFile($file['file_path'], $file['original_filename']);
                }
            }
            
            $zip->close();
            
            // Send file
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
            header('Content-Length: ' . filesize($zip_path));
            readfile($zip_path);
            unlink($zip_path);
            
            logActivity($user_id, 'BULK_DOWNLOAD', null, 'Downloaded ' . count($files) . ' files');
            exit();
        }
        break;
    
    case 'bulk_delete':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $file_ids = $_POST['file_ids'] ?? [];
            
            if (empty($file_ids)) {
                $response['message'] = 'No files selected.';
                break;
            }
            
            $conn = getDBConnection();
            $placeholders = implode(',', array_fill(0, count($file_ids), '?'));
            $params = array_merge([$user_id], $file_ids);
            
            // Get files to delete
            $stmt = $conn->prepare("SELECT * FROM files WHERE user_id = ? AND id IN ($placeholders)");
            $stmt->execute($params);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Delete physical files
            foreach ($files as $file) {
                if (file_exists($file['file_path'])) {
                    unlink($file['file_path']);
                }
            }
            
            // Delete from database
            $stmt = $conn->prepare("DELETE FROM files WHERE user_id = ? AND id IN ($placeholders)");
            $stmt->execute($params);
            
            $response['success'] = true;
            $response['message'] = count($files) . ' files deleted successfully.';
            
            logActivity($user_id, 'BULK_DELETE', null, 'Deleted ' . count($files) . ' files');
        }
        break;
    
    default:
        $response['message'] = 'Invalid action.';
}

// Return JSON response for AJAX requests
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// For non-AJAX, redirect with message
$_SESSION['action_message'] = $response['message'];
$_SESSION['action_success'] = $response['success'];
header('Location: index.php');
exit();
?>
