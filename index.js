//GLOBALS

var geocoder = "";
var directionsService = "";
var dragObject = "";
var newCall = "";
var newChat = "";
var newMonitorChats = "";
var countriesList = new Array();
var chatMonitorKeepAlive = null;
var trainer = "";
var trainee = "";
var monitor = "";
var trainingSession = "";
var monitorChatInterval = "";
var welcomeSlideRotation = "";
var mytrainingChatwindow = "";
var myVideo = document.getElementById("video1");
var videoFinished = "";
var priorOldPanel = "";
var olderPanel = "";
var currentPanel = "";
var exiting = false;
var newSearch = null;
var isTrainingMode = false; // Set to true for trainers/trainees to disable inactivity timeout
var heartbeatInterval = null; // Heartbeat timer - cleared on exit

// ========================================
// Screen Reader Accessibility Utilities
// ========================================

/**
 * Announce a message to screen reader users via ARIA live regions
 * @param {string} message - The message to announce
 * @param {string} priority - 'polite' for non-urgent, 'assertive' for urgent (default: 'polite')
 */
function announceToScreenReader(message, priority = 'polite') {
    // Select the appropriate announcement region based on priority
    const regionId = priority === 'assertive' ? 'sr-announcements-urgent' : 'sr-announcements';
    const region = document.getElementById(regionId);

    if (!region) {
        console.warn('Screen reader announcement region not found:', regionId);
        return;
    }

    // Clear the region first to ensure the announcement is made even if the message is the same
    region.textContent = '';

    // Use setTimeout to ensure the DOM update is processed before the new message
    setTimeout(() => {
        region.textContent = message;
        console.log(`[Screen Reader ${priority}]: ${message}`);
    }, 100);
}

/**
 * Show call history details in a modal dialog
 */
function showCallHistoryModal(date, time, demographics, notes) {
    // Create modal if it doesn't exist
    var modal = document.getElementById('callHistoryModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'callHistoryModal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'callHistoryModalTitle');

        modal.innerHTML =
            '<div class="callHistoryModalContent">' +
                '<div class="callHistoryModalHeader">' +
                    '<h3 id="callHistoryModalTitle">Call Details</h3>' +
                    '<button class="callHistoryModalClose" aria-label="Close">&times;</button>' +
                '</div>' +
                '<div class="callHistoryModalBody">' +
                    '<div class="callHistoryModalDemographics"></div>' +
                    '<div class="callHistoryModalNotes"></div>' +
                '</div>' +
            '</div>';

        document.body.appendChild(modal);

        // Close on clicking X button
        modal.querySelector('.callHistoryModalClose').addEventListener('click', closeCallHistoryModal);

        // Close on clicking overlay
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeCallHistoryModal();
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('open')) {
                closeCallHistoryModal();
            }
        });
    }

    // Update modal title with date/time
    modal.querySelector('#callHistoryModalTitle').textContent = 'Call on ' + date + ' at ' + time;

    // Update demographics
    var demoEl = modal.querySelector('.callHistoryModalDemographics');
    if (demographics) {
        demoEl.textContent = demographics;
        demoEl.style.display = 'block';
    } else {
        demoEl.style.display = 'none';
    }

    // Update notes
    var notesEl = modal.querySelector('.callHistoryModalNotes');
    if (notes) {
        notesEl.textContent = notes;
        notesEl.style.display = 'block';
    } else {
        notesEl.style.display = 'none';
    }

    // Show modal
    modal.classList.add('open');
    modal.querySelector('.callHistoryModalClose').focus();
}

function closeCallHistoryModal() {
    var modal = document.getElementById('callHistoryModal');
    if (modal) {
        modal.classList.remove('open');
    }
}

/**
 * Track volunteers for online/offline announcements
 */
var previousVolunteerList = {};

function improperExit() {
	var improperExit = true;
	 return "Hi";
}

var inactivityTime = function () {
    var time;
    window.onload = resetTimer;
    // DOM Events
    document.onmousemove = resetTimer;
    document.onkeydown = resetTimer;


    function resetTimer() {
        clearTimeout(time);

        // NEVER set inactivity timeout for trainers or trainees
        // They may be passively watching screen shares without mouse/keyboard activity
        if (isTrainingMode) {
            console.log('Inactivity timeout disabled - user in training mode');
            return;
        }

        // For regular volunteers, set the 30-minute inactivity timeout
        if(!trainingSession || !trainingSession.muted) {
        	if(!newCall || newCall.callStatus === 'open') {
		        time = setTimeout(exitProgram, 1800000);
		    }
		}

        // 1000 milliseconds = 1 second
    }
};

window.onload = async function() {  // Made this function async
  try {
    // Wait for call monitor to initialize before proceeding
    await initializeCallMonitor();
    
    // Execute inactivity time after call monitor is ready
    inactivityTime();

    // Check if user is actually a trainer (LoggedOn = 4) or trainee (LoggedOn = 6)
    const userLoggedOn = document.getElementById("userLoggedOn") ? parseInt(document.getElementById("userLoggedOn").value) : 0;
    const isTrainer = userLoggedOn === 4;
    const isTrainee = userLoggedOn === 6;
    const isTrainingUser = isTrainer || isTrainee;

    // Set global flag to disable inactivity timeout for trainers/trainees
    isTrainingMode = isTrainingUser;

    if (isTrainingUser) {
        // User is a trainer or trainee - initialize training fields
        trainer = document.getElementById("trainer") ? document.getElementById("trainer").value : 0;
        trainee = document.getElementById("trainee") ? document.getElementById("trainee").value : 0;
        monitor = document.getElementById("monitor") ? document.getElementById("monitor").value : 0;
        console.log(`Training user detected (LoggedOn=${userLoggedOn}) - training session enabled, inactivity timeout DISABLED`);
    } else {
        // Regular volunteer or other user - disable training session completely
        trainer = 0;
        trainee = 0;
        monitor = 0;
        trainingSession = null;
        console.log(`Non-training user detected (LoggedOn=${userLoggedOn}) - training session disabled, inactivity timeout ACTIVE`);
    }
    
    currentUser = document.getElementById("volunteerID").value;

    // Handle browser close/navigation - send beacon for logging purposes
    // NOTE: sendBeacon does NOT change LoggedOn status anymore (server ignores non-intentional exits)
    // This beacon is kept for potential future analytics/logging but has no database effect
    // User stays "logged in" until heartbeat expires (2 min) or Exit button is clicked
    window.onbeforeunload = function() {
        var data = new FormData();
        data.append('postType', 'exitProgram');
        data.append('VolunteerID', currentUser);
        navigator.sendBeacon('volunteerPosts.php', data);
    };

    // Heartbeat system - send periodic pings to server to indicate we're still active
    // Server will mark users as logged out if no heartbeat received in 2+ minutes
    // This catches browser closes, crashes, and network disconnects
    heartbeatInterval = setInterval(function() {
        var data = new FormData();
        data.append('postType', 'heartbeat');
        fetch('volunteerPosts.php', { method: 'POST', body: data })
            .catch(function(err) {
                console.warn('Heartbeat failed:', err);
            });
    }, 30000); // Send heartbeat every 30 seconds

    // Send initial heartbeat immediately
    (function() {
        var data = new FormData();
        data.append('postType', 'heartbeat');
        fetch('volunteerPosts.php', { method: 'POST', body: data })
            .then(function(response) { return response.json(); })
            .then(function(result) {
                console.log('Initial heartbeat sent:', result.timestamp);
            })
            .catch(function(err) {
                console.warn('Initial heartbeat failed:', err);
            });
    })();

    // Only initialize training session if user is trainer/trainee
    if (isTrainingUser && trainer == 1) {   
      // Current user is a trainer (trainer field = "1")
      try {
        trainingSession = new Training();
        trainingSession.init(currentUser, document.getElementById("assignedTraineeIDs").value, "trainer").then(() => {
            trainingSession.trainerSignedOn(currentUser, currentUser);
        }).catch(error => {
            console.error("Training session initialization failed:", error);
            // Continue without breaking the page
            trainingSession = null;
        });
      } catch (error) {
        console.error("Training session creation failed:", error);
        trainingSession = null;
      }
    } else if (isTrainingUser && trainee == 1) {
      // Current user is a trainee (trainee field = "1")
      const trainerIDField = document.getElementById("trainerID");
      const actualTrainerID = trainerIDField ? trainerIDField.value : null;
      
      try {
        trainingSession = new Training();
        trainingSession.init(actualTrainerID, currentUser, "trainee").then(() => {
            if (actualTrainerID) {
                trainingSession.trainerSignedOn(actualTrainerID, actualTrainerID);
            }
        }).catch(error => {
            console.error("Training session initialization failed:", error);
            trainingSession = null;
        });
      } catch (error) {
        console.error("Training session creation failed:", error);
        trainingSession = null;
      }
    }   

    geocoder = new google.maps.Geocoder();
    directionsService = new google.maps.DirectionsService();

    newChat = new ChatMonitor("testing");
    newChat.init();
    chatMonitorKeepAlive = setTimeout("newChat.init();",30000);
    
    var chatOnlyBox = document.getElementById("oneChatOnly");
    chatOnlyBox.onchange = function () {
      newChat.oneChatOnlyChange();
    }
    
    var controlPane = document.getElementById("newSearchPaneControls");
    controlPane.style.display = 'block';
    
    newInfoCenter = new InfoCenter();
    
    userList = {};
    
    searchBox = document.getElementById("searchBox");
    searchBox.addEventListener('dragstart',drag_start,false); 

    resourceDetailName = document.getElementById("resourceDetailName");
    resourceDetailName.addEventListener('dragstart',resourceDetailDrag_Start, false);

    chat1 = document.getElementById("chatMessage1");
    chat1.addEventListener('dragover',drag_over,false); 
    chat1.addEventListener('drop',resoureDetailDrop,false); 

    chat2 = document.getElementById("chatMessage2");
    chat2.addEventListener('dragover',drag_over,false); 
    chat2.addEventListener('drop',resoureDetailDrop,false); 
    
    var oneChatBox = document.getElementById("oneChatOnly");
    if (oneChatBox.checked) {
      newChat.oneChatOnlyChange();
      newChat.oneChatOnlyChange();
    }

    document.body.addEventListener('dragover',drag_over,false); 
    document.body.addEventListener('drop',drop,false); 

    var mainNewSearchButton = document.getElementById('mainNewSearchButton');
    mainNewSearchButton.onclick = function () {
      document.getElementById("resourceListCategory").innerHTML = "";
      document.getElementById("searchBoxForm").reset();
      document.getElementById("searchParameters").innerHTML = "";
      document.getElementById("infoCenterText").innerHTML = "";
      viewControl('searchBox');
    };
    
    document.getElementById("newSearchClose").onclick = function() {
      viewControl("Main");
    };
    
    document.getElementById("resourceDetailLabelNote").onclick = function() {
      viewControl(this.id);
    };
    
    document.getElementById("findZipSearch").onclick = function() {
      validate(this.id);
    };

    document.getElementById("nationalSearch").onclick = function() {
      validate(this.id);
    };
    
    timers = [];
    timerInterval = "";
    
    document.getElementById("ExitButton").onclick = function() {
      if(newCall || newChat.chats[1] || newChat.chats[2]) {
        alert("You cannot exit while a call or chat is in progress.");
        return;
      }
      exitProgram("user").then(() => {
        console.log('Exit completed');
      });
    };
    
    countries();
    
    var copyableList = document.getElementById("copyableList");
    if (copyableList) {
      copyableList.style.visibility = "hidden";
    }
    
    welcomeSlideRotation = new RotateWelcomeSlides();
    welcomeSlideRotation.init();
    

    var bradStatsButton = document.getElementById('bradStatsButton');
    
    if(bradStatsButton) {
      bradStatsButton.onclick = function() {
        window.open("Stats/newStats.php")
      };
    }
    
    var statsButton = document.getElementById('statsButton');
    statsButton.onclick = function() {
      viewControl("timelinePane");
    };
      
    var video = document.getElementById("videoWindow");
    var showVideo = video.className;
    var fileType = showVideo.slice((showVideo.lastIndexOf(".") - 1 >>> 0) + 2);
    if(showVideo == "none" || fileType == "html") {
      removeElements(video);
    } else {
      var mainElements = document.body.getElementsByTagName("div");
      for (i = 0; i < mainElements.length; i++) {
        mainElements[i].style.display = "none";
      }
      video.style.display = "block";
      var videoPlay = document.getElementById("videoPlayButton");
      videoPlay.onclick = function() {
        var myVideo = document.getElementById("video1");
        if (myVideo.paused) {
          myVideo.play(); 
        } else {
          myVideo.pause(); 
        }
      };
      var params = "postType=watchingVideo";
      var responseFunction = function (results) {
        if(results != "OK") {
          if (typeof showComprehensiveError === 'function') {
            showComprehensiveError(
              'Video Start Error',
              'Problem telling system that video is starting',
              {
                url: 'volunteerPosts.php',
                params: params,
                responseText: results,
                additionalInfo: 'The system failed to record that video watching has started. This may affect tracking.'
              }
            );
          } else {
            alert("Problem telling system that video is starting.");
          }
        }
      };
      var watchingVideoPost = new AjaxRequest("volunteerPosts.php", params, responseFunction); 
      videoFinished = setInterval(videoWatched, 1000);
    }

    var chatOnly = document.getElementById("chatOnlyFlag").value;
    if(chatOnly == 0) {
      // Initialize Twilio Device

/*
      document.getElementById('start-call-monitor').addEventListener('click', async function initializeOnce(event) {
        try {
          console.log("User action detected. Initializing call monitor...");
          await initializeCallMonitor();
          console.log("Call monitor initialized successfully.");
      
          // Remove the button from the page after initialization
          const button = event.target;
          button.remove();
          console.log("Button removed after successful initialization.");
        } catch (error) {
          console.error("Failed to initialize call monitor:", error);
        }
      });
*/      
    }
  } catch (error) {
    console.error("Error during initialization:", error);
    // Handle initialization error appropriately
  }
}


// UTILITY FUNCTIONS
function removeElements(element) {
	while(element.hasChildNodes()) {     
		element.removeChild(element.childNodes[0]);
	}
}

function escapeHTML(str) {
	var div = document.createElement("div");
	div.appendChild(document.createTextNode(str));
	var encodedString = div.innerHTML;
	return encodeURIComponent(encodedString);
}

function unescapeHTML(escapedStr) {
    var div = document.createElement('div');
    div.innerHTML = escapedStr;
    var child = div.childNodes[0];
    return child ? child.nodeValue : '';
};

function testResults(results, resultsObject) {
	if(results && results != "OK" && results != true) {
		if (typeof showComprehensiveError === 'function') {
			showComprehensiveError(
				'Request Error',
				'An error occurred during the request',
				{
					responseText: results,
					additionalInfo: resultsObject ? 'Results Object: ' + JSON.stringify(resultsObject) : 'No additional context available'
				}
			);
		} else {
			alert(results);
		}
	}
}

function formatTime(Time) {
	totalSeconds = Time / 1000;
	hours = Math.floor(totalSeconds / 3600);
	minutes = Math.floor((totalSeconds / 60) - (hours * 60));
	seconds = Math.floor(totalSeconds - ((hours * 3600) + (minutes * 60)));
	if (minutes < 10) {
		minutes = "0" + minutes;
	}
	if (seconds < 10) {
		seconds = "0" + seconds;
	}
	time = hours + ":" + minutes + ":" + seconds;
	return time;
}

function tick() {	
	timers.forEach(function(timer) {
		if(timer.startTime) {
			var stamp = new Date().getTime();
			var elapsed = stamp - timer.startTime;
			var time = formatTime(elapsed);

			var timeDisplay = document.getElementById("timer" + timer.timerNumber);
			timeDisplay.innerHTML =  time;
		}
	});
}
    
