schedule = {};
currentYear = {};
nextYear = {};
userData = {};
currentUser = "";
calendarTitle = "";
selectedUser = "";
updateSchedule = "";
regularScheduleWeek = "";
adminUser = "";


var timeZones = {

	"PST": "Pacific Standard Time",
	"PDT": "Pacific Daylight Time",
	"MST": "Mountain Standard Time",
	"MDT": "Mountain Daylight Time",
	"CST": "Central Standard Time",
	"CDT": "Central Daylight Time",
	"EST": "Eastern Standard Time",
	"EDT": "Eastern Daylight Time",
	"HST": "Hawaii Standard Time",
	"HDT": "Hawaii Daylight Time",
	"BST":	"British Summer Time",
	"GMT":	"Greenwich Mean Time"
}


function getBlockTime(block) {
	var blockStartMinutes = block.block * 30;
	var blockEndMinutes = (block.block + 1) * 30;
	var startTime = new Date();
	var serverOffsetMinutes = document.getElementById("serverOffsetMinutes").value;
	var offset = serverOffsetMinutes - startTime.getTimezoneOffset();
	blockStartMinutes += offset;
	blockEndMinutes += offset;
	startTime.setHours(0,blockStartMinutes,0,0);
	var endTime = new Date();
	endTime.setHours(0,blockEndMinutes,0,0);
	var formattedStartTime = startTime.formattedTime();
	var formattedEndTime = endTime.formattedTime();
	return {"startTime": formattedStartTime , "endTime": formattedEndTime, 'startTimeObject': startTime , "endTimeObject": endTime};	
	
}


function getBlockServerTime(block) {
    var blockStartMinutes = block.block * 30;
    var blockEndMinutes = (block.block + 1) * 30;
    var localTime = new Date();
    localTime.setHours(0, blockStartMinutes, 0, 0);
    var endTime = new Date();
    endTime.setHours(0, blockEndMinutes, 0, 0);

    var formattedStartTime = localTime.formattedTime();
    var formattedEndTime = endTime.formattedTime();
    return {"startTime": formattedStartTime, "endTime": formattedEndTime, 'startTimeObject': localTime, "endTimeObject": endTime};    
}




function fromMysqlDate(timestamp) {
	var dateParts = timestamp.split("-");
	var jsDate = new Date(dateParts[0], dateParts[1] - 1, dateParts[2].substr(0,2));
	
	return jsDate;
}			


function toMysqlDate(currentDate) {
	if (typeof currentDate === 'string') {
		currentDate = new Date(currentDate);
	}
	return currentDate.toMysqlDate();
}



function updateScheduleRoutine() {
	console.log('🔄 updateScheduleRoutine() - Restarting timer');
	clearInterval(updateSchedule);
	updateSchedule = setInterval(function() {
		console.log('⏰ TIMER: 30-second interval triggered - calling getSchedule()');
		schedule.getSchedule();
	}, 30000);
}


function notesSave() {
	var notes1 = encodeURIComponent(document.getElementById("notes1").innerHTML);
	var notes2 = encodeURIComponent(document.getElementById("notes2").innerHTML);
	var notes3 = encodeURIComponent(document.getElementById("notes3").innerHTML);

	var params = 'notes1=' + notes1 + '&notes2=' + notes2 +'&notes3=' + notes3;
	searchRequest = new AjaxRequest("saveNotes.php", params, results, this);
}




window.onbeforeunload = function() {
	notesSave();
	// Reset Calendar Only status when leaving page (browser close, navigation, etc.)
	var data = new FormData();
	data.append('postType', 'exitCalendar');
	navigator.sendBeacon('../../volunteerPosts.php', data);
}


function exitCallSideVCC() {
	if(!adminUser) {
		// For Calendar Only users (LoggedOn=10), use exitCalendar instead of full exitProgram
		// exitCalendar only resets LoggedOn from 10 to 0, preserving session
		var params = "postType=exitCalendar";
		var xhr = new XMLHttpRequest();
		xhr.open("POST", "../../volunteerPosts.php", false); // synchronous to ensure it completes
		xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
		xhr.send(params);
	}
}




var inactivityTime = function () {
    var time;
    window.onload = resetTimer;
    // DOM Events
    document.onmousemove = resetTimer;
    document.onkeydown = resetTimer;


    function resetTimer() {
        clearTimeout(time);
        time = setTimeout(exitCallSideVCC, 1800000);
        // 1000 milliseconds = 1 second
    }
};



window.onload = function() {
  	inactivityTime();



	// Display Time Zone Name
	var timeZone = new Date();
	var timeString = timeZone.toString();
	var zoneStart = timeString.lastIndexOf("(");
	var zoneEnd = timeString.lastIndexOf(")");
	var zone = timeString.substring(zoneStart + 1 , zoneEnd);
	var timeZoneElement = document.getElementById("timeZone");
	timeZoneElement.appendChild(document.createTextNode("Calendar Times Displayed in: "));
	var strongElement = document.createElement('strong');
	if(timeZones[zone]) {
		zone = timeZones[zone];
	}
	strongElement.appendChild(document.createTextNode(zone));
	timeZoneElement.appendChild(strongElement);

	var calendarLogoffButton = document.getElementById('ExitButton');
	calendarLogoffButton.onclick = function() {
		exitCallSideVCC();
		window.location = '../../index.php';
	}

	calendarTitle = document.getElementById('calendarTitle');
	var currentDate = new Date();
	schedule = new GetVolunteerSchedule(currentDate);
	schedule.init();
	
	// Set Next Month and Prior Month Buttons
	var nextMonthButton = document.getElementById("nextMonthButton");
	var priorMonthButton = document.getElementById("priorMonthButton");
	nextMonthButton.onclick = function () {
		schedule.updating = true;
		var calendar = document.getElementById('calendar');
		removeElements(calendar);
		var calendarTitle = document.getElementById('calendarTitle');
		removeElements(calendarTitle);
		var date = schedule.date.getNextMonth();
		schedule = new GetVolunteerSchedule(date);
		schedule.init();
//		nextMonthButton.style.display = 'none';
//		priorMonthButton.style.display = 'block';
	};
	priorMonthButton.onclick = function () {
		schedule.updating = true;
		var calendar = document.getElementById('calendar');
		removeElements(calendar);
		var calendarTitle = document.getElementById('calendarTitle');
		removeElements(calendarTitle);
		var date = schedule.date.getPriorMonth();
		schedule = new GetVolunteerSchedule(date);
		schedule.init();
//		priorMonthButton.style.display = 'none';
//		nextMonthButton.style.display = 'block';
	};

	var selectedUserElement = document.getElementById("userList");
	selectedUserElement.onchange = setSelectedUser;

	updateScheduleRoutine();
	var form = document.getElementById('calendarModeForm');
	var name = 'calendarMode';
	mode = 'calendarMode';
	getCalendarMode(form,name);
	form.onchange = function() {
		getCalendarMode(form,name);
	};
	
	var userTypesForm = document.getElementById("userTypes");
	userTypesForm.onchange = selectedUserShiftTypeChange;

	var userLocationSelectTypes = document.getElementById("userLocationSelectTypes");
	userLocationSelectTypes.onchange = selectedUserLocationChange;
	
	var calendarNotesSaveButton = document.getElementById("calendarNotesSaveButton");
	calendarNotesSaveButton.onclick = function() {
		notesSave();
	};

	var openShiftsButton = document.getElementById("openShiftsButton");
	openShiftsButton.onclick = function() {
		var date = new Date();
		var shifts = new OpenShiftsReport(date);
		shifts.checkDays();
		shifts.findNormalShifts();
		shifts.displayOpenShifts();
	};
	
	
	var messageArea = document.getElementById('messageArea');
	if(messageArea.firstChild.nodeValue.length > 20) {
			messageArea.style.display = "block";
			alert("You are not signed up for this shift.  Please sign up now.");
	} else {
		messageArea.style.display = null;
	}
}




