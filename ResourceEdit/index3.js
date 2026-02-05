function createRequest() {
  try {
    request = new XMLHttpRequest();	var radioFlags = JSON.parse(resource.editCheckBoxStatus);

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
		if (typeof showComprehensiveError === 'function') {
			showComprehensiveError(
				'XMLHttpRequest Error',
				'Error creating XMLHttpRequest object',
				{
					additionalInfo: 'Your browser does not support XMLHttpRequest. This application requires a modern web browser (Chrome, Firefox, Safari, Edge).'
				}
			);
		} else {
			alert("Error creating XMLHttpRequestObject.\n\nPlease use a modern browser (Chrome, Firefox, Safari, Edge).");
		}
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
                self.results(self.xmlHttp.responseText, self.resultsObject, self.xmlHttp.responseXML);
            }
        }
        catch (e) {
            if (typeof showComprehensiveError === 'function') {
                showComprehensiveError(
                    'Resource Request Error',
                    'An error occurred while processing the request',
                    {
                        error: e,
                        additionalInfo: 'Failed to handle the server response. This may be due to a network issue, server error, or malformed response data.'
                    }
                );
            } else {
                alert(e);
            }
        } 
    };
};


function decodeHTMLEntities(encodedStr) {
    const textarea = document.createElement('textarea');
    textarea.innerHTML = encodedStr;
    return textarea.value;
}


function removeElements(element) {
	while(element.hasChildNodes()) {     
		element.removeChild(element.childNodes[0]);
	}
}

function escapeHTML(str) {
	var div = document.createElement("div");
	div.appendChild(document.createTextNode(str));
	var encodedString = div.innerHTML;
	return encodeURIComponent(encodedString);
}

function unescapeHTML(escapedStr) {
    var div = document.createElement('div');
    div.innerHTML = escapedStr;
    var child = div.childNodes[0];
    return child ? child.nodeValue : '';
};


function d2h(d) {
    return d.toString(16);
}

function h2d (h) {
    return parseInt(h, 16);
}

function stringToHex(tmp) {
    if (!tmp) {
        return tmp;
    }

    // Encode as URI component to handle special characters, then convert to hex
    let encoded = encodeURIComponent(tmp); // Encodes special characters
    let hex = '';
    for (let i = 0; i < encoded.length; i++) {
        hex += encoded.charCodeAt(i).toString(16).padStart(2, '0');
    }
    return hex;
}

// Helper function to decode hex back to string
function hexToString(hex) {
    if (!hex) {
        return hex;
    }

    let str = '';
    for (let i = 0; i < hex.length; i += 2) {
        str += String.fromCharCode(parseInt(hex.substr(i, 2), 16));
    }

    return decodeURIComponent(str); // Decode special characters
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
}





function updateTooOldCheck() {
	var webSiteUpdateDate = document.getElementById("webSiteUpdateDate").value;
	var elementUpdateDate = document.getElementById("webSiteUpdateDate");
	if(webSiteUpdateDate) {			
		//Check to make sure webpage update date is less than six months old.
		var updateDate = new Date(Date.parse(webSiteUpdateDate.replace('-','/','g')));
		var now = new Date();

		var daysOld = Math.round((now-updateDate)/(1000*60*60*24));

		if(daysOld > 182) {	
			goToElementByName(elementUpdateDate);
			validationErrorWindow(elementUpdateDate , "The webpage is too old. \n\nClick 'OK' to load a new resource." , daysOld);
			return true;
		}
	} else {
			return false;
	
	}
}



