<?php
session_start();

// Destroy the session
session_destroy();

// Clear session variables
$_SESSION = array();

// Redirect to login page
header("Location: index.php");
exit();
?>