<?php

// Include database connection and functions

// Get client call SID

require_once('../private_html/db_login.php');
session_start();

// Require authentication - reject unauthorized requests
requireAuth();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

$ClientCallSid = $_REQUEST['clientCallSid'] ?? null;

try {
    // Get Conference Room Name from Database using JOIN
    $query = "SELECT 
                CallRouting.Volunteer,
                Volunteers.Username,
                Volunteers.Muted 
              FROM CallRouting 
              LEFT JOIN Volunteers ON (CallRouting.Volunteer = Volunteers.TraineeID)
              WHERE CallRouting.CallSid = ?";
              
    $result = dataQuery($query, [$ClientCallSid]);

    // Initialize variables with default values
    $Volunteer = null;
    $TraineeID = null;
    $Muted = null;

    if (!empty($result)) {
        $Volunteer = $result[0]->Volunteer;
        $TraineeID = $result[0]->Username;
        $Muted = $result[0]->Muted;
    }

    // Use trainee ID if muted and exists
    if ($Muted == 1 && $TraineeID) {
        $Volunteer = $TraineeID;
    }

    // Set headers for XML response
    header("Cache-Control: no-cache, must-revalidate");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
    header("Content-Type: text/xml");

    // Generate TwiML response with proper escaping
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    ?>
<Response>
    <Dial method="POST">
        <Conference 
            beep="onExit" 
            startConferenceOnEnter="true" 
            endConferenceOnExit="true" 
            waitUrl="<?= htmlspecialchars($WebAddress) ?>/Audio/waitMusic.php"
        ><?= htmlspecialchars($Volunteer) ?></Conference>
    </Dial>
</Response>
    <?php

} catch (Exception $e) {
    // Log error and return empty response
    error_log("Conference setup error: " . $e->getMessage());
    header("Content-Type: text/xml");
    echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
}
