<?php
session_start();
include 'includes/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Get all users
$users_query = "SELECT id, username, email, role, regno, status, created_at, is_online, last_seen FROM users ORDER BY created_at DESC";
$users_result = $conn->query($users_query);

// Get all messages with user information
$messages_query = "SELECT m.*, u1.username as sender_name, u2.username as receiver_name 
                   FROM messages m 
                   LEFT JOIN users u1 ON m.sender_id = u1.id 
                   LEFT JOIN users u2 ON m.receiver_id = u2.id 
                   ORDER BY m.created_at DESC 
                   LIMIT 100";
$messages_result = $conn->query($messages_query);

// Get all conversations
$conversations_query = "SELECT c.*, u1.username as user1_name, u2.username as user2_name 
                        FROM conversations c 
                        LEFT JOIN users u1 ON c.user1_id = u1.id 
                        LEFT JOIN users u2 ON c.user2_id = u2.id 
                        ORDER BY c.last_message_at DESC";
$conversations_result = $conn->query($conversations_query);

// Handle user status update
if (isset($_POST['update_status'])) {
    $user_id = $_POST['user_id'];
    $status = $_POST['status'];
    
    $update_stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $update_stmt->bind_param("si", $status, $user_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    header("Location: admin_dashboard.php");
    exit;
}

// Handle role update
if (isset($_POST['update_role'])) {
    $user_id = $_POST['user_id'];
    $role = $_POST['role'];
    
    $update_stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $update_stmt->bind_param("si", $role, $user_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    header("Location: admin_dashboard.php");
    exit;
}

// Handle delete user
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    
    // First delete user's messages and conversations to maintain referential integrity
    $delete_messages = $conn->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?");
    $delete_messages->bind_param("ii", $user_id, $user_id);
    $delete_messages->execute();
    $delete_messages->close();
    
    // Delete user's conversations
    $delete_conversations = $conn->prepare("DELETE FROM conversations WHERE user1_id = ? OR user2_id = ?");
    $delete_conversations->bind_param("ii", $user_id, $user_id);
    $delete_conversations->execute();
    $delete_conversations->close();
    
    // Finally delete the user
    $delete_user = $conn->prepare("DELETE FROM users WHERE id = ?");
    $delete_user->bind_param("i", $user_id);
    $delete_user->execute();
    $delete_user->close();
    
    header("Location: admin_dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Academic ChatConnect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }

        .header {
            background: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .school-logo {
            width: 45px;
            height: 45px;
            object-fit: contain;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
        }

        .logo-main {
            font-size: 1.4rem;
            font-weight: 700;
            color: #2c5aa0;
            line-height: 1.2;
        }

        .logo-subtitle {
            font-size: 0.8rem;
            color: #6c757d;
            font-weight: 500;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #e9ecef;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 500;
            margin-left: 20px;
        }

        .admin-info i {
            color: #007bff;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,123,255,0.3);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #1e7e34;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #007bff;
            color: #007bff;
        }

        .btn-outline:hover {
            background: #007bff;
            color: white;
        }

        .logout-btn {
            background: #6c757d;
            color: white;
        }

        .logout-btn:hover {
            background: #545b62;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section h2 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }

        .status-pending {
            color: #ffc107;
            font-weight: 600;
        }

        .status-active {
            color: #28a745;
            font-weight: 600;
        }

        .online {
            color: #28a745;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .offline {
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s ease;
        }

        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            flex-wrap: wrap;
        }

        .tab {
            padding: 12px 24px;
            cursor: pointer;
            border: 1px solid transparent;
            border-bottom: none;
            margin-right: 5px;
            border-radius: 5px 5px 0 0;
            background: #f8f9fa;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab:hover {
            background: #e9ecef;
        }

        .tab.active {
            background: white;
            border-color: #ddd;
            border-bottom: 1px solid white;
            margin-bottom: -1px;
            color: #007bff;
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6c757d;
            font-weight: 500;
        }

        .search-box {
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                padding: 15px;
            }
            
            .header-content {
                flex-direction: column;
                gap: 10px;
            }
            
            .admin-info {
                margin-left: 0;
            }
            
            .header-actions {
                justify-content: center;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                margin-right: 0;
                margin-bottom: 5px;
                border-radius: 5px;
            }
            
            table {
                font-size: 14px;
            }
            
            th, td {
                padding: 8px;
            }
            
            .school-logo {
                width: 40px;
                height: 40px;
            }
            
            .logo-main {
                font-size: 1.2rem;
            }
        }

        @media (max-width: 576px) {
            .logo-subtitle {
                display: none;
            }
            
            .btn {
                padding: 8px 15px;
                font-size: 13px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo-container">
                <img src="logo.png" alt="Federal Polytechnic Damaturu Logo" class="school-logo">
                <div class="logo-text">
                    <div class="logo-main">Academic ChatConnect</div>
                    <div class="logo-subtitle">Federal Polytechnic Damaturu</div>
                </div>
            </div>
            <div class="admin-info">
                <i class="fas fa-user-shield"></i>
                Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (Admin)
            </div>
        </div>
        <div class="header-actions">
            <a href="chat.php" class="btn btn-primary">
                <i class="fas fa-comments"></i> Go to Chat
            </a>
            <a href="index.html" class="btn btn-outline">
                <i class="fas fa-home"></i> Main Site
            </a>
            <a href="logout.php" class="btn logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="stats-cards">
            <?php
            // Get statistics
            $total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
            $active_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'")->fetch_assoc()['count'];
            $pending_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'pending'")->fetch_assoc()['count'];
            $total_messages = $conn->query("SELECT COUNT(*) as count FROM messages")->fetch_assoc()['count'];
            $online_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_online = 1")->fetch_assoc()['count'];
            ?>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_users; ?></div>
                <div class="stat-label">Total Users</div>
                <i class="fas fa-users" style="color: #007bff; font-size: 1.5rem; margin-top: 10px;"></i>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $active_users; ?></div>
                <div class="stat-label">Active Users</div>
                <i class="fas fa-user-check" style="color: #28a745; font-size: 1.5rem; margin-top: 10px;"></i>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pending_users; ?></div>
                <div class="stat-label">Pending Users</div>
                <i class="fas fa-user-clock" style="color: #ffc107; font-size: 1.5rem; margin-top: 10px;"></i>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_messages; ?></div>
                <div class="stat-label">Total Messages</div>
                <i class="fas fa-envelope" style="color: #17a2b8; font-size: 1.5rem; margin-top: 10px;"></i>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $online_users; ?></div>
                <div class="stat-label">Online Now</div>
                <i class="fas fa-circle" style="color: #28a745; font-size: 1.5rem; margin-top: 10px;"></i>
            </div>
        </div>

        <div class="tabs">
            <div class="tab active" onclick="showTab('users')">
                <i class="fas fa-users"></i> Users Management
            </div>
            <div class="tab" onclick="showTab('messages')">
                <i class="fas fa-envelope"></i> Messages
            </div>
            <div class="tab" onclick="showTab('conversations')">
                <i class="fas fa-comments"></i> Conversations
            </div>
        </div>

        <!-- Users Tab -->
        <div id="users" class="tab-content active">
            <div class="section">
                <h2><i class="fas fa-users-cog"></i> Users Management</h2>
                <div class="search-box">
                    <input type="text" id="userSearch" placeholder="Search users by username, email, or registration number..." onkeyup="searchUsers()">
                </div>
                <table id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Reg No</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Online</th>
                            <th>Last Seen</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                    <span style="color: #007bff; font-size: 0.8em; margin-left: 5px;">(You)</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['regno']); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <select name="role" onchange="this.form.submit()" style="padding: 5px; border: 1px solid #ddd; border-radius: 3px; background: white;">
                                        <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>User</option>
                                        <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                    <input type="hidden" name="update_role" value="1">
                                </form>
                            </td>
                            <td>
                                <span class="status-<?php echo $user['status']; ?>">
                                    <i class="fas fa-<?php echo $user['status'] == 'active' ? 'check-circle' : 'clock'; ?>"></i>
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="<?php echo $user['is_online'] ? 'online' : 'offline'; ?>">
                                    <i class="fas fa-<?php echo $user['is_online'] ? 'circle' : 'circle-notch'; ?>"></i>
                                    <?php echo $user['is_online'] ? 'Online' : 'Offline'; ?>
                                </span>
                            </td>
                            <td><?php echo $user['last_seen']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <?php if ($user['status'] == 'pending'): ?>
                                            <button type="submit" name="update_status" value="1" class="action-btn btn-success" title="Approve User">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <input type="hidden" name="status" value="active">
                                        <?php else: ?>
                                            <button type="submit" name="update_status" value="1" class="action-btn btn-warning" title="Suspend User">
                                                <i class="fas fa-pause"></i> Suspend
                                            </button>
                                            <input type="hidden" name="status" value="pending">
                                        <?php endif; ?>
                                    </form>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="delete_user" value="1" class="action-btn btn-danger" title="Delete User">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Messages Tab -->
        <div id="messages" class="tab-content">
            <div class="section">
                <h2><i class="fas fa-envelope-open-text"></i> Recent Messages</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Conversation ID</th>
                            <th>Sender</th>
                            <th>Receiver</th>
                            <th>Message</th>
                            <th>File</th>
                            <th>Read</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($message = $messages_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $message['id']; ?></td>
                            <td><?php echo $message['conversation_id']; ?></td>
                            <td><?php echo htmlspecialchars($message['sender_name']); ?></td>
                            <td><?php echo htmlspecialchars($message['receiver_name']); ?></td>
                            <td><?php echo htmlspecialchars(substr($message['message_text'], 0, 50)); ?>...</td>
                            <td>
                                <?php if ($message['file_name']): ?>
                                    <a href="<?php echo $message['file_path']; ?>" target="_blank" class="btn btn-outline" style="padding: 4px 8px; font-size: 12px;">
                                        <i class="fas fa-download"></i> <?php echo $message['file_name']; ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #6c757d;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($message['is_read']): ?>
                                    <span class="status-active"><i class="fas fa-check-double"></i> Read</span>
                                <?php else: ?>
                                    <span class="status-pending"><i class="fas fa-check"></i> Unread</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Conversations Tab -->
        <div id="conversations" class="tab-content">
            <div class="section">
                <h2><i class="fas fa-comments"></i> Conversations</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User 1</th>
                            <th>User 2</th>
                            <th>Created At</th>
                            <th>Last Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($conversation = $conversations_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $conversation['id']; ?></td>
                            <td><?php echo htmlspecialchars($conversation['user1_name']); ?></td>
                            <td><?php echo htmlspecialchars($conversation['user2_name']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($conversation['created_at'])); ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($conversation['last_message_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.currentTarget.classList.add('active');
        }

        function searchUsers() {
            const input = document.getElementById('userSearch');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('usersTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const tdUsername = tr[i].getElementsByTagName('td')[1];
                const tdEmail = tr[i].getElementsByTagName('td')[2];
                const tdRegno = tr[i].getElementsByTagName('td')[3];
                
                if (tdUsername || tdEmail || tdRegno) {
                    const txtValueUsername = tdUsername.textContent || tdUsername.innerText;
                    const txtValueEmail = tdEmail.textContent || tdEmail.innerText;
                    const txtValueRegno = tdRegno.textContent || tdRegno.innerText;
                    
                    if (txtValueUsername.toLowerCase().indexOf(filter) > -1 || 
                        txtValueEmail.toLowerCase().indexOf(filter) > -1 ||
                        txtValueRegno.toLowerCase().indexOf(filter) > -1) {
                        tr[i].style.display = '';
                    } else {
                        tr[i].style.display = 'none';
                    }
                }
            }
        }

        // Auto-refresh page every 30 seconds to update online status and new data
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>