function validateRecord() {
    // Declare variables with let to avoid hoisting issues
    let IDNUM = document.getElementById("resourceDetailIDNUM").value.trim();
    let NAME = document.getElementById("resourceDetailEditNAME").value.trim();
    let NAME2 = document.getElementById("resourceDetailEditNAME2").value.trim();
    let CONTACT = document.getElementById("resourceDetailEditCONTACT").value.trim();
    let ADDRESS1 = document.getElementById("resourceDetailEditADDRESS1").value.trim();
    let ADDRESS2 = document.getElementById("resourceDetailEditADDRESS2").value.trim();
    let CITY = document.getElementById("resourceDetailEditCITY").value.trim();
    let STATE = document.getElementById("resourceDetailEditSTATE").value.trim();
    let ZIP = document.getElementById("resourceDetailEditZIP").value.trim();
    let PHONE = document.getElementById("resourceDetailEditPHONE").value.trim();
    let EXT = document.getElementById("resourceDetailEditEXT").value.trim();
    let HOTLINE = document.getElementById("resourceDetailEditHOTLINE").value.trim();
    let INTERNET = document.getElementById("resourceDetailEditINTERNET").value.trim();
    let DESCRIPT = document.getElementById("resourceDetailEditDESCRIPT").value;
    let WWWEB = document.getElementById("resourceDetailEditWWWEB").value;
    let WWWEB2 = document.getElementById("resourceDetailEditWWWEB2").value;
    let WWWEB3 = document.getElementById("resourceDetailEditWWWEB3").value;
    let AREACODE = PHONE.substr(0, 3);
    let NOTE = document.getElementById("resourceDetailEditNOTE").value;
    let webSiteUpdateDate = document.getElementById("webSiteUpdateDate").value;
    let webSiteUpdateType = document.getElementById("webSiteUpdateType").value;
    let CLOSED = document.getElementById("resourceDetailCLOSED").value;
    let notesToAaron = document.getElementById("notesToAaron").value;
    let editUser = document.getElementById("UserID").value;

    updateTooOldCheck();

    // If fields are empty, use old values for geocoding
    ADDRESS1OLD = document.getElementById("resourceDetailADDRESS1").value.trim();
    ADDRESS2OLD = document.getElementById("resourceDetailADDRESS2").value.trim();
    CITYOLD = document.getElementById("resourceDetailCITY").value.trim();
    STATEOLD = document.getElementById("resourceDetailSTATE").value.trim();
    ZIPOLD = document.getElementById("resourceDetailZIP").value.trim();

    if (!ADDRESS1 && ADDRESS1 !== null) {
        ADDRESS1 = ADDRESS1OLD;
    }
    
    if (!ADDRESS2 && ADDRESS2 !== null) {
        ADDRESS2 = ADDRESS2OLD;
    }

    if (!CITY && CITY !== null) {
        CITY = CITYOLD;
    }
    
    if (!STATE && STATE !== null) {
        STATE = STATEOLD;
    }

    if (!ZIP && ZIP !== null) {
        ZIP = ZIPOLD;
    }
    
    // Prepare the form data to be sent to the server
	var jsonData = {
		IdNum: IDNUM,
		NAME: NAME,
		NAME2: NAME2,
		CONTACT: CONTACT,
		ADDRESS1: ADDRESS1,
		ADDRESS2: ADDRESS2,
		CITY: CITY,
		STATE: STATE,
		ZIP: ZIP,
		PHONE: PHONE,
		EXT: EXT,
		HOTLINE: HOTLINE,
		INTERNET: INTERNET,
		DESCRIPT: DESCRIPT,
		WWWEB: WWWEB,
		WWWEB2: WWWEB2,
		WWWEB3: WWWEB3,
		NOTE: NOTE,
		notesToAaron: notesToAaron,
		webSiteUpdateDate: webSiteUpdateDate,
		webSiteUpdateType: webSiteUpdateType,
		editCheckBoxStatus: editCheckBoxStatus,
		editUser: editUser,
		CLOSED: CLOSED
	};



	var finalFormData = [];
	// Collect all checkboxes from the form
	const checkboxes = Array.from(document.querySelectorAll('form input[type="checkbox"]'));
	
	// Loop through each checkbox
	checkboxes.forEach(checkbox => {
		if (checkbox.checked) {  // Only process if checkbox is checked
			var elementObject = {};
			var name = checkbox.name;
			var value = checkbox.value;
			elementObject[name] = value;
			finalFormData.push(elementObject);  // Add to finalFormData array
		}
	});

	var editCheckBoxStatus = array2json(finalFormData);

	jsonData.editCheckBoxStatus = editCheckBoxStatus;
	
    // Handle category selections
    var selectMenus = document.getElementsByTagName("select");
    for (var i = 0; i < selectMenus.length ; i++) {
        var menu = selectMenus[i];
        var menuValue = categories[menu.selectedIndex];
        var menuNumber = i + 1;
        jsonData["TYPE" + menuNumber] = menuValue;
    }

    var MapAddress = ADDRESS1 + " " + ADDRESS2 + " " + CITY + ", " + STATE + " " + ZIP;
    var location = geocodeAddress(MapAddress, jsonData);
}


function addObjectWithNewKey(array, oldKey, newKey) {
    array.forEach(obj => {
        if (obj.hasOwnProperty(oldKey)) {
            // Add a new object with the new key and same value
            array.push({ [newKey]: obj[oldKey] });
        }
    });
    return array; // Return the updated array
}


function array2json(arr) {
    var parts = [];
    var is_list = (Object.prototype.toString.apply(arr) === '[object Array]');

    for(var key in arr) {
    	var value = arr[key];
        if(typeof value == "object") { //Custom handling for arrays
            if(is_list) parts.push(array2json(value)); /* :RECURSION: */
            else parts.push('"' + key + '":' + array2json(value)); /* :RECURSION: */
            //else parts[key] = array2json(value); /* :RECURSION: */
            
        } else {
            var str = "";
            if(!is_list) str = '"' + key + '":';

            //Custom handling for multiple data types
            if(typeof value == "number") str += value; //Numbers
            else if(value === false) str += 'false'; //The booleans
            else if(value === true) str += 'true';
            else str += '"' + value + '"'; //All other things
            // :TODO: Is there any more datatype we should be in the lookout for? (Functions?)

            parts.push(str);
        }
    }
    var json = parts.join(",");
    
    if(is_list) return '[' + json + ']';//Return numerical JSON
    return '{' + json + '}';//Return associative JSON
}

function goToElementByName(element) {
	var position = getElementPosition(element.parentNode);
	window.scroll(position.left, position.top - 50);
	
//	var left = position[1];
//	window.scrollTo(top, left);
}


function remove(id) {
	var parent = id.parentNode;
	var elem = parent.removeChild(id);
}






