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
    adjustChatHeight();
    window.addEventListener('resize', adjustChatHeight);
}

// Dynamically adjust chat height to fit screen without scrolling
function adjustChatHeight() {
    const chatWindow = document.getElementById('groupChatMainWindow');
    if (!chatWindow) return;
    
    // Get viewport height
    const viewportHeight = window.innerHeight;
    
    // Get elements that take up vertical space
    const typingWrapper = document.getElementById('groupChatTypingWrapper');
    const typingWrapperHeight = typingWrapper ? typingWrapper.offsetHeight : 120;
    
    // Account for Mac Dock and browser chrome
    // Use a more conservative calculation to ensure no scrolling
    // Typical Mac Dock is ~70px, browser UI ~40px, add safety margin
    const systemUIBuffer = 110; // Buffer for dock, browser UI, and safety
    
    // Calculate available height more conservatively
    const availableHeight = viewportHeight - typingWrapperHeight - systemUIBuffer;
    
    // Ensure minimum height for usability
    const minHeight = 200;
    const finalHeight = Math.max(availableHeight, minHeight);
    
    // Set the height to prevent scrolling
    chatWindow.style.maxHeight = finalHeight + 'px';
    chatWindow.style.height = finalHeight + 'px';
    
    // Also adjust overflow to ensure scrolling within the chat window
    chatWindow.style.overflowY = 'auto';
}

function preInitialize() {
    // Modern editor initialization - simplified for better UX
    const typingWindow = document.getElementById("groupChatTypingWindow");
    if (typingWindow) {
        // Set up modern contenteditable behavior
        setupModernEditor(typingWindow);
    }
            
    const currentUserElement = document.getElementById("userID");
    currentUser = currentUserElement.value;
    currentUser = currentUser.trim();
    
    const userNamesElement = document.getElementById("userNames");
    if (userNamesElement && userNamesElement.value) {
        userNames = JSON.parse(userNamesElement.value);
    } else {
        console.warn("userNames element not found or empty, using default");
        userNames = {};
    }
    
    initPage();
}

// Modern editor setup
function setupModernEditor(element) {
    // Handle placeholder functionality
    const placeholder = element.getAttribute('data-placeholder') || 'Type your message...';
    
    // Initial placeholder setup
    if (!element.textContent.trim()) {
        element.classList.add('empty');
    }
    
    // Placeholder management
    element.addEventListener('focus', function() {
        this.classList.remove('empty');
    });
    
    element.addEventListener('blur', function() {
        if (!this.textContent.trim()) {
            this.classList.add('empty');
        }
    });
    
    // Prevent default paste behavior and clean content
    element.addEventListener('paste', function(e) {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text/plain');
        document.execCommand('insertText', false, text);
    });
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

    // Modern paste handler
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
    
    if (textArea) {
        textArea.focus();
        setupTextAreaHandlers(textArea);
    }
    
    if (sendButton) {
        sendButton.onclick = () => PostMessage();
    }
}

function setupTextAreaHandlers(textArea) {
    // Handle Enter key for sending messages
    textArea.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            PostMessage();
        }
    });
    
    // Auto-resize functionality
    textArea.addEventListener('input', function() {
        // Reset height to auto to get the correct scrollHeight
        this.style.height = 'auto';
        // Set height based on content, with max height of 120px
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        
        // Handle empty state
        if (!this.textContent.trim()) {
            this.classList.add('empty');
        } else {
            this.classList.remove('empty');
        }
    });
    
    // Focus and blur handlers for placeholder
    textArea.addEventListener('focus', function() {
        this.classList.remove('empty');
    });
    
    textArea.addEventListener('blur', function() {
        if (!this.textContent.trim()) {
            this.classList.add('empty');
        }
    });
}

function stripUnwantedAttributes(node) {
    if (!node) return;
    
    if (node.src) {
        node.removeAttribute("src");
    }
    
    if (node.href) {
        node.removeAttribute("href");
    }

    if (node.target) {
        node.removeAttribute("target");
    }

    if (node.bottom) {
        node.removeAttribute("bottom");
    }

    if (node.backgroundImage) {
        node.removeAttribute("background-image");
    }

    if (node.tagName) {
        switch (node.tagName) {
            case "IMG":
            case "VIDEO":
            case "AUDIO":
            case "TEXTAREA":
            case "INPUT":
            case "BR":
                if (node.parentNode) {
                    node.parentNode.removeChild(node);
                }
                break;
            case "STYLE":
            case "SCRIPT":
                if (node.parentNode) {
                    node.parentNode.removeChild(node);
                }
                break;
            default:
                if (node.style) {
                    node.style.background = "";
                    node.setAttribute("background-image", "");
                }
                break;
        }
    }
}

