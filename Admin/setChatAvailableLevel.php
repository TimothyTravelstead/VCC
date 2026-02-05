<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours for all-day admin sessions
require_once('../../private_html/db_login.php');
include('../../private_html/csrf_protection.php');

// Now start the session with the correct configuration
session_start();

// Check authentication first
if (@$_SESSION["auth"] != "yes") {
    http_response_code(403);
    die("Unauthorized");
}

// Release session lock after authentication check
session_write_close();

// Validate CSRF token for security
requireValidCSRFToken($_REQUEST);
$chatAvailableLevel1 = $_REQUEST["level1"];
$chatAvailableLevel2 = $_REQUEST["level2"];

if(!$chatAvailableLevel1) {
	$chatAvailableLevel1 = 2;
}

if(!$chatAvailableLevel2) {
	$chatAvailableLevel2 = 4;
}

$var_str = var_export($chatAvailableLevel1, true);
$var = "<?php\n\n\$chatAvailableLevel1 = $var_str;\n\n?>";
file_put_contents('../chat/firstChatAvailableLevel.php', $var);


$var_str = var_export($chatAvailableLevel2, true);
$var = "<?php\n\n\$chatAvailableLevel2 = $var_str;\n\n?>";
file_put_contents('../chat/secondChatAvailableLevel.php', $var);

echo 0;


?>


