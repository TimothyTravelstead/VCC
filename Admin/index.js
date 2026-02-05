var formOK = true;
var newUserEntry = true;
var ringing = 0;
var blockedNumber = 0;
var callHistorySort = 'Date';
var userList = {};
var IMMonitorKeepAlive = null;
var AdminMiniUser = "";
var modal = "";

function removeElements(element) {
	if(element) {
		while(element.hasChildNodes()) {     
		element.removeChild(element.childNodes[0]);
	}
	}
}

window.onload = function() {
	AdminMiniUser = document.getElementById('AdminMiniUser').value;
    initPage();
}

// CRITICAL: Close EventSource when page unloads to prevent orphaned PHP processes
window.addEventListener('beforeunload', function() {
    if (typeof newIMMonitor !== 'undefined' && newIMMonitor && newIMMonitor.source) {
        console.log("Admin index.js: Closing EventSource on page unload");
        newIMMonitor.source.close();
    }
    if (IMMonitorKeepAlive) {
        clearTimeout(IMMonitorKeepAlive);
    }
});

window.addEventListener('pagehide', function() {
    if (typeof newIMMonitor !== 'undefined' && newIMMonitor && newIMMonitor.source) {
        console.log("Admin index.js: Closing EventSource on pagehide");
        newIMMonitor.source.close();
    }
});

// ===========================================
// Back Button & Swipe Navigation Protection
// ===========================================

// Track open modals for back button handling
var modalHistoryState = {
    generalModal: false,
    fullDetailsModal: false
};

// Handle browser back button - close modal instead of navigating away
window.addEventListener('popstate', function(event) {
    // Check if any modal is open
    if (modal && modal.modal && modal.modal.classList.contains('show')) {
        modal.hide();
        // Re-push state to prevent actual navigation
        history.pushState({ modalOpen: true }, '');
        return;
    }
    if (fullDetailsModal && fullDetailsModal.modal && fullDetailsModal.modal.classList.contains('show')) {
        fullDetailsModal.hide();
        // Re-push state to prevent actual navigation
        history.pushState({ modalOpen: true }, '');
        return;
    }
});

// Push initial state on page load to enable popstate handling
window.addEventListener('load', function() {
    // Replace current state with our base state
    history.replaceState({ page: 'admin' }, '');
});

// Prevent accidental swipe navigation on modals (macOS trackpad, mouse gestures)
function preventSwipeNavigation(element) {
    if (!element) return;

    // Prevent horizontal overscroll that triggers back/forward navigation
    element.addEventListener('wheel', function(e) {
        // Only prevent if it's a horizontal scroll that might trigger navigation
        if (Math.abs(e.deltaX) > Math.abs(e.deltaY) && Math.abs(e.deltaX) > 10) {
            var atLeftEdge = element.scrollLeft === 0;
            var atRightEdge = element.scrollLeft + element.clientWidth >= element.scrollWidth;

            // Prevent if trying to scroll past edges
            if ((e.deltaX < 0 && atLeftEdge) || (e.deltaX > 0 && atRightEdge)) {
                e.preventDefault();
            }
        }
    }, { passive: false });

    // Touch swipe prevention for mobile/tablet
    var touchStartX = 0;
    element.addEventListener('touchstart', function(e) {
        touchStartX = e.touches[0].clientX;
    }, { passive: true });

    element.addEventListener('touchmove', function(e) {
        var touchCurrentX = e.touches[0].clientX;
        var deltaX = touchCurrentX - touchStartX;

        // If swiping from edge of screen (potential navigation gesture)
        if (touchStartX < 30 && deltaX > 20) {
            e.preventDefault();
        }
        if (touchStartX > window.innerWidth - 30 && deltaX < -20) {
            e.preventDefault();
        }
    }, { passive: false });
}

// Prevent page-level accidental navigation when modal is open
document.addEventListener('wheel', function(e) {
    var modalOpen = (modal && modal.modal && modal.modal.classList.contains('show')) ||
                    (fullDetailsModal && fullDetailsModal.modal && fullDetailsModal.modal.classList.contains('show'));

    if (modalOpen && Math.abs(e.deltaX) > Math.abs(e.deltaY) && Math.abs(e.deltaX) > 30) {
        e.preventDefault();
    }
}, { passive: false });

function initPage() {

	// Initialize the modals
	modal = new GeneralModal();
	fullDetailsModal = new FullDetailsModal();




	newIMMonitor = new IMMonitor();
	newIMMonitor.init();

    var IMAll = document.getElementById("IMAll");
    IMAll.onclick=IMAllRoutine;
    
    var NewUser = document.getElementById("NewUser");
	NewUser.onclick = newUserCreate;   

	var callLogButton = document.getElementById("CallLogButton");
	callLogButton.onclick=WriteToFile;

	var VolunteerLogButton = document.getElementById("VolunteerLogButton");
	VolunteerLogButton.onclick=WriteToFileVolunteerLog;
	
 	if(!AdminMiniUser) {


		var cancelButton = document.getElementById("CancelButton");
		cancelButton.onclick = function() 	{
												alert("Action Cancelled.  No Updates have occurred.");
												newUserCreate();
											}

		var CallHistoryButton = document.getElementById("CallHistoryButton");
		CallHistoryButton.onclick=WriteToFileCallerHistory;
	
		var ChatHistoryButton = document.getElementById("DownloadChatHistoryButton");
		ChatHistoryButton.onclick=WriteToFileChatHistory;

		var BlockedCallersButton = document.getElementById("BlockedCallersButton");
		BlockedCallersButton.onclick=WriteToFileBlockedCallers;


		// Set Up Volunteer Types Check Boxes to Prevent Trainee from having any other Volunteer Type entered
			var inputs = document.getElementsByTagName("input");  
			for (var i = 0; i < inputs.length; i++) {  
				if (inputs[i].type == "checkbox") {  
					var box = inputs[i];
					box.onchange = function() {traineeCheck(this);}
				}	
			}

		var resourceUpdateHistoryButton = document.getElementById("resourceUpdateHistoryButton");
		resourceUpdateHistoryButton.onclick=WriteToFileResourceUpdateHistory;
	
		var DownloadResourcesButton = document.getElementById("DownloadResourcesButton");
		DownloadResourcesButton.onclick=WriteToFileResources;

	}
	
	// Modern download button handler
	var downloadButton = document.getElementById("downloadButton");
	var downloadTypeSelect = document.getElementById("downloadTypeSelect");
	
	if (downloadButton && downloadTypeSelect) {
		downloadButton.onclick = function() {
			var selectedType = downloadTypeSelect.value;
			if (!selectedType) {
				alert("Please select a report type to download");
				return;
			}
			
			// Trigger the appropriate download function based on selection
			switch(selectedType) {
				case "CallLog":
					WriteToFile();
					break;
				case "CallHistory":
					if (!AdminMiniUser) WriteToFileCallerHistory();
					break;
				case "BlockedCallers":
					if (!AdminMiniUser) WriteToFileBlockedCallers();
					break;
				case "VolunteerLog":
					WriteToFileVolunteerLog();
					break;
				case "ChatHistory":
					if (!AdminMiniUser) WriteToFileChatHistory();
					break;
				case "Resources":
					if (!AdminMiniUser) WriteToFileResources();
					break;
				case "ResourceUpdate":
					if (!AdminMiniUser) WriteToFileResourceUpdateHistory();
					break;
			}
		};
	}


   	var exitButton = document.getElementById("ExitButton");
    exitButton.onclick=Exit;

   	var tabButtons = document.getElementById("WorkTabs").getElementsByTagName("span");
    for (var i=0; i<tabButtons.length; i++) {
        var item = tabButtons[i];
        item.onclick = function() {Selected(this);}
    }
    
    var users = document.getElementById("CurrentUsers").getElementsByTagName("span");
    for (var i=0; i<users.length; i++) {
        var item = users[i];
        item.onclick = function() {UserSelected(this.getAttribute("id"));}
    }
    newUserCreate();
 
 
 	//Set onclick events for call History titles - to make the list sortable 
 	if(!AdminMiniUser) {
		var callHistoryTitles = document.getElementById("CallHistoryList").getElementsByTagName("th");	
	
		for (var i=0; i< callHistoryTitles.length ; i++) {
			item = callHistoryTitles[i];
			itemClass = item.class;
			item.onclick = function() {callHistoryListUpdate(this.getAttribute("class"));}
		}
		 
		
		blockListUpdate('Admin');
	
		var ctrl  = document.getElementById("addBlockedNumberData");
		ctrl.onkeydown = phoneNumberFormat;
		
		var control = document.getElementById('addBlockedNumberButton');
		control.onclick = function() {
			blockedNumber = document.getElementById('addBlockedNumberData').value;
			blockedReason = document.getElementById('addBlockedNumberReason').value;
			addBlockedNumber(blockedNumber,blockedReason,0);
		};
	
		var control = document.getElementById('cancelBlockedNumberButton');
		control.onclick = function() {
			var blockedNumberElement = document.getElementById('addBlockedNumberData');
			blockedNumberElement.value = "";  
			alert('No new numbers have been blocked');  
		};
		
		var control = document.getElementById('updateUserBlockedListButton');
		control.onclick = function() {blockListUpdate('User')};

		document.body.addEventListener('dragover',drag_over,false); 
		document.body.addEventListener('drop',drop,false); 
	
		var bradStatsButton = document.getElementById('bradStatsButton');
		
		if(bradStatsButton) {
			bradStatsButton.onclick = function() {
				window.open("/Stats/newStats.php")
			};
		}
	
		var numberHistoryLookupNumberButton = document.getElementById("numberHistoryLookupNumberButton");
		var numberHistoryLookupNumber = document.getElementById('numberHistoryLookupNumber');
		numberHistoryLookupNumber.onblur = phoneNumberHistoryLookup;
		numberHistoryLookupNumber.onkeydown = phoneNumberFormat;
		numberHistoryLookupNumberButton.onclick = phoneNumberHistoryLookup;
	}
//	callMonitor();
}

function phoneNumberHistoryLookup() {
	var numberHistoryLookupNumberButton = document.getElementById("numberHistoryLookupNumberButton");
	var numberHistoryLookupNumber = document.getElementById('numberHistoryLookupNumber');
	if (!numberHistoryLookupNumber.value) {
		return;
	}

	if(!numberHistoryLookupNumber) {
		alert('No phone number entered to look up.');
	} else {
		var formattedNumber = numberHistoryLookupNumber.value.split("-");
		var finalFormattedNumber = "(" + formattedNumber[0] + ") " + formattedNumber[1] + "-" + formattedNumber[2];
		phoneNumberPopup(finalFormattedNumber);
		numberHistoryLookupNumber.value = "";
	}
}

function traineeCheck(box) {
	return; // Now allows trainees to have any other logon type as well
	var traineeChecked = document.getElementById('userTypesTrainee');
	if (box.name == traineeChecked.name && traineeChecked.checked) {
		var inputs = document.getElementsByTagName("input");  
		var cbs = []; //will contain all checkboxes  
		var checked = []; //will contain all checked checkboxes  
		for (var i = 0; i < inputs.length; i++) {  
			if (inputs[i].type == "checkbox") {  
				var box = inputs[i];
				if(box.name != 'userTypesTrainee') {  
					box.checked = false;
				}
			}	
		}
	} else if(box.checked) {
		traineeChecked.checked = false;
	}
}

function phoneNumberFormat(e) {
	ctrl = this;

	if(ctrl.id == "dfax" && ctrl.value.length == 0) {
		ctrl.value = ZipCodeAreaCode;
	}
    
        
    if (!e) var e = window.event;
	if (e.keyCode) code = e.keyCode;
	else if (e.which) code = e.which;

    if(code != 8) {
        if(ctrl.value.length == 3) {
            ctrl.value = ctrl.value + "-";
        } else if(ctrl.value.length == 4) {	
            ctrl.setSelectionRange(4, 4);
        } else if (ctrl.value.length == 7) {
            ctrl.value = ctrl.value + "-";
        }
    } 
    
    if(code == 13) {
    	ctrl.blur();
    }
}


function newUserCreate() {
        document.forms.DetailUserForm.reset();
        resetMenus();
        ReMarkUsers();  
        message=document.getElementById("UserMessage");
        while(message.firstChild) {
        	message.removeChild(message.firstChild);
        }
        message.appendChild(document.createTextNode("ENTER NEW USER INFORMATION"));
        IdNum = document.getElementById("DetailIdNum");
        IdNum.value = "New";
        document.getElementById("DetailFirstName").focus();

		NewUser = document.getElementById("NewUser");
        NewUser.value="New User";
        NewUser.onclick=newUserCreate;
        newUserEntry = true;

}


function LogoffUser(record) {
    // DEBUG: Log what's calling LogoffUser()
    console.log("LogoffUser() called with record:", record, "- Stack trace:");
    console.trace();
    
    // Disable the logoff button immediately to prevent double-clicking
    var logoffButton = document.getElementById(record + "LogoffButton");
    if (logoffButton) {
        logoffButton.disabled = true;
        logoffButton.value = "Logging off...";
        console.log("Disabled logoff button for:", record);
    }
    
    request = createRequest();

    if (request == null) {
        alert("Unable to create request");
        // Re-enable button if request fails
        if (logoffButton) {
            logoffButton.disabled = false;
            logoffButton.value = "Log Off";
        }
        return;
    }

    console.log("LogoffUser calling ExitProgram.php with VolunteerID:", record);
    var url="ExitProgram.php?VolunteerID=" + record;
    
    request.open("GET", url, true);
    request.onreadystatechange = UpdateUserStatus;
    request.send(null);      
    
	exitGroupChat(record);    

}