function Timer(timerNumber) {
	timers[timerNumber] = this;
	if(timerNumber == 3) {
		this.timerNumber = 1;
	} else {
		this.timerNumber = timerNumber;
	}
	var self = this;	
	this.startTime = new Date().getTime();
	
	this.start = function () {
        var timeDisplay = document.getElementById("timer" + self.timerNumber);
		timerInterval = setInterval("tick();",1000);
	};
	

	this.stop = function() {
		this.startTime = null;
		clearInterval(timerInterval);

	};
	
	
	this.clear = function() {
        var timeDisplay = document.getElementById("timer" + this.timerNumber);
        timeDisplay.innerHTML = "";
        delete(timers[this.timerNumber]);
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

function resourceDetailDrag_Start(event) {
	dragObject = this;
    var style = window.getComputedStyle(event.target, null);
	var nameElement = document.getElementById("resourceDetailName").firstChild.nodeValue;
	var hotlineElement = document.getElementById("resourceDetailHotline").value;
	var phoneElement = document.getElementById("resourceDetailPhone").value;
	if(document.getElementById("resourceDetailWWWEB").firstChild.firstChild) {
		var webElement = document.getElementById("resourceDetailWWWEB").firstChild.firstChild.nodeValue;
	} else {
		var webElement = "";
	}
	var emailElement = document.getElementById("resourceDetailInternet").value;

	var Name = "Here is the information.  Name: " + nameElement.replace(/^\s+|\s+$/g, '') + ".  ";
	var Hotline = "";
	var Phone = "";
	var Web = "";
	var eMail = "";
	
	if (hotlineElement.length > 9) {
		Hotline = "Their phone number is: " + hotlineElement.replace(/^\s+|\s+$/g, '');
	} else {
		Hotline = "";
	}
	if (phoneElement.length > 9 && hotlineElement.length > 9) {
		Phone = " or " + phoneElement.replace(/^\s+|\s+$/g, '') + ".  ";
	} else if (phoneElement.length > 9) {
		Phone = "Their phone number is: " + phoneElement.replace(/^\s+|\s+$/g, '') + ".  ";
	} else if (phoneElement.length < 10 && hotlineElement.length > 9) {
		Phone = ".  ";
	}
	
	if (webElement.length > 3) {
		Web = "Their website is: " + webElement.replace(/^\s+|\s+$/g, '') + ".  ";
	} else {
		Web = "";
	}
	if (emailElement.length > 3) {
		eMail = "The email address is: " + emailElement.replace(/^\s+|\s+$/g, '') + ".  ";
	} else {
		eMail = "";
	}

    event.dataTransfer.setData("text/plain", Name + Hotline + Phone + Web + eMail + "You should contact them directly for their location.");
}





// CALL ROUTINES
function Call(conn) {
	var self = this;
	this.volunteerCallSid = false;
	this.callWaitingInterval = "";
	this.callStatus = 'open';
	this.call = conn;
	this.callLog = "";
	this.hotline = "";
	this.answerInProgress = false; // Flag to prevent auto-cancel during answer process
	this.callPane = document.getElementById("callPane");
	this.mainControls = document.getElementById("newSearchPaneControls");
	this.infoCenterButton = 	document.getElementById("infoCenterMenu");
	this.answerButton = document.getElementById("answerCall");
	this.rejectButton = document.getElementById("rejectCall");
	this.hangupButton = document.getElementById("callHangUpButton");
	this.hangupButton.style.visibility = "hidden";
	this.hangupButton.onclick = function () {
		self.hangupButton.style.visibility = "hidden";
		callMonitor.getDevice().disconnectAll()	
	};

	this.blockCallButton = document.getElementById("callBlockButton");
	this.blockCallButton.onclick = function () {
		self.blockCall();
	};
	this.callerId = "(" + conn.From.substring(2,5) + ") " + conn.From.substring(5,8) + "-" + conn.From.substring(8,12);
	this.City = "";
	this.State = "";
	this.Zip = "";
	this.From = "";
	this.url = "volunteerPosts.php";
	this.clientCallerSid = conn.CallSid;
	this.volunteerID = document.getElementById("volunteerID").value;

	this.init = function () {
		this.answerButton.onclick = function () {
			self.answerCall();
		};			
		this.rejectButton.onclick = function () {
			self.cancelCall();
			delete(newCall);
			userList[self.volunteerID].incomingCall = false;
		};		
		this.startCall();
	};
	
	this.startCall = function () {
		this.callStatus = 'ringing';
		this.params = "postType=callerHistory&action=" + encodeURIComponent(this.callerId);
		var searchRequest = new AjaxRequest(this.url, this.params, this.displayResults, this);
	};
	
	this.displayResults = function (results, resultsObject) {
		var callHistoryPane = document.getElementById("callHistoryPane");
		callHistoryPane.style.background = null;
//		callHistoryPane.style.display = "block";
		var displayElement = document.getElementById("callHistoryData");
		displayElement.innerHTML = "";
		
		if(!resultsObject) {
			resultsObject = this;
		}
		
		if(!resultsObject.results) {
			resources = resultsObject.results = JSON.parse(results);
		}
		
		resultsObject.hotline = resources[0];
		document.getElementById("callHotlineDisplay").innerHTML = resultsObject.hotline.longName;
		document.getElementById("callHistoryHotline").innerHTML = resultsObject.hotline.shortName;
		
		this.City = resultsObject.hotline.city;
		this.State = resultsObject.hotline.state;
		this.Zip = resultsObject.hotline.zip;
		this.From = this.City + ", " + this.State;
	
		document.getElementById("callHistoryLocation").innerHTML = this.From;
		document.getElementById("logCity").value = this.City;
		document.getElementById("logState").value = this.State;
		document.getElementById("logZip").value = this.Zip;

		var table = document.createElement("table");
		table.className = "callHistoryTable";

		// Add caption for screen readers (accessibility)
		var caption = document.createElement("caption");
		caption.className = "sr-only";
		caption.appendChild(document.createTextNode("Previous calls from this number"));
		table.appendChild(caption);

		// Create thead for header row (accessibility)
		var thead = document.createElement("thead");
		var tr = document.createElement("tr");

		var headers = ["Date", "Time", "Hotline", "Length", "Category"];
		headers.forEach(function(headerText) {
			var th = document.createElement("th");
			th.setAttribute("scope", "col");
			th.appendChild(document.createTextNode(headerText));
			tr.appendChild(th);
		});
		thead.appendChild(tr);
		table.appendChild(thead);

		// Create tbody for data rows (accessibility)
		var tbody = document.createElement("tbody");
		var rowIndex = 0;
		// Start from index 1 to skip hotline info at index 0
		for (var i = 1; i < resources.length; i++) {
			var resource = resources[i];

			// Skip if not a valid call object (e.g., "No Call History" string)
			if (typeof resource !== 'object' || !resource.date) {
				continue;
			}

			var tr = document.createElement("tr");
			var detailsData = { gender: '', age: '', notes: '' };
			var hasDetails = resource['category'] === "Conversation";

			// Create data cells
			['date', 'time', 'hotline', 'length', 'category'].forEach(function(field) {
				var td = document.createElement('td');
				var div = document.createElement('div');
				div.setAttribute("class", "callHistory" + field);
				div.appendChild(document.createTextNode(resource[field] || ''));
				td.appendChild(div);
				tr.appendChild(td);
			});

			// Collect details data
			if (resource['gender']) {
				try { detailsData.gender = decodeURIComponent(resource['gender']); } catch(e) {}
			}
			if (resource['age']) {
				try { detailsData.age = decodeURIComponent(resource['age']); } catch(e) {}
			}
			if (resource['callLogNotes']) {
				try { detailsData.notes = decodeURIComponent(resource['callLogNotes']); } catch(e) {}
			}

			// Make row clickable if it has details (Conversation calls with gender/age/notes)
			if (hasDetails && (detailsData.gender || detailsData.age || detailsData.notes)) {
				// Make row interactive
				tr.className = 'callHistoryRowExpandable';
				tr.setAttribute('tabindex', '0');
				tr.setAttribute('role', 'button');
				tr.setAttribute('aria-haspopup', 'dialog');
				tr.setAttribute('aria-label', 'Call on ' + (resource['date'] || '') + ' at ' + (resource['time'] || '') + '. Click to view details.');

				// Build demographics and notes for modal
				var demographics = '';
				if (detailsData.gender) demographics += detailsData.gender;
				if (detailsData.age) demographics += (demographics ? ', ' : '') + detailsData.age;

				// Build hover tooltip for sighted users
				var tooltipText = '';
				if (demographics) tooltipText += demographics + '\n';
				if (detailsData.notes) {
					// Truncate long notes for tooltip (max 200 chars)
					var notesPreview = detailsData.notes.length > 200
						? detailsData.notes.substring(0, 200) + '...'
						: detailsData.notes;
					tooltipText += notesPreview;
				}
				if (tooltipText) {
					tr.setAttribute('title', tooltipText.trim());
				}

				// Modal click handler - closure to capture data
				(function(row, date, time, demo, notes) {
					var openModal = function(e) {
						showCallHistoryModal(date, time, demo, notes);
					};
					row.addEventListener('click', openModal);
					row.addEventListener('keydown', function(e) {
						if (e.key === 'Enter' || e.key === ' ') {
							e.preventDefault();
							openModal(e);
						}
					});
				})(tr, resource['date'] || '', resource['time'] || '', demographics, detailsData.notes);
			}

			tbody.appendChild(tr);

			rowIndex++;
		}
		table.appendChild(tbody);
		displayElement.appendChild(table);

		// Screen reader announcement for incoming call
		// rowIndex contains count of valid call records displayed
		var callAnnouncement = 'Incoming call from ' + this.From + '.';
		if (rowIndex > 0) {
			callAnnouncement += ' ' + rowIndex + ' previous call' + (rowIndex > 1 ? 's' : '') + ' from this number.';
		}
		announceToScreenReader(callAnnouncement, 'assertive');

		clearTimeout(self.callWaitingInterval);


		if(self.call.CallStatus == "firstRing") {
			switch(resultsObject.hotline.shortName) {
				case 'LGBT National Hotline':
					var timeLength = 10000;
					self.callWaitingInterval = setTimeout( function() {viewControl('callPane')},timeLength);		
					break;	

				case 'LGBT Switchboard NY':
					var timeLength = 16000;
					self.callWaitingInterval = setTimeout( function() {viewControl('callPane')},timeLength);		
					break;	

				case 'Youth Talkline':
					var timeLength = 14000;
					self.callWaitingInterval = setTimeout( function() {viewControl('callPane')},timeLength);		
					break;	

				case 'LGBT Senior Hotline':
					var timeLength = 12000;
					self.callWaitingInterval = setTimeout( function() {viewControl('callPane')},timeLength);		
					break;				
				
				default:
					var timeLength = 29000;
					self.callWaitingInterval = setTimeout( function() {viewControl('callPane')},timeLength);		
					break;	
			}
		} else {
			viewControl('callPane');
		}		


	};
	
	this.answerCall = function () {
		this.callStatus = 'answering';
		this.answerInProgress = true; // Set flag to prevent auto-cancel during answer
		console.log("🎯 Answer clicked - answerInProgress flag set to prevent auto-cancel race condition");

		// Stop ringing sound when volunteer answers
		var ringingSound = document.getElementById("chatSound");
		if (ringingSound) {
			ringingSound.pause();
			ringingSound.currentTime = 0;
		}
		this.answerButton.setAttribute("src","Images/connecting.png");
		this.answerButton.onclick = "";
		this.rejectButton.style.visibility = "hidden";
		this.answerButton.setAttribute("class","answerCallCenter");

		params = "clientCallerSid=" + this.clientCallerSid;
		var url = "answerCall.php";
		var resultsObject = new Object();
		var postResults = function (results, searchObject) {
			callAnswerResults(results, searchObject);
		};
		var redirectCall = new AjaxRequest(url, params, postResults, resultsObject);

	};

	this.continueCall = function() {
		this.answerInProgress = false; // Clear flag after answer process completes
		console.log("✅ continueCall - answerInProgress flag cleared");

		var thisVolunteer = document.getElementById("volunteerID").value;
		var parameters = {};
		parameters.vccCaller = thisVolunteer;
		if(!trainingSession) {
			self.call = callMonitor.getDevice().connect(parameters);
			this.callConnected();
		} else {
			trainingSession.startNewCall();
			this.callConnected();
		}
	};
	
	this.callConnected = function () {

		this.callStatus = 'connected';
		this.mainControls.style.visibility = "visible";
		this.infoCenterButton.style.visibility = "visible";
		this.hangupButton.style.visibility = "visible";
		this.answerButton.setAttribute("class","answerCallVanish");
		this.answerButton.setAttribute("src","Images/connected.png");
		this.createCallLog(this.hotline.id);		

		params = "postType=startCall&action=" + this.hotline.id;
		var resultsObject = new Object();
		var postResults = function (results, searchObject) {
			testResults(results, searchObject);
		};
		var startCall = new AjaxRequest(this.url, params, postResults, resultsObject);

		setTimeout(function() {viewControl("main")},6000);	
	
		var searchBoxPane = document.getElementById("newSearchPane");
		var volunteerMessage = document.getElementById("volunteerMessage");
		volunteerMessage.style.visibility = "hidden";
		searchBoxPane.style.background = null;
		searchBoxPane.style.color = null;
	};
	
		
	this.endCall = function () {
		callMonitor.getDevice().disconnectAll();
		if(trainingSession) {
			trainingSession.endCall();
			if (this.callStatus !== 'connected') return;
		}
		var callHistoryPane = document.getElementById("callHistoryPane");
		callHistoryPane.style.background = "#F5F5DC";
		callHistoryPane.style.color = "black";
		
		this.callStatus = 'open';
		var params = 'params=none';
		this.callLog.saveable = true;
		var url = "twilioConferenceEnd.php";
		var resultsObject = new Object();
		var postResults = function (results, searchObject) {
			//testResults(results, searchObject);
		};
		var endCall = new AjaxRequest(url, params, postResults, resultsObject);

		if(timers[3]) {
			timers[3].stop();
		}
		this.hangupButton.style.visibility = "hidden";
	//	var callHistoryData = document.getElementById("callHistoryData");
	//	callHistoryData.style.display = "none";
		this.answerButton.setAttribute("src","Images/answer.png");
		this.answerButton.setAttribute("class","answerCallLeft");
		this.rejectButton.style.visibility = null;
		var thisUser = document.getElementById("volunteerID").value;
		//userList[thisUser].incomingCall = false;
	};

			
	this.blockCall = function () {
		this.endCall();
		this.cancelCall();
		var phone = encodeURIComponent(this.callerId);
		var place = encodeURIComponent(this.From);
	
		url = "blockCallWindow.php?PhoneNumber=" + phone + "&Location=" + place + "&Message=" + "";
		mywindow = window.open (url , "Block Caller", "resizable=no,titlebar=0,toolbar=0,scrollbars=no,status=no,height=390,width=500,addressbar=0,menubar=0,location=0");  
		mywindow.moveTo(400,400);
		mywindow.focus();
		self.callLog.cancelLog(self, "Nuisance");
	};
	
	

	this.cancelCall = function ()  {
		this.callStatus = 'open';
        var activeCall = callMonitor.getDevice().activeConnection();
		var ringingSound = document.getElementById("chatSound");
		if (ringingSound) {
			ringingSound.pause();
			ringingSound.currentTime = 0;
		}
		// Get the actual Twilio v2 call object from the device
		// Rejection logic runs for all users including trainers/trainees
		if (activeCall && typeof activeCall.reject === 'function') {
			// Reject the incoming call properly
			console.log("Rejecting incoming call:", activeCall.sid || activeCall.parameters.CallSid);
			activeCall.reject();
		} else {
			// Fallback to disconnectAll for other scenarios
			console.log("No active call to reject, using disconnectAll");
			callMonitor.getDevice().disconnectAll();
		}
		clearTimeout(self.callWaitingInterval);
		this.callWaitingInterval = "";

		this.clearCall();
	};
	
	
	
	this.clearCall = function () {
		logPane.style.background = null;
		document.getElementById("callHistoryPane").style.display = "none";
		if(timers[3]) {
			timers[3].clear();
		}
		// Stop any playing ringtone audio
		var ringingSound = document.getElementById("chatSound");
		if (ringingSound) {
			ringingSound.pause();
			ringingSound.currentTime = 0;
		}
		params = "postType=endCall";
		var resultsObject = new Object();
		var postResults = function (results, searchObject) {
			testResults(results, searchObject);
		};
		var clearCall = new AjaxRequest(this.url, params, postResults, resultsObject);
		newCall = null;

		document.getElementById("resourceListCategory").innerHTML = "";
		document.getElementById("searchBoxForm").reset();
		document.getElementById("searchParameters").innerHTML = "";
		document.getElementById("infoCenterText").innerHTML = "";

		var volunteerMessage = document.getElementById("volunteerMessage");
		volunteerMessage.style.visibility = null;

		userList[self.volunteerID].incomingCall = false;		
		viewControl('Close');
	};
		
	this.createCallLog = function (callLogHotlineId) {
		var self = this;
		var room = 3;
		var color = "rgba(200,175,175,1)";      			

		this.callLog = new CallLog(3);
	
		document.getElementById("logPaneForm").reset();
		this.callLog.setHotline(this.hotline.id);

		document.getElementById('callLogSaveButton').onclick = function () {
			self.callLog.validateLog(self);
		};
		document.getElementById('callLogCancelButton').onclick = function () {
			self.callLog.cancelLog(self, "Cancel");
		};
		document.getElementById('NuisanceButton').onclick = function () {
			self.callLog.cancelLog(self, "Nuisance");
		};
		document.getElementById('HangupButton').onclick = function () {
			self.callLog.cancelLog(self, "Hangup");
		};
		document.getElementById('AbusiveButton').onclick = function () {
			self.callLog.cancelLog(self, "Abusive");
		};
		activeLogPane.class = room;
		logPane.style.background = color;
		activeLogPane.style.backgroundColor = color;
		activeLogPane.style.display = 'block';

		if(timers[3]) {
			delete(timers[3]);
		}
		timers[3] = new Timer(3);
		timers[3].start();		
	};
}

function deleteCallObject() {
	delete(newCall);
}

// Twilio functionality is now loaded from external module
// The callMonitor, newTwilioToken, and initializeCallMonitor functions 
// are defined in twilioModule.js and available globally





//  RESOURCE SEARCH OBJECT AND FUNCTIONS
function Search(zip, category, distance, city, state, name, requestType) {
	this.zipCode = 		zip;
	this.category = 	category;
	this.range = 		distance;
	this.city = 		city;
	this.state =		state;
	this.name = 		name;
	this.requestType =	requestType;
	this.params = 		'';
	this.sortOrder = 	'Distance';
	this.url = 			'zipOnly.php';
	this.countriesList = 	new Array();


	this.searchRequest = function() {
		console.log("searchRequest() entered");
		console.log("requestType:", this.requestType);
		console.log("zipCode:", this.zipCode);
		console.log("range:", this.range);
		
		if(!this.requestType || this.requestType == "") {
			if(this.zipCode) {
				this.requestType = 'zip';
				if(!this.range) {
					alert("Please enter a search range.");
					return false;
				}
			} else if (this.city && this.state) {
				this.requestType = 'city';
			} else if (!this.city && this.state) {
				this.requestType = 'state';
			} else if (this.name) {
				this.requestType = 'name';
			} else {
				alert("Please complete the search criteria");
				return false;
			}
		}
		console.log("About to call postSearch()");
		this.postSearch();
		return;
	};
	

	this.postSearch =	function () {
    this.params = {
        ZipCode: this.zipCode,
        Range: this.range,
        City: this.city,
        State: this.state,
        Name: this.name,
        Category: this.category,
        SearchType: this.requestType
    };
		console.log("postSearch called with params:", this.params);
		console.log("URL:", this.url);
		try {
			var searchRequest = new AjaxRequest(this.url, this.params, this.displayResults, this);
			console.log("AjaxRequest created:", searchRequest);
		} catch (error) {
			console.error("Error creating AjaxRequest:", error);
			if (typeof showComprehensiveError === 'function') {
				showComprehensiveError(
					'Resource Search Error',
					'Failed to create search request: ' + error.message,
					{
						url: this.url,
						params: this.params,
						error: error,
						additionalInfo: 'The resource search could not be initiated. This may be due to a network issue or browser compatibility problem.'
					}
				);
			} else {
				alert("Search error: " + error.message);
			}
		}
	};

	this.displayResults = function (results, searchObject) {	
		console.log("displayResults called with results:", results);
		console.log("searchObject:", searchObject);
		
		if(!searchObject) {
			searchObject = this;
		}
		
		if(!searchObject.results) {
			if(results == "INVALID") {
				showInformationalModal(
					'ZIP Code Not Found',
					'The ZIP code "' + searchObject.zipCode + '" was not found in our database. Please verify the ZIP code and try again.'
				);
				return;
			} else if (results == "NONE") {
				showInformationalModal(
					'No Resources Found',
					'No resources found matching your search criteria. Try adjusting your search parameters or expanding your search range.'
				);
				return;
			} else if (results == "No ZipCode Located") {
				showInformationalModal(
					'ZIP Code Not Found',
					'No ZIP code found for ' + searchObject.city + ', ' + searchObject.state + '. Please verify the city and state name and try again.'
				);
				return;
			} 

			searchObject.results = JSON.parse(results);
			var searchParameters = searchObject.results.Search;

			// Place data into Search Parameters fields
			if(!searchParameters.zipcode) {
				searchParameters.zipcode = ".";
			}
			
			if(!searchObject.zipCode) {
				searchObject.zipCode = searchParameters.zipcode;
			}
			
			searchParameters.place += "  " + searchParameters.zipcode;
			document.getElementById("logCity").value = searchParameters.city;
			document.getElementById("logState").value = searchParameters.state;
			document.getElementById("logZip").value = searchParameters.zipcode;

			if (searchParameters.range > 1) {
				searchParameters.range += " Miles";
			} else if (searchParameters.range == 1) {
				searchParameters.range += " Mile";
			} else {
				searchParameters.range = "";
			}

			field = document.getElementById("searchParameters");
			field.innerHTML = "";						
			field.innerHTML = searchParameters.place + "<br />" + searchParameters.range;
			
		}

		var resources = searchObject.results.Resources;
		var bySortOrder = sortBy(resources, searchObject.sortOrder);
		var frag = document.createDocumentFragment();
		var table = document.createElement('table');
		var serverResponse = document.getElementById("resourceResults");

		// Add caption for screen readers (accessibility)
		var caption = document.createElement('caption');
		caption.className = 'sr-only';
		caption.appendChild(document.createTextNode('Resource search results'));
		table.appendChild(caption);

		// Add visually hidden header row for screen readers (accessibility)
		var thead = document.createElement('thead');
		thead.className = 'sr-only';
		var headerRow = document.createElement('tr');
		['#', 'Name', 'Category 1', 'Category 2', 'Location', 'Zip', 'Distance'].forEach(function(headerText) {
			var th = document.createElement('th');
			th.setAttribute('scope', 'col');
			th.appendChild(document.createTextNode(headerText));
			headerRow.appendChild(th);
		});
		thead.appendChild(headerRow);
		table.appendChild(thead);

		// Create tbody for data rows (accessibility)
		var tbody = document.createElement('tbody');

		while(serverResponse.hasChildNodes()) {
			serverResponse.removeChild(serverResponse.childNodes[0]);
		}


		bySortOrder.forEach(function(entry) {
			var serverResponse = document.getElementById("resourceResults");
			var title = '';
			var tr = document.createElement('tr');
			tr.setAttribute("class","hover");
			var resource = entry.resource;
			var count = bySortOrder.indexOf(entry);
			var td = document.createElement('td');
			div = document.createElement('div');
			div.setAttribute('class','resourceCounter');
			div.appendChild(document.createTextNode(count + 1));
			td.appendChild(div);
			tr.appendChild(td);
			if(searchObject.requestType == 'national' || searchObject.requestType == 'international') {
				resource['Local'] = null;
			}

			if(resource['Local'] == 'Y' && resource['Distance'] == -1) {
				var color = 'red';
			} else if(resource['Country'] == "Canada" || resource['Country'] == "CANADA") {
				var color= "rgba(0,200,0,1)";
			} else if (resource['NonLGBT'] == "Y") {
				var color= 'blue';
			} else {
				var color = 'black';
			}


			for (var key in resource) {	
				switch(key) {
					case 'Name':
					case 'Type1':
					case 'Type2':
					case 'Location':
					case 'Zip':
					case 'Distance':
						td = document.createElement('td');
						div = document.createElement('div');
						div.setAttribute('class',key);
						div.style.color = color;
						if (key == 'Distance' && resource['Local'] == "Y" && resource['Distance'] == -1) {
							div.appendChild(document.createTextNode('n/a'));
						} else {
							div.appendChild(document.createTextNode(resource[key]));
						}
						td.appendChild(div);
						tr.appendChild(td);
						break;
				}
			}
			
			if (resource['Description']) {
				title = resource['Description'];
			}
			tr.title = resource['Name'] + "\n\n" + title;
			tr.onclick = function() {displayRecord(bySortOrder, count);};
			tbody.appendChild(tr);
		});

		table.appendChild(tbody);
		frag.appendChild(table);
		serverResponse.appendChild(frag);
		displayRecord(bySortOrder,0);


		//Hide resourceDetail form and show resourceList
		viewControl('resourceList');
		resourceCount = document.getElementById("resourceListCount");
		resourceCount.innerHTML = "Resources Found: " + bySortOrder.length;

		//Show the Category for the Displayed List
		var resourceListCategory = document.getElementById("resourceListCategory");
		resourceListCategory.innerHTML = "Category: " + searchObject.category;
		serverResponse.scrollTop -= serverResponse.scrollHeight;

		//Set CopyableList Button
		var copyableList = document.getElementById("copyableList");
		if (copyableList) {
			copyableList.onclick = searchObject.copyableList;
		}

		// If this search was triggered by a category button, open the select menu
		if (searchObject.selectToOpen) {
			var selectToOpen = searchObject.selectToOpen;
			setTimeout(function() {
				// Enable pointer events so user can interact with dropdown
				selectToOpen.style.pointerEvents = "auto";
				selectToOpen.focus();
				// Try to programmatically open the dropdown
				if (selectToOpen.showPicker) {
					try {
						selectToOpen.showPicker();
					} catch(e) {
						// showPicker may fail, that's ok
					}
				}
			}, 50);
		}
	};
	
	this.copyableList = function() {
		var resources = newSearch.results.Resources;
		var bySortOrder = sortBy(resources, newSearch.sortOrder);				
		top.copyableListWindow=window.open('','Resource List',
			'width=550,height=550'
		   	+',menubar=0'
		   	+',toolbar=1'
		   	+',status=0');
		top.copyableListWindow.document.writeln(
		  '<html><head><title>Resources</title></head>'
		   +'<body>'
		);
		   
		bySortOrder.forEach(function(entry) {
			var writeableLine = "";
			var resource = entry.resource;
			if (resource.Distance == 1) {
				writeableLine = "<u>" + resource.Distance + " Mile";
			} else if (resource.Distance != "N/A") {
				writeableLine = "<u>" + resource.Distance + " Miles";
			}
			writeableLine += "</u><br />";
			writeableLine += resource.Name + "<br />";
			if (resource.Address1) {
				writeableLine += resource.Address1 + "<br />";
			}
			if (resource.Address2) {
				writeableLine += resource.Address2 + "<br />";
			}
			writeableLine += resource.Location + ", " + resource.Zip + "<br />";
			if (resource.Phone) {
				writeableLine += "Phone: " + resource.Phone + "<br />";
			}
			if (resource.Fax) {
				writeableLine +=  "Fax: &nbsp;&nbsp;&nbsp;&nbsp;" + resource.Fax + "<br />";
			}
			if (resource.Internet) {
				writeableLine +=  "Email: " + resource.Internet + "<br />";
			}
			if (resource.WWWEB) {
				writeableLine +=  "Web: " + resource.WWWEB + "<br />";
			}
			if (resource.WWWEB2) {
				writeableLine +=  "Web: " + resource.WWWEB2 + "<br />";
			}
			if (resource.WWWEB3) {
				writeableLine +=  "Web: " + resource.WWWEB3 + "<br />";
			}
			writeableLine +=  "Categories: " + resource.Type1 + "<br />";
			if (resource.Type2) {
				writeableLine +=  "&nbsp;&nbsp&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" + resource.Type2 + "<br />";
			}
			if (resource.Type3) {
				writeableLine +=  "&nbsp;&nbsp&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" + resource.Type3 + "<br />";
			}
			if (resource.Type4) {
				writeableLine +=  "&nbsp;&nbsp&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" + resource.Type4 + "<br />";
			}
			if (resource.Description) {
				writeableLine +=  resource.Description + "<br />";
			}
			top.copyableListWindow.document.writeln(
				writeableLine + "<br /><br />"
			)
		});

		top.copyableListWindow.document.writeln(
		   '</body></html>'
		);
		top.copyableListWindow.document.close();
	};
}

function displayRecord(searchObject, indexNo) {
	var resource = searchObject[indexNo].resource;
	if(resource['Local'] == "Y") {
		var color = 'red';
	} else if (resource['Country'] == "Canada" || resource['Country'] == "CANADA") {
		var color= 'green';
	} else if (resource['NonLGBT'] == "Y") {
		var color= 'blue';
	} else {
		var color = 'black';
	}
	for (var key in resource) {
		field = document.getElementById("resourceDetail" + key);
		if (field) {
			if(field.tagName === "INPUT" || field.tagName === "TEXTAREA") {
				field.value = resource[key];
			} else {
				if(key=="WWWEB" || key=="WWWEB2" || key=="WWWEB3") {
					if(resource[key]) {
						var web = document.createElement("a");
						web.appendChild(document.createTextNode(resource[key]));
						web.setAttribute("href","http://" + resource[key]);
						web.setAttribute("target","_blank");
						field.innerHTML = "";
						field.setAttribute("title",resource[key]);
						field.appendChild(web);
					} else {
						field.innerHTML = "";
						field.appendChild(document.createTextNode(" "));
					}
				} else if (key == "idnum" ) {
					var idField = "Id. No.: " + resource[key];
					field.innerHTML = "";
					field.appendChild(document.createTextNode(idField));					
				} else if (key == "Note" ) {
				    resource[key] = resource[key].replace(/\n/g, "\n").replace(/\r/g, "\r").replace(/\t/g, "\t");				
					field.innerHTML = "";
					field.appendChild(document.createTextNode(resource[key]));
				} else if (key == "Edate") {
					var updatedField = "Last Updated: " + resource[key];
					field.innerHTML = "";
					field.appendChild(document.createTextNode(updatedField));				
				} else {
					field.innerHTML = "";
					field.appendChild(document.createTextNode(resource[key]));
				}
			}	
	
			if (field.id == 'resourceDetailName') {
				field.style.color = color;
				field.title = resource[key];
			}
			if (field.id == 'resourceDetailDistance') {
				if(resource[key] == -1) {
					field.innerHTML = 'n/a';
				} else if(resource[key] == 1 && searchObject.requestType != 'national') {
					field.appendChild(document.createTextNode(" Mile"));
				} else if (resource[key] != "N/A") {
					field.appendChild(document.createTextNode(" Miles"));
				}
			}
		}
		if (resource[key]) {
			switch(key) {
				case 'Country':			
					resource[key] = resource[key].toUpperCase();
				case 'Address2':
				case 'Location':
				case 'Zip':			
					document.getElementById("resourceDetailAddress1").appendChild(document.createTextNode(resource[key] + " "));
					break;
			}
			switch(key) {		
				case 'Address1':
				case 'Address2':
					document.getElementById("resourceDetailAddress1").appendChild(document.createElement("br"));
					break;
			}
		}
	}
	
	var first = document.getElementById('resourceDetailFirstButton');
	first.onclick = function () {displayRecord(searchObject, 0);};
	var pre = document.getElementById('resourceDetailPreviousButton');
	pre.onclick = function () {displayRecord(searchObject, indexNo - 1);};
	var next = document.getElementById('resourceDetailNextButton');
	next.onclick = function () {displayRecord(searchObject, indexNo + 1);};
	var resourceShowListButton = document.getElementById('resourceShowListButton');
	resourceShowListButton.onclick = function () {viewControl('resourceList');};
	
	//Display resourceDetail form and hide resource list
//	viewControl('resourceDetail');
		
	var location = resource['Address1'] + " " + resource['Address2'] + " " + resource['City'] + ", " + resource['State'];
	var streetviewLabel = document.getElementById("resourceDetailLabelStreetview");
	document.getElementById("resourceDetailLabelNote").click();
	document.getElementById("resourceDetailStreetview").innerHTML = "";
	streetviewLabel.onclick = function() {
		viewControl("resourceDetailLabelStreetview");
		geocodeAddress(location);
	};

	// Check call log checkboxes for resource categories
	if (resource['Type1']) {
		markCallLog(resource['Type1']);
	}
	if (resource['Type2']) {
		markCallLog(resource['Type2']);
	}

	return;
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
	this.url = "volunteerPosts.php";
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
			iframe.title = "Info Center Content";
			iframe.style.height = "inherit";
			iframe.style.width = "inherit";
			infoCenterText.appendChild(iframe);
		} else {
			resultObject.infoCenterText.innerHTML = results;
			resultObject.infoCenterText.scrollTop -= resultObject.infoCenterText.scrollHeight;
		}
	};

	this.infoCenterClose = function () {
		var infoCenterText = document.getElementById("infoCenterText");
		removeElements(infoCenterText)
//		viewControl("infoCenterClose", this.oldPane);
	};

	this.infoCenterDisplay = function () {
		var resourceDetail = document.getElementById('resourceDetail');
		var resourceList = document.getElementById('resourceList');
		var searchBoxPane = document.getElementById("newSearchPane");
		
		if (resourceDetail.style.display && resourceDetail.style.display != "none") {
			this.oldPane = resourceDetail;
		} else if (resourceList.style.display && resourceList.style.display != "none") {
			this.oldPane = resourceList;		
		} else if (searchBoxPane.style.display && searchBoxPane.style.display != "none") {
			this.oldPane = searchBoxPane;
		}
		viewControl("infoCenter");
	};


	this.infoCenterMenu.onclick = function() {
		self.infoCenterDisplay();
	};
	

}

