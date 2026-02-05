<?php
// End a Twilio conference
require_once '../vendor/autoload.php';
require_once '../../private_html/db_login.php';

use Twilio\Rest\Client;
use Twilio\Exceptions\RestException;

header('Content-Type: application/json');

// Start session if needed, then immediately release lock
session_start();
session_write_close();

// Load environment variables from .env file
if (file_exists('../../private_html/.env')) {
    $lines = file('../../private_html/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($name, $value) = explode('=', $line, 2);
            putenv(trim($name) . '=' . trim($value));
        }
    }
}

// Get Twilio credentials
$accountSid = getenv('TWILIO_ACCOUNT_SID');
$authToken = getenv('TWILIO_AUTH_TOKEN');

if (empty($accountSid) || empty($authToken)) {
    error_log("CRITICAL: Twilio credentials missing - SID: " . ($accountSid ? "present" : "missing") . ", Token: " . ($authToken ? "present" : "missing"));
    http_response_code(500);
    echo json_encode(['error' => 'Twilio credentials not configured']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$conferenceId = $input['conferenceId'] ?? '';
$callerId = $input['callerId'] ?? '';
$callerRole = $input['callerRole'] ?? '';

error_log("INFO: endConference called - conferenceId: $conferenceId, callerId: $callerId, callerRole: $callerRole");

try {
    // Conference ID should come from JavaScript (which gets it from hidden field)
    // Database lookup is only a LAST-RESORT fallback for edge cases
    if (empty($conferenceId)) {
        error_log("WARNING: Conference ID empty - JavaScript didn't provide it! Attempting database fallback.");

        if (!empty($callerId)) {
            // Last-resort: Look up conference ID (trainer) from database
            error_log("INFO: Database fallback - looking up conference owner for caller: $callerId");

            // Check if the caller is a trainee - look up their trainer (who owns the conference)
            $trainerQuery = "SELECT UserName FROM volunteers WHERE FIND_IN_SET(?, TraineeID) > 0 LIMIT 1";
            $trainerResult = dataQuery($trainerQuery, [$callerId]);

            if (!empty($trainerResult)) {
                $conferenceId = $trainerResult[0]->UserName;
                error_log("INFO: Database fallback found conference ID (trainer): $conferenceId");
            } else {
                // Caller might be the trainer themselves
                $conferenceId = $callerId;
                error_log("INFO: Database fallback - using caller ID as conference ID: $conferenceId");
            }
        } else {
            error_log("ERROR: No conferenceId AND no callerId - cannot determine conference");
            http_response_code(400);
            echo json_encode(['error' => 'Conference ID required and could not be determined']);
            exit;
        }
    } else {
        error_log("INFO: Using conferenceId from JavaScript: $conferenceId");
    }

    error_log("INFO: Starting conference termination for: $conferenceId");
    $client = new Client($accountSid, $authToken);
    error_log("INFO: Twilio client initialized successfully");
    
    // First check if conference exists
    $conference = null;
    try {
        $conference = $client->conferences($conferenceId)->fetch();
        error_log("INFO: Conference $conferenceId found with status: " . $conference->status);
        
        // If conference is already completed, just return success
        if ($conference->status === 'completed') {
            error_log("INFO: Conference $conferenceId already completed");
            echo json_encode([
                'success' => true, 
                'message' => "Conference already completed",
                'conferenceId' => $conferenceId
            ]);
            exit;
        }
        
    } catch (RestException $e) {
        // Handle specific Twilio errors
        if ($e->getStatusCode() === 404) {
            error_log("INFO: Conference $conferenceId not found (404) - already ended");
            echo json_encode([
                'success' => true, 
                'message' => "Conference not found (already ended)",
                'conferenceId' => $conferenceId
            ]);
            exit;
        } else {
            error_log("ERROR: Twilio REST error fetching conference $conferenceId: " . $e->getMessage() . " (Code: " . $e->getStatusCode() . ")");
            throw $e;
        }
    }
    
    // Get all participants and disconnect them
    $participants = [];
    try {
        $participants = $client->conferences($conferenceId)->participants->read();
        error_log("INFO: Conference $conferenceId has " . count($participants) . " participants");
    } catch (RestException $e) {
        if ($e->getStatusCode() === 404) {
            error_log("INFO: Conference $conferenceId participants not found - conference likely ended");
            echo json_encode([
                'success' => true, 
                'message' => "Conference already ended (no participants found)",
                'conferenceId' => $conferenceId
            ]);
            exit;
        } else {
            error_log("ERROR: Failed to get participants for conference $conferenceId: " . $e->getMessage());
            throw $e;
        }
    }
    
    $disconnectedCount = 0;
    $errors = [];
    
    foreach ($participants as $participant) {
        try {
            error_log("INFO: Attempting to disconnect participant: " . $participant->callSid);
            
            // First try to remove from conference (this also disconnects)
            $client->conferences($conferenceId)
                   ->participants($participant->callSid)
                   ->delete();
                   
            $disconnectedCount++;
            error_log("SUCCESS: Disconnected participant: " . $participant->callSid);
            
        } catch (RestException $e) {
            $errorMsg = "Failed to disconnect participant " . $participant->callSid . ": " . $e->getMessage() . " (Code: " . $e->getStatusCode() . ")";
            error_log("ERROR: " . $errorMsg);
            $errors[] = $errorMsg;
            
            // If participant is already gone (404), that's fine
            if ($e->getStatusCode() === 404) {
                error_log("INFO: Participant " . $participant->callSid . " already disconnected");
                $disconnectedCount++;
            }
        }
    }
    
    // Try to update conference status to completed
    try {
        if ($conference && $conference->status !== 'completed') {
            $updatedConference = $client->conferences($conferenceId)->update(['status' => 'completed']);
            error_log("SUCCESS: Conference $conferenceId marked as completed, final status: " . $updatedConference->status);
        }
    } catch (RestException $e) {
        error_log("WARNING: Could not mark conference as completed: " . $e->getMessage() . " (Code: " . $e->getStatusCode() . ")");
        // This is not critical - conference might auto-complete when all participants leave
    }
    
    $response = [
        'success' => true, 
        'message' => "Conference terminated - disconnected $disconnectedCount participants",
        'conferenceId' => $conferenceId,
        'participantsDisconnected' => $disconnectedCount
    ];
    
    if (!empty($errors)) {
        $response['warnings'] = $errors;
    }
    
    error_log("SUCCESS: Conference $conferenceId termination complete");
    echo json_encode($response);
    
} catch (RestException $e) {
    $errorMsg = "Twilio API error ending conference $conferenceId: " . $e->getMessage() . " (Status: " . $e->getStatusCode() . ")";
    error_log("CRITICAL: " . $errorMsg);
    
    http_response_code(500);
    echo json_encode([
        'error' => $errorMsg,
        'statusCode' => $e->getStatusCode(),
        'conferenceId' => $conferenceId
    ]);
    
} catch (Exception $e) {
    $errorMsg = "Unexpected error ending conference $conferenceId: " . $e->getMessage();
    error_log("CRITICAL: " . $errorMsg);
    
    http_response_code(500);
    echo json_encode([
        'error' => $errorMsg,
        'conferenceId' => $conferenceId
    ]);
}
?>