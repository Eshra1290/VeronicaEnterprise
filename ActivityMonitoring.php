<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'db_connect.php';  

$tasks = [];
$sql = "SELECT t.task_id, t.task_name, t.due_date, t.status_update AS status, w.name AS worker_name 
        FROM tasks t 
        JOIN users w ON t.worker_id = w.worker_id 
        ORDER BY t.task_id DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $tasks = $result->fetch_all(MYSQLI_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Activity Monitoring</title>
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
        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 8px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #EEE6E2;
        }
        .status-not-started { color: red; font-weight: bold; }
        .status-in-progress { color: orange; font-weight: bold; }
        .status-completed { color: green; font-weight: bold; }
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
            <h2>Activity Monitoring</h2>
        </div>

        <div class="content">
            <div class="container">
                <h3>All Task Activities</h3>
                <table>
                    <thead>
                    <tr>
                        <th>Task Name</th>
                        <th>Worker Name</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($tasks)): ?>
                        <tr><td colspan="4">No tasks found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($task['task_name']); ?></td>
                                <td><?php echo htmlspecialchars($task['worker_name']); ?></td>
                                <td><?php echo htmlspecialchars($task['due_date']); ?></td>
                                <td>
                                    <span class="<?php 
                                        echo $task['status'] === 'Not Started' ? 'status-not-started' : 
                                            ($task['status'] === 'In Progress' ? 'status-in-progress' : 'status-completed'); 
                                    ?>">
                                        <?php echo htmlspecialchars($task['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
