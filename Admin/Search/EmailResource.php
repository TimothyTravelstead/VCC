<?php

    if(!isset($_SESSION)) 
    { 
        session_start(); 
    } 

	$idnum = $_REQUEST["idnum"];


	include ('formatemail.php');
	list ($to, $subject, $messageText) = formatMessage($idnum);

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<link type="text/css" rel="stylesheet" href="nicEditPanel.css">
		<script src="nicEdit/nicEdit.js" type="text/javascript"></script>
		<script type="text/javascript">
			bkLib.onDomLoaded(function() {
				new nicEditor(
					{buttonList : 
						['bold','italic','underline','left',
						'center','right','justify','ol','ul',
						'fontSize','fontFamily','indent','outdent',
						'forecolor'] , externalCSS : 'nicEditPanel.css'}).panelInstance('MessageText');
			});
		</script>
		<title>Email a Resource</title>
		<style>
			body {
				width:				600px;
				height:				1080px;
				background-color:	maroon;
				background: -moz-linear-gradient(top,  #550000,  #770000); /* for firefox 3.6+ */
			    background: -webkit-gradient(linear, left top, left bottom, from(#550000), to(#770000)); /* Safari */
				color:				silver;
    			font-family: Calibri, Candara, Segoe, "Segoe UI", Optima, Arial, sans-serif;
			}
			
			h2 {
				text-align:			center;
				font-size:			200%;
				color:				silver;
			}
			
			#MessageText {
			    border-style:       solid;
			    border-width:       3px;
			    border-color:       black gray gray black;
			    padding:	       	5px;
				margin-left:		5px;
				width:				550px;
				height:				700px;
				overflow:			scroll;
				-webkit-user-select:none;
				-khtml-user-select: none;
				-moz-user-select: 	none;
				-o-user-select: 	none;
				user-select: 		none;
				background-color:	white;
				color:				black;
			}
				
			#ButtonArea {
				position:			relative;
				width:				570px;
				color:				white;
				text-align:			right;
				margin-top:			10px;
			}
			
			#SendButton {
				background-color:	#00FFFF;
				color:				black;
				width:				100px;
				font-weight:		bold;
			}
			
			#CancelButton {
				background-color:	white;
				color:				black;
			}
			
			textarea {
				height:			30px;
				width:			300px;
				margin-left:	50px;
			}
		</style>
		<script type="text/javascript">
		
<?php
echo "var idnum=".$idnum.";";
?>
		
			window.onload = function() {
				var sendButton = document.getElementById("SendButton");
				sendButton.onclick = function() {
					var message = document.getElementById("MessageText");
					var messageContent = escape(message.innerHTML);
					sendEmail(messageContent);
				};
				
				var cancelButton = document.getElementById("CancelButton");
				cancelButton.onclick = function() {window.close();}
				
			}
			
			
			function sendEmail(messageContent) {
				var message = document.getElementById("MessageText");
				message.blur();

				var toAddressesElement = document.getElementById("toAddresses");
				var toAddresses = toAddressesElement.value;
								
			   	sendMailRequest = createRequest();

			    if (sendMailRequest == null) {
			        alert("Unable to create request");
			        return;
			    }
			        
			    var url= "sendemailNew.php";
<?php			    
				echo "var fields = '&idnum=".$idnum."&To=' + toAddresses + '&Subject=".$subject."&Message=' + messageContent;";
?> 
			
			    sendMailRequest.open("POST", url, true);
			    sendMailRequest.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
			    sendMailRequest.onreadystatechange = confirmEmail;
			    sendMailRequest.send(fields);				
			}
			
			
			function sendErrorEmail(messageContent) {
				var message = document.getElementById("MessageText");
				message.blur();
				var toAddressesElement = document.getElementById("toAddresses");
				var toAddresses = toAddressesElement.value;

			   	sendMailRequest = createRequest();

			    if (sendMailRequest == null) {
			        alert("Unable to create request");
			        return;
			    }
			        
			    var url= "sendemail.php";
				var fields = "&idnum=" + idnum + "&To=tim@lgbthotline.org&Subject=VCC Resource Email Error&Message=" + messageContent;
			
			    sendMailRequest.open("POST", url, true);
			    sendMailRequest.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
			    sendMailRequest.onreadystatechange = "";
			    sendMailRequest.send(fields);				
			}
			
			
			
			function confirmEmail() {
				if (sendMailRequest.readyState == 4) {
	
		   			if (sendMailRequest.status == 200) {
						var responseMessage = sendMailRequest.responseText;
						
						if(responseMessage == "OK") {
							alert("Email Sent!");
						} else {
//							alert("Email Sent!");
//							sendErrorEmail(responseMessage);
							alert("The system could not send the email at this time. \n " + responseMessage);
						}
						window.close();
					}
				}
			}
	

			
			
			function createRequest() {
			    try {
			        request = new XMLHttpRequest();
			        } catch (tryMS) {
			            try {
			                request = new ActiveXObject("Msxml2.XMLHTTP"); 
			                } catch (otherMS) {
			                    try {
			                        request = new ActiveXObject("Microsoft.XMLHTTP"); 
			                    } catch (failed) {
			                request = null;
			            }
			        }    
			    }
			  return request;
			}


		</script>
	</head>
	<body>
		<h2>VCC Resource Email Form</h2>
		<div id="toAddressesDiv">
<?php
			echo "<b>To: </b><br /><textarea columns='80' rows='5' name='toAddresses' id='toAddresses'>".$to."</textarea>";
?>
		</div><hr><hr>
	<div id="MessageText">
<?php
	echo $messageText;
?>
	</div>
	<div id="ButtonArea">
		<input id="CancelButton" type="button" value="Cancel" />
		<input id="SendButton" type="button" value="Send" />
	</div>
	</body>
</html>

