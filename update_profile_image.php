<?php
session_start();
include 'includes/db.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
$user_id = $_SESSION['user_id'];

if (!empty($_FILES['profile_image']['name']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = "uploads/profiles/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file = $_FILES['profile_image'];
    $file_name = basename($file["name"]);
    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $safe_filename = 'profile_' . $user_id . '_' . uniqid() . '.' . $file_extension;
    $file_path = $upload_dir . $safe_filename;
    $file_size = $file["size"];
    $file_type = $file["type"];
    
    // Validate file size (max 5MB for profile images)
    if ($file_size > 5 * 1024 * 1024) {
        header('HTTP/1.1 400 Bad Request');
        exit(json_encode(['success' => false, 'error' => 'File too large (max 5MB)']));
    }
    
    // Validate file type (only images)
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file_type, $allowed_types)) {
        header('HTTP/1.1 400 Bad Request');
        exit(json_encode(['success' => false, 'error' => 'Only JPEG, PNG, GIF, and WebP images are allowed']));
    }
    
    // Delete old profile picture if exists
    $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($old_profile_picture);
    $stmt->fetch();
    $stmt->close();
    
    if ($old_profile_picture && file_exists($old_profile_picture)) {
        unlink($old_profile_picture);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file["tmp_name"], $file_path)) {
        // Update database
        $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        $stmt->bind_param("si", $file_path, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'profile_picture' => $file_path]);
        } else {
            // Delete the uploaded file if database update fails
            unlink($file_path);
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['success' => false, 'error' => 'Database update failed']);
        }
        $stmt->close();
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['success' => false, 'error' => 'File upload failed']);
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
}
?>