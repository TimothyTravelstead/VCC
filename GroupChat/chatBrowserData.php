<?php
session_start();
include('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');

require 'server/vendor/autoload.php';

$browser = new WhichBrowser\Parser(getallheaders());


$_SESSION['callerBrowser'] = $browser->browser->name ?? 'Unknown';
$_SESSION['callerBrowserVersion'] = $browser->browser->version ? $browser->browser->version->toString() : 'Unknown';
$_SESSION['callerBrowserVersionMajor'] = $browser->browser->version ?? 'Unknown';
$_SESSION['callerComputerType'] = ($browser->device->manufacturer ?? '') . " " . ($browser->device->model ?? '') . " " . ($browser->os->name ?? '');
$_SESSION['callerOS'] = $browser->os->name ?? 'Unknown';

if($browser->os->version ?? null) {
	$_SESSION['callerOSVersion'] = $browser->os->version->toString();
} else {
	$_SESSION['callerOSVersion'] = "Unknown";
}
$_SESSION['callerBrowserDetail'] = $browser->getType();
$_SESSION['chatRoomID'] = $_REQUEST['ChatRoomID'];

$browser = array();

$browser['browser'] = $_SESSION['callerBrowser'];
$browser['version'] = $_SESSION['callerBrowserVersion'];
$browser['platform_description'] =  $_SESSION['callerOS'];
$browser['platform_version'] =  $_SESSION['callerOSVersion'];

if($_SESSION['callerOS'] == "iOS" || $_SESSION['callerOS'] == "Android") {
	$_SESSION['mobile'] = true;
}

$userID = $_SESSION["UserID"] ?? $_REQUEST['userID'] ?? 'test_user';
$chatRoomID = $_REQUEST['ChatRoomID'] ?? 1;

$_SESSION["UserID"] = $userID;

// Release session lock after all session reads/writes complete
session_write_close();

$preParams = [$userID, $chatRoomID];			
$query = "SELECT count(userID) from groupChatStatus where userID = ? AND chatRoomID = ?";
$result = groupChatDataQuery($query, $preParams);
$exists = false;

foreach($result as $item) {
	foreach($item as $key=>$value) {
		if($value > 0) {
			$exists = true;
		}
	}
}					

if(!$exists) {					
	$params = [$userID , 
			$browser['browser'] , 
			$browser['version'] , 
			$browser['platform_description'] , 
			$browser['platform_version'] , 
			$chatRoomID];
	$query = "INSERT INTO groupChatStatus VALUES (null, now(), ? , ? , ? , ? , ? , ' ' , null , null, ? , null, 0)";
	$result = groupChatDataQuery($query, $params);
}


echo "OK";


?>


