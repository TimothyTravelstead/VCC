const addressLocation = window.location.host;


// Minimum supported versions for each browser
const BROWSER_REQUIREMENTS = {
    Chrome: 74,
    Safari: 14,
    Firefox: 67
};


const Browsers = {
    browser: {
        name: '',
        version: {
            original: ''
        }
    },
    os: {
        name: '',
        version: {
            original: ''
        }
    },
    
    detect: function() {
        const userAgent = navigator.userAgent;
        
        // Browser Name and Version Detection
        if (userAgent.indexOf("Firefox") !== -1) {
            this.browser.name = "Firefox";
            this.browser.version.original = userAgent.match(/Firefox\/([0-9._]+)/)[1];
        }
        else if (userAgent.indexOf("Chrome") !== -1 && userAgent.indexOf("Edg") === -1) {
            this.browser.name = "Chrome";
            this.browser.version.original = userAgent.match(/Chrome\/([0-9._]+)/)[1];
        }
        else if (userAgent.indexOf("Safari") !== -1 && userAgent.indexOf("Chrome") === -1) {
            this.browser.name = "Safari";
            this.browser.version.original = userAgent.match(/Version\/([0-9._]+)/)[1];
        }
        else if (userAgent.indexOf("Edg") !== -1) {
            this.browser.name = "Edge";
            this.browser.version.original = userAgent.match(/Edg\/([0-9._]+)/)[1];
        }
        else if (userAgent.indexOf("Opera") !== -1 || userAgent.indexOf("OPR") !== -1) {
            this.browser.name = "Opera";
            this.browser.version.original = userAgent.indexOf("Opera") !== -1 
                ? userAgent.match(/Opera\/([0-9._]+)/)[1]
                : userAgent.match(/OPR\/([0-9._]+)/)[1];
        }

        // OS Name and Version Detection
        if (userAgent.indexOf("Windows") !== -1) {
            this.os.name = "Windows";
            const windowsMatch = userAgent.match(/Windows NT ([0-9._]+)/);
            this.os.version.original = windowsMatch ? windowsMatch[1] : "";
        }
        else if (userAgent.indexOf("Mac") !== -1) {
            this.os.name = "Mac OS X";
            const macMatch = userAgent.match(/Mac OS X ([0-9._]+)/);
            this.os.version.original = macMatch 
                ? macMatch[1].replace(/_/g, '.') 
                : "";
        }
        else if (userAgent.indexOf("Linux") !== -1) {
            this.os.name = "Linux";
            const linuxMatch = userAgent.match(/Linux ([0-9._]+)/);
            this.os.version.original = linuxMatch ? linuxMatch[1] : "";
        }
        else if (userAgent.indexOf("Android") !== -1) {
            this.os.name = "Android";
            const androidMatch = userAgent.match(/Android ([0-9._]+)/);
            this.os.version.original = androidMatch ? androidMatch[1] : "";
        }
        else if (/iPhone|iPad|iPod/.test(userAgent)) {
            this.os.name = "iOS";
            const iosMatch = userAgent.match(/OS ([0-9._]+)/);
            this.os.version.original = iosMatch 
                ? iosMatch[1].replace(/_/g, '.') 
                : "";
        }

        return this;
    }
};


// Initialize the page when DOM is loaded
window.addEventListener('load', () => {
    initializeUI();
    checkBrowserCompatibility();
});


function initializeUI() {
    // Set up calendar button
    const submitButton = document.getElementById("submitButton");
    const calendarButton = document.getElementById("calendarButton");
    calendarButton.addEventListener('click', () => {
        document.getElementById("Calendar").value = 'true';
        submitButton.click();
    });

    // Clear JavaScript warning
    document.getElementById("javascriptWarning").innerHTML = "";

    // Initialize username field
    const username = document.getElementById('user');
    username.value = "";
    username.focus();

    // Initialize password field
    const password = document.getElementById('pass');
    password.value = "";

    // Set up username change handler
    username.addEventListener('change', () => {
        lookup(username.value);
        document.getElementById("pass").focus();
    });
}


