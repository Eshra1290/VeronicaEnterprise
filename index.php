<?php

session_start();

if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
        exit();
    }
    if ($_SESSION['role'] === 'worker') {
        header("Location: worker_dashboard.php");
        exit();
    }
}

/* Not logged in?  Show the login screen */
require 'login.php';