function validationErrorWindow(element, message, daysOld) {
	var existingWindow = document.getElementById("validationErrorWindow");
	if (existingWindow) {
		remove(existingWindow);
	}
	var body = document.getElementsByTagName("body")[0];    

	var select = document.createElement("div");
	select.id = "validationErrorWindow";
	var h1 = document.createElement("h3");
	h1.appendChild(document.createTextNode("Validation Issue"));
	select.appendChild(h1);
	var p = document.createElement("p");
	p.appendChild(document.createTextNode(message));
	select.appendChild(p);
	
	var option = document.createElement("input");
	option.type = 'button';
	option.value = "OK";
	option.class = "defaultButton";
	option.style.marginLeft = "100px";
	option.style.padding = "5px";

	option.onclick = function() {
		this.parentNode.style.display = "none";
		if(element.id == "webSiteUpdateDate" && daysOld > 182) {		
			var IDNUM = document.getElementById("resourceDetailIDNUM").value.trim();
			var webSiteUpdateDate = document.getElementById("webSiteUpdateDate").value;
			var webSiteUpdateType = document.getElementById("webSiteUpdateType").value;
			var params = "idNum=" + IDNUM + "&webSiteUpdateDate=" + webSiteUpdateDate + "&webSiteUpdateType=" + webSiteUpdateType;
			var url="tooOldResourceUpdate.php";
			var updateRecord = new AjaxRequest(url, params, updateRecordResults, this);	
		} else if (element.id == "webSiteUpdateDate") {
			element.focus();
		} else if (element.id == "resourceDetailWWWEB") {
			var IDNUM = document.getElementById("resourceDetailIDNUM").value.trim();
			var params = "idNum=" + IDNUM + "&webSiteUpdateDate=&webSiteUpdateType=";
			var url="tooOldResourceUpdate.php";
			var updateRecord = new AjaxRequest(url, params, updateRecordResults, this);	

		}
	}; 
	select.appendChild(option);

	var elementPosition = null;
	elementPosition = getElementPosition(element);
	var elementHeight = window.getComputedStyle(element).height.replace("px","");
	var topAdjust = (180 - elementHeight) / 2;
	var left = elementPosition.left + 450;
	var top = elementPosition.top - topAdjust;
	select.style.left = left + "px";
	select.style.top = top + "px";
	select.style.display = "block";

	body.appendChild(select);

}


function getElementPosition(element) {
    // (1)

	var elem = element;
    var box = elem.getBoundingClientRect()
    var body = document.body
    var docElem = document.documentElement

    // (2)
    var scrollTop = window.pageYOffset || docElem.scrollTop || body.scrollTop
    var scrollLeft = window.pageXOffset || docElem.scrollLeft || body.scrollLeft

    // (3)
    var clientTop = docElem.clientTop || body.clientTop || 0
    var clientLeft = docElem.clientLeft || body.clientLeft || 0

    // (4)
    var top  = box.top +  scrollTop - clientTop;
    var left = box.left + scrollLeft - clientLeft;
    return { top: Math.round(top), left: Math.round(left) }
}




function geocodeAddress(address, jsonData) {
    geocoder.lookup(address, function(err, place) {
        if (err || place.results.length === 0) {
            // Perform backup geocoding
            let backupAddress = jsonData.CITY + " " + jsonData.STATE + " " + jsonData.ZIP;
            geocoder.lookup(backupAddress, function(err, backupPlace) {
                let lat, lng;
                if (err || backupPlace.results.length === 0) {
                    lat = null;
                    lng = null;
                } else {
                    lat = backupPlace.results[0].coordinate.latitude;
                    lng = backupPlace.results[0].coordinate.longitude;
                }
                // Update jsonData after backup geocoding
                jsonData.LATITUDE = lat;
                jsonData.LONGITUDE = lng;
                updateDatabase(jsonData);
            });
        } else {
            // Use primary geocoding result
            let lat = place.results[0].coordinate.latitude;
            let lng = place.results[0].coordinate.longitude;
            jsonData.LATITUDE = lat;
            jsonData.LONGITUDE = lng;
            updateDatabase(jsonData);
        }
    });
}

function updateDatabase(jsonData) {
	var params = JSON.stringify(jsonData);

	var url="updateResourceReviewRecord.php";
	var updateRecord = new AjaxRequest(url, params, updateRecordResults, this);
}



function updateRecordResults(results, resultsObject) {
	if(results && results != "OK") {
		alert(results);
	} else {
		var volunteerUpdateInfo = document.getElementsByClassName("volunteerUpdateInfo");
		for (var i = 0; i < volunteerUpdateInfo.length; i++) {
			volunteerUpdateInfo[i].style.display = "none";
		}

		clearForm("resourceDetailForm");

		getRecordToUpdate();
	}
}



function clearForm(formId) {
    const form = document.getElementById(formId);
    if (!form) {
        console.error("Form not found:", formId);
        return;
    }

    // Iterate through all child elements of the form
    Array.from(form.elements).forEach((element) => {
        if (element.tagName === "INPUT") {
            if (element.type === "text" || element.type === "password" || element.type === "email") {
                element.value = ""; // Clear text inputs
            } else if (element.type === "checkbox" || element.type === "radio") {
                element.checked = false; // Uncheck checkboxes and radios
            } else if (element.type === "number" || element.type === "date") {
                element.value = ""; // Clear other input types
            }
        } else if (element.tagName === "TEXTAREA") {
            element.value = ""; // Clear textarea
        } else if (element.tagName === "SELECT") {
            element.selectedIndex = 0; // Reset select to the first option
        }
    });
    console.log("Form cleared:", formId);
}





var myWindow = null;
var categories = [];		





window.onload = function() {
    initPage();
    setupEditCheckboxes();
};