function ValidateForm() {

    var IdNum           = document.getElementById("DetailIdNum").value;    
    var FirstName       = document.getElementById("DetailFirstName").value;
    var LastName        = document.getElementById("DetailLastName").value;
    var UserID          = document.getElementById("DetailUserID").value;
    var Password        = document.getElementById("DetailPassword").value;
    var ConfirmPassword = document.getElementById("DetailConfirmPassword").value;
    var Volunteer       = document.getElementById("userTypesVolunteer").checked;
    var ResourcesOnly   = document.getElementById("userTypesResourcesOnly").checked;
    var AdminUser	    = document.getElementById("userTypesAdmin").checked;
    var AdminUserResources	    = document.getElementById("userTypesAdminResources").checked;
    var ResourceAdmin	    = document.getElementById("userTypesResourceAdmin").checked;
    var Trainer         = document.getElementById("userTypesTrainer").checked;
    var Monitor         = document.getElementById("userTypesMonitor").checked;
    var GroupChat         = document.getElementById("userTypesGroupChatModerator").checked;
    var Trainee         = document.getElementById("userTypesTrainee").checked;
    var skypeID         = document.getElementById("DetailskypeID").value;
    var callerType      = document.getElementById("callerTypeSelectMenu").value;
    var userLocationOffice  = document.getElementById("userLocationOffice").checked;
    var userLocationHome  = document.getElementById("userLocationHome").checked;
    var typeChecked = 	Volunteer + ResourcesOnly + AdminUser + Trainer + Monitor + Trainee + AdminUserResources + GroupChat + ResourceAdmin;
    var userLocation = "";
    var userPreferredHotlineYouth  = document.getElementById("userPreferredHotlineYouth").checked;
    var userPreferredHotlineSAGE  = document.getElementById("userPreferredHotlineSAGE").checked;
    var userPreferredHotlineNone  = document.getElementById("userPreferredHotlineNone").checked;
    var userPreferredHotline = "";

    if (IdNum == "") {
        alert("Please select a user to edit or click 'New User' to start.");
        return;
    }
    
    if (newUserEntry) {
    	UniqueID();
    }
    
	if (!userLocationOffice && !userLocationHome) {
		alert ("Please indicate whether the volunteer will work from the Office or Remotely.");
		return;
	} else {
		if (userLocationOffice) {
			userLocation = "SF";
		} else if (userLocationHome) {
			userLocation = "RM";
		} else {
			alert("New User Location Validation Error");
		}
	}

	if (!userPreferredHotlineYouth && !userPreferredHotlineSAGE && !userPreferredHotlineNone) {
		alert ("Please indicate the volunteer's preferred hotline.");
		return;
	} else {
		if (userPreferredHotlineYouth) {
			userPreferredHotline = "Youth";
		} else if (userPreferredHotlineSAGE) {
			userPreferredHotline = "SAGE";
		} else if (userPreferredHotlineNone) {
			userPreferredHotline = "None";
		} else {
			alert("New User Preferred Hotline Validation Error");
		}
	}
    
    if (IdNum != "New") {
        if (Password != "") {
            if (Password !=ConfirmPassword) {
                alert ("Passwords Do Not Match.  Please reenter.");
                document.getElementById("DetailPassword").value="";
                document.getElementById("DetailConfirmPassword").value="";
                document.getElementById("DetailPassword").focus();
                return;
            } else {
                Password = hex_sha1(Password);
            }
        }
        
        if (typeChecked == 0 || typeChecked == null) {
            alert ("Please Check at least one User Type.");
            return;
        }    
        

       
    } else {
    
        if (FirstName =="") {
            alert ("Enter a First Name");
            document.getElementById("DetailFirstName").focus();
            return;
        }
        if (LastName =="") {
            alert ("Enter a Last Name");
            document.getElementById("DetailLastName").focus();
            return;
        }
        if (UserID =="") {
            alert ("Enter a UserID");
            document.getElementById("DetailUserID").focus();
            return;
        } else if (!formOK) {
			return;
		}       	
        if (Password == "") {
            alert ("Enter a password and confirm the password you entered");
            document.getElementById("DetailPassword").focus();
            return;
            
        } else if (Password != ConfirmPassword) {
            alert ("Passwords Do Not Match.  Please reenter.");
            document.getElementById("DetailPassword").value="";
            document.getElementById("DetailConfirmPassword").value="";
            document.getElementById("DetailPassword").focus();
            return;
        } else {
            Password = hex_sha1(Password);
        }
                
        if (typeChecked == null || typeChecked =="" || typeChecked == 0) {
            alert ("Please check at least one User Type.");
            return;
        }    
    }
	var params = "IdNum=" + IdNum + "&FirstName=" + FirstName + "&LastName=" + 
    			LastName + "&skypeID=" + skypeID + "&UserID=" + UserID + "&Password=" + 
    			Password + 
    			"&Volunteer=" + Volunteer + 
    			"&ResourcesOnly=" + ResourcesOnly +
    			"&AdminUser=" + AdminUser +
    			"&AdminResources=" + AdminUserResources +
    			"&ResourceAdmin=" + ResourceAdmin +
    			"&Trainer=" + Trainer +
    			"&Monitor=" + Monitor + 
    			"&Trainee=" + Trainee +
    			"&groupChat=" + GroupChat +
    			"&Location=" + userLocation +
    			"&CallerType=" + callerType +
				"&PreferredHotline=" + userPreferredHotline;


    var url = "UserData.php?" + params;
    request = createRequest();
    if (request == null) {
        alert("Unable to create request");
        return;
    }
    request.open("GET", url, true);
    request.setRequestHeader("Cache-Control" , "no-cache, must-revalidate");
    request.onreadystatechange = UpdateUserStatus;
    request.send(null);       
}


function setChatAvailableLevels() {

    var level1           = document.getElementById("setChatAvailableLevelsFormLevel1").value;    
    var level2           = document.getElementById("setChatAvailableLevelsFormLevel2").value;    
    var csrfToken        = document.querySelector('input[name="csrf_token"]').value;
 
	var params = "level1=" + level1 + "&level2=" + level2 + "&csrf_token=" + csrfToken;

    var url = "setChatAvailableLevel.php";
    setChatAvailableLevelsRequest = createRequest();
    if (setChatAvailableLevelsRequest == null) {
        alert("Unable to create request");
        return;
    }
    setChatAvailableLevelsRequest.open("POST", url, true);
    setChatAvailableLevelsRequest.setRequestHeader("Cache-Control" , "no-cache, must-revalidate");
    setChatAvailableLevelsRequest.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    setChatAvailableLevelsRequest.onreadystatechange = setChatAvailableLevelsResponse;
    setChatAvailableLevelsRequest.send(params);       
}




function setChatAvailableLevelsResponse() {

	if (setChatAvailableLevelsRequest.readyState == 4) {
		if (setChatAvailableLevelsRequest.status == 200) {
		
			var response = setChatAvailableLevelsRequest.responseText;	

			if (response == 0) {
				alert("Chat Availability Levels Changed.");
			} else {
			    alert ("Problem Setting Chat Availability Levels.  Call Tim.  " + response);
				
			}		
		}
	}
}





function UniqueID() {

	var UserID = document.getElementById("DetailUserID");

	if(!UserID.value) {
		return false;
	}

	UniqueIDRequest = createRequest();

	if (UniqueIDRequest == null) {
		alert("Unable to create request");
		return;
	}
	
	id = document.getElementById("DetailUserID").value;

	var url = "UniqueID.php?UserName=" + id;

	UniqueIDRequest.open("GET", url, true);
	UniqueIDRequest.onreadystatechange = UniqueIDResults;
	UniqueIDRequest.send(null);

}
    
    
function UniqueIDResults() {

	if (UniqueIDRequest.readyState == 4) {
		if (UniqueIDRequest.status == 200) {
		
			var UserID = document.getElementById("DetailUserID");

			var response = UniqueIDRequest.responseText;	

			if (response == "OK") {
				formOK = true;
			} else {
				formOK = false;
			    UserID.select();
			    alert ("That UserID is already in use.  Please select another UserID.");
				
			}		
		}
	}
}


function ReInitializePage() {
    document.forms.DetailUserForm.reset();
    resetMenus();
    ReMarkUsers();
    document.getElementById("DetailIdNum").value="";  
    window.location = "index.php";
}


function UpdateUserStatus() {
    if (request.readyState == 4) {
        if (request.status == 200) {
        
            var response = request.responseText;
			// Treat "already logged off" warnings as success since the desired outcome is achieved
			if (response == "OK") {
				modal.success('Update Successful', 'User has been updated successfully. \nRefresh Screen to update User List.');
				document.getElementById('DetailUserForm').reset(); // Clear the form
				
				// Unhighlight the selected user
				var selectedUser = document.querySelector('.UserSelected');
				if (selectedUser) {
					selectedUser.classList.remove('UserSelected');
				}
				newUserCreate();
	        } else {
				modal.error('Update Failed', 'There was an error updating the user record.' + response);
				// Re-enable the button if there was a real error
				// Try to find the button based on the response or last known user
				var buttons = document.querySelectorAll('.UserLogoffButton[disabled]');
				buttons.forEach(function(button) {
					button.disabled = false;
					button.value = "Log Off";
				});
	        }
        }
    }
}



class GeneralModal {
    constructor() {
        this.modal = document.getElementById('generalModal');
        this.modalHeader = document.getElementById('modalHeader');
        this.modalIcon = document.getElementById('modalIcon');
        this.modalTitle = document.getElementById('modalTitle');
        this.modalBody = document.getElementById('modalBody');
        this.closeBtn = document.getElementById('modalClose');

        // Bind close handlers
        this.closeBtn.onclick = () => this.hide();
        this.modal.onclick = (e) => {
            if (e.target === this.modal) this.hide();
        };

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.classList.contains('show')) {
                this.hide();
            }
        });

        // Apply swipe navigation prevention
        if (this.modal) {
            preventSwipeNavigation(this.modal);
        }
    }
    
    show(options = {}) {
        const {
            title = 'Notification',
            message = 'Operation completed.',
            type = 'info', // success, error, warning, info
            autoHide = false,
            hideDelay = 3000
        } = options;
        
        // Set the content
        this.modalTitle.textContent = title;
        this.modalBody.textContent = message;
        
        // Clear previous type classes
        this.modalHeader.classList.remove('success', 'error', 'warning', 'info');
        
        // Add the appropriate type class
        this.modalHeader.classList.add(type);
        
        // Set the appropriate icon
        const icons = {
            success: '✓',
            error: '✗',
            warning: '⚠',
            info: 'i'
        };
        this.modalIcon.textContent = icons[type] || icons.info;
        
        // Show the modal
        this.modal.classList.add('show');

        // Push history state for back button handling
        history.pushState({ modalOpen: true, modalType: 'general' }, '');

        // Auto-hide if requested
        if (autoHide) {
            setTimeout(() => {
                this.hide();
            }, hideDelay);
        }

        return this; // Allow chaining
    }

    hide() {
        this.modal.classList.remove('show');
        return this;
    }
    
    // Convenience methods for common use cases
    success(title, message, autoHide = true, hideDelay = 3000) {
        return this.show({
            title,
            message,
            type: 'success',
            autoHide,
            hideDelay
        });
    }
    
    error(title, message, autoHide = false) {
        return this.show({
            title,
            message,
            type: 'error',
            autoHide
        });
    }
    
    warning(title, message, autoHide = false) {
        return this.show({
            title,
            message,
            type: 'warning',
            autoHide
        });
    }
    
    info(title, message, autoHide = false) {
        return this.show({
            title,
            message,
            type: 'info',
            autoHide
        });
    }
}


/**
 * FullDetailsModal - Comprehensive call details display
 */
