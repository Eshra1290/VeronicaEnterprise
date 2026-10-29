<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_password = $_POST['new_password'];
    $token = $_GET['token'];

    require_once 'db_connect.php';  

    // Check if the reset token exists
    $sql = "SELECT * FROM users WHERE reset_token=?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        die('Error preparing query: ' . $conn->error);
    }

    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();

        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update the password in the database
        $sql = "UPDATE users SET password=?, reset_token=NULL WHERE reset_token=?";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            die('Error preparing query: ' . $conn->error);
        }

        $stmt->bind_param("ss", $hashed_password, $token);
        $stmt->execute();

        // Redirect to the login page after resetting the password
        header("Location: login.php");
        exit();
    } else {
        $error_message = "Invalid or expired token!";
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reset Password</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <style>
    body, html {
      margin: 0;
      padding: 0;
      height: 100%;
      display: flex;
      justify-content: center;
      align-items: center;
      background: transparent;
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
    .card {
      background: #fff;
      padding: 30px;
      border-radius: 10px;
      max-width: 800px;
      width: 90%;
      box-shadow: 0 4px 8px rgba(0,0,0,0.2);
      z-index: 1;
      text-align: center;
    }
    .card img {
      display: block;
      margin: 0 auto 20px auto;
      width: 150px;
      height: 100px;
      object-fit: cover;
    }
    .form-label {
      font-weight: bold;
    }
    .text-danger {
      color: red;
      margin-top: 10px;
    }
    .button-container {
      display: flex;
      flex-direction: column;
      gap: 15px;
    }
  </style>
</head>
<body>

<!-- Background video -->
<video autoplay loop muted>
  <source src="videos/login_background.mp4" type="video/mp4">
</video>

<!-- Reset form container -->
<div class="card">
  <img src="images/logo.jpg" alt="Logo">
  <h2 class="mb-4">Reset Password</h2>

  <form method="POST">
    <div class="mb-3">
      <label for="new_password" class="form-label">New Password</label>
      <input type="password" name="new_password" id="new_password" class="form-control" required>
    </div>

    <div class="button-container">
      <button type="submit" class="btn btn-primary w-100">Reset Password</button>
      <a href="login.php" class="btn btn-secondary w-100">Cancel</a>
    </div>
  </form>

  <?php if (!empty($error_message)): ?>
    <p class="text-danger text-center mt-3"><?php echo htmlspecialchars($error_message); ?></p>
  <?php endif; ?>
</div>

</body>
</html>
