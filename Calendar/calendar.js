var dates = Array();
var schedule = {};
var openHours = {};
var calendarData = {};
var blockArray = Array();
var volunteerID = '';
var fullName = '';
var userType = '';
var updateSchedule = "";

blockArray[0]={startTime: '12:00AM',endTime: '12:30AM'};
blockArray[1]={startTime: '12:30AM',endTime: '1:00AM'};
blockArray[2]={startTime: '1:00AM',endTime: '1:30AM'};
blockArray[3]={startTime: '1:30AM',endTime: '2:00AM'};
blockArray[4]={startTime: '2:00AM',endTime: '2:30AM'};
blockArray[5]={startTime: '2:30AM',endTime: '3:00AM'};
blockArray[6]={startTime: '3:00AM',endTime: '3:30AM'};
blockArray[7]={startTime: '3:30AM',endTime: '4:00AM'};
blockArray[8]={startTime: '4:00AM',endTime: '4:30AM'};
blockArray[9]={startTime: '4:30AM',endTime: '5:00AM'};
blockArray[10]={startTime: '5:00AM',endTime: '5:30AM'};
blockArray[11]={startTime: '5:30AM',endTime: '6:00AM'};
blockArray[12]={startTime: '6:00AM',endTime: '6:30AM'};
blockArray[13]={startTime: '6:30AM',endTime: '7:00AM'};
blockArray[14]={startTime: '7:00AM',endTime: '7:30AM'};
blockArray[15]={startTime: '7:30AM',endTime: '8:00AM'};
blockArray[16]={startTime: '8:00AM',endTime: '8:30AM'};
blockArray[17]={startTime: '8:30AM',endTime: '9:00AM'};
blockArray[18]={startTime: '9:00AM',endTime: '9:30AM'};
blockArray[19]={startTime: '9:30AM',endTime: '10:00AM'};
blockArray[20]={startTime: '10:00AM',endTime: '10:30AM'};
blockArray[21]={startTime: '10:30AM',endTime: '11:00AM'};
blockArray[22]={startTime: '11:00AM',endTime: '11:30AM'};
blockArray[23]={startTime: '11:30AM',endTime: '12:00PM'};
blockArray[24]={startTime: '12:00PM',endTime: '12:30PM'};
blockArray[25]={startTime: '12:30PM',endTime: '1:00PM'};
blockArray[26]={startTime: '1:00PM',endTime: '1:30PM'};
blockArray[27]={startTime: '1:30PM',endTime: '2:00PM'};
blockArray[28]={startTime: '2:00PM',endTime: '2:30PM'};
blockArray[29]={startTime: '2:30PM',endTime: '3:00PM'};
blockArray[30]={startTime: '3:00PM',endTime: '3:30PM'};
blockArray[31]={startTime: '3:30PM',endTime: '4:00PM'};
blockArray[32]={startTime: '4:00PM',endTime: '4:30PM'};
blockArray[33]={startTime: '4:30PM',endTime: '5:00PM'};
blockArray[34]={startTime: '5:00PM',endTime: '5:30PM'};
blockArray[35]={startTime: '5:30PM',endTime: '6:00PM'};
blockArray[36]={startTime: '6:00PM',endTime: '6:30PM'};
blockArray[37]={startTime: '6:30PM',endTime: '7:00PM'};
blockArray[38]={startTime: '7:00PM',endTime: '7:30PM'};
blockArray[39]={startTime: '7:30PM',endTime: '8:00PM'};
blockArray[40]={startTime: '8:00PM',endTime: '8:30PM'};
blockArray[41]={startTime: '8:30PM',endTime: '9:00PM'};
blockArray[42]={startTime: '9:00PM',endTime: '9:30PM'};
blockArray[43]={startTime: '9:30PM',endTime: '10:00PM'};
blockArray[44]={startTime: '10:00PM',endTime: '10:30PM'};
blockArray[45]={startTime: '10:30PM',endTime: '11:00PM'};
blockArray[46]={startTime: '11:00PM',endTime: '11:30PM'};
blockArray[47]={startTime: '11:30PM',endTime: '12:00AM'};


