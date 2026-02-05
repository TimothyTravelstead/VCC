<?php
// Include db_login.php FIRST to get session configuration (8-hour timeout)
require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

// Start session (inherits 8-hour timeout from db_login.php)
session_start();

// Release session lock immediately (no database operations)
session_write_close();

// session_destroy();

?>