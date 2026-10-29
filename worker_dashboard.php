<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'worker') {
    header("Location: login.php");
    exit();
}

require_once 'db_connect.php'; 
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$worker_id = $_SESSION['worker_id'];


// Fetch worker profile details
$stmt = $conn->prepare("SELECT * FROM users WHERE worker_id = ?");
$stmt->bind_param("i", $worker_id);
$stmt->execute();
$result = $stmt->get_result();
$workerProfile = $result->fetch_assoc();
$stmt->close();

// Count total tasks assigned for the current week
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM tasks WHERE worker_id = ? AND week_start_date BETWEEN ? AND ?");
$stmt->bind_param("iss", $worker_id, $weekStart, $weekEnd);
$stmt->execute();
$stmt->bind_result($totalTasks);
$stmt->fetch();
$stmt->close();

// Count total tasks completed
$result = $conn->query("SELECT COUNT(*) as completed FROM tasks WHERE worker_id = $worker_id AND status = 'completed'");
$completedTasks = $result->fetch_assoc()['completed'];

// Count attendance records this week
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
       FROM qr_attendance
      WHERE worker_id = ?
        AND DATE(scanned_at) BETWEEN ? AND ?"
);
$stmt->bind_param("iss", $worker_id, $weekStart, $weekEnd);
$stmt->execute();
$stmt->bind_result($scannedDays);
$stmt->fetch();
$stmt->close();

// Calculate attendance percentage (Mon–Fri = 5 working days)
$attendancePercent = min($scannedDays, 5) / 5 * 100;

// Fetch notifications - Make sure task_name and due_date are being selected
$sql = "SELECT task_name, due_date FROM tasks WHERE worker_id = ? AND due_date >= CURDATE() ORDER BY due_date ASC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $worker_id);
$stmt->execute();
$notifResult = $stmt->get_result();


// Check if rows are returned and assign to $notifications array
$notifications = [];
while ($row = $notifResult->fetch_assoc()) {
    $notifications[] = $row;  // Add task_name and due_date to notifications array
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Worker Dashboard</title>
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
            flex: 2;
        }

        .right-panel {
            flex: 1;
            background: #f7f7f7;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 8px rgba(0,0,0,0.1);
        }

        /* Style for Stats Section */
        .frame {
            background: white;
            padding: 30px; /* Larger padding for bigger sections */
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            font-size: 2rem; /* Larger font size for better visibility */
            font-weight: bold;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 150px;
        }

        .frame:hover {
            background-color: #f0cfa0; /* Hover effect */
        }

        .notifications {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .notifications ul {
            padding-left: 20px;
        }

        .notifications li {
            margin-bottom: 10px;
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
    <h2>Worker Panel</h2>
    <a href="worker_dashboard.php">📋 Dashboard</a>
    <a href="WorkerTaskSchedule.php">📋 Task Schedule</a>
    <a href="ScanAttendance.php">📷 Scan Attendance</a>
    <a href="ActivityStatus.php">✅ Activity Status</a>
    <a href="logout.php">🚪 Logout</a>
</div>

<!-- Main Content -->
<div class="main">
    <!-- Topbar -->
    <div class="topbar">
        <h2>Welcome to the Worker Dashboard</h2>

        <!-- Top Right Profile -->
        <div class="top-right-profile">
            <a href="WorkerProfile.php">
                <img src="<?php echo !empty($workerProfile['profile_picture']) ? $workerProfile['profile_picture'] : 'images/UserLogo.png'; ?>" alt="Worker Profile">
                <p><?php echo htmlspecialchars($workerProfile['name']); ?></p>
            </a>
        </div>
    </div>

    <!-- Main Dashboard Area -->
    <div class="content">
        <!-- Left content (This week's highlights) -->
        <div class="left-panel">
            <h3>This Week's Highlights</h3>

            <!-- Frame for Assigned Tasks, Completed Tasks, Attendance Summary -->
            <div class="frame">
                📦 Assigned Tasks (This Week): <?php echo $totalTasks; ?>
            </div>

            <div class="frame">
                ✅ Completed Tasks: <?php echo $completedTasks; ?>
            </div>

            <div class="frame">
                👥 Attendance: <?php echo $scannedDays; ?>/5 days (<?php echo round($attendancePercent, 1); ?>%)
            </div>
        </div>

        <!-- Right stats panel -->
        <div class="right-panel">
            <!-- Recent Notifications -->
            <div class="notifications">
                <h4>📌 Task Notifications</h4>
                <ul>
                    <?php if (empty($notifications)): ?>
                        <li>No upcoming tasks</li>
                    <?php else: ?>
                        <?php foreach ($notifications as $note): ?>
                            <li>
                                <?php 
                                if (isset($note['task_name']) && isset($note['due_date'])) {
                                    echo htmlspecialchars($note['task_name']) . " — Due: " . htmlspecialchars($note['due_date']);
                                } else {
                                    echo "No task details available.";
                                }
                                ?>
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