function RotateWelcomeSlides() {
	var self = this;
	this.currentSlide = 0;
	this.slideFileNameList = {};
	this.params = "postType=welcomeSlides";
	this.url = "volunteerPosts.php";
	this.welcomeSlideDiv = document.getElementById("volunteerMessageSlide");
	this.rotateIntervalTime = 35000;
	this.rotateInterval = "";

	
	this.init = function() {
		console.log("RotateWelcomeSlides.init() called");
		console.log("URL:", self.url);
		console.log("Params:", self.params);
		try {
			var welcomeSlidesRequest = new AjaxRequest(self.url, self.params, self.welcomeSlidesResponse , self);
			console.log("Welcome slides AjaxRequest created");
		} catch (error) {
			console.error("Error creating welcome slides AjaxRequest:", error);
		}
	};


	this.welcomeSlidesResponse = function(results, resultObject) {
		console.log("welcomeSlidesResponse called with results:", results);
		try {
			self.slideFileNameList = JSON.parse(results);
			console.log("Parsed slide list:", self.slideFileNameList);
			self.startRotateSlides();
		} catch (error) {
			console.error("Error in welcomeSlidesResponse:", error);
		}				
	};

	this.startRotateSlides= function() {
		self.welcomeSlideDiv.src = self.slideFileNameList[self.currentSlide];
		if(self.currentSlide < self.slideFileNameList.length - 1) {
			self.currentSlide += 1;
		} else {
			self.currentSlide = 0;
		}
		self.rotateInterval = setTimeout("welcomeSlideRotation.startRotateSlides()",self.rotateIntervalTime);
	};
	
	this.stopRotateSlides = function() {
		clearTimeout(self.rotateInterval);
	};
		
}

function countries() {	
	var url= "countries.php";
	var countriesResultFunction = function(result) {countriesResult(result);}; 
	var countriesRequest = new AjaxRequest(url, " " , countriesResultFunction, this);
}

function countriesResult(results) {
	var responseDoc = JSON.parse(results);
	countryList = document.getElementById("internationalSearch");
	countryList.innerHTML = "";
	for (i=0;i<responseDoc.length;i++) {
		entry = responseDoc[i];
		if(i == 0) {
			entry = "INTERNATIONAL";
		}
		option = document.createElement("option");
		option.value = entry;
		option.appendChild(document.createTextNode(entry));
		if(entry && entry != " ") {
			countriesList[countryList.childNodes.length] = entry;
			countryList.appendChild(option);
		}
	}
	
	countryList.onchange = function () {
		validate(this.id);
		countryList.selectedIndex = 0;

	};
}



	
//Streetview Functions
function geocodeAddress(address) {

  accuracy = null;
  geocoder.geocode( { 'address': address}, function(results, status) {

    if (status == google.maps.GeocoderStatus.OK) {
	    endLocation = results[0].geometry.location;
      
		//Check the area of the returned object to see if results are accurate enough to display streetview
		var north = results[0].geometry.viewport.getNorthEast().lat();
		var south = results[0].geometry.viewport.getSouthWest().lat();
		var east = results[0].geometry.viewport.getNorthEast().lng();
		var west = results[0].geometry.viewport.getSouthWest().lng();

		var accuracyArea = ((east-west)*( 6378137*Math.PI/180 ) )*Math.cos( north*Math.PI/180 );

      if(results[0].geometry.location_type != "ROOFTOP" && accuracyArea > 500) {
		accuracy = results[0];
	  }
	  var addressParts = results[0].address_components;
	  
      for (var i=0; i<addressParts.length; i++) {
		if (addressParts[i].types[0] == "locality") { 
			addressCity = addressParts[i].long_name; 
		} else if (addressParts[i].types[0] == "administrative_area_level_1") {
			var addressState = addressParts[i].long_name;
		}
	  }  

	  addressCity = addressCity + ", " + addressState;    

	  var request = {
		  origin: addressCity,
		  destination: endLocation,
		  travelMode: google.maps.TravelMode.DRIVING
	  };

	  // Route the directions and pass the response to a
	  // function to find the final street stop.
	  directionsService.route(request, function(response, status) {
		  if (status == google.maps.DirectionsStatus.OK) {
			  endLocation = findEndLocation(response, accuracy);
		}
	  });
	}
	});
}

function findEndLocation(directionResult, accuracy) {
  	var myRoute = directionResult.routes[0].legs[0];
  	var lastStep = myRoute.steps.length - 1;
	var cameraLocation =  myRoute.steps[lastStep].end_location;
	var angle = 0;
	var angle = computeAngle(endLocation, cameraLocation);

	if (!accuracy) {
		var panoramaOptions = {
			position: cameraLocation,
			pov: {
			  heading: angle,
			  pitch: 0
			}
		}
		var panorama = new  google.maps.StreetViewPanorama(document.getElementById('resourceDetailStreetview'),panoramaOptions);
	} else {
		var mapOptions = {
			zoom: 15,
			center: accuracy.geometry.location,
			mapTypeId: google.maps.MapTypeId.ROADMAP
		}

		var map = new google.maps.Map(document.getElementById('resourceDetailStreetview'),mapOptions);
		
//		var boundsData = accuracy.geometry.viewport;
//		accuracyArea = new google.maps.Rectangle({
//			strokeColor: '#FF0000',
//			strokeOpacity: 0.8,
//			strokeWeight: 2,
//			fillColor: '#FF0000',
//			fillOpacity: 0.35,
//			map: map,
//			bounds: boundsData
//		});
		
//		accuracyArea.setMap(map);

	}	
}

function computeAngle(endLatLng, startLatLng) {
      var DEGREE_PER_RADIAN = 57.2957795;
      var RADIAN_PER_DEGREE = 0.017453;
 
      var dlat = endLatLng.lat() - startLatLng.lat();
      var dlng = endLatLng.lng() - startLatLng.lng();
      // We multiply dlng with cos(endLat), since the two points are very closeby,
      // so we assume their cos values are approximately equal.
      var yaw = Math.atan2(dlng * Math.cos(endLatLng.lat() * RADIAN_PER_DEGREE), dlat)
             * DEGREE_PER_RADIAN;
      return wrapAngle(yaw);
}
 
function wrapAngle(angle) {
    if (angle >= 360) {
        angle -= 360;
    } else if (angle < 0) {
        angle += 360;
    }
    return angle;
}

	
	
	
// Sort and View Control
function sortBy(searchObject, sortKey) {
	var arr = [];
	for (var key in searchObject) {
		var obj = searchObject[key];
		var idnum = key;
	
		for (key in obj) {
			var sortData = obj[key];
			if (key == sortKey) {
				arr.push({
					'sortKey':	sortData,
					'distance':	obj.Distance,
					'name':		obj.Name,
					'resource':	obj});
			}
		}
	}
	
	if(sortKey === 'Distance') {
		arr.sort(function (a, b) {
			if(a.distance === b.distance) { 
				var x = a.name.toLowerCase();
				var y = b.name.toLowerCase();

				return x < y ? -1 : x > y ? 1 : 0;
			}
			return a.distance - b.distance; 
		});
	} else {	
		arr.sort(function (a, b) {
			if(a.sortKey === b.sortKey) { 
				if(a.distance === b.distance) { 
					var x = a.name.toLowerCase();
					var y = b.name.toLowerCase();

					return x < y ? -1 : x > y ? 1 : 0;
				}
				return a.distance - b.distance; 
			}

			var x = a.sortKey.toLowerCase();
			var y = b.sortKey.toLowerCase();

			return x < y ? -1 : x > y ? 1 : 0;
		});
	}

	return arr; // returns array
}

