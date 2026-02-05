<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours
require_once('../private_html/db_login.php');

// Now start the session with the correct configuration
session_start();

// Input validation and sanitization
$ConferenceRoom = filter_input(INPUT_GET, 'room', FILTER_SANITIZE_STRING) ?? '';
$ConferenceType = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_STRING) ?? '';

// Set default conference settings
$conferenceSettings = [
    'muted' => 'false',
    'beep' => 'true',
    'startConferenceOnEnter' => 'true',
    'endConferenceOnExit' => 'true',
    'waitUrl' => $WebAddress . '/Audio/waitMusic.php',
    // Status callback for debugging training session events
    'statusCallback' => $WebAddress . '/twilioStatus.php',
    'statusCallbackMethod' => 'POST',
    'statusCallbackEvent' => 'start end join leave mute hold'
];

// Override settings for monitor type
if (strtolower($ConferenceType) === 'monitor') {
    $conferenceSettings['muted'] = 'true';
    $conferenceSettings['beep'] = 'false';
    $conferenceSettings['startConferenceOnEnter'] = 'false';
    $conferenceSettings['endConferenceOnExit'] = 'false';
}

// Training sessions: Always start unmuted, let JavaScript handle mute control
// NOTE: Twilio webhooks don't have access to user sessions (requests come from Twilio servers)
// so we cannot determine who the user is server-side. All muting must be handled client-side.
if (strtolower($ConferenceType) === 'trainer' || strtolower($ConferenceType) === 'trainee') {
    // Both trainer and trainee start unmuted - JavaScript will mute as needed for external calls
    $conferenceSettings['muted'] = 'false';

    // Trainees should not end the conference when they leave
    if (strtolower($ConferenceType) === 'trainee') {
        $conferenceSettings['endConferenceOnExit'] = 'false';
    }
}

// Release session lock after reading session data (or if no session read needed)
session_write_close();

// Build conference attributes string
$conferenceAttributes = implode(' ', array_map(
    function ($key, $value) {
        return sprintf('%s="%s"', $key, htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
    },
    array_keys($conferenceSettings),
    $conferenceSettings
));

// Set content type to XML
header('Content-Type: application/xml; charset=utf-8');

// Generate the TwiML response
$response = sprintf(
    '<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Dial action="%s/trainingCallEstablished.php" method="POST">
        <Conference %s>%s</Conference>
    </Dial>
</Response>',
    htmlspecialchars($WebAddress, ENT_QUOTES, 'UTF-8'),
    $conferenceAttributes,
    htmlspecialchars($ConferenceRoom, ENT_QUOTES, 'UTF-8')
);

echo trim($response);