function getRadioButtonValue(form, name) {
    var radios = form.elements[name];
    
    // loop through list of radio buttons
    for (var i=0, len=radios.length; i<len; i++) {
        if ( radios[i].checked ) { // radio checked?
            val = radios[i].value; // if so, hold its value in val
            break; // and break out of for loop
        }
    }
    return val;
}



function selectedUserShiftTypeChange() {
	var selectedUserElement = document.getElementById("userList");
	var form = document.getElementById('userTypes');
	selectedUser = selectedUserElement.options[selectedUserElement.selectedIndex].value;
	var val = getRadioButtonValue(form, "userTypeRadioButton");

	userData[selectedUser].ShiftType = val; 
}


function selectedUserLocationChange() {
	var selectedUserElement = document.getElementById("userList");
	var form = document.getElementById('userLocationSelectTypes');
	selectedUser = selectedUserElement.options[selectedUserElement.selectedIndex].value;
	var val = getRadioButtonValue(form, "userLocationSelectRadio");

	userData[selectedUser].Location = val; 
}


function getCalendarMode(form, name) {
    var val;
    var calendarView = document.getElementById('calendar');
    var scheduleView = document.getElementById('weeklyShifts');
    var notesView = document.getElementById('notesForm');
    var userShiftDatesForm = document.getElementById('userShiftDatesForm');
	var endingBlockLegend = document.getElementById("endingBlockLegend");
	var futureBlockLegend = document.getElementById("futureBlockLegend");
	var endingBlockLegendText = document.getElementById("endingBlockLegendText");
	var futureBlockLegendText = document.getElementById("futureBlockLegendText");

    var val = getRadioButtonValue(form, name);
    if(val == 'schedule') {
    	mode = 'scheduleMode';
		endingBlockLegend.style.display = null;
		futureBlockLegend.style.display = null;
		endingBlockLegendText.style.display = null;
		futureBlockLegendText.style.display = null;
		calendarView.style.display='none';
		scheduleView.style.display='block';
		notesView.style.display = "block";
    	regularScheduleWeek = {};
    	regularScheduleWeek['month'] = {};
    	regularScheduleWeek.month[0] = new RegularScheduleWeek();
    	regularScheduleWeek.month[0].init();
    	regularScheduleWeek.month[0].displayRegularSchedule();
		var nextMonthButton = document.getElementById("nextMonthButton");
		var priorMonthButton = document.getElementById("priorMonthButton");
		nextMonthButton.style.visibility = 'hidden';
		priorMonthButton.style.visibility = 'hidden';
		userShiftDatesForm.style.visibility = null;

    } else {
    	mode = 'calendarMode';
		endingBlockLegend.style.display = 'none';
		futureBlockLegend.style.display = 'none';
		endingBlockLegendText.style.display = 'none';
		futureBlockLegendText.style.display = 'none';
		calendarView.style.display='block';
		scheduleView.style.display='none';
		notesView.style.display="none";
		var now = new Date();
		updateCalendarDisplay(now);
		var nextMonthButton = document.getElementById("nextMonthButton");
		var priorMonthButton = document.getElementById("priorMonthButton");
		nextMonthButton.style.visibility = null;
		priorMonthButton.style.visibility = null;
		userShiftDatesForm.style.visibility = 'hidden';
    }
}




function setSelectedUser() {
	var startDate = document.getElementById("shiftStartDate");
	var endDate = document.getElementById("shiftEndDate");
	startDate.value = null;
	endDate.value = null;
	var currentUserLegend = document.getElementById("volunteerCurrentUserLegend");
	var currentUserLegendKey = document.getElementById("volunteerCurrentUserLegendKey");
	var selectedUserElement = document.getElementById("userList");
	var userLocationElement = document.getElementById("userLocation");
	selectedUser = selectedUserElement.options[selectedUserElement.selectedIndex].value;
	var volunteerStatus = userData[selectedUser].Volunteer;
	var resourceOnlyStatus = userData[selectedUser].ResourceOnly;
	var trainerStatus = userData[selectedUser].Trainer;

	var spans = document.getElementById("userListBlock").getElementsByTagName('span');
	for (var i = 0; i < spans.length; i++) {  
		var box = spans[i];
		switch(box.id) {
			case "volunteerStatus":
				box.style.display = 'none';
				if(volunteerStatus) {
					box.style.display = null;
					var input = document.getElementById('volunteerStatusRadio')
					input.checked = true;
					userData[selectedUser].ShiftType = input.value;					
				}
				break;
			case "resourceOnlyStatus":
				box.style.display = 'none';
				if(resourceOnlyStatus) {
					box.style.display = null;
				}
				if(!volunteerStatus) {
					var input = document.getElementById('resourceOnlyStatusRadio');
					input.checked = true;
					userData[selectedUser].ShiftType = input.value;					
				}
				break;
			case "trainerStatus":
				box.style.display = 'none';
				if(trainerStatus) {
					box.style.display = null;
				}
				if(!volunteerStatus && !resourceOnlyStatus) {
					var input = document.getElementById('trainerStatusRadio');
					input.checked = true;
					userData[selectedUser].ShiftType = input.value;
				}
				break;
			case "volunteerLocationOfficeSelect":
				if(userData[selectedUser].Location == "SF") {
					var input = document.getElementById('volunteerOfficeRadio');
					input.checked = true;
				}
				break;
				
			case "volunteerLocationRemoteSelect":
				if(userData[selectedUser].Location != "SF") {
					var input = document.getElementById('volunteerRemoteRadio');
					input.checked = true;
				}
				break;
		}				
	}
	if(!adminUser) {
		var userInfo = document.getElementById("userListBlock");
		userInfo.style.background = 'transparent';

		var userInfo = document.getElementById("officeBlockLegend");
		userInfo.style.display = 'none';

		var userInfo = document.getElementById("officeBlockLegendText");
		userInfo.style.display = 'none';

		var userInfo = document.getElementById("remoteBlockLegend");
		userInfo.style.display = 'none';

		var userInfo = document.getElementById("remoteBlockLegendText");
		userInfo.style.display = 'none';

		var userInfo = document.getElementById("endingBlockLegend");
		userInfo.style.display = 'none';

		var userInfo = document.getElementById("futureBlockLegend");
		userInfo.style.display = 'none';

		var userInfo = document.getElementById("resourceOnlyLegend");
		userInfo.style.display = 'none';

		var userInfo = document.getElementById("resourceOnlyLegendText");
		userInfo.style.display = 'none';

		var userInfo = document.getElementById("userList");
		userInfo.style.visibility = 'hidden';
		
		var userInfo = document.getElementById("userLocationSelectTypes");
		userInfo.style.visibility = 'hidden';

		var calendarMode = document.getElementById("calendarModeForm");
		calendarMode.style.visibility = 'hidden';
		mode = 'calendarMode';
		var title = document.getElementById('moduleTitle');
		removeElements(title);
		title.appendChild(document.createTextNode("Volunteer Calendar"));
		
		removeElements(currentUserLegendKey);
		currentUserLegendKey.appendChild(document.createTextNode("Your Shifts"));

	} else {
		var userInfo = document.getElementById("volunteerShiftLegend");
		userInfo.style.display = 'none';

		var userInfo = document.getElementById("volunteerShiftLegendText");
		userInfo.style.display = 'none';

		var userInfo = document.getElementById("tableSpacer");
		userInfo.style.display = 'none';
		
		currentUserLegend.setAttribute("class","userBlock selectedUserBlock");
		removeElements(currentUserLegendKey);
		currentUserLegendKey.appendChild(document.createTextNode("Selected User"));
	}

}

	
	