// Universal AjaxRequest Object.  Pass it the url, the parameters to be sent to the server
// the resultsFunction to call when results are received, and the object to be acted upon
// by the resultsFunction (if any).
// The Universal AjaxRequest Object will recreate the object, create the HTTLRequest,
// post the request to the server, and deliver the results to the resultsFunction and
// resultsObject. 


function AjaxRequest(url, params, resultsFunction, resultsObject) {
    if (params) {
        this.params = params;
        this.type = "POST";
        this.url = url;
        this.contentType = "application/x-www-form-urlencoded";
		this.results = resultsFunction;
		this.resultsObject = resultsObject;
		this.createRequest();
		this.process();
    }
}

AjaxRequest.prototype.createRequest = function() {
    try {
        this.xmlHttp = new XMLHttpRequest();
    }
    catch (e) {
        try {
            this.xmlHttp = new ActiveXObject("Microsoft.XMLHttp");
        }
        catch (e) {}
    }

    if (!this.xmlHttp) {
        alert("Error creating XMLHttpRequestObject");
    }
};

AjaxRequest.prototype.process = function() {
    try {
        if (this.xmlHttp) {
            this.xmlHttp.onreadystatechange = this.handleRequestStateChange();
            this.xmlHttp.open(this.type, this.url, true);
            this.xmlHttp.setRequestHeader("Content-Type", this.contentType);
            this.xmlHttp.send(this.params);
        }
	}
	catch (e) {
		document.getElementById("resourceList").innerHTML = "";

		// Show comprehensive error modal
		if (typeof showComprehensiveError === 'function') {
			showComprehensiveError(
				'Connection Error',
				'Unable to connect to server',
				{
					url: this.url,
					params: this.params,
					method: this.type,
					error: e,
					additionalInfo: 'Failed to initiate XMLHttpRequest. The server may be unavailable or there may be a network issue.'
				}
			);
		} else {
			// Fallback to alert if ErrorModal.js is not loaded
			alert("Unable to connect to server.\n\nURL: " + this.url + "\nError: " + e.message);
		}
	}
};


AjaxRequest.prototype.handleRequestStateChange = function() {
    var self = this;

    return function() {
        try {
            if (self.xmlHttp.readyState == 4 && self.xmlHttp.status == 200) {
                self.results(self.xmlHttp.responseText, self.resultsObject);
            }
        }
        catch (e) {
            alert(e);
        } 
    };
};


Date.prototype.getMonthName = function() {
	var m = ['January','February','March','April','May','June','July',
	'August','September','October','November','December'];
	return m[this.getMonth()];
} 

Date.prototype.getDayName = function() {
	var d = ['Sunday','Monday','Tuesday','Wednesday',
	'Thursday','Friday','Saturday'];
	return d[this.getDay()];
}

Date.prototype.getMonthStart = function() {
	var d = new Date(this.getMonth() + 1 + "/1/" + this.getFullYear()); 
	return d
}


Date.prototype.getNextDay = function() {
	var d = new Date(this); 
	var dayOfMonth = d.getDate();
	d.setDate(dayOfMonth + 1);
	return d
}

Date.prototype.getPriorDay = function(days) {
	if(!days) {
		var days = 1;
	}
	var d = new Date(this); 
	var dayOfMonth = d.getDate();
	d.setDate(dayOfMonth - days);
	return d
}


Date.prototype.getMonthEnd = function() {
	var month = this.getMonth() + 2;
	if(month > 12) {
		month = month - 12;
		var year = this.getFullYear() + 1;
	} else {
		var year = this.getFullYear();
	}
	var dateString = month + "/1/"+ year;
	var d = new Date(month + "/1/"+ year); 
	var dayOfMonth = d.getDate();
	d.setDate(dayOfMonth - 1);
	return d
}


function updateScheduleRoutine() {
	clearInterval(updateSchedule);
	updateSchedule = setInterval(function() {
		schedule.getSchedule();
		schedule.getHours();
		schedule.getScheduleChanges();
	}, 10000);
}


