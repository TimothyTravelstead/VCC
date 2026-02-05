// Global state
const userList = {};
let IMMonitorKeepAlive = null;
let globalIMMonitor = null; // Global reference for cleanup during logout

// Utility functions
const removeElements = (element) => {
    element.innerHTML = '';
};

const toTitleCase = (str) => {
    return str.replace(/\w\S*/g, (txt) => 
        txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase()
    );
};

// Drag and Drop Functionality
const dragAndDrop = {
    dragObject: null,
    
    handleDragStart(event) {
        dragAndDrop.dragObject = this;
        const style = window.getComputedStyle(event.target, null);
        event.dataTransfer.setData("text/plain",
            `${parseInt(style.getPropertyValue("left"), 10) - event.clientX},${parseInt(style.getPropertyValue("top"), 10) - event.clientY}`
        );
    },
    
    handleDragOver(event) {
        event.preventDefault();
        return false;
    },
    
    handleDrop(event) {
        const [offsetX, offsetY] = event.dataTransfer.getData("text/plain").split(',');
        dragAndDrop.dragObject.style.left = `${event.clientX + parseInt(offsetX, 10)}px`;
        dragAndDrop.dragObject.style.top = `${event.clientY + parseInt(offsetY, 10)}px`;
        dragAndDrop.dragObject.style.zIndex = event.target.zIndex + 10;
        event.preventDefault();
        return false;
    }
};

// IM Window Creation
function createImWindow(user) {
    const fragment = document.createDocumentFragment();
    const imPane = document.createElement("div");
    
    imPane.innerHTML = `
        <h2>${user.name}</h2>
        <div id="${user.userName}imBody" class="imBody"></div>
        <form id="${user.userName}imForm">
            <textarea 
                id="${user.userName}imMessage" 
                class="imMessage"
                onkeypress="if(event.key === 'Enter') { event.preventDefault(); user.postIM(user); }">
            </textarea>
            <input 
                type="button" 
                value="Close" 
                onclick="this.parentNode.parentNode.style.display='none'">
            <input 
                type="submit" 
                value="Post" 
                onclick="event.preventDefault(); user.postIM(user);">
        </form>
    `;
    
    imPane.id = `${user.userName}imPane`;
    imPane.className = "imPane";
    imPane.draggable = true;
    imPane.addEventListener('dragstart', dragAndDrop.handleDragStart);
    imPane.addEventListener('click', () => locateImPane(user));
    imPane.style.display = "none";
    
    fragment.appendChild(imPane);
    document.body.appendChild(fragment);
    
    return imPane;
}

// IM Monitor Class
class IMMonitor {
    constructor() {
        this.source = null;
        this.reconnectAttempts = 0;
        this.maxReconnectDelay = 30000; // Max 30 seconds between retries
        this.fallbackTimeout = 60000;   // Fallback reconnect after 60 seconds of no data
    }

    init() {
        if (typeof(EventSource) === "undefined") {
            document.getElementById("result").innerHTML =
                "Sorry, your browser does not support server-sent events...";
            return;
        }

        // CRITICAL: Close existing connection before creating new one
        // This prevents connection leak that causes exponential process growth
        if (this.source) {
            this.source.close();
        }

        // Clear any pending fallback timer
        if (IMMonitorKeepAlive) {
            clearTimeout(IMMonitorKeepAlive);
        }

        this.source = new EventSource("../vccFeed.php?reset=1");
        const source = this.source;

        // Handle successful connection
        source.addEventListener('open', () => {
            console.log("resourceAdmin EventSource: Connected");
            this.reconnectAttempts = 0; // Reset backoff on successful connection
        }, false);

        // Handle connection errors - use exponential backoff for retries
        source.addEventListener('error', (event) => {
            if (source.readyState === EventSource.CLOSED) {
                console.log("resourceAdmin EventSource: Connection closed, reconnecting with backoff...");
                this.reconnectWithBackoff();
            } else if (source.readyState === EventSource.CONNECTING) {
                console.log("resourceAdmin EventSource: Reconnecting...");
            }
        }, false);

        // Handle user list updates
        source.addEventListener('userList', (event) => {
            // Reset fallback timer - we're receiving data normally
            // This is a FALLBACK only, not the primary reconnection mechanism
            // EventSource handles normal reconnection automatically
            if (IMMonitorKeepAlive) clearTimeout(IMMonitorKeepAlive);
            IMMonitorKeepAlive = setTimeout(() => {
                console.log("resourceAdmin EventSource: Fallback reconnect (no data for 60s)");
                this.init();
            }, this.fallbackTimeout);

            const message = JSON.parse(event.data);
            const onlineUsers = {};

            message.forEach(userMessage => {
                onlineUsers[userMessage.UserName] = 1;
                let userRecord = userList[userMessage.UserName];

                if (!userRecord) {
                    userList[userMessage.UserName] = new User(userMessage);
                    userRecord = userList[userMessage.UserName];
                } else {
                    userRecord.updateUser(userMessage);
                }

                this.updateUserDisplay(userRecord);
            });

            this.cleanupOfflineUsers(onlineUsers);
        }, false);

        // Handle IM events
        source.addEventListener('IM', (event) => {
            const message = JSON.parse(event.data);
            userList[message.from].receiveIm(message);
        }, false);
    }