function RegularScheduleWeek() {

	this.init = function() {
		removeElements(calendarTitle);
		calendarTitle.appendChild(document.createTextNode("Master Schedule"));

	
	
		var regularSchedulePane = document.getElementById('weeklyShifts');
		removeElements(regularSchedulePane);
		this.updateData();
	};
	
	this.updateData = function() {
		var newDate = new Date();
		this.month = newDate.getMonth();
		this.year = newDate.getFullYear();
		this.day = [];
		this.firstDay = "";
		var unfinished = true;
		var i = 1;	
		var j = 0;
		while(unfinished) {
			var checkDay = new Date(this.year, this.month, i);
			if(checkDay.getDayName() == 'Sunday') {
				this.firstDay = checkDay;
				this.day[i] = new Day(i, this.month, this.year);
				i += 1;
				for (var k = 1;k < 7; k++) {
					this.day[i] = new Day(i, this.month, this.year);
					i += 1;
				}
				unfinished = false;
			} else {
				i += 1;
			}
		}
	};

	
	this.displayRegularSchedule = function() {
		for (var i=0;i<7;i++) {
			var blank = schedule.hoursData[i];
			if(!blank) {
				blank = true;
			} else {
				blank = false;
			}
			calendarDay(this.firstDay.getDate(), this.firstDay.getMonth(), this.firstDay.getFullYear(), blank, 'regularSchedule');
			this.firstDay = this.firstDay.getNextDay();
		}
	};
}

	
function Year(year) {
	this.year = year;
	this.month = [];
	for (var i= 0; i<12;i++) {
		this.month[i] = new Month(i, year);
	}
}





function Month(month, year) {
	this.month = month;
	this.year = year;
	this.day = [];
	var monthNameDate = new Date(year , month , "1");
	this.monthName = monthNameDate.getMonthName();
	for (var i=1;i<32;i++) {
		var checkDay = new Date(year, month, i);
		if(checkDay.getMonth() == month) {
			this.day[i] = new Day(i, month, year);
		} else {
			this.day[i] = null;
		}
	}
}


function Day(day, month, year) {
	this.day = day;
	this.month = month;
	this.year = year;
	this.block = [];
	this.date = new Date(year , month , day);
	this.dayName = this.date.getDayName();
	this.dayOfWeek = this.date.getDay();
	this.now = new Date();
	this.now = this.now.getPriorDay();
	for (var i=0;i<48;i++) {
		if(schedule.hoursData[this.dayOfWeek] && schedule.hoursData[this.dayOfWeek].StartTime <= i && schedule.hoursData[this.dayOfWeek].EndTime >= i 
			&& schedule.hoursData[this.dayOfWeek].StartTime != schedule.hoursData[this.dayOfWeek].EndTime) {
			
			if(this.date < this.now && mode == 'calendarMode') {
				this.block[i] = new Block(i, day , month, year, "Historical");
			} else {
				var type = arguments.callee.caller.name;
				this.block[i] = new Block(i, day , month, year, type);
			}
		} else {
			this.block[i] = null;
		}
	}
}