function viewControl(showPanel, oldPanel) {
	var resourceDetail = document.getElementById('resourceDetail');
	var resourceList = document.getElementById('resourceList');
	var searchBox = document.getElementById("searchBox");
	var searchBoxPane = document.getElementById("newSearchPane");
	var resourceDetailControl = document.getElementById("resourceDetailControl");
	var infoCenterPane = document.getElementById("infoCenterPane");
	var callPane = document.getElementById("callPane");
	var mainControls = document.getElementById("newSearchPaneControls");
	var infoCenterButton = 	document.getElementById("infoCenterMenu");
	var chatPane = document.getElementById("chatPane");
	var callHistoryPane = document.getElementById("callHistoryPane");
	var copyableList = document.getElementById("copyableList");
	var callHistoryData = document.getElementById("callHistoryData");
	var timelinePane = document.getElementById("timelinePane");
	var timelineData = document.getElementById("timelineData");
	var nonSageAge = document.getElementById("nonSageAge");
	var nonSageDiscoveredCategories = document.getElementById("nonSageDiscoveredCategories");
	var StreetPanel = document.getElementById("resourceDetailStreetview");
	var NotePanel = document.getElementById("resourceDetailNote");
	var StreetLabel = document.getElementById("resourceDetailLabelStreetview");
	var NoteLabel = document.getElementById("resourceDetailLabelNote");


	if(showPanel === "Close") {
		showPanel = olderPanel;
		currentPanel = showPanel;
	} else {
		priorOldPanel = olderPanel;
		olderPanel = currentPanel;
		currentPanel = showPanel;
	
	}
	
	if(currentPanel === olderPanel) {
		olderPanel = priorOldPanel;
	}
	
	removeElements(timelineData);
	timelinePane.style.display = "none";
	callPane.style.display = "none";
	resourceDetail.style.display = 'none';
	resourceList.style.display = 'none';
	searchBox.style.display = 'none';
	searchBoxPane.style.display = 'none';
	searchBoxPane.style.color = null;
	searchBoxPane.style.background = null;
	infoCenterPane.style.display = "none";
	resourceDetailControl.style.visibility = 'hidden';
	infoCenterButton.style.visibility = "visible";
	chatPane.style.display = "block";
	if(copyableList) {
		copyableList.style.visibility = "hidden";
	}
	
	
	if(!newCall) {
		callHistoryData.style.display = "block";
		callHistoryPane.style.display = "none";
		mainControls.style.visibility = "visible";
	}

	if(!oldPanel) {
		oldPanel = "main";
	}
	
	switch(showPanel) {
		case 'resourceList':
			resourceList.style.display = 'block';
			if (copyableList) {
				copyableList.style.visibility = "visible";
			}
			break;
		case 'resourceDetail':
			resourceDetail.style.display = 'block';
			resourceDetailControl.style.visibility = 'visible';
			break;
		case 'searchBox':
			searchBox.style.display = 'block';
			searchBoxPane.style.display = 'block';
			document.getElementById("Distance").value = "100";
			document.getElementById("ZipCode").focus();
			break;
		case 'infoCenter':
			document.getElementById("infoCenterMenu").style.visibility = "hidden";
			infoCenterPane.style.display = "block";
			var infoCenterClose = document.getElementById("infoCenterClose");
			infoCenterClose.onclick = function() {
				newInfoCenter.infoCenterClose();
				viewControl("Close");
			};
			break;
		case 'main':
			document.getElementById("logPane").style.display = null;
			searchBoxPane.style.display = 'block';
			searchBoxPane.style.color = null;
			searchBoxPane.style.background = null;
			if(newCall) {
				viewControl('searchBox');
			}
			break;

		case 'resourceDetailLabelNote':
			viewControl("resourceDetail");
			NoteLabel.style.background = "maroon";
			NoteLabel.style.color = "white";
			StreetLabel.style.background = null;
			StreetLabel.style.color = null;
			var StreetPanel = document.getElementById("resourceDetailStreetview");			
			NotePanel.style.display = "inline-block";
			StreetPanel.style.display = "none";
			break;
			
		case 'resourceDetailLabelStreetview':
			viewControl("resourceDetail");
			StreetPanel.style.display = "inline-block";
			NotePanel.style.display = "none";
			StreetLabel.style.background = "maroon";
			StreetLabel.style.color = "white";
			NoteLabel.style.background = null;
			NoteLabel.style.color = null;
			break;

		case 'callPane':
			if(!newCall) {
				if(olderPanel && olderPanel !== "callPane") {
					viewControl(olderPanel);
				} else {
					viewControl("main");
				}
				break;
			}
			callPane.style.display = 'block';
			searchBoxPane.style.display = "block";
			searchBoxPane.style.color = "black";
			searchBoxPane.style.background = "yellow";
			mainControls.style.visibility = "hidden";
			infoCenterButton.style.visibility = "hidden";
			chatPane.style.display = "none";
			callHistoryPane.style.display = "block";
			callHistoryPane.style.color = null;
			nonSageDiscoveredCategories.style.visibility = "visible";
			nonSageAge.style.display = null;
			// Play ringing sound AFTER buttons are visible
			var ringingSound = document.getElementById("chatSound");
			if (ringingSound) {
				// Handle promise returned by play() - catches autoplay policy blocks
				var playPromise = ringingSound.play();
				if (playPromise !== undefined) {
					playPromise.catch(function(error) {
						console.warn("🔔 Ringing sound blocked by browser autoplay policy:", error.message);
						// Note: User needs to interact with page before audio can play
					});
				}
			}
			break;

		case 'timelinePane':
			if (resourceDetail.style.display && resourceDetail.style.display != "none") {
				oldPanel = "resourceDetail";
			} else if (resourceList.style.display && resourceList.style.display != "none") {
				oldPanel = "resourceList";		
			} else if (searchBoxPane.style.display && searchBoxPane.style.display != "none") {
				oldPanel = "searchBox";
			} else if (infoCenterPane.style.display && infoCenterPane.style.display != "none") {
				oldPanel = "infoCenter";
			}
		

			timelinePane.style.display = "block";
			var iframe = document.createElement("iframe");
			iframe.setAttribute("id",'timelineFrame');
			iframe.setAttribute("src","Stats/timeline.php?pixelsPerMinute=5");
			iframe.setAttribute("title","Call Timeline");
			timelineData.appendChild(iframe);
			var timelineClose = document.getElementById("timelineClose");
			timelineClose.onclick = function() {
				viewControl("Close");
			};
			break;
			
		default:
			viewControl(oldPanel,oldPanel);
			break;

	}
}

function sleep(delay) {
    var start = new Date().getTime();
    while (new Date().getTime() < start + delay);  
}

function validate(button) {
	console.log("validate() called with button:", button);
	
	var zip = 		document.getElementById("ZipCode").value;
	var distance = 	document.getElementById("Distance").value;
	var city =		document.getElementById("City").value;
	var state = 	document.getElementById("State").value;
	var name = 		document.getElementById("Name").value;

	console.log("Search params:", {zip, distance, city, state, name});

	document.getElementById("Distance").blur();

	switch(button) {
		case 'nationalSearch':
			newSearch = new Search("","All",0,"","","","national");
			newSearch.searchRequest();
			break;
			
		case 'internationalSearch':
			var countryName = countriesList[document.getElementById("internationalSearch").selectedIndex];
			if (countryName == "Canada") {
				alert("Please use the normal search boxes for Canadian resources.  Canadian postal codes work the same as Zip Codes.");
				return;
			}
			newSearch = new Search("","All",0,"","",countryName,"international");
			newSearch.searchRequest();
			break;
			
		case 'findZipSearch':
			newSearch = new Search("","All",distance,city,state,name,"findZip");
			newSearch.searchRequest();
			break;
			
		default:
			console.log("Creating new Search with default case");
			try {
				newSearch = new Search(zip,"All",distance,city,state,name);
				console.log("Search object created:", newSearch);
				newSearch.searchRequest();
				console.log("searchRequest() called");
			} catch (error) {
				console.error("Error in search:", error);
				if (typeof showComprehensiveError === 'function') {
					showComprehensiveError(
						'Resource Search Error',
						'Failed to execute search: ' + error.message,
						{
							error: error,
							additionalInfo: 'An error occurred while performing the resource search. Please try again or use different search criteria.'
						}
					);
				} else {
					alert("Search error: " + error.message);
				}
			}
			break;
	}
	

	var byName = document.getElementById('sortByNameButton');
	byName.onclick = function () {
		newSearch.sortOrder = 'Name';
		newSearch.displayResults("");
	};
	var byDistance = document.getElementById('sortByDistanceButton');
	byDistance.onclick = function () {
		newSearch.sortOrder = 'Distance';
		newSearch.displayResults("");
	};
	var byType1 = document.getElementById('sortByType1Button');
	byType1.onclick = function () {
	};
	var byType2 = document.getElementById('sortByType2Button');
	byType2.onclick = function () {
	};
	var byLocation = document.getElementById('sortByLocationButton');
	byLocation.onclick = function () {
		newSearch.sortOrder = 'Location';
		newSearch.displayResults("");
	};
	var byZip = document.getElementById('sortByZipButton');
	byZip.onclick = function () {
		newSearch.sortOrder = 'Zip';
		newSearch.displayResults("");
	};


	// Handle "ALL" button click - search all categories
	var AllButton = document.querySelector(".category-button[data-category='All']");
	if (AllButton) {
		AllButton.onclick = function() {
			markCallLog("All");
			newSearch.category = "All";
			delete(newSearch.results);
			newSearch.selectToOpen = null;
			document.getElementById("Menus").reset();
			newSearch.postSearch();
		};
	}

	// Handle standalone category button clicks (categories without subtypes)
	var CategoryButtons = document.querySelectorAll(".category-button:not([data-category='All'])");
	for (var i = 0; i < CategoryButtons.length; i++) {
		CategoryButtons[i].onclick = function() {
			var category = this.getAttribute("data-category");
			markCallLog(category);
			newSearch.category = category;
			delete(newSearch.results);
			newSearch.selectToOpen = null;
			document.getElementById("Menus").reset();
			newSearch.postSearch();
		};
	}

	// Handle trigger button clicks - search then open dropdown
	var CategoryTriggers = document.querySelectorAll(".category-trigger");
	for (var i = 0; i < CategoryTriggers.length; i++) {
		var trigger = CategoryTriggers[i];
		trigger.onclick = function() {
			var category = this.getAttribute("data-category");
			var selectMenu = this.nextElementSibling;

			markCallLog(category);
			newSearch.category = category;
			delete(newSearch.results);

			// Store the select menu to open after results load
			newSearch.selectToOpen = selectMenu;
			newSearch.postSearch();
		};
	}

	// Handle select menu changes - search for selected subcategory
	var CategoryMenus = document.getElementById("Categories").getElementsByTagName("select");
	for (var i = 0; i<CategoryMenus.length; i++) {
		var Category = CategoryMenus[i];

		Category.onchange = function () {
			var CategoryIndex = this.selectedIndex;

			markCallLog(this.name);

			var Category = this.getElementsByTagName("option")[CategoryIndex].value;
			newSearch.category = Category;
			delete(newSearch.results);

			// Clear the selectToOpen flag and disable pointer events again
			newSearch.selectToOpen = null;
			this.style.pointerEvents = "none";

			document.getElementById("Menus").reset();
			newSearch.postSearch();
		};
	}
}

function markCallLog(Category) {
    if (Category != "All") {
		dashposition = Category.indexOf("-");

		if (dashposition == -1) {
			markCategory = Category;
		} else {
			markCategory = Category.substring(0,dashposition);
		}

		switch(markCategory) {
			case 'Aids':            	markCategory = "AIDS";
										break;
				
			case 'Bar/Club':            markCategory = "Bars";
										break;
				
			case 'Community Center':    markCategory = "Community";
										break;
				
			case 'Fundraising':         markCategory = "Fundraise";
										break;
		}                  
										 
		fields = document.getElementById("activeLogPane").getElementsByTagName("input");
		for (var i=0; i<fields.length; i++) {
			item = fields[i];
			if (item.name == markCategory) {
				item.checked = true;
			}
		}
    }
}

function CallLog(log) {
	var notesPane = document.getElementById("logPaneNotes");
	notesPane.style.visibility = null;
	this.log = 		log;
	this.saveable = false;
	document.getElementById('callLogID').value = this.log;
	this.logPane = 	document.getElementById("activeLogPane");
	this.inputElements = logPane.getElementsByTagName("input");
	this.selectElements = logPane.getElementsByTagName("select");
	this.callLogNotes = logPane.getElementsByTagName("textarea");
	this.color = "";
	this.logData = 	{};
	this.url = 'postCallLog.php';
	this.params = 'callLogID=' + this.log;
	if(this.log == 3) {
		if(newCall.clientCallerSid) {
			this.callSid = newCall.clientCallerSid;
		} else {
			this.callSid = "No CallSid";
		}
	} else {
		var notesPane = document.getElementById("logPaneNotes");
		notesPane.style.visibility = "hidden";
		this.callSid = newChat.chats[log].callerID;
	}
	var genderMenu = document.getElementById('callLogGenderMenu');
	var genderDisplayMessage = document.getElementById('genderDisplayMessage');
	genderDisplayMessage.innerHTML = "";

	genderMenu.onchange = function() {
		var genderDisplayMessage = document.getElementById('genderDisplayMessage');
		var gender = genderMenu.selectedIndex;
		switch(gender) {
			case 0:
				genderDisplayMessage.innerHTML = "";
				break;
			case 1:
				genderDisplayMessage.innerHTML = "<strong>Gender:</strong></br />MALE";
				break;
			case 2:
				genderDisplayMessage.innerHTML = "<strong>Gender:</strong></br />FEMALE";
				break;
			case 3:
				genderDisplayMessage.innerHTML = "<strong>Gender:</strong></br />NON-BINARY";
				break;
			case 4:
				genderDisplayMessage.innerHTML = "<strong>Gender:</strong></br />QUESTIONING";
				break;
			default:
				genderDisplayMessage.innerHTML = "";
				break;
		}
	};
	
	this.setHotline = function (hotline) {
		switch(hotline) {
			case 'GLNH': 
				document.getElementById("GLBTNHC_Program").selectedIndex = 1;
				break;
			case 'Youth': 
				document.getElementById("GLBTNHC_Program").selectedIndex = 2;
				break;
			case 'GLSB-NY': 
				document.getElementById("GLBTNHC_Program").selectedIndex = 3;
				break;
			case 'Chat': 
				document.getElementById("GLBTNHC_Program").selectedIndex = 4;
				break;
			case 'SENIOR': 
				document.getElementById("GLBTNHC_Program").selectedIndex = 5;
				break;
			case 'OUT': 
				document.getElementById("GLBTNHC_Program").selectedIndex = 6;
				break;
		}
	};
	
	this.loadToArray = function () {
		var j=this.inputElements.length;

		for (var i=0; i<j; i++) {
			var element = this.inputElements[i];
			var name = element.name.toString();
			switch(element.type) {
				case 'button':
					break;
				case 'checkbox':
					if(element.checked) {
						var value = 1;
					} else {
						var value = 0;
					}
					this.logData[name] = value;
					break;
				case 'radio':
					if(element.checked) {
						var value = element.value;
					} 
					this.logData[name] = value;
					break;
			}
		}
		
		var j=this.selectElements.length;
		for (var i=0; i<j; i++) {
			var element = this.selectElements[i];
			var selection = element.selectedIndex;
			var options = element.getElementsByTagName('option');
			var value = encodeURIComponent(options[selection].value);
			this.logData[element.name] = value;
		}
		
		var j=this.callLogNotes.length;
		for (var i=0; i<j; i++) {
			var element = this.callLogNotes[i];
			var value = encodeURIComponent(element.value);
			this.logData[element.name] = value;
		}	
	};
	
	
	this.loadFromArray = function () {		
		var j=this.inputElements.length;

		for (var i=0; i<j; i++) {
			var element = this.inputElements[i];
			var name = element.name.toString();
			var value = element.value;
			switch(element.type) {
				case 'button':
					break;
				case 'checkbox':
					if(this.logData[name] == 1) {
						element.checked = true;
					} else {
						element.checked = false;
					}
					break;
				case 'radio':
					if(this.logData[name] == value) {
						element.checked = true;
					} else {
						element.checked = false;
					}
					break;
			}
		}
		document.getElementById('callLogID').value = this.log;

		
		var j=this.selectElements.length;
		for (var i=0; i<j; i++) {
			var element = this.selectElements[i];
			var options = element.getElementsByTagName('option');
			for (var k=0; k<options.length; k++) {
				option = options[k];
				if(option.value == this.logData[element.name]) {
					element.selectedIndex = k;
				}
			}				
		}
	
		var j=this.callLogNotes.length;
		for (var i=0; i<j; i++) {
			var element = this.callLogNotes[i];
			var name = element.name.toString();
			element.value = this.logData[name];
		}	
	};
	
	this.validateLog = function (self) {
		if(!this.saveable) {
			alert("You cannot save the log while the call or chat is in progress.");
			return;
		}

		var j=this.selectElements.length;
		for (var i=0; i<j; i++) {
			var element = this.selectElements[i];
			switch(element.name) {
				case 'GLBTNHC_Program':
					if(this.log == '3' && element.selectedIndex == '4') {
						alert("The Hotline cannot say 'Chat' for a phone call. Please correct.");
						return;
					}
					var fieldName = "Hotline";
					break;
					
				case 'VolunteerRating':
					var fieldName = "Call Quality";
					break;

				default:
					var fieldName = element.name;
			}

			var selection = element.selectedIndex;
			if((fieldName == "Age" || 
				fieldName == "GOT_NUMBER" || 
				fieldName == "Gender" || 
				fieldName == "VolunteerRating" || 
				fieldName == "GLBTNHC_Program" || 
				fieldName == "callHistoryCountry" ) && (selection == 0 || !selection)) {
					if(element.id != 'callHistoryCountry') {
						alert("Please complete the " + fieldName + " field.");
						return;
					}
			}
					
		}
		
		var j=this.inputElements.length;
		var gotNumberSelected = 0;
		
		for (var i=0; i<j; i++) {
			item = this.inputElements[i];		
			if (item.name == "GOT_NUMBER" && item.checked) {
				gotNumberSelected = 1;
			}	
		}

		if(gotNumberSelected==0) {
			alert("How did the caller hear about us?");
			return;
		}
		
		var j=this.callLogNotes.length;
		for (var i=0; i<j; i++) {
			var element = this.callLogNotes[i];
			if (element.value.length == 0 && this.log == 3) {
				alert("Please enter some notes about the call.");
				return;
			}
		}	
		
		this.saveLog(self);
	};


	this.cancelLog = function (self, Type) {
		if(!this.saveable) {
			alert("You cannot cancel the log while the call or chat is in progress.");
			return;
		}
		this.saveLog(self, Type);
	};
	
	
	this.saveLog = function (self, type) {
		this.loadToArray();
		if(this.log == 1) {
			otherChat = document.getElementById("chatBody2");
		} else if (this.log == 2) {
			otherChat = document.getElementById("chatBody1");
		}
		var resultsFunction = function (results, searchObject) {
			testResults(results, searchObject);
		};
		var params = this.params;
		var obj = this.logData;
		var zipCode = document.getElementById("logZip").value;
		var city = document.getElementById("logCity").value;
		var state = document.getElementById("logState").value;
		for (key in obj) {
			params = params + "&" + key + "=" + encodeURIComponent(obj[key]);
		}
		if (type) {
			params = params + "&terminate=" + type + "&zipCode=" + zipCode + "&city=" + city + "&state=" + state + "&callSid=" + encodeURIComponent(this.callSid);
		} else {
			params = params + "&terminate=SAVE" + "&zipCode=" + zipCode + "&city=" + city + "&state=" + state + "&callSid=" + encodeURIComponent(this.callSid);
		}		

		var postLog = new AjaxRequest(this.url, params, resultsFunction);
		logPaneForm = document.getElementById("logPaneForm");
		logPaneForm.reset();
		this.logData = {};
		if(this.log < 3) {
			this.clearChat(self);
		} else {
			this.clearCall(self);
		}
		var genderDisplayMessage = document.getElementById('genderDisplayMessage');
		genderDisplayMessage.innerHTML = "";

	};
	
	
	this.clearChat = function (self) {
		var clearingLog = self;
		var room = clearingLog.chatLog.log;
		if(this.log == 1) {
			thisChat = document.getElementById("chatBody1");
			otherChat = document.getElementById("chatBody2");
			otherLog = newChat.chats[2];
		} else if (this.log == 2) {
			thisChat = document.getElementById("chatBody2");
			otherChat = document.getElementById("chatBody1");
			otherLog = newChat.chats[1];
		}
		thisChat.onclick = "";
		if(otherLog) {
			otherChat.click();
		} else {
			this.logPane.style.display = "none";
			this.logPane.style.backgroundColor = null;
			document.getElementById('logPane').style.backgroundColor = null;
		}
		if(timers[this.log]) {
			timers[this.log].clear();
		}
		
		clearingLog.chatBody.innerHTML = "";
		clearingLog.chatMessage.value = "";
		clearingLog.chatMessage.style.visibility = "hidden";
		clearingLog.chatPost.onclick = "";
		clearingLog.endChatButton.onclick = "";
		delete(newChat.chats[room]);

		var url = 'volunteerPosts.php';
		var resultsObject = new Object();
		var postResults = function (results, searchObject) {
			testResults(results, searchObject);
		};
		params = "postType=clearChat&action=" + encodeURIComponent(this.log);
		var clearChat = new AjaxRequest(url, params, postResults, resultsObject);
		viewControl('main');
	};	

	
	this.clearCall = function (self) {
		activeLogPane.style.display = null;
		viewControl('main');
		if(newCall) {
			newCall.clearCall();
		}
		if(trainingSession) {
			trainingSession.currentlyOnCall = false;
		}
	};
	
	if(this.log < 3) {
		this.setHotline("Chat");
	}

}



