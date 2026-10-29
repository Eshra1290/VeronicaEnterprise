<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'db_connect.php';  

$current_week_start = date('Y-m-d', strtotime('monday this week'));
$current_week_end = date('Y-m-d', strtotime('sunday this week'));
$selected_week_start = isset($_GET['week_start_date']) ? $_GET['week_start_date'] : $current_week_start;
$selected_week_end = date('Y-m-d', strtotime($selected_week_start . ' +6 days'));

// Add Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $task_name = $_POST['task_name'];
    $worker_id = $_POST['worker_id'];
    $due_date = $_POST['due_date'];
    $week_start_date = $_POST['week_start_date'];

    $sql = "INSERT INTO tasks (task_name, worker_id, due_date, week_start_date) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siss", $task_name, $worker_id, $due_date, $week_start_date);

    if ($stmt->execute()) {
        $message = "A new task has been assigned: $task_name";
        $notification_sql = "INSERT INTO notifications (worker_id, message) VALUES (?, ?)";
        $notification_stmt = $conn->prepare($notification_sql);
        $notification_stmt->bind_param("is", $worker_id, $message);
        $notification_stmt->execute();
        $notification_stmt->close();
        $message = "Task added successfully!";
    } else {
        $message = "Error adding task: " . $conn->error;
    }
    $stmt->close();
}

// Delete Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task'])) {
    $task_id = $_POST['task_id'];
    $sql = "DELETE FROM tasks WHERE task_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $task_id);
    if ($stmt->execute()) {
        $message = "Task deleted successfully!";
    } else {
        $message = "Error deleting task: " . $conn->error;
    }
    $stmt->close();
}

// Edit Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_task'])) {
    $task_id = $_POST['edit_id'];
    $task_name = $_POST['edit_name'];
    $worker_name = $_POST['edit_worker_name'];
    $due_date = $_POST['edit_due_date'];

    // Get worker_id based on worker_name
    $worker_sql = "SELECT worker_id FROM users WHERE name = ?";
    $worker_stmt = $conn->prepare($worker_sql);
    $worker_stmt->bind_param("s", $worker_name);
    $worker_stmt->execute();
    $worker_result = $worker_stmt->get_result();
    
    if ($worker_result->num_rows > 0) {
        $worker = $worker_result->fetch_assoc();
        $worker_id = $worker['worker_id'];
    } else {
        $message = "Invalid worker selected.";
        $worker_id = null;
    }

    // Update task if worker_id exists
    if ($worker_id) {
        $sql = "UPDATE tasks SET task_name = ?, worker_id = ?, due_date = ? WHERE task_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisi", $task_name, $worker_id, $due_date, $task_id);

        if ($stmt->execute()) {
            $message = "Task updated successfully!";
        } else {
            $message = "Error updating task: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch Tasks, ordered by the latest task first (descending order)
$sql = "SELECT t.task_id, t.task_name, t.due_date, w.name AS worker_name FROM tasks t JOIN users w ON t.worker_id = w.worker_id WHERE t.week_start_date = ? ORDER BY t.task_id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $selected_week_start);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $tasks = $result->fetch_all(MYSQLI_ASSOC);
}
$stmt->close();
// Fetch Workers
$workers = [];
$sql = "SELECT worker_id, name FROM users WHERE role = 'worker'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $workers = $result->fetch_all(MYSQLI_ASSOC);
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Task Schedule</title>
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
    .form-container, .table-container {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 0 8px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .form-container form {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    input, select {
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
    }
    button {
        padding: 10px;
        background: #EEE6E2;
        border: none;
        border-radius: 5px;
        font-weight: bold;
        cursor: pointer;
    }
    button:hover {
        background: #d6c8bf;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    th, td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    th {
        background: #EEE6E2;
    }
    .week-selector {
        margin-bottom: 15px;
        font-weight: bold;
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
      <h2>Weekly Task Schedule</h2>

    </div> 

  <div class="content">
    <?php if (isset($message)) echo "<p style='color:green;'>$message</p>"; ?>

          <div class="week-selector">
        <form method="GET">
          <label for="week_start_date">Select Week:</label>
          <select id="week_start_date" name="week_start_date" onchange="this.form.submit()">
            <option value="<?php echo $current_week_start; ?>" <?php echo $selected_week_start === $current_week_start ? 'selected' : ''; ?>>Current Week</option>
            <option value="<?php echo date('Y-m-d', strtotime($current_week_start . ' +7 days')); ?>" <?php echo $selected_week_start === date('Y-m-d', strtotime($current_week_start . ' +7 days')) ? 'selected' : ''; ?>>Next Week</option>
            <option value="<?php echo date('Y-m-d', strtotime($current_week_start . ' -7 days')); ?>" <?php echo $selected_week_start === date('Y-m-d', strtotime($current_week_start . ' -7 days')) ? 'selected' : ''; ?>>Previous Week</option>
          </select>
        </form>
      </div>

    <div class="form-container">
      <h3>Assign a New Task</h3>
      <form method="POST">
        <label>Task Name:</label>
        <input type="text" name="task_name" required>

        <label>Assign to Worker:</label>
        <select name="worker_id" required>
          <option value="">Select a Worker</option>
          <?php foreach ($workers as $worker): ?>
            <option value="<?php echo $worker['worker_id']; ?>"><?php echo htmlspecialchars($worker['name']); ?></option>
          <?php endforeach; ?>
        </select>

        <label>Due Date:</label>
        <input type="date" name="due_date" required>

        <label>Week Start Date:</label>
        <input type="date" name="week_start_date" value="<?php echo $selected_week_start; ?>" required>

        <button type="submit" name="add_task">Add Task</button>
      </form>
    </div>

    <div class="table-container">
      <h3>Scheduled Tasks (<?php echo $selected_week_start; ?> to <?php echo $selected_week_end; ?>)</h3>
      <table>
        <thead>
          <tr>
            <th>Task Name</th>
            <th>Assigned Worker</th>
            <th>Due Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($tasks)): ?>
            <tr><td colspan="4">No tasks for this week.</td></tr>
          <?php else: ?>
            <?php foreach ($tasks as $task): ?>
              <tr>
                <td><?php echo htmlspecialchars($task['task_name']); ?></td>
                <td><?php echo htmlspecialchars($task['worker_name']); ?></td>
                <td><?php echo htmlspecialchars($task['due_date']); ?></td>
                <td>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                    <button type="submit" name="delete_task">Delete</button>
                  </form>
                  <button onclick="document.getElementById('edit-<?php echo $task['task_id']; ?>').style.display='block'">Edit</button>
                </td>
              </tr>
              <tr id="edit-<?php echo $task['task_id']; ?>" style="display:none;">
                <td colspan="4">
                  <form method="POST">
                    <input type="hidden" name="edit_id" value="<?php echo $task['task_id']; ?>">
                    <input type="text" name="edit_name" value="<?php echo htmlspecialchars($task['task_name']); ?>" required>

                    <select name="edit_worker_name" required>
                      <?php foreach ($workers as $worker): ?>
                        <option value="<?php echo $worker['name']; ?>" <?php echo $worker['name'] == $task['worker_name'] ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($worker['name']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>

                    <input type="date" name="edit_due_date" value="<?php echo $task['due_date']; ?>" required>
                    <button type="submit" name="edit_task">Update</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</body>
</html>
