<?php
require_once 'includes/config.php';

// Clear user session
unset($_SESSION['user_logged_in']);
unset($_SESSION['user_id']);
unset($_SESSION['user_username']);
unset($_SESSION['user_name']);
unset($_SESSION['user_email']);

// Redirect to home
redirect('index.php');
?>