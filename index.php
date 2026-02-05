<?php
// Redirect to login.php to bypass caching issues
// This file exists for backward compatibility with any hardcoded links to index.php

require_once '../private_html/db_login.php';
session_start();

// If user is already authenticated, redirect to console instead of login
if (isset($_SESSION['auth']) && $_SESSION['auth'] === 'yes') {
    session_write_close();
    header("Location: index2.php");
    exit();
}

session_write_close();
header("Location: login.php");
exit();
?>