const setupEditCheckboxes = () => {
    const checkboxes = document.querySelectorAll("input[type='checkbox'][value='Edited']"); // Select only checkboxes with value 'Edited'
    checkboxes.forEach((checkbox) => {
        checkbox.checked = false;
        toggleEditBlock(checkbox);
        checkbox.addEventListener('change', () => toggleEditBlock(checkbox));
    });

   const deletedboxes = document.querySelectorAll("input[type='checkbox'][value='Remove']"); // Select only checkboxes with value 'Deleted'
    deletedboxes.forEach((deletebox) => {
        deletebox.checked = false;
        toggleDelete(deletebox);
        deletebox.addEventListener('click', () => toggleDelete(deletebox));
    });

};


const uncheckEditBoxes = () => {
    const checkboxes = document.querySelectorAll("input[type='checkbox'][value='Edited']"); // Select only checkboxes with value 'Edited'
    checkboxes.forEach((checkbox) => {
        checkbox.checked = false;
        toggleEditBlock(checkbox);
	});
	
  	const deletedboxes = document.querySelectorAll("input[type='checkbox'][value='Remove']"); // Select only checkboxes with value 'Edited'
	deletedboxes.forEach((deletebox) => {
        deletebox.checked = false;
	});
		
	var inputFields = document.getElementsByTagName("input");
	for (var i=0, max=inputFields.length; i < max; i++) {
		var inputField = inputFields[i];
		if(inputField.name.startsWith("resourceDetailEdit")) {
			inputField.value = "";
		}
	}

	var inputFields = document.getElementsByTagName("textarea");
	for (var i=0, max=inputFields.length; i < max; i++) {
		var inputField = inputFields[i];
		if(inputField.name.startsWith("resourceDetailEdit")) {
			inputField.value = "";
		}
	}	
	document.getElementById("notesToAaron").value = "";	
}


const toggleDelete = (checkbox) => {
    const fieldName = checkbox.name.replace('delete', '');
	if(checkbox.checked) {
		const editCheck = document.getElementsByName("checkbox" + fieldName)[0];
		editCheck.checked = false;
		const editBlock = document.getElementById(fieldName);
		const editFields = editBlock.querySelectorAll("input, textarea");
        hideEditBlock(editBlock, editFields);
	}
}



const toggleEditBlock = (checkbox) => {
    const fieldName = checkbox.name.replace('checkbox', '');
    const editBlock = document.getElementById(fieldName);
    const editFields = editBlock.querySelectorAll("input, textarea");

    if (checkbox.checked) {
        showEditBlock(editBlock);
        editFields.forEach((field) => {
        	if(field.id != "resourceDetailEditNAME2" && field.id != "resourceDetailEditADDRESS2") {
	            field.setAttribute('required', 'required');
	        }
        });
    } else {
        hideEditBlock(editBlock, editFields);
    }
};


const showEditBlock = (editBlock) => {
    editBlock.style.display = 'block';
};

const hideEditBlock = (editBlock, editFields) => {
    editBlock.style.display = 'none';
    editFields.forEach((field) => {
        field.value = "";  // Clears the value of the field
        field.removeAttribute('required');  // Removes the required attribute
    });
};


function editCheckBoxValue(buttonElements) {
	for (var i=0; i < buttonElements.length ; i++) {
		var element = buttonElements[i];
		if(element.checked) {
			return element.value;
		}
	}
	return false;
}


function checkWebForSkip() {

	var web = document.getElementById("resourceDetailWWWEB");
	var web1 = editCheckBoxValue(document.getElementsByName("RadioWWWEB"));
	var web2 = editCheckBoxValue(document.getElementsByName("RadioWWWEB2"));
	var web3 = editCheckBoxValue(document.getElementsByName("RadioWWWEB3"));
	
	if(web1 == "Unknown" || web1 == "Remove") {
		var web1Skip = true;
	} else {
		var web1Skip = false;
	}

	if(web2 == "Unknown" || web2 == "Remove") {
		var web2Skip = true;
	} else {
		var web2Skip = false;
	}

	if(web3 == "Unknown" || web3 == "Remove") {
		var web3Skip = true;
	} else {
		var web3Skip = false;
	}
	
	if(web1Skip && web2Skip && web3Skip) {
		validationErrorWindow(web , "You cannot update a resource with no websites. \n\nClick 'OK' to load a new resource.");
	}
}	



