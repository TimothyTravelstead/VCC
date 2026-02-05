// Core variables
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

// Utility functions
function objectSize(obj) {
    return Object.keys(obj).length;
}

// Initialization
window.onload = function() {
    preInitialize();
}

function preInitialize() {
    myEditor = new nicEditor({
        buttonList : ['bold','italic','underline','left',
        'center','right','justify','fontSize','fontFamily','indent','outdent',
        'forecolor'],
        externalCSS : 'nicEditPanel.css',
        iconsPath: '/GroupChat/nicEdit/nicEditorIcons.gif'
    }).panelInstance('groupChatTypingWindow');
            
    const currentUserElement = document.getElementById("userID");
    currentUser = currentUserElement.value;
    currentUser = currentUser.trim();
    userNames = JSON.parse(document.getElementById("userNames").value);
    initPage();
}

// Text editing utilities
function getCaretCharacterOffsetWithin(element) {
    let caretOffset = 0;
    const doc = element.ownerDocument || element.document;
    const win = doc.defaultView || doc.parentWindow;
    let sel;
    
    if (typeof win.getSelection != "undefined") {
        sel = win.getSelection();
        if (sel.rangeCount > 0) {
            const range = win.getSelection().getRangeAt(0);
            const preCaretRange = range.cloneRange();
            preCaretRange.selectNodeContents(element);
            preCaretRange.setEnd(range.endContainer, range.endOffset);
            caretOffset = preCaretRange.toString().length;
        }
    } else if ((sel = doc.selection) && sel.type != "Control") {
        const textRange = sel.createRange();
        const preCaretTextRange = doc.body.createTextRange();
        preCaretTextRange.moveToElementText(element);
        preCaretTextRange.setEndPoint("EndToEnd", textRange);
        caretOffset = preCaretTextRange.text.length;
    }
    return caretOffset;
}

