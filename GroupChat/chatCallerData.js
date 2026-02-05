

function fingerprintTest(ipAddress, userID) {
 	var options = {};
	var fingerprintResult = Fingerprint2.get(options, function (components) {
		var values = components.map(function (component) { return component.value });
		var result = Fingerprint2.x64hash128(values.join(''), 31);
		processResult(result, ipAddress, userID);
	});			
	
}



function recordCallerData(ipAddress, userID) {
 	var params = "UniqueCallerID=" + escape(ipAddress) + "&userID=" + userID;
	var url= "recordBrowserData.php";
	var responseObject = {};
	var browserDataRequest = new AjaxRequest(url, params, browserResults , responseObject);
	result = {};
	cancelId = setTimeout(fingerprintTest(ipAddress, userID), 500);
	cancelFunction = clearTimeout;
}


function processResult(result, ipAddress, userID) {
 	var params = "UniqueCallerID=" + escape(result) + "&userID=" + userID;
	var url= "recordBrowserData.php";
	var responseObject = {};
	var browserDataRequest = new AjaxRequest(url, params, browserResults , responseObject);
}

function browserResults(results, resultObject) {
	if(results != "OK") {
		alert(results);
	}
}

	