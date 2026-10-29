<?php
session_start();

// Check if the user is logged in as a worker
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'worker') {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['worker_id'])) {
    die("Worker ID is not set in the session. Please log in again.");
}

// Database connection
require_once 'db_connect.php';  

// Fetch worker profile details, including profile_picture
$worker_id = $_SESSION['worker_id']; // Assuming worker_id is stored in the session during login
$sql = "SELECT worker_id, name, username, role, contact, address, profile_picture FROM users WHERE worker_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $worker_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if a profile was found
if ($result->num_rows === 0) {
    die("No worker profile found for the given worker ID.");
}

$workerProfile = $result->fetch_assoc();

// Handle profile update and image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contact = $_POST['contact'];
    $address = $_POST['address'];
    $imagePath = $workerProfile['profile_picture'] ?: 'images/UserLogo.png'; // Default to UserLogo.png if no new picture is uploaded

    // Handle image upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $imageTmp = $_FILES['profile_picture']['tmp_name'];
        $imageName = basename($_FILES['profile_picture']['name']);
        $imageExtension = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($imageExtension, $allowedExtensions)) {
            // Create the upload directory if it doesn't exist
            $upload_dir = 'uploads/profile_pictures/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true); // Create the directory if it doesn't exist
            }

            // Generate a unique filename to avoid conflicts
            $imagePath = $upload_dir . time() . '.' . $imageExtension;

            // Move the uploaded file to the directory
            if (move_uploaded_file($imageTmp, $imagePath)) {
                // Proceed to update the profile with the new image
                $update_sql = "UPDATE users SET contact = ?, address = ?, profile_picture = ? WHERE worker_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sssi", $contact, $address, $imagePath, $worker_id);
            } else {
                $message = "Failed to upload image. Please try again.";
            }
        } else {
            $message = "Invalid image format. Please upload a JPG, JPEG, PNG, or GIF image.";
        }
    } else {
        // If no new image is uploaded, update without changing the picture
        $update_sql = "UPDATE users SET contact = ?, address = ? WHERE worker_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssi", $contact, $address, $worker_id);
    }

    // Execute the update statement
    if (isset($update_stmt) && $update_stmt->execute()) {
        $message = "Profile updated successfully!";
        // Refresh profile data
        $workerProfile['contact'] = $contact;
        $workerProfile['address'] = $address;
        $workerProfile['profile_picture'] = $imagePath; // Update with the new image path
    } else {
        $message = "Error updating profile: " . $conn->error;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Worker Profile</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        }

        .header {
            background-color: #EEE6E2;
            color: black;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h2 {
            margin: 0;
        }

        .header .icons a {
            color: black;
            text-decoration: none;
            font-size: 28px;
            margin-left: 20px;
        }

        .back-nav {
            position: relative;
            margin: 20px 0;
            width: 0;
            height: 0;
            border-left: 20px solid transparent;
            border-right: 20px solid transparent;
            border-bottom: 30px solid #EEE6E2;
            cursor: pointer;
            transform: rotate(-90deg);
            transition: transform 0.2s;
        }

        .back-nav:hover {
            transform: scale(1.1) rotate(-90deg);
        }

        .profile-logo {
            display: block;
            margin: 0 auto 20px;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .profile-container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .profile-container form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .profile-container label {
            font-weight: bold;
        }

        .profile-container input {
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 100%;
        }

        .profile-container button {
            background-color: #EEE6E2;
            color: black;
            padding: 10px;
            font-size: 18px;
            font-weight: bold;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .profile-container button:hover {
            background-color: #d6c8bf;
        }

        .message {
            text-align: center;
            margin-top: 10px;
            color: green;
            font-weight: bold;
        }

        .message.error {
            color: red;
        }
    </style>
</head>
<body>

<div class="header">
    <h2>Worker Profile</h2>

</div>

<div class="back-nav" onclick="window.location.href='worker_dashboard.php'"></div>

<div class="profile-container">
    <img src="<?php echo $workerProfile['profile_picture'] ?: 'images/UserLogo.png'; ?>" alt="Worker Profile Logo" class="profile-logo">

    <?php if (isset($message)) echo "<p class='message'>" . $message . "</p>"; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <label for="name">Name:</label>
        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($workerProfile['name']); ?>" disabled>

        <label for="username">Username:</label>
        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($workerProfile['username']); ?>" disabled>

        <label for="role">Role:</label>
        <input type="text" id="role" name="role" value="<?php echo htmlspecialchars($workerProfile['role']); ?>" disabled>

        <label for="contact">Contact:</label>
        <input type="text" id="contact" name="contact" value="<?php echo htmlspecialchars($workerProfile['contact']); ?>" required>

        <label for="address">Address:</label>
        <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($workerProfile['address']); ?>">

        <label for="profile_picture">Profile Picture:</label>
        <input type="file" id="profile_picture" name="profile_picture" accept="image/*">

        <button type="submit">Update Profile</button>
    </form>
</div>

</body>
</html>
