<?php
session_start();
include 'includes/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $regno = trim($_POST['regno']); // Added registration number field

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($regno)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        // Check if user already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? OR regno = ?");
        $stmt->bind_param("sss", $username, $email, $regno);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = 'Username, email, or registration number already exists';
        } else {
            // Hash password and create user with pending status
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'user'; // Default role
            $status = 'pending'; // Default status - admin needs to verify
            
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, regno, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $username, $email, $hashed_password, $role, $regno, $status);

            if ($stmt->execute()) {
                $success = "Registration successful! Please wait for admin verification. <a href='index.php' style='color: #007bff;'>Login here</a> once approved.";
            } else {
                $error = "Error: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Chat System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .form-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }

        h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 20px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }

        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        button:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }

        .login-link a {
            color: #007bff;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        @media (max-width: 480px) {
            .form-container {
                padding: 30px 20px;
            }
            
            body {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Create Account</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php else: ?>
            <!-- <div class="alert alert-info">
                Note: Your account will be pending until approved by admin.
            </div> -->
            <form method="POST">
                <div class="form-group">
                    <input type="text" name="username" placeholder="Username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <input type="email" name="email" placeholder="Email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <input type="text" name="regno" placeholder="Registration Number" value="<?php echo isset($_POST['regno']) ? htmlspecialchars($_POST['regno']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                
                <div class="form-group">
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                </div>
                
                <button type="submit">Register</button>
            </form>
        <?php endif; ?>
        
        <div class="login-link">
            Already have an account? <a href="index.php">Login here</a>
        </div>
        <div class="login-link">
            <a href="index.html">← Back to Home</a>
        </div>
    </div>
</body>
</html>