window.onload = function() {
	var dateBox = document.getElementById('calendarStartDate');	
	var previousMonthButton = document.getElementById("previousMonthButton");
	previousMonthButton.style.visibility = 'hidden';


	volunteerID = document.getElementById('volunteerID').value;
	fullName = document.getElementById('fullName').value;
	userType = document.getElementById('userType').value;
	var exitButton = document.getElementById('exitCalendarPage');
	exitButton.onclick=function() {
		window.onbeforeunload = null; // Disable unload handler for intentional exit
		// Exit Calendar Only mode (reset LoggedOn from 10 to 0)
		var params = "postType=exitCalendar";
		var xhr = new XMLHttpRequest();
		xhr.open("POST", "../volunteerPosts.php", false); // synchronous to ensure it completes
		xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
		xhr.send(params);
		window.location = '../index.php';
	}

	// Handle browser close/navigation - reset Calendar Only status
	window.onbeforeunload = function() {
		var data = new FormData();
		data.append('postType', 'exitCalendar');
		navigator.sendBeacon('../volunteerPosts.php', data);
	};

	var nextButton = document.getElementById('nextMonthButton');
	var previousButton = document.getElementById('previousMonthButton');
	nextButton.onclick = function() {
		monthChange('next');
	}
	previousButton.onclick = function() {
		monthChange('previous');
	}

	schedule = new GetVolunteerSchedule();
	schedule.init();	
	
	
	var date = new Date();
	newDateBoxValue(date);
	var newCalendarButton = document.getElementById('newCalendarButton');
	newCalendarButton.onclick = function() {newCalendar(dateBox.value);};
	dateBox.onchange = function() {newCalendar(dateBox.value);};
	
	
	var messageArea = document.getElementById('messageArea');
	if(messageArea.firstChild.nodeValue.length > 0) {
			messageArea.style.display = "block";
	} else {
		messageArea.style.display = null;
	}
}
	
	
function newDateBoxValue(newDate) {
	var dateBox = document.getElementById('calendarStartDate');
	dateBox.value = newDate.getMonthName() + " " + newDate.getDate() + ", " + newDate.getFullYear();
	var newCalendarButton = document.getElementById('newCalendarButton');
	newCalendarButton.click();
}	
	
function monthChange(direction) {
	var dateBoxValue = document.getElementById('calendarStartDate').value;
	var previousMonthButton = document.getElementById("previousMonthButton");
	var nextMonthButton = document.getElementById("nextMonthButton");
	var newDate = new Date(dateBoxValue);
	if(direction == "next") {
		moveToDate = newDate.getMonthEnd();
		moveToDate = moveToDate.getNextDay();
		var todaysDate = new Date();
		var todaysMonth = todaysDate.getMonth();
		var moveToMonth = moveToDate.getMonth();
		if (moveToMonth - todaysMonth < 2) {
			newDateBoxValue(moveToDate);
			nextMonthButton.style.visibility = 'hidden';
			previousMonthButton.style.visibility = null;
		}
	}
	if(direction == "previous") {
		moveToDate = newDate.getMonthStart();
		moveToDate = moveToDate.getPriorDay();		
		var todaysDate = new Date();
		var todaysMonth = todaysDate.getMonth();
		var moveToMonth = moveToDate.getMonth();
		if (moveToMonth == todaysMonth) {
			newDateBoxValue(moveToDate);
			nextMonthButton.style.visibility = null;
			previousMonthButton.style.visibility = 'hidden';
		}
	}
}

	
function startCalendar() {
	var newCalendarButton = document.getElementById('newCalendarButton');
	newCalendarButton.click();
}
	
	
function fromMysqlDate(timestamp) {
	var dateParts = timestamp.split("-");
	var jsDate = new Date(dateParts[0], dateParts[1] - 1, dateParts[2].substr(0,2));
	
	return jsDate;
}			


