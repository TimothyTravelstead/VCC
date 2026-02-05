<?php


require_once('../db_login.php');
session_start();
$flag = $_REQUEST['groupChatTransferFlag'] ?? null;

if ($_SESSION['auth'] != 'yes' && !$flag) {
	session_destroy(); 
	header('Location: http://www.LGBTHotline.org/chat');
	die("Please return to http://www.LGBThotline.org to start a new chat.");
} 

if($flag && !$_SESSION['CallerID']) {
	$_SESSION['CallerID'] = $_REQUEST['CallerID'];
	$_SESSION['groupChatTransfer'] = $flag;
}

$groupChatTransferMessage = $_SESSION['groupChatTransferMessage'] ?? null;
$key = $_SESSION['CallerID'] ?? null;
$groupChatTransfer = $_SESSION['groupChatTransfer'] ?? null;

/* Chat Statuses:

	1 = No Such Status
	2 = Normal message
	3 = Caller Ended Chat
	4 = Caller Closed Chat window without ending
	5 = Volunteer Ended Chat
*/

// Include the mysql databsae location and login information



include 'firstChatAvailableLevel.php';
include 'secondChatAvailableLevel.php';


$referringPage = $_SESSION['referringPage'] ?? null;
$ipAddress = json_encode("IP ADDRESS: ".$_SERVER['REMOTE_ADDR']);

//browser detect
$browserSupport = null;

