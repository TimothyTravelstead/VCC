<?php
// Include db_login.php FIRST to set session configuration
require_once('../../private_html/db_login.php');

session_start();

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

error_reporting(0);
ini_set('display_errors', 0);

$ipAddress = $_SESSION['ipAddress'] ?? null;
$UniqueCallerID = $_REQUEST["UniqueCallerID"] ?? null;
$referringPage = $_SESSION['referringPage'] ?? null;


$browser = array();

$browser['browser'] = $_SESSION['callerBrowser'];
$browser['version'] = $_SESSION['callerBrowserVersion'];
$browser['platform_description'] =  $_SESSION['callerOS'];
$browser['platform_version'] =  $_SESSION['callerOSVersion'];

if($_SESSION['callerOS'] == "iOS" || $_SESSION['callerOS'] == "Android") {
	$_SESSION['mobile'] = true;
}

$chatRoomID = $_SESSION['chatRoomID'];
session_write_close();

$userID = $_REQUEST["userID"];


if(!$UniqueCallerID) {
	$UniqueCallerID = $ipAddress;
}

if(!$referringPage) {
	$referringPage = "unknown";
}





// Include the mysql databsae location and login information
include('../../../master/applications/dgqtkqjasj/private_html/db_login_GroupChat.php');


$params = [$UniqueCallerID , $referringPage , $userID];
$query = "UPDATE chatStatus set UniqueCallerID = ? , ReferringSite = ? WHERE userID = ?";
$result = dataQuery($query, $params);





$params = 	[$userID , 
			$browser['browser'] , 
			$browser['version'] , 
			$browser['platform_description'] , 
			$browser['platform_version'] , 
			$UniqueCallerID,
			$ipAddress ,
			$referringPage,
			$chatRoomID];
			
	$query = "INSERT INTO stats VALUES (null, now(), null, ? , ? , ? , ? , ? , ' ' , ? , ? , ?, ?, 0) ON DUPLICATE KEY UPDATE modified = now()";
	$result = dataQuery($query, $params);



echo "OK";


?>
