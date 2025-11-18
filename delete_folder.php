<?php
require_once "config.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["id"];
$username = $_SESSION["username"];
$folder_name = trim($_GET['folder_name'] ?? '');
$current_path = trim($_GET['current_path'] ?? ''); // Path for redirect

$user_storage_dir = __DIR__ . "/uploads/" . $username . "/";
$current_path_slash = ($current_path === '') ? '' : $current_path . '/';

if (empty($folder_name) || strpos($folder_name, '..') !== false) {
    $_SESSION['error'] = "Invalid folder delete request.";
    header("location: index.php?path=" . urlencode($current_path));
    exit;
}

// 1. Define the target directory path
$target_dir = $user_storage_dir . $current_path_slash . $folder_name;

// 2. Define the path string used in the database for files inside this folder
// Example: If current_path is 'docs', and folder_name is 'photos', the DB path is 'docs/photos'
$folder_path_db = $current_path === '' ? $folder_name : $current_path . '/' . $folder_name;


/**
 * Recursively deletes a directory and all its contents (physical cleanup).
 * @param string $dir The directory path to delete.
 * @return bool True on success, false on failure.
 */
function deleteDirectoryRecursively($dir) {
    if (!is_dir($dir)) {
        return true;
    }

    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            if (!deleteDirectoryRecursively($path)) {
                return false;
            }
        } else {
            if (!unlink($path)) {
                return false;
            }
        }
    }
    
    return rmdir($dir);
}


if (!is_dir($target_dir)) {
    $_SESSION['error'] = "Folder '{$folder_name}' not found on the server.";
} 
else {
    // Start database transaction before filesystem operation
    $conn->begin_transaction();
    $success = false;

    try {
        // --- DATABASE CLEANUP ---
        
        // Delete all file records that are IMMEDIATELY inside this folder OR any subfolder.
        // We use LIKE 'path/%' to match nested content, but first, we'll simplify to just the target folder for this simple model.
        
        // Find all files and folders that start with the path (e.g., 'docs/photos' or 'docs/photos/%')
        // NOTE: The percent sign must be appended for SQL LIKE comparison.
        $sql_delete_files = "DELETE FROM files WHERE user_id = ? AND (file_folder_path = ? OR file_folder_path LIKE ?)";
        
        if ($stmt = $conn->prepare($sql_delete_files)) {
            $path_like = $folder_path_db . '/%'; // Matches subfolders like 'docs/photos/sub'
            
            // The first ? is for the exact path ('docs/photos'), and the second is for subpaths ('docs/photos/%')
            $stmt->bind_param("iss", $user_id, $folder_path_db, $path_like);
            $stmt->execute();
            $stmt->close();
        } else {
            throw new Exception("Database preparation failed for file deletion.");
        }
        
        // --- FILESYSTEM CLEANUP ---
        if (deleteDirectoryRecursively($target_dir)) {
            $conn->commit();
            $success = true;
        } else {
            throw new Exception("Failed to delete physical folder content on the server.");
        }

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Deletion failed: " . $e->getMessage();
    }
    
    if ($success) {
        $_SESSION['success'] = "Folder '{$folder_name}' and all its contents deleted successfully.";
    }
}

$conn->close();
header("location: index.php?path=" . urlencode($current_path));
exit;
?>