//  CHAT OBJECTS AND FUNCTIONS
function Chat(name) {
	this.name = name;
	this.chatMessage = document.getElementById("chatMessage" + name);
	this.chatBody = document.getElementById("chatBody" + name);

	this.chatControls = document.getElementById("chat" + name + "Controls");
	this.cannedTextMenu = document.getElementById("chat" + name + "Select");
	this.endChatButton = document.getElementById("chat" + name + "EndButton");
	this.chatPost = document.getElementById("chat" + name + "PostButton");

	this.chatLog = null;
	this.cannedText = {};
	this.chatMessages = {};
	this.color = "white";	
	this.time =	"";
	this.callerBrowserData = "";
	this.hotline = {};
	this.hotline.id = "Chat";
	this.callerID = "";


	this.init = function(invite) {
		var self = this;
		var room = self.name;
		this.initCannedText();
		this.groupChatTransferMessage = invite.groupChatTransferMessage;

		this.cannedTextMenu.onchange = function() {
			self.cannedTextDisplay(this.selectedIndex);
		};
		this.endChatButton.onclick = function () {
			self.volunteerEndChat();
		};
		this.chatPost.onclick = function () {
			self.postMessage();
		};
				
		if(room == 1) {
			this.color = "rgba(255,255,225,1)";
		} else if (room == 2) {
			this.color = "rgba(225,255,255,1)";
		}
		this.chatMessage.onkeypress = function (e) {
			if (!e) {
				var e = window.event;
			}
			if (e.keyCode) {
				code = e.keyCode;
			} else if (e.which) {
				code = e.which;
			}
		
			if(code == 13) {
				self.postMessage();
				return false;
			}	
			if(code == 10) {
				self.postMessage();
				return false;
			}
		};
	};


	this.initCannedText = function () {
		var cannedTextParams = "postType=cannedChatText";
		var cannedTextURL = "volunteerPosts.php";
		var cannedTextResponseObject = this.cannedText;
		var cannedText = new AjaxRequest(cannedTextURL, cannedTextParams, this.cannedTextResponse,this);
	};
	
		
	this.cannedTextResponse = function(results, resultObject) {
		resultObject.cannedText = JSON.parse(results);
		this.cannedText = JSON.parse(results);
	};


	this.cannedTextDisplay = function (message) {
		this.chatMessage.value = this.cannedText[message].text;
		this.cannedTextMenu.selectedIndex = 0;
	};

	
	this.invite = function (message) {
		this.callerID = message.roomid;
		chatInviteObject = this;
		var actionType = "";
		var url = 'volunteerPosts.php';
		var resultsObject = new Object();
		var clickResults = function (results, searchObject) {
			testResults(results, searchObject);
		};
		
		var gabeInviteSound = document.getElementById("inviteSound");
		if(gabeInviteSound) {
			gabeInviteSound.play();
		}

		// Screen reader announcement for chat invitation
		var chatAnnouncement = 'New chat invitation received.';
		if (message.groupChatTransferMessage) {
			chatAnnouncement = 'Group chat transfer received.';
		}
		announceToScreenReader(chatAnnouncement, 'polite');

		mainParams = "postType=chatInvite&text=" + encodeURIComponent(message.room);

		this.newButton = function(type) {
			var self = this;
			body = this.chatBody;
			var button = document.createElement("input");
			button.type="button";
			button.id=type + "Chat";
			button.value = type + " Chat";
			if(type == "Accept") {
				button.setAttribute("class" , "defaultButton");
			}
			var params = mainParams + "&action=" + type;
			button.onclick = function () {
				var chatResponse = new AjaxRequest(url, params, clickResults, resultsObject);
				body = document.getElementById("chatBody" + message.room);
				body.innerHTML = "";
				if(type == "Reject") {
					callMonitor.getDevice().disconnectAll();
					chatInviteObject.chatControls.style.visibility = "hidden";
					chatInviteObject.chatMessage.style.visibility = "hidden";
					delete(newChat.chats[message.room]);
				} else {
					self.showChat();
					var p = document.createElement("p");
					var strong = document.createElement("strong");
					strong.appendChild(document.createTextNode("Caller Browser: "));
					p.appendChild(strong);
					p.appendChild(document.createTextNode(self.callerBrowserData));
					p.style.width = "350px;"
					p.style.textAlign = "center";
					body.appendChild(p);
				}

			};
			body.appendChild(button);
		    var ringing = document.getElementById("chatSound");
			if (ringing) {
				var playPromise = ringing.play();
				if (playPromise !== undefined) {
					playPromise.catch(function(error) {
						console.warn("🔔 Chat ringing sound blocked:", error.message);
					});
				}
			}

		};

		if(message.groupChatTransferMessage) {
			body = document.getElementById("chatBody" + message.room);
			var title = document.createElement("h2");
			title.style.width = "100%";
			title.style.fontWeight = "bold";
			title.style.textAlign = "center";
			title.style.textDecoration = "underline";
			title.appendChild(document.createTextNode("GROUP CHAT TRANSFER"));
			var messageText = document.createElement("p");
			messageText.style.textAlign = 'center';
			messageText.appendChild(document.createTextNode(message.groupChatTransferMessage));
			body.appendChild(title);
			body.appendChild(messageText);
		}
			
		if (!document.getElementById("AcceptChat")) {
			this.newButton("Accept");
		}
		if (!document.getElementById("RejectChat")) {
			this.newButton("Reject");
		}
		
	};	


	this.createChatLog = function () {
		var self = this;
		var room = self.name;
		var color = this.color;
		if(self.name == 1) {
			var otherChat = 2;
		} else if (self.name == 2) {
			var otherChat = 1;
		}
		this.chatLog = new CallLog(self.name);

		this.chatBody.onclick = function () {
			if(activeLogPane.class != room) {
				logPane.style.backgroundColor = color;
				activeLogPane.style.backgroundColor = color;
				if(newChat.chats[otherChat]) {
					newChat.chats[otherChat].chatLog.loadToArray();
				}
				document.getElementById("logPaneForm").reset();
				self.chatLog.loadFromArray();
				document.getElementById('callLogSaveButton').onclick = function () {
					self.chatLog.validateLog(self);
				};
				document.getElementById('callLogCancelButton').onclick = function () {
					self.chatLog.cancelLog(self, "Cancel");
				};
				document.getElementById('NuisanceButton').onclick = function () {
					self.chatLog.cancelLog(self, "Nuisance");
				};
				document.getElementById('HangupButton').onclick = function () {
					self.chatLog.cancelLog(self, "Hangup");
				};
				document.getElementById('AbusiveButton').onclick = function () {
					self.chatLog.cancelLog(self, "Abusive");
				};
				activeLogPane.class = room;
			}
		};

		this.chatBody.click();

		activeLogPane.class = room;	
		logPane.style.backgroundColor = color;
		activeLogPane.style.backgroundColor = color;
		activeLogPane.style.display = 'block';

		var nonSageDiscoveredCategories = document.getElementById("nonSageDiscoveredCategories");
		nonSageDiscoveredCategories.style.display = null;
		nonSageDiscoveredCategories.style.visibility = null;

		nonSageDiscoveredCategories.style.visibility = "visible";
		nonSageAge.style.display = null;

		if(!timers[self.name]) {
			timers[self.name] = new Timer(self.name);
			timers[self.name].start();
		}
		this.chatLog.setHotline(this.hotline.id);

	};


	this.volunteerEndChat = function () {
		var url = 'volunteerPosts.php';
		var resultsObject = new Object();
		var postResults = function (results, searchObject) {
			testResults(results, searchObject);
		};
		params = "postType=endChat&action=" + encodeURIComponent(this.name);
		var endChat = new AjaxRequest(url, params, postResults, resultsObject);
	};
	
	
	this.endChat = function (message) {
		this.chatControls.style.visibility = "hidden";
		this.chatMessage.style.visibility = "hidden";
		this.chatPost.onclick = function() {
			alert("The chat has ended.  You can no longer post messages. Please log the chat.");
		};
		this.chatLog.saveable = true;
		this.chatBody.style.backgroundColor=null;
		if(timers[this.name]) {
			timers[this.name].stop();
		}
	};

	
	this.postMessage = function () {
		var self = this;  // Capture chat room reference for callback
		var text = this.chatMessage.value;
		text = text;
		if (text) {
			var url = 'volunteerPosts.php';
			var resultsObject = new Object();
			var postResults = function (results, searchObject) {
				// Handle "inactive chatroom" error gracefully - chat ended while typing
				if (results && typeof results === 'string' && results.indexOf('inactive chatroom') !== -1) {
					self.endChat();
					alert("The chat has ended. You can no longer post messages. Please log the chat.");
					return;
				}
				testResults(results, searchObject);
			};
			params = "postType=postMessage&action=" + encodeURIComponent(this.name) + "&text=" + encodeURIComponent(text);
			var chatPost = new AjaxRequest(url, params, postResults, resultsObject);
			this.chatMessage.value = '';
		}
	};

	
	this.hideChat = function () {
		this.chatBody.style.display = "none";
		this.chatControls.style.visibility = "hidden";
		this.chatMessage.style.visibility = "hidden";
	};


	this.showChat = function () {
		this.chatBody.style.display = "block";
		this.chatControls.style.visibility = "visible";
		this.chatMessage.style.visibility = "visible";
	};


	this.processChatMessage = function (message) {
		var typing = document.getElementById("typing" + message.room);
		var spacer = document.getElementById("spacer" + message.room);
		if(message.callerTyping != "1") {
			if(typing) {
				this.chatBody.removeChild(typing);
			}
			if(spacer) {
				this.chatBody.removeChild(spacer);
			}
		}
		var existingMessage = this.chatMessages[message.id];
		var existingMessageParagraph = document.getElementById(message.id);
		if(!this.chatLog) {
			this.createChatLog();
		}
		var url = "volunteerPosts.php";
		var	params = "postType=messageStatusUpdate&action=" + message.id;
		var updateMessageStatusResults = function (results, searchObject) {
			testResults(results, searchObject);
		};
		var p = document.createElement("p");
		message.text = decodeURIComponent(message.text);


		if(!existingMessageParagraph) {
			this.chatMessages[message.id] = message.text;
			p.style.backgroundColor = 'rgba(200,200,200,1)';
			p.style.padding = "5px";
			p.style.borderRadius = '12px';
			p.style.width = "400px";
			p.style.marginBottom = "-6px";
			p.setAttribute('id',message.id);
			message.time =		new Date();
			p.title = "Received: " + message.time.toLocaleTimeString();
			p.appendChild(document.createTextNode(message.text));


			if (message.name == 'Volunteer') {
				p.setAttribute("title","Posted: " + message.time.toLocaleTimeString());
				this.chatBody.style.backgroundColor=null;
				p.style.marginLeft = "35px";
				p.style.backgroundColor = 'rgba(250,150,250,.35)';
				params += "&text=" + message.name + "-confirmed";
				var updateMessageStatus = new AjaxRequest(url, params, updateMessageStatusResults);
			}
			if(message.name == 'Caller' && message.id != "typing" + message.room) {
				if(message.room == 1) {
					var gabeSound = document.getElementById("message1Sound");
					if(gabeSound) {
						gabeSound.play();
					}
				} else if (message.room == 2) {
					var gabeSound = document.getElementById("message2Sound");
					if(gabeSound) {
						gabeSound.play();
					}
				}
				params += "&text=Volunteer-confirmed";
				this.chatBody.style.backgroundColor="yellow";
				var updateMessageStatus = new AjaxRequest(url, params, updateMessageStatusResults);
			}
			this.chatBody.appendChild(p);
			this.chatBody.scrollTop += body.scrollHeight + 10;
		} else {
			if(message.name = 'Volunteer' && message.callerDelivered == 1) {
				message.time =		new Date();
				existingMessageParagraph.style.backgroundColor = 'rgba(200,200,250,1)';
				existingMessageParagraph.title = "Delivered: " + message.time.toLocaleTimeString();
				params += "&text=Caller-confirmed";
				var updateMessageStatus = new AjaxRequest(url, params, updateMessageStatusResults);
			}
	
			if (message.name == 'Volunteer' && message.volunteerDelivered == 1) {
				params += "&text=Volunteer-confirmed";
				var updateMessageStatus = new AjaxRequest(url, params, updateMessageStatusResults);
			}
		}	
	};
}

