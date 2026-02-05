<?php

// Authentication check

require_once('../../private_html/db_login.php');
session_start();
if ($_SESSION['auth'] != 'yes') {
    http_response_code(401);
    die("Unauthorized");
}

// Release session lock after authentication check
session_write_close();

// Include database configuration

// Get parameters from request
$UserID = $_REQUEST["UserID"];
$Sender = $_REQUEST["Sender"];
$FinalMessage = $_REQUEST["FinalMessage"];

// Set content type to HTML
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>IM Status</title>
</head>
<body>
<?php

// Debug output (consider removing in production)
echo htmlspecialchars($UserID) . "<br /><br />";
echo htmlspecialchars($Sender) . "<br /><br />";
echo htmlspecialchars($FinalMessage) . "<br /><br />";

// Prepare the base query
$query = "";
$params = [];

// Determine which type of message to send
switch($UserID) {
    case "AdminMessages":
        $query = "UPDATE Volunteers 
                 SET InstantMessage = ?, 
                     IMSender = ? 
                 WHERE LoggedOn = 2";
        $params = [$FinalMessage, $Sender];
        break;
        
    case "All":
        $query = "UPDATE Volunteers 
                 SET InstantMessage = ?, 
                     IMSender = ? 
                 WHERE LoggedOn = 1";
        $params = [$FinalMessage, $Sender];
        break;
        
    default:
        $query = "UPDATE Volunteers 
                 SET InstantMessage = ?, 
                     IMSender = ? 
                 WHERE UserName = ?";
        $params = [$FinalMessage, $Sender, $UserID];
        break;
}

// Execute the query
$result = dataQuery($query, $params);

// Debug output (consider removing in production)
echo htmlspecialchars($query);

// Handle any errors
if ($result === false) {
    http_response_code(500);
    echo "<p>Error sending message</p>";
} else {
    echo "<p>Message sent successfully</p>";
}

// Close the window
echo "<script>window.close();</script>";
?>
</body>
</html>