function toMysqlDate(currentDate) {
	var currentDate = new Date(currentDate);
	var twoDigitMonth = (currentDate.getMonth() + 1 < 10 ? '0' : '') + (currentDate.getMonth() + 1);
	var twoDigitDate = (currentDate.getDate() < 10 ? '0' : '') + currentDate.getDate();
	var mysqlDate = currentDate.getFullYear() + "-" + twoDigitMonth + "-" + twoDigitDate;
	
	return mysqlDate;
}

	
function GetVolunteerSchedule() {
	this.scheduleData = {};
	this.scheduleChangesData = {};
	this.hoursData = [];
	this.params = 		'None';
	this.scheduleUrl = 			'getVolunteerSchedule.php';
	this.hoursUrl = 			'openHours.php';
	this.updating = false;

	
	this.resultFunctionSchedule = function (results, resultObject) {
		if(!resultObject.results) {
			resultObject.scheduleData = {};
			resultObject.scheduleData = JSON.parse(results);
		}
	};

	this.resultFunctionScheduleChanges = function (results, resultObject) {
		if(!resultObject.results) {
			schedule.scheduleChangesData = {};
			var tempScheduleChangesData = JSON.parse(results);
			for (mysqslDate in tempScheduleChangesData) {
				var record = tempScheduleChangesData[mysqslDate];
				var currentDate = fromMysqlDate(mysqslDate);
				schedule.scheduleChangesData[currentDate] = record;
			}
		}
		var dateBox = document.getElementById('calendarStartDate').value;
		var loadDate = new Date(dateBox);
		loadCalendarData(loadDate);
		updateCalendar();
		startCalendar();
	};

	this.resultFunctionHours = function(results,resultObject) {
		if(!resultObject.results) {
			resultObject.hoursData = JSON.parse(results);
		}
		
	};		
	this.init = function() {
		this.getSchedule();
		this.getHours();
		this.getScheduleChanges();
	};
	
	this.getSchedule = function() {
		searchRequest = new AjaxRequest(this.scheduleUrl, this.params, this.resultFunctionSchedule, this);
	};
	
	this.getScheduleChanges = function() {
		if (schedule.updating) {
			return;
		}
		var params = 'changes=true';
		searchRequest = new AjaxRequest(this.scheduleUrl, params, this.resultFunctionScheduleChanges, this);
	};

	this.getHours = function() {
		searchRequest2 = new AjaxRequest(this.hoursUrl, this.params, this.resultFunctionHours, this);
	
	};
}	
	
	
function removeElements(element) {
	while(calendarTitle.hasChildNodes()) {     
		calendarTitle.removeChild(calendarTitle.childNodes[0]);
	}
	while(element.hasChildNodes()) {     
		element.removeChild(element.childNodes[0]);
	}
}

function remove(id) {
	var parent = id.parentNode;
	var elem = parent.removeChild(id);
}


function newCalendar(enteredDate) {

	var calendarTitle=document.getElementById('calendarTitle');
	var calendar=document.getElementById('calendar');

	removeElements(calendar);

	var calendarMonthHeader = document.getElementById('calendarMonthHeader');
	calendarMonthHeader.style.visibility = null;

	var currentDate = new Date(enteredDate); 
	if(currentDate == "Invalid Date") {
		alert("Invalid Date.  Please enter a valid date.");
		this.value = "";
	}
	
	loadCalendarData(currentDate);

	
	var calendarTitle = document.getElementById("calendarTitle");
	calendarTitle.appendChild(document.createTextNode(currentDate.getMonthName() + " " + currentDate.getFullYear()));
	var calendar = document.getElementById("calendar");
	calendar.setAttribute('class','calendarMonth');
	var calendarMonth = new CalendarMonth(currentDate);	
	calendar.appendChild(calendarMonth);
	updateCalendar();

}		