function ChatMonitor(name) {
    this.chats = {};
    this.oneChatOnly = null;
    this.cannedText = {};
    this.source = "";
    this.connectionStartTime = null;
    this.reconnectTimer = null;
    this.maxConnectionDuration = 300000; // 5 minutes
    this.isPageVisible = true;
    this.reconnectAttempts = 0;
    this.maxReconnectDelay = 30000; // 30 seconds max backoff

    this.init = function () {
        // Skip EventSource if Redis polling is enabled
        if (localStorage.getItem('usePolling') === 'true') {
            console.log('EventSource: Skipped - Redis polling is enabled');
            return;
        }

        if(typeof(EventSource)!=="undefined") {
            // Close existing connection if any
            if(this.source) {
                this.source.close();
            }

            // **NEW EVENTSOURCE SERVER SUPPORT WITH FALLBACK**
            // Check localStorage to use new Node.js EventSource server or fallback to old PHP
            // Default: false (old system) until Apache proxy is configured by Cloudways
            const useNewEventSource = localStorage.getItem('useNewEventSource') === 'true';
            let eventSourceURL;
            let isNewServer = false;

            if (useNewEventSource) {
                // Try new Node.js EventSource server via Apache reverse proxy
                // Requires Cloudways to configure: ProxyPass /eventsource http://127.0.0.1:3000/events
                eventSourceURL = '/eventsource';
                isNewServer = true;
                console.log('EventSource: Attempting connection to new Node.js server via Apache proxy');
            } else {
                // Use old PHP EventSource endpoint
                eventSourceURL = 'vccFeed.php?reset=1';
                console.log('EventSource: Using legacy PHP endpoint');
            }

            this.source = new EventSource(eventSourceURL);
            const source = this.source;
            this.connectionStartTime = Date.now();

            // Setup auto-reconnect timer (5 minutes)
            if(this.reconnectTimer) {
                clearTimeout(this.reconnectTimer);
            }
            this.reconnectTimer = setTimeout(() => {
                console.log("EventSource: Auto-reconnecting after 5 minutes");
                this.reconnect();
            }, this.maxConnectionDuration);

            // Handle successful connection
            source.addEventListener('open', () => {
                if (isNewServer) {
                    console.log("EventSource: ✅ Connected to NEW Node.js server (Redis Pub/Sub)");
                } else {
                    console.log("EventSource: Connected to legacy PHP endpoint");
                }
                this.reconnectAttempts = 0; // Reset backoff on success
            }, false);

            // Handle connection errors with exponential backoff and fallback
            source.addEventListener('error', (event) => {
                if (source.readyState === EventSource.CLOSED) {
                    console.log("EventSource: Connection closed");

                    // If new server failed, fallback to old PHP endpoint
                    if (isNewServer && this.reconnectAttempts === 0) {
                        console.warn("EventSource: New server unavailable, falling back to legacy PHP endpoint");
                        localStorage.setItem('useNewEventSource', 'false');
                        this.reconnect(); // Will use old endpoint on reconnect
                    } else {
                        console.log("EventSource: Reconnecting...");
                        this.reconnectWithBackoff();
                    }
                } else if (source.readyState === EventSource.CONNECTING) {
                    console.log("EventSource: Reconnecting...");
                }
            }, false);

            // Logout Handler - server detected LoggedOn=0 for this user
            // This catches cascade logouts (e.g., trainer exited, admin force-exit)
            source.addEventListener('logout', (event) => {
                console.log("EventSource: Received logout event from server - clearing session and redirecting");
                // Close the EventSource to prevent reconnection loop
                source.close();
                if (!exiting) {
                    exiting = true;
                    window.onbeforeunload = null;
                    // Clear the PHP session so login.php doesn't redirect back
                    // Use sendBeacon for reliable delivery during page unload
                    const clearData = new FormData();
                    clearData.append('postType', 'clearSession');
                    navigator.sendBeacon('volunteerPosts.php', clearData);
                    // Brief delay to let sendBeacon fire, then redirect
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 200);
                }
            }, false);

            // Chat Message Handler
            source.addEventListener('chatMessage', (event) => {
                const message = JSON.parse(event.data);        
                newChat.chats[message.room].processChatMessage(message);
            }, false);

            // Typing Status Handler            
            source.addEventListener('typingStatus', (event) => {
                const message = JSON.parse(event.data);
                if(message.callerTyping == 1) {
                    message.name = "Caller";
                    message.text = "...";
                    message.id = "typing" + message.room;
                    newChat.chats[message.room].processChatMessage(message);
                } else if (message.callerTyping == 0) {
                    const typing = document.getElementById("typing" + message.room);
                    if(typing) {
                        document.getElementById("chatBody" + message.room).removeChild(typing);
                    }
                }                
            }, false);

            // User List Handler - Major updates for multi-trainee support
            source.addEventListener('userList', (event) => {
                // Reset fallback timer - this is a FALLBACK only, not the primary reconnection mechanism
                // EventSource handles normal reconnection automatically when vccFeed.php's 30-second loop ends
                // 60-second timeout only triggers if something is wrong and no data is received
                if(chatMonitorKeepAlive != null) clearTimeout(chatMonitorKeepAlive);
                chatMonitorKeepAlive = setTimeout("newChat.init();", 60000);

                const messages = JSON.parse(event.data);
                const onlineUsers = new Set();
                
                // Process each user message
				messages.forEach(message => {
					let userRecord = userList[message.UserName];
					onlineUsers.add(message.UserName);
					
					// Create or update user record
					if(!userRecord) {
						userList[message.UserName] = new User(message);
						userRecord = userList[message.UserName];
					} else {
						userRecord.updateUser(message);
					}
					
					// Handle admin user creation
					if(message.AdminLoggedOn == 2 || message.AdminLoggedOn == 7) {
						if(!userList["Admin"]) {        
							const adminUser = {
								UserName: "admin",
								name: "admin",
								FirstName: "Administrative",
								LastName: "IM's"
							};
							userList["Admin"] = new User(adminUser);
						}
					}
				
					// Process training session updates (only for trainers/trainees)
					const currentUserLoggedOn = document.getElementById("userLoggedOn") ? parseInt(document.getElementById("userLoggedOn").value) : 0;
					if(window.trainingSession && message.AdminLoggedOn && 
					   (currentUserLoggedOn === 4 || currentUserLoggedOn === 6)) {
						// For trainees signing on (AdminLoggedOn === 6)
						if(message.AdminLoggedOn === 6 && trainingSession.role === "trainer") {
							// Find if this is one of our trainees
							const trainee = trainingSession.trainees.find(t => t.id === message.UserName);
							if(trainee && !trainee.isSignedOn) {
								// Trainee is signing on, update our records
								trainingSession.traineeSignedOn(message.UserName, message.FirstName + " " + message.LastName);
								console.log(`Trainee ${message.UserName} has signed on`);
							}
						} 
						// For trainers signing on (AdminLoggedOn === 4)
						else if(message.AdminLoggedOn === 4 && trainingSession.role === "trainee") {
							// Check if this is our trainer signing on
							if(message.TrainerID === trainingSession.trainerID && !trainingSession.trainerIsSignedOn) {
								trainingSession.trainerSignedOn(message.UserName, message.FirstName + " " + message.LastName);
								console.log(`Trainer ${message.UserName} has signed on`);
							}
						}
					}
				
					// Update user display
					const userDisplayed = document.getElementById(message.UserName);
					if(userDisplayed) {
						this.updateUserDisplay(userDisplayed, userRecord, message);
					} else if (this.shouldDisplayUser(message)) {
						this.createUserDisplay(userRecord, message);
					}
				});

                // Handle training session state updates (only for trainers/trainees)
			  // Process training session updates
			   const userLoggedOnStatus = document.getElementById("userLoggedOn") ? parseInt(document.getElementById("userLoggedOn").value) : 0;
			   if (window.trainingSession && 
			       (userLoggedOnStatus === 4 || userLoggedOnStatus === 6)) {
					// For trainers - check which trainees are online/offline
					if (trainingSession.role === "trainer") {
						trainingSession.trainees.forEach(trainee => {
							const traineeStillOnline = messages.some(msg => 
								msg.AdminLoggedOn == 6 && msg.UserName === trainee.id
							);
							
							// Trainee just went offline
							if (!traineeStillOnline && trainee.isSignedOn) {
								trainingSession.traineeSignedOff(trainee.id);
							}
							
							// Trainee just came online 
							const traineeJustSignedOn = messages.some(msg => 
								msg.AdminLoggedOn == 6 && 
								msg.UserName === trainee.id && 
								!trainee.isSignedOn
							);
							
							if (traineeJustSignedOn) {
								const traineeData = messages.find(msg => msg.UserName === trainee.id);
								trainingSession.traineeSignedOn(
									traineeData.UserName, 
									traineeData.FirstName + " " + traineeData.LastName
								);
							}
						});
					}
					
					// For trainees - check if trainer is online/offline
					if (trainingSession.role === "trainee") {
						// Check if trainer is online
						const trainerMessages = messages.filter(msg => 
							msg.AdminLoggedOn == 4 && msg.TrainerID === trainingSession.trainerID
						);
						
						const trainerOnline = trainerMessages.length > 0;
						
						if (!trainerOnline && trainingSession.trainerIsSignedOn) {
							trainingSession.trainerSignedOff();
						} else if (trainerOnline && !trainingSession.trainerIsSignedOn) {
							const trainerData = trainerMessages[0];
							trainingSession.trainerSignedOn(
								trainerData.UserName,
								trainerData.FirstName + " " + trainerData.LastName
							);
						}
					}
					
                    if (trainingSession.connectionStatus === 'ready') {
                        trainingSession.connection = false;
                        trainingSession.connectConference();
                    }										
				}
                // Clean up users who are no longer online
                this.cleanupOfflineUsers(onlineUsers);
            }, false);

            // Other event handlers remain largely unchanged
            source.addEventListener('IM', (event) => {
                const message = JSON.parse(event.data);
                userList[message.from].receiveIm(message);        
            }, false);

            source.addEventListener('logoff', (event) => {
                if(event.data == "0" && !exiting) {
                    window.onbeforeunload = "";
                    exitProgram("admin").then(() => {
                      console.log('Exit completed');
                    });
                }                
            }, false);

            // Chat management event handlers
            this.setupChatEventHandlers(source);

        } else {
            document.getElementById("result").innerHTML = "Sorry, your browser does not support server-sent events...";
        }
    };

    // Helper methods for user list processing
    // LoggedOn values: 1=volunteer, 2=admin, 4=trainer, 6=trainee, 7=admin mini, 8=group chat monitor, 9=resource admin
    this.shouldDisplayUser = function(message) {
        const displayableLoggedOn = [1, 2, 4, 6, 7, 8, 9];
        // Handle both number and string types from JSON
        const loggedOn = parseInt(message.AdminLoggedOn, 10);
        return displayableLoggedOn.includes(loggedOn);
    };

    this.updateUserDisplay = function(userDisplayed, userRecord, message) {
        userDisplayed.childNodes[1].innerHTML = " ";
        const onChatCell = userDisplayed.childNodes[3];
        const onCallCell = userDisplayed.childNodes[2];

        if(monitor == "1") {
            this.updateMonitorDisplay(onCallCell, onChatCell, userRecord);
        } else {
            this.updateNormalDisplay(onCallCell, onChatCell, userRecord);
        }
    };

    this.cleanupOfflineUsers = function(onlineUsers) {
        for(const currentUser in userList) {
            const user = userList[currentUser];
            if(!onlineUsers.has(user.userName) && user.userName !== "admin") {
                // Screen reader announcement for volunteer going offline
                const currentUserID = document.getElementById("volunteerID") ? document.getElementById("volunteerID").value : '';
                if (user.userName !== currentUserID && previousVolunteerList[user.userName]) {
                    announceToScreenReader(user.name + ' is now offline.', 'polite');
                    delete previousVolunteerList[user.userName];
                }

                // Clean up UI elements
                const userElement = document.getElementById(user.userName);
                if(userElement && userElement.parentNode) {
                    userElement.parentNode.removeChild(userElement);
                }
                delete userList[currentUser];
            }
        }
    };

    // Setup chat-related event handlers
    this.setupChatEventHandlers = function(source) {
        source.addEventListener('chatInvite', (event) => {
            const invite = JSON.parse(event.data);
            if(invite.room === 0) {
                this.handleChatRejection();
                return;
            }
            if(!newChat.chats[invite.room]) {
                this.initializeNewChat(invite);
            }
        }, false);

        source.addEventListener('chatEnd', (event) => {
            const message = JSON.parse(event.data);
            newChat.chats[message.room].endChat(message);

            // Send the actual end time to the server to set chat1End/chat2End session variable
            // This captures the exact time the chatter ended the chat (not form submission time)
            if (message.chatEndTime) {
                var url = 'volunteerPosts.php';
                var params = "postType=chatterEndedChat&room=" + encodeURIComponent(message.room) +
                             "&endTime=" + encodeURIComponent(message.chatEndTime);
                var captureEndTime = new AjaxRequest(url, params, function(results) {
                    // End time captured silently
                });
            }
        }, false);

        source.addEventListener('chatActive', (event) => {
            if(newChat.oneChatOnly && !newChat.chats[2]) {
                newChat.oneChatOnlyChange();
            } else if(newChat.chats[event.data].saveable === false) {
                newChat.chats[event.data].showChat();
            }
        }, false);

        // Empty handlers maintained for compatibility
        source.addEventListener('chatBlocked', () => {}, false);
        source.addEventListener('chatOpen', () => {}, false);
    };

    // Chat management methods
    this.handleChatRejection = function() {
        const acceptChat = document.getElementById("AcceptChat");
        if(acceptChat) {
            const buttonParent = acceptChat.parentNode;
            buttonParent.innerHTML = "";
            if(buttonParent.id === "chatBody1" && newChat.chats[1]) {
                delete newChat.chats[1];
            } else if(buttonParent.id === "chatBody2" && newChat.chats[2]) {
                delete newChat.chats[2];
            }
        }
    };

    this.initializeNewChat = function(invite) {
        newChat.chats[invite.room] = new Chat(invite.room);
        newChat.chats[invite.room].init(invite);
        newChat.chats[invite.room].callerBrowserData = 
            `${invite.ComputerType} ${invite.callerOS} -- ${invite.browser} ${invite.browserVersion}`;
        newChat.chats[invite.room].invite(invite);
    };

    // One Chat Only functionality
    this.oneChatOnlyChange = function() {
        const chatOnlyBox = document.getElementById("oneChatOnly");
        const params = "postType=oneChatOnly&action=" + (chatOnlyBox.checked ? "1" : "null");
        const url = "volunteerPosts.php";
        
        this.oneChatOnly = chatOnlyBox.checked ? 1 : null;
        new AjaxRequest(url, params, testResults);

        this.updateChatDisplay();
    };

    // Reconnect with exponential backoff
    this.reconnectWithBackoff = function() {
        if(this.source) {
            this.source.close();
        }

        // Calculate backoff delay: 1s, 2s, 4s, 8s, 16s, 30s (max)
        const delay = Math.min(
            1000 * Math.pow(2, this.reconnectAttempts),
            this.maxReconnectDelay
        );
        this.reconnectAttempts++;

        console.log(`EventSource: Reconnecting in ${delay/1000}s (attempt ${this.reconnectAttempts})`);

        setTimeout(() => {
            if(this.isPageVisible) {
                this.init();
            }
        }, delay);
    };

    // Clean reconnect (no backoff)
    this.reconnect = function() {
        console.log("EventSource: Clean reconnect");
        if(this.source) {
            this.source.close();
        }
        if(this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
        }
        this.init();
    };

    // Page visibility handling - pause when tab hidden
    if(typeof document.hidden !== "undefined") {
        document.addEventListener("visibilitychange", () => {
            if(document.hidden) {
                console.log("EventSource: Tab hidden, pausing connection");
                this.isPageVisible = false;
                if(this.source) {
                    this.source.close();
                }
                if(this.reconnectTimer) {
                    clearTimeout(this.reconnectTimer);
                }
            } else {
                console.log("EventSource: Tab visible, resuming connection");
                this.isPageVisible = true;
                this.init();
            }
        }, false);
    }

    // CRITICAL: Close EventSource when page unloads to prevent orphaned PHP processes
    window.addEventListener('beforeunload', () => {
        if (this.source) {
            console.log("EventSource: Closing connection on page unload");
            this.source.close();
        }
        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
        }
    });

    window.addEventListener('pagehide', () => {
        if (this.source) {
            console.log("EventSource: Closing connection on pagehide");
            this.source.close();
        }
    });

    // Chat display management
    this.updateChatDisplay = function() {
        const elements = {
            body: document.getElementById("chatBody1"),
            body2: document.getElementById("chatBody2"),
            message2: document.getElementById("chatMessage2"),
            controls2: document.getElementById("chat2Controls")
        };

        if(this.oneChatOnly && !this.chats[2]) {
            elements.body.style.height = "480px";
            elements.body2.style.display = "none";
            elements.message2.style.display = "none";
            elements.controls2.style.display = "none";
        } else {
            elements.body.style.height = null;
            elements.body2.style.display = null;
            elements.message2.style.display = null;
            elements.controls2.style.display = null;
        }
    };

    // Display methods
    this.createUserDisplay = function(userRecord, message) {
        const userTable = document.getElementById('volunteerListTable');
        const tr = document.createElement("tr");

        // Screen reader announcement for new volunteer coming online
        // Only announce if the user is not the current logged-in user
        const currentUserID = document.getElementById("volunteerID") ? document.getElementById("volunteerID").value : '';
        if (userRecord.userName !== currentUserID && !previousVolunteerList[userRecord.userName]) {
            announceToScreenReader(userRecord.name + ' is now online.', 'polite');
        }
        previousVolunteerList[userRecord.userName] = true;

        if(monitor != "1") {
            tr.onclick = function() {
                userRecord.loadImPane();
            };
            tr.title = "Click to Send an IM to this Volunteer.";    
            tr.setAttribute("class", "hover");                        
        }
        
        tr.setAttribute("id", userRecord.userName);
        
        // Name cell
        const nameCell = document.createElement("td");
        if(monitor == "1") {
            nameCell.title = "Click to Send an IM to this Volunteer.";    
            nameCell.setAttribute("class", "hover");                        
            nameCell.onclick = function() {
                userRecord.loadImPane();
            };
        }

        if(message.AdminLoggedOn == 2 || message.AdminLoggedOn == 7 || message.AdminLoggedOn == 8) {
            nameCell.style.color = "blue";
        } else if (message.AdminLoggedOn == 9) {
            nameCell.style.color = "green";
        }
        nameCell.appendChild(document.createTextNode(userRecord.name));
        tr.appendChild(nameCell);

        // Spacer cell
        const spacerCell = document.createElement("td");
        spacerCell.appendChild(document.createTextNode(" "));
        tr.appendChild(spacerCell);

        // Status cell
        const statusCell = document.createElement("td");
        let displayOnCallText = userRecord.onCall;
        if(displayOnCallText == "GLNH") {
            displayOnCallText = "LGBTQ";
        } else if (displayOnCallText && displayOnCallText.substring(0, 3) == "OUT") {
            displayOnCallText = "OUT";                        
        }
        statusCell.appendChild(document.createTextNode(displayOnCallText));
        tr.appendChild(statusCell);
        
        // Chat cell
        const chatCell = document.createElement("td");
        if(monitor != "1") {
            chatCell.appendChild(document.createTextNode(userRecord.chat));
        }
        tr.appendChild(chatCell);
        
        // Append to tbody for proper table structure (accessibility)
        const tbody = userTable.querySelector('tbody') || userTable;
        tbody.appendChild(tr);
    };

    this.updateMonitorDisplay = function(onCallCell, onChatCell, userRecord) {
        if(userRecord.onCall == "YES" && !userRecord.beingMonitored) {
            onCallCell.innerHTML = "";
            onCallCell.id = userRecord.userName + "Monitor";
            onCallCell.title = "Click to Monitor This Volunteer's Call";
            const parameters = { agent: userRecord.userName };
            onCallCell.setAttribute("class", "hover");                        
            onCallCell.style.borderRadius = "5px";
            onCallCell.style.border = "2px solid black";

            onCallCell.onclick = () => {
                userRecord.beingMonitored = true;
                onCallCell.innerHTML = "";
                
                try {
                    const device = callMonitor.getDevice();
                    device.connect(parameters);
                    
                    onCallCell.title = "Click to End Monitoring of This Volunteer's Call";
                    onCallCell.style.border = "2px solid yellow";
                    onCallCell.onclick = () => {
                        userRecord.beingMonitored = false;
                        const device = callMonitor.getDevice();
                        device.disconnectAll();
                        onCallCell.title = "";
                        onCallCell.style.background = null;
                        onCallCell.style.border = null;
                    };
                    onCallCell.appendChild(document.createTextNode("End Monitoring"));
                } catch (error) {
                    console.error("Error starting call monitoring:", error);
                    userRecord.beingMonitored = false;
                }
            };

            onCallCell.appendChild(document.createTextNode("Monitor Call"));

        } else if(userRecord.onCall != "YES" && !userRecord.beingMonitored) {
            onCallCell.id = "";
            onCallCell.setAttribute("class", "unhover");                        
            onCallCell.title = "";
            onCallCell.style.background = null;
            onCallCell.style.borderRadius = null;
            onCallCell.style.border = null;
            onCallCell.innerHTML = userRecord.onCall;

            if(userRecord.beingMonitored) {
                userRecord.beingMonitored = false;
                try {
                    const device = callMonitor.getDevice();
                    device.disconnectAll();
                } catch (error) {
                    console.error("Error ending call monitoring:", error);
                }
                onCallCell.title = "";
                onCallCell.style.background = null;
                onCallCell.style.border = null;
            }
            onCallCell.onclick = null;
        }
    };

    this.updateNormalDisplay = function(onCallCell, onChatCell, userRecord) {
        if(userRecord.adminRinging == "Watching Video") {
            onCallCell.innerHTML = "Video";
        } else {
            let displayOnCallText = userRecord.onCall.replace(/[^A-Za-z]+/g, '');

            if(displayOnCallText == "GLNH") {
                displayOnCallText = "LGBTQ";
            } else if (displayOnCallText.substring(0, 3) == "OUT") {
                displayOnCallText = "OUT";                        
            }

            onCallCell.innerHTML = displayOnCallText;
            onChatCell.innerHTML = userRecord.chat;
        }
    };
}



