<?php
/**
 * Get Full Call Details from all database tables
 *
 * Aggregates data from:
 * - CallerHistory: Basic call info, queue metrics
 * - CallLog: Demographics, topics, notes
 * - TwilioStatusLog: Status events timeline
 * - TwilioErrorLog: Any errors/warnings
 */

require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

// Release session lock
session_write_close();

header('Content-Type: application/json');

$callSid = $_REQUEST['CallSid'] ?? null;

if (!$callSid) {
    echo json_encode(['error' => 'CallSid is required']);
    exit;
}

$response = [
    'callSid' => $callSid,
    'overview' => null,
    'queueMetrics' => null,
    'callerInfo' => null,
    'callTopics' => null,
    'volunteerNotes' => null,
    'statusTimeline' => [],
    'errors' => [],
    'warnings' => [],
    'technical' => null,
    'hasCallLog' => false,
    'hasErrors' => false,
    'hasWarnings' => false,
    'hasCallerHistory' => false,
    // Training session fields
    'isTrainingCall' => false,
    'trainingInfo' => null
];

// Helper function to determine if an error is a carrier warning (SHAKEN/STIR related)
function isCarrierWarning($errorCode, $errorMessage, $payload) {
    // SHAKEN/STIR error codes
    $warningCodes = ['32020', '32021'];
    if (in_array($errorCode, $warningCodes)) {
        return true;
    }

    // Check for SHAKEN/STIR keywords in message or payload
    $keywords = ['SHAKEN', 'STIR', 'PASSporT', 'PPT'];
    $searchText = strtoupper($errorMessage . ' ' . json_encode($payload));
    foreach ($keywords as $keyword) {
        if (strpos($searchText, $keyword) !== false) {
            return true;
        }
    }

    return false;
}

// 1. Get CallerHistory data
$callerHistoryQuery = "SELECT
    CallerID, Location, Date, Time, Hotline, Length, Category, VolunteerID,
    callStart, callEnd, intoQueue, outOfQueue, queueMessages, Blocked
FROM CallerHistory
WHERE CALLSID = ?";

$callerHistory = dataQuery($callerHistoryQuery, [$callSid]);

if ($callerHistory && !isset($callerHistory['error']) && count($callerHistory) > 0) {
    $ch = $callerHistory[0];
    $response['hasCallerHistory'] = true;

    // Look up volunteer's full name from Volunteers table
    // Note: VolunteerID in CallerHistory maps to UserName in Volunteers table
    $volunteerFullName = null;
    if (!empty($ch->VolunteerID)) {
        $volunteerQuery = "SELECT CONCAT(FirstName, ' ', LastName) AS FullName FROM volunteers WHERE UserName = ?";
        $volunteerResult = dataQuery($volunteerQuery, [$ch->VolunteerID]);
        if ($volunteerResult && !isset($volunteerResult['error']) && count($volunteerResult) > 0) {
            $volunteerFullName = trim($volunteerResult[0]->FullName);
        }
    }

    // Overview section
    $response['overview'] = [
        'callerID' => $ch->CallerID,
        'location' => $ch->Location,
        'date' => $ch->Date,
        'time' => $ch->Time,
        'hotline' => $ch->Hotline,
        'length' => $ch->Length,
        'category' => $ch->Category,
        'volunteerID' => $ch->VolunteerID,
        'volunteerName' => $volunteerFullName,
        'blocked' => $ch->Blocked ? true : false
    ];

    // Queue metrics section
    $response['queueMetrics'] = [
        'callStart' => $ch->callStart,
        'callEnd' => $ch->callEnd,
        'intoQueue' => $ch->intoQueue,
        'outOfQueue' => $ch->outOfQueue,
        'queueMessages' => $ch->queueMessages
    ];

    // Calculate wait time if we have queue times
    if ($ch->intoQueue && $ch->outOfQueue) {
        $intoTime = strtotime($ch->Date . ' ' . $ch->intoQueue);
        $outTime = strtotime($ch->Date . ' ' . $ch->outOfQueue);
        if ($intoTime && $outTime && $outTime > $intoTime) {
            $waitSeconds = $outTime - $intoTime;
            $response['queueMetrics']['queueWaitTime'] = gmdate("i:s", $waitSeconds);
        }
    }
}

