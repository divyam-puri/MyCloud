<?php
require_once "config.php";

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["id"];
$username = $_SESSION["username"];
$file_list = [];
$error = "";


// --- 1. FOLDER NAVIGATION & SETUP ---
$current_path_encoded = $_GET['path'] ?? '';
$current_path = urldecode($current_path_encoded);

if (strpos($current_path, '..') !== false) {
    $current_path = '';
}

// Clean and normalize the path
$current_path_db = trim($current_path, '/');
$current_path_fs = ($current_path_db === '') ? '' : $current_path_db . '/';
$is_root = ($current_path_db === ''); // New variable for easy root check

// Base directory for the user
$base_storage_dir = __DIR__ . "/uploads/" . $username . "/";
$current_storage_dir = $base_storage_dir . $current_path_fs;

if (!is_dir($base_storage_dir)) {
    if (!mkdir($base_storage_dir, 0777, true)) {
        $error = "CRITICAL ERROR: Failed to create user storage directory.";
    }
}

if ($current_path_db !== '' && !is_dir($current_storage_dir)) {
    header("location: index.php");
    exit;
}
// --- END FOLDER NAVIGATION SETUP ---


// --- 2. DATA FETCHING ---
$param_path = $current_path_db;
$param_user_id = $user_id;

$sql = "SELECT id, original_filename, file_size, upload_date, visibility FROM files WHERE user_id = ? AND file_folder_path = ? ORDER BY upload_date DESC";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("is", $param_user_id, $param_path);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $file_list[] = $row;
        }
    } else {
        $error = "ERROR: Could not fetch files. " . $conn->error;
    }
    $stmt->close();
}


// --- 3. FOLDER LISTING ---
$folders = [];
if (is_dir($current_storage_dir)) {
    $items = scandir($current_storage_dir);
    foreach ($items as $item) {
        if ($item !== '.' && $item !== '..' && is_dir($current_storage_dir . $item) && $item[0] !== '.') {
            $folders[] = $item;
        }
    }
}


// --- 4. UTILITY FUNCTIONS AND MESSAGING ---
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function getFolderVisibility($folder_path) {
    if (file_exists($folder_path . '/.visibility.txt')) {
        return trim(file_get_contents($folder_path . '/.visibility.txt'));
    }
    return 'private';
}

function getParentVisibility($current_path_db, $username) {
    if ($current_path_db === '') return 'private'; 
    
    $parent_dir_path = __DIR__ . "/uploads/" . $username . "/" . $current_path_db;
    $visibility_file = $parent_dir_path . '/.visibility.txt';
    return file_exists($visibility_file) ? trim(file_get_contents($visibility_file)) : 'private';
}

$inherited_visibility = getParentVisibility($current_path_db, $username);


// Check for and display session messages (Success/Error)
$success_msg = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_msg = isset($_SESSION['error']) ? $_SESSION['error'] : null;
$upload_error_msg = isset($_SESSION['upload_error']) ? $_SESSION['upload_error'] : null;

