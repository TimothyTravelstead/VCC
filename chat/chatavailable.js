window.onload = function() {
	initPage();
}



function initPage() {
//	Browsers = new WhichBrowser({
//		useFeatures:		true,
//		detectCamouflage:	true
//	});

	groupChatTransferFlag = document.getElementById('groupChatTransferFlag').value;
	groupChatCallerId = document.getElementById('groupChatCallerId').value;

	var callerBrowser = ""; //Browsers.browser.name;
	var callerBrowserVersion = ""; //Browsers.browser.version.original;
	var callerOS = ""; //Browsers.os.name;
	var callerOSVersion = ""; //Browsers.os.version.original;
	var callerBrowserDetail = navigator.userAgent;

    browserDataRequest = createRequest();
  	if (request == null) {
    	if (typeof showComprehensiveError === 'function') {
    		showComprehensiveError(
    			'Chat Request Error',
    			'Unable to create request',
    			{
    				additionalInfo: 'Failed to initialize chat browser data request. Your browser may not support XMLHttpRequest. Please try a modern browser (Chrome, Firefox, Safari, Edge).'
    			}
    		);
    	} else {
    		alert("Unable to create request");
    	}
    	return;
  	}
	var url= "ChatBrowserData.php?callerBrowser=" + escape(callerBrowser) + "&callerBrowserVersion=" + escape(callerBrowserVersion) + 
			"&callerOS=" + escape(callerOS) + "&callerOSVersion=" + escape(callerOSVersion) + "&callerBrowserDetail=" + 
			escape(callerBrowserDetail);
 	browserDataRequest.open("GET", url, true);
	browserDataRequest.onreadystatechange = browserResults;
 	browserDataRequest.send();


	
}

function browserResults() {
	if (browserDataRequest.readyState == 4) {
    	if (browserDataRequest.status == 200) {
    		var response = browserDataRequest.responseText;
//    		alert(response);
			window.location = "chat.php?groupChatTransferFlag=" + groupChatTransferFlag + "&groupChatCallerId=" + groupChatCallerId;
		}
	}
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