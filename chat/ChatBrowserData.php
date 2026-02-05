<?php
// Include database connection FIRST to set session configuration
require_once('../../private_html/db_login.php');

session_start();

// Get browser data from GET parameters (sent from chatavailable.js)
$_SESSION['callerBrowser'] = $_GET['callerBrowser'] ?? '';
$_SESSION['callerBrowserVersion'] = $_GET['callerBrowserVersion'] ?? '';
$_SESSION['callerOS'] = $_GET['callerOS'] ?? '';
$_SESSION['callerOSVersion'] = $_GET['callerOSVersion'] ?? '';
$_SESSION['callerBrowserDetail'] = $_GET['callerBrowserDetail'] ?? '';

// Legacy fields for compatibility
$_SESSION['callerBrowserVersionMajor'] = $_SESSION['callerBrowserVersion'];
$_SESSION['callerComputerType'] = $_SESSION['callerOS'];

// Release session lock
session_write_close();



?>