function checkBrowserCompatibility() {
    Browsers.detect();
    const { 
        browser: { name: browserName, version: { original: browserVersion } },
        os: { name: osName, version: { original: osVersion } }
    } = Browsers;
    
    const browserDetail = navigator.userAgent;
    const mainVersion = parseInt(browserVersion.split(".")[0], 10);
	  const message = document.getElementById("LogonMessage");

    if (BROWSER_REQUIREMENTS[browserName]) {
        // Check if browser is supported and version meets minimum requirement
        if (mainVersion >= BROWSER_REQUIREMENTS[browserName]) {
            message.innerHTML = "";
        } else {
            message.innerHTML = `Please update your version of ${browserName}. <br>Some functions may not work with your version, ${browserVersion}.`;
        }
    } else {
        // Unsupported browser
        message.innerHTML = `You are using ${browserName} Version ${browserVersion}. 
            <br>The Volunteer Communication System works best with updated versions of Chrome, Safari, or Firefox.
            <br>Some functions may not work properly with your browser`;
    }
}


Date.prototype.formattedTime = function() {
	var hours = this.getHours();
	var minutes = this.getMinutes();
	if(minutes < 10) {
		var formattedMinutes = "0" + minutes;
	} else {
		var formattedMinutes = minutes;
	}
	
	if(hours == 0) {
		hours = 12;
		appendix = "am";
	} else if (hours >= 12) {
		var appendix = "pm";
		if(hours > 12) {
			hours -= 12;
		}
	} else {
		var appendix = "am";
	}

	var formattedTime = hours + ":" + formattedMinutes + appendix;
	
	return formattedTime;
}


function trainees() {
    new AjaxRequest(
        "traineeLookup.php",  // url
        null,                 // params (null for this GET request)
        traineeList,          // callback function
        null
    );
}


function prePasswordCheck() {
    const password = document.getElementById('pass').value;
    const username = document.getElementById('user').value;
    
    // Modern authentication: send plaintext password securely
    // Server will handle hashing logic based on stored hash type
    // CSRF token is automatically added by the AjaxRequest override
    new AjaxRequest(
        `https://${addressLocation}/loginverify2.php`,
        {
            password: password, // Send plaintext password
            Calendar: 'check',
            UserID: username
        },
        prePasswordCheckResults,
        null,
        "POST"
    );
}


function prePasswordCheckResults(response) {
    if (response === "OK") {
        calendarCheck();
    } else {
        const message = document.getElementById("LogonMessage");
        message.innerHTML = "WRONG USER ID OR PASSWORD";
    }
}


function calendarCheck() {
    const username = document.getElementById('user').value;
    const type = 'user';

    new AjaxRequest(
        `https://${addressLocation}/Calendar/getFutureVolunteerSchedule.php`,
        {
            UserID: username,
            type: type
        },
        calendarResult,
        null,
        "GET"
    );
}


function getBlockTime(block) {
	var blockStartMinutes = block * 30;
	var blockEndMinutes = blockStartMinutes + 30;
	var startTime = new Date();
	var endTime = new Date();
	var serverOffsetMinutes = document.getElementById("serverOffsetMinutes").value;
	var offset = serverOffsetMinutes - startTime.getTimezoneOffset();
	blockStartMinutes += offset;
	blockEndMinutes += offset;
	startTime.setHours(0,blockStartMinutes,0,0);
	endTime.setHours(0,blockEndMinutes,0,0);
	var formattedStartTime = startTime.formattedTime();
	var formattedEndTime = endTime.formattedTime();
	return {"startTime": formattedStartTime , "endTime": formattedEndTime, 'block': block};		
}


function objectSize(obj) {
	var size = 0, key;
	for (key in obj) {
		if (obj.hasOwnProperty(key)) size++;
	}
	return size;
}