function initPage() {
	// Trap all events
	document.body.addEventListener('click', logEvent, true);
	document.body.addEventListener('input', logEvent, true);
	document.body.addEventListener('change', logEvent, true);
	document.body.addEventListener('submit', logEvent, true);
	document.body.addEventListener('keydown', logEvent, true);
	document.body.addEventListener('keyup', logEvent, true);
	document.body.addEventListener('focus', logEvent, true);
	document.body.addEventListener('blur', logEvent, true);
	document.body.addEventListener('reset', logEvent, true);

	// Any other generic event trapping
	document.body.addEventListener('*', logEvent, true);

	function logEvent(event) {
		console.log(`Event: ${event.type}`);
		console.log('Target:', event.target);
		console.log('Current Target:', event.currentTarget);
		console.log('Default Prevented:', event.defaultPrevented);
		console.log('---');
	}


  let tokenID = null;

  const hostname = window.location.hostname; // Get the hostname of the current site

  if (hostname === "vcctest.org") {
    tokenID = "eyJraWQiOiJBNTU3RzhITloyIiwidHlwIjoiSldUIiwiYWxnIjoiRVMyNTYifQ.eyJpc3MiOiJKOEtNSlNTUlRIIiwiaWF0IjoxNzMzNzg4ODExLCJvcmlnaW4iOiJ2b2x1bnRlZXJsb2dpbi5vcmcifQ.yy52AYCREtOkFjiG4QXbr8qSKf5rsJ9ojCOPljAvMlG8qGZfFBEiF7KYA5wsLsfRVvGkH_pdj7gdXiqm5j1Lwg";
  } else if (hostname === "vcctest.org") {
    tokenID = "eyJraWQiOiJLUjNUM1VLQzNVIiwidHlwIjoiSldUIiwiYWxnIjoiRVMyNTYifQ.eyJpc3MiOiJKOEtNSlNTUlRIIiwiaWF0IjoxNzMyNjc3MzYyLCJvcmlnaW4iOiJ2Y2N0ZXN0Lm9yZyJ9.NIKkXbT6gWMOe647vspsNIvRDo0--rf9UvbRwOZKTGcs0H-U2LFKY2r6xVHhwEE8L7JXV88E8m_YPmq82oLc0Q";
  } else {
    tokenID = null; // Set to null if hostname doesn't match
  }

  mapkit.init({
      authorizationCallback: function(done) {
          done(tokenID);
      }
  });


//	mapkit.init({
//		authorizationCallback: function(done) {
//			done('eyJhbGciOiJFUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6IkJORFM0WFA5NTUifQ.eyJpc3MiOiJKOEtNSlNTUlRIIiwiaWF0IjoxNjcyOTgyMjg2LCJleHAiOjE3MDEzODg4MDAsIm9yaWdpbiI6Imh0dHBzOi8vdm9sdW50ZWVybG9naW4ub3JnIn0.A5BVFg0CmGW3Xd2YRQBLrnBRUq27Dk92Rz_PncQRX-3pOAk7RWdFgX0QuF6gjEWddchv5x0TGWFMvXhN0nm9xg');
//		}
//	});



// 	mapkit.init({
//    	authorizationCallback: function(done) {    	
//    		done('eyJhbGciOiJFUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6IkFBVkxOS1Y3NDcifQ.eyJpc3MiOiJKOEtNSlNTUlRIIiwiaWF0IjoxNTgwODU4Mjg5LCJleHAiOjE2MTIzOTY4MDB9.oReplMwi85-z4KQZMIihv062tXF7Tr0Bp-JHyruepqRKND0ETwR8jU-o9UrKUiyKfJmWWGbwbV-7QfwDvxr1GQ');
//	    }
//	});


	geocoder = new mapkit.Geocoder({
		language: "en",
		getsUserLocation: false
	});


	var ctrl = document.getElementById("resourceDetailEditHOTLINE");
	ctrl.onkeydown = phoneNumberFormat;

	var ctrl2 = document.getElementById("resourceDetailEditPHONE");
	ctrl2.onkeydown = phoneNumberFormat;
	
	var ctrl3 = document.getElementById("webSiteUpdateDate");
	ctrl3.onblur = updateTooOldCheck;

	
	var web1 = document.getElementsByName("RadioWWWEB");
	for (var i=0;i < web1.length ; i++) {
		var item = web1[i];
		item.onclick = checkWebForSkip;
	}

	
	var web2 = document.getElementsByName("RadioWWWEB2");
	for (var i=0;i < web2.length ; i++) {
		var item = web2[i];
		item.onclick = checkWebForSkip;
	}
	
	var web3 = document.getElementsByName("RadioWWWEB3");
	for (var i=0;i < web3.length ; i++) {
		var item = web3[i];
		item.onclick = checkWebForSkip;
	}
	
	document.addEventListener('DOMContentLoaded', function() {
		// Select all the edit checkboxes
		const editCheckboxes = document.querySelectorAll(".editGroup input[type='checkbox'][name^='checkbox']");
	
		const customMessages = {
			NAME: "Please enter the corrected 'Name'.",
			CONTACT: "Please enter the corrected 'Contact Name'.",
			ADDRESS: "Please enter the corrected 'Address'.",
			CITY: "Please enter the corrected 'City'.",
			STATE: "Please enter the corrected 'State'.",
			ZIP: "Please enter the corrected 'Zip Code'.",
			PHONE: "Please enter the corrected 'Phone'.",
			EXT: "Please enter the corrected 'Phone Extension'.",
			HOTLINE: "Please enter the corrected 'Toll Free Phone Number'.",
			INTERNET: "Please enter the corrected 'Email Address'.",
			DESCRIPT: "Please enter the corrected 'Description' or select 'Confirmed' if no change is needed",
			WWWEB: "Please enter the corrected 'Website 1'.",
			WWWEB2: "Please enter the corrected 'Website 2'.",
			WWWEB3: "Please enter the corrected 'Website 3'.",
			NOTE: "Please enter the corrected 'Note' for this resource.",
			webSiteUpdateDate: "Please enter the most recent date the website was updated",
			webSiteUpdateType: "Please enter the reason you can see that the website was updated"
		};
	
		editCheckboxes.forEach(function(checkbox) {
			checkbox.addEventListener('change', function() {
				// Get the id suffix from the checkbox name, for example "NAME" from "checkboxNAME"
				const idSuffix = checkbox.name.replace('checkbox', '');
				// Find the corresponding edit block using the id suffix
				const editBlock = document.getElementById(idSuffix);
				if (editBlock) {
					// Get all input fields and textareas inside the edit block
					const inputFields = editBlock.querySelectorAll('input, textarea');
					inputFields.forEach(function(input) {
						if (checkbox.checked) {
							// Add required attribute if checkbox is checked
							input.setAttribute('required', 'required');
							if (customMessages[idSuffix]) {
								input.setCustomValidity(customMessages[idSuffix]);
							}
						} else {
							// Remove required attribute if checkbox is unchecked
							input.removeAttribute('required');
							input.setCustomValidity('');
						}
					});
				}
			});
		});
	

	});
	
	document.getElementById("resourceDetailForm").addEventListener("submit", function(event) {
		console.log("Form submitted!");
		event.preventDefault(); // Prevent submission for debugging
	});

	document.getElementById("notesToAaron").addEventListener("input", function(event) {
		event.stopPropagation();
		console.log("Input event triggered:", event);
	});	
	
	
	
	// Handle form submission
	const form = document.getElementById('resourceDetailForm');
	form.addEventListener('submit', function(event) {
		// HTML validation is performed first
		if (form.checkValidity()) {
		  // Only if HTML5 validation passes, proceed with custom JS validation
		  if (!validateRecord()) {
			// Prevent form submission if custom validation fails
			event.preventDefault();
		  }
		} else {
		  // Prevent form submission if HTML5 validation fails
		  event.preventDefault();
		}
	});

	
	var cancelButton = document.getElementById("Cancel");
	cancelButton.onclick = function() 	{
		    var IDNUM = document.getElementById("resourceDetailIDNUM").value.trim();
			var url="cancelUpdate.php";
			var params = "idNum=" + IDNUM
			var getRecord = new AjaxRequest(url, params, cancelUpdateResults, this);
		}
		
	var dateUnknownButton = document.getElementById('dateUnknownButton');
	dateUnknownButton.onclick = function() {
		
		cancelButton.click();
		
		
		
		};


   	var exitButton = document.getElementById("ExitButton");
    exitButton.onclick = function() 	{
											var params = "none";
											var responseFunction = function (results) {
												exitConfirmed(results);
											};
											var exitProgramRequest = new AjaxRequest("exitProgram.php", params, responseFunction);				
										}


	var editCheckBoxes = document.getElementsByTagName("input");
	for (var i=0, max=editCheckBoxes.length; i < max; i++) {
		var editCheckBox = editCheckBoxes[i];
		var type = editCheckBox.getAttribute("type");
		var defaultButtonValue = editCheckBox.value;
		if(type == "checkbox" && defaultButtonValue != "Remove") {
			editCheckBox.onchange = function() {
				var value = this.checked;
				var id = this.name;
				var fieldName = id.replace('checkbox','');
				var editBlock = document.getElementById(fieldName);
				var dataBlock = document.getElementById("resourceDetail" + fieldName);
				var newValueBlock = document.getElementById("resourceDetailEdit" + fieldName);
				if(fieldName == "NAME") {
					var newValueBlock2 = document.getElementById("resourceDetailEditNAME2");
				}
				if(fieldName == "ADDRESS") {
					newValueBlock = document.getElementById("resourceDetailEditADDRESS1");
					var newValueBlock2 = document.getElementById("resourceDetailEditADDRESS2");
				}

				if(dataBlock) {
					var currentValue = dataBlock.value;
				}
				if(value==true) {
					newValueBlock.onchange = function() {
					};
					if(newValueBlock2) {
						newValueBlock2.onchange = function() {
						};
					}
					editBlock.style.display = 'block';
					var elements = editBlock.childNodes;
					for (var i=0, max=elements.length; i < max; i++) {
						var element = elements[i];
						if(element.type == 'textarea') {
							element.value = currentValue;
						}
					}
				} else {
					newValueBlock.value = null;				
					if(newValueBlock2) {
						newValueBlock2.value = null;				
					}
					var elements = editBlock.childNodes;
					for (var i=0, max=elements.length; i < max; i++) {
						var element = elements[i];
						if(element.tagName == 'INPUT') {
							if(element.type == 'text') {
								element.value = "";
							}
						} else if (element.type == 'textarea') {
							element.value = "";
						}
					}
					editBlock.style.display = null;
				}
			};
		} else if (editCheckBox.id == "resourceDetailWWWEB" || editCheckBox.id == "resourceDetailWWWEB2" || editCheckBox.id == "resourceDetailWWWEB3" ) {
			editCheckBox.onclick = function() {
				var address = this.value;
				if(address) {
					var myWindow = window.open("http://" + address, "New Window", "height=900,width=600,left=800,top=0");
					myWindow.moveTo(0, 800); 

				}
			}
		}
	}
	createTypesMenu();
}