function getSelectionHtml() {
    let html = "";
    if (typeof window.getSelection != "undefined") {
        const sel = window.getSelection();
        if (sel.rangeCount) {
            const container = document.createElement("div");
            for (let i = 0; i < sel.rangeCount; ++i) {
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

function setEndOfContenteditable(contentEditableElement) {
    stripUnwantedAttributes(contentEditableElement);

    let range, selection;
    if (document.createRange) {
        range = document.createRange();
        range.selectNodeContents(contentEditableElement);
        range.collapse(false);
        selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
    } else if (document.selection) {
        range = document.body.createTextRange();
        range.moveToElementText(contentEditableElement);
        range.collapse(false);
        range.select();
    }
}

// Page initialization
function initPage() {
    const body = document.getElementsByTagName("body")[0];
    const userID = document.getElementById("userID").value;

    // Paste handler
    document.addEventListener("paste", function(e) {
        e.preventDefault();
        const text = e.clipboardData.getData("text/plain");
        document.execCommand("insertHTML", false, text);
    });
   
    // Event handlers setup
    setupEventHandlers();

    // Initialize chat monitor
    newChat = new GroupChatMonitor("testing");
    newChat.init();    	   

    users = document.getElementById('assignedTraineeIDs').value.split(',');    
    
}

function setupEventHandlers() {
    const sendButton = document.getElementById("groupChatSubmitButton");
    const textArea = document.getElementById("groupChatTypingWindow");
    textArea.focus();
    
    sendButton.onclick = () => PostMessage();
    
    setupTextAreaHandlers(textArea);
}

function setupTextAreaHandlers(textArea) {
    textArea.onfocus = function() {
        textArea.onkeypress = function(e) {
            const code = e.keyCode || e.which;
            if(code === 13 || code === 10) {
                PostMessage(null);
                return false;
            }
        };
    };
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


// Message posting
function PostMessage(message) {
    // Get the folder path of the current script
    const baseUrl = `${window.location.origin}${window.location.pathname}`;
    const folderPath = baseUrl.substring(0, baseUrl.lastIndexOf("/") + 1); // Extract the folder path
    const apiUrl = folderPath + "CallerPostMessage.php"; // Construct the full URL for the PHP file

    const chatRoomID = document.getElementById("groupChatRoomID").value;

    if (!message) {
        message = {
            userID: document.getElementById("userID").value.trim(),
            messageID: "0",
            text: "",
            highlightMessage: false,
            deleteMessage: false
        };

        const messageElement = document.getElementById("groupChatTypingWindow");
        removeUnwantedElements(messageElement);
        message.text = encodeURIComponent(messageElement.innerHTML.trim());

        if (!message.text.length) {
            return; // Exit if no text to send
        }
    }

    if (message.text.trim()) {
        // Properly escape single quotes and other characters
        const updatedString = message.text.replace(/('[a-zA-Z0-9\s]+\s*)'(\s*[a-zA-Z0-9\s]+')/g, "$1\\\'$2");

        // Use the dynamically constructed URL
        new AjaxRequest(
            apiUrl,
            {
                userID: message.userID,
                Text: updatedString,
                messageID: message.messageID,
                highlightMessage: message.highlightMessage,
                deleteMessage: message.deleteMessage,
                chatRoomID: chatRoomID
            },
            postMessageResult // Pass the function reference, not a call
        );
    }

    const typingWindow = document.getElementById("groupChatTypingWindow");
    if (typingWindow) {
        typingWindow.innerHTML = ""; // Clear the input window
        setEndOfContenteditable(typingWindow); // Reset focus
    }
}



function postMessageResult(results, resultObject) {
    const trimmedResult = results.trim();
    if (trimmedResult === "OK") {
        return true;
    }
}
   

// Message class
class Message {
    constructor(message) {
        this.userID = message.userID;
		if(this.userID == currentUser) {
			this.name = "Me";
		} else {
			this.name = userNames[this.userID];
		}
        this.messageID = message.id;
        this.text = decodeURIComponent(message.message);
        this.time = message.created;
        this.messageBlock = false;
        this.messageClass = false;
        this.deleteMessage = message.deleteMessage;
        this.highlightMessage = message.highlightMessage;
        this.window = document.getElementById("groupChatMainWindow");
        this.displayed = false;
    }

    postUpdateToMessage() {
        PostMessage(this);
    }
    
    createMessageContainer() {
      var container = document.createElement("div");
      container.setAttribute("class","groupChatMessageContainer");
      container.id = "groupChatMessageID" + this.messageID;
      return container;
    }

    updateMessage(message) {
        this.deleteMessage = message.deleteMessage;
        this.highlightMessage = message.highlightMessage;
        
        if(message.deleteMessage) {
            return;
        } else {
            return;
        }
    }
    
    appendMessage(container) {
		const nameBlock = document.createElement("div");
		nameBlock.setAttribute("class","nameBlock");
		if(this.name != "Me") {
			nameBlock.appendChild(document.createTextNode(decodeURIComponent(this.name)));
			nameBlock.style.marginLeft = "5px";
		}

		var date = new Date();
		var interimTime = date.createFromMysql(this.time);
		var timeBlock = document.createElement("span");
		timeBlock.appendChild(document.createTextNode(interimTime.toLocaleTimeString()));
		timeBlock.setAttribute("class" , "timeStamp");
		nameBlock.appendChild(timeBlock);			

		var messageText = document.createElement("div");
		messageText.setAttribute("class","groupChatWindowMessage");
		messageText.innerHTML = this.text
		messageText.title = interimTime.toLocaleTimeString();
		if(this.name != "Me") {
			messageText.style.marginLeft = "5px";
			messageText.style.backgroundColor = "#FDDCE2";
		}
		container.appendChild(nameBlock);
    	container.appendChild(messageText);
    	
    	return container;
    }

    display() {
        if(this.messageBlock || this.deleteMessage) {
            this.displayed = this.messageBlock ? true : false;
            return;
        }

        const container = this.createMessageContainer();        
        this.appendMessage(container);        
        this.window.appendChild(container);
    
        this.messageBlock = document.getElementById("groupChatMessageID" + this.messageID);
        this.messageClass = container.getAttribute("class");
        
        this.window.scrollTop += this.window.scrollHeight + 10; 
        
        this.displayed = true;           
    }
}

// User class
class User {
    constructor(message) {
        this.name = decodeURIComponent(message.name);
        this.userID = message.userID;
        this.avatarID = message.avatarID;
        this.signedOn = true;
        this.status = message.action === 'signon' ? 1 : 0;
        this.messages = [];
        this.moderator = message.moderator;
        this.chatRoomID = message.chatRoomID;
        this.userNameBlock = "";
        this.groupChatTypingWrapper = document.getElementById("groupChatTypingWrapper");
        this.im = [];
        this.sendToChat = false;
    }

    updateUser(message) {
        this.sendToChat = message.sendToChat;
        this.name = message.name;
        this.userID = message.userID;
        this.avatarID = message.avatarID;
        this.moderator = message.moderator;
        this.chatRoomID = message.chatRoomID;

        this.handleUserUpdate(message);
    }
}

// Chat monitor
class GroupChatMonitor {
    constructor(name) {
        this.loggedOnModerators = 0;
        this.source = "";
        this.numberOfUsers = 0;
        this.chatRoomID = document.getElementById("trainerID").value;
        this.userID = document.getElementById("userID").value;
        this.lastEventId = 0;
    }

    init() {
        if(typeof(EventSource) !== "undefined") {
            this.source = new EventSource(`chatFeed2.php?chatRoomID=${this.chatRoomID}&lastEventId=${this.lastEventId}`);
            this.setupEventListeners();
        } else {
            this.handleUnsupportedBrowser();
        }
    }

    setupEventListeners() {
        this.source.addEventListener('chatMessage', (event) => {
            this.lastEventId = event.lastEventId || this.lastEventId;
            const message = JSON.parse(event.data);
            
            switch(message.type) {
                case "User":
                    this.handleUserEvent(message);
                    break;
                    
                case "message":
                case "toOneOnOneChat":
                    this.handleChatMessage(message);
                    break;
                    
                case "roomStatus":
                    this.handleRoomStatus(message);
                    break;
                    
                case "IM":
                    this.handleInstantMessage(message);
                    break;
                    
                case "timeout":
                    this.handleTimeout(message);
                    break;
            }
            
            if(message.numberOfLoggedOnUsers) {
                this.numberOfUsers = message.numberOfLoggedOnUsers;
                this.updateUserCount();
            }
        });

        this.source.addEventListener('heartbeat', () => {
            console.log("Heartbeat received: " + new Date().toISOString());
        });

        this.source.onerror = (error) => {
            console.error("EventSource failed:", error);
            this.source.close();
            setTimeout(() => {
                this.init();
            }, 5000);
        };
    }

    handleUserEvent(message) {
        if(message.action === "signon" || message.action === "signoff") {
            if(!users[message.userID]) {
                users[message.userID] = new User(message);
            } else {
                users[message.userID].updateUser(message);
            }
            
            if(message.moderator) {
                this.loggedOnModerators += (message.action === "signon") ? 1 : -1;
            }
        }
    }

    handleChatMessage(data) {
        if(messages[data.id]) {
            messages[data.id].updateMessage(data);
        } else {
            let message = new Message(data);
            messages[message.messageID] = message;		
            message.display();
        }
    }

    handleRoomStatus(message) {
        const statusWindow = document.getElementById("groupChatStatusWindow");
        if(statusWindow) {
            statusWindow.innerHTML = decodeURIComponent(message.message);
            isRoomOpen = (message.roomStatus === "1");
        }
    }

    handleInstantMessage(message) {
        if(message.imTo === this.userID || message.imFrom === this.userID) {
            const otherUser = (message.imTo === this.userID) ? message.imFrom : message.imTo;
            
            if(!users[otherUser]) {
                users[otherUser] = new User(message);
            }
            
            if(!users[otherUser].im[message.id]) {
                users[otherUser].im[message.id] = new InstantMessage(message);
                users[otherUser].im[message.id].display();
            }
        }
    }

    handleTimeout(message) {
        if(message.userID === this.userID) {
            alert(`You have been timed out for ${message.minutes} minutes`);
            window.location.href = "logout.php";
        }
    }

    updateUserCount() {
        const countElement = document.getElementById("numberOfUsers");
        if(countElement) {
            countElement.innerHTML = this.numberOfUsers;
        }
    }

    handleUnsupportedBrowser() {
        console.error("Your browser doesn't support Server-Sent Events");
        alert("Your browser doesn't support Server-Sent Events. Please use a modern browser.");
    }
}