function removeUnwantedElements(messageNodeList) {
    if (!messageNodeList) return;
    
    const iterator = document.createNodeIterator(
        messageNodeList,
        NodeFilter.SHOW_ELEMENT,
        null,
        false
    );

    let node = iterator.nextNode();
    const nodesToProcess = [];
    
    // Collect nodes first to avoid modifying while iterating
    while (node) {
        nodesToProcess.push(node);
        node = iterator.nextNode();
    }
    
    // Process collected nodes
    nodesToProcess.forEach(stripUnwantedAttributes);
}

// Enhanced AjaxRequest wrapper for better error handling
function enhancedAjaxRequest(url, params, callback) {
    const xhr = new XMLHttpRequest();
    
    // Convert params object to form data
    const formData = new FormData();
    for (const key in params) {
        formData.append(key, params[key]);
    }
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                callback(xhr.responseText, null);
            } else {
                callback(null, {
                    error: `HTTP ${xhr.status}: ${xhr.statusText}`,
                    status: xhr.status
                });
            }
        }
    };
    
    xhr.onerror = function() {
        callback(null, {
            error: "Network error occurred",
            status: 0
        });
    };
    
    xhr.open('POST', url, true);
    xhr.send(formData);
}

// Enhanced message posting with modern UI feedback
function PostMessage(message) {
    const baseUrl = `${window.location.origin}${window.location.pathname}`;
    const folderPath = baseUrl.substring(0, baseUrl.lastIndexOf("/") + 1);
    const apiUrl = folderPath + "CallerPostMessage.php";
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
        if (messageElement) {
            removeUnwantedElements(messageElement);
            message.text = encodeURIComponent(messageElement.textContent.trim());

            if (!message.text.length) {
                return; // Exit if no text to send
            }
        }
    }

    if (message.text.trim()) {
        // Show loading state
        const submitButton = document.getElementById("groupChatSubmitButton");
        const originalText = submitButton.textContent;
        submitButton.textContent = "Sending...";
        submitButton.disabled = true;
        submitButton.classList.add('loading');

        // Prepare parameters with debug flag for development
        const params = {
            userID: message.userID,
            Text: message.text,
            messageID: message.messageID,
            highlightMessage: message.highlightMessage ? 'true' : 'false',
            deleteMessage: message.deleteMessage ? 'true' : 'false',
            chatRoomID: chatRoomID
        };
        
        // Add debug parameter if in development mode
        if (window.location.hostname === 'localhost' || window.location.search.includes('debug=1')) {
            params.debug = '1';
        }

        // Use enhanced AJAX if available, fallback to original AjaxRequest
        const ajaxFunction = typeof enhancedAjaxRequest !== 'undefined' ? enhancedAjaxRequest : 
            function(url, params, callback) {
                new AjaxRequest(url, params, callback);
            };

        ajaxFunction(apiUrl, params, function(response, error) {
            // Reset button state
            submitButton.textContent = originalText;
            submitButton.disabled = false;
            submitButton.classList.remove('loading');
            
            if (error) {
                console.error("Network error:", error);
                showNotification("Network error. Please check your connection.", "error");
                return;
            }
            
            const success = postMessageResult(response, null);
            if (success) {
                // Clear and reset the input only on success
                const typingWindow = document.getElementById("groupChatTypingWindow");
                if (typingWindow) {
                    typingWindow.textContent = "";
                    typingWindow.style.height = 'auto';
                    typingWindow.classList.add('empty');
                    setEndOfContenteditable(typingWindow);
                }
            }
        });
    }
}

// Enhanced postMessageResult function with better debugging
function postMessageResult(results, resultObject) {
    try {
        // Handle null or undefined results
        if (!results) {
            console.error("No response received from server");
            showNotification("No response from server. Please try again.", "error");
            return false;
        }

        // Handle string responses
        if (typeof results === 'string') {
            const trimmedResult = results.trim();
            
            // Check for simple "OK" response (success)
            if (trimmedResult === "OK") {
                return true;
            }
            
            // Try to parse as JSON
            let response;
            try {
                response = JSON.parse(trimmedResult);
            } catch (e) {
                // If it's not JSON and not "OK", it's likely an error
                console.error("Unexpected response format:", trimmedResult);
                showNotification("Server returned unexpected response. Please try again.", "error");
                return false;
            }
            
            return handleJsonResponse(response);
        } 
        
        // Handle object responses
        if (typeof results === 'object') {
            return handleJsonResponse(results);
        }
        
        // Unexpected response type
        console.error("Unexpected response type:", typeof results, results);
        showNotification("Unexpected server response. Please try again.", "error");
        return false;
        
    } catch (error) {
        console.error("Error processing message result:", error);
        showNotification("Failed to process server response. Please try again.", "error");
        return false;
    }
}

