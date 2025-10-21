<?php
require_once 'includes/config.php';

// Clear all user/admin session
unset($_SESSION['user_logged_in']);
unset($_SESSION['user_id']);
unset($_SESSION['user_username']);
unset($_SESSION['user_name']);
unset($_SESSION['user_email']);
unset($_SESSION['user_role']);

// Destroy session
session_destroy();

// Redirect to login
redirect('login.php');
?>