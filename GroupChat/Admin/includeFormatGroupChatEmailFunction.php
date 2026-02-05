<?php

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
		$class = "groupChatWindowMessage".$deleted." ".$highlighted;
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



?>