// Helper function to handle JSON responses
function handleJsonResponse(response) {
    // Check for explicit error
    if (response.error) {
        console.error("Server error:", response.error);
        
        // Show user-friendly error message
        let errorMessage = "Message send failed.";
        if (response.error.includes("ChatRoom")) {
            errorMessage = "Chat room error. Please refresh the page.";
        } else if (response.error.includes("UserID")) {
            errorMessage = "User authentication error. Please log in again.";
        } else if (response.error.includes("database") || response.error.includes("Database")) {
            errorMessage = "Database error. Please try again.";
        }
        
        showNotification(errorMessage, "error");
        
        // Log detailed error for debugging
        if (response.sql_debug || response.last_error) {
            console.group("Detailed Error Information:");
            if (response.sql_debug) console.log("SQL:", response.sql_debug);
            if (response.last_error) console.log("Database Error:", response.last_error);
            if (response.steps) console.log("Steps:", response.steps);
            console.groupEnd();
        }
        
        return false;
    }
    
    // Check if response has steps array (your debug format)
    if (response.steps && Array.isArray(response.steps)) {
        // Check if all steps were successful
        const allStepsSuccessful = response.steps.every(step => step.success === true);
        
        if (allStepsSuccessful) {
            // Success - all steps completed without errors
            console.log("Message sent successfully:", response);
            return true;
        } else {
            // Some steps failed
            const failedSteps = response.steps.filter(step => step.success === false);
            console.error("Some message processing steps failed:", failedSteps);
            
            showNotification("Message processing failed. Please try again.", "error");
            return false;
        }
    }
    
    // Check for explicit success flag
    if (response.success === true) {
        console.log("Message sent successfully:", response);
        return true;
    }
    
    // If we get here, it's an unexpected response format
    console.warn("Unexpected response structure:", response);
    
    // If there's no error and no explicit failure, assume success
    // This is a fallback for responses that don't match expected format
    return true;
}

// Enhanced notification system with better UX
function showNotification(message, type = "info", duration = 3000) {
    // Remove existing notifications of the same type
    const existingNotifications = document.querySelectorAll(`.notification-${type}`);
    existingNotifications.forEach(notification => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    });
    
    const notification = document.createElement("div");
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Style the notification with your red theme
    const styles = {
        info: { bg: '#006600', border: '#004400' },
        error: { bg: '#cc0000', border: '#990000' },
        warning: { bg: '#cc6600', border: '#994d00' },
        success: { bg: '#006600', border: '#004400' }
    };
    
    const style = styles[type] || styles.info;
    
    Object.assign(notification.style, {
        position: 'fixed',
        top: '20px',
        right: '20px',
        padding: '12px 16px',
        borderRadius: '8px',
        border: `2px solid ${style.border}`,
        color: 'white',
        backgroundColor: style.bg,
        fontFamily: '"Times New Roman", Georgia, serif',
        fontSize: '14px',
        fontWeight: 'bold',
        zIndex: '10000',
        boxShadow: '0 4px 6px -1px rgba(85, 0, 0, 0.2)',
        transform: 'translateX(100%)',
        transition: 'transform 0.3s ease',
        maxWidth: '300px',
        wordWrap: 'break-word'
    });
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Remove after delay
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, duration);
}

