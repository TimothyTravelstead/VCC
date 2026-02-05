<?php
session_start();

// Release session lock immediately (this script doesn't use session data)
session_write_close();

header('Access-Control-Allow-Origin: https://www.volunteerlogin.org');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once '/home/1203785.cloudwaysapps.com/dgqtkqjasj/public_html/vendor/autoload.php';
include_once('/home/1203785.cloudwaysapps.com/dgqtkqjasj/private_html/db_login_GroupChat.php');
require_once('includeFormatGroupChatEmailFunction.php');

//Import the PHPMailer class into the global namespace
use PHPMailer\PHPMailer\PHPMailer; //important, on php files with more php stuff move it to the top
use PHPMailer\PHPMailer\SMTP; //important, on php files with more php stuff move it to the top

//SMTP needs accurate times, and the PHP time zone MUST be set
//This should be done in your php.ini, but this is how to do it if you don't have access to that
date_default_timezone_set('Etc/UTC');


$chatRoomID = $_REQUEST["chatRoomID"];



// formatSingleMessage function is now included from includeFormatGroupChatEmailFunction.php


function sendEmail($to, $subject, $message) {


	$email_subject = $subject;

	//Enable SMTP debugging
	// SMTP::DEBUG_OFF = off (for production use)
	// SMTP::DEBUG_CLIENT = client messages
	// SMTP::DEBUG_SERVER = client and server messages
	//$mail->SMTPDebug = SMTP::DEBUG_off;

	//SMTP
	$mail = new PHPMailer(true); //important
	$mail->CharSet = 'UTF-8';  //not important
	$mail->isSMTP(); //important
	$mail->Host = 'smtp.dreamhost.com'; //important
	$mail->Port       = 587; //important
	$mail->SMTPSecure = 'tls'; //important
	$mail->SMTPAuth   = true; //important, your IP get banned if not using this

	//Auth
	$mail->Username = 'update@lgbthotlineresources.org';
	$mail->Password = 'LGBTNHC181!';//Steps mentioned in last are to create App password
	
	//Set who the message is to be sent from, you need permission to that email as 'send as'
	$mail->SetFrom('update@lgbthotlineresources.org', 'LGBT National Help Center'); //you need "send to" permission on that account, if dont use yourname@mail.org
	
	//Set an alternative reply-to address
	$mail->addReplyTo('update@lgbthotlineresources.org', 'Tanya');

	//Set who the message is to be sent to
	$mail->addAddress('aaron@lgbthotline.org');
	$mail->addAddress('matt@lgbthotline.org');
	$mail->addAddress('travelstead@mac.com');	//Set the subject line
	$mail->Subject = $email_subject;
	//Read an HTML message body from an external file, convert referenced images to embedded,
	//convert HTML into a basic plain-text alternative body
	$mail->msgHTML($message);  //you can also use $mail->Body = "</p>This is a <b>body</b> message in html</p>" 
	//Replace the plain text body with one created manually
	//$mail->AltBody = 'This is a plain-text message body';
	//Attach an image file
	//$mail->addAttachment('../../../images/phpmailer_mini.png');

	//send the message, check for errors
	if (!$mail->send()) {
		echo 'Mailer Error: ' . $mail->ErrorInfo;
	} else {
	 	return "OK";
	}
}


$chatDate = "";
$chatStartTime = "";
$chatEndTime = "";
$chatRoomName = "";


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


$params = [$chatRoomID, $chatRoomID];
$query="SELECT Status as status, Name as name, (SELECT Name from groupChatRooms
		WHERE chatRoomID = ? LIMIT 1) as chatRoomName , Text as text, MessageNumber,
		userID, callerDelivered, volunteerDelivered, ChatTime as time, Modified,
		highlightMessage, deleteMessage FROM groupChat
		WHERE chatRoomID = ? ORDER By MessageNumber asc";



$result = groupChatDataQuery($query, $params);

if(!$result) {
	echo "No Result";
	$to = "travelstead@mac.com";
	$subject = "LGBTNHC Group Chat Transcript for ".$chatRoomName." on ".$chatDate." is Empty!";
	$message = "<HTML><head></head><body><h1>NO TRANSCRIPT TO SEND!!!</h1></body></html";
	$sentIt = sendEmail($to, $subject, $message);

	return $sentIt." - Empty Transcript";
} else {
	$num_rows = sizeof($result);
}



$chat = "";  //Variable to hold the chat messages, formatted
$moderators = ""; //Variable to hold the name of moderators



if($num_rows && $num_rows > 0) {
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

$result = groupChatDataQuery($query, $params);

if($result) {
	foreach($result as $item) {
		foreach($item as $key=>$value) {
			if(!$moderators) {
				$moderators = $value;
			} else {
				$moderators = $moderators.", ".$value;
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



$to = "aaron@lgbthotline.org, matt@lgbthotline.org, travelstead@mac.com";
$subject = "LGBTNHC Group Chat Transcript for ".$chatRoomName." on ".$chatDate;
$message = $emailBody;


$sentIt = sendEmail($to, $subject, $message);

return $sentIt;
?>
