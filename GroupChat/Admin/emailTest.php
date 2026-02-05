<?php
session_start();


require_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');	



$userID = $_REQUEST["userID"];
$chatRoomID = $_REQUEST["chatRoomID"];

if(!$userID) {
	$userID = $_SESSION["UserID"];
}

if(!$chatRoomID) {
	$chatRoomID = $_SESSION['chatRoomID'];
}

// Release session lock after reading session data
session_write_close();


function formatSingleMessage($message) {
	$deleted = "";
	$highlighted = "";
	$title = "";

	if($message['deleteMessage']) {
		$deleted = " deleted";
		$title = "Deleted at ".$message['Modified'];
	} 
	if($message['highlightMessage']) {
		$highlighted = "highlighted";
	} 
	
	if($message['name'] == "System") {
		$class = "systemMessage";
	} else {
		$class = "groupChatWindowMessage".$deleted;
	}
	
	$formattedMessage = "<div class='groupChatMessageContainer ".$highlighted."'><div class='nameBlock'>"
						.$message['name']
						."<span class='timeBlock'>"
						.$message['time']
						."</span></div><div class='"
						.$class
						."' title='"
						.$title
						."'>"
						.$message['text']
						."</div></div><br>";
	return $formattedMessage."\n";
}


$chatDate = "";
$chatStartTime = "";
$chatEndTime = "";
$chatRoomName = "";


$params = [$chatRoomID, $chatRoomID];
$query="SELECT Status as status, Name as name, (SELECT Name from groupChatRooms 
		WHERE chatRoomID = ? LIMIT 1) as chatRoomName , Text as text, MessageNumber, 
		userID, callerDelivered, volunteerDelivered, ChatTime as time, Modified, 
		highlightMessage, deleteMessage FROM groupChat 
		WHERE chatRoomID = ? ORDER By MessageNumber asc"; 


$result = dataQuery($query, $params);
$num_rows = sizeof($result);


$style = "<style>
	.groupChatMessageContainer {
		display:		block;
		width:			500px;
		height:			auto;
		vertical-align:	middle;
		border:			none;
		min-height:		64px;
		overflow:		hidden;
	}

	.nameBlock {
		display:  		block;
		width: 			auto;
		text-align: 	left;
		vertical-align: middle;
		height:			auto;
		overflow:		hidden;
		margin-left:	52px;
		font-weight:	bold;
		font-style:		italic;
		margin-bottom:	5px;
	}

	.avatarBlock {
		display:  	block;
		width: 		48px;
		text-align: center;
		vertical-align: middle;
		height:		64px;
		float:		left;
	}

	.avatarImage {
		height:	auto;
		width:	auto;
		text-align: center;
		opacity: 1;
		margin:	auto;
	}

	.deleted {
	    text-decoration: line-through;
	}
	
	.highlighted {
	    background-color: yellow;
	}
	
	.groupChatWindowMessage {
		display:  			block;
		width: 				400px;
		vertical-align: 	middle;
		background-color:	rgba(200,200,250,.5);
		padding:		  	5px;
		border-radius:		12px;
		margin-bottom:		6px;
		height:				auto;
		margin-left:		52px;	
	}
	
	.systemMessage {
		display:  			block;
		width: 				auto;
		vertical-align: 	middle;
		background-color:	transparent;
		padding:		  	5px;
		margin-bottom:		6px;
		height:				auto;
		text-align:			left;
		margin-left:		52px;	

	}
	
	.timeBlock {
		display:			inline-block;
		font-size:			75%;
		font-style:			italic;
		float:				right;
		font-weight:		normal;
		margin-right:		50px;
	}

</style>";
	
	
$chat = "";  //Variable to hold the chat messages, formatted
$moderators = ""; //Variable to hold the name of moderators



if($num_rows > 0) {
	foreach($result as $item) {
		$singleMessage = Array();

		foreach($item as $key=>$value) {

			switch($key) {
		
				case 'MessageNumber':
					$singleMessage['id'] = 			$value;
					break;

				case 'userID':
					$singleMessage['userID'] = $value;	
					break;
					
				case 'Modified':				
					$singleMessage[$key] = date( 'g:i A', strtotime($value));
					break;
		
				case 'chatRoomName':				
					if(!$chatRoomName) {
						$chatRoomName = $value;
					}
					break;

				case 'time':				
					if(!$chatStartTime) {
						$chatDate = date( 'm/d/y', strtotime($value));
						$chatStartTime = date( 'g:i A', strtotime($value));
					}
					$chatEndTime = date( 'g:i A', strtotime($value));
					$singleMessage[$key] = date('g:i A', strtotime($value));
					break;
				default:
					$singleMessage[$key] = $value;
					break;		
			}				
		}				
		$formattedMessage = formatSingleMessage($singleMessage);			
				
		$chat = $chat." ".$formattedMessage;
	}
}

$params = [$chatRoomID];
$query="SELECT name from callers WHERE chatRoomID = ? and moderator = 1 ORDER By name asc"; 

$result = dataQuery($query, $params);
$num_rows = sizeof($result);
if($num_rows > 0) {
	foreach($result as $item) {
		foreach($item as $key=>$value) {
			if(!$moderators) {
				$moderators = $value;
			} else {
				$moderators + $value + ", ";
			}
		}
	}
}

$header = "<html><head><meta charset='utf-8' />".$style."</head>";
$bodyStart = "<body><h1>Chat Transcript</h1><div><strong>Chat Room: </strong>"
				.$chatRoomName
				."<br><strong>Date: </strong>"
				.$chatDate
				." from "
				.$chatStartTime
				." to "
				.$chatEndTime."</div>";
$bodyContinue = "<div><strong>Moderators: </strong>".$moderators."</div><br>";

$bodyEnd = "</body></html>";



$emailBody = $header." ".$bodyStart." ".$bodyContinue." ".$chat." ".$bodyEnd;




echo $emailBody;
die();



$to = "timtravelstead@ntattorneys.com";
$subject = "LGBTNHC Group Chat Transcript for ".$chatRoomName." on ".$chatDate;
$message = $emailBody;
$envelope_from = "tatiana@lgbthotline.org";



// To send HTML mail, the Content-type header must be set
$headers  = 'MIME-Version: 1.0' . "\r\n";
$headers .= 'Content-type: text/html; charset=utf8mb4' . "\r\n";

// Additional headers
$headers .= 'From: LGBT National Help Center <tatiana@lgbthotline.org>'. "\r\n";

$additional_parameters = "-f ".$envelope_from;

// Mail it
ini_set(sendmail_from,'tatiana@lgbthotline.org');  //used to force system to use the from address
mail($to, $subject, $message, $headers,$additional_parameters ); 

?>




?>
