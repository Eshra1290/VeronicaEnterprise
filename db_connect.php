
<?php
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "veronicaenterprise";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli($servername, $db_username, $db_password, $dbname);
$conn->set_charset("utf8mb4");
?>
