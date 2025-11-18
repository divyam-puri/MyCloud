<?php
// Include the database configuration and start the session
require_once "config.php";

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["id"]; // ID of the logged-in user (the supposed file owner)

// 1. Validate the file ID input
if (!isset($_GET['file_id']) || empty($_GET['file_id'])) {
    $_SESSION['error'] = "Invalid delete request: File ID missing.";
    header("location: index.php");
    exit;
}

$file_id = $_GET['file_id'];
$success_flag = false;
$error_flag = false;

// 2. Step 1: Get the stored_filename and verify ownership (CRUCIAL SECURITY STEP)
$sql_select = "SELECT stored_filename FROM files WHERE id = ? AND user_id = ?";
if ($stmt_select = $conn->prepare($sql_select)) {
    $stmt_select->bind_param("ii", $file_id, $user_id);
    
    if ($stmt_select->execute()) {
        $result = $stmt_select->get_result();
        
        if ($result->num_rows == 1) {
            $file = $result->fetch_assoc();
            $stored_filename = $file['stored_filename'];
            $file_to_delete = __DIR__ . "/uploads/" . $stored_filename;
            
            $deleted_filesystem = false;
            
            // 3. Delete the physical file from the server
            if (file_exists($file_to_delete)) {
                if (unlink($file_to_delete)) {
                    $deleted_filesystem = true;
                } else {
                    $_SESSION['error'] = "Error deleting physical file from server. Check permissions.";
                    $error_flag = true;
                }
            } else {
                // If the database record exists but the file is missing, we still proceed to delete the record.
                $deleted_filesystem = true;
            }

            if ($deleted_filesystem && !$error_flag) {
                // 4. Delete the record from the database
                $sql_delete = "DELETE FROM files WHERE id = ? AND user_id = ?";
                if ($stmt_delete = $conn->prepare($sql_delete)) {
                    $stmt_delete->bind_param("ii", $file_id, $user_id);
                    if ($stmt_delete->execute()) {
                        $_SESSION['success'] = "File deleted successfully.";
                        $success_flag = true;
                    } else {
                        $_SESSION['error'] = "Error deleting file record from database: " . $stmt_delete->error;
                        $error_flag = true;
                    }
                    $stmt_delete->close();
                } else {
                    $_SESSION['error'] = "Database statement preparation failed (DELETE).";
                    $error_flag = true;
                }
            }
            
        } else {
            // File not found OR user_id doesn't match the file owner
            $_SESSION['error'] = "File not found or access denied (You are not the owner).";
            $error_flag = true;
        }
    } else {
        $_SESSION['error'] = "Error executing database query (SELECT).";
        $error_flag = true;
    }
    $stmt_select->close();
} else {
    $_SESSION['error'] = "Database statement preparation failed (SELECT).";
    $error_flag = true;
}

// 5. Clean up and redirect
$conn->close();
header("location: index.php");
exit;
?>