function loadCalendarData(enteredDate) {
	calendarData = {};
	var firstOfMonth = enteredDate.getMonthStart();
	var lastOfMonth = enteredDate.getMonthEnd();
	var daysInMonth = (lastOfMonth - firstOfMonth) / (24*60*60*1000) + 1;
	var currentDate = firstOfMonth;
	

	//Set Up Entire Array to Ensure All Displayed Days Will Be Covered
	var dateLoadingStart = firstOfMonth;
	for (var i = 0 ; i < daysInMonth ; i++) {
		calendarData[dateLoadingStart] = {};
		for (var j = 0 ; j < blockArray.length ; j++) {
			calendarData[dateLoadingStart][j] = {};
		}
		var newDateLoadingStart = dateLoadingStart.getNextDay();
		dateLoadingStart = newDateLoadingStart;
	}
			

	for (var i=0;i<daysInMonth;i++) {
		var dayOfWeek = currentDate.getDay();
		for(blockNumber in schedule.scheduleData[dayOfWeek]) {	
			blockData = schedule.scheduleData[dayOfWeek][blockNumber];
			for(key in blockData) {
				var user = {};
				user = blockData[key];
				calendarData[currentDate][blockNumber][user.UserName] = user;
			}
		}			

		var mysqlDate = toMysqlDate(currentDate);


//		var dateToChange = new Date("04/15/2015");		
//		calendarData[dateToChange] = "Loaded";

		if(schedule.scheduleChangesData[currentDate]) {
			for (blocks in schedule.scheduleChangesData[currentDate]) {
				var block = schedule.scheduleChangesData[currentDate][blocks];
				block.forEach(function(blockData) {
					var blockNumber = blockData.Block;
					var UserName = blockData.UserName;
					if(blockData.Type == "Add") {
						if(!calendarData[currentDate]) {
							calendarData[currentDate] = {};
						}					
						if(!calendarData[currentDate][blockNumber]) {
							calendarData[currentDate][blockNumber] = {};						
						}
						if(!calendarData[currentDate][blockNumber][blockData.UserName]) {
							calendarData[currentDate][blockNumber][blockData.UserName] = {};
							calendarData[currentDate][blockNumber][blockData.UserName] = blockData;
						}
					} else if(blockData.Type == 'Delete') {
						if(calendarData[currentDate][blockNumber][blockData.UserName]) {
							delete(calendarData[currentDate][blockNumber][blockData.UserName]);
						}
					}
				});
			}
		}
		var newCurrentDate = currentDate.getNextDay();
		currentDate = newCurrentDate;
	}
}


function updateCalendar() {
	if (schedule.updating) {
		return;
	}
	var calendar = document.getElementById("calendar");
	var calendarDisplay = calendar.childNodes;
	for (var i=0 ; i < calendarDisplay.length ; i++) {
		var week = calendarDisplay[i];
		var days = week.childNodes;
		for (var j=0 ; j < days.length ; j++) {
			var day = days[j];
			var dayDate = new Date(day.id);
			var blocks = day.childNodes;
			for (var k=0 ; k < blocks.length ; k++) {
				var block = blocks[k];
				if(block.class = 'calendarDayBlockArea') {
					var blockArea = block;
					var dayBlocks = blockArea.childNodes;
					if(dayBlocks.length > 1) {
						for (var l=0 ; l < dayBlocks.length ; l++) {
							var singleBlock = dayBlocks[l];
							var blockid = singleBlock.id.split(".");
							var blockNumber = blockid[1];
							var usersDisplayed = singleBlock.getElementsByTagName('div');

							for (var m=0 ; m < usersDisplayed.length ; m++) {
								var user = usersDisplayed[m];
								var userid = user.id.split(".");
								var userName = userid[0];
								if(!calendarData[day.id][blockNumber][userName]) {
									remove(user);
								}
							}
							if(day.id != 'blankDay') {
								var dayBlockPresent = calendarData[day.id][blockNumber];
								if(dayBlockPresent) {
									for (userNameKey in calendarData[day.id][blockNumber]) {
										var userSignedUp = calendarData[day.id][blockNumber][userNameKey];
										var displayed = false;
										for (var n = 0 ; n < usersDisplayed.length ; n++ ) {
											var userDisplayed = usersDisplayed[n];
											var userDisplayedID = userDisplayed.id.split(".")[0];
											if (userDisplayedID == userNameKey) {
												displayed = true;
											}
										}
										if(!displayed) {
											var userToAdd = new UserBlock(userSignedUp.FullName, "", userSignedUp, singleBlock);
											singleBlock.appendChild(userToAdd); 
//												alert("User Not Displayed");
										}
									}
								}
							}
						}
					}
				} 
			}
		}
	}
}


function CalendarMonth(startDate) {
	var calendar = document.createDocumentFragment();
	var firstOfMonth = startDate.getMonthStart();
	var lastOfMonth = startDate.getMonthEnd();
	var weekStart = firstOfMonth;
	
	var numberOfCalandarDaysNeeded = firstOfMonth.getDay() + lastOfMonth.getDate();
	var numberOfCalendarWeeksNeeded = Math.round( (numberOfCalandarDaysNeeded / 7) + .49);

	for(var i = 0 ; i < numberOfCalendarWeeksNeeded ; i++) {
		var calendarWeek = new CalendarWeek(weekStart, startDate);
		calendar.appendChild(calendarWeek);
	}
	
	return calendar;	

}


