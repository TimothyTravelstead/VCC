var userList = [];





window.onload = function() {


	var groupChatMonitor1RoomSelector = document.getElementById("groupChatMonitor1RoomSelector");
	var groupChatMonitor2RoomSelector = document.getElementById("groupChatMonitor2RoomSelector");

	var groupChatMonitor1CloseButton = document.getElementById("groupChatMonitor1CloseButton");
	var groupChatMonitor2CloseButton = document.getElementById("groupChatMonitor2CloseButton");

	var showAdminFeaturesElement = document.getElementById("showAdminFeatures");
	var showAdminFeaturesValue = showAdminFeaturesElement ? showAdminFeaturesElement.value.trim() : 'not found';
	var showAdminFeatures = showAdminFeaturesValue === '1';
	const VCCIMButtonElement = document.getElementById("VCCIMButton");
	
	if(showAdminFeatures) {    	// If Admin, not Monitor, show close room buttons & hide VCCIm Functions
		groupChatMonitor1CloseButton.style.display = 'inline';
		groupChatMonitor2CloseButton.style.display = 'inline';
		VCCIMButtonElement.style.display = "none";
		
	} else {
//		VCCIMButtonElement.onclick = function() {VCCIMButton(this);};
	}	
	
	groupChatMonitor1CloseButton.onclick = function() {
		var roomID = groupChatMonitor1RoomSelector.options[groupChatMonitor1RoomSelector.selectedIndex].id;
		closeRoom(roomID);
	}

	groupChatMonitor2CloseButton.onclick = function() {
		var roomID = groupChatMonitor2RoomSelector.options[groupChatMonitor2RoomSelector.selectedIndex].id;
		closeRoom(roomID);
	}
	
	groupChatMonitor1RoomSelector.onchange = function() {
		var value = this.options[this.selectedIndex].id;
		var frame1 = document.getElementById("groupChatAdminFrame1");
		var signOn = moderatorSignOn(frame1, value);				
	};
		
	groupChatMonitor2RoomSelector.onchange = function() {
		var value = this.options[this.selectedIndex].id;
		var frame2 = document.getElementById("groupChatAdminFrame2");
		var signOn = moderatorSignOn(frame2, value);
	};

	var exitButton = document.getElementById("ExitButton");
	exitButton.onclick = Exit;

	var monitor = new VCCMonitor("testing");
	monitor.init();
	


	const newInfoCenter = new InfoCenter();

	//Make InfoCenter Draggable
/*	document.getElementById('groupChatMonitor1').addEventListener('dragover',drag_over,false); 
	document.getElementById('groupChatMonitor1').addEventListener('drop',drop,false); 

	document.getElementById('groupChatMonitor2').addEventListener('dragover',drag_over,false); 
	document.getElementById('groupChatMonitor2').addEventListener('drop',drop,false); 

	
	document.body.addEventListener('dragover',drag_over,false); 
	document.body.addEventListener('drop',drop,false); 
	var infoCenterPaneElement = document.getElementById("infoCenterPane");
	infoCenterPaneElement.addEventListener('dragstart',drag_start, false);
*/
}


window.onbeforeunload = function (event) {
	navigator.sendBeacon("moderatorExitVCC.php", "Exit=true");
};


function closeRoom(chatRoomID) {
	var groupChatMonitor1RoomSelector = document.getElementById("groupChatMonitor1RoomSelector");
	var groupChatMonitor2RoomSelector = document.getElementById("groupChatMonitor2RoomSelector");

	// Find the room name for confirmation
	var roomName = "";
	for(var i = 0; i < groupChatMonitor1RoomSelector.options.length; i++) {
		if(groupChatMonitor1RoomSelector.options[i].id == chatRoomID) {
			roomName = groupChatMonitor1RoomSelector.options[i].innerHTML;
			break;
		}
	}
	if(!roomName) {
		for(var i = 0; i < groupChatMonitor2RoomSelector.options.length; i++) {
			if(groupChatMonitor2RoomSelector.options[i].id == chatRoomID) {
				roomName = groupChatMonitor2RoomSelector.options[i].innerHTML;
				break;
			}
		}
	}

	var verifyClose = confirm("Close the room: " + roomName + "?\n\nThis will clear the chat and email the transcript.");
	if(verifyClose) {
		var responseObject = {};
		var url = "closeRoom.php";
		var params = "chatRoomID=" + chatRoomID;
		var closeRequestResult = function(result, resultObject) {
			alert(result);
		}
		var closeRoomRequest = new AjaxRequest(url, params, closeRequestResult , responseObject);
	}

}