function Block(block, day, month, year, type) {
	self = this;
	this.block = block;
	this.date = new Date(year, month, day);
	this.dayOfWeek = this.date.getDay();
	this.users = {};
	
	if(schedule.scheduleHistory[self.date] && type == 'Historical') {
		if(schedule.scheduleHistory[self.date][block]) {
			var changeData = schedule.scheduleHistory[self.date][block];
			changeData.forEach(function(user) {
				self.users[user.UserName] = user;
				
				if(!userData[user.UserName] || !userData[user.UserName].scheduleHistory[self.date]) {
					if(!userData[user.UserName].scheduleHistory) {
						userData[user.UserName].scheduleHistory = {};
					}

					userData[user.UserName].scheduleHistory[self.date] = {};
				}
				if(!userData[user.UserName].scheduleHistory[self.date][block]) {
					userData[user.UserName].scheduleHistory[self.date][block] = {};
				}
				userData[user.UserName].scheduleHistory[self.date][block] = user;
			});
		}
	} else if (schedule.scheduleData[this.dayOfWeek] && type != "Month") {
		if(schedule.scheduleData[this.dayOfWeek][block]) {
			schedule.scheduleData[this.dayOfWeek][block].forEach(function(user) {
				self.users[user.UserName] = user;
			
				if(!userData[user.UserName].scheduleData[user.Day]) {
					userData[user.UserName].scheduleData[user.Day] = {};
				}
				userData[user.UserName].scheduleData[user.Day][user.Block] = user;
			});
		}
	} else if (schedule.scheduleData[this.dayOfWeek] && type == "Month") {
		if(schedule.scheduleData[this.dayOfWeek][block]) {
			schedule.scheduleData[this.dayOfWeek][block].forEach(function(user) {
				var startDate = fromMysqlDate(user.StartDate);
				var endDate = fromMysqlDate(user.EndDate);
				if(startDate <= self.date && endDate >= self.date) {
					self.users[user.UserName] = user;
				
					if(!userData[user.UserName].scheduleData[user.Day]) {
						userData[user.UserName].scheduleData[user.Day] = {};
					}
					userData[user.UserName].scheduleData[user.Day][user.Block] = user;
				}
			});
		}
	} 
	if(schedule.scheduleChangesData[self.date] && type == 'Month') {
		if(schedule.scheduleChangesData[self.date][block]) {
			var changeData = schedule.scheduleChangesData[self.date][block];
			changeData.forEach(function(user) {
				if(user.Type == "Delete") {
					delete(self.users[user.UserName]);
				} else {
					self.users[user.UserName] = user;
				}
				if(!userData[user.UserName].scheduleChanges[self.date]) {
					userData[user.UserName].scheduleChanges[self.date] = {};
				}
				if(!userData[user.UserName].scheduleChanges[self.date][block]) {
					userData[user.UserName].scheduleChanges[self.date][block] = {};
				}
				userData[user.UserName].scheduleChanges[self.date][block] = user;
			});
		}
	}
}	








	
function GetVolunteerSchedule(date) {
	this.date = date;
	this.month = date.getMonth();
	this.year = date.getFullYear();
	this.scheduleData = {};
	this.scheduleChangesData = {};
	this.scheduleHistory = {};
	this.hoursData = [];
	this.params = 		'None';
	this.scheduleUrl = 			'../getVolunteerSchedule.php';
	this.futureScheduleUrl = 			'../getFutureVolunteerSchedule.php';
	this.hoursUrl = 			'../openHours.php';
	this.userDataURL = 			'../userData.php';
	this.scheduleHistoryUrl = 'getScheduleHistory.php';
	this.updating = true;
	console.log('🚨 SCHEDULE CONSTRUCTOR: updating set to TRUE');
	var self = this;

	
	this.resultFunctionSchedule = function (results, resultObject) {
		if(!resultObject.results) {
			resultObject.scheduleData = {};
			resultObject.scheduleData = JSON.parse(results);
		}
		regularScheduleWeek = {};
    	regularScheduleWeek['month'] = {};
    	regularScheduleWeek.month[0] = new RegularScheduleWeek();
    	regularScheduleWeek.month[0].updateData();

		self.getHours();
	};

	this.resultFunctionScheduleChanges = function (results, resultObject) {
		if(!resultObject.results) {
			self.scheduleChangesData = {};
			var tempScheduleChangesData = JSON.parse(results);
			for (mysqslDate in tempScheduleChangesData) {
				var record = tempScheduleChangesData[mysqslDate];
				var currentDate = fromMysqlDate(mysqslDate);
				self.scheduleChangesData[currentDate] = record;
			}
		}
		updateCalendarDisplay(self.date);
	};

	this.resultFunctionScheduleHistory = function (results, resultObject) {
		if(!resultObject.results) {
			self.scheduleHistory = {};
			var tempScheduleHistoryData = JSON.parse(results);
			for (mysqslDate in tempScheduleHistoryData) {
				var record = tempScheduleHistoryData[mysqslDate];
				var currentDate = fromMysqlDate(mysqslDate);
				self.scheduleHistory[currentDate] = record;
			}
		}
	};


	this.resultFunctionUserData = function(results,resultObject) {
		if(!resultObject.results) {
			userData = JSON.parse(results);
		}
		var userListMenu = document.getElementById('userList');
		removeElements(userListMenu);
		var obj = userData;
		for (key in obj) {
			var user = obj[key];
			userData[user.UserName] = new User(user);
			var option = document.createElement('option');
			option.value = user.UserName;
			option.appendChild(document.createTextNode(user.ListName));
			userListMenu.appendChild(option);
		}
		self.updating = false;

		var selectedUserElement = document.getElementById("userList");
		if(selectedUser) {
			currentUser = selectedUser;
		} else {
			currentUser = document.getElementById("volunteerID").value;
		}
		for(var i = 0, j = selectedUserElement.options.length; i < j; ++i) {
			var user = selectedUserElement.options[i];
			if(user.value == currentUser) { 
				selectedUserElement.selectedIndex = i;
				break;
			}
		}

		var volunteerID = document.getElementById("volunteerID").value;
		var userLoggedOn = userData[volunteerID] ? userData[volunteerID].LoggedOn : 0;
		adminUser = (userLoggedOn == 2 || userLoggedOn == 7);
		if(adminUser) {
			// Admin users access calendar from within VCC - hide exit button
			var exitButton = document.getElementById("ExitButton");
			exitButton.style.display = 'none';
			var blockColorLegend = document.getElementById('blockColorLegend');
			blockColorLegend.style.display = 'none';
		}
		// Non-admin users: keep exit button visible, don't call exitCallSideVCC()
		// Users with LoggedOn=10 (Calendar Only) or other statuses can view calendar
		setSelectedUser();
	};


	this.resultFunctionHours = function(results,resultObject) {
		if(!resultObject.results) {
			resultObject.hoursData = JSON.parse(results);
		}
		self.getScheduleChanges();
	};
	
			
	this.init = function() {
		console.log('🟢 SCHEDULE INIT: Setting updating to FALSE for initial load');
		this.updating = false; // Allow initial data load
		this.getSchedule();
		this.getUserData();
		this.getScheduleHistory();
	};
	
	this.getFutureSchedule = function() {
		var params = 'month=' + this.month + '&year=' + this.year;
		searchRequest = new AjaxRequest(this.futureScheduleUrl, params, this.resultFunctionSchedule, this);
	};
	


	this.getSchedule = function() {
		console.log('📅 getSchedule() called - updating flag:', self.updating);
		if (self.updating) {
			console.log('❌ getSchedule() BLOCKED by updating flag');
			return;
		}
		console.log('✅ getSchedule() PROCEEDING - fetching data with retry mechanism');
		var params = 'month=' + this.month + '&year=' + this.year;
		
		// Use BackgroundAjaxRequest with retry mechanism for background updates
		searchRequest = new BackgroundAjaxRequest(this.scheduleUrl, params, this.resultFunctionSchedule, this, {
			maxRetries: 3,        // Retry up to 3 times
			initialDelay: 1000,   // Start with 1 second delay
			maxDelay: 8000,       // Cap at 8 second delay
			silentMode: true,     // Don't show alerts on failure
			logErrors: true       // Log errors to console for debugging
		});
	};
	
	this.getScheduleChanges = function() {
		if (self.updating) {
			return;
		}
		var params = 'changes=true&month=' + this.month + '&year=' + this.year;
		
		// Use BackgroundAjaxRequest with retry mechanism for background updates
		searchRequest = new BackgroundAjaxRequest(this.scheduleUrl, params, this.resultFunctionScheduleChanges, this, {
			maxRetries: 3,        // Retry up to 3 times
			initialDelay: 1000,   // Start with 1 second delay
			maxDelay: 8000,       // Cap at 8 second delay
			silentMode: true,     // Don't show alerts on failure
			logErrors: true       // Log errors to console for debugging
		});
	};

	this.getHours = function() {
		// Use BackgroundAjaxRequest with retry mechanism for background updates
		searchRequest2 = new BackgroundAjaxRequest(this.hoursUrl, this.params, this.resultFunctionHours, this, {
			maxRetries: 3,        // Retry up to 3 times
			initialDelay: 1000,   // Start with 1 second delay
			maxDelay: 8000,       // Cap at 8 second delay
			silentMode: true,     // Don't show alerts on failure
			logErrors: true       // Log errors to console for debugging
		});
	};

	this.getUserData = function() {
		console.log('👤 getUserData() called - updating flag:', self.updating);
		if (self.updating) {
			console.log('❌ getUserData() BLOCKED by updating flag');
			return;
		}
		console.log('✅ getUserData() PROCEEDING - fetching data with retry mechanism');
		
		// Use BackgroundAjaxRequest with retry mechanism for background updates
		searchRequest3 = new BackgroundAjaxRequest(this.userDataURL, this.params, this.resultFunctionUserData, this, {
			maxRetries: 3,        // Retry up to 3 times
			initialDelay: 1000,   // Start with 1 second delay
			maxDelay: 8000,       // Cap at 8 second delay
			silentMode: true,     // Don't show alerts on failure
			logErrors: true       // Log errors to console for debugging
		});
	};

	this.getScheduleHistory = function() {
		console.log('📜 getScheduleHistory() called - updating flag:', self.updating);
		if (self.updating) {
			console.log('❌ getScheduleHistory() BLOCKED by updating flag');
			return;
		}
		console.log('✅ getScheduleHistory() PROCEEDING - fetching data with retry mechanism');
		var params = 'month=' + this.month + '&year=' + this.year;
		
		// Use BackgroundAjaxRequest with retry mechanism for background updates
		searchRequest = new BackgroundAjaxRequest(this.scheduleHistoryUrl, params, this.resultFunctionScheduleHistory, this, {
			maxRetries: 3,        // Retry up to 3 times
			initialDelay: 1000,   // Start with 1 second delay
			maxDelay: 8000,       // Cap at 8 second delay
			silentMode: true,     // Don't show alerts on failure
			logErrors: true       // Log errors to console for debugging
		});
	};



}	
	
	
function calendarMonth(month, year) {
	var calendar = document.getElementById("calendar");
	removeElements(calendar);
	var firstDate = new Date(year, month, 1);
	var firstDay = firstDate.getDay();
	var startDate = firstDate.getPriorDay(firstDay);
	while(firstDay > 0) {
		var blankDay = startDate.getDate();
		var blankMonth = startDate.getMonth();
		var blankYear = startDate.getFullYear();

		calendarDay(blankDay , blankMonth, blankYear, "PriorMonth", 'Month');
		startDate = startDate.getNextDay();
		firstDay -= 1;
	}
	var displayMonth = currentYear.month[month].day;
	displayMonth.forEach(function(day) {
		var todaysDate = new Date();
		if(day) {
			if(day.date < todaysDate.getPriorDay()) {
				var blankDay = day.date.getDate();
				var blankMonth = day.date.getMonth();
				var blankYear = day.date.getFullYear();
	
				calendarDay(blankDay , blankMonth, blankYear, "PastDate", 'Month');
			} else if (day) {
				var stuff = calendarDay(day.day, month, year, false, 'Month');
			}
		}
	});
}