function CalendarWeek(weekStartDate, startDate) {
	var originalStartDate = new Date(weekStartDate);
	var calendar = document.createDocumentFragment();
	var calendarWeek = document.createElement("div");
	calendarWeek.setAttribute('id',weekStartDate);
	calendarWeek.setAttribute('class','calendarWeek');
	for(var i = 0; i < 7; i++){
		var dayOfMonth = weekStartDate.getDate();
		var dayOfWeek = weekStartDate.getDay();
		if(dayOfWeek > i) {
			var calendarDay = new CalendarDay(weekStartDate,true, startDate);
		} else if(originalStartDate.getMonth() != weekStartDate.getMonth()) {
			var calendarDay = new CalendarDay(weekStartDate,true, startDate);
		} else {
			var calendarDay = new CalendarDay(weekStartDate,false, startDate);
			weekStartDate.setDate(dayOfMonth + 1);
		}			
		calendarWeek.appendChild(calendarDay);
	}
	calendar.appendChild(calendarWeek);
	return calendar;

}



function CalendarDay(blockDate, blankDay, startDate, type) {
	var calendarDay = document.createElement("div");
	calendarDay.style.display = "inline-block";

	if(blankDay) {
		calendarDay.setAttribute('id','blankDay');
	} else {
		calendarDay.setAttribute('id',blockDate);
	}
	if(type != 'singleDay') {
		calendarDay.setAttribute('class','calendarDay');
	} else {
		calendarDay.setAttribute('class','calendarDaySingleDay');
	}
	var calendarDayToken = document.createElement("div");
	var calendarDayBlockArea = document.createElement("div");
	calendarDayToken.setAttribute('class','calendarDateToken');
	if(type != 'singleDay') {
		calendarDayBlockArea.setAttribute('class','calendarDayBlockArea');
	} else {
		calendarDayBlockArea.setAttribute('class','calendarDayBlockAreaSingleDay');
	}
	if(blankDay || type == 'singleDay') {
		var dateNumber = "";
		calendarDayToken.style.visibility = 'hidden';
	} else {
		var dateNumber = blockDate.getDate();
		var calendarDayTokenClickDate = new Date(startDate.getMonth() + 1 + "/" + dateNumber + "/" + startDate.getFullYear());
		calendarDayToken.onclick = function() {singleDay(calendarDayTokenClickDate);};
	}	
	
	calendarDayToken.appendChild(document.createTextNode(dateNumber));
	var highlightDay = startDate.getDate();
	if(dateNumber == highlightDay) {
		calendarDayToken.style.backgroundColor = 'green';
	}
	calendarDay.appendChild(calendarDayToken);

	var dayNumber = blockDate.getDay();
	var dayHeader = document.getElementById("dayHeader" + dayNumber);
	dayHeader.style.display = 'inline-block';
	if(schedule.hoursData[dayNumber]) {
		var blockStart = schedule.hoursData[dayNumber]["StartTime"] * 1;
		var blockEnd = schedule.hoursData[dayNumber]["EndTime"] * 1;
			
		for(var i = blockStart; i < blockEnd; i++){
			var testBlock = new ShiftBlock(i,blockDate, blankDay, type);
			calendarDayBlockArea.appendChild(testBlock);
		}
	} 
	calendarDay.appendChild(calendarDayBlockArea);
	return calendarDay;
}


function results(results) {
//	alert(results);
}