    // Reconnect with exponential backoff for error recovery
    reconnectWithBackoff() {
        if (this.source) {
            this.source.close();
        }
        if (IMMonitorKeepAlive) {
            clearTimeout(IMMonitorKeepAlive);
        }

        // Calculate backoff delay: 1s, 2s, 4s, 8s, 16s, 30s (max)
        const delay = Math.min(
            1000 * Math.pow(2, this.reconnectAttempts),
            this.maxReconnectDelay
        );
        this.reconnectAttempts++;

        console.log(`resourceAdmin EventSource: Reconnecting in ${delay/1000}s (attempt ${this.reconnectAttempts})`);

        setTimeout(() => {
            this.init();
        }, delay);
    }

    updateUserDisplay(userRecord) {
        const userDisplayed = document.getElementById(userRecord.userName);
        if (userDisplayed) {
            this.updateExistingUser(userDisplayed, userRecord);
        } else {
            this.createNewUserRow(userRecord);
        }
    }

    updateExistingUser(element, userRecord) {
        element.childNodes[1].innerHTML = this.getShiftDisplay(userRecord);
        element.childNodes[2].innerHTML = userRecord.adminRinging ? 'Ringing' : userRecord.onCall;
        element.childNodes[3].innerHTML = userRecord.chat;
    }

    getShiftDisplay(userRecord) {
        if (userRecord.AdminLoggedOn === 2 || userRecord.AdminLoggedOn === 7) {
            return 'n/a';
        } else if (userRecord.AdminLoggedOn === 3) {
            return 'Res.';
        }
        return userRecord.shift;
    }

    createNewUserRow(userRecord) {
        const userTable = document.getElementById('volunteerListTable');
        const tr = document.createElement("tr");
        tr.id = userRecord.userName;
        
        // Add user name cell
        const nameCell = this.createNameCell(userRecord);
        tr.appendChild(nameCell);
        
        // Add shift cell
        const shiftCell = document.createElement("td");
        shiftCell.textContent = this.getShiftDisplay(userRecord);
        tr.appendChild(shiftCell);
        
        // Add call status cell
        const callCell = document.createElement("td");
        callCell.textContent = userRecord.adminRinging || userRecord.onCall;
        tr.appendChild(callCell);
        
        // Add chat status cell
        const chatCell = document.createElement("td");
        chatCell.textContent = userRecord.chat;
        tr.appendChild(chatCell);
        
        // Add chat only indicator cell
        const chatOnlyCell = document.createElement("td");
        chatOnlyCell.textContent = userRecord.oneChatOnly ? "Yes" : "-";
        tr.appendChild(chatOnlyCell);
        
        // Add logoff button cell
        const logoffCell = this.createLogoffCell(userRecord);
        tr.appendChild(logoffCell);
        
        userTable.appendChild(tr);
    }

    createNameCell(userRecord) {
        const td = document.createElement("td");
        td.style.color = "rgb(6,69,173)";
        td.onclick = () => userRecord.loadImPane();
        td.title = "Click to Send an IM to this Volunteer.";
        td.className = "hover";
        td.textContent = userRecord.name;
        return td;
    }