// TRAINING
function trainingChat(role) {
    const width = screen.availWidth;
    const height = screen.availHeight;


    if(role == "trainer") {
        mytrainingChatwindow = window.open(
          "TrainingChat/index.php",
          "Training Chat",
          `location=1,status=1,scrollbars=1,width=400,height=${height},top=0,left=0`
        );
    } else {
        mytrainingChatwindow = window.open(
          "TrainingChat/chatFrame.php",
          "Training Chat",
          `location=1,status=1,scrollbars=1,width=400,height=${height},top=0,left=0`
        );
    }
}

// Function to show alerts with a fade
function showAlert(message) {
    // Create alert element if it doesn't exist
    let alertElement = document.getElementById('trainingAlert');
    if (!alertElement) {
        alertElement = document.createElement('div');
        alertElement.id = 'trainingAlert';
        alertElement.style.position = 'fixed';
        alertElement.style.top = '20px';
        alertElement.style.left = '50%';
        alertElement.style.transform = 'translateX(-50%)';
        alertElement.style.padding = '10px 20px';
        alertElement.style.backgroundColor = 'rgba(0,0,0,0.8)';
        alertElement.style.color = 'white';
        alertElement.style.borderRadius = '5px';
        alertElement.style.zIndex = '9999';
        alertElement.style.opacity = '0';
        alertElement.style.transition = 'opacity 0.3s ease-in-out';
        document.body.appendChild(alertElement);
    }
    
    // Show the message
    alertElement.textContent = message;
    alertElement.style.opacity = '1';
    
    // Hide after 3 seconds
    setTimeout(() => {
        alertElement.style.opacity = '0';
    }, 3000);
}

// MONITORING CHATS
function MonitorChats(userID) {
	var self = this;
	this.chats = {};
	this.monitoredUser = userID;
	this.chats[1] = {};
	this.chats[2] = {};
	this.chats[1].chatMessages = {};
	this.chats[2].chatMessages = {};


	this.init = function() {
		var params = "postType=monitorChatStart";
		var url = "volunteerPosts.php";
		var monitorChatResults = function (results) {testResults(results);};

		params += "&action=" + this.monitoredUser;
		var monitorChatResults = new AjaxRequest(url, params, monitorChatResults);
		var userDisplayed = document.getElementById(this.monitoredUser);
		var onChatCell = userDisplayed.childNodes[3];
		var userID = this.monitoredUser;

		while(onChatCell.hasChildNodes()) {     
			onChatCell.removeChild(onChatCell.childNodes[0]);
		}

		onChatCell.appendChild(document.createTextNode("End Monitoring"));
		onChatCell.id = this.monitoredUser.userName + "Monitor";
		onChatCell.title = "Click to Stop Monitoring This Volunteer's Chats";
		onChatCell.setAttribute("class" , "hover");						
		onChatCell.style.borderRadius = "5px";
		onChatCell.style.border = "2px solid black";
		onChatCell.onclick = function() {
			clearInterval(monitorChatInterval);
			newMonitorChats = "";
			userList[self.monitoredUser].chatBeingMonitored = false;
			element = document.getElementById("chatBody1");
			while(element.hasChildNodes()) {     
				element.removeChild(element.childNodes[0]);
			}
			element = document.getElementById("chatBody2");
			while(element.hasChildNodes()) {     
				element.removeChild(element.childNodes[0]);
			}

			element = onChatCell;
			while(element.hasChildNodes()) {     
				element.removeChild(element.childNodes[0]);
			}
			
			onChatCell.title = "Click to Monitor This Volunteer's Chats";
			onChatCell.style.border = "2px solid yellow";
			onChatCell.onclick = function() {
				newMonitorChats = new MonitorChats(userID);
				newMonitorChats.init();
			}					
			onChatCell.title = "";
			onChatCell.style.background = null;
			onChatCell.style.border = null;
		}	
		monitorChatInterval = setInterval("newMonitorChats.monitorChatMessages();",2000);		
	};

		

	this.monitorChatMessages = function() {
		var self = this;
		var params = "monitoredUser=" + this.monitoredUser;
		var url = "monitorChat.php";
		var monitorChatResults = function (results) {self.monitorChatProcessMessages(results);};
		var monitorChatMessagesResults = new AjaxRequest(url, params, monitorChatResults);
	};

	this.monitorChatProcessMessages = function(results) {
		var self = this;
		results = JSON.parse(results);
		if(results) {
			results.forEach(function(message) {
				self.postMessage(message);
			});
		}
	};

	this.postMessage = function(message) {
		var self = this;
		var typing = document.getElementById("typing" + message.room);
		var spacer = document.getElementById("spacer" + message.room);
		this.chatBody = document.getElementById("chatBody" + message.room);
	
		if(message.callerTyping != "1") {
			if(typing) {
				self.chatBody.removeChild(typing);
			}
			if(spacer) {
				self.chatBody.removeChild(spacer);
			}
		}
		var existingMessage = self.chats[message.room].chatMessages[message.id];
		var existingMessageParagraph = document.getElementById(message.id);
		var p = document.createElement("p");
		message.text = decodeURIComponent(message.text);

		if(!existingMessageParagraph) {
			self.chats[message.room].chatMessages[message.id] = message.text;
			p.style.backgroundColor = 'rgba(200,200,200,1)';
			p.style.padding = "5px";
			p.style.borderRadius = '12px';
			p.style.width = "400px";
			p.style.marginBottom = "-6px";
			p.setAttribute('id',message.id);
			message.time =		new Date();
			p.title = "Received: " + message.time.toLocaleTimeString();
			p.appendChild(document.createTextNode(message.text));


			if (message.name == 'Volunteer') {
				p.setAttribute("title","Posted: " + message.time.toLocaleTimeString());
				self.chatBody.style.backgroundColor=null;
				p.style.marginLeft = "35px";
				p.style.backgroundColor = 'rgba(250,150,250,.35)';
			}
			if(message.name == 'Caller' && message.id != "typing" + message.room) {
//				self.chatBody.style.backgroundColor="yellow";
			}
			self.chatBody.appendChild(p);
			self.chatBody.scrollTop += self.chatBody.scrollHeight + 10;
		} else {
			if(message.name = 'Volunteer' && message.callerDelivered == 1) {
				message.time =		new Date();
				existingMessageParagraph.style.backgroundColor = 'rgba(200,200,250,1)';
				existingMessageParagraph.title = "Delivered: " + message.time.toLocaleTimeString();
			}

			if (message.name == 'Volunteer' && message.volunteerDelivered == 1) {
			}
		}	
	};
}

function monitorChatEnd(elementID,parameters) {
	this.monitoredUser = null;
	var params = "postType=monitorChatEnd";
	var url = "volunteerPosts.php";
	var monitorChatResults = function (results) {testResults(results);};

	var monitorChatResults = new AjaxRequest(url, params, monitorChatResults);
	document.getElementById("chatBody1").innerHTML = "";
	document.getElementById("chatBody2").innerHTML = "";
	newChat.chats[1] = null;
	newChat.chats[2] = null;
}

function endMonitoring(elementID, parameters) {
	var element = document.getElementById(elementID);
	callMonitor.getDevice().disconnectAll();
	element.title = "Click to Monitor of This Volunteer's Call";
	element.style.background = null;
	element.onclick = function() {monitorCall(elementID,parameters);} 
}

function callAnswerResults(results, resultsObject) {
	if(results && results != "OK") {
		if(results != "The call was already answered by another volunteer") {
			//alert("System/Twilio Error: " + results);
		}
		newCall.answerButton.setAttribute("src","Images/answer.png");
		newCall.answerButton.setAttribute("class","answerCallLeft");
		newCall.rejectButton.style.visibility = null;
		newCall.cancelCall();
		deleteCallObject();
	} else {
		setTimeout("newCall.continueCall()",2000);
	}
}





// USER FUNCTIONS
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
	div2.setAttribute("draggable" , "true");
	div2.addEventListener('dragstart',drag_start,false); 
	div2.onclick = function () {locateImPane(user);}; 

	div2.style.display = "none";
	div2.appendChild(h2);
	div2.appendChild(div);
	div2.appendChild(form);
	frag.appendChild(div2);
	document.getElementsByTagName("body")[0].appendChild(frag);
	return div2;
}

