<?php
/**
 * Legacy login verification file - REDIRECT TO NEW VERSION
 * This file redirects all login attempts to the new loginverify2.php
 * which includes modern security features and CSRF protection.
 */

// Log the redirect for debugging
error_log("LOGIN REDIRECT: Old loginverify.php called, redirecting to loginverify2.php");

// Forward all POST data to the new file
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Build the redirect URL with POST data
    $postData = http_build_query($_POST);
    
    // Use curl to forward the POST request to the new file
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://' . $_SERVER['HTTP_HOST'] . '/loginverify2.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    
    // Extract headers and body
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    // Forward the response headers (especially redirects)
    $headerLines = explode("\n", $headers);
    foreach ($headerLines as $header) {
        $header = trim($header);
        if (!empty($header) && strpos($header, 'HTTP/') !== 0) {
            header($header);
        }
    }
    
    // Set the HTTP response code
    http_response_code($httpCode);
    
    // Output the response body
    echo $body;
    exit;
} else {
    // For GET requests, just redirect
    header("Location: loginverify2.php");
    exit;
}
?>