// Enhanced Message class with iOS Messages styling
class Message {
    constructor(message) {
        this.userID = message.userID;
        if (this.userID == currentUser) {
            this.name = "You";
            this.isOwnMessage = true;
        } else {
            // Try to get name from multiple sources
            // 1. From userNames array (populated from PHP)
            // 2. From the message itself if it contains a name field
            // 3. From users array if user exists there
            this.name = userNames[this.userID] || 
                       (message.name ? decodeURIComponent(message.name) : null) ||
                       (users[this.userID] ? users[this.userID].name : null) ||
                       this.userID || 
                       "Unknown User";
            this.isOwnMessage = false;
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
        const container = document.createElement("div");
        container.className = "groupChatMessageContainer";
        
        // Add iOS-style message alignment classes
        if (this.isOwnMessage) {
            container.classList.add("own-message");
        } else {
            container.classList.add("other-message");
        }
        
        container.id = "groupChatMessageID" + this.messageID;
        
        // Check if this message is from the same user as the previous message
        const previousMessage = this.getPreviousMessage();
        if (previousMessage && previousMessage.userID === this.userID) {
            container.setAttribute("data-same-user", "true");
        }
        
        return container;
    }

    getPreviousMessage() {
        const allContainers = Array.from(this.window.querySelectorAll('.groupChatMessageContainer'));
        const currentIndex = allContainers.length; // This will be the next index
        if (currentIndex > 0) {
            const previousContainer = allContainers[currentIndex - 1];
            const previousId = previousContainer.id.replace('groupChatMessageID', '');
            return messages[previousId];
        }
        return null;
    }

    updateMessage(message) {
        this.deleteMessage = message.deleteMessage;
        this.highlightMessage = message.highlightMessage;
        
        if (message.deleteMessage) {
            // Handle message deletion with iOS-style animation
            if (this.messageBlock) {
                this.messageBlock.style.opacity = '0';
                this.messageBlock.style.transform = 'scale(0.8)';
                setTimeout(() => {
                    if (this.messageBlock && this.messageBlock.parentNode) {
                        this.messageBlock.parentNode.removeChild(this.messageBlock);
                    }
                }, 200);
            }
            return;
        }
        
        if (message.highlightMessage !== this.highlightMessage) {
            const messageElement = this.messageBlock?.querySelector('.groupChatWindowMessage');
            if (messageElement) {
                if (message.highlightMessage) {
                    messageElement.classList.add('highlightMessage');
                } else {
                    messageElement.classList.remove('highlightMessage');
                }
            }
        }
    }
    
    appendMessage(container) {
        // Only show name for other users and when it's not a consecutive message
        const showName = !this.isOwnMessage && !container.hasAttribute("data-same-user");
        
        if (showName) {
            const nameBlock = document.createElement("div");
            nameBlock.className = "nameBlock";
            nameBlock.textContent = this.name;
            container.appendChild(nameBlock);
        }

        // Create iOS-style message bubble
        const messageText = document.createElement("div");
        messageText.className = "groupChatWindowMessage";
        messageText.innerHTML = this.text;
        
        // Add timestamp (iOS style - usually hidden unless tapped/shown)
        const timeBlock = document.createElement("span");
        timeBlock.className = "timeStamp";
        const date = new Date();
        const interimTime = date.createFromMysql(this.time);
        timeBlock.textContent = interimTime.toLocaleTimeString([], {
            hour: 'numeric',
            minute: '2-digit'
        });
        
        // For iOS style, timestamp goes in the name block for others, or create a subtle one
        if (showName) {
            const nameBlock = container.querySelector('.nameBlock');
            nameBlock.appendChild(timeBlock);
        } else {
            // For consecutive messages or own messages, add timestamp on hover/tap
            messageText.title = interimTime.toLocaleTimeString();
        }
        
        // Add highlight class if needed
        if (this.highlightMessage) {
            messageText.classList.add("highlightMessage");
        }
        
        container.appendChild(messageText);
        
        return container;
    }

    display() {
        if (this.messageBlock || this.deleteMessage) {
            this.displayed = this.messageBlock ? true : false;
            return;
        }

        const container = this.createMessageContainer();        
        this.appendMessage(container);        
        this.window.appendChild(container);
    
        this.messageBlock = document.getElementById("groupChatMessageID" + this.messageID);
        this.messageClass = container.getAttribute("class");
        
        // iOS-style smooth scroll to bottom
        this.window.scrollTo({
            top: this.window.scrollHeight,
            behavior: 'smooth'
        });
        
        this.displayed = true;           
    }
}
// Enhanced User class
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

    handleUserUpdate(message) {
        // Update user status in UI
        this.updateStatusIndicator();
    }

    updateStatusIndicator() {
        // Update online status indicator if it exists
        const statusIndicator = document.querySelector('.status-indicator');
        if (statusIndicator && this.status) {
            statusIndicator.style.backgroundColor = this.status === 1 ? '#006600' : '#cc0000';
        }
    }
}

