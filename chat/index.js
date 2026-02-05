window.onload = function() {
    initPage();

    document.getElementById("MessageItself").class = "Not";
	setInterval(function(){typing()},1000);
    
}

window.onunload = function() {
	improperEnd();
}

    
firstMessage = false;
waitCount = 0;
chatMessages = [];



function initPage() {
 	myInterval = setInterval(checkDetails,1000);
  	var sendButton = document.getElementById("SendButton");
    sendButton.onclick=PostMessage;
    
  	var endChatButton = document.getElementById("endChatButton");
    endChatButton.onclick = function() {endChat(3,"YOU HAVE ENDED THIS CHAT.  THANK YOU FOR USING THE LGBT NATIONAL HELP CENTER PEER-CHAT SERVICE.");} 
    
    document.getElementById("LastMessage").value = 0;
    
    messageInit();
    
}




function messageInit() {
    var sendChat = document.getElementById("MessageItself");
    sendChat.onkeypress = onEnter; 
}






function onEnter( e ) {
	if (!e) var e = window.event
	if (e.keyCode) code = e.keyCode;
	else if (e.which) code = e.which;
    if(code == 13) {
        document.getElementById("SendButton").click();
        document.getElementById("MessageItself").value = "";
    }
}




function typing() {
    var sendChat = document.getElementById("MessageItself");
    var value = sendChat.value;
    var length = value.length;
    var priorTyping = sendChat.class;
	var CallerID = document.getElementById("CallerID").value;

	if (length > 1) {
		sendChat.class = "Typing";
		if(priorTyping != "Typing") {
			var	params = "postType=typingStatusUpdate&action=" + encodeURIComponent(CallerID) + "&text=Caller-typing";
		}
	} else {
		sendChat.class = "Not";
		if(priorTyping != "Not") {
			var	params = "postType=typingStatusUpdate&action=" + encodeURIComponent(CallerID) + "&text=Caller-not";
		}
	}
    var url = "../volunteerPosts.php";
	if (params) {
		typingRequest = createRequest();
		typingRequest.open("POST", url, true);
		typingRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
		typingRequest.onreadystatechange = typingStatusResponse;
		typingRequest.send(params);  
	}
}


