<?php
require_once "config.php";

$file_id = isset($_GET['file_id']) ? $_GET['file_id'] : null;
$is_public_access = isset($_GET['public_access']) && $_GET['public_access'] === 'true'; // New flag

$logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$user_id = $logged_in ? $_SESSION["id"] : null;

if (!$file_id) {
    if ($logged_in) {
        $_SESSION['error'] = "Invalid file request.";
        header("location: index.php");
    } else {
        http_response_code(404);
        echo "Error: File Not Found or Invalid Request.";
    }
    exit;
}

// Retrieve file info, including the folder path
$sql = "SELECT original_filename, stored_filename, file_size, mime_type, user_id, visibility, file_folder_path FROM files WHERE id = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $file_id);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            $file = $result->fetch_assoc();
            
            $owner_id = $file['user_id'];
            $owner_username = $conn->query("SELECT username FROM users WHERE id = $owner_id")->fetch_assoc()['username'];
            $folder_path_db = $file['file_folder_path']; 
            $folder_path_fs = ($folder_path_db === '') ? '' : $folder_path_db . '/';
            $file_path = __DIR__ . "/uploads/" . $owner_username . "/" . $folder_path_fs . $file['stored_filename'];

            // --- ACCESS CONTROL LOGIC (MODIFIED) ---
            $can_access = false;
            
            // Rule 1: Uploader can always access (logged in)
            if ($logged_in && $owner_id == $user_id) {
                $can_access = true;
            } 
            // Rule 2: Public access via shared link/viewer (must be public visibility AND have public_access flag)
            elseif ($is_public_access && $file['visibility'] === 'public') {
                $can_access = true;
            }
            // Rule 3: File is not public, and user is not owner/logged in -> denied
            
            if ($can_access) {
                if (file_exists($file_path)) {
                    // Set headers and stream the file
                    header('Content-Description: File Transfer');
                    header('Content-Type: ' . $file['mime_type']);
                    header('Content-Disposition: attachment; filename="' . $file['original_filename'] . '"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . $file['file_size']);
                    
                    ob_clean();
                    flush();
                    readfile($file_path);
                    exit;
                } else {
                    $error_message = "Error: File not found on the server.";
                }
            } else {
                $error_message = "Access Denied. This file is private or requires login.";
                http_response_code(403); // Forbidden
            }
            
        } else {
            $error_message = "File not found in the database.";
            http_response_code(404);
        }
    }
    $stmt->close();
}

// Handle errors
$conn->close();
if ($logged_in && isset($error_message) && $error_message !== "Access Denied. This file is private or requires login.") {
    // Show non-access related errors to logged-in users on the dashboard
    $_SESSION['error'] = $error_message;
    header("location: index.php");
    exit;
} elseif (isset($error_message)) {
    // Display error message directly for public access failures
    echo "<h1>Error: " . $error_message . "</h1>";
    exit;
}

http_response_code(500);
echo "An unexpected server error occurred.";
exit;
?>