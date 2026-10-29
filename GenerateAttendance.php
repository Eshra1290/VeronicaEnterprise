<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

/* ---------- 0. shared DB connection ---------- */
require_once 'db_connect.php';      

include 'phpqrcode/qrlib.php';

/* ---------- 1. Folders & flags ---------- */
$qrDir = "qr_codes/";
if (!is_dir($qrDir)) { mkdir($qrDir); }

$qrGenerated  = false;
$qrFile       = '';
$fallbackCode = '';

/* ---------- 2. Generate a new QR (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendanceId = uniqid("ATT_");
    $fallbackCode = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

    /* create PNG */
    $qrFile = $qrDir . $attendanceId . ".png";
    QRcode::png($attendanceId, $qrFile, QR_ECLEVEL_L, 150);

    /* store in DB */
        /* store in DB with LOCAL timestamp */
    $timestamp = date('Y-m-d H:i:s');       // Malaysia time (UTC+8)

    $stmt = $conn->prepare(
        "INSERT INTO attendance (attendance_id, fallback_code, created_at)
         VALUES (?, ?, ?)"
    );
    $stmt->bind_param("sss", $attendanceId, $fallbackCode, $timestamp);
    $stmt->execute();
    $stmt->close();

    $qrGenerated = true;
}

/* ---------- 3. Fetch last 10 records ---------- */
$qrHistory = [];
$result = $conn->query(
    "SELECT attendance_id, fallback_code, created_at
     FROM attendance
     ORDER BY created_at DESC
     LIMIT 10"
);
if ($result && $result->num_rows > 0) {
    $qrHistory = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
 <meta name="viewport" content="width=1024">
  <title>Generate Attendance QR</title>
  <!-- (styles unchanged) -->
  <style>
    html,body{margin:0;padding:0;height:100%;font-family:Arial,Helvetica,sans-serif}
    .wrapper{display:flex;height:100vh}
    .sidebar{width:220px;background:#3b2f2f;color:#fff;padding:30px 20px;display:flex;flex-direction:column;gap:20px}
    .sidebar a{color:#fff;text-decoration:none;font-weight:bold;padding:10px 0;border-bottom:1px solid #ddd;display:block;transition:.3s}
    .sidebar a:hover{background:#f0cfa0;color:#3b2f2f}
    .sidebar a:last-child{border-bottom:none}
    .main{flex-grow:1;display:flex;flex-direction:column}
    .topbar{background:#EEE6E2;padding:15px 30px;display:flex;justify-content:space-between;align-items:center}
    .content{padding:20px;display:flex;flex-direction:column;align-items:center;overflow-y:auto}
    .container{background:#fff;padding:20px;border-radius:10px;text-align:center;max-width:500px;width:100%;box-shadow:0 0 10px rgba(0,0,0,.1)}
    button{padding:10px 20px;font-size:16px;border:none;background:#EEE6E2;border-radius:5px;font-weight:bold;cursor:pointer}
    button:hover{background:#d6c8bf}
    .qr-code img{width:500px;margin-top:20px}
    .history{margin-top:30px;width:100%;max-width:600px}
    .history table{width:100%;border-collapse:collapse}
    .history th,.history td{padding:10px;border-bottom:1px solid #ccc;text-align:center}
    .history th{background:#EEE6E2}
    @media(max-width:600px){
      .wrapper{flex-direction:column}
      .sidebar{width:100%;flex-direction:row;overflow-x:auto;padding:10px}
      .sidebar a{flex:1;text-align:center}
      .container,.history{width:90%}
    }
  </style>
</head>
<body>
<div class="wrapper">
  <!-- ===== sidebar ===== -->
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

  <!-- ===== main ===== -->
  <div class="main">
    <div class="topbar"><h2>Generate Attendance QR</h2></div>

    <div class="content">
      <!-- generator -->
      <div class="container">
        <h3>QR Generator</h3>
        <form method="POST">
          <button type="submit">Generate QR Code</button>
        </form>

        <?php if ($qrGenerated): ?>
          <div class="qr-code">
            <p>Scan this QR Code for attendance:</p>
            <img src="<?= $qrFile ?>" alt="QR Code">
            <p style="font-size:1.2rem;font-weight:bold">
                Backup code:&nbsp;<?= htmlspecialchars($fallbackCode) ?>
            </p>
          </div>
        <?php endif; ?>
      </div>

      <!-- history -->
      <div class="history">
        <h3>Recent Attendance QR Records</h3>
        <table>
          <thead>
            <tr><th>Attendance ID</th><th>Backup Code</th><th>Date Created</th></tr>
          </thead>
          <tbody>
          <?php if (empty($qrHistory)): ?>
              <tr><td colspan="3">No QR records found.</td></tr>
          <?php else: foreach ($qrHistory as $qr): ?>
              <tr>
                <td><?= htmlspecialchars($qr['attendance_id']) ?></td>
                <td><?= htmlspecialchars($qr['fallback_code']) ?></td>
                <td><?= htmlspecialchars($qr['created_at'])   ?></td>
              </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
<?php
/* optional tidy-up */
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