function Exit() {
	var responseObject = {};
	var url = "moderatorRoomList.php";
	var params = "none";
	var moderatorRoomListRequest = new AjaxRequest(url, params, exitConfirmed , responseObject);
}


function exitConfirmed(results) {
	if(results != "none") {
		alert("Please log off of all Group Chat Rooms before leaving the System."  + results);
		return;
	} else {
		completeExit();
	}
}



function moderatorSignOn(frame, selector) {
	console.log("========== moderatorSignOn() DEBUG ==========");
	console.log("frame:", frame);
	console.log("selector:", selector);

	var responseObject = {};
	if(selector == 0) {
		console.log("selector is 0, clearing frame.src");
		frame.src = "";
		return;
	}

	var userID = document.getElementById("AdministratorID").value;
	userID = userID.trim();
	console.log("userID:", userID);

	var url = "moderatorSignOn.php";
	var params = "Name=" + "Moderator" + "&userID=" + userID + "&ChatRoomID=" + selector;
	console.log("Calling moderatorSignOn.php with params:", params);
	var avatarOptionsRequest = new AjaxRequest(url, params, signOnResponse , responseObject);

	// Add cache-buster to prevent browser from loading cached iframe content
	var cacheBuster = Date.now() + '_' + Math.floor(Math.random() * 1000000);
	var iframeSrc = '../index.php?ChatRoomID=' + selector + '&userID=' + userID + '&_cb=' + cacheBuster;
	console.log("Setting frame.src to:", iframeSrc);
	frame.src = iframeSrc;
	console.log("=============================================");
}


function signOnResponse(results, resultObject) {
	if(results != "OK") {
		alert("Moderator Sign On/Switch Error.  " + results);
	}
}
	
function signOffResponse(results, resultObject) {
	if(results != "OK") {
		alert("Moderator Sign Off Error.  " + results);
		return;
	}
}


function completeExit() {
	var completeExitResponseObject = {};
	var url = "moderatorExitVCC.php";
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
		window.location = '../../index.php';
	};

	var params = "postType=exitProgram";
	var exitProgramRequest = new AjaxRequest("../../volunteerPosts.php", params, finalResponseFunction);	
}


function VCCIMButton() {
	var IMDisplay = document.getElementById("volunteerList");
	var displayed = IMDisplay.style.display;
	
	if(!displayed || displayed == "none") {
		IMDisplay.style.display = "inline-block";
	} else {
		IMDisplay.style.display = "none";
	}
}

