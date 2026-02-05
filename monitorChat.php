<?php

// Include database connection and functions

// Get session and request variables

require_once '../private_html/db_login.php';
session_start();

// Require authentication - reject unauthorized requests
requireAuth();

$VolunteerID = $_SESSION['UserID'] ?? null;
$Reset = $_REQUEST['reset'] ?? null;
$monitoredUser = $_REQUEST['monitoredUser'] ?? null;

// Reset message counter if requested
if ($Reset == 1) {
    $_SESSION["MessageNumber"] = null;
}

// Initialize message counters
$MessageNumber = $_SESSION["MessageNumber"] ?? 0;
$MessageSentCount = $MessageNumber ? null : 0;

try {
    // Get active chat rooms for monitored user
    $query = "SELECT Active1, Active2 
              FROM Volunteers 
              WHERE UserName = ?";
    $result = dataQuery($query, [$monitoredUser]);

    if (empty($result)) {
        throw new Exception("Could not find monitored user");
    }

    $room1 = $result[0]->Active1 ?: "Empty";
    $room2 = $result[0]->Active2 ?: "Empty";

    // Get chat messages for both rooms
    $query = "SELECT 
                Status,
                Name,
                Text,
                MessageNumber,
                CallerID,
                callerDelivered,
                volunteerDelivered
              FROM Chat 
              WHERE CallerID IN (?, ?)
              ORDER BY MessageNumber ASC";
    
    $messages = dataQuery($query, [$room1, $room2]);

    $messagesArray = [];

    // Process messages
    foreach ($messages as $message) {
        // Determine which room this message belongs to
        $roomNumber = match($message->CallerID) {
            $room1 => 1,
            $room2 => 2,
            default => null
        };

        if ($roomNumber !== null) {
            $singleMessage = [
                'id' => $message->MessageNumber,
                'room' => $roomNumber,
                'name' => $message->Name,
                'text' => $message->Text,
                'status' => $message->Status,
                'callerID' => $message->CallerID,
                'callerDelivered' => $message->callerDelivered,
                'volunteerDelivered' => $message->volunteerDelivered
            ];

            $messagesArray[] = $singleMessage;
            
            // Update session message number
            $_SESSION["MessageNumber"] = $message->MessageNumber;
            $MessageSentCount++;
        }
    }

    // Set JSON content type header
    header('Content-Type: application/json');
    
    // Return messages as JSON
    echo json_encode($messagesArray);

} catch (Exception $e) {
    // Log error and return empty array
    error_log("Chat monitor error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([]);
}