// 2. Get CallLog data (demographics, topics, notes)
$callLogQuery = "SELECT
    RecordID, GENDER, AGE, Ethnicity, CallLogNotes,
    COMEOUT, AIDS_HIV, TGENDER, RELATION, PARENT, SUICIDE, RUNAWAY, VIOLENCE, SELF_EST, OTHER,
    AIDS, BARS, BISEXUAL, BOOKSTORE, BUSINESS, COMMUNITY, COUNSELING, CRISIS, CULTURAL,
    FUNDRAISE, HEALTH, HOTEL, HOTLINE, LEGAL, LESBIAN, MEDIA, POLITICAL, PROFESSIONAL,
    RECOVERY, RELIGION, RESTAURANT, SENIOR, SOCIAL, SPORTS, STUDENT, SUPPORT, TRANSGENDER, YOUTH,
    Senior_Housing, Senior_Meals, Senior_Medical, Senior_Legal, Senior_Transportation,
    Senior_Social, Senior_Support, Senior_None,
    Internet_Google, Internet_Facebook, Internet_Twitter, Internet_Instagram, Internet_Other
FROM CallLog
WHERE CallSid = ?";

$callLog = dataQuery($callLogQuery, [$callSid]);

if ($callLog && !isset($callLog['error']) && count($callLog) > 0) {
    $cl = $callLog[0];
    $response['hasCallLog'] = true;

    // Caller info section
    $response['callerInfo'] = [
        'gender' => urldecode($cl->GENDER ?? ''),
        'age' => urldecode($cl->AGE ?? ''),
        'ethnicity' => urldecode($cl->Ethnicity ?? '')
    ];

    // Volunteer notes
    $response['volunteerNotes'] = urldecode($cl->CallLogNotes ?? '');

    // Call topics - Issues (caller concerns)
    $issues = [];
    $issueFields = [
        'COMEOUT' => 'Coming Out',
        'AIDS_HIV' => 'AIDS/HIV',
        'TGENDER' => 'Transgender Issues',
        'RELATION' => 'Relationships',
        'PARENT' => 'Parenting',
        'SUICIDE' => 'Suicide/Crisis',
        'RUNAWAY' => 'Runaway/Homeless',
        'VIOLENCE' => 'Violence/Abuse',
        'SELF_EST' => 'Self-Esteem',
        'OTHER' => 'Other Issues'
    ];

    foreach ($issueFields as $field => $label) {
        if (isset($cl->$field) && $cl->$field) {
            $issues[] = $label;
        }
    }

    // Call topics - Resources (referrals)
    $resources = [];
    $resourceFields = [
        'AIDS' => 'AIDS Services',
        'BARS' => 'Bars/Nightlife',
        'BISEXUAL' => 'Bisexual Resources',
        'BOOKSTORE' => 'Bookstores',
        'BUSINESS' => 'Business',
        'COMMUNITY' => 'Community Centers',
        'COUNSELING' => 'Counseling',
        'CRISIS' => 'Crisis Services',
        'CULTURAL' => 'Cultural',
        'FUNDRAISE' => 'Fundraising',
        'HEALTH' => 'Health Services',
        'HOTEL' => 'Hotels',
        'HOTLINE' => 'Hotlines',
        'LEGAL' => 'Legal Services',
        'LESBIAN' => 'Lesbian Resources',
        'MEDIA' => 'Media',
        'POLITICAL' => 'Political',
        'PROFESSIONAL' => 'Professional',
        'RECOVERY' => 'Recovery/Addiction',
        'RELIGION' => 'Religious/Spiritual',
        'RESTAURANT' => 'Restaurants',
        'SENIOR' => 'Senior Services',
        'SOCIAL' => 'Social Groups',
        'SPORTS' => 'Sports/Recreation',
        'STUDENT' => 'Student Groups',
        'SUPPORT' => 'Support Groups',
        'TRANSGENDER' => 'Transgender Services',
        'YOUTH' => 'Youth Services'
    ];

    foreach ($resourceFields as $field => $label) {
        if (isset($cl->$field) && $cl->$field) {
            $resources[] = $label;
        }
    }

    // Senior-specific topics
    $seniorTopics = [];
    $seniorFields = [
        'Senior_Housing' => 'Housing',
        'Senior_Meals' => 'Meals',
        'Senior_Medical' => 'Medical',
        'Senior_Legal' => 'Legal',
        'Senior_Transportation' => 'Transportation',
        'Senior_Social' => 'Social',
        'Senior_Support' => 'Support',
        'Senior_None' => 'None'
    ];

    foreach ($seniorFields as $field => $label) {
        if (isset($cl->$field) && $cl->$field) {
            $seniorTopics[] = $label;
        }
    }

    // Internet source
    $internetSource = null;
    $internetFields = [
        'Internet_Google' => 'Google',
        'Internet_Facebook' => 'Facebook',
        'Internet_Twitter' => 'Twitter',
        'Internet_Instagram' => 'Instagram',
        'Internet_Other' => 'Other'
    ];

    foreach ($internetFields as $field => $label) {
        if (isset($cl->$field) && $cl->$field) {
            $internetSource = $label;
            break;
        }
    }

    $response['callTopics'] = [
        'issues' => $issues,
        'resources' => $resources,
        'seniorTopics' => $seniorTopics,
        'internetSource' => $internetSource,
        'recordID' => $cl->RecordID
    ];
}