function createImWindow(user) {
	// CREATE IM WINDOW FOR THIS USER	
	var frag = document.createDocumentFragment();
	var h2 = document.createElement("h2");
	var div = document.createElement("div");
	var div2 = document.createElement("div");
	var form = document.createElement("form");
	var textarea = document.createElement("textarea");
	var input = document.createElement("input");
	var input2 = document.createElement("input");

	h2.appendChild(document.createTextNode(user.name));

	textarea.setAttribute("id",user.userName + "imMessage");
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
		
		if(code == 13) {
			user.postIM(user);
		}	
		if(code == 10) {
			user.postIM(user);
		}
	}

	input.setAttribute("id" , user.userName + "imClose");
	input.setAttribute("type","button");
	input.setAttribute("value" , "Close");
	input.onclick = function () {
		this.parentNode.parentNode.style.display='none';
		document.getElementById("groupChatAdminFrame1").focus();
	}

	input2.setAttribute("id" , user.userName + "imPost");
	input2.setAttribute("type" , "submit");
	input2.setAttribute("value" , "Post");
	input2.onclick = function () {
		user.postIM(user);
		return false;
	}


	form.setAttribute("id" , user.userName + "imForm");
	form.appendChild(textarea);
	form.appendChild(input);
	form.appendChild(input2);

	div.setAttribute("id" , user.userName + "imBody");
	div.setAttribute("class" , "imBody");

	div2.setAttribute("id" , user.userName + "imPane");
	div2.setAttribute("class" , "imPane");
	div2.onclick = function () {locateImPane(user);}; 

	div2.style.display = "none";
	div2.appendChild(h2);
	div2.appendChild(div);
	div2.appendChild(form);
	frag.appendChild(div2);
	document.getElementsByTagName("body")[0].appendChild(frag);
	return div2;
}


function testResults(results, resultsObject) {
	if(results && results != "OK") {
		alert(results);
	}
}