if(!$groupChatTransfer) {
	$browserSupportMessage = null;
	$browser['browser'] = $_SESSION['callerBrowser'];
	$browser['version'] = $_SESSION['callerBrowserVersion'];
	$browser['platform_description'] =  $_SESSION['callerOS'];
	$browser['platform_version'] =  $_SESSION['callerOSVersion'];
	
	if($browser['browser'] == "Internet Explorer" && $browser['version'] < 9) {
		$browserSupport = "Not";
	}

	if($browser['browser'] == "Opera") {
		$browserSupport = "Not";
	}

	if($browser['browser'] == "Chrome" && $browser['version'] < 30) {
		$browserSupport = "Not";
	}

	if($browser['browser'] == "Safari" && $browser['version'] < 5) {
		$browserSupport = "Not";
	}

	if($browser['browser'] == "Firefox" && $browser['version'] < 3.6) {
		$browserSupport = "Not";
	}

	if($browser['browser'] == "Android" && $browser['version'] < 4) {
		$browserSupport = "Not";
	}

	if($browser['browser'] != "Firefox" && $browser['browser'] != "Chrome" && $browser['browser'] != "Safari" && $browser['browser'] != "Internet Explorer" && $browser['browser'] != "Android" && $browser['browser'] != "Edge") {
		$browserSupport = "Not";
	}
	

// Temp Fix for browser detection issues
$browserSupport = null;

	if($browserSupport != null) {
		$browserSupportMessage = "Your Browser, ".$browser['browser']." ".$browser['version']." is not supported because it is too old a version.";
		$browserSupportMessage = $browserSupportMessage."<br><br>To use our chat service, please upgrade your 
			browser using one of the following links: <br /><br /><a href='http://www.google.com/chrome/browser'>
			Chrome</a><br /><a href='http://www.apple.com/softwareupdate'>Safari</a><br /><a href='http://www.mozilla.org/firefox'>Firefox</a><br /><a href='http://windows.microsoft.com/en-us/internet-explorer/download-ie'>Internet Explorer</a>";
	}

	if ($browser['browser'] == "Android" && $browserSupport != null) {
		$browserSupportMessage = "Your Browser, ".$browser['browser']." ".$browser['version']." is not supported because it is too old a version.";
		$browserSupportMessage = $browserSupportMessage."<br><br>If possible, please upgrade Android to version 4.0 or higher.  <br />Otherwise, please sign on to our chat service 
			using a desktop, laptop, or Apple mobile device.";
	}

	global $findLoggedon;
	global $findEligible;
	global $findChatting;
	global $findAvailableChatting;
	global $status;
	global $open;
	global $potential;

	$findLoggedon = " WHERE ((LoggedOn = 1) OR 
					(LoggedOn = 4 and Muted = 0) OR 
					(LoggedOn = 6 and Muted = 0))";
	$findEligible = $findLoggedon." AND Ringing is null AND OnCall = 0 AND (((Active1 is null OR Active2 is null) and OneChatOnly is null) or (Active1 is null AND Active2 is null and OneChatOnly = '1')) AND ChatInvite is null AND DESK != 2"; // Desk is used for CallerType and 2 = Calls Only
	$findChatting = $findLoggedon." AND OnCall = 0 AND ((Active1 is not null) OR (Active2 is not null) OR ChatInvite is not null)";
	$findAvailableChatting = $findLoggedon." AND OnCall = 0 AND ((Active1 is not null AND Active2 is null AND OneChatOnly is null) OR (Active2 is not null AND Active1 is null AND OneChatOnly is null)) AND ChatInvite is null";

	$findChatOnly = $findEligible." AND ChatOnly = 1";

	//Determine if Hotline is Open
		$query = "SELECT DayofWeek FROM Hours WHERE (DayofWeek = DATE_FORMAT(curdate(),'%w') + 1) AND (start < curtime() AND end > curtime())";
		$result = mysqli_query($connection,$query);
		while ($result_row = mysqli_fetch_row(($result))) {
			$open =		$result_row[0];
		}

	//Chat Invite Counts Routine
		$query2= "SELECT count(username) FROM volunteers".$findLoggedon;
		$result2 = mysqli_query($connection,$query2);
		while ($result_row = mysqli_fetch_row(($result2))) {
			$loggedon =		$result_row[0];
		}
		
		if ($loggedon < $chatAvailableLevel1) {
			$potential = 0;
		} elseif ($loggedon < $chatAvailableLevel2) {
			$potential = 1; 
		} else {
			$potential = 2;
		}
	
		$query3 = "SELECT count(username) FROM volunteers".$findEligible;
		$result3 = mysqli_query($connection,$query3);
		while ($result_row = mysqli_fetch_row(($result3))) {
			$eligible =		$result_row[0];
		}
	
		$query4 = "SELECT count(username) FROM volunteers".$findChatting;
		$result4 = mysqli_query($connection,$query4);
		while ($result_row = mysqli_fetch_row(($result4))) {
			$chatting =		$result_row[0];
		}
	
	
		$query5 = "SELECT count(username) FROM volunteers".$findAvailableChatting;
		$result5 = mysqli_query($connection,$query5);
		while ($result_row = mysqli_fetch_row(($result5))) {
			$availableChatting =		$result_row[0];
		}
	
		$query6 = "SELECT count(username) FROM volunteers".$findChatOnly;
		$result6 = mysqli_query($connection,$query6);
		while ($result_row = mysqli_fetch_row(($result6))) {
			$chatOnly =		$result_row[0];
		}

		if ($browserSupport != null) {
			$browserStatus = "unsupported";	
		} else {
			$browserStatus = "Supported";
	
	
			if (!$open) {
				$status = "closed";	
			} else if ($chatOnly > 0) {
				//Invite All Eligible Chat Only Volunteers
					$status = "allChatOnly";
					$query7A = "UPDATE Volunteers SET Volunteers.InstantMessage = '".$groupChatTransferMessage."' , Volunteers.ChatInvite = '".$key."'".$findChatOnly;
					$result7A = mysqli_query($connection,$query7A);	
			} else if ($loggedon < $chatAvailableLevel1) {
				$status = "busy";
			} else if ($chatting < $potential) {
				if ($eligible > 0) {
					//Invite All Eligible
						$status = "allEligible";
						$query6 = "UPDATE Volunteers SET Volunteers.InstantMessage = '".$groupChatTransferMessage."' , Volunteers.ChatInvite = '".$key."'".$findEligible;
						$result6 = mysqli_query($connection,$query6);	
				} else {	
					$status = "busy";
				}	
			} else if ($chatting == $potential) {
				if ($availableChatting > 0) {
					//Invite All Available Chatting
						$status = "allAvailableChatting";
						$query7 = "UPDATE Volunteers SET Volunteers.InstantMessage = '".$groupChatTransferMessage."' , Volunteers.ChatInvite = '".$key."'".$findAvailableChatting;
						$result7 = mysqli_query($connection,$query7);	
				} else {
					$status = "busy";
				}
			} else {
				$status = "busy";
			}
		}
	if(!isset($browser['browser_name_pattern'])) {
		$browser['browser_name_pattern']= 'none';
	}
		
	$query = "INSERT INTO chatStatus VALUES (null, now(),'".$key."' , null, null, '".$browser['browser']."', '".$browser['version']."', '".$browser['platform_description']."', '".$browser['platform_version']."','".$browser['browser_name_pattern']."' , '".$status."' , '".$browserStatus."' , null , null)";
	$result = mysqli_query($connection,$query);

} else {
	$status = 'connected';
}	
			

