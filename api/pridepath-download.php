<?php
/**
 * Public API for downloading PridePath spreadsheet files
 * 
 * Usage:
 * - /api/pridepath-download.php?type=state - Downloads the most recent State Laws spreadsheet
 * - /api/pridepath-download.php?type=local - Downloads the most recent Local Laws spreadsheet
 * - /api/pridepath-download.php?type=both - Returns JSON with download links for both
 * 
 * Note: This API always serves the most recent file based on the date in the filename
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Get parameters
$type = strtolower($_GET['type'] ?? '');

// Validate type parameter
if (!in_array($type, ['state', 'local', 'both'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid type. Use "state", "local", or "both"']);
    exit;
}

// Path to PridePath directory
$pridePathDir = dirname(__DIR__) . '/pridePath';

// Function to find the latest file by type
function findLatestFile($dir, $pattern) {
    if (!is_dir($dir)) {
        return null;
    }
    
    $files = glob($dir . '/' . $pattern);
    if (empty($files)) {
        return null;
    }
    
    // Sort files by name (which includes date) in descending order
    rsort($files);
    return $files[0]; // Return the most recent
}

// Function to serve file download
function serveFile($filepath) {
    if (!file_exists($filepath)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'File not found']);
        exit;
    }
    
    $filename = basename($filepath);
    $filesize = filesize($filepath);
    
    // Set headers for file download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $filesize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');
    
    // Output file
    readfile($filepath);
    exit;
}

// Handle different request types
if ($type === 'both') {
    // Return JSON with information about both files
    $stateFile = findLatestFile($pridePathDir, '*State Laws.*');
    $localFile = findLatestFile($pridePathDir, '*Local Laws.*');
    
    $response = [
        'state' => null,
        'local' => null
    ];
    
    if ($stateFile) {
        $response['state'] = [
            'filename' => basename($stateFile),
            'date' => substr(basename($stateFile), 0, 10),
            'download_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                            "://$_SERVER[HTTP_HOST]/api/pridepath-download.php?type=state",
            'size' => filesize($stateFile)
        ];
    }
    
    if ($localFile) {
        $response['local'] = [
            'filename' => basename($localFile),
            'date' => substr(basename($localFile), 0, 10),
            'download_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                            "://$_SERVER[HTTP_HOST]/api/pridepath-download.php?type=local",
            'size' => filesize($localFile)
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} else {
    // Download specific file
    $pattern = ($type === 'state') ? '*State Laws.*' : '*Local Laws.*';
    $file = findLatestFile($pridePathDir, $pattern);
    
    if ($file) {
        serveFile($file);
    } else {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => "No $type laws file found"]);
    }
}
?>