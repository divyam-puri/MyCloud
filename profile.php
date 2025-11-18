<?php
require_once "config.php";

// Redirect if not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Profile</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <a class="navbar-brand" href="index.php">☁️ MyCloud</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="btn btn-outline-light" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </nav>
    <div class="container">
        <h1 class="my-4">User Profile</h1>
        <p class="lead">This page is reserved for managing user settings, such as changing passwords or viewing storage statistics.</p>
        <div class="card p-4">
            <h4>Username: <?php echo htmlspecialchars($_SESSION["username"]); ?></h4>
            <p>User ID: <?php echo $_SESSION["id"]; ?></p>
            <p class="text-muted">**Feature Coming Soon:** Password change, email updates, and storage usage graph.</p>
        </div>
        <p class="mt-4"><a href="index.php" class="btn btn-secondary">← Back to Dashboard</a></p>
    </div>
</body>
</html>