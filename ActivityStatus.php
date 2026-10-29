<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'worker') {
    header("Location: login.php");
    exit();
}

require_once 'db_connect.php';  

$current_week_start = date('Y-m-d', strtotime('monday this week'));
$current_week_end = date('Y-m-d', strtotime('sunday this week'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $task_id = $_POST['task_id'];
    $new_status = $_POST['status_update'];

    // Update task status
    $stmt = $conn->prepare("UPDATE tasks SET status_update = ? WHERE task_id = ? AND worker_id = ?");
    $stmt->bind_param("sii", $new_status, $task_id, $_SESSION['worker_id']);
    
    if ($stmt->execute()) {
        // Get the worker's name
        $worker_name_stmt = $conn->prepare("SELECT name FROM users WHERE worker_id = ?");
        $worker_name_stmt->bind_param("i", $_SESSION['worker_id']);
        $worker_name_stmt->execute();
        $worker_name_stmt->bind_result($worker_name);
        $worker_name_stmt->fetch();
        $worker_name_stmt->close();

        // Insert notification for admin with worker's name
        $message = "$worker_name updated task status to $new_status";
        $insert_notification = $conn->prepare("INSERT INTO notifications (worker_id, message, created_at) VALUES (?, ?, NOW())");
        $insert_notification->bind_param("is", $_SESSION['worker_id'], $message);
        $insert_notification->execute();
        
        $message = "Task status updated successfully!";
    } else {
        $message = "Error updating task status: " . $conn->error;
    }
    $stmt->close();
}

$worker_id = $_SESSION['worker_id'];
$sql = "SELECT * FROM tasks WHERE worker_id = ? AND week_start_date = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $worker_id, $current_week_start);
$stmt->execute();
$result = $stmt->get_result();
$tasks = [];
if ($result->num_rows > 0) {
    $tasks = $result->fetch_all(MYSQLI_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=1024">
  <title>Activity Status</title>
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
      padding: 20px;
      overflow-y: auto;
    }
    .message {
      text-align: center;
      color: green;
      font-weight: bold;
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
      <h2>Activity Status</h2>
    </div>

    <div class="content">
      <?php if (isset($message)) echo "<p class='message'>$message</p>"; ?>
      <h3>Tasks for <?php echo htmlspecialchars($current_week_start); ?> to <?php echo htmlspecialchars($current_week_end); ?></h3>
      <table class="task-table">
        <thead>
          <tr>
            <th>Task Name</th>
            <th>Due Date</th>
            <th>Status</th>
            <th>Update</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($tasks)): ?>
          <tr>
            <td colspan="4" class="no-tasks">No tasks assigned for this week.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($tasks as $task): ?>
          <tr>
            <td><?php echo htmlspecialchars($task['task_name']); ?></td>
            <td><?php echo htmlspecialchars($task['due_date']); ?></td>
            <td><?php echo htmlspecialchars($task['status_update']); ?></td>
            <td>
              <form method="POST">
                <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                <select name="status_update" required>
                  <option value="Not Started" <?php echo $task['status_update'] === 'Not Started' ? 'selected' : ''; ?>>Not Started</option>
                  <option value="In Progress" <?php echo $task['status_update'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                  <option value="Completed" <?php echo $task['status_update'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
                <button type="submit" name="update_status">Update</button>
              </form>
            </td>
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
