<?php
require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

session_write_close();

echo "Boaty McBoat Face";
?>