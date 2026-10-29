<?php 

session_start();

require_once 'db_connect.php';  

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];


    // Fetch user data based on the entered username
    $sql = "SELECT * FROM users WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if the username exists
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();

        // Verify the password
        if (password_verify($password, $row['password'])) {
            // Store user data in session variables
            $_SESSION['role'] = $row['role'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['worker_id'] = $row['worker_id']; // Add worker_id to the session

            // Redirect to the appropriate dashboard based on the role
            if ($row['role'] == 'admin') {
                    $_SESSION['admin_id'] = $row['worker_id']; 
                header("Location: admin_dashboard.php");
            } elseif ($row['role'] == 'worker') {
                header("Location: worker_dashboard.php");
            }
        } else {
            $error_message = "Incorrect password!";
        }
    } else {
        $error_message = "No user found!";
    }

    $stmt->close();

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: transparent;
            overflow: hidden;
        }
        video {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -1;
        }
        .login-container {
            background-color: rgb(255, 255, 255);
            padding: 20px;
            border-radius: 10px;
            width: 100%;
            max-width: 400px;
            z-index: 1;
            text-align: center;
        }
        .login-container img {
            width: 150px;
            height: 100px;
            object-fit: cover;
            border-radius: 0%;
            margin-bottom: 20px;
        }
        .login-container h2 {
            margin-bottom: 20px;
        }
        .login-container .form-label {
            font-weight: bold;
        }
        .login-container .btn {
            width: 100%;
        }
        .login-container p {
            margin-top: 20px;
        }
        .text-danger {
            color: red;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <!-- Video background -->
    <video autoplay loop muted>
        <source src="videos/login_background.mp4" type="video/mp4">
    </video>

    <!-- Login form -->
    <div class="login-container">
        <img src="images/logo.jpg" alt="Enterprise Logo"> 
        <h2>Veronica Enterprise Oil Palm Plantation Worker Management System - Login</h2>
        <form method="POST" action="">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" id="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
        <hr>
        <p>Don't have an account? <a href="signup.php">Sign up here</a></p>
        <?php if (!empty($error_message)): ?>
            <p class="text-danger"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <!-- Forgot password link -->
<p><a href="ForgotPassword.php">Forgot Password?</a></p>

    </div>
</body>
</html>