function calendarResult(responseText) {
    try {
        const response = JSON.parse(responseText);

        if (response === "Yes") {
            login();
            return;
        }

        // Skip end-of-shift selection for resource editors (they're not answering phones)
        const editResourcesField = document.getElementById("editResources");
        if (editResourcesField && editResourcesField.value === "true") {
            login();
            return;
        }

        const { Schedule: schedule, CurrentBlock: currentBlock } = response;
        const currentVolunteers = objectSize(schedule[currentBlock]);

        if (currentVolunteers > 10) {
            alert("No Space at the inn. Too many volunteers are already signed in. Please check later.");
            return;
        }

        // Set up end of shift selection
        const endOfShiftBox = document.getElementById("endOfShiftBox");
        const logonBox = document.getElementById("Logon");
        const endOfShiftMenu = document.getElementById("endOfShiftMenu");
        
        // Clear existing options
        endOfShiftMenu.innerHTML = '';
        
        // Show the box
		logonBox.style.visibility = "hidden";
        endOfShiftBox.style.display = "block";

        // Add options for available time blocks
        for (const key in schedule) {
            const volunteersInBlock = objectSize(schedule[key]);
            
            if (volunteersInBlock >= 4) {
                break;
            }

            const Time = getBlockTime(key);
            const option = document.createElement("option");
            option.value = Time.block;
            option.textContent = Time.endTime;
            endOfShiftMenu.appendChild(option);
        }

        // Add change handler
        endOfShiftMenu.onchange = () => {
            endOfShiftBox.style.visibility = "hidden";
            login();
        };

    } catch (e) {
        console.error("Error processing calendar response:", e);
        alert("Error processing calendar data");
    }
}

  
function lookup(username) {
    new AjaxRequest(
        "defaultsLookup.php",         // base url
        { UserID: username },         // params (will be properly encoded)
        defaults,                     // callback function
        null
    );
}


function SubmitButton() {
    input = document.getElementById("submitButton");
    input.onclick = validate;
}


function logonTypeSelected() {
    const logonType = document.getElementById("logonType");
    const traineeList = document.getElementById("trainees");
    
    // Clean up existing elements
    const cleanUp = removeElements(traineeList);
    
    if (logonType.selectedIndex) {
        const selectedItem = logonType.options[logonType.selectedIndex].value;
        
        // Check if Trainer is selected (value == 4)
        if (selectedItem == 4) {
            // Expand the logon box before loading trainees
            adjustLogonBoxHeight(true);
            // Call trainees function to load trainee lists
            trainees();
        } else {
            // Reset logon box height for non-trainer selections
            adjustLogonBoxHeight(false);
            // Clear any existing trainee lists
            if (traineeList) {
                while (traineeList.firstChild) {
                    traineeList.removeChild(traineeList.firstChild);
                }
            }
        }
    } else {
        // No selection - reset to default height
        adjustLogonBoxHeight(false);
    }
}

const NORMAL_HEIGHT = 185;  // Original height
const EXPANDED_HEIGHT = 330;  // Height when trainee lists are shown

function adjustLogonBoxHeight(expand = true) {
    const logonBox = document.getElementById('Logon');
    if (!logonBox) return;
    
    // Animate the height change
    logonBox.style.transition = 'height 0.3s ease-in-out';
    logonBox.style.height = (expand ? EXPANDED_HEIGHT : NORMAL_HEIGHT) + 'px';
}

// Add this to your existing logon type change handler
function handleLogonTypeChange(selectElement) {
    const selectedValue = selectElement.value;
    const traineeContainer = document.getElementById('trainees');
    
    if (selectedValue === 'Trainer') {
        adjustLogonBoxHeight(true);
        // Your existing code to show trainee lists
        // fetchTrainees(); or whatever function you use
    } else {
        adjustLogonBoxHeight(false);
        if (traineeContainer) {
            traineeContainer.innerHTML = '';  // Clear trainee lists
        }
    }
}