// 3. Get Twilio Status Log events
$statusQuery = "SELECT
    timestamp,
    CallStatus,
    StatusCallbackEvent,
    Direction,
    ConferenceSid,
    FriendlyName,
    Muted,
    Hold,
    CallDuration,
    SequenceNumber,
    FromNumber,
    ToNumber,
    FromCity,
    FromState,
    FromCountry,
    ToCity,
    ToState,
    ToCountry,
    ParentCallSid,
    ApiVersion
FROM TwilioStatusLog
WHERE CallSid = ?
ORDER BY timestamp ASC, SequenceNumber ASC";

$statusResults = dataQuery($statusQuery, [$callSid]);

if ($statusResults && !isset($statusResults['error'])) {
    foreach ($statusResults as $row) {
        $response['statusTimeline'][] = [
            'timestamp' => $row->timestamp,
            'status' => $row->CallStatus,
            'event' => $row->StatusCallbackEvent,
            'direction' => $row->Direction,
            'conferenceSid' => $row->ConferenceSid,
            'friendlyName' => $row->FriendlyName,
            'muted' => $row->Muted,
            'hold' => $row->Hold,
            'duration' => $row->CallDuration,
            'sequence' => $row->SequenceNumber
        ];

        // Capture technical details from first status event
        if (!$response['technical']) {
            $response['technical'] = [
                'callSid' => $callSid,
                'parentCallSid' => $row->ParentCallSid,
                'conferenceSid' => $row->ConferenceSid,
                'friendlyName' => $row->FriendlyName,
                'direction' => $row->Direction,
                'fromNumber' => $row->FromNumber,
                'toNumber' => $row->ToNumber,
                'fromCity' => $row->FromCity,
                'fromState' => $row->FromState,
                'fromCountry' => $row->FromCountry,
                'toCity' => $row->ToCity,
                'toState' => $row->ToState,
                'toCountry' => $row->ToCountry,
                'apiVersion' => $row->ApiVersion
            ];
        }
    }
}

