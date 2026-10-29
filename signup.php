<?php
date_default_timezone_set('Asia/Kuala_Lumpur');   // Malaysian time

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name             = trim($_POST['name']);
    $username         = trim($_POST['username']);
    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $contact          = trim($_POST['contact']);

    if ($password !== $confirm_password) {
        $error_message = "Passwords do not match!";
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $created_at      = date('Y-m-d H:i:s');   // current MYT timestamp

        require_once 'db_connect.php';
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $ins = $conn->prepare(
              "INSERT INTO users (name, username, password, contact, role, created_at)
               VALUES (?, ?, ?, ?, 'worker', ?)"
               
            );
            $ins->bind_param('sssss', $name, $username, $hashed_password, $contact, $created_at);
           
            error_log("DEBUG signup: user=$username pwd=$hashed_password contact=$contact created=$created_at");

 
            $ins->execute();

            $success_message = "Signup successful! You can now <a href='login.php'>login</a>";
            $ins->close();
        } catch (mysqli_sql_exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
        $conn->close();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
 body, html {
     margin: 0;
     padding: 0;
     min-height: 100vh;          /* keep full-height video */
     display: flex;
     justify-content: center;
     align-items: flex-start;    /* top-align so you can scroll */
     background-color: transparent;
     overflow-y: auto;           /* enable vertical scrolling */
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
        .signup-container {
            background-color: #ffffff; /* Solid white background */
            padding: 30px;
            border-radius: 10px;
            width: 100%;
            max-width: 800px; /* Widened the box */
            z-index: 1;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Added shadow for a polished look */
        }
        .signup-container img {
            width: 150px;
            height: 100px;
            object-fit: cover;
            border-radius: 0%;
            margin-bottom: 20px;
            display: block;
            margin: 0 auto;

        }
        .signup-container h2 {
            margin-bottom: 20px;
            text-align: center; /* Center the text */
        }
        .signup-container .form-label {
            font-weight: bold;
        }
        .signup-container .btn {
            width: 100%;
        }
        .signup-container p {
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

    <!-- Signup form -->
    <div class="signup-container">
        <img src="images/logo.jpg" alt="Logo">
        <h2>Veronica Enterprise Oil Palm Plantation Worker Management System - Signup</h2>
        <form method="POST" action="">
            <div class="mb-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" name="name" id="name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" id="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="contact" class="form-label">Contact</label>
                <input type="text" name="contact" id="contact" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Signup</button>
        </form>

        <?php if (!empty($error_message)): ?>
            <p class="text-danger text-center mt-3"><?php echo $error_message; ?></p>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <p class="text-success text-center mt-3"><?php echo $success_message; ?></p>
        <?php endif; ?>

        <!-- Back to Login Button -->
        <div class="text-center mt-3">
            <a href="login.php" class="btn btn-secondary">Back to Login</a>
        </div>
    </div>
</body>
</html>