// Enhanced Chat monitor with modern features
class GroupChatMonitor {
    constructor(name) {
        this.loggedOnModerators = 0;
        this.source = "";
        this.numberOfUsers = 0;
        this.chatRoomID = document.getElementById("groupChatRoomID").value;
        this.userID = document.getElementById("userID").value;
        this.lastEventId = 0;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000;
        this.roomCheckAttempts = 0;
        this.maxRoomCheckAttempts = 20; // 20 attempts = ~2 minutes
    }

    init() {
        if (typeof(EventSource) !== "undefined") {
            if (this.chatRoomID === "0" || !this.chatRoomID) {
                // Room not found initially, start retry mechanism
                this.checkForRoom();
            } else {
                this.connect();
            }
        } else {
            this.handleUnsupportedBrowser();
        }
    }

    checkForRoom() {
        const statusDiv = document.getElementById('connectionStatus');
        const statusText = document.getElementById('statusText');
        
        if (this.roomCheckAttempts === 0) {
            statusDiv.style.display = 'block';
            statusText.textContent = 'Waiting for trainer to start session...';
        }
        
        this.roomCheckAttempts++;
        
        if (this.roomCheckAttempts <= this.maxRoomCheckAttempts) {
            console.log(`Checking for training room... attempt ${this.roomCheckAttempts}/${this.maxRoomCheckAttempts}`);
            statusText.textContent = `Connecting... (${this.roomCheckAttempts}/${this.maxRoomCheckAttempts})`;
            
            // Check if room exists now
            fetch('checkTrainingRoom.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `trainer=${encodeURIComponent(document.getElementById('trainerID').value)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.chatRoomID) {
                    console.log(`Training room found! ID: ${data.chatRoomID}`);
                    statusText.textContent = 'Connected to training room!';
                    setTimeout(() => statusDiv.style.display = 'none', 2000);
                    
                    this.chatRoomID = data.chatRoomID;
                    document.getElementById('groupChatRoomID').value = data.chatRoomID;
                    this.connect();
                } else {
                    // Try again with exponential backoff
                    const delay = Math.min(6000, 2000 * Math.pow(1.5, this.roomCheckAttempts - 1));
                    setTimeout(this.checkForRoom.bind(this), delay);
                }
            })
            .catch(error => {
                console.error('Room check failed:', error);
                setTimeout(this.checkForRoom.bind(this), 5000);
            });
        } else {
            statusText.textContent = 'Trainer not available - please try again later';
            statusDiv.style.background = '#ff6666';
            showNotification("Training room not available. The trainer may not be online yet.", "warning");
        }
    }

    connect() {
        try {
            this.source = new EventSource(`chatFeed2.php?chatRoomID=${this.chatRoomID}&lastEventId=${this.lastEventId}`);
            this.setupEventListeners();
            this.reconnectAttempts = 0;
        } catch (error) {
            console.error("Failed to connect to chat:", error);
            this.handleReconnection();
        }
    }

    setupEventListeners() {
        this.source.addEventListener('chatMessage', (event) => {
            this.lastEventId = event.lastEventId || this.lastEventId;
            const message = JSON.parse(event.data);
            
            this.updateConnectionStatus(true);
            
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
            
            if (message.numberOfLoggedOnUsers) {
                this.numberOfUsers = message.numberOfLoggedOnUsers;
                this.updateUserCount();
            }
        });

        this.source.addEventListener('open', () => {
            console.log("Chat connection established");
            this.updateConnectionStatus(true);
            this.reconnectAttempts = 0;
        });

        this.source.addEventListener('error', (error) => {
            console.error("EventSource failed:", error);
            this.updateConnectionStatus(false);
            
            if (this.source.readyState === EventSource.CLOSED) {
                this.handleReconnection();
            }
        });

        this.source.addEventListener('heartbeat', () => {
            console.log("Heartbeat received: " + new Date().toISOString());
        });
    }

    updateConnectionStatus(isConnected) {
        const statusIndicator = document.querySelector('.status-indicator');
        const statusText = document.querySelector('.chat-status span');
        
        if (statusIndicator) {
            statusIndicator.style.backgroundColor = isConnected ? '#006600' : '#cc0000';
        }
        
        if (statusText) {
            statusText.textContent = isConnected ? 'Online' : 'Reconnecting...';
        }
    }

    handleReconnection() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1);
            
            console.log(`Reconnection attempt ${this.reconnectAttempts} in ${delay}ms`);
            
            setTimeout(() => {
                this.connect();
            }, delay);
        } else {
            console.error("Max reconnection attempts reached");
            showNotification("Connection lost. Please refresh the page.", "error");
        }
    }

    handleUserEvent(message) {
        if (message.action === "signon" || message.action === "signoff") {
            if (!users[message.userID]) {
                users[message.userID] = new User(message);
            } else {
                users[message.userID].updateUser(message);
            }
            
            if (message.moderator) {
                this.loggedOnModerators += (message.action === "signon") ? 1 : -1;
            }
        }
    }

    handleChatMessage(data) {
        if (messages[data.id]) {
            messages[data.id].updateMessage(data);
        } else {
            let message = new Message(data);
            messages[message.messageID] = message;		
            message.display();
            
            // Play notification sound for received messages
            if (!message.isOwnMessage) {
                this.playNotificationSound();
            }
        }
    }

    handleRoomStatus(message) {
        const statusWindow = document.getElementById("groupChatStatusWindow");
        if (statusWindow) {
            statusWindow.innerHTML = decodeURIComponent(message.message);
        }
        
        // Update room status and show/hide UI accordingly
        isRoomOpen = (message.roomStatus === "1");
        
        // Update UI when room status changes
        const typingWrapper = document.getElementById("groupChatTypingWrapper");
        if (typingWrapper && isRoomOpen) {
            // Focus the input area when room opens
            const typingWindow = document.getElementById("groupChatTypingWindow");
            if (typingWindow) {
                setTimeout(() => typingWindow.focus(), 100);
            }
            console.log("✅ Room is OPEN - Chat ready");
        }
    }

    handleInstantMessage(message) {
        if (message.imTo === this.userID || message.imFrom === this.userID) {
            const otherUser = (message.imTo === this.userID) ? message.imFrom : message.imTo;
            
            if (!users[otherUser]) {
                users[otherUser] = new User(message);
            }
            
            if (!users[otherUser].im[message.id]) {
                users[otherUser].im[message.id] = new InstantMessage(message);
                users[otherUser].im[message.id].display();
            }
        }
    }

    handleTimeout(message) {
        if (message.userID === this.userID) {
            showNotification(`You have been timed out for ${message.minutes} minutes`, "warning");
            setTimeout(() => {
                window.location.href = "logout.php";
            }, 2000);
        }
    }

    updateUserCount() {
        const countElement = document.getElementById("numberOfUsers");
        if (countElement) {
            countElement.innerHTML = this.numberOfUsers;
        }
    }

    playNotificationSound() {
        // Simple notification sound using Web Audio API
        if ('AudioContext' in window || 'webkitAudioContext' in window) {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
                oscillator.frequency.setValueAtTime(600, audioContext.currentTime + 0.1);
                
                gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
                
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.2);
            } catch (error) {
                console.log("Could not play notification sound:", error);
            }
        }
    }

    handleUnsupportedBrowser() {
        console.error("Your browser doesn't support Server-Sent Events");
        showNotification("Your browser doesn't support real-time chat. Please use a modern browser.", "error");
    }
}