// 3b. Check if this was a training session call
// Training conferences use the trainer's username as FriendlyName
if ($response['technical'] && !empty($response['technical']['friendlyName'])) {
    $friendlyName = $response['technical']['friendlyName'];

    // Check if FriendlyName matches a trainer's username
    $trainerQuery = "SELECT UserName, CONCAT(FirstName, ' ', LastName) AS FullName, Trainer FROM volunteers WHERE UserName = ? AND Trainer = 1";
    $trainerResult = dataQuery($trainerQuery, [$friendlyName]);

    if ($trainerResult && !isset($trainerResult['error']) && count($trainerResult) > 0) {
        $trainer = $trainerResult[0];
        $response['isTrainingCall'] = true;

        // Get mute events from status timeline for this specific call
        // Includes: participant-mute/participant-unmute (Twilio-confirmed),
        // participant-join (initial state with muted value)
        $muteEvents = [];
        foreach ($response['statusTimeline'] as $event) {
            $isMuteRelated = in_array($event['event'], ['participant-mute', 'participant-unmute']) ||
                             ($event['muted'] !== null && $event['event'] === 'participant-join');
            if ($isMuteRelated) {
                $muteEvents[] = [
                    'timestamp' => $event['timestamp'],
                    'event' => $event['event'],
                    'muted' => $event['muted'] ? true : false,
                    'callSid' => $callSid
                ];
            }
        }

        // Query system-initiated mute events (app_mute/app_unmute) by conference FriendlyName
        // These are logged when external calls trigger muting of training participants
        $conferenceMuteQuery = "SELECT
            timestamp, CallSid, StatusCallbackEvent, Muted, RawRequest
        FROM TwilioStatusLog
        WHERE FriendlyName = ?
          AND StatusCallbackEvent IN ('app_mute', 'app_unmute')
        ORDER BY timestamp ASC";

        $conferenceMuteResults = dataQuery($conferenceMuteQuery, [$friendlyName]);

        if ($conferenceMuteResults && !isset($conferenceMuteResults['error'])) {
            foreach ($conferenceMuteResults as $row) {
                // Parse RawRequest to get initiator info
                $rawData = json_decode($row->RawRequest, true);
                $initiator = $rawData['initiator'] ?? 'unknown';

                $muteEvents[] = [
                    'timestamp' => $row->timestamp,
                    'event' => $row->StatusCallbackEvent,
                    'muted' => $row->StatusCallbackEvent === 'app_mute',
                    'callSid' => $row->CallSid,
                    'initiator' => $initiator
                ];
            }

            // Sort all mute events by timestamp
            usort($muteEvents, function($a, $b) {
                return strcmp($a['timestamp'], $b['timestamp']);
            });
        }

        $response['trainingInfo'] = [
            'trainerUsername' => $trainer->UserName,
            'trainerFullName' => trim($trainer->FullName),
            'conferenceName' => $friendlyName,
            'muteEvents' => $muteEvents
        ];
    }
}

// 4. Get Twilio Errors
$errorQuery = "SELECT
    received_at,
    Sid,
    Level,
    Payload
FROM TwilioErrorLog
WHERE JSON_SEARCH(Payload, 'one', ?) IS NOT NULL
   OR JSON_EXTRACT(Payload, '$.call_sid') = ?
   OR JSON_EXTRACT(Payload, '$.CallSid') = ?
ORDER BY received_at ASC";

$errorResults = dataQuery($errorQuery, [$callSid, $callSid, $callSid]);

if ($errorResults && !isset($errorResults['error'])) {
    foreach ($errorResults as $row) {
        $payload = json_decode($row->Payload, true);

        // Extract error code and message from various possible locations
        $errorCode = $payload['error_code'] ?? $payload['ErrorCode'] ?? null;
        $errorMessage = null;

        if (isset($payload['more_info'])) {
            if (is_array($payload['more_info'])) {
                $errorMessage = $payload['more_info']['parserMessage']
                    ?? $payload['more_info']['Msg']
                    ?? $payload['more_info']['message']
                    ?? json_encode($payload['more_info']);
            } else {
                $errorMessage = $payload['more_info'];
            }
        } elseif (isset($payload['error_message'])) {
            $errorMessage = $payload['error_message'];
        } elseif (isset($payload['message'])) {
            $errorMessage = $payload['message'];
        }

        $entry = [
            'timestamp' => $row->received_at,
            'sid' => $row->Sid,
            'level' => $row->Level,
            'errorCode' => $errorCode,
            'errorMessage' => $errorMessage
        ];

        // Categorize as warning or error
        if (isCarrierWarning($errorCode, $errorMessage ?? '', $payload)) {
            $response['warnings'][] = $entry;
            $response['hasWarnings'] = true;
        } else {
            $response['errors'][] = $entry;
            $response['hasErrors'] = true;
        }
    }
}

// If no technical details from status log, create minimal from CallerHistory
if (!$response['technical'] && $response['overview']) {
    $response['technical'] = [
        'callSid' => $callSid,
        'parentCallSid' => null,
        'conferenceSid' => null,
        'friendlyName' => null,
        'direction' => 'inbound',
        'fromNumber' => $response['overview']['callerID'],
        'toNumber' => null,
        'fromCity' => null,
        'fromState' => null,
        'fromCountry' => null,
        'toCity' => null,
        'toState' => null,
        'toCountry' => null,
        'apiVersion' => null
    ];
}

echo json_encode($response);
