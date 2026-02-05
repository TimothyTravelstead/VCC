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



function recordCallerData(ipAddress) {
 
 	
	var fingerprintTest = new Fingerprint2().get(function(result, components){
		this.result = result;
		
		if(!result) {
			result = ipAddress;
		}
		processResult(result);
//		console.log(components); // an array of FP components
	});			


}


function processResult(result) {
	var CallerID = document.getElementById("CallerID").value;
	var referrer = document.getElementById("referringPage").value;

    browserDataRequest = createRequest();

  	
	var url= "recordBrowserData.php?UniqueCallerID=" + escape(result) + "&CallerID=" + escape(CallerID) + "&referrer=" + escape(referrer);
 	browserDataRequest.open("GET", url, true);
	browserDataRequest.onreadystatechange = browserResults;
 	browserDataRequest.send();

}

function browserResults() {
	if (browserDataRequest.readyState == 4) {
    	if (browserDataRequest.status == 200) {
    		var response = browserDataRequest.responseText;
			response = response.trim();
//			alert(response);
		}
	}
}

	