function ShiftBlock(blockNumber, blockDate, blankDay, type) {
	self = this;
	this.blockNumber = blockNumber;
	this.blockDate = blockDate;
	var block = document.createElement('div');
	if(type != 'singleDay') {
		block.setAttribute('class','shiftBlock');
	} else {
		block.setAttribute('class','shiftBlockSingleDaySingleDay');
	}
	block.id=this.blockDate + "." + this.blockNumber;
	block.onclick = function(event) {
		var existingDeleteMenu = document.getElementById("deleteBlockMenu");
		if (existingDeleteMenu) {
			remove(existingDeleteMenu);
			document.onclick = null;
			return;
		}
		schedule.updating = true;
		updateScheduleRoutine();
		var data = this.id.split(".");
		var date = toMysqlDate(data[0]);
		var clickedDate = new Date(data[0]);
		var d = new Date();
		var today = d.getPriorDay();
		blockUsersPresent = block.childNodes.length - 1;

		if(today > clickedDate) {
			alert("Cannot edit past schedule information.");
			return;
		} 
		
		if(!calendarData[clickedDate] || !calendarData[clickedDate][blockNumber] || !calendarData[clickedDate][blockNumber][volunteerID]) {
			if(blockUsersPresent >=3) {
				alert("This time slot is full.  Please select another time to volunteer.");
				return;
			}
			schedule.updating = true;
			var type = 'Add';
			params = "date=" + date + "&type=" + type + "&block=" + data[1] + "&volunteerID=" + volunteerID;  
			var results = function(results, resultObject) {schedule.updating = false;};
			var user = {};
			user.Block = blockNumber;
			user.FullName = fullName;
			user.UserName = volunteerID;
			calendarData[clickedDate][blockNumber][volunteerID] = user;
			var userBlock = new UserBlock(fullName, userType, user, block);
			block.appendChild(userBlock);

			searchRequest2 = new AjaxRequest("insertChange.php", params, results, this);

		} else {
			selectMenu(event, volunteerID, clickedDate, data[1], block);
//			deleteUserShift(volunteerID, clickedDate, data[1], block)
//			var type = 'Delete';
//			var elementID = volunteerID + "." + clickedDate + "." + blockNumber;
//			var element = document.getElementById(elementID);
//			calendarData[clickedDate][blockNumber][volunteerID] = {};
//			remove(element);
		}

	};
	if(!blankDay) {
		var span = document.createElement("span");
		span.setAttribute('class','timeBlock');
		span.appendChild(document.createTextNode(blockArray[blockNumber].startTime));
		span.title = blockArray[blockNumber].startTime + "-" + blockArray[blockNumber].endTime;
		block.appendChild(span);
		if(calendarData[blockDate]) {
			if(calendarData[blockDate][blockNumber]) {
				var currentBlock = calendarData[blockDate][blockNumber];
				for (users in currentBlock) {
					var user = currentBlock[users];
//					var userBlock = new UserBlock(user.FullName, type, user);
//					block.appendChild(userBlock);
				}
			}
		}
	} else {
		block.style.visibility = 'hidden';
	}
	if(type == 'singleDay') {
		block.setAttribute('class','shiftBlockSingleDay');
	}
	return block;
}



function getClickPosition(e) {
	var parentPosition = getPosition(e.currentTarget);
	var xPosition = e.clientX;
	var yPosition = parentPosition.y;

	return { x: xPosition, y: yPosition };
}


function getPosition(element) {
	var xPosition = 0;
	var yPosition = 0;
	 
	while (element) {
		xPosition += (element.offsetLeft + element.clientLeft);
		yPosition += (element.offsetTop + element.clientTop);
		element = element.offsetParent;
	}
	return { x: xPosition, y: yPosition };	
}


