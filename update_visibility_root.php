<?php
require_once "config.php";

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Authentication failed.']);
    exit;
}

$user_id = $_SESSION["id"];
$username = $_SESSION["username"];
$type = $_POST['type'] ?? '';
$visibility = $_POST['visibility'] ?? '';

// Validate inputs
if (!in_array($type, ['file', 'folder'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid type specified.']);
    exit;
}
if (!in_array($visibility, ['private', 'public'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid visibility setting.']);
    exit;
}

$conn->begin_transaction(); // Start transaction for safety

try {
    if ($type === 'file') {
        $file_id = $_POST['id'] ?? null;
        if (!$file_id) throw new Exception("File ID is missing.");

        // Update the file visibility in the database (only if it's in the root folder)
        $sql = "UPDATE files SET visibility = ? WHERE id = ? AND user_id = ? AND file_folder_path = ''";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sii", $visibility, $file_id, $user_id);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                throw new Exception("File not found, access denied, or file is not in the root directory.");
            }
            $stmt->close();
        } else {
            throw new Exception("Database prepare failed for file update.");
        }
        $message = "File visibility updated to " . $visibility;

    } elseif ($type === 'folder') {
        $folder_name = trim($_POST['name'] ?? '');
        if (empty($folder_name)) throw new Exception("Folder name is missing.");

        $target_dir = __DIR__ . "/uploads/" . $username . "/" . $folder_name;
        $visibility_file = $target_dir . '/.visibility.txt';

        // Update visibility on the filesystem
        if (!is_dir($target_dir)) {
             throw new Exception("Folder not found on server.");
        }

        if (file_put_contents($visibility_file, $visibility) === false) {
            throw new Exception("Failed to update folder visibility file. Check permissions.");
        }
        $message = "Folder visibility updated to " . $visibility;
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
exit;
?>