    createLogoffCell(userRecord) {
        const td = document.createElement("td");
        const input = document.createElement("input");
        input.type = "button";
        input.className = "UserLogoffButton";
        input.value = "Log Off";
        input.id = `${userRecord.userName}LogoffButton`;
        input.name = userRecord.userName;
        input.onclick = () => LogoffUser(input.name);
        td.appendChild(input);
        return td;
    }

    cleanupOfflineUsers(onlineUsers) {
        const usersListed = document.getElementById("volunteerListTable").getElementsByTagName("tr");
        Array.from(usersListed).forEach(userRow => {
            const userID = userRow.getAttribute("id");
            if (!onlineUsers[userID] && userID !== "userListHeader") {
                userRow.remove();
                delete userList[userID];
            }
        });
    }
}

// User Class
class User {
    constructor(userData) {
        this.volunteerID = document.getElementById("volunteerID").value;
        this.userName = userData.UserName;
        this.name = `${userData.FirstName} ${userData.LastName}`;
        this.shift = userData.Shift;
        this.onCall = userData.OnCall;
        this.chat = userData.Chat;
        this.AdminLoggedOn = userData.AdminLoggedOn;
        this.currentUser = userData.currentUser;
        this.adminRinging = userData.adminRinging;
        this.im = [];
        
        if (this.adminRinging) {
            const phoneNumberStart = userData.adminRinging.indexOf("(");
            this.incomingNumber = userData.adminRinging.substr(phoneNumberStart, 14);
        }
        
        // Create IM window if it doesn't exist
        if (!this.imPane) {
            this.imPane = createImWindow(this);
            this.imBody = document.getElementById(`${this.userName}imBody`);
            this.imForm = document.getElementById(`${this.userName}imForm`);
            this.imMessage = document.getElementById(`${this.userName}imMessage`);
        }
    }
    
    closeImPane() {
        this.imPane.style.display = "none";
    }
    
    updateUser(userData) {
        this.shift = userData.Shift;
        this.onCall = userData.OnCall;
        this.chat = userData.Chat;
        this.adminRinging = userData.adminRinging;
        
        if (this.adminRinging) {
            const phoneNumberStart = this.adminRinging.indexOf("(");
            this.incomingNumber = "Not Available";
        }
    }
    
    loadImPane() {
        locateImPane(this);
        this.imBody.innerHTML = "";
        this.imMessage.value = "";
        this.imPane.style.display = "block";
        
        this.im.forEach(im => {
            const messageElement = document.createElement("p");
            Object.assign(messageElement.style, {
                backgroundColor: im.fromUser === this.volunteerID ? 
                    'rgba(150,150,250,.35)' : 'rgba(220,220,220,1)',
                padding: "4px",
                borderRadius: '12px',
                width: "200px",
                marginBottom: "-10px",
                marginLeft: im.fromUser === this.volunteerID ? "50px" : "0"
            });
            
            const time = im.time.toLocaleTimeString();
            messageElement.title = time;
            messageElement.textContent = im.message;
            
            this.imBody.appendChild(messageElement);
        });
        
        this.imBody.scrollTop = this.imBody.scrollHeight;
        this.imMessage.focus();
    }
    
    async postIM(user = this) {
        if (!user.imMessage.value.trim()) return;
        
        user.imPane.style.display = "none";
        
        const message = {
            text: user.imMessage.value,
            from: this.volunteerID,
            to: user.userName
        };
        
        if (user.userName === "All") {
            const imMessage = new ImMessage(message);
            Object.values(userList).forEach(recipient => {
                if (recipient.userName !== 'All' && !recipient.currentUser) {
                    recipient.im.push(imMessage);
                }
            });
        }
        
        try {
            await new AjaxRequest(
                '../volunteerPosts.php',
                `postType=postIM&action=${encodeURIComponent(user.userName)}&text=${encodeURIComponent(message.text)}`,
                testResults,
                {},
                "POST"
            );
        } catch (error) {
            console.error('Error posting IM:', error);
        }
    }
    
    async receiveIm(message) {
        const imMessage = new ImMessage(message);
        
        if (message.from === this.volunteerID) {
            userList[message.to].im[message.id] = imMessage;
            await this.updateMessageStatus(message.id, 'from');
        } else {
            const gabeIMSound = document.getElementById("IMSound");
            if (gabeIMSound && !userList[message.from].im[message.id]) {
                gabeIMSound.play();
            }
            userList[message.from].im[message.id] = imMessage;
            userList[message.from].loadImPane();
            await this.updateMessageStatus(message.id, 'to');
        }
    }
    