function traineeList(responseText) {
    if (responseText === "No Data") {
        alert("No Trainees in the Database.");
        return;
    }
    
    try {
        const trainees = JSON.parse(responseText);
        if (!trainees || trainees.length === 0) {
            alert("No Trainees in the Database.");
            return;
        }
        
        // Create labels and select elements
        const label1 = document.createElement("label");
        label1.htmlFor = "traineeList1";
        label1.appendChild(document.createTextNode("First Trainee: "));
        
        const label2 = document.createElement("label");
        label2.htmlFor = "traineeList2";
        label2.appendChild(document.createTextNode("Second Trainee: "));
        
        const select1 = document.createElement("select");
        select1.id = "traineeList1";
        select1.name = "trainee1";
        
        const select2 = document.createElement("select");
        select2.id = "traineeList2";
        select2.name = "trainee2";
        
        // Function to populate a select element
        function populateSelect(select) {
            // Add a default option
            const defaultOption = document.createElement("option");
            defaultOption.value = "";
            defaultOption.appendChild(document.createTextNode("-- Select trainee --"));
            select.appendChild(defaultOption);
            
            // Add all trainees
            trainees.forEach(trainee => {
                const option = document.createElement("option");
                option.value = trainee.userName;
                option.appendChild(document.createTextNode(`${trainee.firstName} ${trainee.lastName}`));
                select.appendChild(option);
            });
        }
        
        // Populate both select elements
        populateSelect(select1);
        populateSelect(select2);
        
        // Get the trainee container and clear it
        const traineeContainer = document.getElementById("trainees");
        traineeContainer.innerHTML = '';
        
        // Add elements directly to the container
        traineeContainer.appendChild(label1);
        traineeContainer.appendChild(select1);
        traineeContainer.appendChild(document.createElement("br"));
        traineeContainer.appendChild(document.createElement("br"));
        traineeContainer.appendChild(label2);
        traineeContainer.appendChild(select2);
        
        // Add event listeners to prevent selecting the same trainee in both lists
        select1.addEventListener('change', function() {
            Array.from(select2.options).forEach(option => {
                option.disabled = option.value === this.value && option.value !== "";
            });
        });
        
        select2.addEventListener('change', function() {
            Array.from(select1.options).forEach(option => {
                option.disabled = option.value === this.value && option.value !== "";
            });
        });
        
    } catch (e) {
        console.error("Error processing trainee data:", e);
        alert("Error processing trainee data");
    }
}


function defaults(responseText) {
    try {
        const userDefaults = JSON.parse(responseText);
        
        // Validate user match
        const userField = document.getElementById("user");
        if (userDefaults.UserName && userField.value !== userDefaults.UserName) {
            return;
        }
        if (userDefaults.UserName) {
            userField.value = userDefaults.UserName;
        }

        // Clear and rebuild logon type menu
        const logonTypeMenu = document.getElementById('logonTypeMenu');
        logonTypeMenu.innerHTML = '';
        
        // Create select element
        const select = document.createElement("select");
        select.id = "logonType";
        select.onchange = logonTypeSelected;

        let loggedOnValue = null;
        let resourceEditAvailable = false;
        let defaultCount = 0;

        // Process user defaults
        const typeMapping = {
            Volunteer: { value: 1, text: "Volunteer" },
            ResourceOnly: { value: 2, text: "Resources Only", enableResourceEdit: true },
            AdminUser: { value: 3, text: "Admin", enableResourceEdit: true },
            Trainer: { value: 4, text: "Trainer" },
            Monitor: { value: 5, text: "Monitor" },
            Trainee: { value: 6, text: "Trainee" },
            AdminResources: { value: 7, text: "Admin Mini", enableResourceEdit: true },
            groupChatMonitor: { value: 8, text: "Group Chat Monitor" },
            ResourceAdmin: { value: 9, text: "Resource Mini" }
        };

        // Build options
        for (const [type, value] of Object.entries(userDefaults)) {
            if (type !== "UserName" && type !== "CallerType" && value === 1) {
                const typeConfig = typeMapping[type];
                if (typeConfig) {
                    loggedOnValue = typeConfig.value;
                    if (typeConfig.enableResourceEdit) {
                        resourceEditAvailable = true;
                    }
                    
                    const option = document.createElement("option");
                    option.value = typeConfig.value;
                    option.textContent = typeConfig.text;
                    select.appendChild(option);
                }
            }
            if (type !== "UserName" && type !== "CallerType") {
                defaultCount += Number(value);
            }
        }

        // Handle resource edit button
        if (resourceEditAvailable) {
            const button = document.createElement("input");
            button.type = "button";
            button.value = "Update Resources";
            button.id = "editResourcesButton";
            button.onclick = () => {
                const ID = document.getElementById('user').value;
                document.getElementById('UserID').value = ID;
                document.getElementById("editResources").value = 'true';
                document.getElementById('submitButton').click();
            };
            document.getElementById("LogonButtons").appendChild(button);
        }

        // Handle caller type visibility - Always keep checkbox visible so user can change preference
        const chatBox = document.getElementById('ChatOnly');
        const CallerTypeLabel = document.getElementById('CallerTypeLabel');

        // Always keep the checkbox visible regardless of CallerType
        chatBox.style.visibility = "visible";

        // Set appropriate label based on CallerType
        if (userDefaults.CallerType === 0) {
            CallerTypeLabel.innerHTML = "Chat Only: ";
        } else if (userDefaults.CallerType === 1) {
            CallerTypeLabel.innerHTML = "Chat Only: ";
        } else if (userDefaults.CallerType === 2) {
            CallerTypeLabel.innerHTML = "Chat Only: ";
        } else {
            CallerTypeLabel.innerHTML = "Chat Only: ";
        }

        // Finalize menu setup
        if (defaultCount > 0) {
            logonTypeMenu.appendChild(select);
        } else {
            if (loggedOnValue === 4) {
                logonTypeSelected();
            }
            document.getElementById("Admin").value = loggedOnValue;
        }
        
        SubmitButton();
        document.getElementById("pass").focus();

    } catch (e) {
        console.error("Error processing user defaults:", e);
        alert("Error processing user defaults");
    }
}
        
	
function validate() {
	var calendarField = document.getElementById("Calendar");

	if(calendarField.value == 'true') {
		var changingCalendar = true;
	} else {
		var changingCalendar = false;
	}

	var logonType = document.getElementById("logonType");
	if(!logonType) {
		var message = document.getElementById("LogonMessage");
		message.innerHTML = "";
		message.appendChild(document.createTextNode("WRONG USER ID OR PASSWORD"));
		return;
	}	
	var logonValue = logonType.options[logonType.selectedIndex].value;
	var checkCalendar = true;		
	
	for (var i=0; i<logonType.length; i++){
		if(logonType.options[i].value == 3) {
			checkCalendar = false;
		}
	}
	
	if(logonValue == 1 && checkCalendar && !changingCalendar) {				
		var onCalendar = prePasswordCheck();
	} else {		
		login();
	}
}


