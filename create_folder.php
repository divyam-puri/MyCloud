<?php
require_once "config.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$username = $_SESSION["username"];
$folder_name = trim($_POST['folder_name'] ?? '');
$current_path_db = trim($_POST['current_path'] ?? ''); // Get path (normalized)

// 1. DETERMINE VISIBILITY (LOGIC MODIFIED FOR ROOT DROPDOWN)
if ($current_path_db === '') {
    // ROOT: Use the posted visibility from the dropdown
    $visibility = $_POST['visibility'] ?? 'private';
} else {
    // SUBFOLDER: Inherit parent's visibility
    $parent_dir_path = __DIR__ . "/uploads/" . $username . "/" . $current_path_db;
    $visibility_file = $parent_dir_path . '/.visibility.txt';
    $visibility = file_exists($visibility_file) ? trim(file_get_contents($visibility_file)) : 'private';
}

// 2. Define the full target directory path
$user_storage_dir = __DIR__ . "/uploads/" . $username . "/";
$target_base_dir = $user_storage_dir . ($current_path_db === '' ? '' : $current_path_db . '/');
$target_dir = $target_base_dir . $folder_name;


// Ensure base path exists
if (!is_dir($target_base_dir) && !mkdir($target_base_dir, 0777, true)) {
    $_SESSION['error'] = "Server Error: Cannot access user storage.";
    header("location: index.php?path=" . urlencode($current_path_db));
    exit;
}

// 3. Validate input
if (empty($folder_name)) {
    $_SESSION['error'] = "Folder name cannot be empty.";
} elseif (!preg_match('/^[a-zA-Z0-9_ -]+$/', $folder_name)) {
    $_SESSION['error'] = "Invalid folder name. Use only letters, numbers, spaces, hyphens, and underscores.";
} else {
    
    if (is_dir($target_dir)) {
        $_SESSION['error'] = "Folder '{$folder_name}' already exists in this location.";
    } 
    elseif (mkdir($target_dir, 0777, true)) {
        
        // 4. Write the determined visibility status
        $visibility_file = $target_dir . '/.visibility.txt';
        if (file_put_contents($visibility_file, $visibility) !== false) {
            $_SESSION['success'] = "Folder '{$folder_name}' created successfully in '{$current_path_db}' and set to " . $visibility . ".";
        } else {
            $_SESSION['error'] = "Folder created, but failed to set visibility. Defaulting to private.";
        }

    } else {
        $_SESSION['error'] = "Failed to create folder '{$folder_name}'. Check server permissions.";
    }
}

$conn->close();
header("location: index.php?path=" . urlencode($current_path_db));
exit;
?>