Date.prototype.toMysqlDate = function() {

	var date = this.getFullYear() + '-' +
    ('00' + (this.getMonth()+1)).slice(-2) + '-' +
    ('00' + this.getDate()).slice(-2);

	return date;
}


function closeRecord(event) {
    event.preventDefault();
    console.log("Function triggered:", event);
	 
	var closedElement = document.getElementById("resourceDetailCLOSED");
	closedElement.value = "Y";
	
	var editCheckBoxs = document.getElementsByTagName("input");
	
	var editDate = new Date();
	editDate = editDate.toMysqlDate();
	document.getElementById("webSiteUpdateDate").value = editDate;	
	document.getElementById("webSiteUpdateType").value = "CLOSING RECORD";
	
	for (var i=0;i<editCheckBoxs.length;i++) {
		var button=editCheckBoxs[i];
		if(!button.name) {
			button.name = button.id;
		}
	}

	var Note = document.getElementById("notesToAaron");
	if(Note.value.length < 5) {
		validationErrorWindow(Note ,"Please enter an explanation for why you believe the record should be closed." , 100);
		return;
	}
	const oldNoteValue = Note.value;
	Note.value = "CLOSE RECORD RECOMMENDATION: " + oldNoteValue;

	validateRecord();
}



function exitConfirmed(results) {
	if(results == "OK" || results == "Unauthorized") {			
		var params = "postType=exitProgram";
		var responseFunction = function (results) {
			exitCompleted(results);
		};
		var exitProgramRequest = new AjaxRequest("../volunteerPosts.php", params, responseFunction);	
	}
}