// Add debugging helper for development
function debugChat() {
    console.group("Chat Debug Information:");
    console.log("Current User:", currentUser);
    console.log("Chat Room ID:", document.getElementById("groupChatRoomID")?.value);
    console.log("User Names:", userNames);
    console.log("Messages Count:", Object.keys(messages).length);
    console.log("Users Count:", Object.keys(users).length);
    console.groupEnd();
}

// Make debug function available globally in development
if (window.location.hostname === 'localhost' || window.location.search.includes('debug=1')) {
    window.debugChat = debugChat;
}

// Add modern CSS for placeholder when empty
const style = document.createElement('style');
style.textContent = `
    #groupChatTypingWindow.empty::before {
        content: attr(data-placeholder);
        color: var(--neutral-400);
        pointer-events: none;
    }
    
    #groupChatSubmitButton.loading {
        opacity: 0.7;
        cursor: not-allowed;
    }
    
    .notification {
        font-family: "Times New Roman", Georgia, serif;
        font-size: 0.875rem;
        font-weight: bold;
    }
    
    .groupChatMessageContainer {
        animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .user-avatar {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 0.625rem;
        text-transform: uppercase;
        border: 2px solid #550000;
        box-shadow: 0 1px 2px 0 rgba(85, 0, 0, 0.1);
    }
    
    .own-message {
        align-self: flex-end;
        align-items: flex-end;
    }
    
    .other-message {
        align-self: flex-start;
        align-items: flex-start;
    }
`;
document.head.appendChild(style);