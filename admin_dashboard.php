<?php
session_start();

require_once 'db_connect.php';  

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}



// Total workers
$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'worker'");
$totalWorkers = $result->fetch_assoc()['total'] ?? 0;

// Workers present today
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(DISTINCT worker_id) AS present FROM qr_attendance WHERE DATE(scanned_at) = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$stmt->bind_result($workersPresent);
$stmt->fetch();
$stmt->close();
$workersPresent = $workersPresent ?? 0;

// Tasks assigned this week
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM tasks WHERE week_start_date BETWEEN ? AND ?");
$stmt->bind_param("ss", $weekStart, $weekEnd);
$stmt->execute();
$stmt->bind_result($tasksThisWeek);
$stmt->fetch();
$stmt->close();
$tasksThisWeek = $tasksThisWeek ?? 0;

// Fetch admin profile details
$admin_id = $_SESSION['admin_id']; // Replace with the correct admin ID or session value
$sql = "SELECT profile_picture, name FROM admin_profile WHERE admin_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$adminProfile = $result->fetch_assoc();
$stmt->close();


// Get latest notifications
$notifications = [];
$sql = "SELECT n.message, n.created_at, u.username FROM notifications n
        LEFT JOIN users u ON n.worker_id = u.worker_id
        ORDER BY n.created_at DESC
        LIMIT 5";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

// Handle clearing notifications
if (isset($_POST['clear_notifications'])) {
    $conn->query("DELETE FROM notifications");
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            display: flex;
            min-height: 100vh;                /* new: keeps sidebar full-height */
            overflow-y: auto;                 /* new: page can now scroll */
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

        .top-right-profile a {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: black;
        }

        .top-right-profile a:hover {
            cursor: pointer;
        }

        .top-right-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }

        .top-right-profile p {
            margin: 0;
            font-weight: bold;
        }

        .content {
            padding: 20px 30px;
            display: flex;
            gap: 30px;
        }

        .left-panel {
            flex: 3; /* More space for left panel */
        }

        .right-panel {
            flex: 1;
            background: #f7f7f7;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 8px rgba(0,0,0,0.1);
        }

        /* Frame style for each section */
        .frame {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            height: 150px; /* Set height to make them bigger */
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .frame:hover {
            background-color: #f0cfa0;
        }

        .notifications {
            margin-top: 20px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .notifications ul {
            padding-left: 20px;
        }

        .notifications li {
            margin-bottom: 10px;
        }

        .notifications li small {
            display: block;
            color: gray;
        }

        .clear-notifications {
            text-align: center;
            margin-top: 20px;
        }

        .clear-notifications button {
            background-color: #ff6666;
            color: white;
            padding: 10px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
        }

        .clear-notifications button:hover {
            background-color: #e55555;
        }

    </style>
</head>
<body>

<!-- Sidebar -->
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

<!-- Main Content -->
<div class="main">
    <!-- Topbar -->
    <div class="topbar">
        <h2>Welcome to the Admin Dashboard</h2>

       

<!-- Top Right Profile -->
<div class="top-right-profile">
    <a href="AdminProfile.php">
        <!-- Display profile picture if exists, else fallback to logo.jpg -->
        <img src="<?php echo !empty($adminProfile['profile_picture']) ? $adminProfile['profile_picture'] : 'images/logo.jpg'; ?>" alt="Admin Profile">
        <p><?php echo htmlspecialchars($adminProfile['name']); ?></p>
    </a>
</div>


    
    </div>

    <!-- Main Dashboard Area -->
    <div class="content">
        <div class="left-panel">
            <h3>Admin Quick Tools</h3>
            
            <!-- Frame for Total Workers, Present Today, Tasks This Week -->
            <div class="frame">
                👥 Total Workers: <?php echo $totalWorkers; ?>
            </div>

            <div class="frame">
                ✅ Present Today: <?php echo $workersPresent; ?>
            </div>

            <div class="frame">
                📋 Tasks This Week: <?php echo $tasksThisWeek; ?>
            </div>
        </div>

        <div class="right-panel">
            <!-- Recent Notifications -->
            <div class="notifications">
                <h4>🔔 Recent Notifications</h4>
                <ul>
                    <?php if (empty($notifications)): ?>
                        <li>No recent notifications.</li>
                    <?php else: ?>
                        <?php foreach ($notifications as $note): ?>
                            <li>
                                <?php echo htmlspecialchars($note['message']); ?>
                                <small>From: <?php echo $note['username'] ?? 'System'; ?> | 
                                    <?php echo date('Y-m-d H:i', strtotime($note['created_at'])); ?>
                                </small>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Clear Notifications Button -->
            <div class="clear-notifications">
                <form method="POST">
                    <button type="submit" name="clear_notifications">Clear Notifications</button>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>