    async updateMessageStatus(messageId, direction) {
        try {
            await new AjaxRequest(
                "../volunteerPosts.php",
                `postType=IMReceived&action=${messageId}&text=${direction}`,
                testResults,
                {},
                "POST"
            );
        } catch (error) {
            console.error('Error updating message status:', error);
        }
    }
}

// Message Class
class ImMessage {
    constructor(message) {
        this.user = message.to;
        this.message = message.text;
        this.time = new Date();
        this.fromUser = message.from;
        this.id = message.id;
        this.toDelivered = message.toDelivered;
        this.fromDelivered = message.fromDelivered;
    }
}

// IM Window Positioning
function locateImPane(user) {
    Object.values(userList).forEach(otherUser => {
        if (otherUser.name !== user.name && otherUser.imPane.style.display === "block") {
            const otherStyle = window.getComputedStyle(otherUser.imPane);
            const userStyle = window.getComputedStyle(user.imPane);
            
            const otherX = parseInt(otherStyle.getPropertyValue("left"));
            const otherY = parseInt(otherStyle.getPropertyValue("top"));
            const otherZ = parseInt(otherStyle.getPropertyValue("z-index"));
            const userX = parseInt(userStyle.getPropertyValue("left"));
            const userY = parseInt(userStyle.getPropertyValue("top"));
            const userZ = parseInt(userStyle.getPropertyValue("z-index"));
            
            if (otherX === userX && otherY === userY) {
                user.imPane.style.left = `${userX + 50}px`;
                user.imPane.style.top = `${userY + 50}px`;
                user.imPane.style.zIndex = otherZ + 1;
                locateImPane(user);
            }
            
            if (otherZ >= userZ) {
                user.imPane.style.zIndex = otherZ + 1;
                locateImPane(user);
            }
        }
    });
}

// Results Testing
function testResults(results) {
    if (results) {
        console.warn('Server returned:', results);
        alert(results);
    }
}

// Page Initialization
window.addEventListener('load', () => {
    globalIMMonitor = new IMMonitor();
    globalIMMonitor.init();

    // CRITICAL: Close EventSource when page unloads to prevent orphaned PHP processes
    window.addEventListener('beforeunload', () => {
        if (globalIMMonitor && globalIMMonitor.source) {
            console.log("resourceAdmin: Closing EventSource on page unload");
            globalIMMonitor.source.close();
        }
        if (IMMonitorKeepAlive) {
            clearTimeout(IMMonitorKeepAlive);
        }
    });

    window.addEventListener('pagehide', () => {
        if (globalIMMonitor && globalIMMonitor.source) {
            console.log("resourceAdmin: Closing EventSource on pagehide");
            globalIMMonitor.source.close();
        }
    });

    const exitButton = document.getElementById("ExitButton");
    exitButton.onclick = Exit;

    // Set up drag and drop handlers
    document.addEventListener('dragover', dragAndDrop.handleDragOver);
    document.addEventListener('drop', dragAndDrop.handleDrop);
});

// Exit Handler
const Exit = async () => {
    // DEBUG: Log what's calling Exit()
    console.log("resourceAdmin.js Exit() called - Stack trace:");
    console.trace();

    // CRITICAL: Close EventSource BEFORE logout to prevent orphaned connections
    if (globalIMMonitor && globalIMMonitor.source) {
        console.log("resourceAdmin.js Exit(): Closing EventSource before logout");
        globalIMMonitor.source.close();
        globalIMMonitor.source = null;
    }
    if (IMMonitorKeepAlive) {
        clearTimeout(IMMonitorKeepAlive);
        IMMonitorKeepAlive = null;
    }

    const administrator = document.getElementById("AdministratorID").value;
    try {
        console.log("resourceAdmin.js calling ExitProgram.php with VolunteerID:", administrator);
        await new AjaxRequest(
            "ExitProgram.php",
            { VolunteerID: administrator },
            () => {
                console.log("resourceAdmin.js ExitProgram.php completed, redirecting...");
                window.location.assign("https://vcctest.org");
            },
            null,
            "GET"
        );
    } catch (error) {
        console.error('Error during exit:', error);
        alert('Error during exit. Please try again.');
    }
};
