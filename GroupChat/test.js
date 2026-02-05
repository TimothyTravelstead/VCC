var users = [];
var userNames = [];
var messages = [];
var firstMessage = false;
var waitCount = 0;
var newChat = "";
var currentUser = "";
var dragObject = "";
var isRoomOpen = false;
var myEditor = "";
var checkVolunteerStatus = "";




function objectSize(obj) {
    var size = 0, key;
    for (key in obj) {
        if (obj.hasOwnProperty(key)) size++;
    }
    return size;
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


function improperExit() {
    if(users && users[currentUser] && users[currentUser].status > 0 && !users[currentUser].moderator) {
		improperExitRequest = createRequest();
		  
		if (improperExitRequest == null) {
			alert("Unable to create request");
			return;
		}

		var url= "callerSignOff.php";

		improperExitRequest.open("POST", url, false);
		improperExitRequest.send(null);
		improperExitRequest.onreadystatechange = improperExitResult;
	}
}

function improperExitResult() {
	if (improperExitRequest.readyState == 4 && improperExitRequest.status == 200) {
		endChat();
	}
}



var inactivityTime = function () {
    var time = null;
    window.onload = resetTimer;
    // DOM Events
    document.onmousemove = resetTimer;
    document.onkeydown = resetTimer;
    document.onchange = resetTimer;


    function resetTimer() {
        clearTimeout(time);
        time = setTimeout(endChat, 1800000);
        // 1000 milliseconds = 1 second
    }
};








window.onload = function() {
	inactivityTime();
	preInitialize();
}


function preInitialize() {
	myEditor = new nicEditor(
		{buttonList : 
			['bold','italic','underline','left',
			'center','right','justify',
			'fontSize','fontFamily','indent','outdent',
			'forecolor'] , externalCSS : 'nicEditPanel.css' , iconsPath: '/GroupChat/nicEdit/nicEditorIcons.gif'}).panelInstance('groupChatTypingWindow');
				
	currentUser = document.getElementById("userID").value;
	currentUser = currentUser.trim();
    initPage();
    var avatars = new GetAvatars();
    avatars.init();
}

function getCaretCharacterOffsetWithin(element) {
    var caretOffset = 0;
    var doc = element.ownerDocument || element.document;
    var win = doc.defaultView || doc.parentWindow;
    var sel;
    if (typeof win.getSelection != "undefined") {
        sel = win.getSelection();
        if (sel.rangeCount > 0) {
            var range = win.getSelection().getRangeAt(0);
            var preCaretRange = range.cloneRange();
            preCaretRange.selectNodeContents(element);
            preCaretRange.setEnd(range.endContainer, range.endOffset);
            caretOffset = preCaretRange.toString().length;
        }
    } else if ( (sel = doc.selection) && sel.type != "Control") {
        var textRange = sel.createRange();
        var preCaretTextRange = doc.body.createTextRange();
        preCaretTextRange.moveToElementText(element);
        preCaretTextRange.setEndPoint("EndToEnd", textRange);
        caretOffset = preCaretTextRange.text.length;
    }
    return caretOffset;
}



function getSelectionHtml() {
    var html = "";
    if (typeof window.getSelection != "undefined") {
        var sel = window.getSelection();
        if (sel.rangeCount) {
            var container = document.createElement("div");
            for (var i = 0, len = sel.rangeCount; i < len; ++i) {
                container.appendChild(sel.getRangeAt(i).cloneContents());
            }
            html = container.innerHTML;
        }
    } else if (typeof document.selection != "undefined") {
        if (document.selection.type == "Text") {
            html = document.selection.createRange().htmlText;
        }
    }
    return html;
}





function selectText(node) {
    if (document.selection) { // IE
        var range = document.body.createTextRange();
        range.moveToElementText(node);
        range.select();
    } else if (window.getSelection) {
        var range = document.createRange();
        range.selectNode(node);
        window.getSelection().removeAllRanges();
        window.getSelection().addRange(range);
    }
}

function setEndOfContenteditable(contentEditableElement) {

	stripUnwantedAttributes(contentEditableElement);

    var range,selection;
    if(document.createRange)//Firefox, Chrome, Opera, Safari, IE 9+
    {
        range = document.createRange();//Create a range (a range is a like the selection but invisible)
        range.selectNodeContents(contentEditableElement);//Select the entire contents of the element with the range
        range.collapse(false);//collapse the range to the end point. false means collapse to end rather than the start
        selection = window.getSelection();//get the selection object (allows you to change selection)
        selection.removeAllRanges();//remove any selections already made
        selection.addRange(range);//make the range you have just created the visible selection
    }
    else if(document.selection)//IE 8 and lower
    { 
        range = document.body.createTextRange();//Create a range (a range is a like the selection but invisible)
        range.moveToElementText(contentEditableElement);//Select the entire contents of the element with the range
        range.collapse(false);//collapse the range to the end point. false means collapse to end rather than the start
        range.select();//Select the range (make it the visible selection
    }
}

function initPage() {
	var body = document.getElementsByTagName("body")[0];
	var groupChatModeratorFlag = document.getElementById("groupChatModeratorFlag").value;

	if(groupChatModeratorFlag > 0) {
		document.getElementById("groupChatRoomName").style.display = "none";
	}
	
	
	var userID = document.getElementById("userID").value;

	document.addEventListener("paste", function(e) {
		// cancel paste
		e.preventDefault();

		// get text representation of clipboard
		var text = e.clipboardData.getData("text/plain");

		// insert text manually
		document.execCommand("insertHTML", false, text);
	});



	var width = (window.innerWidth > 0) ? window.innerWidth : screen.width;
	var height = (window.innerHeight > 0) ? window.innerHeight : screen.height;

	if(width < 480) {
		body.style.height = height - 50 + "px";
	}
	
	var callerBrowser = ""; 
	var callerBrowserVersion = "";
	var callerOS = ""; 
	
	var callerOSVersion = "unknown";	

	var callerBrowserDetail = navigator.userAgent;
	var chatRoomID = document.getElementById("groupChatRoomID").value;


	var params = "callerBrowser=" + escape(callerBrowser) + "&callerBrowserVersion=" + escape(callerBrowserVersion) + 
			"&callerOS=" + escape(callerOS) + "&callerOSVersion=" + escape(callerOSVersion) + "&callerBrowserDetail=" + 
			escape(callerBrowserDetail) + "&ChatRoomID=" + escape(chatRoomID) + "&userID=" + escape(userID);
			
			
	var groupChatPortraitMode = document.getElementById("groupChatPortraitMode");
	var groupChatLandscapeMode = document.getElementById("groupChatLandscapeMode");
	
	if(callerOS == "iOS" || callerOS == "Android") {

		// Listen for orientation changes
		window.addEventListener("orientationchange", function() {
			// Announce the new orientation number
			if(window.orientation != 0) {
				groupChatPortraitMode.style.display = 'none';
				groupChatLandscapeMode.style.display = 'block';
			} else {
				groupChatPortraitMode.style.display = 'block';
				groupChatLandscapeMode.style.display = 'none';

			};
		}, false);
		
		document.getElementById("groupChatMobileFlag").value = 1;
	}		
			
	
	var url= "chatBrowserData.php";
	var responseObject = {};
	var browserDataMessageRequest = new AjaxRequest(url, params, browserDataMessageResults , responseObject);  //Fingerprint data added after results of this call

	var signedOnButton = document.getElementById("groupChatSignInFormSubmit");
	signedOnButton.onclick = groupChatLogin;
	
	var nicEdit = document.getElementsByClassName("nicEdit-panelContain");
	for (var i = 0 ; i < nicEdit.length ; i++) {
		var nice = nicEdit[i].parentNode;
		nice.style.width = "94vw";
		nice.style.height = "auto";
	}

  	var sendButton = document.getElementById("groupChatSubmitButton");
	var textArea = document.getElementById("groupChatTypingWindow");
	textArea.focus();
    sendButton.onclick = function() {

		PostMessage();
	};
	 
	 
	textArea.onfocus = function() {

		textArea.onkeypress = function (e) {
			if (!e) {
				var e = window.event;
			}
			if (e.keyCode) {
				code = e.keyCode;
			} else if (e.which) {
				code = e.which;
			}
		
			if(code == 13) {
				PostMessage(null);
				return false;
			}	
			if(code == 10) {
				PostMessage(null);
				return false;
			}
		};
	};
	

  	var endChatButton = document.getElementById("groupChatExitButton");
    endChatButton.onclick = function() {
		endChat();
    } 
    
    var mobileMenu = document.getElementById("menuButton");
    mobileMenu.onclick = function() {
    	changeMembersVisibility();
    };
    
	var groupChatNoNewMembers = document.getElementById("groupChatNoNewMembers");
	if(groupChatNoNewMembers) {
		groupChatNoNewMembers.onclick = function() {
			groupChatBlockNewMembers();
		};
	}

	newChat = new GroupChatMonitor("testing");
	newChat.init();    	   
	roomOpen();
}

function groupChatBlockNewMembers() {
	var groupChatNoNewMembers = document.getElementById("groupChatNoNewMembers");
	var block = groupChatNoNewMembers.checked;
	if(block) {
		var chatRoomID = document.getElementById("groupChatRoomID").value;
		var params = "chatRoomID=" + chatRoomID + "&block=1";
		var responseObject = {};
		var url= "blockNewMembers.php";
		var postMessageRequest = new AjaxRequest(url, params, postMessageResult , responseObject);
	} else {
		var chatRoomID = document.getElementById("groupChatRoomID").value;
		var params = "chatRoomID=" + chatRoomID + "&block=0";
		var responseObject = {};
		var url= "blockNewMembers.php";
		var postMessageRequest = new AjaxRequest(url, params, postMessageResult , responseObject);
	}
}


function endChat() {
	var chatRoomID = document.getElementById("groupChatRoomID").value;
	var params = "chatRoomID=" + chatRoomID + "&userID=" + currentUser;
	var responseObject = {};
	var url= "callerSignOff.php";
	var postMessageRequest = new AjaxRequest(url, params, postMessageResult , responseObject);
	document.getElementById("groupChatSignIn").style.display = null;
	document.getElementById("groupChatTypingWrapper").style.display = null;
	document.getElementById("groupChatControlButtonArea").style.display = null;
	document.onkeypress = null;
	
	for (user in users) {
	  users[user].display();
	}
	
	var moderators = countLoggedOnUsers();
	if(moderators == 0) {
		roomClosedCheck(chatRoomID);
	}
	
	if(users && users[currentUser] && users[currentUser].moderator == 1) {
		completeExit();
	}
}


function completeExit() {
	var completeExitResponseObject = {};
	var url = "Admin/moderatorExitVCC.php";
	var params = "none";
	var preFinalExitRequest = new AjaxRequest(url, params, completeExitResponseFunction , completeExitResponseObject);	
}
	
	
function completeExitResponseFunction(results) {
	if(results.trim() != "OK") {
		//alert("Exiting Results: " + results);
	}

//	window.onbeforeunload = "";

	var finalResponseFunction = function (results) {	
		window.onbeforeunload = "";
		window.location = '../index.php';
	};

	var params = "postType=exitProgram";
	var exitProgramRequest = new AjaxRequest("../volunteerPosts.php", params, finalResponseFunction);	
}












function changeMembersVisibility(status) {
	var chatMembers = document.getElementById("groupChatMemberListContainer");

	if(status) {
		if(status == "hide") {
			chatMembers.setAttribute("class","hideMembers");		
		} else if (status == "show") {
			chatMembers.setAttribute("class", "showMembers");
		}
	} else { 
		var chatMembersShowing = chatMembers.getAttribute("class");
		if(chatMembersShowing!="hideMembers") {
			chatMembers.setAttribute("class","hideMembers");
		} else if(chatMembersShowing=="hideMembers") {
			chatMembers.setAttribute("class", "showMembers");
		}	
	}
}



function PostMessage(message) {
	var chatRoomID = document.getElementById("groupChatRoomID").value;

	if(!message) {
		var message = new Object();
		message.userID = document.getElementById("userID").value;
		message.userID = message.userID.trim()
		message.messageID = "0";  
		var pulledContent = nicEditors.findEditor('groupChatTypingWindow').getContent();
		var messageElement = document.getElementById("groupChatTypingWindow");
		removeUnwantedElements(messageElement);
		message.text = encodeURIComponent(messageElement.innerHTML);
		
		var messageFull = true;		
		
		if(messageElement.firstChild.nodeValue) {
			messageFull = messageElement.firstChild.nodeValue.replace(/\s/g, '').length;
		} else if(messageElement.firstChild.innerText) {
			messageFull = messageElement.firstChild.innerText.replace(/\s/g, '').length;
		} else if(messageElement.nodeValue) {
			messageFull = messageElement.nodeValue.replace(/\s/g, '').length;
		}
		
				
		if(!messageFull) {
			return;
		}
				
		message.highlightMessage = false;
		message.deleteMessage = false;	
	}
	if(message.text.trim()) {
		var updatedString = message.text.replace(/('[a-zA-Z0-9\s]+\s*)'(\s*[a-zA-Z0-9\s]+')/g,"$1\\\'$2");
		var finalMessage = JSON.stringify(message);
		
		var params = "userID=" + message.userID + "&Text=" + updatedString + "&messageID=" + 
						message.messageID + "&highlightMessage=" + message.highlightMessage + 
						"&deleteMessage=" + message.deleteMessage + "&chatRoomID=" + chatRoomID;
		var responseObject = {};

		var url= "CallerPostMessage.php";
  
		var postMessageRequest = new AjaxRequest(url, params, postMessageResult, responseObject);
	}
	
	var styleElements = "";		
	var newElementStart = "";
	var newElementEnd = "";
	var messageElement = document.getElementById("groupChatTypingWindow");

	var iterator = document.createNodeIterator(messageElement.lastChild);

		// get the first matching node
	var node = iterator.nextNode();
	while (node) {
		if(node.nodeName != 'SPAN' && node.nodeName != '#text') {
			if(node.nodeName == 'B' || node.nodeName == "U" || node.nodeName == "I") {
				newElementStart += "<" + node.nodeName  + ">";
			} 
			if(node.nodeName == 'FONT') {
				var size = node.getAttribute("size");
				var face = node.getAttribute('face');
				var color = node.getAttribute('color');
				newElementStart += "<" + node.nodeName + " size='" + size + "' face='" + face + "' color='" + color + "'>";
			}
			newElementEnd += "</" + node.nodeName + ">";
		}	
		node = iterator.nextNode ();
	}
	
	styleElements = newElementStart + "<span contenteditable='true'>&nbsp</span>" + newElementEnd;

	messageElement.innerHTML = null;
	nicEditors.findEditor('groupChatTypingWindow').setContent(styleElements);

	var iterator = document.createNodeIterator(messageElement.lastChild);

		// get the first matching node
	var node = iterator.nextNode();
	while (node) {
		if(node.nodeName == 'SPAN') {
			selectText(node);
			break;
		} else {	
			node = iterator.nextNode ();
		}
	}
//	setEndOfContenteditable(messageElement);
	var groupChatMobileFlag = document.getElementById('groupChatMobileFlag').value;
	if(groupChatMobileFlag) {
		messageElement.blur();
	} else {
		messageElement.focus();
	}
}
 
  
function postMessageResult(results, resultObject) {
	if(results.trim() != "OK") {
		alert(results);
	}
}
    

function browserDataMessageResults(results, resultObject) {
	if(results.trim() == "OK") {
		var userID = document.getElementById("userID").value;
		var ipAddress = document.getElementById("ipAddress").value;
		var address = {"ipAddress" : ipAddress.trim()};		
		recordCallerData(ipAddress, userID);		
	} else {
		alert("An initial page error has occurred.  Please reload your page.");
	}
}


    
    
    
function roomOpen() {
	var groupChatSignIn = document.getElementById("groupChatSignIn");
	var groupChatMainWindow = document.getElementById("groupChatMainWindow");
	var groupChatNumberCount = document.getElementById("groupChatNumberCount");
	var groupChatModeratorFlag = document.getElementById("groupChatModeratorFlag").value;
	var loggedOnModerators = countLoggedOnUsers();					

	if(1 == 2 && loggedOnModerators == 0 && (!groupChatModeratorFlag || groupChatModeratorFlag == 0)) {
		groupChatMainWindow.innerHTML = "";
		groupChatNumberCount.innerText = 0;
		groupChatSignIn.style.display = "none";


		for (var key in users) {
			var user = users[key];
			var loggedOn = user.isLoggedOn();
			if(loggedOn) {
				user.logOffUser();
			}
		}
		if(users && users[currentUser] && users[currentUser].moderator == 1) {
			groupChatSignIn.style.display = null;
		}
		var p = document.createElement("p");
		p.innerHTML = "Room Closed. Please try during open hours, or wait for moderator to sign in.";
		p.setAttribute("id" , "groupChatClosedRoomMessage");
		groupChatMainWindow.appendChild(p);

	} else {
		isRoomOpen = true;
		var groupChatClosedRoomMessage = document.getElementById("groupChatClosedRoomMessage");
		if(groupChatClosedRoomMessage) {
			remove(groupChatClosedRoomMessage);
		}
		if(users && users[currentUser]) {
			var loggedOn = users[currentUser].isLoggedOn();
			var status = users[currentUser].status;
			if(!loggedOn) {
				groupChatSignIn.style.display = null;
			} else if (status == 1) {			
				removeLogonScreen();
			}
		} else {
			groupChatSignIn.style.display = null;
		}
	}	
}
    
function GroupChatMonitor(name) {
	var self = this;
	this.loggedOnModerators = 0;
	this.source = "";	
	this.numberOfUsers = 0;
	this.chatRoomID = document.getElementById("groupChatRoomID").value;
	this.userID = document.getElementById("userID").value;
	
	this.init = function () {
		if(typeof(EventSource)!= "undefined") {
			this.source = new EventSource("chatFeed2.php?chatRoomID=" + self.chatRoomID);
			var source = this.source;
	
			source.addEventListener('chatMessage', function(event) {
				self.processChatMessage(event.data);
			});
				
		} else {
			newChat = null;
			var groupChatMainWindow = document.getElementById("groupChatMainWindow");
			var groupChatSignIn = document.getElementById("groupChatSignIn");
			groupChatSignIn.style.display = "none";
		
			groupChatMainWindow.innerHTML = "<strong>Sorry, your browser is not supported.<br>Please use the latest version of Safari, Chrome, or Firefox.<br><br>You can download them using one of the following links: <br /><br /><a href='http://www.google.com/chrome/browser'>Chrome</a><br /><a href='http://www.apple.com/softwareupdate'>Safari</a><br /><a href='http://www.mozilla.org/firefox'>Firefox</a><br />"; 
			fail;
		}
	};
	
	
	this.processChatMessage = function(message) {
			var data = JSON.parse(message);
			var groupChatNoNewMembers = document.getElementById("groupChatNoNewMembers");
		
			var type = data.type;
		
			switch (type) {
				case "user":
					if (!users[data.userID]) { // If user does not currently exist
						users[data.userID] = new User(data);
						if (users[data.userID].status) {
							users[data.userID].logOnUser();
						}
					} else {
						users[data.userID].updateUser(data);
					}
					break;
				
				case "message":
					if(messages[data.messageNumber]) {
						messages[data.messageNumber].updateMessage(data);
					} else {
						var message = new Message(data);
						messages[message.messageID] = message;		
						message.display();
					}
					break;
				
				case "IM":
					if(users[data.userID]) {
						users[data.userID].receiveIm(data);		
					}
					break;
				
				
				case "roomStatus":
					if(data.roomStatus === 'closed' && isRoomOpen) {
						roomClosed(data.chatRoomID);		
					} else if(data.roomStatus === 'open' && !isRoomOpen) {
						if(groupChatNoNewMembers) {
							groupChatNoNewMembers.checked = null;
						}
						roomOpen();		
					} else if(data.roomStatus === 'full') {
						if(groupChatNoNewMembers) {
							groupChatNoNewMembers.checked = true;
						}
					} else if(data.roomStatus === 'reopen') {
						if(groupChatNoNewMembers) {
							groupChatNoNewMembers.checked = null;
						}
					}
					break;
				case 'toOneOnOneChat':
					users[data.userID].privateChatTransferAcceptedCheck();
					break;

			}
		};
}	
	

function confirmedCloseCheck(chatRoomID) {
	var moderators = countLoggedOnUsers();
	if(moderators > 0) {
		return;
	}
	roomClosed(chatRoomID);

}



function roomClosedCheck(chatRoomID) {
  	myVar = setTimeout(function(){ confirmedCloseCheck(chatRoomID); }, 1000);

}



function roomClosed(chatRoomID) {
	isRoomOpen = false;
	if(messages.length > 0) {
		for (var key in messages) {
			var message = messages[key];
			message.deleteMessage = 1;
			message.updateMessage(message);
		}
	}


	var groupChatMembersListWindow = document.getElementById("groupChatMembersListWindow");
	groupChatMembersListWindow.innerHTML = "";

	var groupChatNumberCount = document.getElementById("groupChatNumberCount");
	groupChatNumberCount.innerHTML = 0;

	var groupChatTypingWrapper = document.getElementById("groupChatTypingWrapper");
	groupChatTypingWrapper.style.display = "none";
	
	if(users.length > 0) {
		for (var key in users) {
			var user = users[key];
			user.logOffUser();
		}
	}

	var groupChatModeratorFlag = document.getElementById("groupChatModeratorFlag").value;
	if(groupChatModeratorFlag === "0") {
		var groupChatSignIn = document.getElementById("groupChatSignIn");
		groupChatSignIn.style.display = "none";

		var groupChatMainWindow = document.getElementById("groupChatMainWindow");
		var p = document.createElement("p");
		p.innerHTML = "Room Closed. Please try during open hours, or wait for moderator to sign in.";
		p.setAttribute("id" , "groupChatClosedRoomMessage");
		groupChatMainWindow.appendChild(p);

		var originalPage = document.getElementById("groupChatPageReferrer").value;
		if(originalPage) {
			window.location.href = originalPage;
		} else {
			window.location.href = "https://www.glbthotline.org/youthchatrooms.html";
		}

		
	} else {
		var groupChatMonitor1RoomSelector = window.parent.document.getElementById('groupChatMonitor1RoomSelector'); 
		var groupChatMonitor2RoomSelector = window.parent.document.getElementById('groupChatMonitor2RoomSelector'); 

		if(groupChatMonitor1RoomSelector.selectedIndex == chatRoomID) {
			groupChatMonitor1RoomSelector.selectedIndex = 0;
			var groupChatAdminFrame1 = window.parent.document.getElementById('groupChatAdminFrame1');
			groupChatAdminFrame1.src = ""; 
		} 
		
		if(groupChatMonitor2RoomSelector.selectedIndex == chatRoomID) {
			groupChatMonitor2RoomSelector.selectedIndex = 0;
			var groupChatAdminFrame2 = window.parent.document.getElementById('groupChatAdminFrame2');
			groupChatAdminFrame2.src = ""; 
		}
	}
}






function stripUnwantedAttributes(node) {
	if(node.src) {
		node.removeAttribute("src");
	}
				
	if(node.href) {
		node.removeAttribute("href");
	}

	if(node.target) {
		node.removeAttribute("target");
	}

	if(node.bottom) {
		node.removeAttribute(bottom);
	}

	if(node.backgroundImage) {
		node.removeAttribute(background-image);
	}

	if(node.tagName) {
		switch (node.tagName) {
			case "IMG":
			case "VIDEO":
			case "AUDIO":
			case "TEXTAREA":
			case "INPUT":
			case "BR":
				remove(node);
				break;
			case "STYLE":
			case "SCRIPT":
				remove(node);
				break;
			default:
				node.style.background = "";
				node.setAttribute("background-image","");
				break;
		}
	}	
}

	
function removeUnwantedElements(messageNodeList) {

	var iterator = document.createNodeIterator (messageNodeList);

		// get the first matching node
	var node = iterator.nextNode();

	while (node) {
		stripUnwantedAttributes(node);
		node = iterator.nextNode ();
	}
}	
	
function Message(message) {
	var self = this;
	this.name = decodeURIComponent(message.name);
	this.userID = message.userID;
	this.messageID = message.id;
	this.text = message.message;
	this.time = message.created;
	this.messageBlock = false;
	this.messageClass = false;
	this.deleteMessage = message.deleteMessage;
	this.highlightMessage = message.highlightMessage;
	this.window = document.getElementById("groupChatMainWindow");
	this.displayed = false;
	
	
	this.postUpdateToMessage = function() {
		PostMessage(self);
	};
	
	
	this.postUpdateToMessageResponse = function(responseText , responseObject) {
		if(responseText != "OK") {
			alert(responseText);
		}
	};
	
	
	this.updateMessage = function(message) {
		self.deleteMessage = message.deleteMessage;
		self.highlightMessage = message.highlightMessage;
		if(message.deleteMessage) {
			self.delete();
		} else {
			self.recolorMessage();
		}
	};
	
	this.display = function() {
		if(self.messageBlock) {
			self.displayed = true;
			return;
		}
		
		if(self.deleteMessage) {
			self.displayed = false;
			return;
		}
		
		var container = document.createElement("div");
		container.setAttribute("class","groupChatMessageContainer");
		container.id = "groupChatMessageID" + self.messageID;

		if(self.userID != "system") {
			var avatarBlock = document.createElement("div");
			avatarBlock.setAttribute("class","avatarBlock");
			var avatarImage = document.createElement("img");
			avatarImage.setAttribute("class","avatarImage");
			if(users[self.userID] && users[self.userID].avatarID) {
				avatarImage.src = users[self.userID].avatarID;
			}
			avatarBlock.appendChild(avatarImage);

			var nameBlock = document.createElement("div");
			nameBlock.setAttribute("class","nameBlock");
			if(users[self.userID] && users[self.userID].moderator) {
				nameBlock.appendChild(document.createTextNode("Moderator-" + decodeURIComponent(self.name)));
				nameBlock.style.color = "red";
			} else {
				nameBlock.appendChild(document.createTextNode(decodeURIComponent(self.name)));
			}

			var date = new Date();
			var interimTime = date.createFromMysql(self.time);
			var timeBlock = document.createElement("span");
			timeBlock.appendChild(document.createTextNode(interimTime.toLocaleTimeString()));
			timeBlock.setAttribute("class" , "timeStamp");
			nameBlock.appendChild(timeBlock);			

			var messageText = document.createElement("div");
			messageText.setAttribute("class","groupChatWindowMessage");
			self.makeUneditable();
			messageText.innerHTML = self.text
			messageText.title = interimTime.toLocaleTimeString();
			container.appendChild(avatarBlock);
			container.appendChild(nameBlock);

		} else {
			var messageText = document.createElement("div");
			messageText.setAttribute("class","systemMessage");
			self.makeUneditable();
			messageText.innerHTML = self.text;
			container.style.height = "32px";
			container.style.minHeight = "32px";
			container.style.color = "blue";
		}
						
		container.appendChild(messageText);
		self.window.appendChild(container);

		
		self.messageBlock = document.getElementById("groupChatMessageID" + self.messageID);
		self.messageClass = container.getAttribute("class");
		
		//Check to see if scroll flag is turned on
		var groupChatScrollCheckBox = document.getElementById("groupChatScrollCheckBox").checked;
		if(groupChatScrollCheckBox) {
			self.window.scrollTop += self.window.scrollHeight + 10; 
		}
		
		self.displayed = true;
		self.recolorMessage();

		if (!self.moderator && self.userID !== "system") {
			messageText.style.backgroundColor = 'rgba(200,200,250,.5)';
		}
		
		if(users && users[currentUser] && users[currentUser].moderator) {
			messageText.title += "\nClick for Message Options";
		}
		
		//Message Settings if Current User is a moderator
		if(users && users[currentUser] && users[currentUser].moderator && self.userID != "system" && users[currentUser].status == 1) {		
			container = self.addModeratorFunctions(container);
		}	

	};
	
	this.makeUneditable = function() {
		var makeNode = document.createElement("span");
		makeNode.innerHTML = self.text;
		var iterator = document.createNodeIterator(makeNode);

		// get the first matching node
		var node = iterator.nextNode();

		while (node) {
			if(node.nodeType != 3 && node.contentEditable != "false") {
				node.setAttribute("contentEditable", false);
			}
			node = iterator.nextNode();
		}
		self.text = makeNode.innerHTML;
	};
	
	this.recolorMessage = function() {
		var existingOptionsWindow = document.getElementById("groupChatModeratorMessageOptions");

		if(!existingOptionsWindow && self.messageBlock) {
			if(self.highlightMessage) {
				self.messageBlock.setAttribute("class", self.messageClass + " highlightMessage");
			} else {
				self.messageBlock.setAttribute("class", self.messageClass);
			}
		}
	};
	
	this.addModeratorFunctions = function() {
		self.messageBlock.onmouseover = function() {
			var existingOptionsWindow = document.getElementById("groupChatModeratorMessageOptions");
			if (!existingOptionsWindow) {
				self.messageBlock.setAttribute("class", self.messageClass + " messageSelection");
			}
		};
	
		self.messageBlock.onmouseleave = function() {
			self.recolorMessage();
		};

		self.messageBlock.onclick = function() {
			self.messageBlock.setAttribute("class", self.messageClass + " messageSelection");
			self.addModeratorOptionsWindow();
		};

	};
	
	this.addModeratorOptionsWindow = function() {
		var existingOptionsWindow = document.getElementById("groupChatModeratorMessageOptions");
		if (existingOptionsWindow) {
			remove(existingOptionsWindow);
		}
		
		var body = document.getElementsByTagName("body")[0];    
		var select = document.createElement("div");
		select.style.position = "absolute";
		select.setAttribute("class","div");
		select.id = "groupChatModeratorMessageOptions";
		var h1 = document.createElement("h3");
		h1.appendChild(document.createTextNode("Moderator Options"));
		select.appendChild(h1);
		
		var inputHighlight = document.createElement("input");
		inputHighlight.style.display = "block";
		inputHighlight.type = "button";
		inputHighlight.style.margin = "10px";
		inputHighlight.style.fontSize = "12px";

		if(self.highlightMessage) {
			inputHighlight.value = "UnHighlight";
			inputHighlight.onclick = function() {
				self.highlightMessage = 0;
				self.postUpdateToMessage();
				remove(this.parentNode);
				self.recolorMessage();
			};				
		} else {
			inputHighlight.value = "Highlight";
			inputHighlight.onclick = function() {
				self.highlightMessage = 1;
				self.postUpdateToMessage();
				remove(this.parentNode);
				self.recolorMessage();
			};
		}
		select.appendChild(inputHighlight);

		var inputDelete = document.createElement("input");
		inputDelete.style.display = "block";
		inputDelete.style.fontSize = "12px";
		inputDelete.type = "button";
		inputDelete.value = "Delete";
		inputDelete.style.margin = "10px";
		inputDelete.onclick = function() {
			self.deleteMessage = 1;
			self.postUpdateToMessage();
			remove(this.parentNode);
		};

		select.appendChild(inputDelete);

		var option = document.createElement("input");
		option.type = 'button';
		option.style.fontSize = "12px";
		option.style.fontWeight = "bold";
		option.value = "CANCEL";
		option.class = "defaultButton";
		option.style.marginLeft = "100px";
		option.style.padding = "5px";
		option.onclick = function() {
			remove(this.parentNode);
			self.recolorMessage();

		}; 
		select.appendChild(option);

		var elementPosition = null;
		elementPosition = self.getMessagePosition();
		var elementHeight = window.getComputedStyle(self.messageBlock).height.replace("px","");
		var topAdjust = (180 - elementHeight) / 2;
		var left = elementPosition.left + 20;
		var top = elementPosition.top - topAdjust + 80;
		if(elementHeight > 200) {
			top = 200;
		}
		select.style.left = left + "px";
		select.style.top = top + "px";
		select.style.display = "block";

		body.appendChild(select);
	};
	
	
	this.getMessagePosition = function() {
		var elem = self.messageBlock;
		var box = elem.getBoundingClientRect();
		var body = document.body;
		var docElem = document.documentElement;

		var scrollTop = window.pageYOffset || docElem.scrollTop || body.scrollTop;
		var scrollLeft = window.pageXOffset || docElem.scrollLeft || body.scrollLeft;

		var clientTop = docElem.clientTop || body.clientTop || 0;
		var clientLeft = docElem.clientLeft || body.clientLeft || 0;

		var top  = box.top +  scrollTop - clientTop;
		var left = box.left + scrollLeft - clientLeft;
		return { top: Math.round(top), left: Math.round(left) };
	};

	
	this.delete = function() {
		remove(self.messageBlock);
	};
	
	this.highlight = function() {
		self.messageBlock.setAttribute("class" , self.messageClass + " highlightMessage");
		self.highlightMessage = true;
	};

	this.unHighlight = function() {
		self.messageBlock.setAttribute("class" , self.messageClass);
		self.highlightMessage = false;
	};

		
	messages[message.id] = self;
}	


function ImMessage(message) {
	this.user = 		message.userID; 		//action field from chatFeed2 carries the imTo data
	this.imTo = 		message.imTo;
	this.message = 		unescapeHTML(message.message);
	this.time =			new Date();
	this.imFrom = 		message.imFrom;			//userID field form chatFeed2 carries the imFrom data
	this.id = 			message.messageNumber;
}

function countLoggedOnUsers() {
	var chatRoomID = document.getElementById("groupChatRoomID").value;
	var loggedOnUsers = 0;
	var moderators = 0;
	for (var key in users) {
		var user = users[key];
		if(user.status) {
			loggedOnUsers += 1;
		}
		if(user.moderator && user.status) {
			moderators += 1;
		}
	}
	var groupChatNumberCount = document.getElementById("groupChatNumberCount");
	groupChatNumberCount.innerText = loggedOnUsers;

	return moderators;
}
	
function User(message) {
	var self = this;
	this.name = decodeURIComponent(message.name);
	this.userID = message.userID;
	this.avatarID = message.avatarID;
	this.signedOn = true;
	this.status = false;
	this.messages = [];
	this.moderator = message.moderator;
	this.chatRoomID = message.chatRoomID;
	
	if(message.action ==='signon') {
		self.status = 1;
	} else if (message.action === 'signoff') {
		self.status = 0;
	}
	this.userNameBlock = "";
	this.groupChatTypingWrapper = document.getElementById("groupChatTypingWrapper");
	this.im = [];
	this.sendToChat = false;
	
	this.display = function() {
		if(self.userNameBlock) {
			remove(self.userNameBlock);
		}
		if(self.moderator == 2 || self.status == 0) {   //VCC Admin -- able to exercise moderator functions but not listed as user
			return;
		} 
		var groupChatMembersListWindow = document.getElementById("groupChatMembersListWindow");
		var div = document.createElement("div");
		div.setAttribute("id","groupChatUserID" + self.userID);
		var chatName = document.createElement("span");
		var chatAvatar = document.createElement("img");
		chatAvatar.src = self.avatarID;
		chatAvatar.style.border = "0px solid transparent";
		chatAvatar.setAttribute("class","avatarMemberList");
		chatAvatar.title = self.name;
		chatName.appendChild(document.createTextNode(self.name));
		div.style.verticleAlign = "middle";
		chatName.style.verticleAlign = "middle";
		if(self.moderator) {
			chatName.style.fontWeight = "bold";
			chatName.style.color = "red";
			chatName.title = "Moderator-" + self.name;
		}
		chatAvatar.style.verticleAlign = "middle";
 		div.appendChild(chatAvatar);
		div.appendChild(chatName);
		groupChatMembersListWindow.appendChild(div);
		self.userNameBlock = document.getElementById("groupChatUserID" + self.userID);		
		if(users[currentUser] && users[currentUser].status && users[currentUser].status== 1) {
			if(self.moderator ) {
				if(self.userID == users[currentUser].userID) {
					self.userNameBlock.onclick = function() {
						alert("You cannot click on your own name.");
					};
				} else {
					chatName.title = "Moderator.  Click to send private message.";
					self.addPrivateChatFunctions();
				}
				
			} else if (users[currentUser] && users[currentUser].moderator) {
				self.addModeratorFunctions();
			}
		} 				
	}; 


	this.updateUser = function(message) {
		self.sendToChat = message.sendToChat;
		self.name = message.name;
		self.userID = message.userID;
		self.avatarID = message.avatarID;
		self.moderator = message.moderator;
		self.chatRoomID = message.chatRoomID;
		if(self.moderator == 2 && self.userID == users[currentUser].userID) {
			var groupChatTypingWrapper = document.getElementById("groupChatTypingWrapper");
			var groupChatSignIn = document.getElementById("groupChatSignIn");
			self.logOnUser();
			groupChatTypingWrapper.style.display = "none";
			groupChatSignIn.style.display = "none";
		} else if(message.action === 'signoff') {
			self.signedOn = 0;
			self.status = 0;
			self.logOffUser();
		} else if(message.action === "signon") {
			self.signedOn = 1;
			self.status = 1;
			self.logOnUser();
			self.display();
		} else if (self.userID == users[currentUser].userID && self.sendToChat !== null) {
			self.privateChatTransferAcceptedCheck();
		}		
		var moderators = countLoggedOnUsers();
		if(moderators == 0) {
			roomClosedCheck(self.chatRoomID);
		}
	};


	this.privateChatTransferAcceptedCheck = function() {
		var url = '../chat/chatAccepted.php';
		params = "CallerID=" + self.userID;
		var resultsObject = new Object();
		var chatAvailableCheck = new AjaxRequest(url, params, self.completePrivateChatTransfer, resultsObject);
	};

	this.completePrivateChatTransfer = function(results, resultsObject) {
		if(results == 'connected') {
			var groupChatRoomID = document.getElementById("groupChatRoomID").value;
			endChat();
			window.location = '../chat/chat.php?CallerID=' + self.userID + '&groupChatTransferFlag=1';
		} else {
			self.privateChatTransferAcceptedCheck();
		}
	};

	
	this.logOffUser = function() {
		userNames[message.name] = false;
	 	self.status = 0;
	 	self.signedOn = false;
	 	if(self.imPane) {
		 	remove(self.imPane);
		 }
		 self.imPane = false;
	 	if(self.userID == currentUser) {
	 		self.groupChatTypingWrapper.style.display = "none";
			var footer = document.getElementById("groupChatControlButtonArea");
			footer.style.display = "none";
	 		var groupChatSignIn = document.getElementById("groupChatSignIn");
	 		groupChatSignIn.style.display = null;
	 	}
		if(self.userNameBlock) {
			remove(self.userNameBlock);
		}
		self.userNameBlock = null;
		var moderators = countLoggedOnUsers();
		if(moderators == 0) {
			roomClosedCheck(self.chatRoomID);
		}
	 };
	 
	 this.logOnUser = function() {
		userNames[message.name] = true;
	 	self.status = 1;
		self.signedOn = true;
	 	if(self.userID == currentUser) {
			removeLogonScreen();
			self.groupChatTypingWrapper.style.display = "block";
			var footer = document.getElementById("groupChatControlButtonArea");
			footer.style.display = "block";
	 		var groupChatSignIn = document.getElementById("groupChatSignIn");
	 		groupChatSignIn.style.display = "none";

			for (var key in users) {
				var user = users[key];
				if(user.status) {
					user.display();
				}
			}			
		}		
	 	self.display();
		var moderators = countLoggedOnUsers();
		if(moderators == 0) {
			roomClosedCheck(self.chatRoomID);
		}
	 };
	 
	 
	this.recolorBlock = function() {
		var existingOptionsWindow = document.getElementById("groupChatModeratorUserOptions");

		if(!existingOptionsWindow) {
			self.userNameBlock.setAttribute("class", "");
		} else {
			self.userNameBlock.setAttribute("class", "messageSelection");
		}
	};
	
	
	this.addPrivateChatFunctions = function() {
		self.userNameBlock.onmouseover = function() {
			var existingOptionsWindow = document.getElementById("groupChatPrivateIMOptions");
			if (!existingOptionsWindow) {
				self.userNameBlock.setAttribute("class", "messageSelection");
			}
		};
	
		self.userNameBlock.onmouseleave = function() {
			self.recolorBlock();
		};

		self.userNameBlock.onclick = function() {
			self.userNameBlock.setAttribute("class", "messageSelection");
			var clickedUserElement = this;
			var clickedUserID = this.id.replace("groupChatUserID","");
			users[clickedUserID].loadImPane();
		};
	};

	this.chatVolunteerAvailable = function(chatAvailableResultsFunction) {
		var url = '../chat/ChatAvailable.php';
		params = "none";
		var resultsObject = new Object();
		var chatAvailableCheck = new AjaxRequest(url, params, chatAvailableResultsFunction, resultsObject);
	
	}
	
	this.addModeratorFunctions = function() {
		self.userNameBlock.onmouseover = function() {
			var existingOptionsWindow = document.getElementById("groupChatModeratorUserOptions");
			if (!existingOptionsWindow) {
				self.userNameBlock.setAttribute("class", "messageSelection");
			}
		};
	
		self.userNameBlock.onmouseleave = function() {
			self.recolorBlock();
		};

		self.userNameBlock.onclick = function() {
			self.userNameBlock.setAttribute("class", "messageSelection");
			var resultsObject = new Object();
			var results = "";
			var resultsFunction = function(results) {
				self.addModeratorOptionsWindow(results);
			};
			var chatAvailable = self.chatVolunteerAvailable(resultsFunction);
		};
	};
	
	this.addModeratorOptionsWindow = function(chatAvailableFlag) {
		var existingOptionsWindow = document.getElementById("groupChatModeratorUserOptions");
		if (existingOptionsWindow) {
			remove(existingOptionsWindow);
		}
		
		var body = document.getElementsByTagName("body")[0];    
		var select = document.createElement("div");
		select.style.position = "absolute";
		select.setAttribute("class","div");
		select.style.width = "auto";
		select.id = "groupChatModeratorUserOptions";
		var h1 = document.createElement("h3");
		h1.appendChild(document.createTextNode("Moderator Options"));
		select.appendChild(h1);
		
		var inputPrivateMessage = document.createElement("input");
		inputPrivateMessage.style.fontSize = "12px";
		inputPrivateMessage.style.display = "block";
		inputPrivateMessage.type = "button";
		inputPrivateMessage.style.margin = "10px";
		inputPrivateMessage.value = "Private Msg";
		inputPrivateMessage.onclick = function() {
			clearInterval(checkVolunteerStatus);
			self.loadImPane();
			remove(this.parentNode);
			self.recolorBlock();
		};				
		select.appendChild(inputPrivateMessage);

		var inputSendToChat = document.createElement("input");
		inputSendToChat.id = "groupChatInitialSendToChatButton";
		inputSendToChat.style.display = "block";
		inputSendToChat.style.fontSize = "12px";
		inputSendToChat.type = "button";
		inputSendToChat.style.margin = "10px";
		inputSendToChat.value = "To 1-1 Chat";
		if(chatAvailableFlag == "available") {

			inputSendToChat.onclick = function() {
				self.addModeratorSentToChatOptionsWindow();
				remove(this.parentNode);
				self.recolorBlock();
			};				
		} else {
			inputSendToChat.style.color = "gray";
			if(chatAvailableFlag == "closed"){
				inputSendToChat.title = "One-to-One Chat Closed.";
			} else {
				inputSendToChat.title = "No Chat Volunteers Available";
			}
			inputSendToChat.onmouseover = function() {
				this.style.background = "white";
			}
		}
		select.appendChild(inputSendToChat);

		var inputBlockToday = document.createElement("input");
		inputBlockToday.style.display = "block";
		inputBlockToday.style.fontSize = "12px";
		inputBlockToday.type = "button";
		inputBlockToday.value = "Block User";
		inputBlockToday.style.margin = "10px";
		
		inputBlockToday.onclick = self.addModeratorBlockOptionsWindow;

		select.appendChild(inputBlockToday);

		var option = document.createElement("input");
		option.type = 'button';
		option.style.fontSize = "12px";
		option.style.fontWeight = "bold";
		option.value = "CANCEL";
		option.class = "defaultButton";
		option.style.marginLeft = "100px";
		option.style.padding = "5px";
		option.onclick = function() {
			clearInterval(checkVolunteerStatus);
			remove(this.parentNode);
			self.recolorBlock();
			var textArea = document.getElementById("groupChatTypingWindow");
			setEndOfContenteditable(textArea);


		}; 
		select.appendChild(option);

		var elementPosition = null;
		elementPosition = self.getMessagePosition();
		var elementHeight = window.getComputedStyle(self.userNameBlock).height.replace("px","");
		var topAdjust = (180 - elementHeight) / 2;
		var left = elementPosition.left - 200;
		var top = elementPosition.top - topAdjust;
		select.style.left = left + "px";
		select.style.top = top + "px";
		select.style.display = "block";

		body.appendChild(select);
		
		var refreshFunction = function() {
			var resultsObject = new Object();
			var results = "";
			var resultsFunction = function(results) {
				self.updateChatAvailableButtonResults(results);
			};
			var chatAvailable = self.chatVolunteerAvailable(resultsFunction);		
		};

		checkVolunteerStatus = setInterval(refreshFunction, 1000);

	};
	
	
	this.updateChatAvailableButtonResults = function(results, resultsObject) {
	
		var groupChatInitialSendToChatButton = document.getElementById("groupChatInitialSendToChatButton");
		var sendToChatSubmitButton = document.getElementById("sendToChatSubmitButton");
		if(groupChatInitialSendToChatButton) {
			var button = groupChatInitialSendToChatButton;
		} else if (sendToChatSubmitButton) {
			var button = sendToChatSubmitButton;
		} else {
			clearInterval(checkVolunteerStatus);
			return;
		}
	
		if(results != 'available') {
			button.style.color = "gray";
			if(results == "closed"){
				button.title = "One-to-One Chat Closed.";
			} else {
				button.title = "No Chat Volunteers Available";
			}
			button.onmouseover = function() {
				this.style.background = "white";
			}
			button.onclick = null;
		} else {
			var sendToChatMessage = document.getElementById("sendToChatMessage");
			button.onmouseover = null;
			button.title = "";
			button.style.color = "black";
			button.style.background = null;
			if(sendToChatSubmitButton) {		
				button.onclick = function() {
					self.sendThisUserToChat(sendToChatMessage.value);
					clearInterval(checkVolunteerStatus);
					remove(this.parentNode);
					self.recolorBlock();
				};
			} else if(groupChatInitialSendToChatButton) {
				button.onclick = function() {
					self.addModeratorSentToChatOptionsWindow();
					remove(this.parentNode);
					self.recolorBlock();
				};
			}
		}
	};	

	this.addModeratorSentToChatOptionsWindow = function() {
		
		var existingOptionsWindow = document.getElementById("groupChatModeratorUserOptions");
		if (existingOptionsWindow) {
			remove(existingOptionsWindow);
		}

		//Remove any Old Block Options Windows
		var existingBlockOptionsWindow = document.getElementById("groupChatModeratorUserBlockOptions");
		if (existingBlockOptionsWindow) {
			remove(existingBlockOptionsWindow);
		}

		var select = document.createElement("div");
		select.style.position = "absolute";
		select.setAttribute("class","div");
		select.style.width = "auto";
		select.id = "groupChatModeratorSentToChatOptions";
		var h1 = document.createElement("h3");
		h1.appendChild(document.createTextNode("Send to Chat"));
		select.appendChild(h1);

		var label = document.createElement("label");
		label.appendChild(document.createTextNode("Message to VCC Peer-Counselor:"));
		select.appendChild(label);

		var sendToChatMessage = document.createElement("textarea");
			sendToChatMessage.id="sendToChatMessage";
		sendToChatMessage.style.display = "block";
		sendToChatMessage.style.fontSize = "12px";
		sendToChatMessage.style.width = "320px";
		sendToChatMessage.style.marginLeft = "20px";
		sendToChatMessage.type = "text";
		sendToChatMessage.placeholder = "What do you want to tell the One-to-One Chat Volunteer?";

		select.appendChild(sendToChatMessage);

		var option = document.createElement("input");
		option.type = 'button';
		option.style.fontSize = "12px";
		option.style.fontWeight = "bold";
		option.style.marginLeft = "100px";
		option.value = "CANCEL";
		option.class = "defaultButton";
		option.style.padding = "5px";
		option.onclick = function() {
			clearInterval(checkVolunteerStatus);
			remove(this.parentNode);
			self.recolorBlock();
			var textArea = document.getElementById("groupChatTypingWindow");
			setEndOfContenteditable(textArea);

		}; 
		select.appendChild(option);

		var sendToChatButton = document.createElement("input");
		sendToChatButton.id = "sendToChatSubmitButton";
		sendToChatButton.style.display = "inline-block";
		sendToChatButton.style.fontSize = "12px";
		sendToChatButton.type = "button";
		sendToChatButton.value = "Send to Chat";
		sendToChatButton.style.marginLeft = "10px";
		
		sendToChatButton.onclick = function() {  //Send to Chat Code
			self.sendThisUserToChat(sendToChatMessage.value);
			remove(this.parentNode);
			self.recolorBlock();
			var textArea = document.getElementById("groupChatTypingWindow");
			setEndOfContenteditable(textArea);

		};
		
		select.appendChild(sendToChatButton);

		var elementPosition = null;
		elementPosition = self.getMessagePosition();
		var elementHeight = window.getComputedStyle(self.userNameBlock).height.replace("px","");
		var topAdjust = (180 - elementHeight) / 2;
		var left = elementPosition.left - 200;
		var top = elementPosition.top - topAdjust;
		select.style.left = left + "px";
		select.style.top = top + "px";
		select.style.display = "block";

		body.appendChild(select);
	}
	
	this.addModeratorBlockOptionsWindow = function() {
		//Remove main Moderator Options Window
		var existingOptionsWindow = document.getElementById("groupChatModeratorUserOptions");
		if (existingOptionsWindow) {
			remove(existingOptionsWindow);
		}

		//Remove any Old Block Options Windows
		var existingBlockOptionsWindow = document.getElementById("groupChatModeratorUserBlockOptions");
		if (existingBlockOptionsWindow) {
			remove(existingBlockOptionsWindow);
		}
		
		//Create New Block Options Window
		var select = document.createElement("div");
		select.style.position = "absolute";
		select.setAttribute("class","div");
		select.style.width = "auto";
		select.id = "groupChatModeratorUserBlockOptions";
		var h1 = document.createElement("h3");
		h1.appendChild(document.createTextNode("Block User"));
		select.appendChild(h1);

		var label = document.createElement("label");
		label.appendChild(document.createTextNode("Block User Thru:"));
		select.appendChild(label);

		var endBlockTime = document.createElement("input");
		endBlockTime.style.display = "inline-block";
		endBlockTime.style.fontSize = "12px";
		endBlockTime.style.width = "40px;";
		endBlockTime.style.marginLeft = "20px";
		endBlockTime.type = "date";
		var now = new Date;		
		now = now.toMysqlDate();
		now = now.split(" ")[0];
		now = now.toString();
		endBlockTime.value = now;
		endBlockTime.style.margin = "10px";

		var endBlockMessage = document.createElement("input");
		endBlockMessage.style.display = "block";
		endBlockMessage.style.fontSize = "12px";
		endBlockMessage.style.width = "320px";
		endBlockMessage.style.marginLeft = "20px";
		endBlockMessage.style.marginBottom = "10px";
		endBlockMessage.type = "text";
		endBlockMessage.placeholder = "Why did you block this person?";

		select.appendChild(endBlockTime);
		select.appendChild(endBlockMessage);

		var option = document.createElement("input");
		option.type = 'button';
		option.style.fontSize = "12px";
		option.style.fontWeight = "bold";
		option.style.marginLeft = "100px";
		option.value = "CANCEL";
		option.class = "defaultButton";
		option.style.padding = "5px";
		option.onclick = function() {
			remove(this.parentNode);
			self.recolorBlock();
		}; 
		select.appendChild(option);

		var inputBlockToday = document.createElement("input");
		inputBlockToday.style.display = "inline-block";
		inputBlockToday.style.fontSize = "12px";
		inputBlockToday.type = "button";
		inputBlockToday.value = "Block User";
		inputBlockToday.style.marginLeft = "10px";
		
		inputBlockToday.onclick = function() {  //Block Caller Code
			self.blockThisUser(endBlockTime.value, endBlockMessage.value);
			remove(this.parentNode);
			self.recolorBlock();
		};
		
		select.appendChild(inputBlockToday);


		var inputBlockIpToday = document.createElement("input");
		inputBlockIpToday.style.display = "inline-block";
		inputBlockIpToday.style.fontSize = "12px";
		inputBlockIpToday.class = "defaultButton";		
		inputBlockIpToday.value = "Block User by IP";
		inputBlockIpToday.style.marginLeft = "10px";
		
		inputBlockIpToday.onclick = function() {  //Block Caller Code
			const type = "ip";
			self.blockThisUser(endBlockTime.value, endBlockMessage.value, type);
			remove(this.parentNode);
			self.recolorBlock();
		};
		
		select.appendChild(inputBlockIpToday);


		var elementPosition = null;
		elementPosition = self.getMessagePosition();
		var elementHeight = window.getComputedStyle(self.userNameBlock).height.replace("px","");
		var topAdjust = (180 - elementHeight) / 2;
		var left = elementPosition.left - 200;
		var top = elementPosition.top - topAdjust;
		select.style.left = left + "px";
		select.style.top = top + "px";
		select.style.display = "block";

		body.appendChild(select);

	};
	
	
	this.getMessagePosition = function() {
		var elem = self.userNameBlock;
		var box = elem.getBoundingClientRect();
		var body = document.body;
		var docElem = document.documentElement;

		var scrollTop = window.pageYOffset || docElem.scrollTop || body.scrollTop;
		var scrollLeft = window.pageXOffset || docElem.scrollLeft || body.scrollLeft;

		var clientTop = docElem.clientTop || body.clientTop || 0;
		var clientLeft = docElem.clientLeft || body.clientLeft || 0;

		var top  = box.top +  scrollTop - clientTop;
		var left = box.left + scrollLeft - clientLeft;
		return { top: Math.round(top), left: Math.round(left) };
	};
	 
	this.isLoggedOn = function()  {
	 	return self.status;
	 };
	 
	this.createImWindow = function() {
		if(!users[currentUser].status || users[currentUser].status === 0 ) {
			return;
		}
		// CREATE IM WINDOW FOR THIS USER	
		var frag = document.createDocumentFragment();
		var h2 = document.createElement("h2");
		var div = document.createElement("div");
		var div2 = document.createElement("div");
		var form = document.createElement("form");
		var textarea = document.createElement("textarea");
		var input = document.createElement("input");
		var input2 = document.createElement("input");

		if(self.moderator) {
			h2.appendChild(document.createTextNode("PM with " + self.name));
		} else {
			h2.appendChild(document.createTextNode(self.name));
		}
		textarea.setAttribute("id",self.userID + "imMessage");
		textarea.setAttribute("class" , "imMessage");
		textarea.onkeypress = function(e) {	
			if (!e) {
				var e = window.event;
			}
			if (e.keyCode) {
				code = e.keyCode;
			} else if (e.which) {
				code = e.which;
			}
		
			if(code === 13) {
		        e.preventDefault();
				self.postIM();
			}	
			if(code == 10) {
		        e.preventDefault();
				self.postIM();
			}
		};

		input.setAttribute("id" , self.userID + "imClose");
		input.setAttribute("type","button");
		input.setAttribute("value" , "Close");
		input.onclick = function () {
			self.closeImPane();
		}

		input2.setAttribute("id" , self.userID + "imPost");
		input2.setAttribute("type" , "button");
		input2.setAttribute("value" , "Post");
		input2.onclick = function () {
			self.postIM();
		};


		form.setAttribute("id" , self.userID + "imForm");
		form.appendChild(textarea);
		form.appendChild(input);
		form.appendChild(input2);

		div.setAttribute("id" , self.userID + "imBody");
		div.setAttribute("class" , "imBody");

		div2.setAttribute("id" , self.userID + "imPane");
		div2.setAttribute("class" , "imPane");
		div2.setAttribute("draggable" , "true");
		div2.addEventListener('dragstart',drag_start,false); 

		div2.style.display = "none";
		div2.appendChild(h2);
		div2.appendChild(div);
		div2.appendChild(form);
		frag.appendChild(div2);
		document.getElementsByTagName("body")[0].appendChild(frag);
		self.imPane = div2;
		self.imBody = div;
		self.imForm = form;
		self.imMessage = textarea;

	};

	this.closeImPane = function (user) {
		remove(self.imPane);
		self.imPane = null;
		document.getElementById("groupChatTypingWindow").focus();

	};

	this.sendThisUserToChat = function(message) {
		var chatRoomID = document.getElementById("groupChatRoomID").value;
		var params = "chatRoomID=" + chatRoomID + "&userID=" + self.userID + "&name=" + self.name + "&message=" + encodeURIComponent(message);
		var responseObject = {};
		var url= "Admin/sendToChat.php";
		var blockUserRequest = new AjaxRequest(url, params, self.sendThisUserToChatResults , responseObject);
	};

	this.sendThisUserToChatResults = function(results, responseObject) {
		if(results != "OK") {
			alert(results);
		}	
	};

	this.blockThisUser = function(endOfBlock, message, type) {
		var chatRoomID = document.getElementById("groupChatRoomID").value;
		var params = "chatRoomID=" + chatRoomID + "&endDate=" + endOfBlock + "&userID=" + self.userID + "&name=" + self.name + "&message=" + encodeURIComponent(message) + "&type=" + type;
		var responseObject = {};
		var url= "Admin/blockUser.php";
		var blockUserRequest = new AjaxRequest(url, params, self.blockUserResults , responseObject);
	};

	this.blockUserResults = function(results, responseObject) {
		if(results != "OK") {
			alert(results);
		}
	};

	
	this.loadImPane = function () {
		if(!self.imPane) {
			self.createImWindow();
		}
		
		self.imBody.innerHTML = "";
//		self.imMessage.value = "";
		self.imPane.style.display = "block";
		self.im.forEach(function(im) {
			var p = document.createElement("p");
			p.style.backgroundColor = 'rgba(220,220,220,1)';
			p.style.padding = "4px";
			p.style.borderRadius = '12px';
			p.style.width = "200px";
			p.style.marginBottom = "-10px";
			var time = im.time;
			var convertedTime = time.toLocaleTimeString();
			p.title = convertedTime;

			if (im.imFrom == users[currentUser].userID) {
				p.style.marginLeft = "50px";
				p.style.backgroundColor = 'rgba(150,150,250,.35)';
			}
			p.appendChild(document.createTextNode(im.message));
			self.imBody.appendChild(p);
			self.imBody.scrollTop += self.imBody.scrollHeight;
		});			
		self.imMessage.focus();
	};
	
	this.postIM = function (user) {
		if(!user) {
			user=self;
		}
		var chatRoomID = document.getElementById("groupChatRoomID").value;

		var imMessageText = escapeHTML(user.imMessage.value);
		user.imMessage.value = "";

	
		var url = 'groupChatPostIM.php';
		var resultsObject = new Object();
		var postResults = function (results, searchObject) {
			postMessageResult(results, searchObject);
		};
				
		params = "imTo=" + escape(self.userID) + "&userID=" + escape(users[currentUser].userID) + "&Text=" + imMessageText + "&chatRoomID=" + chatRoomID;
		var postIM = new AjaxRequest(url, params, postResults, resultsObject);
		var textArea = document.getElementById("groupChatTypingWindow");
		if(!users[currentUser].moderator) {
			user.imPane.style.display = "none";
			setEndOfContenteditable(textArea);
		} 
		return false;
	};

	this.receiveIm = function (message) {
		var imMessage = new ImMessage(message);
		if(users[currentUser] && message.imFrom === users[currentUser].userID) {   //userID = IMsender  
			users[imMessage.imTo].im[imMessage.id] = imMessage;
			var url = "groupChatIMReceived.php";
			var	params = "action=" + imMessage.id + "&text=from";
			var updateMessageStatusResults = function (results, searchObject) {
				postMessageResult(results, searchObject);
			};
			var updateMessageStatus = new AjaxRequest(url, params, updateMessageStatusResults);
			if(users[currentUser].moderator) {
				users[imMessage.imTo].loadImPane();
			}

		} else if (message.imTo === users[currentUser].userID) {
			users[imMessage.imFrom].im[imMessage.id] = imMessage;
			users[imMessage.imFrom].loadImPane();
			var url = "groupChatIMReceived.php";
			var	params = "action=" + imMessage.id + "&text=to";
			var updateMessageStatusResults = function (results, searchObject) {
				postMessageResult(results, searchObject);
			};
			var updateMessageStatus = new AjaxRequest(url, params, updateMessageStatusResults);
		}
	};
		
	 
	 return self;
}
	 
    
    
function groupChatLogin() {
	var chatRoomID = document.getElementById("groupChatRoomID").value;
	var userID = document.getElementById("userID").value;

	var loginForm = document.getElementById("groupChatSignInForm");
	var groupChatAvatarSelectionArea = document.getElementById("groupChatAvatarSelectionArea");
	var groupChatModeratorFlag= document.getElementById("groupChatModeratorFlag");
	var avatarsPossible = groupChatAvatarSelectionArea.getElementsByTagName("img");
	var selectedAvatar = false;
	for (var i=0 ; i < avatarsPossible.length ; i++) {
		var avatarStatus = avatarsPossible[i].getAttribute("class");
		if(avatarStatus=="avatarSelected") {
			selectedAvatar = avatarsPossible[i];
		}
	}
	if(!selectedAvatar) {
		alert("Please select an avatar by clicking on it.");
		return false;
	}
	var name = document.getElementById("groupChatSignInFormName").value;
	if(!name) {
		alert("Please type in a name to use for this chat.");
		return false;
	}
	
	if(userNames[document.getElementById("groupChatSignInFormName").value]) {
		alert("That name is already in use.  Please choose another name.");
		return false;
	}
	
	
	var responseObject = {};
	var url = "callerSignOn.php";
	var params = "Name=" + encodeURIComponent(name) + "&Avatar=" + escape(selectedAvatar.getAttribute("src")) + 
				 "&Moderator=" + groupChatModeratorFlag.value + "&chatRoomID=" + chatRoomID +
				 "&userID=" + userID;  
	var avatarOptionsRequest = new AjaxRequest(url, params, signOnResponse , responseObject);

}
    
    
    
    
function signOnResponse(results, resultObject) {
	if(results == "OK") {
		removeLogonScreen();
	} else if (results == "Full") {
		alert("The Chat Room is currently full.  Please try to sign on once someone leaves.");
	} else if (results == "Blocked") {
		alert("BLOCKED: You cannot join the chat room right now.  Please try later.");
	} else if (results == "Closed") {
		alert("Room Closed. Please try during open hours, or wait for moderator to sign in.");
	} else {
		alert("An error has occurred:  "  + results);
	}
}
	



function removeLogonScreen() {
	var groupChatSignIn = document.getElementById("groupChatSignIn");
	groupChatSignIn.style.display = "none";
	var groupChatTypingWrapper = document.getElementById("groupChatTypingWrapper");
	groupChatTypingWrapper.style.display = "block";		
	document.getElementById("groupChatControlButtonArea").style.display = "block";

	var groupChatModeratorFlag = document.getElementById("groupChatModeratorFlag").value;
	if(groupChatModeratorFlag != 0) {
		var groupChatScroll = document.getElementById("groupChatScroll");
		groupChatScroll.style.display = "inline-block";
	}
	document.getElementById("groupChatTypingWindow").focus();	
}

    
function GetAvatars() {
	var self = this;
	this.avatarFileNameList = {};
	this.currentAvatar = 0;
	this.url = "getAvatars.php";
	this.groupChatAvatarSelectionArea = document.getElementById("groupChatAvatarSelectionArea");
	this.params ='get=true';
	

	
	this.init = function() {
		var avatarOptionsRequest = new AjaxRequest(self.url, self.params, self.avatarOptionsRequestResponse , self);
	};


	this.avatarOptionsRequestResponse = function(results, resultObject) {
		self.avatarFileNameList = JSON.parse(results);
		self.placeAvatars();				
	};

	this.placeAvatars= function() {
		while(self.currentAvatar < self.avatarFileNameList.length) {
			var newAvatar = document.createElement("img");
			const avatarSize = 144/3;
			newAvatar.style.width = avatarSize + "px";
			newAvatar.style.height = avatarSize + "px";
			newAvatar.src = self.avatarFileNameList[self.currentAvatar];
			newAvatar.setAttribute("class" , "avatar");
			newAvatar.id = self.avatarFileNameList[self.currentAvatar];
			newAvatar.onclick = function() {self.avatarSelected(this);};
			groupChatAvatarSelectionArea.appendChild(newAvatar);
			self.currentAvatar += 1;	
		}
	};
	
	this.avatarSelected = function(avatar) {
		var avatars = this.groupChatAvatarSelectionArea.getElementsByTagName("img");
		for (var i=0 ; i < avatars.length ; i++) {
			avatars[i].setAttribute("class" , "avatar");
		}
		avatar.setAttribute("class" , "avatarSelected");
	};
	
		
}


 function drag_start(event) {
	dragObject = this;
    var style = window.getComputedStyle(event.target, null);
    event.dataTransfer.setData("text/plain",
    	(parseInt(style.getPropertyValue("left"),10) - event.clientX) + ',' + 
    	(parseInt(style.getPropertyValue("top"),10) - event.clientY));
} 

function drag_over(event) { 
    event.preventDefault(); 
    return false; 
} 

function resoureDetailDrop(event) {
    var data = event.dataTransfer.getData("text/plain");
    if(data.substr(0,4) == "Here") {
		event.target.value = data;
	}
    event.preventDefault();
    event.target.focus();
    return false;
}

function drop(event) { 
    var offset = event.dataTransfer.getData("text/plain").split(',');
    dragObject.style.left = (event.clientX + parseInt(offset[0],10)) + 'px';
    dragObject.style.top = (event.clientY + parseInt(offset[1],10)) + 'px';
    dragObject.style.zIndex = event.target.zIndex + 10;
    event.preventDefault();
    return false;
} 




