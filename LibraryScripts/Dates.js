

//Date Prototype Functions

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


Date.prototype.createFromMysql = function(mysql_string)
{ 
   var t, result = null;

   if( typeof mysql_string === 'string' )
   {
      t = mysql_string.split(/[- :]/);

      //when t[3], t[4] and t[5] are missing they defaults to zero
      result = new Date(t[0], t[1] - 1, t[2], t[3] || 0, t[4] || 0, t[5] || 0);          
   }

   return result;   
}


Date.prototype.toMysqlDate = function() {

	var date = this.getFullYear() + '-' +
    ('00' + (this.getMonth()+1)).slice(-2) + '-' +
    ('00' + this.getDate()).slice(-2) + ' ' + 
    ('00' + this.getHours()).slice(-2) + ':' + 
    ('00' + this.getMinutes()).slice(-2) + ':' + 
    ('00' + this.getSeconds()).slice(-2);

	return date;
}

Date.prototype.getDayStart = function() {
	var date = new Date(this.getFullYear(), this.getMonth(), this.getDate());
	return date;
}


Date.prototype.addHours= function(h){
    this.setHours(this.getHours()+h);
    return this;
}


Date.prototype.addMinutes = function(minutes) {
   var date = new Date(this.getTime() + minutes*60000);
    return date;
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


function mysqlToTimestamp(mysqlDateTime) {
    // Check if the input includes a time part
    const [datePart, timePart] = mysqlDateTime.split(" ");

    if (!datePart) {
        throw new Error("Invalid MySQL date or datetime format.");
    }

    // Parse the date part
    const dateParts = datePart.split("-");
    if (dateParts.length !== 3) {
        throw new Error("Invalid MySQL date format. Expected format: YYYY-MM-DD.");
    }

    const year = parseInt(dateParts[0], 10);
    const month = parseInt(dateParts[1], 10) - 1; // JavaScript months are 0-based
    const day = parseInt(dateParts[2], 10);

    let hours = 0, minutes = 0, seconds = 0;

    // If a time part is provided, parse it
    if (timePart) {
        const timeParts = timePart.split(":");
        if (timeParts.length >= 2) {
            hours = parseInt(timeParts[0], 10);
            minutes = parseInt(timeParts[1], 10);
            seconds = timeParts[2] ? parseInt(timeParts[2], 10) : 0;
        } else {
            throw new Error("Invalid MySQL time format. Expected format: HH:mm:ss.");
        }
    }

    // Create a Date object
    const date = new Date(year, month, day, hours, minutes, seconds);

    // Return the timestamp
    return date;
}

// Example usage
const mysqlDateOnly = "2024-12-31";
const mysqlDateTime = "2024-12-31 15:45:30";

console.log("Timestamp (date only):", mysqlToTimestamp(mysqlDateOnly));
console.log("Timestamp (datetime):", mysqlToTimestamp(mysqlDateTime));
