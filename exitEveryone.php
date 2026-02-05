<?php

// Include database connection and functions


require_once('../private_html/db_login.php');
session_start();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

try {
    // Get all logged-in volunteers
    $query = "SELECT UserName FROM Volunteers WHERE LoggedOn > 0";
    $volunteers = dataQuery($query);

    // Reset status for each volunteer
    foreach ($volunteers as $volunteer) {
        $query = "UPDATE Volunteers
                  SET LoggedOn = 0,
                      Active1 = NULL,
                      Active2 = NULL,
                      OnCall = 0,
                      ChatInvite = NULL,
                      Ringing = NULL,
                      TraineeID = NULL,
                      Muted = 0,
                      IncomingCallSid = NULL
                  WHERE UserName = ?";

        dataQuery($query, [$volunteer->UserName]);

        // Remove from CallControl table
        $deleteControl = "DELETE FROM CallControl WHERE user_id = ?";
        dataQuery($deleteControl, [$volunteer->UserName]);
    }
} catch (Exception $e) {
    // Log error but don't expose details
    error_log("Volunteer cleanup error: " . $e->getMessage());
}
