<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'worker') {
    header("Location: login.php");
    exit();
}

$worker_id = $_SESSION['worker_id'];
require_once 'db_connect.php';  

// Fetch worker profile details
$workerProfile = [];
$sql = "SELECT * FROM users WHERE worker_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $worker_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $workerProfile = $result->fetch_assoc(); // Fetch profile only if available
}
$stmt->close();

$current_week_start = date('Y-m-d', strtotime('monday this week'));
$current_week_end = date('Y-m-d', strtotime('sunday this week'));

$sql = "SELECT * FROM tasks WHERE worker_id = ? AND week_start_date = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $worker_id, $current_week_start);
$stmt->execute();
$result = $stmt->get_result();
$tasks = [];
if ($result->num_rows > 0) {
    $tasks = $result->fetch_all(MYSQLI_ASSOC);
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
   <meta name="viewport" content="width=1024">
  <title>Worker Task Schedule</title>
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

    .top-right-profile a {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none; /* Removes underline */
      color: black; /* Keeps the text color as black */
    }

    .top-right-profile a:hover {
      cursor: pointer; /* Changes the cursor to pointer to indicate it's clickable */
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
      padding: 20px;
      overflow-y: auto;
    }
    .task-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    .task-table th, .task-table td {
      padding: 10px;
      border-bottom: 1px solid #ddd;
      text-align: left;
    }
    .task-table th {
      background-color: #EEE6E2;
    }
    .no-tasks {
      text-align: center;
      color: #666;
      padding: 20px;
    }
    @media (max-width: 600px) {
      .wrapper {
        flex-direction: column;
      }
      .sidebar {
        width: 100%;
        flex-direction: row;
        overflow-x: auto;
        padding: 10px;
      }
      .sidebar a {
        flex: 1;
        text-align: center;
      }
    }

  </style>
</head>
<body>

<div class="wrapper">
  <div class="sidebar">
    <h2>Worker Panel</h2>
    <a href="worker_dashboard.php">📋 Dashboard</a>
    <a href="WorkerTaskSchedule.php">📋 Task Schedule</a>
    <a href="ScanAttendance.php">📷 Scan Attendance</a>
    <a href="ActivityStatus.php">✅ Activity Status</a>
    <a href="logout.php">🚪 Logout</a>
  </div>

  <div class="main">
    <div class="topbar">
      <h2>Task Schedule for the Week</h2>

    </div>

    <div class="content">
      <h3>Tasks for <?php echo htmlspecialchars($current_week_start); ?> to <?php echo htmlspecialchars($current_week_end); ?></h3>
      <table class="task-table">
        <thead>
          <tr>
            <th>Task Name</th>
            <th>Due Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($tasks)): ?>
            <tr>
              <td colspan="2" class="no-tasks">No tasks assigned for this week.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($tasks as $task): ?>
              <tr>
                <td><?php echo htmlspecialchars($task['task_name']); ?></td>
                <td><?php echo htmlspecialchars($task['due_date']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</body>
</html>
