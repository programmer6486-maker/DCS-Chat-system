<?php
session_start();
include 'includes/db.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
$sender_id = $_SESSION['user_id'];
$receiver_id = intval($_POST['receiver_id']);
$message_text = trim($_POST['message']);
$file_name = $file_path = $file_size = $file_type = '';

// Validate receiver exists
$stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
$stmt->bind_param("i", $receiver_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    header('HTTP/1.1 400 Bad Request');
    exit(json_encode(['success' => false, 'error' => 'Receiver not found']));
}
$stmt->close();

// Handle file upload if present
if (isset($_FILES['file']) && !empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = "uploads/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_name = basename($_FILES["file"]["name"]);
    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $safe_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $file_name);
    $file_path = $upload_dir . $safe_filename;
    $file_size = $_FILES["file"]["size"];
    $file_type = $_FILES["file"]["type"];
    
    // Validate file size (max 10MB)
    if ($file_size > 10 * 1024 * 1024) {
        header('HTTP/1.1 400 Bad Request');
        exit(json_encode(['success' => false, 'error' => 'File too large (max 10MB)']));
    }
    
    // Validate file type
    $allowed_types = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 
        'text/plain',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/x-rar-compressed'
    ];
    
    if (!in_array($file_type, $allowed_types)) {
        header('HTTP/1.1 400 Bad Request');
        exit(json_encode(['success' => false, 'error' => 'Invalid file type']));
    }
    
    if (!move_uploaded_file($_FILES["file"]["tmp_name"], $file_path)) {
        header('HTTP/1.1 500 Internal Server Error');
        exit(json_encode(['success' => false, 'error' => 'File upload failed']));
    }
}

// Check if both message and file are empty
if (empty($message_text) && empty($file_name)) {
    header('HTTP/1.1 400 Bad Request');
    exit(json_encode(['success' => false, 'error' => 'Message or file is required']));
}

// Get or create conversation
$stmt = $conn->prepare("SELECT id FROM conversations 
                       WHERE (user1_id = ? AND user2_id = ?) 
                       OR (user2_id = ? AND user1_id = ?)");
$stmt->bind_param("iiii", $sender_id, $receiver_id, $sender_id, $receiver_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $conversation = $result->fetch_assoc();
    $conversation_id = $conversation['id'];
} else {
    $stmt = $conn->prepare("INSERT INTO conversations (user1_id, user2_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $sender_id, $receiver_id);
    if ($stmt->execute()) {
        $conversation_id = $conn->insert_id;
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        exit(json_encode(['success' => false, 'error' => 'Failed to create conversation']));
    }
}
$stmt->close();

// Insert message (can have text, file, or both)
$stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, message_text, file_name, file_path, file_size, file_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iisssis", $conversation_id, $sender_id, $message_text, $file_name, $file_path, $file_size, $file_type);

if ($stmt->execute()) {
    // Update conversation last message time
    $update_stmt = $conn->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = ?");
    $update_stmt->bind_param("i", $conversation_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    // Update sender's last seen
    $update_user = $conn->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
    $update_user->bind_param("i", $sender_id);
    $update_user->execute();
    $update_user->close();
    
    echo json_encode(['success' => true]);
} else {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
?>