function exitCompleted(results) {
		window.onbeforeunload = "";
		window.location = '../index.php';
}


function cancelUpdateResults(results, resultsObject)  {
	getRecordToUpdate();
}


function loadApprovalForm(updateObject) {
	var saveButton = document.getElementById("Update");
	saveButton.value = "Approve";
	
	uncheckEditBoxes();

	
	var volunteerUpdateInfo = document.getElementsByClassName("volunteerUpdateInfo");

	for (var i = 0; i < volunteerUpdateInfo.length; i++) {
		volunteerUpdateInfo[i].style.display = "inline-block";
	}

	var webSiteUpdateVolunteer = document.getElementById("webSiteUpdateVolunteer");
	var webSiteUpdateVolunteerDate = document.getElementById("webSiteUpdateVolunteerDate");
	

	var cancelButton = document.getElementById("Cancel");
	cancelButton.value = "Reject";

	var resourceObject = updateObject;
	var resource = JSON.parse(resourceObject["UpdateData"]);
	var editFlags = {};
	var deleteFlags = {};

	if(resource.editCheckBoxStatus) {		
		var radioFlags = JSON.parse(resource.editCheckBoxStatus);
		radioFlags = addObjectWithNewKey(radioFlags, "checkboxADDRESS", "checkboxADDRESS1");
		radioFlags = addObjectWithNewKey(radioFlags, "deleteADDRESS", "deleteADDRESS1");
		radioFlags = addObjectWithNewKey(radioFlags, "deleteADDRESS", "deleteADDRESS2");

		radioFlags.forEach(item => {
			// Extract the checkbox name and associated value
			const checkboxName = Object.keys(item)[0];
			const checkboxValue = item[checkboxName];
	
			// Find the checkbox element(s) matching the name
			const checkboxes = document.querySelectorAll(`input[type="checkbox"][name="${checkboxName}"]`);
	
			// Process each matching checkbox
			checkboxes.forEach(checkbox => {
				// If the checkbox's value matches the data value, check it
				if (checkbox.value === checkboxValue) {
					checkbox.checked = true;
				} else {
					// Uncheck the checkbox if the value does not match
					checkbox.checked = false;
				}
			});
		});	
	}
	
	webSiteUpdateVolunteer.innerHTML = resource["editUser"];
	document.getElementById("resourceDetailCLOSED").value = resourceObject["CLOSED"];
	var mySQLDate = resourceObject["ModifiedTime"];
	var newDate = new Date(mySQLDate);
	webSiteUpdateVolunteerDate.innerHTML = newDate.toLocaleDateString("en-US");

	
	for (var key in resource) {		

		if(key == "webSiteUpdateDate" || key == "webSiteUpdateType" || key == "notesToAaron") {
			field = document.getElementById(key);
			field.value = resource[key];
		}

		field = document.getElementById("resourceDetailEdit" + key);
		if (field) {	
			const radioKey = "checkbox" + key;
			const hasEdited = radioFlags.some(obj => obj[radioKey] === "Edited");

			switch(key) {
				case "ADDRESS1":
					if(radioFlags.some(obj => obj[radioKey] === "Edited")) {
						radioFlags.push({ ["checkboxADDRESS2"]: "Edited" });
					}
					break;
				case "NAME":
					if(radioFlags.some(obj => obj[radioKey] === "Edited")) {
						radioFlags.push({ ["checkboxNAME2"]: "Edited" });
					}
					break;
			}

			if(resource[key] && hasEdited) {

				switch(key) {
					case "ADDRESS1":
					case "ADDRESS2":
						var editBlock = document.getElementById("ADDRESS");
						break;
					case "NAME":
					case "NAME2":
						var editBlock = document.getElementById("NAME");
						break;
					default:
						var editBlock = document.getElementById(key);
						break;			
				}

				if(editBlock) {
					editBlock.style.display = 'block';
				}
					
				if(field.tagName === "INPUT" || field.tagName === "TEXTAREA") {
					field.value = resource[key];
				} else if (key == "IDNUM" ) {
	//				field.value = resource[key];					
				} else if (key == "Note" ) {
					resource[key] = decodeHTMLEntities(resource[key]);			
					field.innerHTML = "";
					field.appendChild(document.createTextNode(resource[key]));
				} else if (key == "Edate") {
					var updatedField = "Last Updated: " + resource[key];
					field.innerHTML = "";
					field.appendChild(document.createTextNode(updatedField));				
				} else {
					field.innerHTML = "";
					field.appendChild(document.createTextNode(resource[key]));
				}
			}	
		}

		if(field && (key=="WWWEB" || key=="WWWEB2" || key=="WWWEB3")) {
			if(resource[key]) {
				var url = "http://" + resource[key];
				field.onclick = function(){
					var url = "http://" + this.value;
					if(!myWindow) {
						myWindow = window.open(url , resource["Name"], "resizable=no,titlebar=0,toolbar=0,scrollbars=yes,status=no,height=1080,width=820,addressbar=1,menubar=1,location=1");  
						myWindow.moveTo(1800,50);
						myWindow.focus();
						myWindow.onunload = function(){
							myWindow = null;
						};
					} else {
						myWindow.location.assign(url);
					}
				};
				if(key=="WWWEB") {
//					field.click();
				}
			}
		}
	}
	
	
	for(var i=1;i<9;i++) {
		var menu = document.getElementById("resourceDetailTYPE" + i);
		menu.removeAttribute("disabled");
	}
	window.scrollTo(0, 0);		
}





