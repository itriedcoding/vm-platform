<?php
session_start();
require_once 'includes/auth.php';

$auth = new Auth();
$result = $auth->logout();

// Redirect to login page
header('Location: login.php?message=' . urlencode($result['message']));
exit();
?>