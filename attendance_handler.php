<?php
// Start session
session_start();

// Include database connection
include 'db_connect.php';

// Check if the code parameter exists
if (isset($_GET['code'])) {
    $qr_code = $_GET['code'];

    // Query the database to find the user
    $sql = "SELECT * FROM workers WHERE qr_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $qr_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // Worker found
        $worker = $result->fetch_assoc();

        // Record attendance
        $sql = "INSERT INTO attendance (worker_id, timestamp) VALUES (?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $worker['id']);
        $stmt->execute();

        echo "Attendance recorded for " . htmlspecialchars($worker['name']) . ".";
    } else {
        echo "Invalid QR code. Worker not found.";
    }

    $stmt->close();
} else {
    echo "No QR code detected.";
}
?>
