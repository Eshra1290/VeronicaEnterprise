<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'db_connect.php';  

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_worker'])) {
    $worker_id = $_POST['worker_id'];
    $delete_sql = "DELETE FROM users WHERE worker_id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $worker_id);
    if ($stmt->execute()) {
        $message = "Worker removed successfully!";
    } else {
        $message = "Error removing worker: " . $conn->error;
    }
    $stmt->close();
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!empty($search)) {
    $sql = "SELECT worker_id, name, username, contact, address, profile_picture FROM users WHERE role = 'worker' AND name LIKE ?";
    $stmt = $conn->prepare($sql);
    $likeSearch = "%$search%";
    $stmt->bind_param("s", $likeSearch);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT worker_id, name, username, contact, address, profile_picture FROM users WHERE role = 'worker'";
    $result = $conn->query($sql);
}

$workers = [];
if ($result->num_rows > 0) {
    $workers = $result->fetch_all(MYSQLI_ASSOC);
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Staff Management</title>
  <style>
    html, body {
        margin: 0;
        padding: 0;
        height: 100%;
        font-family: Arial, sans-serif;
    }
    .wrapper {
        display: flex;
        height: 100vh;
    }
    .sidebar {
        width: 220px;
        background-color: #3b2f2f;
        color: white;
        padding: 30px 20px;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    .sidebar a {
        color: white;
        text-decoration: none;
        font-weight: bold;
        padding: 10px 0;
        display: block;
        border-bottom: 1px solid #ddd;
        transition: background-color 0.3s ease;
    }
    .sidebar a:hover {
        background-color: #f0cfa0;
        color: #3b2f2f;
    }
    .sidebar a:last-child {
        border-bottom: none;
    }
    .main {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }
    .topbar {
        background-color: #EEE6E2;
        padding: 15px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .topbar .right-icons {
        display: flex;
        align-items: center;
        gap: 20px;
        font-size: 22px;
    }
    .content {
        padding: 20px 30px;
        overflow-y: auto;
    }
    .card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); /* ✅ Better grid fit */
    gap: 20px;
    justify-items: center; /* ✅ Center cards within each column */
    }
.worker-card {
    background: linear-gradient(to bottom right, #f5ebe3, #f0dcd0);
    border-radius: 16px;
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
    padding: 25px 20px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 320px;
    height: 420px;
    box-sizing: border-box;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.worker-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
}

.worker-card img {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    margin-bottom: 15px;
    object-fit: cover;
    border: 2px solid #fff;
    box-shadow: 0 0 5px rgba(0,0,0,0.1);
}

.details-box {
    background-color: white;
    width: 100%;
    border-radius: 8px;
    box-shadow: inset 0 0 5px rgba(0,0,0,0.05);
    padding: 10px 15px;
    margin-bottom: 15px;
    font-size: 15px;
}

.details-box .row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px solid #ddd;
}

.details-box .row:last-child {
    border-bottom: none;
}

.details-box .label {
    font-weight: bold;
    color: #3b2f2f;
    text-align: left;
}

.details-box .value {
    text-align: right;
}

.card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 30px;
    justify-content: center;
    place-items: center; /* ✅ Center the item(s) horizontally and vertically */
}


    .message {
        text-align: center;
        color: green;
        font-weight: bold;
        margin-top: 10px;
    }
  </style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="TaskSchedule.php">🗓 Task Schedule</a>
        <a href="GenerateAttendance.php">📤 Generate Attendance</a>
        <a href="ActivityMonitoring.php">📈 Activity Monitoring</a>
        <a href="Reports.php">📑 Reports</a>
        <a href="StaffManagement.php">👥 Staff Management</a>
        <a href="logout.php">🚪 Logout</a>
    </div>

    <div class="main">
        <div class="topbar">
            <h2>Staff Management</h2>
        </div>

        <div class="content">
            <?php if (isset($message)) echo "<p class='message'>$message</p>"; ?>

            <form method="GET" style="text-align: center; margin-bottom: 20px;">
                <input type="text" name="search" placeholder="Search worker by name..." value="<?php echo htmlspecialchars($search); ?>" style="padding: 8px; width: 250px; font-size: 16px;">
                <button type="submit" style="padding: 8px 16px; font-size: 16px;">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="StaffManagement.php" style="margin-left: 10px; color: #ff4d4d; text-decoration: none;">Clear</a>
                <?php endif; ?>
            </form>

            <h3 style="text-align: center;">List of Workers</h3>
            <div class="card-grid">
                <?php foreach ($workers as $worker): ?>
                    <div class="worker-card">
                        <img src="<?php echo !empty($worker['profile_picture']) ? $worker['profile_picture'] : 'images/UserLogo.png'; ?>" alt="Worker Image">
                       <div class="details-box">
                        <div class="row">
                            <div class="label">Name:</div>
                            <div class="value"><?php echo htmlspecialchars($worker['name']); ?></div>
                        </div>
                        <div class="row">
                            <div class="label">Worker ID:</div>
                            <div class="value"><?php echo htmlspecialchars($worker['worker_id']); ?></div>
                        </div>
                        <div class="row">
                            <div class="label">Phone:</div>
                            <div class="value"><?php echo htmlspecialchars($worker['contact']); ?></div>
                        </div>
                        <div class="row">
                            <div class="label">Address:</div>
                            <div class="value"><?php echo htmlspecialchars($worker['address']); ?></div>
                        </div>
                        
                    </div>

                        <form method="POST" onsubmit="return confirm('Are you sure you want to remove this worker?');">
                            <input type="hidden" name="worker_id" value="<?php echo $worker['worker_id']; ?>">
                            <button type="submit" name="remove_worker">Delete</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