function calendarDay(day, month, year, blank, type) {
	var date = new Date(year, month, day);
	var dayName = date.getDayName();
	if(type == 'Month') {
		var calendar = document.getElementById("calendar");
	} else {
		var calendar = document.getElementById("weeklyShifts");
	}
	var dayElement = document.createElement("div");
	dayElement.id = month + "/" + day + "/" + year;
	dayElement.setAttribute("class","calendarDay " + dayName);
	if ((blank && !adminUser) || blank == "PriorMonth") {
		dayElement.style.backgroundColor = "gray";
		var token = document.createElement("span");
		token.setAttribute("class" , "dayToken");
		token.appendChild(document.createTextNode(day));	
		dayElement.appendChild(token);
	} else {
		dayElement.style.backgroundColor = "white";
		if(type == 'Month') {
			if (blank == 'PastDate') {
				dayElement.style.backgroundColor = "gray";
				type = "Historical";
			}
			var token = document.createElement("span");
			token.setAttribute("class" , "dayToken");
			token.appendChild(document.createTextNode(day));	
			dayElement.appendChild(token);
			var span = document.createElement("span");
			span.setAttribute("class", 'dayName');
			span.appendChild(document.createTextNode(" " + dayName));
			dayElement.appendChild(span);
			var displayBlock = currentYear.month[month].day[day].block;
		} else {
			var displayBlock = regularScheduleWeek.month[0].day[day].block;
		}
		displayBlock.forEach(function(block) {
			if(block) {
				var blockElement = calendarBlock(block, day, month, year, type);
				dayElement.appendChild(blockElement);
			}
		});
	}
		

	calendar.appendChild(dayElement);
}


function numberOfNonResourceUsers(obj) {
	if (obj) {
		var numberOfUsers = 0;
		for(key in obj) {
			var user = obj[key];
			if(user.ShiftType != "ResourceOnly") {
				numberOfUsers += 1;
			}
		}
	} else {
		var numberOfUsers = 0;
	}
	return numberOfUsers;
}


function calendarBlock(block, day, month, year, type) {


	var blockTime = getBlockTime(block);
//	alert(test.endTime);




	var blockElement = document.createElement("div");
	blockElement.setAttribute("class","blockElement");
	blockElement.id = new Date(year, month, day) + block.block;
	var blockName = document.createElement("span");
	blockName.setAttribute("class",'blockName');
	blockName.appendChild(document.createTextNode(blockTime.startTime));
	blockElement.title = blockTime.startTime + " - " + blockTime.endTime;	
	blockElement.appendChild(blockName);
	if(type != "Historical") {
		blockElement.onclick=function(event) {
			checkBlockForUser(event, block.date,block.block);
		}
	}
	if(type == 'Month' || type == "Historical") {
		var users = currentYear.month[month].day[day].block[block.block].users;
	} else {
		var users = regularScheduleWeek.month[0].day[day].block[block.block].users;
	}
	for (key in users) {
		var user = users[key];
		if(user) {
			userElement = new UserBlock(user, block, type);
			blockElement.appendChild(userElement);
		}
	}
	if(objectSize(users) == 0) {
		var numberOfUsers = 0;
	} else {
		var numberOfUsers = numberOfNonResourceUsers(users);
	}
	
	if(numberOfUsers == 0 && type != "Historical") {
		blockElement.style.backgroundColor = "RGBA(255, 255, 0, .65)";
	}
	if(numberOfUsers >= 3 && type != "Historical") {
		blockElement.style.backgroundColor = "silver";
	}
	return blockElement;
}
	
	

function checkBlockForUser(event, date, block) {

	var shiftStartDate = document.getElementById("shiftStartDate").value;
	var shiftEndDate = document.getElementById("shiftEndDate").value;
	
	var existingDeleteMenu = document.getElementById("deleteConfirmDialog");
	if(existingDeleteMenu.style.visibility) {
		return;
	}
	var dayClicked = date.getDate();
	var monthClicked = date.getMonth();
	var yearClicked = date.getFullYear();
	if(mode == 'calendarMode') {	
		if(!currentYear.month[monthClicked].day[dayClicked].block[block].users || !currentYear.month[monthClicked].day[dayClicked].block[block]) {
			var numberOfUsers = 0;
		} else {
			var numberOfUsers = numberOfNonResourceUsers(currentYear.month[monthClicked].day[dayClicked].block[block].users);
		}

		if(currentYear.month[monthClicked].day[dayClicked].block[block].users[selectedUser]) {
			selectMenu(event, selectedUser, date, block)
		} else if(numberOfUsers < 3 || (adminUser && numberOfUsers < 4)) {
			addSelectedUser(event, selectedUser, date, block);
		} else if(numberOfUsers >= 3) {
			alert("Please contact Aaron at aaron@GLBThotline.org to add you as the fourth person to this shift.");
		}

	} else {
		var listOfUsers = regularScheduleWeek.month[0].day[dayClicked].block[block].users;
		var numberOfUsers = objectSize(listOfUsers);
		if(regularScheduleWeek.month[0].day[dayClicked].block[block].users[selectedUser]) {
			selectMenu(event, selectedUser, date, block)
		} else if(numberOfUsers < 4) {
			addSelectedUser(event, selectedUser, date, block);
		} else if(numberOfUsers >= 4) {
			alert("Too Many volunteers have already signed up for this block.  Please select another block.");
		}
	}
}