if ($browserStatus == "unsupported" && $status != 'connected') {
	$status = "unsupported";
}

mysqli_close($connection);



	if ($status == "busy") {
			echo "<!DOCTYPE html>";
			echo "<html lang='en'>";
			echo "<head>";
			echo "    <meta charset='UTF-8'>";
			echo "    <meta name='viewport' content='width=device-width, initial-scale=1.0'>";
			echo "    <title>Chat Application</title>";
			echo "    <link rel='stylesheet' href='style2.css'>";
			echo "</head>";
			echo "<body>";
				echo "<h1>Sorry, our volunteers are currently busy helping other people.  Please try later, or you can email a volunteer at: <br><br> <a href='mailto:help@LGBThotline.org?Subject=Peer-counseling or Information Request'>help@LGBThotline.org</a></h1>";
			echo "</body>";
			echo "</html>";
			die();
	} elseif ($status == "closed") {
			echo "<!DOCTYPE html>";
			echo "<html lang='en'>";
			echo "<head>";
			echo "    <meta charset='UTF-8'>";
			echo "    <meta name='viewport' content='width=device-width, initial-scale=1.0'>";
			echo "    <title>Chat Application</title>";
			echo "    <link rel='stylesheet' href='style2.css'>";
			echo "</head>";
			echo "<body>";
				echo "<h1>Chat services are currently unavailable.  Please try back during our open hours.  You can also email a volunteer at: <br><br> <a href='mailto:help@LGBThotline.org?Subject=Peer-counseling or Information Request'>help@LGBThotline.org</a></h1>";
			echo "</body>";
			echo "</html>";
			die();
	} elseif ($status == "unsupported") {
			echo "<!DOCTYPE html>";
			echo "<html lang='en'>";
			echo "<head>";
			echo "    <meta charset='UTF-8'>";
			echo "    <meta name='viewport' content='width=device-width, initial-scale=1.0'>";
			echo "    <title>Chat Application</title>";
			echo "    <link rel='stylesheet' href='style2.css'>";
			echo "</head>";
			echo "<body>";
				echo "<h1>".$browserSupportMessage."</h1>";
			echo "</body>";
			echo "</html>";
			die();
	} 
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Application</title>
    <link rel="stylesheet" href="style2.css">
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <p><img src="lgbt-branding_logo-horiz-small.png" alt="Logo" class="chat-logo"></p>
            <h1>Peer Chat</h1>
        </div>
        <div class="chat-body">
            <!-- Messages will be displayed here -->
        </div>
        <div class="chat-footer">
            <textarea placeholder="Type a message..." autofocus></textarea>
            <button>SEND</button>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const chatBody = document.querySelector('.chat-body');
            const messageInput = document.querySelector('.chat-footer textarea');
            const sendButton = document.querySelector('.chat-footer button');
            const chatFooter = document.querySelector('.chat-footer');

            let username = prompt("Enter your username for the chat:");
            if (!username || username.trim() === "") {
                alert("Username is required to join the chat.");
                return;
            }
            username = username.trim();
            const userId = Math.random().toString(36).substr(2, 9);

            const ws = new WebSocket(`ws://localhost:8080/?room=room1&username=${encodeURIComponent(username)}&userId=${userId}`);

            ws.onopen = () => {
                console.log('Connected to the WebSocket server');
                ws.send(JSON.stringify({ type: 'login', username: username, userId: userId }));
            };

            ws.onmessage = (event) => {
                const data = JSON.parse(event.data);

                if (data.type === 'system' || data.type ==='error') {
                    displayMessage(data.username, data.message, 'system');
                    if (data.message.includes('Our Volunteers are busy helping others.  Please try again later.') || data.type === 'error') {
                        chatFooter.style.display = 'none'; // Hide the send button
                    }
                    return;
                }

                if (data.type === 'delivery-ack') {
                    updateMessageStatus(data.message);
                    return;
                }

                if (data.message && data.message.trim() !== '') {
                    if (data.userId !== userId) {
                        displayMessage(data.username, data.message, 'received');
                    } else {
                        displayMessage(data.username, data.message, 'sent');
                    }
                }
            };

            ws.onerror = (event) => {
                console.error('WebSocket error:', event);
            };

            sendButton.addEventListener('click', sendAndDisplayMessage);
            messageInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendAndDisplayMessage();
                }
            });

            function sendAndDisplayMessage() {
                let message = messageInput.value.trim();
                if (message) {
                    message = sanitizeInput(removeLinks(message));
                    const messageData = JSON.stringify({ type: 'message', username: username, message: message, userId: userId });
                    ws.send(messageData);
                    displayMessage(username, message, 'sent');
                    messageInput.value = '';
                }
            }

            function displayMessage(senderUsername, message, messageType) {
                const messageWrapper = document.createElement('div');
                messageWrapper.classList.add('message-wrapper', messageType);

                if (messageType === 'system') {
                    const systemMessageContent = `${message}`;
                    messageWrapper.classList.add('system-message');
                    messageWrapper.textContent = systemMessageContent;
                    chatBody.appendChild(messageWrapper);
                    chatBody.scrollTop = chatBody.scrollHeight;
                    return;
                }

                const usernameElement = document.createElement('div');
                usernameElement.classList.add('username');
                usernameElement.textContent = senderUsername;

                const messageElement = document.createElement('div');
                if (isOnlyEmojis(message)) {
                    messageElement.classList.add('emoji-message');
                } else {
                    messageElement.classList.add('message', messageType);
                }
                messageElement.innerHTML = `<p>${message}</p>`;

                messageWrapper.appendChild(usernameElement);
                messageWrapper.appendChild(messageElement);

                if (messageType === 'sent') {
                    const messageStatus = document.createElement('div');
                    messageStatus.classList.add('message-status');
                    messageStatus.textContent = 'Sending...';
                    messageWrapper.appendChild(messageStatus);
                }

                chatBody.appendChild(messageWrapper);
                chatBody.scrollTop = chatBody.scrollHeight;
            }

            function isOnlyEmojis(str) {
                const emojiRegex = /^[\u{1F600}-\u{1F64F}\u{1F300}-\u{1F5FF}\u{1F680}-\u{1F6FF}\u{1F700}-\u{1F77F}\u{1F780}-\u{1F7FF}\u{1F800}-\u{1F8FF}\u{1F900}-\u{1F9FF}\u{1FA00}-\u{1FA6F}\u{1FA70}-\u{1FAFF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{2B50}\u{2B55}\u{FE0F}\u{1F90D}-\u{1F971}]+$/u;
                return emojiRegex.test(str);
            }

            function updateMessageStatus(messageStatus) {
                const lastMessage = document.querySelector('.chat-body .message-wrapper.sent:last-child .message-status');
                if (lastMessage) {
                    lastMessage.textContent = messageStatus;
                }
            }

            function removeLinks(message) {
                const urlRegex = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#/%=~_|$?!:,.]*[-A-Z0-9+&@#/%=~_|$])/ig;
                return message.replace(urlRegex, '');
            }

            function sanitizeInput(input) {
                return input.replace(/&/g, "&amp;")
                            .replace(/</g, "&lt;")
                            .replace(/>/g, "&gt;")
                            .replace(/"/g, "&quot;")
                            .replace(/'/g, "&#039;");
            }
        });
    </script>
</body>
</html>




