<?php
require_once('../../private_html/db_login.php');
session_start();

// Require admin authentication
requireAdmin();

session_write_close();


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
    //Get file extension
    $ext_arr = explode("\.",basename($file_title));
    $ext = strtolower($ext_arr[count($ext_arr)-1]); //Get the last extension

    // Clean the filename: remove spaces, special characters, and normalize
    $clean_title = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_title); // Replace special chars with underscore
    $clean_title = preg_replace('/_+/', '_', $clean_title); // Replace multiple underscores with single
    $clean_title = trim($clean_title, '_'); // Remove leading/trailing underscores

    //Not really uniqe - but for all practical reasons, it is
    $uniqer = substr(md5(uniqid(rand(),1)),0,5);
    $file_name = $uniqer . '_' . $clean_title;//Get Unique Name with cleaned filename

    $all_types = explode(",",strtolower($types));
    if($types) {
        if(in_array($ext,$all_types));
        else {
            $result = "'".$_FILES[$file_id]['name']."' is not a valid file."; //Show error if any.
            return array('',$result);
        }
    }

    //Where the file must be uploaded to
    if($folder) $folder .= '/';//Add a '/' at the end of the folder
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
            chmod($uploadfile,0777);//Make it universally writable.
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

if($_FILES['newVolunteerVideo']['name']) {
	list($file,$error) = upload('newVolunteerVideo','../HeadquartersMessage');
	if($error) {
		print $error;
	} else {
		header('Location: /Admin');	
	}
}
?>