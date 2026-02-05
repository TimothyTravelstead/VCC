function toMysqlDate(currentDate) {
    var currentDate = new Date(currentDate);
    var twoDigitMonth = (currentDate.getMonth() + 1 < 10 ? '0' : '') + (currentDate.getMonth() + 1);
    var twoDigitDate = (currentDate.getDate() < 10 ? '0' : '') + currentDate.getDate();
    var mysqlDate = currentDate.getFullYear() + "-" + twoDigitMonth + "-" + twoDigitDate;

    return mysqlDate;
}

function refresh() {
    var refresh = document.getElementById("refresh");
    refresh.click();
}

Date.prototype.getNextDay = function(days) {
    if(!days) {
        var days = 1;
    }
    var d = new Date(this); 
    var dayOfMonth = d.getDate();
    d.setDate(dayOfMonth + days);
    return d;
}

window.onload = function() {

    var timeLineButton = document.getElementById("timeLineButton");
    var refresh = document.getElementById("refresh");
    var dateElement = document.getElementById("date");
    var backElement = document.getElementById("backButton");
    var nextElement = document.getElementById("nextButton");
    var printButton = document.getElementById("printButton");
    var averagesButton = document.getElementById("averagesButton");
    var printFlag = document.getElementById("printFlag").value;

    // Refresh every minute if not in print mode
    if(printFlag != 1) {
        timerInterval = setInterval("refresh()",60000);
    }

    printButton.onclick = function() {
        window.print();
    }

    var date = document.getElementById("date").value;
    if(!dateElement.value) {
        dateElement.value = new Date().toLocaleDateString();
    } 

    // Timeline button
    timeLineButton.onclick = function() {
        var date = document.getElementById("date").value;
        window.open("timeline.php?date=" + toMysqlDate(date) + "&cb=" + new Date().getTime());  // Added cache buster
    }

    // Refresh button
    refresh.onclick = function() {
        var date = document.getElementById("date").value;
        var aLink = document.createElement("a");
        aLink.href = "newStats.php?date=" + toMysqlDate(date) + "&cb=" + new Date().getTime();  // Added cache buster
        var body = document.getElementsByTagName("body")[0];
        body.appendChild(aLink);
        aLink.click();
    }

    // Averages button
    averagesButton.onclick = function() {
        var aLink = document.createElement("a");
        aLink.href = "newStatsAverages.php?startDate=" + toMysqlDate(date) + "&endDate=" + toMysqlDate(date) + "&cb=" + new Date().getTime();  // Added cache buster
        var body = document.getElementsByTagName("body")[0];
        body.appendChild(aLink);
        aLink.click();
    }

    dateElement.onchange = function() {
        refresh.click();
    }    

    if(printFlag == 1) {
        window.print();    
    }

    function moveDays(days) {
        var date = document.getElementById("date").value;
        var date = new Date(date);
        var newDate = date.getNextDay(days);
        dateElement.value = newDate.toLocaleDateString();
        var refresh = document.getElementById("refresh");
        refresh.click();
    }

    // Back and next buttons
    backElement.onclick = function() {
        moveDays(-1);
    }

    nextElement.onclick = function() {
        moveDays(1);
    }

}