function adminLogin() {
	var Admin = document.getElementById('Admin');
	Admin.value = 1;
	login();
}


function ResourcesLogin() {
	var Admin = document.getElementById('Admin');
	Admin.value = 2;
	login();
}


function login() {
	// Modern authentication: send plaintext password securely over HTTPS
	// Server will handle all hashing logic based on stored hash type
	var password = document.getElementById('pass').value;
	var passwordField = document.getElementById('hash');
	passwordField.value = password; // Send plaintext password
	
	var user = document.getElementById('UserID');
	var ID = document.getElementById('user').value;
	user.value = ID;

	var ChatOnlyFlag = document.getElementById('ChatOnlyFlag');
	
	if (document.getElementById('ChatOnly').checked) {			
		ChatOnlyFlag.value = 1;
	} else {
		ChatOnlyFlag.value = 0;
	}

	logonType = document.getElementById("logonType");
	if(logonType) {
		var selectedItem = logonType.options[logonType.selectedIndex].value;
	   var Admin = document.getElementById('Admin');
	   Admin.value = selectedItem;

	}

  // Get both trainee select elements
  var traineeList1 = document.getElementById('traineeList1');
  var traineeList2 = document.getElementById('traineeList2');
  
  // Initialize trainee value variable
  var traineeValues = [];
  
  // Check and get first trainee if selected
  if(traineeList1 && traineeList1.selectedIndex > 0) {  // > 0 to skip the "Select trainee" option
      traineeValues.push(traineeList1.options[traineeList1.selectedIndex].value);
  }
  
  // Check and get second trainee if selected
  if(traineeList2 && traineeList2.selectedIndex > 0) {
      traineeValues.push(traineeList2.options[traineeList2.selectedIndex].value);
  }
  
  // Set the combined trainee value to the hidden input
  var traineeInput = document.getElementById('Trainee');
  if(traineeInput) {
      traineeInput.value = traineeValues.join(',');  // Combine with comma separator
  }

	var f = document.getElementById('finalform');
	f.submit();
}
