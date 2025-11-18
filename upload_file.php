<?php
require_once "config.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["id"];
$username = $_SESSION["username"];

// 1. Get folder path and normalize
$current_path_db = trim($_POST['current_path'] ?? '', '/');
$current_path_fs = ($current_path_db === '') ? '' : $current_path_db . '/';

$upload_dir = __DIR__ . "/uploads/" . $username . "/" . $current_path_fs;

// 2. DETERMINE VISIBILITY (LOGIC MODIFIED FOR ROOT DROPDOWN)
if ($current_path_db === '') {
    // ROOT: Use the POSTED visibility
    $visibility = $_POST['visibility'] ?? 'private';
} else {
    // SUBFOLDER: Inherit parent's visibility
    $parent_dir = __DIR__ . "/uploads/" . $username . "/" . $current_path_db;
    $visibility_file = $parent_dir . '/.visibility.txt';
    $visibility = file_exists($visibility_file) ? trim(file_get_contents($visibility_file)) : 'private';
}

// 3. Ensure the target directory exists
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        $_SESSION['upload_error'] = "Server Error: Failed to create/access storage directory: " . $current_path_fs;
        header("location: index.php?path=" . urlencode($current_path_db));
        exit;
    }
}

if (isset($_FILES["uploaded_file"]) && $_FILES["uploaded_file"]["error"] == 0) {
    
    $original_filename = basename($_FILES["uploaded_file"]["name"]);
    $file_size = $_FILES["uploaded_file"]["size"];
    $mime_type = $_FILES["uploaded_file"]["type"];
    
    $stored_filename = uniqid() . "-" . time() . "." . pathinfo($original_filename, PATHINFO_EXTENSION);
    $target_file_path = $upload_dir . $stored_filename;

    if (move_uploaded_file($_FILES["uploaded_file"]["tmp_name"], $target_file_path)) {
        
        // --- SQL INSERTION ---
        $sql = "INSERT INTO files (user_id, original_filename, stored_filename, file_path, file_size, mime_type, visibility, file_folder_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        if ($stmt = $conn->prepare($sql)) {
            $relative_path = "uploads/" . $username . "/" . $current_path_fs . $stored_filename; 
            
            $stmt->bind_param("isssisss", $user_id, $original_filename, $stored_filename, $relative_path, $file_size, $mime_type, $visibility, $current_path_db); 
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "The file " . htmlspecialchars($original_filename) . " has been uploaded to '{$current_path_db}' and set to " . $visibility . ".";
            } else {
                $_SESSION['upload_error'] = "File uploaded, but database error: " . $stmt->error;
                unlink($target_file_path);
            }
            $stmt->close();
        } else {
            $_SESSION['upload_error'] = "Database statement preparation failed: " . $conn->error;
        }
    } else {
        $_SESSION['upload_error'] = "Sorry, there was an error moving your file to the server.";
    }
} else {
    $error_codes = [1 => 'Too large (php.ini)', 2 => 'Too large (HTML)', 3 => 'Partial upload', 4 => 'No file uploaded', 6 => 'Missing temp folder', 7 => 'Failed write', 8 => 'Extension stop'];
    $code = $_FILES["uploaded_file"]["error"] ?? 4;
    $_SESSION['upload_error'] = "Upload Failed: " . ($error_codes[$code] ?? "Unknown error code: $code");
}

$conn->close();
header("location: index.php?path=" . urlencode($current_path_db));
exit;
?>