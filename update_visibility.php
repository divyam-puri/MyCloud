<?php
require_once "config.php";

header('Content-Type: application/json');

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$user_id = $_SESSION["id"];
$file_id = $_POST['file_id'] ?? null;
$visibility = $_POST['visibility'] ?? null;

// Validate input
if (!$file_id || !in_array($visibility, ['private', 'public'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid file ID or visibility setting.']);
    exit;
}

// Prepare the update statement: Must verify ownership (user_id)
$sql = "UPDATE files SET visibility = ? WHERE id = ? AND user_id = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("sii", $visibility, $file_id, $user_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Visibility updated successfully.']);
        } else {
            // This happens if the user ID doesn't match the file ID (owner check failed)
            echo json_encode(['success' => false, 'message' => 'File not found or access denied.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare database statement.']);
}

$conn->close();
exit;
?>