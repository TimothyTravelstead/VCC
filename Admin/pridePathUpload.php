<?php
// Error reporting disabled for production
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, private");

if (@$_SESSION["auth"] != "yes") {
    die("Unauthorized");
}

// Release session lock after authentication check
session_write_close();

include('../../private_html/csrf_protection.php');

// Get CSRF token from POST data
$csrfToken = $_POST['csrf_token'] ?? '';

if (!validateCSRFToken($csrfToken)) {
    die("CSRF token validation failed");
}

/**
 * A function for easily uploading files. This function will automatically generate a new 
 *        file name so that files are not overwritten.
 * Taken From: http://www.bin-co.com/php/scripts/upload_function/
 * Arguments:    $file_id- The name of the input field contianing the file.
 *                $folder    - The folder to which the file should be uploaded to - it must be writable. OPTIONAL
 *                $types    - A list of comma(,) seperated extensions that can be uploaded. If it is empty, anything goes OPTIONAL
 * Returns  : This is somewhat complicated - this function returns an array with two values...
 *                The first element is randomly generated filename to which the file was uploaded to.
 *                The second element is the status - if the upload failed, it will be 'Error : Cannot upload the file 'name.txt'.' or something like that
 */
function upload($file_id, $folder="", $types="") {
    if(!$_FILES[$file_id]['name']) return array('','No file specified');

    $file_title = $_FILES[$file_id]['name'];
    //Get file extension - find last "." and pull everything afterward
    $lastDotPos = strrpos($file_title, '.');
    $ext = ($lastDotPos !== false) ? strtolower(substr($file_title, $lastDotPos + 1)) : '';

    //Use original filename without adding unique prefix
    $file_name = $file_title;

    $all_types = explode(",",strtolower($types));
    if($types) {
        // Trim whitespace from allowed types
        $all_types = array_map('trim', $all_types);
        
        if(!in_array($ext,$all_types)) {
            $result = "'".$_FILES[$file_id]['name']."' is not a valid file. Extension found: '".$ext."'. Allowed types: " . implode(', ', $all_types); //Show error if any.
            return array('',$result);
        }
    }

    //Where the file must be uploaded to
    if($folder && substr($folder, -1) !== '/') {
        $folder .= '/'; //Add a '/' at the end of the folder if not present
    }
    $uploadfile = $folder . $file_name;

    $result = '';
    //Move the file from the stored location to the new location
    
    $move = move_uploaded_file($_FILES[$file_id]['tmp_name'], $uploadfile);
    if (!$move) {
        $result = "Cannot upload the file '".$_FILES[$file_id]['name']."'"; //Show error if any.
        if(!file_exists($folder)) {
            $result .= " : Folder don't exist.";
        } elseif(!is_writable($folder)) {        
            $result .= " : Folder not writable. ".$folder." ";
            $result .= getPermissions($folder);
        } elseif(!is_writable($uploadfile)) {
            $result .= " : File not writable.";
        }
        $file_name = '';
        
    } else {
        if(!$_FILES[$file_id]['size']) { //Check if the file is made
            @unlink($uploadfile);//Delete the Empty file
            $file_name = '';
            $result = "Empty file found - please use a valid file."; //Show the error message
        } else {
            chmod($uploadfile,0644);//Make it readable by web server
        }
    }

    return array($file_name,$result);
}

function getPermissions($folder) {
    $perms = fileperms($folder);

    switch ($perms & 0xF000) {
        case 0xC000: // socket
            $info = 's';
            break;
        case 0xA000: // symbolic link
            $info = 'l';
            break;
        case 0x8000: // regular
            $info = 'r';
            break;
        case 0x6000: // block special
            $info = 'b';
            break;
        case 0x4000: // directory
            $info = 'd';
            break;
        case 0x2000: // character special
            $info = 'c';
            break;
        case 0x1000: // FIFO pipe
            $info = 'p';
            break;
        default: // unknown
            $info = 'u';
    }

    // Owner
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ?
                (($perms & 0x0800) ? 's' : 'x' ) :
                (($perms & 0x0800) ? 'S' : '-'));

    // Group
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ?
                (($perms & 0x0400) ? 's' : 'x' ) :
                (($perms & 0x0400) ? 'S' : '-'));

    // World
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ?
                (($perms & 0x0200) ? 't' : 'x' ) :
                (($perms & 0x0200) ? 'T' : '-'));

    return $info;
}

if($_FILES['pridePathSpreadsheet']['name']) {
    $spreadsheetType = $_POST['spreadsheetType'] ?? 'unknown';
    
    // Validate spreadsheet type
    if (!in_array($spreadsheetType, ['type1', 'type2'])) {
        die("Invalid spreadsheet type specified");
    }
    
    // Set allowed file types for spreadsheets
    $allowedTypes = "xlsx,xls,csv";
    
    // Use absolute path for reliability
    $uploadPath = dirname(__DIR__) . '/pridePath';
    
    // Ensure directory exists
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0775, true);
    }
    
    list($file,$error) = upload('pridePathSpreadsheet', $uploadPath, $allowedTypes);
    if($error) {
        echo "<script>alert('Upload Error: " . addslashes($error) . "'); window.history.back();</script>";
    } else {
        // Create new filename with date and type
        $date = date('Y-m-d');
        $typeLabel = ($spreadsheetType == 'type1') ? 'State Laws' : 'Local Laws';
        
        // Get the file extension
        $lastDotPos = strrpos($file, '.');
        $extension = ($lastDotPos !== false) ? substr($file, $lastDotPos) : '';
        
        // Create new filename: yyyy-mm-dd State Laws.xlsx or yyyy-mm-dd Local Laws.xlsx
        $newFilename = $date . ' ' . $typeLabel . $extension;
        
        // Move/rename the uploaded file to its final name
        $targetPath = $uploadPath . '/' . $file;
        $newTargetPath = $uploadPath . '/' . $newFilename;
        
        // If file exists, it will be overwritten
        if (rename($targetPath, $newTargetPath)) {
            echo "<script>alert('Spreadsheet uploaded successfully as: " . addslashes($newFilename) . "'); window.location.href='index.php';</script>";
        } else {
            echo "<script>alert('File uploaded but could not be renamed'); window.location.href='index.php';</script>";
        }
    }
} else {
    echo "<script>alert('No file specified for upload'); window.history.back();</script>";
}
?>