class FullDetailsModal {
    constructor() {
        this.modal = document.getElementById('fullDetailsModal');
        this.modalHeader = document.getElementById('fullDetailsHeader');
        this.modalIcon = document.getElementById('fullDetailsIcon');
        this.modalTitle = document.getElementById('fullDetailsTitle');
        this.modalBody = document.getElementById('fullDetailsBody');
        this.closeBtn = document.getElementById('fullDetailsClose');

        // Bind close handlers
        if (this.closeBtn) {
            this.closeBtn.onclick = () => this.hide();
        }
        if (this.modal) {
            this.modal.onclick = (e) => {
                if (e.target === this.modal) this.hide();
            };
        }

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal && this.modal.classList.contains('show')) {
                this.hide();
            }
        });

        // Apply swipe navigation prevention
        if (this.modal) {
            preventSwipeNavigation(this.modal);
        }
    }

    show(data) {
        if (!this.modal) return;

        // Set title based on call type and errors
        var titleSuffix = '';
        if (data.isTrainingCall) titleSuffix += ' - TRAINING SESSION';
        if (data.hasErrors) titleSuffix += ' (HAS ERRORS)';
        this.modalTitle.textContent = 'Full Call Details' + titleSuffix;

        // Update header style based on errors/training
        this.modalHeader.classList.remove('success', 'error', 'warning', 'info', 'training');
        if (data.hasErrors) {
            this.modalHeader.classList.add('error');
            this.modalIcon.textContent = '!';
        } else if (data.isTrainingCall) {
            this.modalHeader.classList.add('training');
            this.modalIcon.textContent = '🎓';
        } else {
            this.modalHeader.classList.add('info');
            this.modalIcon.textContent = 'i';
        }

        // Build and display content
        this.modalBody.innerHTML = this.buildContent(data);

        // Initialize collapsible sections
        this.initCollapsibles();

        // Show modal
        this.modal.classList.add('show');

        // Push history state for back button handling
        history.pushState({ modalOpen: true, modalType: 'fullDetails' }, '');
    }

    hide() {
        if (this.modal) {
            this.modal.classList.remove('show');
        }
    }

    buildContent(data) {
        var html = '';

        // 1. Call Overview section
        html += this.buildOverviewSection(data);

        // 2. Training Session info (if this was a training call - show prominently)
        if (data.isTrainingCall) {
            html += this.buildTrainingSection(data);
        }

        // 3. Queue Metrics section
        html += this.buildQueueSection(data);

        // 4. Errors section (if any - show prominently)
        if (data.hasErrors) {
            html += this.buildErrorsSection(data);
        }

        // 5. Carrier Warnings section (SHAKEN/STIR - collapsed by default)
        if (data.hasWarnings) {
            html += this.buildWarningsSection(data);
        }

        // 6. Caller Information section
        html += this.buildCallerInfoSection(data);

        // 7. Call Topics & Resources section
        html += this.buildTopicsSection(data);

        // 8. Volunteer Notes section
        html += this.buildNotesSection(data);

        // 9. Twilio Status Timeline section
        html += this.buildTimelineSection(data);

        // 10. Technical Details section
        html += this.buildTechnicalSection(data);

        return html;
    }

    buildSection(title, content, options = {}) {
        var className = 'details-section';
        if (options.type) className += ' ' + options.type;
        if (options.collapsed) className += ' collapsed';

        return '<div class="' + className + '">' +
            '<div class="details-section-header">' +
            '<span>' + escapeHtml(title) + '</span>' +
            '<span class="toggle-icon">&#9660;</span>' +
            '</div>' +
            '<div class="details-section-content">' + content + '</div>' +
            '</div>';
    }

    buildOverviewSection(data) {
        if (!data.hasCallerHistory && !data.overview) {
            return this.buildSection('Call Overview', '<div class="empty-state">Call history not found in database</div>');
        }

        var o = data.overview || {};
        var html = '<div class="details-grid">';
        html += '<span class="label">Caller ID:</span><span class="value">' + escapeHtml(o.callerID || '-') + '</span>';
        html += '<span class="label">Location:</span><span class="value">' + escapeHtml(o.location || '-') + '</span>';
        html += '<span class="label">Date:</span><span class="value">' + escapeHtml(o.date || '-') + '</span>';
        html += '<span class="label">Time:</span><span class="value">' + escapeHtml(o.time || '-') + '</span>';
        html += '<span class="label">Hotline:</span><span class="value">' + escapeHtml(o.hotline || '-') + '</span>';
        html += '<span class="label">Duration:</span><span class="value">' + escapeHtml(o.length || '-') + '</span>';
        html += '<span class="label">Category:</span><span class="value">' + escapeHtml(o.category || '-') + '</span>';
        html += '<span class="label">Volunteer:</span><span class="value">' + escapeHtml(o.volunteerName || o.volunteerID || 'N/A') + '</span>';
        if (o.blocked) {
            html += '<span class="label">Status:</span><span class="value" style="color: red; font-weight: bold;">BLOCKED</span>';
        }
        html += '</div>';

        return this.buildSection('Call Overview', html);
    }

    buildTrainingSection(data) {
        if (!data.trainingInfo) {
            return '';
        }

        var t = data.trainingInfo;
        var html = '<div class="training-badge">🎓 Training Session Call</div>';
        html += '<div class="details-grid">';
        html += '<span class="label">Trainer:</span><span class="value">' + escapeHtml(t.trainerFullName || t.trainerUsername || '-') + '</span>';
        html += '<span class="label">Conference Name:</span><span class="value">' + escapeHtml(t.conferenceName || '-') + '</span>';
        html += '</div>';

        // Display mute events if any (for debugging training muting issues)
        if (t.muteEvents && t.muteEvents.length > 0) {
            html += '<div style="margin-top: 12px;"><strong>Mute Events During Training:</strong></div>';
            html += '<table class="timeline-table" style="margin-top: 8px;">';
            html += '<thead><tr><th>Time</th><th>Event</th><th>Participant</th><th>Trigger</th></tr></thead>';
            html += '<tbody>';

            t.muteEvents.forEach(function(evt) {
                var eventLabel = evt.event;
                var triggerLabel = '-';

                // Make event names more readable
                if (evt.event === 'app_mute') {
                    eventLabel = '🔇 System Mute';
                    triggerLabel = evt.initiator || 'external call';
                } else if (evt.event === 'app_unmute') {
                    eventLabel = '🔊 System Unmute';
                    triggerLabel = evt.initiator || 'call ended';
                } else if (evt.event === 'participant-mute') {
                    eventLabel = '🔇 Twilio Mute';
                } else if (evt.event === 'participant-unmute') {
                    eventLabel = '🔊 Twilio Unmute';
                } else if (evt.event === 'participant-join') {
                    eventLabel = '📞 Joined';
                    triggerLabel = evt.muted ? 'muted' : 'unmuted';
                }

                // Show last 8 chars of CallSid for participant identification
                var participantId = evt.callSid ? '...' + evt.callSid.slice(-8) : '-';

                html += '<tr>';
                html += '<td style="white-space: nowrap;">' + formatTime(evt.timestamp) + '</td>';
                html += '<td>' + eventLabel + '</td>';
                html += '<td style="font-family: monospace; font-size: 11px;">' + escapeHtml(participantId) + '</td>';
                html += '<td>' + escapeHtml(triggerLabel) + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
        }

        return this.buildSection('Training Session', html, { type: 'training' });
    }

    buildQueueSection(data) {
        if (!data.queueMetrics) {
            return this.buildSection('Queue Metrics', '<div class="empty-state">No queue data available</div>', { collapsed: true });
        }

        var q = data.queueMetrics;
        var html = '<div class="details-grid">';
        html += '<span class="label">Call Start:</span><span class="value">' + escapeHtml(q.callStart || '-') + '</span>';
        html += '<span class="label">Call End:</span><span class="value">' + escapeHtml(q.callEnd || '-') + '</span>';
        html += '<span class="label">Into Queue:</span><span class="value">' + escapeHtml(q.intoQueue || '-') + '</span>';
        html += '<span class="label">Out of Queue:</span><span class="value">' + escapeHtml(q.outOfQueue || '-') + '</span>';
        if (q.queueWaitTime) {
            html += '<span class="label">Wait Time:</span><span class="value" style="font-weight: bold;">' + escapeHtml(q.queueWaitTime) + '</span>';
        }
        html += '<span class="label">Queue Messages:</span><span class="value">' + escapeHtml(q.queueMessages || '0') + '</span>';
        html += '</div>';

        return this.buildSection('Queue Metrics', html, { collapsed: true });
    }

    buildErrorsSection(data) {
        if (!data.errors || data.errors.length === 0) {
            return '';
        }

        var html = '<table class="error-table">';
        html += '<thead><tr><th>Time</th><th>Level</th><th>Code</th><th>Message</th></tr></thead>';
        html += '<tbody>';

        data.errors.forEach(function(err) {
            var levelClass = err.level === 'Error' ? 'error-level-error' : 'error-level-warning';
            html += '<tr>';
            html += '<td style="white-space: nowrap;">' + formatTime(err.timestamp) + '</td>';
            html += '<td class="' + levelClass + '">' + escapeHtml(err.level || '-') + '</td>';
            html += '<td>' + escapeHtml(err.errorCode || '-') + '</td>';
            html += '<td>' + escapeHtml(err.errorMessage || '-') + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';

        return this.buildSection('Twilio Errors (' + data.errors.length + ')', html, { type: 'error' });
    }

    buildWarningsSection(data) {
        if (!data.warnings || data.warnings.length === 0) {
            return '';
        }

        var html = '<p style="font-size: 12px; color: #666; margin: 0 0 10px 0;">These are caller\'s carrier issues (SHAKEN/STIR verification), not errors in your system.</p>';
        html += '<table class="warning-table">';
        html += '<thead><tr><th>Time</th><th>Code</th><th>Message</th></tr></thead>';
        html += '<tbody>';

        data.warnings.forEach(function(warn) {
            html += '<tr>';
            html += '<td style="white-space: nowrap;">' + formatTime(warn.timestamp) + '</td>';
            html += '<td>' + escapeHtml(warn.errorCode || '-') + '</td>';
            html += '<td>' + escapeHtml(warn.errorMessage || '-') + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';

        return this.buildSection('Carrier Warnings (' + data.warnings.length + ')', html, { type: 'warning', collapsed: true });
    }

    buildCallerInfoSection(data) {
        if (!data.hasCallLog) {
            return this.buildSection('Caller Information',
                '<div class="empty-state">Volunteer did not complete post-call log</div>',
                { collapsed: true });
        }

        var c = data.callerInfo || {};
        var html = '<div class="details-grid">';
        html += '<span class="label">Gender:</span><span class="value' + (!c.gender ? ' empty' : '') + '">' + escapeHtml(c.gender || 'Not recorded') + '</span>';
        html += '<span class="label">Age:</span><span class="value' + (!c.age ? ' empty' : '') + '">' + escapeHtml(c.age || 'Not recorded') + '</span>';
        html += '<span class="label">Ethnicity:</span><span class="value' + (!c.ethnicity ? ' empty' : '') + '">' + escapeHtml(c.ethnicity || 'Not recorded') + '</span>';
        html += '</div>';

        return this.buildSection('Caller Information', html, { collapsed: true });
    }

    buildTopicsSection(data) {
        if (!data.hasCallLog || !data.callTopics) {
            return this.buildSection('Call Topics & Resources',
                '<div class="empty-state">Volunteer did not complete post-call log</div>',
                { collapsed: true });
        }

        var t = data.callTopics;
        var html = '';

        // Issues
        html += '<div style="margin-bottom: 12px;"><strong>Issues Discussed:</strong>';
        if (t.issues && t.issues.length > 0) {
            html += '<div class="topic-pills">';
            t.issues.forEach(function(issue) {
                html += '<span class="topic-pill issue">' + escapeHtml(issue) + '</span>';
            });
            html += '</div>';
        } else {
            html += '<span class="empty-state" style="display: block; padding: 5px; text-align: left;">None selected</span>';
        }
        html += '</div>';

        // Resources/Referrals
        html += '<div style="margin-bottom: 12px;"><strong>Resources/Referrals:</strong>';
        if (t.resources && t.resources.length > 0) {
            html += '<div class="topic-pills">';
            t.resources.forEach(function(resource) {
                html += '<span class="topic-pill resource">' + escapeHtml(resource) + '</span>';
            });
            html += '</div>';
        } else {
            html += '<span class="empty-state" style="display: block; padding: 5px; text-align: left;">None selected</span>';
        }
        html += '</div>';

        // Senior Topics (if any)
        if (t.seniorTopics && t.seniorTopics.length > 0) {
            html += '<div style="margin-bottom: 12px;"><strong>Senior Topics:</strong>';
            html += '<div class="topic-pills">';
            t.seniorTopics.forEach(function(topic) {
                html += '<span class="topic-pill senior">' + escapeHtml(topic) + '</span>';
            });
            html += '</div></div>';
        }

        // Internet Source (if any)
        if (t.internetSource) {
            html += '<div><strong>Found Us Via:</strong> <span class="topic-pill internet">' + escapeHtml(t.internetSource) + '</span></div>';
        }

        return this.buildSection('Call Topics & Resources', html, { collapsed: true });
    }

    buildNotesSection(data) {
        if (!data.hasCallLog) {
            return this.buildSection('Volunteer Notes',
                '<div class="empty-state">Volunteer did not complete post-call log</div>',
                { collapsed: true });
        }

        var notes = data.volunteerNotes || '';
        var html = '<div class="notes-content' + (!notes ? ' empty' : '') + '">' +
            (notes ? escapeHtml(notes) : 'No notes recorded') +
            '</div>';

        return this.buildSection('Volunteer Notes', html, { collapsed: !notes });
    }

    buildTimelineSection(data) {
        if (!data.statusTimeline || data.statusTimeline.length === 0) {
            return this.buildSection('Twilio Status Timeline',
                '<div class="empty-state">No Twilio status events recorded for this call</div>',
                { collapsed: true });
        }

        var html = '<div class="timeline-container">';
        html += '<table class="timeline-table">';
        html += '<thead><tr><th>Time</th><th>Status</th><th>Event</th><th>Conference</th><th>Muted</th></tr></thead>';
        html += '<tbody>';

        data.statusTimeline.forEach(function(evt) {
            var statusClass = 'status-' + (evt.status || '').toLowerCase().replace(/\s+/g, '-');
            html += '<tr>';
            html += '<td style="white-space: nowrap;">' + formatTime(evt.timestamp) + '</td>';
            html += '<td class="' + statusClass + '">' + escapeHtml(evt.status || '-') + '</td>';
            html += '<td>' + escapeHtml(evt.event || '-') + '</td>';
            html += '<td>' + escapeHtml(evt.friendlyName || '-') + '</td>';
            html += '<td>' + (evt.muted === 1 ? 'Yes' : evt.muted === 0 ? 'No' : '-') + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';

        return this.buildSection('Twilio Status Timeline (' + data.statusTimeline.length + ' events)', html, { collapsed: true });
    }

    buildTechnicalSection(data) {
        var t = data.technical || {};
        var html = '<div class="details-grid">';

        html += '<span class="label">Call SID:</span><span class="value"><span class="technical-id">' + escapeHtml(data.callSid || '-') + '</span></span>';

        if (t.parentCallSid) {
            html += '<span class="label">Parent Call SID:</span><span class="value"><span class="technical-id">' + escapeHtml(t.parentCallSid) + '</span></span>';
        }
        if (t.conferenceSid) {
            html += '<span class="label">Conference SID:</span><span class="value"><span class="technical-id">' + escapeHtml(t.conferenceSid) + '</span></span>';
        }
        if (t.friendlyName) {
            html += '<span class="label">Conference Name:</span><span class="value">' + escapeHtml(t.friendlyName) + '</span>';
        }
        if (t.direction) {
            html += '<span class="label">Direction:</span><span class="value">' + escapeHtml(t.direction) + '</span>';
        }
        if (t.fromNumber) {
            html += '<span class="label">From Number:</span><span class="value">' + escapeHtml(t.fromNumber) + '</span>';
        }
        if (t.toNumber) {
            html += '<span class="label">To Number:</span><span class="value">' + escapeHtml(t.toNumber) + '</span>';
        }

        // Geographic info
        var fromLocation = [t.fromCity, t.fromState, t.fromCountry].filter(Boolean).join(', ');
        if (fromLocation) {
            html += '<span class="label">From Location:</span><span class="value">' + escapeHtml(fromLocation) + '</span>';
        }

        var toLocation = [t.toCity, t.toState, t.toCountry].filter(Boolean).join(', ');
        if (toLocation) {
            html += '<span class="label">To Location:</span><span class="value">' + escapeHtml(toLocation) + '</span>';
        }

        if (t.apiVersion) {
            html += '<span class="label">API Version:</span><span class="value">' + escapeHtml(t.apiVersion) + '</span>';
        }

        html += '</div>';

        // Copy button
        html += '<div style="margin-top: 15px;">';
        html += '<button class="copy-btn" onclick="copyTechnicalDetails(\'' + escapeHtml(data.callSid) + '\')">';
        html += '<span>Copy Technical Details</span>';
        html += '</button></div>';

        return this.buildSection('Technical Details', html, { type: 'technical', collapsed: true });
    }

    initCollapsibles() {
        var headers = this.modalBody.querySelectorAll('.details-section-header');
        headers.forEach(function(header) {
            header.onclick = function() {
                var section = header.parentElement;
                section.classList.toggle('collapsed');
            };
        });
    }
}

// Global instance
var fullDetailsModal = null;

/**
 * Show full call details in comprehensive modal
 */
function showFullCallDetails(callSid) {
    var request = createRequest();
    if (request == null) {
        modal.error('Error', 'Unable to create request');
        return;
    }

    // Show loading state
    if (fullDetailsModal && fullDetailsModal.modal) {
        fullDetailsModal.modalBody.innerHTML = '<div style="text-align: center; padding: 40px;"><span style="font-size: 18px;">Loading...</span></div>';
        fullDetailsModal.modal.classList.add('show');
    }

    var url = "getFullCallDetails.php?CallSid=" + encodeURIComponent(callSid);
    request.open("GET", url, true);
    request.setRequestHeader("Cache-Control", "no-cache, must-revalidate");
    request.onreadystatechange = function() {
        if (request.readyState == 4) {
            if (request.status == 200) {
                try {
                    var data = JSON.parse(request.responseText);
                    fullDetailsModal.show(data);
                } catch (e) {
                    modal.error('Error', 'Failed to parse response: ' + e.message);
                    if (fullDetailsModal) fullDetailsModal.hide();
                }
            } else {
                modal.error('Error', 'Failed to fetch full details: HTTP ' + request.status);
                if (fullDetailsModal) fullDetailsModal.hide();
            }
        }
    };
    request.send(null);
}

/**
 * Copy technical details to clipboard
 */
function copyTechnicalDetails(callSid) {
    // Build text to copy
    var technicalSection = document.querySelector('.details-section.technical .details-grid');
    if (!technicalSection) {
        alert('Could not find technical details');
        return;
    }

    var labels = technicalSection.querySelectorAll('.label');
    var values = technicalSection.querySelectorAll('.value');
    var text = 'Call Technical Details\n';
    text += '======================\n';

    for (var i = 0; i < labels.length; i++) {
        var label = labels[i].textContent.replace(':', '');
        var value = values[i].textContent;
        text += label + ': ' + value + '\n';
    }

    // Copy to clipboard
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            // Show copied feedback
            var btn = document.querySelector('.copy-btn');
            if (btn) {
                btn.classList.add('copied');
                btn.querySelector('span').textContent = 'Copied!';
                setTimeout(function() {
                    btn.classList.remove('copied');
                    btn.querySelector('span').textContent = 'Copy Technical Details';
                }, 2000);
            }
        }).catch(function(err) {
            alert('Failed to copy: ' + err);
        });
    } else {
        // Fallback for older browsers
        var textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            alert('Technical details copied to clipboard');
        } catch (err) {
            alert('Failed to copy to clipboard');
        }
        document.body.removeChild(textarea);
    }
}


function UserSelected(id) {
    if (id != null) {
    
		newUserEntry = false;

        request = createRequest();

        if (request == null) {
            alert("Unable to create request");
            return;
        }
        
        document.getElementById("DetailIdNum").value = id;
   
        var url = "userDetail.php?UserID=" + id;

        request.open("GET", url, true);
        request.setRequestHeader("Cache-Control" , "no-cache, must-revalidate");
        request.onreadystatechange = UserSelectedResults;
        request.send(null);
    }
}


function ReMarkUsers() {
    Users = document.getElementById("CurrentUsers").getElementsByTagName("span");
    var finalClass = "";
    
    for (var i=0; i<Users.length; i++) {
        var item = Users[i];
        Class = item.getAttribute("class");
        var Classes = Class.split(" ");
    
        for (j=0; j<Classes.length; j++) {
            itemClass = Classes[j];
            if (itemClass != "UserSelected") {
                var finalClass = finalClass + " " + itemClass;
            }
        }
        item.setAttribute("class",finalClass);
        finalClass = "";
    }
}


function resetMenus() {
	document.getElementById("DetailUserForm").reset();

}


function UserSelectedResults() {
    if (request.readyState == 4) {
        if (request.status == 200) {
        
            UserType = 0;

			message=document.getElementById("UserMessage");
			while(message.firstChild) {
				message.removeChild(message.firstChild);
			}
			message.appendChild(document.createTextNode("MODIFY USER DATA"));
			document.getElementById("DetailUserForm").reset();
            resetMenus();
            var userData = JSON.parse(request.responseText);
            
            
            //Eliminate Prior UserSelected Records
            ReMarkUsers();
            

            var DetailIdNum     = document.getElementById("DetailIdNum");
            var DetailFirstName = document.getElementById("DetailFirstName");
            var DetailLastName  = document.getElementById("DetailLastName");
            var DetailskypeID    = document.getElementById("DetailskypeID");
            var DetailUserID    = document.getElementById("DetailUserID");
			var userTypesVolunteer 		= document.getElementsByName('userTypesVolunteer')[0];
			var userTypesResourcesOnly 	= document.getElementsByName('userTypesResourcesOnly')[0];
			var userTypesAdmin 			= document.getElementsByName('userTypesAdmin')[0];
			var userTypesAdminResources = document.getElementsByName('userTypesAdminResources')[0];
		    var userTypesResourceAdmin	    = document.getElementsByName("userTypesResourceAdmin")[0];
			var userTypesTrainer 		= document.getElementsByName('userTypesTrainer')[0];
			var userTypesMonitor 		= document.getElementsByName('userTypesMonitor')[0];
			var userTypesGroupChatModerator 		= document.getElementsByName('userTypesGroupChatModerator')[0];
			var userTypesTrainee 		= document.getElementsByName('userTypesTrainee')[0];
			var DetailUserLocationOffice 		= document.getElementsByName('userLocation')[0];
			var DetailUserLocationHome 		= document.getElementsByName('userLocation')[1];
			var DetailUserPreferredHotlineYouth 		= document.getElementsByName('userPreferredHotline')[0];
			var DetailUserPreferredHotlineSage 		= document.getElementsByName('userPreferredHotline')[1];
			var DetailUserPreferredHotlineNone 		= document.getElementsByName('userPreferredHotline')[2];
			var CallerTypeSelectMenu 		= document.getElementsByName('callerTypeSelectMenu');

            DetailIdNum.value = userData['IdNum'];
            DetailFirstName.value = userData['FirstName'];
            DetailLastName.value = userData['LastName'];
            DetailskypeID.value = userData['skypeID'];
            DetailUserID.value = userData['UserID'];

            if(userData['Volunteer']) {
            	userTypesVolunteer.checked = true;
            }
            
            if(userData['ResourceOnly']) {
            	userTypesResourcesOnly.checked = true;
            }
            
            if(userData['AdminUser']) {
	            userTypesAdmin.checked = true;
            }
            
            if(userData['ResourceAdmin']) {
	            userTypesResourceAdmin.checked = true;
            }
            
            if(userData['AdminResources']) {
	            userTypesAdminResources.checked = true;
            }

            if(userData['Trainer']) {
            	userTypesTrainer.checked = true;
            }
            
            if(userData['Monitor']) {
	            userTypesMonitor.checked = true;
            }
            
            if(userData['GroupChat']) {
	            userTypesGroupChatModerator.checked = true;
            }

            if(userData['Trainee']) {
	            userTypesTrainee.checked = true;
            }
            
            if(userData['CallerType'] == 0) {
            	document.getElementById("callerTypeBoth").selected = true;
            } else if (userData['CallerType'] == 1) {
            	document.getElementById("callerTypeChat").selected = true;
            } else if (userData['CallerType'] == 2) {
            	document.getElementById("callerTypeCall").selected = true;
            }
            


            	
            if(userData['Location'] == "SF") {
				DetailUserLocationOffice.checked = true;
            } else {
				DetailUserLocationHome.checked = true;
			}

            if(userData['PreferredHotline'] == "Youth") {
				DetailUserPreferredHotlineYouth.checked = true;
            } else if(userData['PreferredHotline'] == "SAGE")  {
				DetailUserPreferredHotlineSage.checked = true;
			} else {
				DetailUserPreferredHotlineNone.checked = true;
			}	
			
			var UserSelected = document.getElementById(userData['IdNum']);
			var OldClass = UserSelected.getAttribute("class");
			UserSelected.setAttribute("class", OldClass + " UserSelected"); 
        }
        
        NewUser = document.getElementById("NewUser");
        NewUser.value="Delete User";
        NewUser.onclick=DeleteUser;
        
        document.getElementById("SubmitButton").focus();
    }
 }


function DeleteUser() {
    deleteRequest = createRequest();

    if (deleteRequest == null) {
        alert("Unable to create request");
        return;
    }
        
    id=document.getElementById("DetailIdNum").value;
   
    var url = "UserDelete.php?IdNum=" + id;

    deleteRequest.open("GET", url, true);
    deleteRequest.onreadystatechange = deleteConfirm;
    deleteRequest.send(null);
}


function deleteConfirm() {
	if (deleteRequest.readyState == 4) {
        if (deleteRequest.status == 200) {
   			var response = deleteRequest.responseText;
            //alert (response);       
			ReInitializePage();
		    window.location.reload();

        }
    }
}


function Selected(frontItem) {
   	var tabButtons = document.getElementById("WorkTabs").getElementsByTagName("span");
    for (var i=0; i<tabButtons.length; i++) {
        var item = tabButtons[i];
        if (item.getAttribute("id") == frontItem.getAttribute("id")) {
            item.setAttribute("class","Selected");
        } else {
            item.setAttribute("class","NotSelected");
        }
    }
    if (frontItem.getAttribute("id") == "Tab3") {
  		var infoCenter = new InfoCenter();
    } else {
  		var infoCenter = "";
  	}
    othersToBottom(frontItem);
}


function othersToBottom(topPane) {
    panes = document.getElementById("DataPane").getElementsByTagName("div");
	var IMAll = document.getElementById("IMAll");
	var exitButton = document.getElementById("ExitButton");
	var calendar = document.getElementById('Calendar');
	calendar.style.display = 'none';

	var groupChat = document.getElementById('groupChat');
	if(groupChat) {
		groupChat.style.display = 'none';
	}
	var widget = document.getElementById('Widget');
	if(widget) {
		widget.style.display = 'none';
	}
	IMAll.style.display = null;
	ExitButton.style.display = null;

    for (var i=0; i<panes.length; i++) {
		if(!AdminMiniUser) { 
			if(topPane == 'Tab7' || topPane == 'Tab9' || topPane == 'Tab10') {
				document.getElementById('CallHistoryList').style.display = 'none';
			} else {
				document.getElementById('CallHistoryList').style.display = null;
			}
		}

        var item = panes[i];
        var pane = item.getAttribute("id");
        switch (topPane.getAttribute("id")) {
            case 'Tab1':    var frontPane = 'Users';
                            break;
                            
            case 'Tab2':    var frontPane = 'CallLog';
                            break;
                            
            case 'Tab3':    var frontPane = 'InfoCenter';
                            break;
                            
            case 'Tab5':    var frontPane = 'ResourceSearch';
                            break;
                            
            case 'Tab6':    var frontPane = 'callBlocking';
                            break;
                            
            case 'Tab7':    var frontPane = 'Calendar';
							calendar.style.display = 'block';
							IMAll.style.display = 'none';
							ExitButton.style.display = 'none';
                            break;
            case 'Tab8':    var frontPane = 'Stats';
                            break;
            case 'Tab9':    var frontPane = 'groupChat';
							groupChat.style.display = 'block';
							// Add cache-busting to GroupChat iframe when tab is accessed
							var groupChatFrame = document.getElementById('groupChatFrame');
							if (groupChatFrame) {
								var currentSrc = groupChatFrame.src;
								var baseSrc = currentSrc.split('?')[0]; // Remove existing parameters
								groupChatFrame.src = baseSrc + "?cb=" + new Date().getTime();
							}
							IMAll.style.display = 'none';
							ExitButton.style.display = 'none';
                            break;
            case 'Tab10':   var frontPane = 'Widget';
							widget.style.display = 'block';
							IMAll.style.display = 'none';
							ExitButton.style.display = 'none';
                            break;
        }
        if (pane != frontPane && pane != 'ColumnHeaders') {
            item.setAttribute("class","PaneNotSelected");
        } else if (pane == frontPane && pane != 'ColumnHeaders') {
            item.setAttribute("class","PaneSelected");
        }
    }
	return;
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
            
            
function WriteToFile(){

    Start = document.getElementById("CallLogStart").value;
    End = document.getElementById("CallLogEnd").value;
    if(!AdminMiniUser) {
    	url = "downloadLog.php?Start=" + Start + "&End=" + End + "&Type=All";
    } else {
    	url = "downloadLog.php?Start=" + Start + "&End=" + End + "&Type=Mini";
    
    }
    
    CallLog = document.getElementById("CallLogFile");
    CallLog.innerHTML = "";
    ir=document.createElement('iframe');
    ir.setAttribute("id","ifr");
    ir.setAttribute("src",url);
    ir.setAttribute("style","display:none");
    CallLog.appendChild(ir);
}
 
 
function WriteToFileCallerHistory(){
    Start = document.getElementById("CallLogStart").value;
    End = document.getElementById("CallLogEnd").value;
    url = "downloadCallerHistory.php?Start=" + Start + "&End=" + End;
    
    CallLog = document.getElementById("CallLogFile");
    CallLog.innerHTML = "";
    ir=document.createElement('iframe');
    ir.setAttribute("id","ifr");
    ir.setAttribute("src",url);
    ir.setAttribute("style","display:none");
    CallLog.appendChild(ir);
}


function WriteToFileResourceUpdateHistory(){
    Start = document.getElementById("CallLogStart").value;
    End = document.getElementById("CallLogEnd").value;
    url = "resourceUpdateReport.php?Start=" + Start + "&End=" + End;
        
    mywindow = window.open (url , "Profiles", "resizable=yes,titlebar=1,toolbar=1,scrollbars=yes,status=no,height=840,width=750,addressbar=0,menubar=0,location=0");  
    mywindow.moveTo(250,250);
    mywindow.focus();

}




function WriteToFileChatHistory(){
    Start = document.getElementById("CallLogStart").value;
    End = document.getElementById("CallLogEnd").value;
    url = "downloadChatData.php?Start=" + Start + "&End=" + End;
    
    CallLog = document.getElementById("CallLogFile");
    CallLog.innerHTML = "";
    ir=document.createElement('iframe');
    ir.setAttribute("id","ifr");
    ir.setAttribute("src",url);
    ir.setAttribute("style","display:none");
    CallLog.appendChild(ir);
}


function WriteToFileBlockedCallers() {

    url = "downloadBlockedCallers.php";
    
    CallLog = document.getElementById("CallLogFile");
    ir=document.createElement('iframe');
    ir.setAttribute("id","ifr");
    ir.setAttribute("src",url);
    ir.setAttribute("style","display:none");
    CallLog.appendChild(ir);
}


function WriteToFileVolunteerLog() {
    Start = document.getElementById("CallLogStart").value;
    End = document.getElementById("CallLogEnd").value;

    url = "downloadVolunteerLog.php?Start=" + Start + "&End=" + End;
    
    CallLog = document.getElementById("CallLogFile");
    ir=document.createElement('iframe');
    ir.setAttribute("id","ifr");
    ir.setAttribute("src",url);
    ir.setAttribute("style","display:none");
    CallLog.appendChild(ir);
}


function WriteToFileResources() {

    url = "downloadResources.php";
    
    Resources = document.getElementById("CallLogFile");
    ir=document.createElement('iframe');
    ir.setAttribute("id","ifr");
    ir.setAttribute("src",url);
    ir.setAttribute("style","display:none");
    Resources.appendChild(ir);
}


function Exit() {
    // DEBUG: Log what's calling Exit()
    console.log("Exit() called - Stack trace:");
    console.trace();
    
    Administrator = document.getElementById("AdministratorID").value;

	userList = "";
	if(!AdminMiniUser) {
		newIMMonitor.source.close();
		newIMMonitor.source="";
		newIMMonitor = "";
		removeElements(document.getElementById("callHistoryScrollList"));
	}
	removeElements(document.getElementById("Calendar"));
	removeElements(document.getElementById("Stats"));
	
    
    request = createRequest();

    if (request == null) {
        alert("Unable to create request");
        return;
    }

    var url="ExitProgram.php?VolunteerID=" + Administrator;
    
    request.open("GET", url, false);
    request.send(null);
	exitGroupChat(Administrator);    
	removeElements(document.getElementById("groupChat"));
	
	window.location.assign("../login.php");
}


function exitGroupChat(user) {
    // DEBUG: Log what's calling exitGroupChat()
    console.log("exitGroupChat() called with user:", user, "- Stack trace:");
    console.trace();
    
    groupChatRequest = createRequest();

    if (request == null) {
        alert("Unable to create request");
        return;
    }

    console.log("exitGroupChat calling ExitProgram.php with VolunteerID:", user);
    var url="ExitProgram.php?VolunteerID=" + user;
    
    groupChatRequest.open("GET", url, false);
    groupChatRequest.send(null);
}


//Updated IM Functions
function IMMonitor(name) {
	this.source = "";

	this.init = function () {
			if(typeof(EventSource)!=="undefined") {
				this.source = new EventSource("../vccFeed.php?reset=1");
				var source=this.source;
	
				source.addEventListener('userList', function(event) {
					if(IMMonitorKeepAlive != null) {
						clearTimeout(IMMonitorKeepAlive);
					}
					IMMonitorKeepAlive = setTimeout("newIMMonitor.init();",30000);
			
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
							if(userRecord.AdminLoggedOn == 2 || userRecord.AdminLoggedOn == 7) {
								userDisplayed.childNodes[1].innerHTML = 'n/a';
							} else if (userRecord.AdminLoggedOn == 3) {
								userDisplayed.childNodes[1].innerHTML = 'Res.';
							} else {
								userDisplayed.childNodes[1].innerHTML = userRecord.shift;
							}
							
							if(userRecord.adminRinging && !AdminMiniUser) {
								var Ref = "javascript:phoneNumberPopup('" + userRecord.incomingNumber + "')";
								var menuItem = document.createElement("a");
								menuItem.setAttribute("href",Ref);
				                menuItem.appendChild(document.createTextNode(userRecord.adminRinging));
								while (userDisplayed.childNodes[2].firstChild) {
									userDisplayed.childNodes[2].removeChild(userDisplayed.childNodes[2].firstChild);
								}
								userDisplayed.childNodes[2].appendChild(menuItem);
							} else if(!AdminMiniUser) {								
			
								var displayOnCallText = userRecord.onCall;
		
								if(displayOnCallText == "GLNH") {
									displayOnCallText = "LGBTQ";
								}
								userDisplayed.childNodes[2].innerHTML = displayOnCallText;								
							} else {
								userDisplayed.childNodes[2].innerHTML = "";
							}
						
							
							userDisplayed.childNodes[3].innerHTML = userRecord.chat;
						} else {
							var userTable = document.getElementById('volunteerListTable');
							var tr = document.createElement("tr");
						
							tr.setAttribute("id",userRecord.userName);
							var td = document.createElement("td");
							td.style.color = "rgb(6,69,173)";
							td.onclick = function() {
								userRecord.loadImPane();
							};
							td.title = "Click to Send an IM to this Volunteer.";	
							td.setAttribute("class" , "hover");

							td.appendChild(document.createTextNode(userRecord.name));
							tr.appendChild(td);

							var td = document.createElement("td");
							td.appendChild(document.createTextNode(userRecord.shift));
							tr.appendChild(td);

							var td = document.createElement("td");
							if(userRecord.adminRinging && !AdminMiniUser) {
								td.appendChild(document.createTextNode(userRecord.adminRinging));
							} else {								
								if(!AdminMiniUser) {
									td.appendChild(document.createTextNode(userRecord.onCall));
								} else {
									td.appendChild(document.createTextNode(""));
								}
							}
							tr.appendChild(td);
						
							var td = document.createElement("td");
							td.appendChild(document.createTextNode(userRecord.chat));
							tr.appendChild(td);

							var td = document.createElement("td");
							if (!userRecord.oneChatOnly) {
								var oneChatOnly = "-";
							} else {
								var oneChatOnly = "Yes";
							}
							td.appendChild(document.createTextNode(oneChatOnly));
							tr.appendChild(td);

//							var td = document.createElement("td");
//							var button = document.createElement('input');
//							button.type = 'button';
//							button.value='Video';
//							button.id=userRecord.userName/+ 'Video';
//							button.onclick = function() {alert("Video Link Coming Soon");}
//							td.appendChild(button);
//							tr.appendChild(td);

							var td = document.createElement("td");
							input = document.createElement("input");
							input.setAttribute("type","button");
							input.setAttribute("class","UserLogoffButton");
							input.setAttribute("value","Log Off");
							input.setAttribute("id",userRecord.userName + "LogoffButton");
							input.setAttribute("name",userRecord.userName);
							input.onclick = function() {LogoffUser(this.name);}
							td.appendChild(input);
							tr.appendChild(td);
							userTable.appendChild(tr);		
						}					
					});
					var usersListed = document.getElementById("volunteerListTable").getElementsByTagName("tr");
					if(usersListed.length > 1) {
						for (var i=0; i<usersListed.length; i++) {
							var userID = usersListed[i].getAttribute("id");
							if(!onlineUsers[userID] && userID != "userListHeader") {
								document.getElementById('volunteerListTable').removeChild(document.getElementById(userID));
								delete(userList[userID]);
							}
						}
					}
					if(!AdminMiniUser) {    					
						callHistoryListUpdate(callHistorySort);
					}


				},false);				

				source.addEventListener('IM', function(event) {
					message = JSON.parse(event.data);
					if(message && message.from) {
						userList[message.from].receiveIm(message);		
					} else {
					}
				},false);			
				
				
				source.addEventListener('logoff', function(event) {
					console.log("Logoff event received - data:", event.data);
					if(event.data == "0") {
						console.log("Received logoff data=0 - Admin users ignore this due to session/permission issues");
						// For admin users, ignore logoff=0 events entirely
						// These are typically caused by session file permission issues, not actual logouts
						// Admin users must manually click EXIT to logout
					} else if(event.data == "2") {
						console.log("Admin user confirmed logged in (data=2)");
					} else {
						console.log("Not triggering exit - data is not 0 (LoggedOn status: " + event.data + ")");
					}				
				},false);			//Logoff		
				
		} else {
			document.getElementById("result").innerHTML="Sorry, your browser does not support server-sent events...";
		}
	};

}

// Twilio Functions for Call Monitoring
function callMonitor() {
	var token = document.getElementById("token").value;	
	Twilio.Device.setup(token);

	Twilio.Device.sounds.incoming(false);

	Twilio.Device.ready(function (device) {
	});

	Twilio.Device.error(function (error) {
		alert("Twilio Error: " + error.message);
	});

	Twilio.Device.connect(function (conn) {
		monitorConnected(conn);
	});

	Twilio.Device.disconnect(function (conn) {
		endMonitor(conn);
	});

	Twilio.Device.incoming(function (conn) {
	});	  

	Twilio.Device.cancel( function(conn) {
		endMonitor(conn);
	});
}





function monitorCall (userName) {
	var conferenceRoomParameters = "{RedirectConferenceRoom: '" + userName + "'}";
	this.call = Twilio.Device.connect(conferenceRoomParameters);
}

function monitorConnected(conn) {
		alert("Monitor Connected to: " + conn.from);
	};
	
function endMonitor(conn) {
		Twilio.Device.disconnectAll();
//		alert("Monitor Ended");
	};







//User Objects and Functions

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
	this.volunteerID = document.getElementById("AdministratorID").value;

	this.userName = user.UserName;
	var self = this;
	if(!user.pronouns) {
		this.name = user.FirstName + " " + user.LastName;
	} else {
		this.name = user.FirstName + " " + user.LastName + " (" + user.pronouns + ")";
	}

	this.shift = user.Shift;
	this.onCall = user.OnCall;
	this.chat = user.Chat;
	this.AdminLoggedOn = user.AdminLoggedOn;
	this.currentUser = user.currentUser;
	this.adminRinging = user.adminRinging;

	if(this.adminRinging) {
		var phoneNumberStart = user.adminRinging.indexOf("(");	
		this.incomingNumber = user.adminRinging.substr(phoneNumberStart , 14);
	}
	
	
	this.im = [];
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
		self.shift = 			user.Shift;
		self.onCall = 			user.OnCall;
		self.chat = 			user.Chat;
		self.adminRinging = 	user.adminRinging;
		if(self.adminRinging) {
			var phoneNumberStart = self.adminRinging.indexOf("(");	
			self.incomingNumber = self.adminRinging.substr(phoneNumberStart , 14);
		}
	};
	
	
	this.loadImPane = function () {
		locateImPane(self);
		self.imBody.innerHTML = "";
//		self.imMessage.value = "";
		self.imPane.style.display = "block";
		self.im.forEach(function (im) {
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
		var message = {};
		message.text = user.imMessage.value;
		user.imMessage.value = "";
		message.from = this.volunteerID;
		message.to = user.userName;
		if(user.userName == "All") {
			var imMessage = new ImMessage(message);
			for (var key in userList) {
				var user = userList[key];
				if(user.userName != 'All' && !user.currentUser) {
					user.im.push(imMessage);
				}
			}
		} else {
			var imMessage = new ImMessage(message);
//			user.im.push(imMessage);
		}
		var url = '../volunteerPosts.php';
		var resultsObject = new Object();
		var postResults = function (results, searchObject) {
			testResults(results, searchObject);
		};
		params = "postType=postIM&action=" + encodeURIComponent(user.userName) + "&text=" + message.text;
		var postIM = new AjaxRequest(url, params, postResults, resultsObject);
		return false;
	};


	this.receiveIm = function (message) {
		var imMessage = new ImMessage(message);
		if(message.from == this.volunteerID) {
			userList[message.to].im[message.id] = imMessage;
			var url = "../volunteerPosts.php";
			var	params = "postType=IMReceived&action=" + message.id + "&text=from";
			var updateMessageStatusResults = function (results, searchObject) {
				testResults(results, searchObject);
			};
			var updateMessageStatus = new AjaxRequest(url, params, updateMessageStatusResults);
		} else {
			var gabeIMSound = document.getElementById("IMSound");
			if(gabeIMSound && !userList[message.from].im[message.id]) {
				gabeIMSound.play();
			}
			userList[message.from].im[message.id] = imMessage;
			userList[message.from].loadImPane();
			var url = "../volunteerPosts.php";
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
	this.message = 		message.text;
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



function testResults(results, resultsObject) {
	if(results) {
		alert(results);
	}
}









function IMAllRoutine() {
    
    var UserName = document.getElementById("VolunteerID");
    VolunteerID = document.getElementById("AdministratorID").value;
    
    if(!userList['All']) {
    	var message = {'idnum':'all',
    					'FirstName': 'All', 		
						'LastName': 'Users',
						'Shift': '1',
						'Office': '1',
						'Desk': '1', 
						'OnCall': '0',
						'Chat1': '',
						'Chat2': '',
						'UserName':'All',
						'ringing': '',
						'ChatOnly':  '',
						'AdminLoggedOn':  '',
						'oneChatOnly': 	 ''}
						
	    userList['All'] = new User(message);
	}
	userList['All'].loadImPane();
}





function messageCheck() {
    RoomStatusRequest = createRequest();
    if (RoomStatusRequest == null) {
        alert("Unable to create request");
        return;
    }
    VolunteerID = document.getElementById("AdministratorID").value;
    var url= "VolunteerRoomStatus.php?Room=" + encodeURIComponent("1") + "&VolunteerID=" + encodeURIComponent(VolunteerID);
    
    RoomStatusRequest.open("POST", url, true);
    RoomStatusRequest.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
    RoomStatusRequest.setRequestHeader("Cache-Control" , "no-cache, must-revalidate");
    RoomStatusRequest.onreadystatechange = messageCheckResults;
    RoomStatusRequest.send(null);
}




function messageCheckResults() {

      if (RoomStatusRequest.readyState == 4) {
        if (RoomStatusRequest.status == 200) {
        
            var VolunteerID = document.getElementById("AdministratorID").value;
            var Response = RoomStatusRequest.responseXML;

                
            var Items = Response.getElementsByTagName("item");
                       

            if (Items.length == 0) {
                  return;

            } else {
                // Display Record
                for (var i=0; i<Items.length; i++) {
                    var item = Items[i];

                   
                    var IMSenderElement = item.getElementsByTagName("imsender")[0];
                    if (IMSenderElement.firstChild.nodeValue == "None") {
                        var IMSender = " ";
                    } else {
                        var IMSender = IMSenderElement.firstChild.nodeValue;
                    }

                    var IMSenderIDElement = item.getElementsByTagName("imsenderid")[0];
                    var IMSenderID = IMSenderIDElement.firstChild.nodeValue;

                    var InstantMessageElement = item.getElementsByTagName("instantmessage")[0];
                    var InstantMessage = InstantMessageElement.firstChild.nodeValue;
                                     
                    if (IMSender == " ") {
                        return;
                    } else { 
                        UserID = VolunteerID;
                        Type = "Receive";
                        Sender = UserID;
                        Message = InstantMessage;
                        mypopup(IMSender,IMSenderID,Type,Sender,Message);
                    }
                }
            }
        }
    }
}






function uploadPridePathSpreadsheet(event, type) {
	var input = document.getElementById('pridePath' + type.charAt(0).toUpperCase() + type.slice(1));
	var file = input.files[0];
	
	if (!file) {
		alert('Please select a file to upload');
		event.preventDefault();
		return false;
	}
	
	var allowedTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'];
	var allowedExtensions = ['.xls', '.xlsx', '.csv'];
	var fileName = file.name.toLowerCase();
	var hasValidExtension = allowedExtensions.some(ext => fileName.endsWith(ext));
	
	if (!allowedTypes.includes(file.type) && !hasValidExtension) {
		alert('Please select a valid spreadsheet file (.xlsx, .xls, or .csv)');
		event.preventDefault();
		return false;
	}
	
	// Allow the form to submit normally if validation passes
	return true;
}

function newVolunteerVideoFile() {
	var input = document.getElementById('newVolunteerVideo');
	var file = input.files[0];
	var formData = new FormData();
	formData.append('file', input);

	var textType = /video.*/;
	if (file.type.match(textType)) {
		videoUploadRequest = createRequest();
		videoUploadRequest.open("POST", "videoUpload.php", true);
		videoUploadRequest.setRequestHeader("HTTP_X_FILENAME", file.name);
		videoUploadRequest.onreadystatechange = uploadVideoResults;
		videoUploadRequest.send(formData);
	} else {
	  alert("File not supported!  Name: " + file.name + "\n" + "Last Modified Date :" + file.lastModifiedDate);
	}
}





function uploadVideoResults() {
    if (videoUploadRequest.readyState == 4) {
        if (videoUploadRequest.status == 200) { 
            responseDoc = videoUploadRequest.responseText;
			alert("New Volunteer Video Uploaded");
		}
	}
}







function phoneNumberPopup(phoneNumber){
	
    url = "callerHistory.php?CallerID=" + phoneNumber;

	if (phoneNumber == " ") {
		return;
	}
	
    myNewWindow = window.open (url , "Caller History: " + phoneNumber, "resizable=no,titlebar=0,toolbar=0,scrollbars=no,status=no,height=800,width=750,addressbar=0,menubar=0,location=0");  
    myNewWindow.moveTo(50,50);
    myNewWindow.focus();
} 






function InfoCenter() {
	var self = this;
	this.infoCenterPane = document.getElementById("InfoCenter");
	this.infoCenterText = document.getElementById("infoCenterText");
	this.infoCenterButtons = document.getElementById("infoCenterButtons").getElementsByTagName("input");
	this.currentDisplayedItem = "";

 	if(!AdminMiniUser) {
		this.newInfoCenterFile = document.getElementById("newInfoCenterFile");
		this.newInfoCenterFile.onchange = function() {
			self.uploadNewFile();
		}
		this.infoCenterDeleteCurrentItem = document.getElementById("infoCenterDeleteCurrentItem");
		this.infoCenterDeleteCurrentItem.onclick = function() {
			if(confirm("Delete the '" + self.currentDisplayedItem + "' InfoCenter Item?")) {
				self.deleteCurrentItem(self);
			} else {
				alert("'" + self.currentDisplayedItem  + "' will remain in InfoCenter.");
			}
		}
	}	

	this.params = "postType=infoCenter";
	this.url = "../volunteerPosts.php";
	this.text = {};

	for (i=0;i<this.infoCenterButtons.length; i++) {
		item = this.infoCenterButtons[i];
		item.onclick = function() {
			var finalParams = self.params + "&action=" + this.value;
			var infoCenterText = new AjaxRequest(self.url, finalParams, self.infoCenterResponse , self);
			self.currentDisplayedItem = this.value;
		};
	}
		
				
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
	
	this.uploadNewFile = function() {
		var file = this.newInfoCenterFile.files[0];
		var textType = /text.*/;
		if (file.type.match(textType)) {
		  var reader = new FileReader();

		  reader.onload = function(e) {
			var finalParams = "postType=infoCenterUpload&action=" + file.name + "&text=" + encodeURIComponent(reader.result);
			var infoCenterText = new AjaxRequest(self.url, finalParams, self.infoCenterUploadResponse , self);
			var buttonList = document.getElementById("infoCenterButtons");
			var newButton = document.createElement("input");
			newButton.setAttribute("type","button");
			newButton.setAttribute("class","infoCenterButton");
			var buttonName = file.name.split(".")[0];
			newButton.setAttribute("value",buttonName);
			newButton.onclick = function() {
				var finalParams = self.params + "&action=" + this.value;
				var infoCenterText = new AjaxRequest(self.url, finalParams, self.infoCenterResponse , self);
				self.currentDisplayedItem = this.value;
			}
			buttonList.appendChild(newButton);
			self.infoCenterText.innerHTML = reader.result;
			self.infoCenterText.scrollTop -= self.infoCenterText.scrollHeight;
	
		  }
		  reader.readAsText(file);  
		} else {
		  alert("File not supported!  Name: " + file.name + "\n" + "Last Modified Date :" + file.lastModifiedDate);
    	}
	};
	
	this.infoCenterUploadResponse = function(results, resultObject) {
		alert("New InfoCenter Item Uploaded.");
	};
	
	this.deleteCurrentItem = function(self) {
		var finalParams = "postType=infoCenterDeleteItem&action=" + self.currentDisplayedItem;
		var infoCenterText = new AjaxRequest(self.url, finalParams, self.infoCenterDeleteItemResponse , self);
	};
	
	this.infoCenterDeleteItemResponse = function(results, resultObject) {
		for (i=0;i<self.infoCenterButtons.length; i++) {
			item = self.infoCenterButtons[i];
			if(item.value == self.currentDisplayedItem) {
				document.getElementById("infoCenterButtons").removeChild(item);
			}
		}
		removeElements(document.getElementById("infoCenterText"));
		alert("Item Deleted: " + results);
	};
}


function getWelcomeText() {
    WelcomeTextRequest = createRequest();
    
    if (WelcomeTextRequest == null) {
        alert("Unable to create request");
        return;
    }
    
    var url= "../GetWelcomeMessage.php";

    WelcomeTextRequest.open("GET", url, true);
    WelcomeTextRequest.setRequestHeader("Cache-Control" , "no-cache, must-revalidate");
    WelcomeTextRequest.onreadystatechange = displayWelcomeText;
    WelcomeTextRequest.send(null);
}


function displayWelcomeText() {
    if (WelcomeTextRequest.readyState == 4) {

        if (WelcomeTextRequest.status == 200) { 
        
            responseDoc = WelcomeTextRequest.responseText;
			document.getElementById("InfoCenterWelcomeMessage").value = responseDoc;
		}
	}
}



function postWelcomeMessage() {

	PostTextRequest = createRequest();
    
    if (PostTextRequest == null) {
        alert("Unable to create request");
        return;
    }

	var Message = document.getElementById("InfoCenterWelcomeMessage").value;

    var url= "UpdateWelcomeMessage.php?Message=" + encodeURIComponent(Message);


    PostTextRequest.open("GET", url, true);
    PostTextRequest.setRequestHeader("Cache-Control" , "no-cache, must-revalidate");
    PostTextRequest.onreadystatechange = postWelcomeMessageResult;
    PostTextRequest.send(null);
}


function postWelcomeMessageResult() {
    if (PostTextRequest.readyState == 4) {

        if (PostTextRequest.status == 200) { 
        
			responseDoc = PostTextRequest.responseText;

			responseDoc = responseDoc.replace(/(\r\n|\n|\r)/gm,"");
			responseDoc = responseDoc.trim();
			
			 if (responseDoc == "OK") {
				 alert("Message Updated!");
			} else {
			 	alert ("There was a problem updating the Welcome Screen Message.  Call Tim: " + "|" + responseDoc + "|");
			 }
		}
	}
}


function getInfoCenterItem() {
     InfoCenterItemRequest = createRequest();
    
    if (InfoCenterItemRequest == null) {
        alert("Unable to create request");
        return;
    }

    var item = this.value;
    
    var url= "../InfoCenter.php?item=" + encodeURIComponent(item);
    
    InfoCenterItemRequest.open("GET", url, true);
    InfoCenterItemRequest.setRequestHeader("Cache-Control" , "no-cache, must-revalidate");
    InfoCenterItemRequest.onreadystatechange = displayInfoCenterItem;
    InfoCenterItemRequest.send(null);
}




function displayInfoCenterItem() {    
      
   if (InfoCenterItemRequest.readyState == 4) {
        if (InfoCenterItemRequest.status == 200) { 
        
            var responseDoc = InfoCenterItemRequest.responseText;
            var TableDiv = document.getElementById("InfoCenterText");
            TableDiv.innerHTML = responseDoc;
            
            TableDiv.scrollTop = TableDiv.scrollTop - TableDiv.scrollTop;

        }
    }
}


function blockListUpdate(Type) {

    blockListUpdateRequest = createRequest();
    
    if (blockListUpdateRequest == null) {
        alert("Unable to create request");
        return;
    }
    
    var url= "BlockedList.php?ListType=" + encodeURIComponent(Type);
        
    blockListUpdateRequest.open("GET", url, true);
    blockListUpdateRequest.setRequestHeader("Cache-Control" , "no-cache, must-revalidate");
    blockListUpdateRequest.onreadystatechange = function(){displayBlockList(Type)};
    blockListUpdateRequest.send(null);

}



function displayBlockList(Type) {
	if (blockListUpdateRequest.readyState == 4) {
		if (blockListUpdateRequest.status == 200) { 
				
			var resources = JSON.parse(blockListUpdateRequest.responseText);

			if(Type == 'Admin') {			
				var adminDiv = document.getElementById("adminBlocked");  
				var innerAdminDiv = document.createElement("div");  
				innerAdminDiv.setAttribute("id","adminBlockedTable");
			} else {
				var adminDiv = document.getElementById("userBlocked");  
				var innerAdminDiv = document.createElement("div");  
				innerAdminDiv.setAttribute("id","userBlockedTable");
			}

			// Remove Existing List
			for (var j=adminDiv.childNodes.length; j>0; j--) {
				adminDiv.removeChild(adminDiv.childNodes[j-1]);     
			}

			p = document.createElement("p");

			if(Type == 'Admin') {
				p.innerHTML = "Admin. Blocked Numbers";
				p.title = 'Listed in Phone Number Order, with Exchange Blocks Listed First';
			} else {
				p.innerHTML = "User Blocked Numbers";
				p.title = 'Listed in Date Order, then Phone Number Order';
			}
			adminDiv.appendChild(p);
			var adminTable = document.createElement("table");

			
			// Create New Admin List
			for (key in resources) {
				var call = resources[key];
				tableRecord = createTableItem(call, Type);
				
				adminTable.appendChild(tableRecord);
			}
				
			innerAdminDiv.appendChild(adminTable);
			adminDiv.appendChild(innerAdminDiv);
			
			if (Type == 'Admin') {
				blockListUpdate('User');
			}
			groupChatBlockListUpdate();
		}
	}
}


function groupChatBlockListUpdate() {
    groupChatBlockListUpdateRequest = createRequest();
    
    if (groupChatBlockListUpdateRequest == null) {
        alert("Unable to create request");
        return;
    }
    
    var url= "../GroupChat/Admin/groupChatBlockList.php";
        
    groupChatBlockListUpdateRequest.open("GET", url, true);
    groupChatBlockListUpdateRequest.setRequestHeader("Cache-Control" , "no-cache, must-revalidate");
    groupChatBlockListUpdateRequest.onreadystatechange = function(){groupChatBlockListUpdateResults()};
    groupChatBlockListUpdateRequest.send(null);
}




function groupChatBlockListUpdateResults() {
	if (groupChatBlockListUpdateRequest.readyState == 4) {
		if (groupChatBlockListUpdateRequest.status == 200) { 

			var resources = JSON.parse(groupChatBlockListUpdateRequest.responseText);

			var adminDiv = document.getElementById("groupChatBlocked");  
			var innerAdminDiv = document.createElement("div");  
			innerAdminDiv.setAttribute("id","groupChatBlockedTable");

			// Remove Existing List
			for (var j=adminDiv.childNodes.length; j>0; j--) {
				adminDiv.removeChild(adminDiv.childNodes[j-1]);     
			}

			p = document.createElement("p");

			p.innerHTML = "Group Chat Blocked List";
			p.title = 'Next Blocked Chatter to Expire Listed First';
			adminDiv.appendChild(p);

			var adminTable = document.createElement("table");
			adminTable.style.marginLeft = "1px";

			// Create New Admin List
			for (key in resources) {
				var resource = resources[key];
				var tableRow = document.createElement("tr");

				var tableblockTime = document.createElement("td");
				tableblockTime.setAttribute("class", "Date");

				var tableblockName = document.createElement("td");
				tableblockName.setAttribute("class", "Name");

				var tableBlockVolunteerName = document.createElement("td");
				tableBlockVolunteerName.setAttribute("class", "User");


				for (key in resource) {
					var value = resource[key];		
					switch(key) {
						case 'id':
							var blockedID = value;
							tableRow.id = "groupChatBlockID" + value;
							break;
						case 'message':
							var blockMessage = value;
							tableRow.title = value;
							break;
						case 'blockEndTime':
							var date = value.split(" ");
							tableblockTime.appendChild(document.createTextNode(date[0]));
							break;
						case 'name':							
							tableblockName.appendChild(document.createTextNode(value));
							break;
						case 'volunteerName':
							tableBlockVolunteerName.appendChild(document.createTextNode(value));
							break;
						default:
							break;					
					}
					tableRow.appendChild(tableblockTime);
					tableRow.appendChild(tableblockName);
					tableRow.appendChild(tableBlockVolunteerName);
				}
				var tableItem = document.createElement("td");
				var button1 = document.createElement("input");
				button1.setAttribute("type", "button");
				button1.setAttribute("value", "Delete");
				button1.setAttribute("id", "groupChatBlockID" + blockedID);
				button1.onclick = function() {
					var blockID = this.id.replace("groupChatBlockID" , "");						
					updateGroupChatBlock(blockID, "delete");
				}
				tableItem.appendChild(button1);

				tableRow.appendChild(tableItem);
				tableRow.setAttribute("title",blockMessage);

				var tableItem = document.createElement("td");
				var button2 = document.createElement("input");
				button2.setAttribute("type", "button");
				button2.setAttribute("value", "Block");
				button2.setAttribute("id", "groupChatBlockID" + blockedID);

				button2.onclick = function() {				
					var blockID = this.id.replace("groupChatBlockID" , "");						
					updateGroupChatBlock(blockID, "update");}
				tableItem.appendChild(button2);

				tableRow.appendChild(tableItem);

				adminTable.appendChild(tableRow);
			}
				
			innerAdminDiv.appendChild(adminTable);
			adminDiv.appendChild(innerAdminDiv);			
		}
	}
}



function updateGroupChatBlock(blockedID, type) {
    var url= "../GroupChat/Admin/updateBlockedUser.php";
	var params = "id=" + blockedID + "&type=" + type;

	var responseObject = {};
	var updateGroupChatBlockRecord = new AjaxRequest(url, params, updateGroupChatBlockResults , responseObject);
}


function updateGroupChatBlockResults(results, resultObject) {
	if (results == "OK") {
		 alert("GroupChat Record Updated!");
	} else {
		alert ("There was a problem updating the Group Chat Blocked Status of this Caller.  Call Tim: " + "|" + results + "|");
	 }
	groupChatBlockListUpdate();
}


function addBlockedNumber(blockedNumber, blockedReason, InternetNumber) {		

	var correctedBlockedNumber = blockedNumber.replace(/\D/g,'');
	var numbers = /^[0-9]*$/.test(correctedBlockedNumber);

	if(numbers && (correctedBlockedNumber.length == 3 || correctedBlockedNumber.length == 6 || correctedBlockedNumber.length == 10)) {  
	
		addBlockedNumberRequest = createRequest();
    
		if (addBlockedNumber == null) {
			alert("Unable to create request");
			return;
		}
    
		var UserID = document.getElementById("AdministratorID").value;	
		var url= "../BlockCallSubmit.php?PhoneNumber=" + encodeURIComponent(correctedBlockedNumber) + "&VolunteerID=" + encodeURIComponent(UserID) + "&Type=Admin&Message=" + encodeURIComponent(blockedReason) + "&InternetNumber=" + encodeURIComponent(InternetNumber);

		addBlockedNumberRequest.open("GET", url, true);
		addBlockedNumberRequest.setRequestHeader("Cache-Control" , "no-cache, must-revalidate");
		addBlockedNumberRequest.onreadystatechange = function() {addBlockedNumberResponse(blockedNumber, InternetNumber)};
		addBlockedNumberRequest.send(null);
		
	} else {
		alert('Please input numeric characters only, and enter only a complete phone number or an area code and exchange.');  
		document.addBlockedNumberForm.addBlockedNumberData.focus();  
		return;
	}
}
	
	
function addBlockedNumberResponse(blockedNumber, InternetNumber) {
	if (addBlockedNumberRequest.readyState == 4) {
		if (addBlockedNumberRequest.status == 200) { 
			var responseDoc = addBlockedNumberRequest.responseText;
			var alertMessage = 'The VCC will now permanently block calls from: ' + blockedNumber;
			if(InternetNumber == 1) {
				alertMessage += ' and will play the \"No Internet Calls\" recording.';
			} else {
				alertMessage += ' and will NOT play any message.';
			}
			if (responseDoc == "OK") {
				blockListUpdate('Admin');
				var blockedNumberElement = document.getElementById("addBlockedNumberData");
				blockedNumberElement.value = "";  
				alert(alertMessage);  
				blockListUpdate("Admin");
			} else { 
				alert(responseDoc);
			}
		} 
	}  
}




function callHistoryListUpdate(SortOrder) {

	callHistorySort = SortOrder;

	callHistoryListRequest = createRequest();

    if (callHistoryListRequest == null) {
        alert("Unable to create request");
        return;
    }

	var url= "callHistoryList.php?SortOrder=" + SortOrder;

    callHistoryListRequest.open("GET", url, true);
    callHistoryListRequest.setRequestHeader("Cache-Control" , "no-cache, must-revalidate");
    callHistoryListRequest.onreadystatechange = displayCallHistoryList;
    callHistoryListRequest.send(null);
}

/**
 * Show Twilio call details in a modal
 * Fetches status events and errors for a specific CallSid
 */
function showTwilioCallDetails(callSid) {
    var request = createRequest();
    if (request == null) {
        modal.error('Error', 'Unable to create request');
        return;
    }

    var url = "getTwilioCallDetails.php?CallSid=" + encodeURIComponent(callSid);
    request.open("GET", url, true);
    request.setRequestHeader("Cache-Control", "no-cache, must-revalidate");
    request.onreadystatechange = function() {
        if (request.readyState == 4) {
            if (request.status == 200) {
                try {
                    var data = JSON.parse(request.responseText);
                    displayTwilioDetailsModal(data);
                } catch (e) {
                    modal.error('Error', 'Failed to parse response: ' + e.message);
                }
            } else {
                modal.error('Error', 'Failed to fetch Twilio details: HTTP ' + request.status);
            }
        }
    };
    request.send(null);
}

/**
 * Display Twilio details in the modal with formatted HTML
 */
function displayTwilioDetailsModal(data) {
    var html = '<div style="max-height: 400px; overflow-y: auto; font-size: 12px;">';

    // Call SID header
    html += '<div style="margin-bottom: 10px; padding: 5px; background: #f0f0f0; border-radius: 4px;">';
    html += '<strong>Call SID:</strong> <code style="font-size: 11px;">' + escapeHtml(data.callSid) + '</code>';
    html += '</div>';

    // Errors section (show first if any - these are real errors, not carrier warnings)
    if (data.errors && data.errors.length > 0) {
        html += '<div style="margin-bottom: 15px;">';
        html += '<h4 style="color: red; margin: 0 0 8px 0; border-bottom: 1px solid red;">Errors (' + data.errors.length + ')</h4>';
        html += '<table style="width: 100%; border-collapse: collapse; font-size: 11px;">';
        html += '<tr style="background: #ffeeee;"><th style="text-align: left; padding: 4px; border: 1px solid #ddd;">Time</th>';
        html += '<th style="text-align: left; padding: 4px; border: 1px solid #ddd;">Level</th>';
        html += '<th style="text-align: left; padding: 4px; border: 1px solid #ddd;">Code</th>';
        html += '<th style="text-align: left; padding: 4px; border: 1px solid #ddd;">Message</th></tr>';

        data.errors.forEach(function(err) {
            // Extract error message - may be nested in more_info object
            var errorMsg = '-';
            if (err.errorMessage) {
                if (typeof err.errorMessage === 'object') {
                    // Handle nested more_info structure
                    errorMsg = err.errorMessage.parserMessage || err.errorMessage.Msg || err.errorMessage.message || JSON.stringify(err.errorMessage);
                } else {
                    errorMsg = err.errorMessage;
                }
            }
            html += '<tr>';
            html += '<td style="padding: 4px; border: 1px solid #ddd; white-space: nowrap;">' + formatTime(err.timestamp) + '</td>';
            html += '<td style="padding: 4px; border: 1px solid #ddd; color: ' + (err.level === 'Error' ? 'red' : 'orange') + ';">' + escapeHtml(err.level || '-') + '</td>';
            html += '<td style="padding: 4px; border: 1px solid #ddd;">' + escapeHtml(err.errorCode || '-') + '</td>';
            html += '<td style="padding: 4px; border: 1px solid #ddd;">' + escapeHtml(errorMsg) + '</td>';
            html += '</tr>';
        });
        html += '</table></div>';
    }

    // Carrier Warnings section (SHAKEN/STIR issues - not your fault)
    if (data.warnings && data.warnings.length > 0) {
        html += '<div style="margin-bottom: 15px;">';
        html += '<h4 style="color: #996600; margin: 0 0 8px 0; border-bottom: 1px solid #996600;">Carrier Warnings (' + data.warnings.length + ')</h4>';
        html += '<p style="font-size: 11px; color: #666; margin: 0 0 8px 0;">These are caller\'s carrier issues (SHAKEN/STIR verification), not errors in your system.</p>';
        html += '<table style="width: 100%; border-collapse: collapse; font-size: 11px;">';
        html += '<tr style="background: #fff8e6;"><th style="text-align: left; padding: 4px; border: 1px solid #ddd;">Time</th>';
        html += '<th style="text-align: left; padding: 4px; border: 1px solid #ddd;">Code</th>';
        html += '<th style="text-align: left; padding: 4px; border: 1px solid #ddd;">Message</th></tr>';

        data.warnings.forEach(function(warn) {
            var warnMsg = '-';
            if (warn.errorMessage) {
                if (typeof warn.errorMessage === 'object') {
                    warnMsg = warn.errorMessage.parserMessage || warn.errorMessage.Msg || warn.errorMessage.message || JSON.stringify(warn.errorMessage);
                } else {
                    warnMsg = warn.errorMessage;
                }
            }
            html += '<tr>';
            html += '<td style="padding: 4px; border: 1px solid #ddd; white-space: nowrap;">' + formatTime(warn.timestamp) + '</td>';
            html += '<td style="padding: 4px; border: 1px solid #ddd;">' + escapeHtml(warn.errorCode || '-') + '</td>';
            html += '<td style="padding: 4px; border: 1px solid #ddd;">' + escapeHtml(warnMsg) + '</td>';
            html += '</tr>';
        });
        html += '</table></div>';
    }

    // Status events section
    html += '<div>';
    html += '<h4 style="color: #333; margin: 0 0 8px 0; border-bottom: 1px solid #333;">Status Events (' + (data.statusEvents ? data.statusEvents.length : 0) + ')</h4>';

    if (data.statusEvents && data.statusEvents.length > 0) {
        html += '<table style="width: 100%; border-collapse: collapse; font-size: 11px;">';
        html += '<tr style="background: #f5f5f5;"><th style="text-align: left; padding: 4px; border: 1px solid #ddd;">Time</th>';
        html += '<th style="text-align: left; padding: 4px; border: 1px solid #ddd;">Status</th>';
        html += '<th style="text-align: left; padding: 4px; border: 1px solid #ddd;">Event</th>';
        html += '<th style="text-align: left; padding: 4px; border: 1px solid #ddd;">Conference</th>';
        html += '<th style="text-align: left; padding: 4px; border: 1px solid #ddd;">Muted</th></tr>';

        data.statusEvents.forEach(function(evt) {
            var statusColor = getStatusColor(evt.status);
            html += '<tr>';
            html += '<td style="padding: 4px; border: 1px solid #ddd; white-space: nowrap;">' + formatTime(evt.timestamp) + '</td>';
            html += '<td style="padding: 4px; border: 1px solid #ddd; color: ' + statusColor + ';">' + escapeHtml(evt.status || '-') + '</td>';
            html += '<td style="padding: 4px; border: 1px solid #ddd;">' + escapeHtml(evt.event || '-') + '</td>';
            html += '<td style="padding: 4px; border: 1px solid #ddd;">' + escapeHtml(evt.friendlyName || '-') + '</td>';
            html += '<td style="padding: 4px; border: 1px solid #ddd;">' + (evt.muted === 1 ? 'Yes' : evt.muted === 0 ? 'No' : '-') + '</td>';
            html += '</tr>';
        });
        html += '</table>';
    } else {
        html += '<p style="color: #666; font-style: italic;">No status events recorded for this call.</p>';
    }
    html += '</div>';

    // Full Details button
    html += '<div style="margin-top: 15px; text-align: center;">';
    html += '<button class="full-details-btn" onclick="showFullCallDetails(\'' + escapeHtml(data.callSid) + '\'); modal.hide();">View Full Details</button>';
    html += '</div>';

    html += '</div>';

    // Show in modal using innerHTML for formatted content
    // Only show error styling for real errors, not carrier warnings
    var modalTitle = 'Twilio Call Details';
    var modalType = 'info';
    var modalIcon = 'i';

    if (data.hasErrors) {
        modalTitle = 'Twilio Call Details (HAS ERRORS)';
        modalType = 'error';
        modalIcon = '!';
    } else if (data.hasWarnings) {
        modalTitle = 'Twilio Call Details (carrier warnings)';
        modalType = 'warning';
        modalIcon = '⚠';
    }

    // Use the modal but set innerHTML for the body
    modal.modalTitle.textContent = modalTitle;
    modal.modalBody.innerHTML = html;
    modal.modalHeader.classList.remove('success', 'error', 'warning', 'info');
    modal.modalHeader.classList.add(modalType);
    modal.modalIcon.textContent = modalIcon;
    modal.modal.classList.add('show');
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Helper function to format timestamp
function formatTime(timestamp) {
    if (!timestamp) return '-';
    // Extract just the time portion if it's a full datetime
    var parts = timestamp.split(' ');
    return parts.length > 1 ? parts[1] : timestamp;
}

// Helper function to get color for call status
function getStatusColor(status) {
    var colors = {
        'queued': '#888',
        'ringing': '#0066cc',
        'in-progress': '#009900',
        'completed': '#333',
        'busy': '#cc6600',
        'failed': '#cc0000',
        'no-answer': '#cc6600',
        'canceled': '#888',
        'initiated': '#0066cc'
    };
    return colors[status] || '#333';
}



function displayCallHistoryList() {

	if (callHistoryListRequest.readyState == 4) {

		if (callHistoryListRequest.status == 200) { 
			var results = callHistoryListRequest.response;
			var calls = JSON.parse(results);
		    var TableDiv = document.getElementById("callHistoryScrollList");  


			// Remove Existing List
			for (var j=TableDiv.childNodes.length; j>0; j--) {
				TableDiv.removeChild(TableDiv.childNodes[j-1]);     
			}
			
			
			// Create New List


			callTable = document.createElement("table");
			

			for (key in calls) {
				var call = calls[key];		
				const tableRecord = createTableItem(call);
								
				callTable.appendChild(tableRecord);
				TableDiv.appendChild(callTable);
			}		
		}
	}
}




function createTableItem(resource, Type) {
	var tableRow = document.createElement("tr");

	for (var key in resource) {	
		var tableItem = document.createElement("td");
		tableItem.setAttribute("class",key + " bottomBorders");
		switch(key) {
			case 'location':
				var locationString = resource[key].split(", ");
				locationString[0] = toTitleCase(locationString[0]);
				resource[key] = locationString[0] + ", " + locationString[1];
				break;			

			case 'callerID':
			case 'blockedID':
				var CallerID = resource[key];
				tableItem.onclick = function() {phoneNumberPopup(this.firstChild.nodeValue);}
				tableItem.title = "Click to see all calls by this number";
				break;		
		
			case 'message':
				var blockMessage = resource[key];
				break;
		
			case 'internetNumber':
			case 'admin':
				var checkBoxItem = document.createElement('input');
				checkBoxItem.type = 'checkbox';
				checkBoxItem.name='InternetNumber';
				checkBoxItem.title='Check to Play \"No Internet Calls\" Recording';
				if(resource[key] == 0) {
					 checkBoxItem.checked = false;
				} else {
					checkBoxItem.checked = true;
				}
			
				checkBoxItem.onchange = function() {
					if(checkBoxItem.checked == false) {
						var checkStatus = 0;
					} else {
						var checkStatus = 1;
					}
					addBlockedNumber(CallerID, blockMessage, checkStatus)
				} 
				tableRow.setAttribute("title",blockMessage);			
				tableItem.appendChild(checkBoxItem);
				tableRow.appendChild(tableItem);			//Add to row here to avoid value being added later, excluded from generalized add function for this reason
				break;
			
			
			case 'user':
				if(Type == "User") {			//Add to row here to avoid adding to Admin tables in general add to row section
					tableItem.appendChild(document.createTextNode(resource[key]));
					tableRow.appendChild(tableItem);
				}
				break;
							
			case 'category':
				// Store callSid for click handler
				var callSid = resource['callSid'];
				var hasErrors = resource['hasErrors'];
				var hasWarnings = resource['hasWarnings'];

				switch(resource[key]) {
					case 'Busy':
						tableItem.title = "No Volunteers Available to Accept Call - Click for Twilio details";
						break;
					case 'H/U':
						tableItem.title = "Caller Hung Up on Volunteer - Click for Twilio details";
						break;
					case 'CONV' :
						tableItem.title = "Conversation - Click for Twilio details";
						break;
					case 'BLK-C' :
						tableItem.title = "Call Blocked By CallerID - Click for Twilio details";
						break;
					case 'BLK-A' :
						tableItem.title = "Call Blocked By Administrator - Click for Twilio details";
						break;
					case 'BLK-U' :
						tableItem.title = "Call Blocked By User - Click for Twilio details";
						break;
					case 'CLSD' :
						tableItem.title = "Call During Closed Hours - Click for Twilio details";
						break;
					case 'N/A' :
						tableItem.title = "Volunteers Available But Did Not Answer Call - Click for Twilio details";
						break;
					case 'RING' :
						tableItem.title = "Call is Ringing with Volunteer - Click for Twilio details";
						break;
					case 'H/U-R':
						tableItem.title = "Caller Hung While Phone Was Ringing - Click for Twilio details";
						break;
					case 'SKYPE':
						tableItem.title = "Blocked Internet Call (Skype/Google Voice) - Click for Twilio details";
						break;
					case 'In Progress':
						tableRow.style.background = "white";
						tableItem.title = "Click for Twilio details";
						break;
					default:
						tableItem.title = "Click for Twilio details";
						break;
				}

				// Add volunteer name to tooltip if call was answered
				if (resource['volunteerName']) {
					tableItem.title = resource['volunteerName'] + '\n' + tableItem.title;
				}

				// Show in red if there are actual errors (not just carrier warnings)
				if (hasErrors) {
					tableItem.style.color = 'red';
					tableItem.style.fontWeight = 'bold';
					tableItem.title += ' (HAS ERRORS)';
				} else if (hasWarnings) {
					// Carrier warnings only - show subtle indicator
					tableItem.style.color = '#996600';
					tableItem.title += ' (carrier warnings)';
				}

				// Make clickable to show full call details directly
				if (callSid) {
					tableItem.style.cursor = 'pointer';
					tableItem.onclick = (function(sid) {
						return function() { showFullCallDetails(sid); };
					})(callSid);
				}
				break;			
			
			case 'gender':
				if(resource[key]) {
					tableRow.title = "Gender: "+ tableRow.title + decodeURIComponent(resource[key]) + ", ";
				}
				break;
			case 'age':
				if(resource[key]) {
					tableRow.title = "Age: " + tableRow.title + decodeURIComponent(resource[key]) + "\n";
				}
				break;
			case 'callLogNotes':
				if(resource[key]) {
					try {
						tableRow.title = "\nNotes: "+ tableRow.title + decodeURIComponent(resource[key]);
					} 
					catch(err) {
						tableRow.title = tableRow.title;						
					}
				}									
				break;

			default:
				break;
		}			//Create Table Item and Title information 

		switch(key) {				// GENERAL ADD TO ROW SECTION -  List of Items that will add to table rather than title items
			case 'callerID':
			case 'location':
			case 'callerID':
			case 'blockedID':
			case 'date':
			case 'time':
			case 'hotline':
			case 'length':
				tableItem.appendChild(document.createTextNode(resource[key]));
				tableRow.appendChild(tableItem);
				break;
			case 'category':
				tableItem.appendChild(document.createTextNode(resource[key]));
				tableRow.appendChild(tableItem);
				break;
		}



	}			
			

	if (Type == 'Admin' || Type == 'User') {
		var tableItem = document.createElement("td");
		var button = document.createElement("input");
		button.setAttribute("type", "button");
		button.setAttribute("value", "Delete");
		button.onclick = function() {deleteBlockedNumber(CallerID);}
		tableItem.appendChild(button);

		tableRow.appendChild(tableItem);
		tableRow.setAttribute("title",blockMessage);

	}

	if (Type == 'User') {
		var tableItem = document.createElement("td");
		var button = document.createElement("input");
		button.setAttribute("type", "button");
		button.setAttribute("value", "Block");
		button.onclick = function() {addBlockedNumber(CallerID, blockMessage, 0);}
		tableItem.appendChild(button);

		tableRow.appendChild(tableItem);
	}
	return tableRow;
}


function toTitleCase(str) {
    return str.replace(/\w\S*/g, function(txt){return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();});
}



function deleteBlockedNumber(CallerID) {

	deleteBlockedNumberRequest = createRequest();
    
    if (deleteBlockedNumberRequest == null) {
        alert("Unable to create request");
        return;
    }
    CallerID = encodeURIComponent(CallerID);
    var url= "deleteBlockedNumber.php?PhoneNumber=" + CallerID;

    deleteBlockedNumberRequest.open("GET", url, true);
    deleteBlockedNumberRequest.setRequestHeader("Cache-Control" , "no-cache, must-revalidate");
    deleteBlockedNumberRequest.onreadystatechange = function() {deleteBlockedNumberResults(CallerID)};
    deleteBlockedNumberRequest.send(null);

}


function deleteBlockedNumberResults(CallerID) {
	if (deleteBlockedNumberRequest.readyState == 4) {
		if (deleteBlockedNumberRequest.status == 200) { 
			var responseDoc = deleteBlockedNumberRequest.responseText;
			if (responseDoc == "OK") {
				blockListUpdate('Admin');
				alert('The VCC will no longer block calls from: ' + CallerID);  
			} else {
				alert(responseDoc);
			}
		}
	}
}




// Window Dragging Events

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
