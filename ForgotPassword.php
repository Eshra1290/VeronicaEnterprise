<?php
/* ------------------------------------------------------------------
   1. Handle the form
------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* normalise inputs */
    $username_in = strtolower(trim($_POST['username']));           // e.g. " Anthon " → "anthon"
    $phone_in    = preg_replace('/\D/', '', $_POST['phone']);      // keep digits only

    require_once 'db_connect.php';

    /* ----------------------------------------------------------------
       2. Fetch one row by TRIM-med, lower-cased username
          (works even if the DB value has trailing blanks)
    ---------------------------------------------------------------- */
    $sql = "
        SELECT TRIM(contact) AS phone
        FROM   users
        WHERE  LOWER(TRIM(username)) = ?
        LIMIT  1
    ";
    $stmt = $conn->prepare($sql) or die('Prepare failed: '.$conn->error);
    $stmt->bind_param('s', $username_in);
    $stmt->execute();
    $stmt->bind_result($phone_db_raw);
    $user_found = $stmt->fetch();
    $stmt->close();

    /* ----------------------------------------------------------------
       3. Compare digits only
    ---------------------------------------------------------------- */
    if ($user_found) {
        $phone_db = preg_replace('/\D/', '', $phone_db_raw);       // digits only
        if ($phone_in === $phone_db) {

            /* ----------------------------------------------------------------
               4. Generate token --> redirect
            ---------------------------------------------------------------- */
            $token = bin2hex(random_bytes(16));
            $up = $conn->prepare("
                    UPDATE users
                    SET reset_token = ?
                    WHERE LOWER(TRIM(username)) = ?
            ") or die('Prepare failed: '.$conn->error);
            $up->bind_param('ss', $token, $username_in);
            $up->execute();
            $up->close();

            header("Location: resetPassword.php?token=$token");
            exit();
        }
    }

    /* If we reach here: mismatch */
    $error_message = "Invalid username or phone number!";
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Forgot Password</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body,html{margin:0;padding:0;height:100%;display:flex;justify-content:center;align-items:center;background:transparent;overflow:hidden}
video{position:fixed;top:0;left:0;width:100%;height:100%;object-fit:cover;z-index:-1}
.card{background:#fff;padding:30px;border-radius:10px;max-width:800px;width:90%;box-shadow:0 4px 8px rgba(0,0,0,.2);z-index:1}
.card img{display:block;margin:0 auto 20px auto;width:150px;height:100px;object-fit:cover}
.text-danger{color:red;margin-top:10px}
</style>
</head>
<body>

<!-- video background (same as signup) -->
<video autoplay loop muted>
  <source src="videos/login_background.mp4" type="video/mp4">
</video>

<div class="card">
  <img src="images/logo.jpg" alt="Logo">
  <h2 class="text-center mb-4">Forgot Password</h2>

  <form method="POST">
    <div class="mb-3">
      <label class="form-label" for="username">Username</label>
      <input class="form-control" id="username" name="username" required autofocus>
    </div>
    <div class="mb-3">
      <label class="form-label" for="phone">Phone Number</label>
      <input class="form-control" id="phone" name="phone" required placeholder="e.g. 0123456701">
    </div>
    <button class="btn btn-primary w-100" type="submit">Submit</button>
  </form>

  <?php if(!empty($error_message)): ?>
    <p class="text-danger text-center"><?= htmlspecialchars($error_message) ?></p>
  <?php endif; ?>

  <div class="text-center mt-3">
    <a class="btn btn-secondary" href="login.php">Back to Login</a>
  </div>
</div>

</body>
</html>
