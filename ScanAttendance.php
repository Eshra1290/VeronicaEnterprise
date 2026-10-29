<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include 'db_connect.php';

/* ------------------------------------------------------------------
   1. QR-scan → AJAX (unchanged logic)
------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_code'])) {
    $attendance_id = $_POST['qr_code'];

    if (!isset($_SESSION['worker_id'])) {
        echo json_encode(["success" => false, "message" => "Not logged in as a worker."]);
        exit();
    }
    $worker_id = $_SESSION['worker_id'];

    $timestamp = date('Y-m-d H:i:s');
$stmt = $conn->prepare(
    "INSERT INTO qr_attendance (worker_id, attendance_id, scanned_at) VALUES (?, ?, ?)"
);
$stmt->bind_param("iss", $worker_id, $attendance_id, $timestamp);

    $ok = $stmt->execute();
    echo json_encode([
        "success" => $ok,
        "message" => $ok ? "Attendance recorded successfully." : "Failed to record attendance."
    ]);
    exit();
}

/* ------------------------------------------------------------------
   2. Manual 4-digit code (regular form post)
------------------------------------------------------------------ */
$feedback = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_code'])) {
    if (!isset($_SESSION['worker_id'])) {
        $feedback = "Not logged in as a worker.";
    } else {
        $code = $_POST['manual_code'];
        $stmt = $conn->prepare(
            "SELECT attendance_id
             FROM attendance
             WHERE fallback_code = ?
             ORDER BY created_at DESC
             LIMIT 1"
        );
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $stmt->bind_result($attendance_id);
        if ($stmt->fetch()) {
            $stmt->close();
            $worker_id = $_SESSION['worker_id'];
            $ins = $conn->prepare(
                "INSERT INTO qr_attendance (worker_id, attendance_id, scanned_at)
                 VALUES (?, ?, NOW())"
            );
            $ins->bind_param("is", $worker_id, $attendance_id);
            $feedback = $ins->execute()
                ? "Attendance recorded successfully."
                : "Failed to record attendance.";
            $ins->close();
        } else {
            $feedback = "Invalid or expired code.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
 <meta name="viewport" content="width=1024">

  <meta charset="UTF-8">
  <title>Scan Attendance</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>

  <style>
html, body {
  margin: 0;
  padding: 0;
  height: 100%;
  font-family: Arial, Helvetica, sans-serif;
  background: #f9f9f9;
}

.wrapper {
  display: flex;
  height: 100vh;
  overflow: hidden;
}

.sidebar {
  width: 220px;
  background: #3b2f2f;
  color: #fff;
  padding: 30px 20px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.sidebar a {
  color: #fff;
  text-decoration: none;
  font-weight: bold;
  padding: 10px 0;
  border-bottom: 1px solid #fff;
}

.sidebar a:hover {
  color: #f0cfa0;
}

.main {
  flex-grow: 1;
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow: hidden;
}

.topbar {
  background: #EEE6E2;
  padding: 15px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  box-sizing: border-box;
}

.content {
  flex-grow: 1;
  padding: 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  overflow-y: auto;
}

#reader {
  width: 100%;
  max-width: 400px;
  height: auto;
  margin-top: 20px;
  border: 2px solid #ddd;
  background: #000;
}

#message {
  margin-top: 20px;
  font-size: 18px;
  text-align: center;
  word-wrap: break-word;
}

button {
  padding: 10px 20px;
  font-size: 16px;
  margin-top: 15px;
  cursor: pointer;
  border: none;
  border-radius: 5px;
  background: #EEE6E2;
}

button:hover {
  background: #d6c8bf;
}

#cancel-scan {
  background: red;
  color: #fff;
}

#cancel-scan:hover {
  background: darkred;
}

form#manual-form {
  margin-top: 25px;
  text-align: center;
}

input#manual_code {
  padding: 8px;
  width: 120px;
  text-align: center;
  font-size: 16px;
  border-radius: 4px;
  border: 1px solid #ccc;
}



  </style>
</head>
<body>
<div class="wrapper">
  <!-- ==== Sidebar ==== -->
  <div class="sidebar">
    <h2>Worker Panel</h2>
    <a href="worker_dashboard.php">📋 Dashboard</a>
    <a href="WorkerTaskSchedule.php">📋 Task Schedule</a>
    <a href="ScanAttendance.php">📷 Scan Attendance</a>
    <a href="ActivityStatus.php">✅ Activity Status</a>
    <a href="logout.php">🚪 Logout</a>
  </div>

  <!-- ==== Main ==== -->
  <div class="main">
    <div class="topbar"><h2>QR Code Attendance</h2></div>

    <div class="content">
      <!-- Camera scan -->
      <button id="start-scan">Start Scanning</button>
      <div id="reader" style="display:none;"></div>
      <button id="cancel-scan" style="display:none;">Cancel Scan</button>

      <!-- Manual backup code -->
      <form id="manual-form" method="POST" style="margin-top:25px;">
        <label for="manual_code">Enter 4-digit code:</label><br>
        <input type="text" id="manual_code" name="manual_code"
               maxlength="4" pattern="\d{4}" required
               style="padding:8px;margin-top:8px;width:120px;text-align:center;">
        <button type="submit" style="padding:8px 20px;margin-left:8px;">Submit</button>
      </form>

      <!-- Feedback -->
      <p id="message" style="color:<?php echo $feedback ? 'green' : 'inherit'; ?>">
        <?php echo htmlspecialchars($feedback); ?>
      </p>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {

  const qrScanner = new Html5Qrcode("reader");

  /* ---------- Start camera scan ---------- */
  document.getElementById("start-scan").addEventListener("click", () => {
      document.getElementById("start-scan").style.display = "none";
      document.getElementById("reader").style.display = "block";
      document.getElementById("cancel-scan").style.display = "inline-block";

      qrScanner.start(
          { facingMode:"environment" },
          { fps:10, qrbox:350 },
          (decodedText) => {
              qrScanner.stop().then(() => {

                  fetch("ScanAttendance.php", {
                      method:"POST",
                      headers:{"Content-Type":"application/x-www-form-urlencoded"},
                      body:"qr_code=" + encodeURIComponent(decodedText)
                  })
                  .then(res => res.json())
                  .then(data => {
                      document.getElementById("message").textContent = data.message;
                      document.getElementById("message").style.color = data.success ? "green" : "red";
                          if (data.success && navigator.vibrate) {
        navigator.vibrate(200);   // 200 ms buzz
    }
                  
                  });
              });
          },
          (err) => { console.warn("QR not detected:", err); }
      );
  });

  /* ---------- Cancel scan ---------- */
  document.getElementById("cancel-scan").addEventListener("click", () => {
      qrScanner.stop().then(() => {
          document.getElementById("start-scan").style.display = "inline-block";
          document.getElementById("reader").style.display = "none";
          document.getElementById("cancel-scan").style.display = "none";
          document.getElementById("message").textContent = "Scanning canceled.";
          document.getElementById("message").style.color = "red";
      });
  });
});
</script>
</body>
</html>