function User(user) {

	this.userName = user.UserName;
	var self = this;
	this.callObject = "";
	this.volunteerID = document.getElementById("volunteerID").value;

	if(!user.pronouns) {
		this.name = user.FirstName + " " + user.LastName;
	} else {
		this.name = user.FirstName + " " + user.LastName + " (" + user.pronouns + ")";
	}

	this.shift = user.Shift;
	this.onCall = " ";
	this.chat = user.Chat;
	this.AdminLoggedOn = user.AdminLoggedOn;
	this.traineeID = user.TraineeID;
	this.muted = user.Muted;
	this.im = [];
	this.beingMonitored = false;
	this.chatBeingMonitored = false;
	this.incomingCall = false;
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

		// REMOVED: Database-driven control detection
		// Control is now managed purely client-side
		
		
		if(user.Chat != " " && monitor == "1" && !self.chatBeingMonitored) {
			var userChatCell = document.getElementById(user.UserName).childNodes[3];
			element = userChatCell;
			while(element.hasChildNodes()) {     
				element.removeChild(element.childNodes[0]);
			}
			userChatCell.appendChild(document.createTextNode("Monitor Chats"));
			userChatCell.title = "Click to Monitor This Volunteer's Chat";
			userChatCell.setAttribute("class" , "hover");						
			userChatCell.onclick = function() {
				self.chatBeingMonitored = true;
				newMonitorChats = new MonitorChats(user.UserName);
				newMonitorChats.init();
			}
		}
		
        const userLoggedOnValue = document.getElementById("userLoggedOn") ? parseInt(document.getElementById("userLoggedOn").value) : 0;
        if(trainingSession && 
           (userLoggedOnValue === 4 || userLoggedOnValue === 6)) {
            // Simplified logic: Watch for whoever has control going on/off call
            // The startNewCall/endCall methods will handle muting appropriately
            if (user.UserName == trainingSession.incomingCallsTo) {
                // The person who should receive calls is changing status
                console.log(`Training call status check: ${user.UserName} onCall=${self.onCall}, currentlyOnCall=${trainingSession.currentlyOnCall}`);
                
                if (self.onCall == "YES" && !trainingSession.currentlyOnCall) {
                    // They're going on a call - everyone should respond (mute if not them)
                    console.log("📞 Starting new call in training session");
                    trainingSession.startNewCall();
                } else if (self.onCall == "NO" && trainingSession.currentlyOnCall) {
                    // They're explicitly OFF the call - everyone can unmute
                    // Changed from != "YES" to == "NO" to avoid false triggers
                    console.log("📞 Ending call in training session");
                    trainingSession.endCall();
                }
            }
        }		
    
		if(self.signedInUser && self.callObject && (self.callObject.CallStatus == 'firstRing' || self.callObject.CallStatus == 'secondRing') && !self.incomingCall) {
			self.incomingCall = true;
			if (!newChat.chats[1] && !newChat.chats[2]) {
				newCall = new Call(self.callObject);
				newCall.init();
				// TRAINING MUTING FIX: Do NOT mute trainees when call starts ringing
				// Instead, wait for the call to be answered (onCall becomes "YES")
				// The feed update check at lines 3400-3403 will handle muting when 
				// answerCall.php sets OnCall=1 and vccFeed broadcasts onCall="YES"
				// This allows trainers/trainees to finish conversations before external call connects
				/*
				if (trainingSession) {
					trainingSession.startNewCall();
				}
				*/
			} else {
				self.incomingCall = false;
				if(trainingSession) {
					trainingSession.endCall();
				}
			}
		}
		
		if(self.signedInUser && !self.callObject && self.incomingCall && newCall && newCall.callStatus == 'ringing') {
			// DON'T auto-cancel if user is in training session
			// Training participants can answer external calls which route to their training conference
			if (trainingSession) {
				console.log("⚠️ Auto-cancel blocked: User in training session - external calls route to conference");
				return;
			}

			// DON'T auto-cancel if answer is in progress (race condition with answerCall.php)
			if (newCall.answerInProgress) {
				console.log("⚠️ Auto-cancel blocked: Answer in progress - ignoring transient null callObject from vccFeed race condition");
				return;
			}

			console.log("❌ Auto-cancelling call: callObject is null and no answer in progress");
			self.incomingCall = false;
			newCall.cancelCall();
			deleteCallObject();
		}
		if(self.signedInUser && !self.callObject && !self.incomingCall && newCall) {
			var conn = callMonitor.getDevice().activeConnection();
			if(!conn) {
				deleteCallObject();
			}	
		}
	};
	
	
	this.loadImPane = function () {
		locateImPane(self);
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
		user.imMessage.value = "";
		var url = 'volunteerPosts.php';
		var resultsObject = new Object();
		var postResults = function (results, searchObject) {
			testResults(results, searchObject);
		};
		params = "postType=postIM&action=" + encodeURIComponent(user.userName) + "&text=" + imMessageText;
		var postIM = new AjaxRequest(url, params, postResults, resultsObject);
		
		return false;
	};

	this.receiveIm = function (message) {
		if(trainingSession && (message.from == trainingSession.traineeID || message.to == trainingSession.traineeID)) {
			return;
		} else {
			if(message.from == this.volunteerID) {
				var imMessage = new ImMessage(message);
				userList[message.to].im[message.id] = imMessage;
				var url = "volunteerPosts.php";
				var	params = "postType=IMReceived&action=" + message.id + "&text=from";
				var updateMessageStatusResults = function (results, searchObject) {
					testResults(results, searchObject);
				};
				var updateMessageStatus = new AjaxRequest(url, params, updateMessageStatusResults);
				// Self-IM: message is stored above, will display when user opens IM pane manually
			} else if (message.to == this.volunteerID || message.to == 'All') {

				var gabeIMSound = document.getElementById("IMSound");
				if(gabeIMSound && !userList[message.from].im[message.id]) {
					gabeIMSound.play();
				}

				if(userList[message.from].AdminLoggedOn == 2) {
//					alert("Hell Yes");
					message.from = 'Admin';
				}
				var imMessage = new ImMessage(message);
				userList[message.from].im[message.id] = imMessage;
				userList[message.from].loadImPane();
				var url = "volunteerPosts.php";
				var	params = "postType=IMReceived&action=" + message.id + "&text=to";
				var updateMessageStatusResults = function (results, searchObject) {
					testResults(results, searchObject);
				};
				var updateMessageStatus = new AjaxRequest(url, params, updateMessageStatusResults);
			}
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

function videoWatched() {
	var myVideo = document.getElementById("video1");
	var watched = myVideo.ended;
	if(watched) {
		var params = "postType=videoEnded";
		var responseFunction = function (results) {
			if(results != "OK") {
				if (typeof showComprehensiveError === 'function') {
				showComprehensiveError(
					'Video End Error',
					'Problem telling system that video had ended',
					{
						url: 'volunteerPosts.php',
						params: params,
						responseText: results,
						additionalInfo: 'The system failed to record that video watching has ended. This may affect tracking.'
					}
				);
			} else {
				alert("Problem telling system that video had ended.");
			}
			}
		};
		var watchingVideoPost = new AjaxRequest("volunteerPosts.php", params, responseFunction);	
		var video = document.getElementById("videoWindow");
		var mainElements = document.body.getElementsByTagName("div");
		for (i = 0; i < mainElements.length; i++) {
			mainElements[i].style.display = null;
		}
		var oneChatBox = document.getElementById("oneChatOnly");
		if (oneChatBox.checked) {
			newChat.oneChatOnlyChange();
			newChat.oneChatOnlyChange();
		}
		var video = document.getElementById("videoWindow");
		removeElements(video);
		clearInterval(videoFinished);
		sendEmail();
	}
}

function sendEmail() {
	var volunteerID = document.getElementById("volunteerID").value;

	var watchingUser = userList[volunteerID].name;
	var message = watchingUser + " has just watched the most recent Volunteer Message video.";
	var toAddresses = "aaron@lgbthotline.org";			
	var subject = watchingUser + " Watched Video";

	var params = "To=" + toAddresses + "&Subject=" + subject + "&Message=" + message + "&idnum=video_notification";
	var responseFunction = function (results) {
		if(results != "OK") {
//			alert("Problem sending email at the end of video watching.");
			console.log(results);
		}
	};	
	var watchingVideoEmail = new AjaxRequest("Admin/Search/sendemailNew.php", params, responseFunction);	
}
			
async function exitProgram(type) {
    if(exiting === true) {
        return;
    }
    exiting = true;

    // Stop heartbeat to prevent unnecessary requests during exit
    if (heartbeatInterval) {
        clearInterval(heartbeatInterval);
        heartbeatInterval = null;
    }

    // Disable onbeforeunload to prevent sendBeacon during intentional exit
    window.onbeforeunload = null;
    
    // Block any other actions while exiting
    const blocker = document.createElement('div');
    blocker.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        z-index: 10000;
        display: flex;
        justify-content: center;
        align-items: center;
    `;
    blocker.innerHTML = '<h2>Exiting program, please wait...</h2>';
    document.body.appendChild(blocker);

    if (typeof type === 'undefined') {
        type = "admin";
    }
    const volunteerID = document.getElementById("volunteerID").value;
    const trainees = document.getElementById("assignedTraineeIDs").value;
    
    // Capture training session info BEFORE destroying it
    const isTrainer = trainingSession && trainingSession.role === "trainer";
    const trainerID = isTrainer ? (trainingSession.trainerID || volunteerID) : null;
    
    console.log("🔍 Pre-cleanup capture - isTrainer:", isTrainer, "trainerID:", trainerID);

    try {
        // Step 1: Unload Twilio
        console.log("🔵 Step 1: Unloading Twilio...");
        if (callMonitor?.unload) {
            await callMonitor.unload();
        }
        console.log("✅ Twilio unloaded successfully");

        // Step 2: Clean up training session and screen sharing
        console.log("🔵 Step 2: Cleaning up training session...");
        if (trainingSession) {
            try {
                // Stop screen sharing properly
                if (trainingSession.shareScreen) {
                    if (trainingSession.shareScreen.closeConnection) {
                        trainingSession.shareScreen.closeConnection();
                    }
                    if (trainingSession.shareScreen.localStream) {
                        trainingSession.shareScreen.localStream.getTracks().forEach(track => track.stop());
                    }
                }
                
                // Destroy the training session
                if (trainingSession.destroy) {
                    trainingSession.destroy();
                }
            } catch (error) {
                console.error("Error cleaning up training session:", error);
            }
        }
        
        // Clear videos
        const localVideo = document.getElementById("localVideo");
        const remoteVideo = document.getElementById("remoteVideo");
        if (localVideo) localVideo.srcObject = null;
        if (remoteVideo) remoteVideo.srcObject = null;

        // Close chat window
        if (mytrainingChatwindow) {
            mytrainingChatwindow.close();
        }
        console.log("✅ Training session cleanup completed");

        // Step 3: Close training chat room if was a trainer
        console.log("🔵 Step 3: Checking training chat cleanup...");
        console.log("isTrainer (captured):", isTrainer);
        console.log("trainerID (captured):", trainerID);
        console.log("volunteerID:", volunteerID);
        
        if (isTrainer && trainerID) {
            console.log("🟢 Closing training chat room for ID:", trainerID);
            console.log("📤 Sending parameters: chatRoomID=" + trainerID);
            
            try {
                await new Promise((resolve, reject) => {
                    new AjaxRequest(
                        "TrainingChat/Admin/closeRoom.php",
                        "chatRoomID=" + trainerID,
                        function(chatResults) {
                            console.log("Chat room closure result:", chatResults);
                            if (chatResults === "OK") {
                                console.log("✅ Training chat room closed successfully");
                                resolve();
                            } else {
                                console.error("Failed to close chat room:", chatResults);
                                // Continue with exit even if chat room closure fails
                                resolve();
                            }
                        }
                    );
                });
            } catch (error) {
                console.error("Error closing training chat room:", error);
            }
        } else {
            console.log("🟡 Skipping chat room closure - isTrainer:", isTrainer, "trainerID:", trainerID);
        }
        console.log("✅ Training chat cleanup completed");

        // Step 4: Trainee signoffs are now handled server-side in volunteerPosts.php
        // When a trainer's exitProgram runs, the server automatically logs out all trainees
        console.log("🔵 Step 4: Trainee signoffs handled server-side");
        if (trainingSession && trainees) {
            console.log("📝 Trainees will be logged out by server:", trainees);
        }

        // Step 5: Display logoff message
        console.log("🔵 Step 5: Displaying logoff message...");
        const logOffMessage = type === "user"
            ? "EXITING PROGRAM. THANKS FOR VOLUNTEERING!"
            : "The Administrator has logged you off.";

        const h1 = document.createElement("h2");
        h1.appendChild(document.createTextNode(logOffMessage));
        
        const messageWindow = document.getElementById("volunteerMessage");
        if (messageWindow) {
            messageWindow.style.background = "yellow";
            messageWindow.style.fontSize = "90%";
            messageWindow.innerHTML = "";
            messageWindow.appendChild(h1);
        }

        viewControl("main");
        console.log("✅ Logoff message displayed");

        // Step 6: Final server confirmation
        console.log("🔵 Step 6: Final server confirmation...");
        if (trainingSession) {
            trainingSession = false;
        }
        
        await new Promise((resolve) => {
            new AjaxRequest(
                "volunteerPosts.php",
                "postType=exitProgram&Action=" + volunteerID + "&intentional=true",
                (results) => {
                    exitConfirmed(results);
                    resolve();
                }
            );
        });
        console.log("✅ Exit program completed successfully");

    } catch (error) {
        console.error("❌ Error during exit program:", error);
        if (typeof showComprehensiveError === 'function') {
            showComprehensiveError(
                'Exit Program Error',
                'An error occurred while exiting the program',
                {
                    error: error,
                    additionalInfo: 'The system encountered an error during logout. You may need to close your browser window manually. Your session should still be terminated on the server.'
                }
            );
        } else {
            alert("An error occurred while exiting. Please close your browser if needed.");
        }
    } finally {
        // Remove blocker
        if (blocker && blocker.parentNode) {
            document.body.removeChild(blocker);
        }
    }
}

function exitConfirmed(results) {
    if (results === "OK") {
        window.onbeforeunload = null;
        window.location.href = 'login.php';
    } else {
        console.error("Received unexpected result:", results);
        if (typeof showComprehensiveError === 'function') {
            showComprehensiveError(
                'Exit Confirmation Error',
                'Failed to properly exit the program',
                {
                    responseText: results,
                    additionalInfo: 'The server did not confirm successful logout. Expected "OK" but received: "' + results + '". Please try exiting again or contact support if the problem persists.'
                }
            );
        } else {
            alert("Failed to properly exit the program. Please try again.");
        }
    }
}

// **EVENTSOURCE SERVER TOGGLE FUNCTION**
// Call from browser console to switch between new Node.js server and old PHP endpoint
// Usage: toggleEventSource(true)  - Use new server
//        toggleEventSource(false) - Use old PHP
//        toggleEventSource()       - Show current status
window.toggleEventSource = function(useNew) {
    if (useNew === undefined) {
        // Show current status
        const current = localStorage.getItem('useNewEventSource') !== 'false';
        console.log('EventSource Mode:', current ? 'NEW Node.js Server (Redis Pub/Sub)' : 'OLD PHP Endpoint');
        console.log('To switch: toggleEventSource(true) for new, toggleEventSource(false) for old');
        return current;
    }

    localStorage.setItem('useNewEventSource', useNew ? 'true' : 'false');
    console.log('EventSource switched to:', useNew ? 'NEW Node.js Server' : 'OLD PHP Endpoint');
    console.log('Reloading page to apply changes...');
    setTimeout(() => location.reload(), 1000);
};

// =============================================================================
// **REDIS-BACKED POLLING SYSTEM**
// Alternative to EventSource that reads from Redis cache instead of database
// Uses dual polling: 2.5s for general updates, 500ms for incoming calls
// =============================================================================

var vccPolling = {
    generalInterval: null,      // 2.5s polling for user list, events, chat
    ringingInterval: null,      // 500ms polling for incoming calls
    lastTimestamp: 0,           // Last poll timestamp for delta updates
    activeChatRooms: [],        // Chat rooms user is currently in
    currentlyRinging: false,    // Track if we're currently in ringing state
    enabled: false,             // Is polling system active

    // Start the polling system
    start: function() {
        if (this.enabled) return;
        this.enabled = true;

        console.log('VCC Polling: Starting Redis-backed polling system');
        console.log('VCC Polling: General updates every 2.5s, ringing check every 500ms');

        // Start general polling (2.5 seconds)
        this.generalInterval = setInterval(() => this.pollGeneral(), 2500);
        this.pollGeneral(); // Immediate first poll

        // Start fast ringing poll (500ms)
        this.ringingInterval = setInterval(() => this.pollRinging(), 500);
        this.pollRinging(); // Immediate first poll
    },

    // Stop the polling system
    stop: function() {
        if (!this.enabled) return;
        this.enabled = false;

        console.log('VCC Polling: Stopping polling system');

        if (this.generalInterval) {
            clearInterval(this.generalInterval);
            this.generalInterval = null;
        }
        if (this.ringingInterval) {
            clearInterval(this.ringingInterval);
            this.ringingInterval = null;
        }
    },

    // General polling (user list, events, chat updates)
    pollGeneral: function() {
        if (!this.enabled) return;

        const params = new URLSearchParams({
            since: this.lastTimestamp,
            users: 'true',
            chatRooms: this.activeChatRooms.join(',')
        });

        fetch('vccPoll.php?' + params.toString())
            .then(response => {
                if (!response.ok) {
                    if (response.status === 401) {
                        // Track 401 errors - only redirect after multiple failures
                        this._authFailCount = (this._authFailCount || 0) + 1;
                        console.warn('VCC Polling: Auth failed, attempt', this._authFailCount);

                        if (this._authFailCount >= 5) {
                            console.error('VCC Polling: Session expired after 5 attempts, redirecting to login');
                            window.location.href = 'login.php';
                        }
                        return null;
                    }
                    throw new Error('Poll failed: ' + response.status);
                }
                // Reset auth fail count on success
                this._authFailCount = 0;
                return response.json();
            })
            .then(data => {
                if (!data) return;

                // Debug: Log poll response
                console.log('VCC Polling: Response received', {
                    timestamp: data.timestamp,
                    source: data.source,
                    hasUserList: !!data.userList,
                    userCount: data.userList ? data.userList.length : 0,
                    hasEvents: data.events && data.events.length > 0,
                    hasIMs: data.instantMessages && data.instantMessages.length > 0
                });

                // Log source for debugging
                if (data.fallback) {
                    console.warn('VCC Polling: Using database fallback (Redis unavailable)');
                }

                // Handle user list update
                // Only update lastTimestamp if we successfully process the userList
                // This prevents losing data when UI isn't ready yet
                if (data.userList) {
                    const processed = this.handleUserListUpdate(data.userList);
                    if (processed) {
                        this.lastTimestamp = data.timestamp;
                    }
                } else {
                    // No userList in response, update timestamp anyway
                    this.lastTimestamp = data.timestamp;
                }

                // Handle queued events (IMs, chat invites, etc.)
                if (data.events && data.events.length > 0) {
                    data.events.forEach(event => this.handleEvent(event));
                }

                // Handle chat updates
                if (data.chatUpdates) {
                    Object.keys(data.chatUpdates).forEach(callerID => {
                        this.handleChatUpdate(callerID, data.chatUpdates[callerID]);
                    });
                }

                // Handle instant messages
                if (data.instantMessages && data.instantMessages.length > 0) {
                    data.instantMessages.forEach(message => {
                        if (userList[message.from]) {
                            userList[message.from].receiveIm(message);
                        }
                    });
                }
            })
            .catch(error => {
                console.error('VCC Polling: General poll error:', error);
            });
    },

    // Fast ringing poll (500ms for incoming calls)
    pollRinging: function() {
        if (!this.enabled) return;

        fetch('vccRinging.php')
            .then(response => {
                if (!response.ok) return null;
                return response.json();
            })
            .then(data => {
                if (!data) return;

                // Debug: Log ringing response when ringing is detected
                if (data.ringing) {
                    console.log('VCC Polling: RINGING DETECTED:', data);
                }

                if (data.ringing && !this.currentlyRinging) {
                    // New incoming call detected
                    this.currentlyRinging = true;
                    console.log('VCC Polling: Incoming call detected via fast-poll');
                    this.handleIncomingCall(data);
                } else if (!data.ringing && this.currentlyRinging) {
                    // Ringing stopped (call answered elsewhere or abandoned)
                    this.currentlyRinging = false;
                    console.log('VCC Polling: Ringing stopped - cleaning up call UI');
                    this.handleRingingStopped();
                }
            })
            .catch(() => {
                // Silent fail - next poll will retry
            });
    },

    // Handle user list updates (same format as EventSource userList event)
    // Returns true if successfully processed, false if UI not ready
    handleUserListUpdate: function(users) {
        console.log('VCC Polling: handleUserListUpdate called with', users.length, 'users');

        // Ensure userList exists
        if (typeof userList === 'undefined') {
            window.userList = {};
        }

        // Ensure User class and newChat are available
        if (typeof User === 'undefined') {
            console.log('VCC Polling: User class not defined yet');
            return false;
        }
        if (typeof newChat === 'undefined' || !newChat.shouldDisplayUser || !newChat.createUserDisplay) {
            // First poll may arrive before UI is ready - this is normal, next poll will work
            if (!this._newChatWarningShown) {
                console.log('VCC Polling: Waiting for UI initialization (newChat not ready)...');
                this._newChatWarningShown = true;
            }
            return false;
        }

        console.log('VCC Polling: Processing user list update');

        // Reset keepalive timer
        if (typeof chatMonitorKeepAlive !== 'undefined' && chatMonitorKeepAlive != null) {
            clearTimeout(chatMonitorKeepAlive);
        }
        if (typeof newChat !== 'undefined') {
            chatMonitorKeepAlive = setTimeout("newChat.init();", 60000);
        }

        const onlineUsers = new Set();

        // Process each user (same logic as EventSource userList handler)
        users.forEach(message => {
            try {
                let userRecord = userList[message.UserName];
                onlineUsers.add(message.UserName);

                if (!userRecord) {
                    userList[message.UserName] = new User(message);
                    userRecord = userList[message.UserName];
                } else {
                    userRecord.updateUser(message);
                }

                // Update or create user display
                const userDisplayed = document.getElementById(message.UserName);
                const shouldDisplay = newChat.shouldDisplayUser(message);

                if (userDisplayed) {
                    newChat.updateUserDisplay(userDisplayed, userRecord, message);
                } else if (shouldDisplay) {
                    newChat.createUserDisplay(userRecord, message);
                }
            } catch (e) {
                console.error('VCC Polling: Error processing user', message.UserName, e);
            }

            // Handle admin user creation
            if (message.AdminLoggedOn == 2 || message.AdminLoggedOn == 7) {
                if (!userList["Admin"]) {
                    const adminUser = {
                        UserName: "admin",
                        name: "admin",
                        FirstName: "Administrative",
                        LastName: "IM's"
                    };
                    userList["Admin"] = new User(adminUser);
                }
            }
        });

        // Clean up users who logged off
        Object.keys(userList).forEach(userName => {
            if (userName !== "Admin" && !onlineUsers.has(userName)) {
                delete userList[userName];
            }
        });

        console.log('VCC Polling: User list update complete');
        return true;
    },

    // Handle queued events
    handleEvent: function(event) {
        console.log('VCC Polling: Received event:', event.type);

        switch (event.type) {
            case 'chatInvite':
                // Handle chat invitation
                if (event.data && typeof handleChatInvite === 'function') {
                    handleChatInvite(event.data);
                }
                break;
            case 'IM':
                // Handle instant message
                if (event.data) {
                    console.log('VCC Polling: IM received from', event.data.from);
                }
                break;
            case 'trainingUpdate':
                // Handle training session update
                if (event.data) {
                    console.log('VCC Polling: Training update:', event.data);
                }
                break;
            default:
                console.log('VCC Polling: Unknown event type:', event.type);
        }
    },

    // Handle chat room updates
    handleChatUpdate: function(callerID, update) {
        // Handle typing status
        if (update.typing) {
            if (newChat.chats[callerID]) {
                const message = {
                    name: "Caller",
                    text: "...",
                    id: "typing" + callerID,
                    room: callerID
                };
                newChat.chats[callerID].processChatMessage(message);
            }
        } else {
            const typing = document.getElementById("typing" + callerID);
            if (typing && typing.parentNode) {
                typing.parentNode.removeChild(typing);
            }
        }

        // Handle new messages
        if (update.messages && update.messages.length > 0) {
            update.messages.forEach(msg => {
                if (newChat.chats[callerID]) {
                    newChat.chats[callerID].processChatMessage(msg);
                }
            });
        }
    },

    // Handle incoming call (fast-poll detected ringing)
    handleIncomingCall: function(data) {
        console.log('VCC Polling: Processing incoming call', data.callSid, data);

        // Get current volunteer's ID
        const volunteerID = document.getElementById("volunteerID").value;
        if (!volunteerID) {
            console.error('VCC Polling: No volunteerID found');
            return;
        }

        // Get the User object for current volunteer
        const myUser = userList[volunteerID];
        if (!myUser) {
            console.error('VCC Polling: User not in userList:', volunteerID);
            return;
        }

        // Check if already handling an incoming call
        if (myUser.incomingCall) {
            console.log('VCC Polling: Already handling an incoming call');
            return;
        }

        // Check if already in a chat (same logic as SSE version)
        if (typeof newChat !== 'undefined' && (newChat.chats[1] || newChat.chats[2])) {
            console.log('VCC Polling: User is in chat, not showing call UI');
            return;
        }

        // Create a call object that mimics what the SSE/database provides
        const callObject = {
            From: data.callerInfo?.from || 'Unknown',
            CallSid: data.callSid,
            CallStatus: 'firstRing'
        };

        console.log('VCC Polling: Creating Call object:', callObject);

        // Mark incoming call active
        myUser.incomingCall = true;

        // Create and display the Call UI
        try {
            newCall = new Call(callObject);
            newCall.init();
            console.log('VCC Polling: Call UI initialized successfully');
        } catch (e) {
            console.error('VCC Polling: Error creating Call UI:', e);
            myUser.incomingCall = false;
        }
    },

    // Handle ringing stopped (call answered elsewhere or abandoned)
    handleRingingStopped: function() {
        const volunteerID = document.getElementById("volunteerID").value;
        if (!volunteerID) return;

        const myUser = userList[volunteerID];
        if (myUser && myUser.incomingCall) {
            myUser.incomingCall = false;
        }

        // Cancel the call UI if it exists and is still in ringing state
        if (typeof newCall !== 'undefined' && newCall && newCall.callStatus === 'ringing') {
            console.log('VCC Polling: Cancelling call UI - ringing stopped');
            try {
                newCall.cancelCall();
                if (typeof deleteCallObject === 'function') {
                    deleteCallObject();
                }
            } catch (e) {
                console.error('VCC Polling: Error cancelling call:', e);
            }
        }
    },

    // Add a chat room to track
    addChatRoom: function(callerID) {
        if (!this.activeChatRooms.includes(callerID)) {
            this.activeChatRooms.push(callerID);
            console.log('VCC Polling: Tracking chat room', callerID);
        }
    },

    // Remove a chat room from tracking
    removeChatRoom: function(callerID) {
        const index = this.activeChatRooms.indexOf(callerID);
        if (index > -1) {
            this.activeChatRooms.splice(index, 1);
            console.log('VCC Polling: Stopped tracking chat room', callerID);
        }
    }
};

// **POLLING SYSTEM TOGGLE FUNCTION**
// Call from browser console to switch between SSE and polling
// Usage: togglePolling(true)  - Use Redis-backed polling
//        togglePolling(false) - Use SSE (vccFeed.php)
//        togglePolling()      - Show current status
window.togglePolling = function(usePolling) {
    if (usePolling === undefined) {
        const current = localStorage.getItem('usePolling') === 'true';
        console.log('Update Mode:', current ? 'REDIS POLLING (vccPoll.php + vccRinging.php)' : 'SSE (vccFeed.php)');
        console.log('To switch: togglePolling(true) for polling, togglePolling(false) for SSE');
        return current;
    }

    localStorage.setItem('usePolling', usePolling ? 'true' : 'false');
    console.log('Update mode switched to:', usePolling ? 'REDIS POLLING' : 'SSE');
    console.log('Reloading page to apply changes...');

    // CRITICAL: Disable the beforeunload handler to prevent logout on reload
    // The exitProgram handler would otherwise log the user out when the page reloads
    window.onbeforeunload = null;

    setTimeout(() => location.reload(), 500);
};

// Auto-start polling if enabled in localStorage
// This runs after page load to check if polling mode is enabled
// We use 'load' event instead of 'DOMContentLoaded' to ensure page is fully initialized
window.addEventListener('load', function() {
    const usePolling = localStorage.getItem('usePolling') === 'true';
    if (usePolling) {
        // Wait for session to be fully established after page load
        // Increased delay to handle page refresh scenarios
        setTimeout(function() {
            console.log('VCC Polling: Auto-starting (localStorage.usePolling = true)');
            // Close EventSource if it was started
            if (typeof newChat !== 'undefined' && newChat && newChat.source) {
                console.log('VCC Polling: Closing EventSource connection');
                newChat.source.close();
            }
            // Start polling system
            vccPolling.start();
        }, 1000); // 1 second delay ensures session is ready
    }
});

// Clean up polling on page unload
window.addEventListener('beforeunload', function() {
    if (vccPolling.enabled) {
        vccPolling.stop();
    }
});