// Clear session messages after displaying them
unset($_SESSION['success']);
unset($_SESSION['error']);
unset($_SESSION['upload_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cloud Dashboard - <?php echo htmlspecialchars($username); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <a class="navbar-brand" href="#">☁️ MyCloud</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <span class="navbar-text mr-3">
                        Welcome, <b><?php echo htmlspecialchars($username); ?></b>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="btn btn-outline-light" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container mt-5">
        <h1 class="my-4 pt-3">File Dashboard</h1>
        
        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $success_msg; ?><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
        <?php endif; ?>
        <?php if ($error_msg || $upload_error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $error_msg . $upload_error_msg; ?><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-<?php echo $is_root ? 8 : 12; ?> mt-5">
                <div class="file-upload-section shadow-sm">
                    <h3>Upload New File 
                        <?php if (!$is_root): ?>
                            (Inherits: 
                            <span class="badge badge-<?php echo ($inherited_visibility === 'public' ? 'info' : 'secondary'); ?>">
                                <?php echo ucfirst($inherited_visibility); ?>
                            </span>)
                        <?php endif; ?>
                    </h3>
                    <form action="upload_file.php" method="post" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="col-md-<?php echo $is_root ? 7 : 9; ?> form-group">
                                <div class="custom-file">
                                    <input type="file" name="uploaded_file" class="custom-file-input" id="customFile" required>
                                    <label class="custom-file-label" for="customFile">Choose file</label>
                                </div>
                            </div>
                            
                            <?php if ($is_root): // Only show visibility dropdown in root ?>
                                <div class="col-md-3 form-group">
                                    <select name="visibility" class="form-control" required>
                                        <option value="private" selected>Private</option>
                                        <option value="public">Public</option>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <div class="col-md-<?php echo $is_root ? 2 : 3; ?> form-group">
                                <button type="submit" class="btn btn-success btn-block">Upload</button>
                            </div>
                        </div>
                        <input type="hidden" name="current_path" value="<?php echo htmlspecialchars($current_path_db); ?>">
                    </form>
                </div>
            </div>
            
            <div class="col-md-<?php echo $is_root ? 4 : 12; ?>">
                <div class="file-upload-section shadow-sm">
                    <h3>Create Folder
                        <?php if (!$is_root): ?>
                            (Inherits: 
                            <span class="badge badge-<?php echo ($inherited_visibility === 'public' ? 'info' : 'secondary'); ?>">
                                <?php echo ucfirst($inherited_visibility); ?>
                            </span>)
                        <?php endif; ?>
                    </h3>
                    <form action="create_folder.php" method="post">
                        <div class="form-group">
                            <input type="text" name="folder_name" class="form-control" placeholder="Folder Name" required pattern="[a-zA-Z0-9_ -]+" title="Letters, numbers, spaces, underscores, and hyphens only">
                        </div>
                        
                        <?php if ($is_root): // Only show visibility dropdown in root ?>
                            <div class="form-group">
                                <select name="visibility" class="form-control" required>
                                    <option value="private" selected>Private (Default)</option>
                                    <option value="public">Public (Shareable)</option>
                                </select>
                            </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-primary btn-block">Create</button>
                        <input type="hidden" name="current_path" value="<?php echo htmlspecialchars($current_path_db); ?>">
                    </form>
                </div>
            </div>
        </div>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">MyCloud Root</a></li>
                <?php
                $path_parts = array_filter(explode('/', $current_path));
                $cumulative_path = '';
                foreach ($path_parts as $part) {
                    $cumulative_path .= urlencode($part) . '/';
                    $breadcrumb_path = trim($cumulative_path, '/');
                    echo '<li class="breadcrumb-item"><a href="index.php?path=' . urlencode($breadcrumb_path) . '">' . htmlspecialchars($part) . '</a></li>';
                }
                ?>
                <?php if ($current_path_db !== ''): ?>
                    <li class="breadcrumb-item active" aria-current="page">Contents</li>
                <?php endif; ?>
            </ol>
        </nav>
        
        <?php 
        $parent_path = dirname($current_path_db);
        if ($parent_path === '.') $parent_path = '';
        
        ?>
        <?php if ($current_path_db !== ''): ?>
            <a href="index.php?path=<?php echo urlencode($parent_path); ?>" class="btn btn-sm btn-secondary mb-3"><i class="fas fa-level-up-alt"></i> Back to Parent</a>
        <?php endif; ?>

        <h2 class="mt-5">Storage Contents (<?php echo $current_path_db === '' ? 'Root' : htmlspecialchars($current_path_db); ?>)</h2>

        <table class="table table-striped table-hover shadow-sm">
            <thead class="thead-dark">
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Type</th>
                    <th scope="col">Size / Created</th>
                    <th scope="col">Visibility</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($folders) > 0): ?>
                    <?php foreach ($folders as $folder_name): 
                        $folder_path = $current_storage_dir . $folder_name;
                        $folder_visibility = getFolderVisibility($folder_path);
                        $next_path = $current_path_db === '' ? $folder_name : $current_path_db . '/' . $folder_name;
                    ?>
                    <tr>
                        <td><i class="fas fa-folder text-warning mr-2"></i> <strong><?php echo htmlspecialchars($folder_name); ?></strong></td>
                        <td>Folder</td>
                        <td><?php echo date("M d, Y H:i", filectime($folder_path)); ?></td>
                        <td>
                            <?php if ($is_root): ?>
                            <select class="form-control form-control-sm visibility-switch-folder" data-folder-name="<?php echo htmlspecialchars($folder_name); ?>">
                                <option value="private" <?php echo ($folder_visibility === 'private' ? 'selected' : ''); ?>>Private</option>
                                <option value="public" <?php echo ($folder_visibility === 'public' ? 'selected' : ''); ?>>Public</option>
                            </select>
                            <?php else: ?>
                            <span class="badge badge-<?php echo ($folder_visibility === 'public' ? 'info' : 'secondary'); ?>">
                                <?php echo ucfirst($folder_visibility); ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="file-actions">
                            <a href="index.php?path=<?php echo urlencode($next_path); ?>" class="btn btn-sm btn-primary">Open</a>
                            
                            <?php if ($folder_visibility === 'public'): ?>
                                <button type="button" class="btn btn-sm btn-info" onclick="copyFolderLink('<?php echo urlencode($folder_name); ?>')">Share</button>
                            <?php endif; ?>
                            <a href="delete_folder.php?folder_name=<?php echo urlencode($folder_name); ?>&current_path=<?php echo urlencode($current_path_db); ?>" class="btn btn-sm btn-danger delete-btn">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (count($file_list) > 0): ?>
                    <?php foreach ($file_list as $file): ?>
                    <tr>
                        <td><i class="fas fa-file mr-2"></i> <?php echo htmlspecialchars($file['original_filename']); ?></td>
                        <td>File</td>
                        <td><?php echo formatBytes($file['file_size']); ?></td>
                        <td>
                            <?php if ($is_root): ?>
                            <select class="form-control form-control-sm visibility-switch-file" data-file-id="<?php echo $file['id']; ?>">
                                <option value="private" <?php echo ($file['visibility'] === 'private' ? 'selected' : ''); ?>>Private</option>
                                <option value="public" <?php echo ($file['visibility'] === 'public' ? 'selected' : ''); ?>>Public</option>
                            </select>
                            <?php else: ?>
                            <span class="badge badge-<?php echo ($file['visibility'] === 'public' ? 'info' : 'secondary'); ?>">
                                <?php echo ucfirst($file['visibility']); ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="file-actions">
                            <a href="download_file.php?file_id=<?php echo $file['id']; ?>" class="btn btn-sm btn-info">Download</a>
                            <?php if ($file['visibility'] === 'public'): ?>
                                <button type="button" class="btn btn-sm btn-primary" onclick="copyLink(<?php echo $file['id']; ?>)">Share</button>
                            <?php endif; ?>
                            <a href="delete_file.php?file_id=<?php echo $file['id']; ?>&current_path=<?php echo urlencode($current_path_db); ?>" class="btn btn-sm btn-danger delete-btn">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php if (count($folders) == 0): ?>
                    <tr>
                        <td colspan="5" class="text-center">No files or folders in this directory.</td>
                    </tr>
                    <?php endif; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/scripts.js"></script>
    <script>
    // --- NEW AJAX FUNCTIONS ---

    // Generic function to send AJAX requests
    function sendVisibilityUpdate(url, data) {
        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    window.location.reload(); 
                } else {
                    alert("Error: " + response.message);
                }
            },
            error: function(xhr) {
                alert("A server error occurred. Status: " + xhr.status);
                window.location.reload(); 
            }
        });
    }

    // File Visibility Change Handler
    $(document).on('change', '.visibility-switch-file', function() {
        const fileId = $(this).data('file-id');
        const newVisibility = $(this).val();
        
        sendVisibilityUpdate('update_visibility_root.php', {
            type: 'file',
            id: fileId,
            visibility: newVisibility
        });
    });

    // Folder Visibility Change Handler
    $(document).on('change', '.visibility-switch-folder', function() {
        const folderName = $(this).data('folder-name');
        const newVisibility = $(this).val();
        
        sendVisibilityUpdate('update_visibility_root.php', {
            type: 'folder',
            name: folderName,
            visibility: newVisibility
        });
    });
    
    // --- END NEW AJAX FUNCTIONS ---


    // File input label update
    $('#customFile').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName || 'Choose file');
    });

    // JavaScript function to copy the public file link
    function copyLink(fileId) {
        const baseURL = window.location.origin;
        const publicLink = `${baseURL}/download_file.php?file_id=${fileId}`;
        const tempInput = document.createElement('input');
        tempInput.value = publicLink;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        alert("Public file link copied to clipboard: " + publicLink);
    }
    
    // JavaScript function to copy the public folder link
    function copyFolderLink(folderName) {
        const baseURL = window.location.origin;
        const currentPath = '<?php echo urlencode($current_path_db); ?>';
        const separator = currentPath ? '/' : '';
        const fullFolderPath = currentPath + separator + folderName; 
        
        const publicLink = `http://localhost/cloud/view_folder.php?user=<?php echo urlencode($username); ?>&path=${fullFolderPath}`;

        const tempInput = document.createElement('input');
        tempInput.value = publicLink;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);

        alert("Public folder link copied to clipboard: " + publicLink);
    }
    </script>
</body>
</html>