function ImMessage(message) {
	this.user = 		message.to;
	this.message = 		unescapeHTML(message.text);
	this.time =			new Date();
	this.fromUser = 	message.from;
	this.id = 			message.id;
	this.toDelivered = 	message.toDelivered;
	this.fromDelivered = message.fromDelivered;
}





	this.createImWindow = function() {
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
		};

		input.setAttribute("id" , user.userName + "imClose");
		input.setAttribute("type","button");
		input.setAttribute("value" , "Close");
		input.onclick = function () {
			this.parentNode.parentNode.style.display='none';
		}

		input2.setAttribute("id" , user.userName + "imPost");
		input2.setAttribute("type" , "submit");
		input2.setAttribute("value" , "Post");
		input2.onclick = function () {
			user.postIM(user);
			return false;
		};


		form.setAttribute("id" , user.userName + "imForm");
		form.appendChild(textarea);
		form.appendChild(input);
		form.appendChild(input2);

		div.setAttribute("id" , user.userName + "imBody");
		div.setAttribute("class" , "imBody");

		div2.setAttribute("id" , user.userName + "imPane");
		div2.setAttribute("class" , "imPane");
		div2.setAttribute("draggable" , "true");
		div2.addEventListener('dragstart',drag_start,false); 
		div2.onclick = function () {locateImPane(user);}; 

		div2.style.display = "none";
		div2.appendChild(h2);
		div2.appendChild(div);
		div2.appendChild(form);
		frag.appendChild(div2);
		document.getElementsByTagName("body")[0].appendChild(frag);
		self.imPane = div2;
		self.imBody = div;
		self.imForm = form;
		self.imMessage = textArea;

	};



	this.closeImPane = function (user) {
		self.imWindowdocument.getElementById(self.userName + "imPane").style.display = "none";
	};

	
	
	
	this.loadImPane = function () {
		if(!self.imPane) {
			self.createImWindow();
		}

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
	
		var url = 'groupChatPostIM.php';
		var resultsObject = new Object();
		var postResults = function (results, searchObject) {
			postMessageResult(results, searchObject);
		};
		
		imTo, $userID, $Text
		
		params = "imTo=" + escape(self.userID) + "&userID=" + escape(users[currentUser].userID) + "&Text=" + imMessageText;
		var postIM = new AjaxRequest(url, params, postResults, resultsObject);
		return false;
	};

	this.receiveIm = function (message) {
		if(message.from == user[currentUser].userID) {
			var imMessage = new ImMessage(message);
			users[message.to].im[message.id] = imMessage;
//			var url = "groupChatPostIMReceived.php";
//			var	params = "postType=IMReceived&action=" + message.id + "&text=from";
//			var updateMessageStatusResults = function (results, searchObject) {
//				postMessageResult(results, searchObject);
//			};
			var updateMessageStatus = new AjaxRequest(url, params, updateMessageStatusResults);
		} else if (message.to == user[currentUser].userID) {
			var imMessage = new ImMessage(message);
			users[message.from].im[message.id] = imMessage;
			users[message.from].loadImPane();
//			var url = "volunteerPosts.php";
//			var	params = "postType=IMReceived&action=" + message.id + "&text=to";
//			var updateMessageStatusResults = function (results, searchObject) {
//				testResults(results, searchObject);
//			};
//			var updateMessageStatus = new AjaxRequest(url, params, updateMessageStatusResults);
		}
	};
		