function UserBlock(user, block, type) {
	self = this;
	var noEndDate = fromMysqlDate("2999-12-31");
	var now = new Date();
	var userInitials = user.FullName.split(" ");
	userInitials[0] = userInitials[0].substr(0,1);
	userInitials[1] = userInitials[1].substr(0,1);
	if(userInitials[1] == " " && userInitials[2]) {
		userInitials[1] = userInitials[2];
	}
	this.userName = user.UserName;
	this.fullName = user.FullName;
	var userBlock = document.createElement('span');
	userBlock.id=user.UserName + "." + block.date;
	userBlock.onclick = function() {
//		alert(userData[user.UserName].fullName);
	};
	userBlock.appendChild(document.createTextNode(userInitials[0] + userInitials[1]));
	if(user.ShiftType == 'Trainer') {
		var userLocationClass = "userBuddyShift";
		var userTitle = user.FullName + ": Buddy Shift";
	} else if (user.ShiftType == 'ResourceOnly') {
		var userLocationClass = "userResourceOnly";
		var userTitle = user.FullName + ": Resource Only";
	} else if (user.Location == "SF") {
		var userLocationClass = "userInOffice";
		var userTitle = user.FullName + ": Office Shift";
	} else {
		var userLocationClass = "userRemote";
		var userTitle = user.FullName + ": Remote Shift";
	}
	if(!adminUser) {
		if(userLocationClass == "userResourceOnly") {
			userBlock.style.display = "none";
		} else if(userLocationClass == "userBuddyShift") {
			userLocationClass = userLocationClass;
		} else {
			userLocationClass = "userResourceOnly";
		}
	} 

	

	userBlock.setAttribute('class','userBlock ' + userLocationClass);

	if(type == "regularSchedule") {
		var classText = "userBlock " + userLocationClass;
	
		if(user.StartDate) {
			var startDate = fromMysqlDate(user.StartDate);
			var endDate = fromMysqlDate(user.EndDate);
			if(startDate > now) {
				userBlock.setAttribute("class",classText + " futureUserBlock");
				userTitle = "FUTURE SHIFT\n" + userTitle + "\nStarting: " + startDate.formattedDate();
				var shiftType = "future";
			}
		}

		if(endDate < noEndDate) {
			var shiftType = "ending";
			userBlock.setAttribute("class",classText + " endingUserBlock");

			userTitle = "ENDING SHIFT\n" + userTitle + "\nEnding: " + endDate.formattedDate(); 
		}		
	} 

	userBlock.title = userTitle;

	if(user.UserName == selectedUser) {
		userBlock.setAttribute("class", "selectedUserBlock " + userBlock.getAttribute("class"));
		if(startDate > now) {
			userBlock.setAttribute("class","userBlock selectedUserBlock futureUserBlock");
		} else {
			userLocationClass = "selectedUserBlock";
		}

//			userBlock.style.color = "white";
	} 
	

	switch(userLocationClass) {
	
			case "userInOffice":
				if (shiftType != "future") {
					userBlock.style.backgroundColor = "RGBA(173, 216, 230, 1)";
				} else { 
					userBlock.style.backgroundColor = "RGBA(173, 216, 230, .5)";
				}
				break;
					
		
			case "userRemote":
				if (shiftType != "future") {
					userBlock.style.backgroundColor = "RGBA(250, 181, 127, 1)";
				} else { 
					userBlock.style.backgroundColor = "RGBA(250, 181, 127, .5)";
				}
				break;
					

			case "userBuddyShift":
				if (shiftType != "future") {
					userBlock.style.backgroundColor = "RGBA(100, 250, 100, 1)";
				} else { 
					userBlock.style.backgroundColor = "RGBA(100, 250, 100, .5)";
				}
				break;
					

			case "userResourceOnly":
				userBlock.style.backgroundColor = "white";
				break;
					
			
			case "currentUserBlock":
				if (shiftType != "future") {
					userBlock.style.backgroundColor = "RGBA(250, 181, 127, 1)";
				} else { 
					userBlock.style.backgroundColor = "RGBA(250, 181, 127, .5)";
				}
				break;
					
		
			case "selectedUserBlock":
				if (shiftType != "future") {
					userBlock.style.backgroundColor = "RGBA(128,0,128, 1)";
					userBlock.style.color = "white";
				} else { 
					userBlock.style.backgroundColor = "RGBA(128,0,128, .5)";
				}
				break;
					
		}
		
	if(type == "Historical") {
		if(user.LoggedOnForBlock == "0") {
			userBlock.style.backgroundColor = "yellow";
			userBlock.title += "\rMISSED SHIFT.";	
		} else { 
			userBlock.style.backgroundColor = "silver";
		}
	}

	return userBlock;	
}	


function results(results) {
//	alert(results);
}

function getStyle(className) {
    var x, sheets,classes;
    for( sheets=document.styleSheets.length-1; sheets>=0; sheets-- ){
        classes = document.styleSheets[sheets].rules || document.styleSheets[sheets].cssRules;
        for(x=0;x<classes.length;x++) {
            if(classes[x].selectorText===className){
                classStyleTxt = (classes[x].cssText ? classes[x].cssText : classes[x].style.cssText).match(/\{\s*([^{}]+)\s*\}/)[1];
                var classStyles = {};
                var styleSets = classStyleTxt.match(/([^;:]+:\s*[^;:]+\s*)/g);
                for(y=0;y<styleSets.length;y++){
                    var style = styleSets[y].match(/\s*([^:;]+):\s*([^;:]+)/);
                    if(style.length > 2)
                        classStyles[style[1]]=style[2];
                }
                return classStyles;
            }
        }
    }
    return false;
}

function User(userData) {
	this.UserName = userData.UserName;
	this.FullName = userData.FullName;
	this.Type = userData.Type;
	this.Initials = userData.Initials;
	this.Location = userData.Location;
	this.Trainer = userData.Trainer;
	this.ResourceOnly = userData.ResourceOnly;
	this.Volunteer = userData.Volunteer;
	this.LoggedOn = userData.LoggedOn;
	this.scheduleData = {};
	this.scheduleChanges = {};
	this.scheduleHistory = {};
	this.ShiftType = "";
}



function addSelectedUser(event, selectedUser, date, blockNumber) {
	console.log('🔄 addSelectedUser() START - Setting updating to TRUE');
	console.log('🎯 Current mode: ' + mode);
	schedule.updating = true;
	var dayClicked = date.getDate();
	var monthClicked = date.getMonth();
	var yearClicked = date.getFullYear();
	if(mode == 'calendarMode') {
		console.log('💾 SAVING LOCAL DATA: month=' + monthClicked + ', day=' + dayClicked + ', block=' + blockNumber + ', user=' + selectedUser);
		currentYear.month[monthClicked].day[dayClicked].block[blockNumber].users[selectedUser] = userData[selectedUser];
		var addDate = toMysqlDate(date);
		console.log('💾 AJAX SAVE REQUEST: ' + addDate + ', block=' + blockNumber + ', user=' + selectedUser);
		params = "date=" + addDate + "&type=" + "Add" + "&block=" + blockNumber + "&volunteerID=" + selectedUser + "&shiftType=" + userData[selectedUser].ShiftType + "&shiftLocation=" + userData[selectedUser].Location;  
		var results = function(results, resultObject) {
			console.log('✅ AJAX SAVE COMPLETE - Setting updating to FALSE');
			schedule.updating = false;
			console.log('🗺 calendarMonth() - Refreshing display');
			calendarMonth(monthClicked, yearClicked);
			//alert(results);
		};
		console.log('💾 Starting AJAX save request...');
		searchRequest2 = new AjaxRequest("../insertChange.php", params, results, this);
	} else if (mode == 'scheduleMode') {
		console.log('📋 SCHEDULE MODE - Processing user addition');
		var startDateInput = document.getElementById("shiftStartDate").value;
		var endDateInput = document.getElementById("shiftEndDate").value;
		console.log('📅 Raw date inputs: start="' + startDateInput + '", end="' + endDateInput + '"');
		
		// Handle empty date inputs
		if(!startDateInput || startDateInput === "") {
			var startDate = "1900-01-01";
			console.log('📅 Using default start date: ' + startDate);
		} else {
			var startDate = toMysqlDate(startDateInput);
			console.log('📅 Converted start date: ' + startDate);
		}
		
		if(!endDateInput || endDateInput === "") {
			var endDate = "2999-12-31";
			console.log('📅 Using default end date: ' + endDate);
		} else {
			var endDate = toMysqlDate(endDateInput);
			console.log('📅 Converted end date: ' + endDate);
		}
		
		// Additional fallback for NaN dates
		if(startDate.includes("NaN")) {
			var startDate = "1900-01-01";
			console.log('📅 NaN detected, using fallback start date: ' + startDate);
		}
		if(endDate.includes("NaN")) {
			var endDate = "2999-12-31";
			console.log('📅 NaN detected, using fallback end date: ' + endDate);
		}
		var dayOfWeekClicked = regularScheduleWeek.month[0].day[dayClicked].dayOfWeek;
		console.log('💾 SAVING SCHEDULE DATA: dayOfWeek=' + dayOfWeekClicked + ', block=' + blockNumber + ', user=' + selectedUser);
		if(!schedule.scheduleData[dayOfWeekClicked]) {
			schedule.scheduleData[dayOfWeekClicked] = [];
		}
		if(!schedule.scheduleData[dayOfWeekClicked][blockNumber]) {
			schedule.scheduleData[dayOfWeekClicked][blockNumber] = [];
		}
		schedule.scheduleData[dayOfWeekClicked][blockNumber].push(userData[selectedUser]); 
    	regularScheduleWeek.month[0].init();
    	regularScheduleWeek.month[0].displayRegularSchedule();
		params = "day=" + dayOfWeekClicked + "&type=" + "Add" + "&block=" + blockNumber + "&volunteerID=" + selectedUser + "&startDate=" + startDate + "&endDate=" + endDate
				+ "&shiftType=" + userData[selectedUser].ShiftType + "&shiftLocation=" + userData[selectedUser].Location;  
		console.log('💾 AJAX SCHEDULE SAVE REQUEST: day=' + dayOfWeekClicked + ', block=' + blockNumber + ', user=' + selectedUser + ', dates=' + startDate + ' to ' + endDate);
		console.log('🔗 Full Ajax params: ' + params);
		var results = function(results, resultObject) {
			console.log('✅ AJAX SCHEDULE SAVE COMPLETE - Setting updating to FALSE');
			console.log('📋 Schedule save result: ' + results);
			schedule.updating = false;
			var name = 'calendarMode';
			var form = document.getElementById('calendarModeForm');
			updateScheduleRoutine();
			getCalendarMode(form,name);
		};
		console.log('💾 Starting AJAX schedule save request...');
		searchRequest2 = new AjaxRequest("../regularScheduleChange.php", params, results, this);
	}
}