function User(user) {

	this.userName = user.UserName;
	var self = this;
	this.callObject = "";
	this.volunteerID = document.getElementById("AdministratorID").value;
	this.volunteerID = this.volunteerID.trim();
	this.name = user.FirstName + " " + user.LastName;
	this.shift = user.Shift;
	this.onCall = " ";
	this.chat = user.Chat;
	this.AdminLoggedOn = user.AdminLoggedOn;
	this.im = [];
	this.signedInUser = user.UserName == this.volunteerID;
	if(!this.imPane) {
		createImWindow(self);
		this.imPane = document.getElementById(self.userName + "imPane")
		this.imBody = document.getElementById(self.userName + "imBody")
		this.imForm = document.getElementById(self.userName + "imForm")
		this.imMessage = document.getElementById(self.userName + "imMessage")
	}

	this.closeImPane = function (user) {
		document.getElementById(self.userName + "imPane").style.display = "none";
	};
	
	this.updateUser = function (user) {
		self.signedInUser = user.UserName == this.volunteerID;
		self.shift = 			user.Shift;
		self.onCall = 			user.OnCall;
		self.adminRinging = 	user.adminRinging;

		self.chat = 	user.Chat;
		self.callObject = JSON.parse(user.CallObject);
		if(self.callObject) {
			if(self.callObject.CallStatus) {
				self.callObject.CallStatus = user.CallStatus;
			}
		}
	};
	
	
	this.loadImPane = function () {
		locateImPane(self);
		self.imBody.innerHTML = "";
		self.imMessage.value = "";
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

			if (im.fromUser == self.volunteerID) {
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
		user.imPane.style.display = "none";
		var imMessageText = escapeHTML(user.imMessage.value);
	
		var url = '../../volunteerPosts.php';
		var resultsObject = new Object();
		var postResults = function (results, searchObject) {
			testResults(results, searchObject);
		};
		params = "postType=postIM&action=" + escape(user.userName) + "&text=" + imMessageText;
		var postIM = new AjaxRequest(url, params, postResults, resultsObject);
		document.getElementById("groupChatAdminFrame1").focus();
		return false;
	};

	this.receiveIm = function (message) {
		if(message.from == this.volunteerID) {
			var imMessage = new ImMessage(message);
			userList[message.to].im[message.id] = imMessage;
			var url = "../../volunteerPosts.php";
			var	params = "postType=IMReceived&action=" + message.id + "&text=from";
			var updateMessageStatusResults = function (results, searchObject) {
				testResults(results, searchObject);
			};
			var updateMessageStatus = new AjaxRequest(url, params, updateMessageStatusResults);
		} else if (message.to == this.volunteerID || message.to == 'All') {

			var imMessage = new ImMessage(message);
			userList[message.from].im[message.id] = imMessage;
			userList[message.from].loadImPane();
			var url = "../../volunteerPosts.php";
			var	params = "postType=IMReceived&action=" + message.id + "&text=to";
			var updateMessageStatusResults = function (results, searchObject) {
				testResults(results, searchObject);
			};
			var updateMessageStatus = new AjaxRequest(url, params, updateMessageStatusResults);
		}
	};
	
	this.displayIm = function () {
		this.imPane.openWindow();
		alert("this.imPane.displayIM");
	};
	
}


function ImMessage(message) {
	this.user = 		message.to;
	this.message = 		unescapeHTML(message.text);
	this.time =			new Date();
	this.fromUser = 	message.from;
	this.id = 			message.id;
	this.toDelivered = 	message.toDelivered;
	this.fromDelivered = message.fromDelivered;
}


function locateImPane(user) {
	for (var key in userList) {
		var otherIM = userList[key];
		var otherIMPane = otherIM.imPane;
		var userPane = user.imPane;
	    var otherStyle = window.getComputedStyle(otherIMPane, null);
	    var userStyle = window.getComputedStyle(userPane, null);

		if(otherIM.name != user.name && otherIM.imPane.style.display == "block") {
			var otherX = parseInt(otherStyle.getPropertyValue("left"),10);
			var otherY = parseInt(otherStyle.getPropertyValue("top"),10);
			var otherZ = parseInt(otherStyle.getPropertyValue("z-Index"),10);
			var userX = parseInt(userStyle.getPropertyValue("left"));
			var userY = parseInt(userStyle.getPropertyValue("top"));
			var userZ = parseInt(userStyle.getPropertyValue("z-Index"),10);

			
			if (otherX == userX && otherY == userY) {
				userPane.style.left = (userX + 50) + 'px';
				userPane.style.top = (userY + 50) + 'px';
				userPane.style.zIndex = (otherZ + 1);
				locateImPane(user);
			}
			if (otherZ >= userZ) {
				userPane.style.zIndex = (otherZ + 1);
				locateImPane(user);
			}
		}
	}
}


function VCCMonitor(name) {
	this.source = "";
	

	this.init = function () {
		if(typeof(EventSource)!=="undefined") {
			this.source = new EventSource("../../vccFeed.php?reset=1");
			var source=this.source;
	
			const showAdminFeatures = document.getElementById("showAdminFeatures").value === '1';

			if(!showAdminFeatures)  {
				source.addEventListener('userList', function(event) {
	//				if(chatMonitorKeepAlive != null)clearTimeout(chatMonitorKeepAlive);
	//				chatMonitorKeepAlive = setTimeout("newChat.init();",30000);

					message = JSON.parse(event.data);
					var onlineUsers = {};

					message.forEach(function(message) {
						var userRecord = userList[message.UserName];
						onlineUsers[message.UserName] = 1;
					
						if(!userRecord) {
							userList[message.UserName] = new User(message);
							userRecord = userList[message.UserName];
						} else {
							userRecord.updateUser(message);
						}
					
						var userDisplayed = document.getElementById(message.UserName);
										
						if(userDisplayed) {
										
							userDisplayed.childNodes[1].innerHTML = " ";
							// userDisplayed.childNodes[3].innerHTML = userRecord.chat;

							var onChatCell = userDisplayed.childNodes[3];
							var onCallCell = userDisplayed.childNodes[2];

							if(userRecord.adminRinging == "Watching Video") {
								onCallCell.innerHTML = "Video";
							} else {
								onCallCell.innerHTML = userRecord.onCall;
								onChatCell.innerHTML = userRecord.chat;
							}
						

						} else if (message.AdminLoggedOn < 5 || message.AdminLoggedOn > 6) {
							var userTable = document.getElementById('volunteerListTable');
							var tr = document.createElement("tr");

							tr.onclick = function() {
								userRecord.loadImPane();
							};
							tr.title = "Click to Send an IM to this Volunteer.";	
							tr.setAttribute("class" , "hover");						
						
							tr.setAttribute("id",userRecord.userName);
							var td = document.createElement("td");

							td.appendChild(document.createTextNode(userRecord.name));
							tr.appendChild(td);

							var td = document.createElement("td");

							td.appendChild(document.createTextNode(" "));
							tr.appendChild(td);

							var td = document.createElement("td");

							td.appendChild(document.createTextNode(userRecord.onCall));
							tr.appendChild(td);
						
							var td = document.createElement("td");
							td.appendChild(document.createTextNode(userRecord.chat));

							tr.appendChild(td);
							userTable.appendChild(tr);		
						}						
					});

				// Delete Users No Longer Signed On
					for(currentUser in userList) {
						var user = userList[currentUser];
						var userPresent = false;
						var adminPresent = false;
						message.forEach(function(userStillSignedOn) {
							if(userStillSignedOn.UserName == user.userName || user.userName == "admin") {
								userPresent = true;
							}
						});
						if(!userPresent) {
							var userTable = document.getElementById('volunteerListTable');
							if(document.getElementById(user.userName)) {
								userTable.removeChild(document.getElementById(user.userName));
							}
							delete(userList[currentUser]);
						}
					}

				},false);			//UserList	

				source.addEventListener('IM', function(event) {
					message = JSON.parse(event.data);
					if(userList[message.from]) {
						userList[message.from].receiveIm(message);
					}		
				},false);			//IM	
			}

			source.addEventListener('logoff', function(event) {
				if(event.data == "0") {
					window.onbeforeunload = "";
					Exit();
				}				
			},false);			//Logoff	


		} else {
			document.getElementById("result").innerHTML="Sorry, your browser does not support server-sent events...";
		}
	};

}


function InfoCenter() {
	var self = this;
	this.infoCenterMenu = document.getElementById("infoCenterMenu");
	this.infoCenterPane = document.getElementById("infoCenterPane");
	this.infoCenterText = document.getElementById("infoCenterText");
	this.infoCenterClose = document.getElementById("infoCenterClose");
	this.infoCenterButtons = document.getElementById("infoCenterButtons").getElementsByTagName("input");
	this.oldPane = null;

	this.params = "postType=infoCenter";
	this.url = "../../volunteerPosts.php";
	this.text = {};

	for (i=0;i<this.infoCenterButtons.length; i++) {
		item = this.infoCenterButtons[i];
		item.onclick = function() {
			var finalParams = self.params + "&action=" + this.value;
			var infoCenterText = new AjaxRequest(self.url, finalParams, self.infoCenterResponse , self);
		};
	}
	
	this.infoCenterClose.onclick = function () {
		self.infoCenterClose();
	};
	
	this.infoCenterResponse = function(results, resultObject) {
		var leftString = results.substr(0,4);
		var infoCenterText = document.getElementById("infoCenterText");
		removeElements(infoCenterText)
		if(leftString == "http" || leftString == "HTTP") {
			var iframe = document.createElement("iframe");
			iframe.src = results;
			iframe.style.height = "inherit";
			iframe.style.width = "inherit";
			infoCenterText.appendChild(iframe);
		} else {
			resultObject.infoCenterText.innerHTML = results;
			resultObject.infoCenterText.scrollTop -= resultObject.infoCenterText.scrollHeight;
		}
	};

	this.infoCenterClose = function () {
		self.infoCenterPane.style.display = null;

	};

	this.infoCenterDisplay = function () {
		self.infoCenterPane.style.display = "block";
	};


	this.infoCenterMenu.onclick = function() {
		if(!self.infoCenterPane.style.display) {	
			self.infoCenterDisplay();		
		} else {
			self.infoCenterClose();		
		}
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


function drop(event) { 
    var offset = event.dataTransfer.getData("text/plain").split(',');
    dragObject.style.left = (event.clientX + parseInt(offset[0],10)) + 'px';
    dragObject.style.top = (event.clientY + parseInt(offset[1],10)) + 'px';
    dragObject.style.zIndex = event.target.zIndex + 10;
    event.preventDefault();
    return false;
} 