function selectMenu(event, volunteerID, clickedDate, blockNumber, block) {
	var existingDeleteMenu = document.getElementById("deleteBlockMenu");
	if (existingDeleteMenu) {
		remove(existingDeleteMenu);
	}
	var position = getClickPosition(event);
    var x = position.x;
    var y = position.y;
    
	var body = document.getElementsByTagName("body")[0];    

	var select = document.createElement("div");
	var h1 = document.createElement("h3");
	h1.appendChild(document.createTextNode("Delete: "));
	select.appendChild(h1);
	select.id = "deleteBlockMenu"; 			
	var option = document.createElement("input");
	option.type = 'button';
	option.value = "Entire Shift";
	option.style.padding = "5px";
	option.onclick = function() {
		deleteUserShift(volunteerID, clickedDate, blockNumber, block);
		remove(select);
		document.onclick = null;

	} 
	select.appendChild(option);
	var option = document.createElement("input");
	option.style.padding = "5px";
	option.type = 'button';
	option.value = "Single Block";
	option.onclick = function() {
		deleteSingleUserBlock(volunteerID, clickedDate, blockNumber, block);
		remove(select);
		document.onclick = null;
	} 

	select.appendChild(option);
	select.position = 'absolute';
	select.style.top = y + 'px';
    select.style.left = x + 'px';
    select.style.height = '100px';
    select.style.lineHeight = "20px";
    select.style.width = '120px';
    select.style.position = 'absolute';
    select.style.border = "3px solid black";
    select.style.borderRadius = "10px";
    select.style.background = "silver";
    select.style.textAlign = "center";
	select.style.boxShadow = "10px 10px 10px black";

    select.style.zIndex = '100000';
    select.backgroundColor = 'green';
	body.appendChild(select);      
	var outsideClickedNumber = 0;
	document.onclick = function() {
		outsideClickedNumber += 1;
		if(outsideClickedNumber > 1) {
			if(clickedOutsideElement(select.id)) {
				remove(select);
				document.onclick = null;
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



function deleteUserShift(volunteerID, clickedDate, blockNumber, block) {
	schedule.updating = true;
	var loopContinue = true;
	var firstBlock = null;
	blockNumber = Number(blockNumber);
	
	// Find earliest block in User's Shift
	while (loopContinue) {
		var elementID = volunteerID + "." + clickedDate + "." + blockNumber;
		var element = document.getElementById(elementID);
		if (element) {
			firstBlock = blockNumber;
			blockNumber = blockNumber - 1;
		} else {
			loopContinue = false;
		}
	}

	// Delete blocks from User's Shift
	loopContinue = true;
	var blockNumber = firstBlock;
	while (loopContinue) {
		var elementID = volunteerID + "." + clickedDate + "." + blockNumber;
		var element = document.getElementById(elementID);
		if (element) {
			remove(element);
			calendarData[clickedDate][blockNumber][volunteerID] = null;
			var date = toMysqlDate(clickedDate);
			params = "date=" + date + "&type=" + "Delete" + "&block=" + blockNumber + "&volunteerID=" + volunteerID;  
			var results = function(results, resultObject) {schedule.updating = false;};
			searchRequest2 = new AjaxRequest("insertChange.php", params, results, this);
			blockNumber = blockNumber + 1;
		} else {
			loopContinue = false;
		}
	}
}

function deleteSingleUserBlock(volunteerID, clickedDate, blockNumber, block) {
	var elementID = volunteerID + "." + clickedDate + "." + blockNumber;
	var element = document.getElementById(elementID);
	remove(element);
	calendarData[clickedDate][blockNumber][volunteerID] = null;
	var date = toMysqlDate(clickedDate);
	params = "date=" + date + "&type=" + "Delete" + "&block=" + blockNumber + "&volunteerID=" + volunteerID;  
	var results = function(results, resultObject) {schedule.updating = false;};
	searchRequest2 = new AjaxRequest("insertChange.php", params, results, this);	
}


function UserBlock(userName, type, user, parent) {
	self = this;
	var userInitials = userName.split(" ");
	userInitials[0] = userInitials[0].substr(0,1);
	userInitials[1] = userInitials[1].substr(0,1);
	this.userName = userName;
	var userBlock = document.createElement('div');
	userBlock.id=user.UserName + "." + parent.id;
//	userBlock.onclick = function() {alert(this.id + "--" + this.parentNode.id);};
	if(type != 'singleDay') {
		userBlock.appendChild(document.createTextNode(userInitials[0] + userInitials[1]));
		userBlock.setAttribute('class','userBlock');
	} else {
		userBlock.appendChild(document.createTextNode(userName));
		userBlock.setAttribute('class','userBlockSingleDay');
	}
	userBlock.title = userName;
	return userBlock;
}	


function singleDay(date) {
	var calendar = document.getElementById('calendar');
	removeElements(calendar);
	var calendarMonthHeader = document.getElementById('calendarMonthHeader');
	calendarMonthHeader.style.visibility = 'hidden';
	var dayOfMonth = date.getDate();
	var weekStart = new Date();
	weekStart.setDate(dayOfMonth - date.getDay());

	
	var calendarTitle = document.getElementById("calendarTitle");
	calendarTitle.appendChild(document.createTextNode(date.getMonthName() + " " + date.getDate() + ", " + date.getFullYear()));
	var calendar = document.getElementById("calendar");
	calendar.setAttribute('class','calendarSingleDay');
	var singleDay = CalendarDay(date, false, weekStart,'singleDay');
	calendar.appendChild(singleDay);
	
}




