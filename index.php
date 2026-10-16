<?php
// index.php - The Gatekeeper

session_start();

// Check if the user is already logged in
if (isset($_SESSION['user_id'])) {
    // If logged in, send them straight to the Dashboard
    header("Location: pages/home.php");
    exit(); // Always exit after a header redirect!
} else {
    // If not logged in, send them to the Login page
    header("Location: pages/login.php");
    exit();
}