function createTypesMenu() {    
    
      //Init Category Select Boxes
   
    var url= "typesList.php";
    var params = "none";
   
	var typesMenuRequest = new AjaxRequest(url, params, displayTypesMenu);	
  
}

function displayTypesMenu(responseText, responseObject, responseXML) {
	  const types = JSON.parse(responseText); // Assuming responseText is a JSON string
    const selectElements = [
        document.getElementById("resourceDetailTYPE1"),
        document.getElementById("resourceDetailTYPE2"),
        document.getElementById("resourceDetailTYPE3"),
        document.getElementById("resourceDetailTYPE4"),
        document.getElementById("resourceDetailTYPE5"),
        document.getElementById("resourceDetailTYPE6"),
        document.getElementById("resourceDetailTYPE7"),
        document.getElementById("resourceDetailTYPE8"),
    ];

    // Disable all select menus initially and clear existing options
    selectElements.forEach((menu) => {
        if (menu) {
            menu.setAttribute("disabled", true);
            menu.options.length = 0; // Clear any existing options
        }
    });

    // Iterate over the types object and populate the select menus
    Object.entries(types).forEach(([key, value], index) => {
        const menuChoice = value.trim(); // Remove extra whitespace
        categories[index] = menuChoice; // Populate the categories array

        selectElements.forEach((select) => {
            if (select) {
                const option = new Option(menuChoice, menuChoice, false, false);
                select.options[select.options.length] = option; // Add option to menu
            }
        });
    });


    // Call the next function
    getRecordToUpdate();
}

function findTypeSelectionIndex(selectedCategory) {

	var selectedIndex = 0;	
	var selectedCategorytrimmed = selectedCategory.replace(/^\s+|\s+$/g, '') ;

	for (var j=0; j<categories.length; j++) {
		indexOfCategories = categories[j].replace(/^\s+|\s+$/g, '') ;

		if (selectedCategorytrimmed == indexOfCategories) {
			var selectedIndex = j;
		}		
	}
	
	if (selectedCategory != " " && selectedIndex == 0) {
		return "ERROR";
	}

	return selectedIndex;
}

function markSelectedCategory(selectedCategory, MenuNumber) {

	var menu = document.getElementById("resourceDetail" + MenuNumber);
	menu.options.selectedIndex = selectedCategory;
}



function getRecordToUpdate() {
	var url="getOldestRecord.php";
	var params = "none";
	var getRecord = new AjaxRequest(url, params, getRecordResults, this);
}

function getRecordResults(results, resultsObject) {
	if(results && results != "None") {
		var resource = JSON.parse(results);

		uncheckEditBoxes();

		for (var key in resource) {
			field = document.getElementById("resourceDetail" + key);
			if (field) {
				if (key == "CLOSED") {
					var updatedField = resource[key];
					field.value = updatedField;
				} else if(field.tagName === "INPUT" || field.tagName === "TEXTAREA") {
					field.value = resource[key];
				} else if (key == "IDNUM" ) {
					field.value = resource[key];					
				} else if (key == "Note" ) {
					resource[key] = decodeHTMLEntities(resource[key]);
					field.innerHTML = "";
					field.appendChild(document.createTextNode(resource[key]));
				} else if (key == "Edate") {
					var updatedField = "Last Updated: " + resource[key];
					field.innerHTML = "";
					field.appendChild(document.createTextNode(updatedField));				
				} else {
					switch(key) {
						case "TYPE1":
						case "TYPE2":
						case "TYPE3":
						case "TYPE4":
						case "TYPE5":
						case "TYPE6":
						case "TYPE7":
						case "TYPE8":
							var selectedCategory = findTypeSelectionIndex(resource[key]);
							var selectedIndex = markSelectedCategory(selectedCategory, key);
							break;
						default:		
							field.innerHTML = "";
							field.appendChild(document.createTextNode(resource[key]));					
					}
				}
			}	

			if(field && (key=="WWWEB" || key=="WWWEB2" || key=="WWWEB3")) {
				if(resource[key]) {
					var url = "http://" + resource[key];
					field.onclick = function(){
						var url = "http://" + this.value;

						if(myWindow) {
							myWindow = null;
						}
						myWindow = window.open(url , resource["Name"], "resizable=no,titlebar=0,toolbar=0,scrollbars=yes,status=no,height=1080,width=820,addressbar=1,menubar=1,location=1");  
						if(myWindow) {
							myWindow.moveTo(1800,50);
							myWindow.focus();
							myWindow.onunload = function(){
								myWindow = null;
							};
						}
					};
					if(key=="WWWEB") {
						field.click();
					}
				}
			}
		}
		
		if(resource["updateData"]) {
			var updateObject = resource["updateData"];
			var approvalForm = loadApprovalForm(updateObject);
		}

		window.scrollTo(0, 0);		
	} else if (results == "None") {
		clearForm("resourceDetailForm");
		alert("There are no records to approve.  Tell those slacker volunteers to get busy updating resources!!!");
		window.close();
	}
}