function getClickPosition(e) {
	var xPosition = e.clientX + document.body.scrollLeft + document.documentElement.scrollLeft; 
	var yPosition = e.clientY + document.body.scrollTop + document.documentElement.scrollTop; 

    return { x: xPosition, y: yPosition };
}
 



function selectMenu(event, volunteerID, clickedDate, blockNumber) {
	schedule.updating = true;
	var addFutureShift = document.getElementById("addFutureShift");
	addFutureShift.style.display = "none";

	var existingDeleteMenu = document.getElementById("deleteConfirmDialog");
	var deleteShiftButton = document.getElementById("deleteShiftButton");
	var deleteBlockButton = document.getElementById("deleteBlockButton");
	var position = getClickPosition(event);
    var x = position.x;
    var y = position.y;
	existingDeleteMenu.style.top = y + "px";
	existingDeleteMenu.style.left = x + "px";
	existingDeleteMenu.style.visibility = "visible";
	existingDeleteMenu.style.zIndex = 1000;
	var outsideClickedNumber = 0;
	deleteShiftButton.onclick = function() {
		//alert("Delete Shift For: " + userData[selectedUser].fullName);
		existingDeleteMenu.style.visibility = null;
		deleteUserShift(volunteerID, clickedDate, blockNumber);
		schedule.updating = false;

	}
	deleteBlockButton.onclick = function() {
		//alert("Delete Block For: " + userData[selectedUser].fullName);
		existingDeleteMenu.style.visibility = null;
		deleteSingleUserBlock(volunteerID, clickedDate, blockNumber);
		schedule.updating = false;
	}
	
	if(mode != 'calendarMode') {
		addFutureShift.style.display = null;
		addFutureShift.onclick = function() {
			//alert("Delete Shift For: " + userData[selectedUser].fullName);
			existingDeleteMenu.style.visibility = null;
			deleteUserShift(volunteerID, clickedDate, blockNumber, "Update");
			schedule.updating = false;
		}
	}
	
	document.onclick = function() {
		outsideClickedNumber += 1;
		if(outsideClickedNumber > 1) {
			if(clickedOutsideElement(existingDeleteMenu.id)) {
				existingDeleteMenu.style.visibility = null;
				document.onclick = null;
				deleteShiftButton.onclick = null;
				deleteBlockButton.onclick = null;
				schedule.updating = false;
			} else {
			}
		}
	}
}


function clickedOutsideElement(elemId) {
  var theElem = getEventTarget(window.event);
  while(theElem != null) {
    if(theElem.id == elemId)
      return false;
    theElem = theElem.offsetParent;
  }
  return true;
}

function getEventTarget(evt) {
  var targ = (evt.target) ? evt.target : evt.srcElement;
  if(targ != null) {
    if(targ.nodeType == 3)
      targ = targ.parentNode;
  }
  return targ;
}



function deleteUserShift(volunteerID, date, blockNumber, type) {
	schedule.updating = true;
	var loopContinue = true;
	var firstBlock = null;
	blockNumber = Number(blockNumber);
	var dayClicked = date.getDate();
	var monthClicked = date.getMonth();
	var yearClicked = date.getFullYear();

	
	// Find earliest block in User's Shift
	while (loopContinue) {
		if(mode == 'calendarMode') {
			if(currentYear.month[monthClicked].day[dayClicked].block[blockNumber] &&
			currentYear.month[monthClicked].day[dayClicked].block[blockNumber].users[selectedUser]) {
				blockNumber = blockNumber - 1;
			} else {
				firstBlock = blockNumber + 1;
				loopContinue = false;
			}
		} else if (mode == 'scheduleMode') {
			if(regularScheduleWeek.month[0].day[dayClicked].block[blockNumber] && regularScheduleWeek.month[0].day[dayClicked].block[blockNumber].users[selectedUser]) {			
				blockNumber = blockNumber - 1;
			} else {
				firstBlock = blockNumber + 1;
				loopContinue = false;
			}
		}
	}


	// Delete blocks from User's Shift
	loopContinue = true;
	var blockNumber = firstBlock;
	while (loopContinue) {
		if(mode == 'calendarMode') {
			if(currentYear.month[monthClicked].day[dayClicked].block[blockNumber].users[selectedUser]) {
				deleteSingleUserBlock(volunteerID, date, blockNumber)
				blockNumber = blockNumber + 1;
			} else {
				loopContinue = false;
			}
		} else if (mode == 'scheduleMode') {
			if(regularScheduleWeek.month[0].day[dayClicked].block[blockNumber].users[selectedUser]) {			
				deleteSingleUserBlock(volunteerID, date, blockNumber)
				if(type == "Update") {
					addSelectedUser(event, selectedUser, date, blockNumber);
				}
				blockNumber = blockNumber + 1;
			} else {
				loopContinue = false;
			}
		}
	}
}

function deleteSingleUserBlock(volunteerID, date, blockNumber) {
	var dayClicked = date.getDate();
	var monthClicked = date.getMonth();
	var yearClicked = date.getFullYear();
	if(mode == 'calendarMode') {
		delete(currentYear.month[monthClicked].day[dayClicked].block[blockNumber].users[selectedUser]);
		var deleteDate = toMysqlDate(date);
		params = "date=" + deleteDate + "&type=" + "Delete" + "&block=" + blockNumber + "&volunteerID=" + selectedUser;  
		var results = function(results, resultObject) {schedule.updating = false;};
		searchRequest2 = new AjaxRequest("../insertChange.php", params, results, this);
		calendarMonth(monthClicked, yearClicked);
	} else if (mode == 'scheduleMode') {
		var startDate = regularScheduleWeek.month[0].day[dayClicked].block[blockNumber].users[selectedUser].StartDate;
		var endDate = regularScheduleWeek.month[0].day[dayClicked].block[blockNumber].users[selectedUser].EndDate;
		var dayOfWeekClicked = regularScheduleWeek.month[0].day[dayClicked].dayOfWeek;
		
		for (i = 0 ; i < schedule.scheduleData[dayOfWeekClicked][blockNumber].length ; i++) {	
			if(schedule.scheduleData[dayOfWeekClicked][blockNumber][i].UserName == volunteerID) {
	    	    schedule.scheduleData[dayOfWeekClicked][blockNumber].splice(i--, 1);
			}
		}
    	regularScheduleWeek.month[0].init();
    	regularScheduleWeek.month[0].displayRegularSchedule();
		params = "day=" + dayOfWeekClicked + "&type=" + "Delete" + "&block=" + blockNumber + "&volunteerID=" + selectedUser + "&startDate=" + startDate + "&endDate=" + endDate;  
		var results = function(results, resultObject) {
			//alert(results);
			schedule.updating = false;
			updateScheduleRoutine();
		};
		searchRequest2 = new AjaxRequest("../regularScheduleChange.php", params, results, this);
	}
}


