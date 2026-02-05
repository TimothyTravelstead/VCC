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


Date.prototype.getNextDay = function(days) {
	if(!days) {
		var days = 1;
	}
	var d = new Date(this); 
	var dayOfMonth = d.getDate();
	d.setDate(dayOfMonth + days);
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


Date.prototype.getNextMonth = function() {
	var d = new Date(this); 
	var nextMonth = d.getMonthEnd();
	var e = nextMonth.getNextDay();
	return e;
}

Date.prototype.getPriorMonth = function() {
	var d = new Date(this); 
	var priorMonth = d.getMonthStart();
	var e = priorMonth.getPriorDay();
	return e;
}

Date.prototype.formattedDate = function() {
	var monthName = this.getMonthName();
	var date = this.getDate();
	var year = this.getFullYear();
	var formattedDate = monthName + " " + date + ", " + year;
	return formattedDate;
}

Date.prototype.openShiftsFormattedDate = function() {
	var shortFormat = this.shortFormattedDate();
	var date = this.getDate();
	var dayOfWeek = this.getDayName();
	var formattedDate = dayOfWeek + ", " + shortFormat;
	return formattedDate;
}


Date.prototype.shortFormattedDate = function() {
	var monthName = this.getMonthName();
	var date = this.getDate().toString();
	var lastDigit = date.substr(date.length - 1);
	switch(lastDigit) {
		case '1':
			var ending = "st";
			break;
		case '2':
			var ending = "nd";
			break;
		case '3':
			var ending = "rd";
			break;
		default:
			var ending = "th";
			break;
	}
	if(date > 9 && date < 21) {
		ending = "th";
	}
	var formattedDate = monthName + " " + date + ending;
	return formattedDate;
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

Date.prototype.addHours= function(h){
    this.setHours(this.getHours()+h);
    return this;
}


Date.prototype.getWeekStart = function() {
	var day = this.getDay();
	var date = this.getPriorDay(day);
	return date;
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