function typingStatusResponse() {
    if (typingRequest.readyState == 4) {
        if (typingRequest.status == 200) {
			if (typingRequest.responseText) {
				alert(typingRequest.responseText);
			}
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








function PostMessage() {

  request = createRequest();

  if (request == null) {
    if (typeof showComprehensiveError === 'function') {
        showComprehensiveError(
            'Chat Request Error',
            'Unable to create request',
            {
                additionalInfo: 'Failed to create XMLHttpRequest for chat functionality. Your browser may not support XMLHttpRequest. Please try a modern browser (Chrome, Firefox, Safari, Edge).'
            }
        );
    } else {
        alert("Unable to create request");
    }
    return;
  }

  var messageElement= document.getElementById("Message");
  var text = document.getElementById("MessageItself").value.replace(/[\n\r]+/g, '');	
  var message = encodeURIComponent(text);
 
  var chat= document.getElementById("Chat").value;
  var CallerID = document.getElementById("CallerID").value;
  var status = 2;
  
   messageElement.innerHTML = "<p id=\"MessageText\"><textarea id=\"MessageItself\" rows=\"15\" cols=\"54\"></textarea></p>";
   messageInit();
    
  var url= "CallerPostMessage.php?Message=" + encodeURIComponent(message) + "&CallerID=" + encodeURIComponent(CallerID) + "&Status=" + encodeURIComponent(status);
  
  request.open("POST", url, true);
  request.onreadystatechange = processChatMessage;
  request.send(null);
  
  document.getElementById("MessageItself").focus();
}
  
    
    
    
function improperEnd() {
    var request = createRequest();
    var CallerID = document.getElementById("CallerID").value;

    var url= "CallerPostMessage.php?Message=ImproperClose&CallerID=" + encodeURIComponent(CallerID) + "&Status=" + "4";

    request.open("GET", url, false);
    request.send(null);    

}
    

    
    
function pausecomp(millis) {   
}
    
    
    
    
function endChat(Status, Message) {
    clearInterval(myInterval);
    document.getElementById("Send").innerHTML = "";
    document.getElementById("Message").innerHTML = "";
    
    var request = createRequest();

    if (request == null) {
        if (typeof showComprehensiveError === 'function') {
        showComprehensiveError(
            'Chat Request Error',
            'Unable to create request',
            {
                additionalInfo: 'Failed to create XMLHttpRequest for chat functionality. Your browser may not support XMLHttpRequest. Please try a modern browser (Chrome, Firefox, Safari, Edge).'
            }
        );
    } else {
        alert("Unable to create request");
    }
        return;
    }
 
    var CallerID = document.getElementById("CallerID").value;
    
    displayMessages(" ", Message, 0);

    var Chat = document.getElementById("Chat");
    Chat.scrollTop += Chat.scrollHeight;

	if(Status != 5) {
	    var url= "CallerPostMessage.php?Message=&CallerID=" + encodeURIComponent(CallerID) + "&Status=" + encodeURIComponent(Status);

    	request.open("POST", url, true);
    	request.send(null);
    }
	window.onbeforeunload = "";	
}

    
    
    
    
    
  
 function checkDetails() {
 
    var CallerID = document.getElementById("CallerID").value;
    var LastMessage = document.getElementById("LastMessage").value;
 
    request = createRequest();

    if (request == null) {
        if (typeof showComprehensiveError === 'function') {
        showComprehensiveError(
            'Chat Request Error',
            'Unable to create request',
            {
                additionalInfo: 'Failed to create XMLHttpRequest for chat functionality. Your browser may not support XMLHttpRequest. Please try a modern browser (Chrome, Firefox, Safari, Edge).'
            }
        );
    } else {
        alert("Unable to create request");
    }
        return;
    }
   
   var url2 = "callerPullMessages.php?CallerID=" + encodeURIComponent(CallerID) + "&LastMessage=" + LastMessage;

  request.open("POST", url2, true);
  request.onreadystatechange = processChatMessage;
  request.send(null);
}



function htmlDecode(input){
  var e = document.createElement('div');
  e.innerHTML = input;
  return e.childNodes.length === 0 ? "" : e.childNodes[0].nodeValue;
}




function processChatMessage () {
	responseText = request.responseText;

	var allMessages = {};
	if(!responseText) {
		return false;
	}
	allMessages = JSON.parse(responseText);
	allMessages.forEach(function(message) {

		var existingMessage = message.id;
		var existingMessageParagraph = document.getElementById(message.id);
		var chatBody = document.getElementById("Chat");


    var div = document.createElement("div");
    div.classList = 'message-wrapper';
		var p = document.createElement("p");
		message.text = decodeURIComponent(message.text);
		

		if(!existingMessageParagraph) {
			chatMessages[message.id] = message;
			p.setAttribute('id',message.id);
			message.time =		new Date();
			p.title = "Posted: " + message.time.toLocaleTimeString();
			p.appendChild(document.createTextNode(message.text));

			if (message.name == 'Volunteer') {
        div.classList.add('message-wrapper', 'received');
				p.setAttribute("title","Received: " + message.time.toLocaleTimeString());
        p.classList = 'VolunteerMessage';
				chatBody.style.backgroundColor=null;
				p.style.backgroundColor = 'rgba(250,150,250,.35)';
				p.style.color = "black";

			}

			if(message.name == 'Caller') {
        div.classList.add('message-wrapper', 'sent');
        p.classList = 'CallerMessage';
				p.style.backgroundColor = 'rgba(200,200,250,1)';
			}


      div.appendChild(p);
			chatBody.appendChild(div);
			chatBody.scrollTop += chatBody.scrollHeight + 10;
		}
		

	   	messageStatusUpdateRequest = createRequest();

		if (messageStatusUpdateRequest == null) {
			alert("Unable to create request");
			return;
		}
		   
		var url = "../volunteerPosts.php";
		var params = "postType=messageStatusUpdate&action=" + encodeURIComponent(message.id) + "&text=";
		if(message.name == "Caller") {
			params += "Caller-confirmed";
		} else if (message.name == "Volunteer") {
			params += "Caller-delivered";
		}				
		
		messageStatusUpdateRequest.open("POST", url, true);
		messageStatusUpdateRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
		messageStatusUpdateRequest.onreadystatechange = messageStatusUpdateResponse;
		messageStatusUpdateRequest.send(params);  

		if (message.status == "5") {
			endChat(message.status , "");
		}

		// Status 7 = All volunteers busy/rejected - show the reject message and end chat
		if (message.status == "7") {
			endChat(message.status, message.text);
		}

	});
}






function messageStatusUpdateResponse() {
    if (messageStatusUpdateRequest.readyState == 4) {
        if (messageStatusUpdateRequest.status == 200) {
        	
            var responseDoc = messageStatusUpdateRequest.responseText;
            if(responseDoc != "") {
	            alert(responseDoc);
	        }
        }
    }
}




function displayMessages(Name, Text, MessageNumber) { 

    var Chat = document.getElementById("Chat");
    var p = document.createElement("p");
    var span = document.createElement("span");
    if(Name != " ") {
	    span.appendChild(document.createTextNode(Name + ": "));
	}
    span.setAttribute("class","name");
    p.appendChild(span);
                
    var span = document.createElement("span");
    span.appendChild(document.createTextNode(Text));
    span.setAttribute("class","text");
    p.appendChild(span);
                
    Chat.appendChild(p);
                                
    document.getElementById("LastMessage").value = MessageNumber;
    
}

    
    
    
    
    
    