function updateCalendarDisplay(date) {
	if (schedule.updating) {
		return;
	}

	if(!date) {
		date = new Date();
	}

	var month = date.getMonth();
	var year = date.getFullYear();

   if(mode == 'scheduleMode') {
    	regularScheduleWeek.month[0].updateData();
		var regularSchedulePane = document.getElementById('weeklyShifts');
		removeElements(regularSchedulePane);
    	regularScheduleWeek.month[0].displayRegularSchedule();
    } else {
		removeElements(calendarTitle);
		calendarTitle.appendChild(document.createTextNode(date.getMonthName() + " " + year));
		currentYear = new Year(year);	
		nextYear = new Year(Number(year + 1));
		calendarMonth(month, year);
	}
}


function getNextMonday(date) {
    const dayOfWeek = date.getDay(); // Get the current day of the week (0 = Sunday, 1 = Monday, etc.)
    const distanceToMonday = (8 - dayOfWeek) % 7; // Calculate the distance to next Monday
    const nextMonday = new Date(date); // Create a copy of the current date
    nextMonday.setDate(date.getDate() + distanceToMonday); // Move the date forward to next Monday
    return nextMonday;
}


function OpenShiftsReport(date) {
	var self = this;
	date = getNextMonday(date);
	this.date = date;
	this.year = date.getFullYear();
	var oldMode = mode;
	mode = 'calendarMode';
	this.month = date.getMonth();
	this.day= date.getDate();
	this.dayOfWeek = date.getDay();
	this.numberOfDays = (7 - this.dayOfWeek) + 7;
	this.openDays = [];
	
	this.checkDays = function() {
		for(var i=0;i<this.numberOfDays;i++) {
			this.openDays[i] = self.checkDay();
			this.openShifts = [];
			date = date.getNextDay();
			this.month = date.getMonth();
			this.day= date.getDate();
		}			
	};

		
	
	this.checkDay = function() {
		var openShifts = [];
		if(date.getFullYear() == self.year) {
			var blocks = currentYear.month[this.month].day[this.day].block;
		} else {
			var blocks = nextYear.month[this.month].day[this.day].block;
		}
		blocks.forEach(function(block) {
			if(block) {
				var blockTimes = getBlockServerTime(block);
				var openShiftStart = self.openBlockCheck(block);
				if(!openShiftStart) {
					return false;
				} else {
					openShifts[block.block] = openShiftStart;
				}
			}
		});
		return openShifts;
	};


	this.openBlockCheck = function(block) {
		var firstBlock = block.block;
		var blockNumber = block.block;
		var openShift = true;
		var totalUsers = 0;

		if(block.block < 38) {
			var shiftLength = 6;	
		} else {
			var shiftLength = 4;	
		}
		
		for( var i = firstBlock;i<firstBlock + shiftLength; i++) {
			if(date.getFullYear() == self.year) {
				var activeYear = currentYear;
			} else {
				var activeYear = nextYear;
			}
			if(activeYear.month[this.month].day[this.day].block[i]) {
				if(activeYear.month[this.month].day[this.day].block[i].users) {
					var users = activeYear.month[this.month].day[this.day].block[i].users;
					var onShift = numberOfNonResourceUsers(users);
					totalUsers += onShift;
					if(onShift > 1) {
						return false;
					}
				}
			} else {
				return false;
			}
		}
		block.times = getBlockServerTime(block);
		var fakeBlock = {};
		switch(block.block) {
			case 22:
			case 28:
			case 34:
				fakeBlock.block = firstBlock + 5;
				break;
			case 40:
				fakeBlock.block = firstBlock + 3;
				break;
			default:
				fakeBlock.block = null;
				break;
		}
		
		var lastBlockTime = getBlockServerTime(fakeBlock).endTime;
		var lastBlockTimeObject = getBlockServerTime(fakeBlock).endTimeObject;
		block.times.endTime = lastBlockTime;
		block.times.endTimeObject = lastBlockTimeObject;
		block.totalUsers = totalUsers;
		return block;
	};
	
	
	this.findNormalShifts = function() {
		self.openDays.forEach(function(day) {
			day.forEach(function(block) {
				if(block) {			
					switch(block.block) {
						case 22:
						case 28:
						case 34:
						case 40:
							break;
						default:
							day[block.block] = null;
							break;
						}					}
				});
			});
		};
						
										
	this.displayOpenShifts = function() {
		var openShiftsResults = document.getElementById("openShiftsResults");
		removeElements(openShiftsResults);
		var openingParagraph = "If you have availability, here is when we can most use your help in the next two weeks:";
		var p = document.createElement("p");
		var br = document.createElement("br");
		openShiftsResults.appendChild(document.createTextNode(openingParagraph));
		openShiftsResults.appendChild(br);
		var openShiftsText = "";
		var i = 0;
		var startOfSecondWeek = self.openDays.length - 7;
		var secondWeekStart = false;
		self.openDays.forEach(function(day) {
			var dayParagraph = document.createElement("p");						
			var openShiftToday = false;
			day.forEach(function(block) {
				if(block) {
					if(!openShiftToday) {
						if(i == 0 || (i >= startOfSecondWeek && !secondWeekStart)) {
							var weekHeader = document.createElement("h3");
							weekHeader.style.color = 'blue';
							var startOfWeek = block.date.getWeekStart().getNextDay().shortFormattedDate();
							if(i==0) {							
								weekHeader.appendChild(document.createTextNode("(" + startOfWeek + " to "));
							} else if (i >= startOfSecondWeek) {
								secondWeekStart = true;
								weekHeader.appendChild(document.createTextNode("FOLLOWING WEEK (" + startOfWeek + " to "));
							}
							var endOfWeek = block.date.getWeekStart().getNextDay(6).shortFormattedDate();
							weekHeader.appendChild(document.createTextNode(endOfWeek + ")"));
							openShiftsResults.appendChild(weekHeader);
						}
						var dayHeader = document.createElement("strong");		
						var dateString = block.date.openShiftsFormattedDate();
						dayHeader.appendChild(document.createTextNode(dateString));		
						dayParagraph.appendChild(dayHeader);
						dayParagraph.appendChild(document.createElement("br"));
						openShiftToday = true;
					}
					var startTimeText = block.times.startTimeObject.formattedTime();
					var endTimeText = block.times.endTimeObject.formattedTime();
					easternStartTime = block.times.startTimeObject.addHours(3).formattedTime();;
					easternEndTime = block.times.endTimeObject.addHours(3).formattedTime();;
					var pacificShiftText = startTimeText + "-" + endTimeText + " Pacific ";
					var easternShiftText = "(" + easternStartTime + "-" + easternEndTime + " Eastern)";
					var shiftText = document.createTextNode(pacificShiftText + easternShiftText);

					if(block.totalUsers == 0) {
						var span = document.createElement("span");
						span.style.color = 'red';
						span.appendChild(shiftText)
						var emptyShift = document.createTextNode(" <-- NO ONE SIGNED UP, VOLUNTEER ESPECIALLY NEEDED");
						span.appendChild(emptyShift);
					} else {
						var span = document.createElement("span");
						span.style.color = 'black';
						span.appendChild(shiftText)
					}
					dayParagraph.appendChild(span);								
					dayParagraph.appendChild(document.createElement("br"));

					var numberOfUsers = numberOfNonResourceUsers(block.users);
				}
			});
			if(dayParagraph.childNodes) {
				openShiftsResults.appendChild(dayParagraph);
			}
			i += 1;
		});
	};

		
	
}
