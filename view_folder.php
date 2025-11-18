<?php
// Note: We include config.php to establish the database connection, but we do NOT start a session here
// because this page must be accessible by non-logged-in users.
require_once "config.php";

$owner_username = $_GET['user'] ?? null;
$folder_path_raw = $_GET['path'] ?? null;

$error = '';
$file_list = [];
$folders = [];
$folder_visibility = 'private'; // Assume private by default

// Basic validation
if (!$owner_username || !$folder_path_raw) {
    $error = "Invalid share link. Owner or path is missing.";
} else {
    // 1. Sanitize and normalize path
    $folder_path_db = trim($folder_path_raw, '/');
    if (strpos($folder_path_db, '..') !== false) {
        $error = "Invalid path detected.";
    }
    
    // Determine the owner's ID
    $owner_id = null;
    $sql_owner = "SELECT id FROM users WHERE username = ?";
    if ($stmt_owner = $conn->prepare($sql_owner)) {
        $stmt_owner->bind_param("s", $owner_username);
        $stmt_owner->execute();
        $result_owner = $stmt_owner->get_result();
        if ($row_owner = $result_owner->fetch_assoc()) {
            $owner_id = $row_owner['id'];
        }
        $stmt_owner->close();
    }
    
    if (!$owner_id) {
        $error = "Owner user not found.";
    }
}

if (empty($error)) {
    // 2. Determine Folder Visibility
    $owner_base_dir = __DIR__ . "/uploads/" . $owner_username . "/";
    $current_dir = $owner_base_dir . $folder_path_db;

    if (!is_dir($current_dir)) {
        $error = "The specified folder does not exist.";
    } else {
        $visibility_file = $current_dir . '/.visibility.txt';
        $folder_visibility = file_exists($visibility_file) ? trim(file_get_contents($visibility_file)) : 'private';

        // 3. ENFORCE ACCESS: If folder is not public, deny access immediately
        if ($folder_visibility !== 'public') {
            $error = "Access Denied. This folder is private.";
        }
    }
}

if (empty($error)) {
    // 4. Fetch PUBLIC files inside this folder (even if the folder is public, the file might be private)
    $sql_files = "SELECT id, original_filename, file_size, upload_date, visibility FROM files 
                  WHERE user_id = ? AND file_folder_path = ? AND visibility = 'public' 
                  ORDER BY original_filename ASC";
                  
    if ($stmt_files = $conn->prepare($sql_files)) {
        $stmt_files->bind_param("is", $owner_id, $folder_path_db);
        if ($stmt_files->execute()) {
            $result_files = $stmt_files->get_result();
            while ($row = $result_files->fetch_assoc()) {
                $file_list[] = $row;
            }
        }
        $stmt_files->close();
    }
    
    // 5. List PUBLIC sub-folders
    $items = scandir($current_dir);
    $folder_path_fs = ($folder_path_db === '') ? '' : $folder_path_db . '/';
    
    foreach ($items as $item) {
        $item_path = $current_dir . '/' . $item;
        if ($item !== '.' && $item !== '..' && is_dir($item_path) && $item[0] !== '.') {
            $visibility_file = $item_path . '/.visibility.txt';
            $subfolder_visibility = file_exists($visibility_file) ? trim(file_get_contents($visibility_file)) : 'private';
            
            if ($subfolder_visibility === 'public') {
                $folders[] = [
                    'name' => $item,
                    'path' => $folder_path_db === '' ? $item : $folder_path_db . '/' . $item
                ];
            }
        }
    }
}

// Utility function (duplicated here since we didn't include index.php)
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shared Folder: <?php echo htmlspecialchars($folder_path_raw); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { padding-top: 60px; background-color: #f8f9fa; }
        .container { max-width: 900px; }
        .navbar-brand { font-weight: bold; }
        .file-icon { color: #007bff; }
        .folder-icon { color: #ffc107; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <a class="navbar-brand" href="#">☁️ Public Shared Folder</a>
    </nav>

    <div class="container">
        <h1 class="my-4">
            Viewing: <?php echo htmlspecialchars($folder_path_raw); ?>
        </h1>
        <p class="text-muted">Shared by: <strong><?php echo htmlspecialchars($owner_username); ?></strong></p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php else: ?>
        
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="view_folder.php?user=<?php echo urlencode($owner_username); ?>&path=">Root</a></li>
                    <?php
                    $path_parts = array_filter(explode('/', $folder_path_db));
                    $cumulative_path = '';
                    foreach ($path_parts as $part) {
                        $cumulative_path .= urlencode($part) . '/';
                        $breadcrumb_path = trim($cumulative_path, '/');
                        echo '<li class="breadcrumb-item"><a href="view_folder.php?user=' . urlencode($owner_username) . '&path=' . urlencode($breadcrumb_path) . '">' . htmlspecialchars($part) . '</a></li>';
                    }
                    ?>
                </ol>
            </nav>

            <table class="table table-striped table-hover shadow-sm">
                <thead class="thead-dark">
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Type</th>
                        <th scope="col">Size</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($folders) == 0 && count($file_list) == 0): ?>
                        <tr>
                            <td colspan="4" class="text-center">This folder contains no public files or sub-folders.</td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php foreach ($folders as $folder): ?>
                        <tr>
                            <td><i class="fas fa-folder folder-icon mr-2"></i> <strong><?php echo htmlspecialchars($folder['name']); ?></strong></td>
                            <td>Folder</td>
                            <td>-</td>
                            <td>
                                <a href="view_folder.php?user=<?php echo urlencode($owner_username); ?>&path=<?php echo urlencode($folder['path']); ?>" class="btn btn-sm btn-primary">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php foreach ($file_list as $file): ?>
                        <tr>
                            <td><i class="fas fa-file file-icon mr-2"></i> <?php echo htmlspecialchars($file['original_filename']); ?></td>
                            <td>File</td>
                            <td><?php echo formatBytes($file['file_size']); ?></td>
                            <td>
                                <a href="download_file.php?file_id=<?php echo $file['id']; ?>&public_access=true" class="btn btn-sm btn-success">Download</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>