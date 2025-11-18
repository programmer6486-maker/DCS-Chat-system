<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    exit('<div class="message error">Unauthorized access</div>');
}

$current_user = $_SESSION['user_id'];
$selected_user = intval($_GET['user_id']);

// Get conversation
$stmt = $conn->prepare("SELECT id FROM conversations 
                       WHERE (user1_id = ? AND user2_id = ?) 
                       OR (user2_id = ? AND user1_id = ?)");
$stmt->bind_param("iiii", $current_user, $selected_user, $current_user, $selected_user);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="no-messages">No messages yet. Start the conversation!</div>';
    $stmt->close();
    exit;
}

$conversation = $result->fetch_assoc();
$conversation_id = $conversation['id'];
$stmt->close();

// Fetch messages
$stmt = $conn->prepare("SELECT m.*, u.username 
                       FROM messages m 
                       JOIN users u ON m.sender_id = u.id 
                       WHERE m.conversation_id = ? 
                       ORDER BY m.created_at ASC");
$stmt->bind_param("i", $conversation_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="no-messages">No messages yet. Start the conversation!</div>';
} else {
    while ($row = $result->fetch_assoc()) {
        $messageClass = ($row['sender_id'] == $current_user) ? 'sent' : 'received';
        $senderName = ($row['sender_id'] == $current_user) ? 'You' : htmlspecialchars($row['username']);
        $time = date('g:i A', strtotime($row['created_at']));
        
        echo "<div class='message {$messageClass}'>";
        echo "<div class='message-header'>";
        echo "<span>{$senderName}</span>";
        echo "</div>";
        
        if (!empty($row['message_text'])) {
            echo "<div>" . nl2br(htmlspecialchars($row['message_text'])) . "</div>";
        }
        
        if (!empty($row['file_path'])) {
            $fileSize = $row['file_size'] ? formatFileSize($row['file_size']) : '';
            $fileIcon = getFileIcon($row['file_type']);
            echo "<div class='file-message'>";
            echo "<a href='{$row['file_path']}' download='" . htmlspecialchars($row['file_name']) . "' target='_blank'>";
            echo "{$fileIcon} " . htmlspecialchars($row['file_name']) . " {$fileSize}";
            echo "</a>";
            echo "</div>";
        }
        
        echo "<div class='message-time'>{$time}</div>";
        echo "</div>";
    }
}

$stmt->close();

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

function getFileIcon($fileType) {
    if (strpos($fileType, 'image/') === 0) return '🖼️';
    if (strpos($fileType, 'application/pdf') === 0) return '📄';
    if (strpos($fileType, 'text/') === 0) return '📝';
    if (strpos($fileType, 'application/msword') === 0 || strpos($fileType, 'application/vnd.openxmlformats') === 0) return '📄';
    if (strpos($fileType, 'application/vnd.ms-excel') === 0) return '📊';
    return '📎';
}
?>