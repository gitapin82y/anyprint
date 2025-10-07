<?php
require_once '../includes/config.php';

// Clear admin session
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_name']);

// Destroy session
session_destroy();

// Redirect to login
redirect('login.php');
?>