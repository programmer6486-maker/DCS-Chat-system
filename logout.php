<?php
session_start();
include 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Update user status to offline
    $stmt = $conn->prepare("UPDATE users SET is_online = 0, last_seen = NOW() WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

// Destroy session
session_destroy();

// Redirect to login page
header("Location: index.php");
exit;
?>