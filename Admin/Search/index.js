var adminSearch = null;
var categories = new Array();
var changedForm = 0;
var countries = [];
var map = "";
var storedLatitude = null;
var storedLongitude = null;
var storedGeoAccuracy = null;
var storedAddress = null;
var storedResourceName = null;


window.onload = function() {
	setTimeout(	function() {createTypesMenu()},1000);
	setTimeout(	function() {createCountriesMenu()},2000);
	adminSearch = new AdminSearch();
	viewControl('searchBox');
	initPage();
}


function decodeHTMLEntities(encodedStr) {
    const textarea = document.createElement('textarea');
    textarea.innerHTML = encodedStr;
    return textarea.value;
}


function onEnter( e ) {
	if (!e) var e = window.event
	if (e.keyCode) code = e.keyCode;
	else if (e.which) code = e.which;
    if(code == 13) {
        document.getElementById("SendButton").click();
    }
}



function initPage() {
	const url = window.location.hostname.toLowerCase();
 	this.tokenID = "";
    if (url.includes("vcctest.org")) {
		this.tokenID = "eyJraWQiOiJBNTU3RzhITloyIiwidHlwIjoiSldUIiwiYWxnIjoiRVMyNTYifQ.eyJpc3MiOiJKOEtNSlNTUlRIIiwiaWF0IjoxNzMzNzg4ODExLCJvcmlnaW4iOiJ2b2x1bnRlZXJsb2dpbi5vcmcifQ.yy52AYCREtOkFjiG4QXbr8qSKf5rsJ9ojCOPljAvMlG8qGZfFBEiF7KYA5wsLsfRVvGkH_pdj7gdXiqm5j1Lwg";
    } else if (url.includes("vccTest.org")) {
		this.tokenID = "eyJraWQiOiJBNTU3RzhITloyIiwidHlwIjoiSldUIiwiYWxnIjoiRVMyNTYifQ.eyJpc3MiOiJKOEtNSlNTUlRIIiwiaWF0IjoxNzMzNzg4ODExLCJvcmlnaW4iOiJ2b2x1bnRlZXJsb2dpbi5vcmcifQ.yy52AYCREtOkFjiG4QXbr8qSKf5rsJ9ojCOPljAvMlG8qGZfFBEiF7KYA5wsLsfRVvGkH_pdj7gdXiqm5j1Lwg";
    } else {
        console.error("Maps Token Error: Unrecognized web address.");
    }


  mapkit.init({
      authorizationCallback: function(done) {
          done(this.tokenID);
      }
  });

 	  
	//HOLDER FOR CHANGE FORM DETECTION PROCESSING
	document.detailForm.onchange = function() {
        changedForm = 1;
        var item = document.getElementById("StreetviewPane");
        if (item.getAttribute("class") == "TopPane") {
			showStreetViewResults();
        }
    }
        
	document.getElementById("dZip").onblur = detailFormZipLookup;
	
	var ctrl = document.getElementById("dPhone");
	ctrl.onkeydown = phoneNumberFormat;
	
	var ctrl = document.getElementById("dInternet");
	ctrl.onblur = emailFormat;
    
	var ctrl = document.getElementById("dAddress1");
	ctrl.onblur = addressFormat;
    
	var ctrl = document.getElementById("dAddress2");
	ctrl.onblur = addressFormat;
    
	var ctrl = document.getElementById("dFax");
	ctrl.onkeydown = phoneNumberFormat;
	ctrl.onblur = function() {
		if(this.value.length < 5) {
			this.value = "";
		}
	}
    
    
    var ctrl = document.getElementById("websiteLabel");
    ctrl.onclick = function() {webPageOpen("dWwweb");};
    
    var ctrl = document.getElementById("website2Label");
    ctrl.onclick = function() {webPageOpen("dWwweb2");};

    var ctrl = document.getElementById("website3Label");
    ctrl.onclick = function() {webPageOpen("dWwweb3");};

    var ctrl = document.getElementById("emailLabel");
    ctrl.onclick = emailToResource;
    
	var ctrl = document.getElementById("addNewCountry");
	ctrl.onclick = addNewCountry;
	
	
// Search Button Links
	var mainNewSearchButton = document.getElementById('SendButton');
	mainNewSearchButton.onclick = function () {
		adminSearch.validate(this.id);
	};

	document.searchBoxForm.onkeypress = onEnter;
	
	document.getElementById("findZipSearch").onclick = function() {
		adminSearch.validate(this.id);
	};

	document.getElementById("nationalSearch").onclick = function() {
		adminSearch.validate(this.id);
	};
	
	const internationalSearch = document.getElementById("internationalSearch");
	var internationalSearchHandler = function () {
		// Store the selected value before resetting
		var selectedCountry = this.value;

		// Don't process if it's the placeholder being selected
		if (!selectedCountry || selectedCountry.trim() === "" || selectedCountry === " ") {
			return;
		}

		// Call validate with the country
		adminSearch.validate(this.id, selectedCountry);

		// Reset the dropdown after a short delay to avoid triggering during search
		setTimeout(function() {
			internationalSearch.selectedIndex = 0;
		}, 100);
	};
	internationalSearch.onchange = internationalSearchHandler;

	var ctrl = document.getElementById("dHotline");
	ctrl.onkeydown = phoneNumberFormat;
	
	
	const addNewRecordButton = document.getElementById('addNewRecordButton');
	addNewRecordButton.onclick = function() {
		if (changedForm == 1) {
			adminSearch.FailedToSaveOrCancel();
			return;
		}
		viewControl('detailPane');
		adminSearch.addNewRecord();
	};

	const NewSearch = document.getElementById('NewSearch');
	NewSearch.onclick = function() {
		if (changedForm == 1) {
			adminSearch.FailedToSaveOrCancel();
			return;
		}
		viewControl('searchBox');
	};

	const CancelNewRecordButton = document.getElementById('CancelNewRecordButton');
	CancelNewRecordButton.onclick = function() {
		adminSearch.cancelNewRecord();
	};
	
	const saveRecordButton = document.getElementById('saveRecord');
	saveRecordButton.onclick = function() {
		adminSearch.saveRecord();
	};
}
    

function webPageOpen(field) {

	var urlElement = document.getElementById(field);
	var url = "http://" + urlElement.value;

	webPageWindow = window.open (url , "", "resizable=no,titlebar=0,toolbar=0,scrollbars=no,status=no,height=1200,width=650,addressbar=1,menubar=1,location=1");  
	webPageWindow.moveTo(2000,50);
	webPageWindow.focus();
}

	

function createCountriesMenu() {
    var url= "../../countries.php";
    const params = [];   
   	var searchRequest = new AjaxRequest(url, params, countriesMenu, this);    
}



function countriesMenu(result, resultObject) {
	var response = JSON.parse(result);
	var countryListSearch = document.getElementById("internationalSearch");
	countryListSearch.innerHTML = "<option value=' '> Int'l Listings</option>";
	var countryListDetail = document.getElementById("dCountry");
	
	for (i=0;i<response.length;i++) {
		entry = response[i];
		if(entry) {
			countries[i] = entry;
			option = document.createElement("option");
			option.value = entry;
			option.appendChild(document.createTextNode(entry));
			countryListSearch.appendChild(option);
			var option2 = option.cloneNode(true);
			countryListDetail.appendChild(option2);
		}
	}
}

function addNewCountry() {
	var newCountry=prompt("Please enter the new country","");
	if(!newCountry) {
		return;
	}
	var countryListSearch = document.getElementById("internationalSearch");
	var countryListDetail = document.getElementById("dCountry");
	countryNumber = countries.length;
	countries.push(newCountry);
	option = document.createElement("option");
	option.value = newCountry;
	option.appendChild(document.createTextNode(newCountry));
	countryListSearch.appendChild(option);
	var option2 = option.cloneNode(true);
	countryListDetail.appendChild(option2);
	document.detailForm.countries.options.selectedIndex = countryNumber - 1;
	changedForm = 1;
}


function findCountrySelectIndex(Country) {
	// The dCountry select has countries starting at index 0 (no placeholder in detail form)
	// The countries array also starts at index 0
	// So we need to find the matching index directly
	var selectedIndex = 0;
	for (var j=0; j<countries.length; j++) {
		if (Country.trim() == countries[j]) {
			selectedIndex = j;
			break;
		}
	}
	return selectedIndex;
}


    
    
    
function detailFormZipLookup()  {
	
	zipLookup(document.getElementById("dZip").value);
	
}    


function zipLookup(Zip) {
    
	var url= "zipOnly.php?";
	this.params = "ZipCode=" + Zip;
	var searchRequest = new AjaxRequest(url, params, recordZipInfo, this);    
}


function recordZipInfo(result, resultObject) {
   	var response = JSON.parse(result);
			
	document.getElementById("dCity").onblur = "";

	if (response.status == "OK") {

		// Record City,State in the Log File
		ZipCodeCity = titleCase(response['City']); 
		ZipCodeState = response['State'];
					
		var City = document.getElementById("dCity");
		var State = document.getElementById("dState");
		
		if(!City.value) {
			City.value = ZipCodeCity;
			State.value = ZipCodeState;
		}
		
	}
}


function phoneNumberFormat(e) {
	ctrl = this;
	code = "";

        
    if (!e) var e = window.event;
	if (e.keyCode) code = e.keyCode;
	else if (e.which) code = e.which;
        
    if(code && code != 8) {
        if(ctrl.value.length == 3) {
            ctrl.value = ctrl.value + "-";
        } else if(ctrl.value.length == 4) {	
            ctrl.setSelectionRange(4, 4);
        } else if (ctrl.value.length == 7) {
            ctrl.value = ctrl.value + "-";
        }
    }
}


function emailFormat() {
	ctrl = this;
    
    var email = this.value;
	var website = document.detailForm.dWwweb.value;  
    var domain = website.replace("www.", "@"); 

    if (domain.search("@") < 0) {
        domain = "@" + domain;
    }
    
    if (email.search("@") < 0 && email.length > 0) { 
        ctrl.value = ctrl.value + domain;
    }               
}



function addressFormat() {
	ctrl = this;
    
    var address = this.value;
    var strippedAddress = address.replace(/[^0-9]/g, '');

    if (address.length == strippedAddress.length && address.length > 0) {
        this.value = "P.O. Box " + address;
    }
}


function emailToResource() {
    var idnum = document.getElementById("dIdnum").value.trim();

    var url = "EmailResource.php?idnum=" + idnum;

    webPageWindow = window.open (url , "Send an Email to a Resource", "resizable=no,titlebar=0,toolbar=0,scrollbars=yes,status=no,height=1080,width=620,addressbar=1,menubar=1,location=1");  
    webPageWindow.moveTo(2000,50);
    webPageWindow.focus();
    
}



	
function createTypesMenu() {        
	var url= "typesList.php";
	this.params = "none";
	var searchRequest = new AjaxRequest(url, params, displayTypesMenu, this);
}



function displayTypesMenu(results, searchObject) {	
	var response = JSON.parse(results);
	var types = new Array();
	for(var i in response) {
		types.push(response[i]);
	}

	var select1=document.detailForm.Type1SelectMenu;
	var select2=document.detailForm.Type2SelectMenu;
	var select3=document.detailForm.Type3SelectMenu;
	var select4=document.detailForm.Type4SelectMenu;
	var select5=document.detailForm.Type5SelectMenu;
	var select6=document.detailForm.Type6SelectMenu;
	var select7=document.detailForm.Type7SelectMenu;
	var select8=document.detailForm.Type8SelectMenu;
	select1.options.length = 0;
	select2.options.length = 0;
	select3.options.length = 0;
	select4.options.length = 0;
	select5.options.length = 0;
	select6.options.length = 0;
	select7.options.length = 0;
	select8.options.length = 0;

	for (var j=0; j<types.length; j++) {
		var menuChoice = types[j];
		categories[j] = menuChoice;
		select1.options[select1.options.length]=new Option(menuChoice, menuChoice, false, false);
		select2.options[select2.options.length]=new Option(menuChoice, menuChoice, false, false);
		select3.options[select3.options.length]=new Option(menuChoice, menuChoice, false, false);
		select4.options[select4.options.length]=new Option(menuChoice, menuChoice, false, false);
		select5.options[select5.options.length]=new Option(menuChoice, menuChoice, false, false);
		select6.options[select6.options.length]=new Option(menuChoice, menuChoice, false, false);
		select7.options[select7.options.length]=new Option(menuChoice, menuChoice, false, false);
		select8.options[select8.options.length]=new Option(menuChoice, menuChoice, false, false);
	}
}



function viewControl(view) {
	var searchBox = document.getElementById("searchBox");
	var resourceList = document.getElementById('resourceList');
	var detailInfo = document.getElementById('detailInfo');
	var detailPane = document.getElementById('detailPane');
	var resourceDetailControl = document.getElementById("resourceDetailControl");
	var searchParameters = document.getElementById("searchParameters");
	var addNewRecordButton = document.getElementById("addNewRecordButton");
	var NewSearch = document.getElementById("NewSearch");
	var bulkEmailButton = document.getElementById("resourceDetailBulkEmailButton");      


	//Hide All Control Buttons
	var buttons = resourceDetailControl.getElementsByTagName("input");
	for (i = 0; i < buttons.length; i++) {
		buttons[i].style.visibility = "hidden";
	}
		
	searchBox.style.display = 'none';
	detailInfo.style.display = "none";
	resourceList.style.display = 'none';
	detailPane.style.display = 'none';
	resourceDetailControl.style.display = 'none';
	bulkEmailButton.style.visibility = "hidden";

	switch(view) {
		
		case 'searchBox':
			searchParameters.innerHTML = "";
			searchBox.style.display = 'block';
			document.searchBoxForm.reset();
			document.getElementById("IdNum").focus();
			break;
		
		case 'resourceList':
			resourceDetailControl.style.display = 'block';
			resourceList.style.display = 'block';
			addNewRecordButton.style.visibility = null;
			NewSearch.style.visibility = null;

			if(adminSearch.requestType == "dateRange") {
				bulkEmailButton.style.visibility = "visible";
			} else {
				bulkEmailButton.style.visibility = "hidden";
			}
			break;
		
		case 'detailPane':
			detailInfo.style.display = "block";
			resourceDetailControl.style.display = 'block';
			detailPane.style.display = 'block';
			resourceDetailControl.style.display = 'block';
			var buttons = resourceDetailControl.getElementsByTagName("input");
			for (i = 0; i < buttons.length; i++) {
				buttons[i].style.visibility = null;
			}
			break;
		
		default:
			searchBox.style.display = 'block';
			break;
	}
}


/**
 * Geolocation Info Modal Functions
 */

function showGeoInfoModal() {
	var modal = document.getElementById('geoInfoModal');
	var modalBody = document.getElementById('geoModalBody');

	if (!modal || !modalBody) {
		console.error('Geo info modal elements not found');
		return;
	}

	// Build the modal content
	var html = '';

	// Resource Info Section
	html += '<div class="geo-info-section">';
	html += '<h4>Resource</h4>';
	html += '<div class="geo-info-row">';
	html += '<span class="geo-info-label">Name:</span>';
	html += '<span class="geo-info-value">' + escapeHtml(storedResourceName || 'Unknown') + '</span>';
	html += '</div>';
	html += '<div class="geo-info-row">';
	html += '<span class="geo-info-label">Address:</span>';
	html += '<span class="geo-info-value' + (storedAddress ? '' : ' no-data') + '">' +
			(storedAddress ? escapeHtml(storedAddress) : 'No address on file') + '</span>';
	html += '</div>';
	html += '</div>';

	// Coordinates Section
	html += '<div class="geo-info-section">';
	html += '<h4>Coordinates</h4>';

	var hasCoords = storedLatitude && storedLongitude &&
					storedLatitude !== 0 && storedLongitude !== 0;

	html += '<div class="geo-info-row">';
	html += '<span class="geo-info-label">Latitude:</span>';
	html += '<span class="geo-info-value' + (hasCoords ? '' : ' no-data') + '">' +
			(hasCoords ? storedLatitude.toFixed(6) : 'Not geocoded') + '</span>';
	html += '</div>';

	html += '<div class="geo-info-row">';
	html += '<span class="geo-info-label">Longitude:</span>';
	html += '<span class="geo-info-value' + (hasCoords ? '' : ' no-data') + '">' +
			(hasCoords ? storedLongitude.toFixed(6) : 'Not geocoded') + '</span>';
	html += '</div>';
	html += '</div>';

	// Accuracy Section
	html += '<div class="geo-info-section">';
	html += '<h4>Geocoding Accuracy</h4>';
	html += '<div class="geo-info-row">';
	html += '<span class="geo-info-label">Level:</span>';
	html += '<span class="geo-info-value">' + formatGeoAccuracy(storedGeoAccuracy, hasCoords) + '</span>';
	html += '</div>';
	html += '<div class="geo-info-row">';
	html += '<span class="geo-info-label">Description:</span>';
	html += '<span class="geo-info-value">' + getGeoAccuracyDescription(storedGeoAccuracy, hasCoords) + '</span>';
	html += '</div>';
	html += '</div>';

	// External Maps Link
	if (hasCoords) {
		html += '<div class="geo-info-section">';
		html += '<h4>External Maps</h4>';
		var googleMapsUrl = 'https://www.google.com/maps?q=' + storedLatitude + ',' + storedLongitude;
		var appleMapsUrl = 'https://maps.apple.com/?ll=' + storedLatitude + ',' + storedLongitude;
		html += '<a class="geo-map-link" href="' + googleMapsUrl + '" target="_blank">Open in Google Maps</a> ';
		html += '<a class="geo-map-link" href="' + appleMapsUrl + '" target="_blank" style="background: linear-gradient(#333, #555);">Open in Apple Maps</a>';
		html += '</div>';
	}

	// Re-geocode Section
	html += '<div class="geo-info-section">';
	html += '<h4>Re-geocode Resource</h4>';
	html += '<p style="font-size: 12px; color: #666; margin: 0 0 8px 0;">Re-run geocoding to update coordinates based on the current address.</p>';
	html += '<button type="button" class="geo-regeocode-btn" id="reGeocodeBtn">Re-geocode This Resource</button>';
	html += '<div id="reGeocodeStatus"></div>';
	html += '</div>';

	modalBody.innerHTML = html;

	// Show modal
	modal.classList.add('visible');

	// Set up close handlers
	var closeBtn = document.getElementById('geoModalClose');
	if (closeBtn) {
		closeBtn.onclick = hideGeoInfoModal;
	}

	// Close on background click
	modal.onclick = function(e) {
		if (e.target === modal) {
			hideGeoInfoModal();
		}
	};

	// Close on Escape key
	document.addEventListener('keydown', geoModalKeyHandler);

	// Set up re-geocode button handler
	var reGeocodeBtn = document.getElementById('reGeocodeBtn');
	if (reGeocodeBtn) {
		reGeocodeBtn.onclick = reGeocodeResource;
	}
}

function hideGeoInfoModal() {
	var modal = document.getElementById('geoInfoModal');
	if (modal) {
		modal.classList.remove('visible');
	}
	document.removeEventListener('keydown', geoModalKeyHandler);
}

function geoModalKeyHandler(e) {
	if (e.key === 'Escape') {
		hideGeoInfoModal();
	}
}

function formatGeoAccuracy(accuracy, hasCoords) {
	// If no accuracy but has coordinates, it's legacy data
	if (!accuracy) {
		if (hasCoords) {
			return '<span class="geo-accuracy-badge medium">LEGACY (unknown)</span>';
		}
		return '<span class="geo-accuracy-badge none">NOT GEOCODED</span>';
	}

	var badgeClass = 'none';
	var displayText = accuracy;

	// Determine badge color based on accuracy level
	switch(accuracy.toUpperCase()) {
		case 'ADDRESS':
		case 'ADDRESS_SIMPLIFIED':
		case 'STREET':
			badgeClass = 'high';
			displayText = accuracy.replace(/_/g, ' ');
			break;
		case 'ZIP':
		case 'ZIP_ONLY':
		case 'ZIP_CENTER':
		case 'ZIP_FALLBACK':
			badgeClass = 'medium';
			displayText = accuracy.replace(/_/g, ' ');
			break;
		case 'CITY':
		case 'REGION':
		case 'COUNTY':
			badgeClass = 'low';
			displayText = accuracy;
			break;
		case 'UNSUPPORTED':
		case 'FAILED':
		case 'NONE':
			badgeClass = 'none';
			displayText = accuracy;
			break;
		default:
			badgeClass = 'medium';
			displayText = accuracy.replace(/_/g, ' ');
	}

	return '<span class="geo-accuracy-badge ' + badgeClass + '">' + displayText + '</span>';
}

function getGeoAccuracyDescription(accuracy, hasCoords) {
	if (!accuracy) {
		if (hasCoords) {
			return 'Coordinates exist but accuracy level was not recorded. Click "Re-geocode" to update with accuracy tracking.';
		}
		return 'This resource has not been geocoded yet. Save the record to trigger geocoding.';
	}

	switch(accuracy.toUpperCase()) {
		case 'ADDRESS':
		case 'ADDRESS_SIMPLIFIED':
			return 'Coordinates are based on the exact street address. High precision.';
		case 'STREET':
			return 'Coordinates are based on the street. Good precision.';
		case 'ZIP':
		case 'ZIP_ONLY':
		case 'ZIP_CENTER':
			return 'Coordinates are based on the ZIP code center. Moderate precision (typically within a few miles).';
		case 'ZIP_FALLBACK':
			return 'Street address could not be found; coordinates are based on ZIP code center as a fallback.';
		case 'CITY':
			return 'Coordinates are based on the city center only. Low precision.';
		case 'REGION':
			return 'Coordinates are based on a regional center. Very low precision.';
		case 'COUNTY':
			return 'Coordinates are based on the county center. Low precision.';
		case 'UNSUPPORTED':
			return 'The address or location type is not supported for geocoding.';
		case 'FAILED':
			return 'Geocoding failed. The address may be invalid or incomplete.';
		default:
			return 'Accuracy level: ' + accuracy;
	}
}

function escapeHtml(text) {
	if (!text) return '';
	var div = document.createElement('div');
	div.textContent = text;
	return div.innerHTML;
}

/**
 * Update the geo accuracy overlay on the map
 */
function updateGeoAccuracyOverlay() {
	var overlay = document.getElementById('geoAccuracyOverlay');
	if (!overlay) return;

	var hasCoords = storedLatitude && storedLongitude &&
					storedLatitude !== 0 && storedLongitude !== 0;

	// Remove all classes
	overlay.className = '';

	if (!hasCoords) {
		overlay.textContent = 'NOT GEOCODED';
		overlay.className = 'none';
		return;
	}

	if (!storedGeoAccuracy) {
		overlay.textContent = 'LEGACY';
		overlay.className = 'medium';
		return;
	}

	var accuracy = storedGeoAccuracy.toUpperCase();
	var displayText = storedGeoAccuracy.replace(/_/g, ' ');

	switch(accuracy) {
		case 'ADDRESS':
		case 'ADDRESS_SIMPLIFIED':
		case 'STREET':
			overlay.className = 'high';
			break;
		case 'ZIP':
		case 'ZIP_ONLY':
		case 'ZIP_CENTER':
		case 'ZIP_FALLBACK':
			overlay.className = 'medium';
			break;
		case 'CITY':
		case 'REGION':
		case 'COUNTY':
			overlay.className = 'low';
			break;
		default:
			overlay.className = 'medium';
	}

	overlay.textContent = displayText;
}

/**
 * Re-geocode the current resource using server-side geocoding
 */
function reGeocodeResource() {
	var btn = document.getElementById('reGeocodeBtn');
	var statusDiv = document.getElementById('reGeocodeStatus');
	var idNum = document.getElementById('dIdnum').value;

	if (!idNum) {
		statusDiv.className = 'geo-regeocode-status error';
		statusDiv.textContent = 'Error: No resource ID found';
		return;
	}

	if (!storedAddress) {
		statusDiv.className = 'geo-regeocode-status error';
		statusDiv.textContent = 'Error: No address on file to geocode';
		return;
	}

	// Disable button and show pending status
	btn.disabled = true;
	btn.textContent = 'Geocoding...';
	statusDiv.className = 'geo-regeocode-status pending';
	statusDiv.textContent = 'Processing...';

	// Get ZIP code from form
	var zip = document.getElementById('dZip').value || '';

	// Build request
	var xhr = new XMLHttpRequest();
	xhr.open('POST', 'updateLocationData.php', true);
	xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	xhr.onreadystatechange = function() {
		if (xhr.readyState === 4) {
			btn.disabled = false;
			btn.textContent = 'Re-geocode This Resource';

			if (xhr.status === 200) {
				try {
					var response = JSON.parse(xhr.responseText);
					if (response.status === 'OK') {
						// Update stored values
						storedLatitude = parseFloat(response.latitude);
						storedLongitude = parseFloat(response.longitude);
						storedGeoAccuracy = response.accuracy;

						// Update the geo accuracy overlay on the map
						updateGeoAccuracyOverlay();

						// Refresh the modal content immediately with new data
						showGeoInfoModal();

					} else {
						statusDiv.className = 'geo-regeocode-status error';
						statusDiv.textContent = 'Error: ' + (response.message || 'Unknown error');
					}
				} catch (e) {
					statusDiv.className = 'geo-regeocode-status error';
					statusDiv.textContent = 'Error parsing response: ' + e.message;
				}
			} else if (xhr.status === 401) {
				statusDiv.className = 'geo-regeocode-status error';
				statusDiv.textContent = 'Session expired. Please refresh the page and try again.';
			} else {
				statusDiv.className = 'geo-regeocode-status error';
				statusDiv.textContent = 'Server error: ' + xhr.status;
			}
		}
	};

	// Send the request
	var params = 'IDNUM=' + encodeURIComponent(idNum) +
				 '&ADDRESS=' + encodeURIComponent(storedAddress) +
				 '&ZIP=' + encodeURIComponent(zip);
	xhr.send(params);
}


function AdminSearch() {
	var self = this;
	this.resources = new Array();
	this.requestType = "";
	
	var buttons = document.getElementById('searchBox').getElementsByTagName('input');
	for (i=0;i < buttons.length - 1;i++) {
		const element = buttons[i];
		const buttonId = element.id;
		if (element.type == 'button') {
			element.onclick = function() {
				self.validate(buttonId);
			};
		}
	}
	
	
	
	this.validate = function(button, selectedCountry) {
		var zip = 		document.getElementById("ZipCode").value;
		var distance = 	document.getElementById("Distance").value;
		var city =		document.getElementById("City").value;
		var state = 	document.getElementById("State").value;
		var name = 		document.getElementById("Name").value;
		var idNum = 	document.getElementById("IdNum").value;
		var searchFromDate = 	document.getElementById("SearchFromDate").value;
		var searchToDate = 	document.getElementById("SearchToDate").value;


		document.getElementById("Distance").blur();

		switch(button) {
			case 'nationalSearch':
				newSearch = new self.search("","All",0,"","","","national");
				newSearch.searchRequest();
				break;
			
			case 'internationalSearch':
				// Use the selectedCountry parameter passed from the onchange handler
				var countryName = selectedCountry;

				// Check for placeholder (single space)
				if (!countryName || countryName.trim() === "") {
					// User selected the placeholder option or empty value
					return;
				}
				if (countryName == "Canada") {
					alert("Please use the normal search boxes for Canadian resources.  Canadian postal codes work the same as Zip Codes.");
					return;
				}
				newSearch = new self.search("","All",0,"","",countryName,"international");
				newSearch.searchRequest();
				break;
			
			case 'findZipSearch':
				if(!city || !state) {
					alert("Please enter a city and a state to find a zip code.");
					return;	
				} 
				newSearch = new self.search("","All",distance,city,state,name,"findZip");
				newSearch.searchRequest();
				break;
			
			case 'ProfilesButton':
				printProfiles();
				break;
			
			default:
				newSearch = new self.search(zip,"All",distance,city,state,name, "", idNum, searchFromDate, searchToDate);
				newSearch.searchRequest();
				break;
		}
	

		var byName = document.getElementById('sortByNameButton');
		byName.onclick = function () {
			newSearch.sortOrder = 'Name';
			newSearch.displayResults("");
		};
		var byDistance = document.getElementById('sortByDistanceButton');
		byDistance.onclick = function () {
			newSearch.sortOrder = 'Distance';
			newSearch.displayResults("");
		};
		var byType1 = document.getElementById('sortByType1Button');
		byType1.onclick = function () {
		};
		var byType2 = document.getElementById('sortByType2Button');
		byType2.onclick = function () {
		};
		var byLocation = document.getElementById('sortByLocationButton');
		byLocation.onclick = function () {
			newSearch.sortOrder = 'Location';
			newSearch.displayResults("");
		};
		var byZip = document.getElementById('sortByZipButton');
		byZip.onclick = function () {
			newSearch.sortOrder = 'Zip';
			newSearch.displayResults("");
		};


		// Handle category select dropdowns (for categories with subtypes)
		var CategoryMenus = document.getElementById("Categories").getElementsByTagName("select");
		for (var i = 0; i<CategoryMenus.length; i++) {
			var Category = CategoryMenus[i];
			Category.onchange= function () {
				var CategoryIndex = this.selectedIndex;
				var Category = this.getElementsByTagName("option")[CategoryIndex].value;
				newSearch.category = Category;
				delete(newSearch.results);
				newSearch.selectToOpen = null;
				this.style.pointerEvents = "none";
				document.getElementById("Menus").reset();
				newSearch.postSearch();
			};
		}

		// Handle category trigger buttons (for categories with subtypes)
		// Clicking the trigger searches for the main category, then opens the dropdown
		var CategoryTriggers = document.getElementById("Categories").getElementsByClassName("category-trigger");
		for (var i = 0; i < CategoryTriggers.length; i++) {
			CategoryTriggers[i].onclick = function () {
				var category = this.getAttribute("data-category");
				var selectMenu = this.nextElementSibling;  // Gets the associated select

				newSearch.category = category;
				delete(newSearch.results);

				// Store the select menu to open after results load
				newSearch.selectToOpen = selectMenu;
				document.getElementById("Menus").reset();
				newSearch.postSearch();
			};
		}

		// Handle category buttons (for categories without subtypes)
		var CategoryButtons = document.getElementById("Categories").getElementsByClassName("categoryButton");
		for (var i = 0; i < CategoryButtons.length; i++) {
			CategoryButtons[i].onclick = function () {
				var Category = this.getAttribute("data-category");
				newSearch.category = Category;
				delete(newSearch.results);
				newSearch.selectToOpen = null;
				document.getElementById("Menus").reset();
				newSearch.postSearch();
			};
		}
	};


	this.search = function(zip, category, distance, city, state, name, requestType, idNum, searchFromDate, searchToDate) {
		var self = this;
		this.zipCode = 		zip;
		this.category = 	category;
		this.range = 		distance;
		this.city = 		city;
		this.state =		state;
		this.name = 		name;
		this.searchFromDate = searchFromDate;
		this.searchToDate = searchToDate;
		this.requestType =	requestType;
		this.idNum = 		idNum;
		this.params = 		'';
		this.sortOrder = 	'Distance';
		this.url = 			'../../zipOnly.php';
		this.countriesList = 	new Array();
		this.indexNo = 		0;
		this.bySortOrder = 	new Array();


		this.searchRequest = function() {
			if(!this.requestType || this.requestType == "") {
				if(this.idNum) {
					self.displayRecord(null, null, this.idNum);
					return;
				}

				if(this.zipCode) {
					this.requestType = 'zip';
					if(!this.range) {
						alert("Please enter a search range.");
						return false;
					}
				} else if (this.city && this.state) {
					this.requestType = 'city';
				} else if (!this.city && this.state) {
					this.requestType = 'state';
				} else if (this.name) {
					this.requestType = 'name';
				} else if (this.searchFromDate && this.searchToDate) {
					this.requestType = 'dateRange';
				} else {
					alert("Please complete the search criteria");
					return false;
				}
			}
			adminSearch.requestType = this.requestType;
			this.postSearch();
			return;
		};
	

		this.postSearch =	function () {
			this.params = "ZipCode=" + encodeURIComponent(this.zipCode) + "&Range=" + encodeURIComponent(this.range) + 
				"&City=" + encodeURIComponent(this.city) + "&State=" + encodeURIComponent(this.state) + "&Name=" + 
				encodeURIComponent(this.name) + "&Category=" + encodeURIComponent(this.category) + "&SearchType=" + encodeURIComponent(this.requestType)
				+ "&searchFromDate=" + encodeURIComponent(this.searchFromDate) + "&searchToDate=" + encodeURIComponent(this.searchToDate)
				+ "&isAdminSearch=true";
			var searchRequest = new AjaxRequest(this.url, this.params, this.displayResults, this);
		};

		this.displayResults = function (results, searchObject) {
			if(!searchObject) {
				searchObject = this;
			}

			if(!searchObject.results) {
				if(results == "INVALID") {
					showInformationalModal(
						'ZIP Code Not Found',
						'The ZIP code "' + searchObject.zipCode + '" was not found in our database. Please verify the ZIP code and try again.'
					);
					return;
				} else if (results == "NONE") {
					showInformationalModal(
						'No Resources Found',
						'No resources found matching your search criteria. Try adjusting your search parameters or expanding your search range.'
					);
					return;
				} else if (results == "No ZipCode Located") {
					showInformationalModal(
						'ZIP Code Not Found',
						'No ZIP code found for ' + searchObject.city + ', ' + searchObject.state + '. Please verify the city and state name and try again.'
					);
					return;
				}

				searchObject.results = JSON.parse(results);
				var searchParameters = searchObject.results.Search;

				// Place data into Search Parameters fields
				if(!searchParameters.zipcode) {
					searchParameters.zipcode = ".";
				}
			
				if(!searchObject.zipCode) {
					searchObject.zipCode = searchParameters.zipcode;
				}
			
				searchParameters.place += "  " + searchParameters.zipcode;

				if (searchParameters.range > 1) {
					searchParameters.range += " Miles";
				} else if (searchParameters.range == 1) {
					searchParameters.range += " Mile";
				} else {
					searchParameters.range = "";
				}

				
				if(self.requestType == 'dateRange') {
					field = document.getElementById("searchParameters");
					field.innerHTML = "";						
					field.innerHTML = "Updated From <br />" + self.searchFromDate + " to " + self.searchToDate;
					addBulkEmailButton();
				} else {				
					field = document.getElementById("searchParameters");
					field.innerHTML = "";						
					field.innerHTML = searchParameters.place + "<br />" + searchParameters.range;
				}
			}

			var resources = searchObject.results.Resources;
			adminSearch.resources = resources;
			var bySortOrder = self.sortBy(resources, searchObject.sortOrder);				
			var frag = document.createDocumentFragment();
			var table = document.createElement('table');
			var serverResponse = document.getElementById("resourceResults");

			while(serverResponse.hasChildNodes()) {     
				serverResponse.removeChild(serverResponse.childNodes[0]);
			}


			bySortOrder.forEach(function(entry) {
				var serverResponse = document.getElementById("resourceResults");
				var title = '';
				var tr = document.createElement('tr');
				tr.setAttribute("class","hover");
				var resource = entry.resource;
				var count = bySortOrder.indexOf(entry);
				var td = document.createElement('td');
				div = document.createElement('div');
				div.setAttribute('class','resourceCounter');
				div.appendChild(document.createTextNode(count + 1));
				td.appendChild(div);
				tr.appendChild(td);
				if(searchObject.requestType == 'national' || searchObject.requestType == 'international') {
					resource['Local'] = null;
				}

				if(resource['Local'] == 'Y' && resource['Distance'] == -1) {
					var color = 'red';
				} else if(resource['Country'] == "Canada" || resource['Country'] == "CANADA") {
					var color= "rgba(0,200,0,1)";
				} else if (resource['NonLGBT'] == "Y") {
					var color= 'blue';
				} else {
					var color = 'black';
				}


				for (var key in resource) {	
					switch(key) {
						case 'Name':
						case 'Type1':
						case 'Type2':
						case 'Location':
						case 'Zip':
						case 'Distance':
							const resourceValue = decodeHTMLEntities(resource[key]);
							td = document.createElement('td');
							div = document.createElement('div');
							div.setAttribute('class',key);
							div.style.color = color;
							if (key == 'Distance' && resource['Local'] == "Y" && resource['Distance'] == -1) {
								div.appendChild(document.createTextNode('n/a'));
							} else {
								div.appendChild(document.createTextNode(resourceValue));
							}
							td.appendChild(div);
							tr.appendChild(td);
							break;
							
							
						case 'Closed':
							if(resource[key] == "Y") {
								tr.setAttribute("class" ,"List Closed");
							}
							break;
					}
				}
			
				if (resource['Description']) {
					title = resource['Description'];
				}
				tr.title = resource['Name'] + "\n\n" + title;
				tr.onclick = function() {
					self.displayRecord(bySortOrder, count);
				};
				table.appendChild(tr);
			});
		
			frag.appendChild(table);
			serverResponse.appendChild(frag);

			//Hide resourceDetail form and show resourceList
			viewControl('resourceList');			
			resourceCount = document.getElementById("resourceListCount");
			resourceCount.innerHTML = "Resources Found: " + bySortOrder.length;	
		
			//Show the Category for the Displayed List
			var resourceListCategory = document.getElementById("resourceListCategory");
			resourceListCategory.innerHTML = "Category: " + searchObject.category;
			serverResponse.scrollTop -= serverResponse.scrollHeight;

			//Set CopyableList Button
			var copyableList = document.getElementById("copyableList");
			if (copyableList) {
				copyableList.onclick = searchObject.copyableList;
			}

			// Auto-open the subcategory dropdown if a trigger button was clicked
			if (searchObject.selectToOpen) {
				var selectToOpen = searchObject.selectToOpen;
				setTimeout(function() {
					selectToOpen.style.pointerEvents = "auto";
					selectToOpen.focus();
					if (selectToOpen.showPicker) {
						try {
							selectToOpen.showPicker();
						} catch(e) {
							// showPicker may fail in some contexts, ignore
						}
					}
				}, 50);
			}
		};
	
		this.copyableList = function() {
			var resources = newSearch.results.Resources;
			var bySortOrder = self.sortBy(resources, newSearch.sortOrder);				
			top.copyableListWindow=window.open('','Resource List',
				'width=550,height=550'
				+',menubar=0'
				+',toolbar=1'
				+',status=0');
			top.copyableListWindow.document.writeln(
			  '<html><head><title>Resources</title></head>'
			   +'<body>'
			);
		   
			bySortOrder.forEach(function(entry) {
				var writeableLine = "";
				var resource = entry.resource;
				if (resource.Distance == 1) {
					writeableLine = "<u>" + resource.Distance + " Mile";
				} else if (resource.Distance != "N/A") {
					writeableLine = "<u>" + resource.Distance + " Miles";
				}
				writeableLine += "</u><br />";
				writeableLine += resource.Name + "<br />";
				if (resource.Address1) {
					writeableLine += resource.Address1 + "<br />";
				}
				if (resource.Address2) {
					writeableLine += resource.Address2 + "<br />";
				}
				writeableLine += resource.Location + ", " + resource.Zip + "<br />";
				if (resource.Phone) {
					writeableLine += "Phone: " + resource.Phone + "<br />";
				}
				if (resource.Fax) {
					writeableLine +=  "Fax: &nbsp;&nbsp;&nbsp;&nbsp;" + resource.Fax + "<br />";
				}
				if (resource.Internet) {
					writeableLine +=  "Email: " + resource.Internet + "<br />";
				}
				if (resource.WWWEB) {
					writeableLine +=  "Web: " + resource.WWWEB + "<br />";
				}
				if (resource.WWWEB2) {
					writeableLine +=  "Web: " + resource.WWWEB2 + "<br />";
				}
				if (resource.WWWEB3) {
					writeableLine +=  "Web: " + resource.WWWEB3 + "<br />";
				}
				writeableLine +=  "Categories: " + resource.Type1 + "<br />";
				if (resource.Type2) {
					writeableLine +=  "&nbsp;&nbsp&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" + resource.Type2 + "<br />";
				}
				if (resource.Type3) {
					writeableLine +=  "&nbsp;&nbsp&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" + resource.Type3 + "<br />";
				}
				if (resource.Type4) {
					writeableLine +=  "&nbsp;&nbsp&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" + resource.Type4 + "<br />";
				}
				if (resource.Description) {
					writeableLine +=  resource.Description + "<br />";
				}
				top.copyableListWindow.document.writeln(
					writeableLine + "<br /><br />"
				)
			});

			top.copyableListWindow.document.writeln(
			   '</body></html>'
			);
			top.copyableListWindow.document.close();
		};

		this.sortBy = function(searchObject, sortKey) {
			var arr = [];
			for (var key in searchObject) {
				var obj = searchObject[key];
				var idnum = key;
	
				for (key in obj) {
					var sortData = obj[key];
					if (key == sortKey) {
						arr.push({
							'sortKey':	sortData,
							'distance':	obj.Distance,
							'name':		obj.Name,
							'resource':	obj});
					}
				}
			}
	
			if(sortKey === 'Distance') {
				arr.sort(function (a, b) {
					if(a.distance === b.distance) { 
						var x = a.name.toLowerCase();
						var y = b.name.toLowerCase();

						return x < y ? -1 : x > y ? 1 : 0;
					}
					return a.distance - b.distance; 
				});
			} else {	
				arr.sort(function (a, b) {
					if(a.sortKey === b.sortKey) { 
						if(a.distance === b.distance) { 
							var x = a.name.toLowerCase();
							var y = b.name.toLowerCase();

							return x < y ? -1 : x > y ? 1 : 0;
						}
						return a.distance - b.distance; 
					}

					var x = a.sortKey.toLowerCase();
					var y = b.sortKey.toLowerCase();

					return x < y ? -1 : x > y ? 1 : 0;
				});
			}
			this.bySortOrder = arr;
			return arr; // returns array
		};
	
		this.displayRecord = function(searchObject, indexNo, idNum) {
			if(!idNum && searchObject.length > 0) {
				if(searchObject[indexNo]) {
					var resource = searchObject[indexNo].resource;
					var idNum = resource.idnum;
					const detailScreenCount = document.getElementById('detailScreenCount');
					var recordNo = indexNo + 1;
					detailScreenCount.innerHTML = "Record " + recordNo + " of " + searchObject.length;
					this.indexNo = indexNo;
				}
			} else {
				field = document.getElementById("searchParameters");
				field.innerHTML = "";				
				const span = document.createElement("span");
				span.style.fontSize = "150%";		
				span.style.fontWeight = "bold";	
				span.innerHTML = "ID NUM: " + idNum;
				span.style.marginRight = "10px";
				field.appendChild(span);
			}
			const detailScreenCategory = document.getElementById('detailScreenCategory');
			const resourceListCategory = document.getElementById('resourceListCategory');
			detailScreenCategory.innerHTML = resourceListCategory.innerHTML;
			
			self.getResourceDetail(idNum);
		};
	
		this.getResourceDetail = function(idnum) {
			var url = "DetailLocate.php";
			var params = "idnum=" + encodeURIComponent(idnum);
			var searchRequest = new AjaxRequest(url, params, self.displayRecordResults, this);
		}
	
		this.displayRecordResults = function(results, searchObject) {	
			var resource = JSON.parse(results); 
			if(resource.length == 0) {
				alert("No results");
				return;
			}

			viewControl('detailPane');

			if(resource['Local'] == "Y") {
				var color = 'red';
			} else if (resource['Country'] == "Canada" || resource['Country'] == "CANADA") {
				var color= 'green';
			} else if (resource['NonLGBT'] == "Y") {
				var color= 'blue';
			} else {
				var color = 'black';
			}
		
		
			for (var key in resource) {
				const fieldID = titleCase(key);
				field = document.getElementById("d" + fieldID);
				if (field) {
					switch(key) {
						case 'NAME':
						case 'NAME2':
						case 'ADDRESS1':
						case 'ADDRESS2':
						case 'CITY':
						case 'STATE':
						case 'ZIP':
						case 'CONTACT':
						case 'PHONE':
						case 'EXT':
						case 'HOTLINE':
						case 'FAX':
						case 'DESCRIPT':
						case 'WWWEB':
						case 'WWWEB2':
						case 'WWWEB3':
						case 'INTERNET':
						case 'MAILPAGE':
						case 'IDNUM':
						case 'EDATE':
							field.value = resource[key];
							break;

						case 'NOTE':
							field.innerHTML = resource[key].replace(/\n/g, "\n").replace(/\r/g, "\r").replace(/\t/g, "\t");				
							break;

						case 'SHOWMAIL':					//SHOWMAIL is actually hidemail flag, so Y means not to show mai address.  
							if(resource[key] == "Y") {
								field.checked = true;
							} else { 
								field.checked = false;
							}
							break;

						case 'WEBSITE':
							if(resource[key] == "Y") {
								field.checked = true;
							} else { 
								field.checked = false;
							}
							break;

						case 'CNATIONAL':
							if(resource[key] == "Y") {
								field.checked = true;
							} else { 
								field.checked = false;
							}
							break;

						case 'GIVE_ADDR':				//GIVE_ADDR is actually hide address, so "Y" means to hide the address
							if(resource[key] == "Y") {
								field.checked = true;
							} else { 
								field.checked = false;
							}
							break;

						case 'CLOSED':
							if(resource[key] == "Y") {
								field.checked = true;
							} else { 
								field.checked = false;
							}
							break;

						case 'local':
							if(resource[key] == "Y") {
								field.checked = true;
							} else { 
								field.checked = false;
							}
							break;
				
						case 'NONLGBT':
							if(resource[key] == "Y") {
								field.checked = true;
							} else {
								field.checked = false;
							}
							break;

						case 'YOUTHBLOCK':
							if(resource[key] == "1" || resource[key] == 1) {
								field.checked = true;
							} else {
								field.checked = false;
							}
							break;


						case 'TYPE1':
						case 'TYPE2':
						case 'TYPE3':
						case 'TYPE4':
						case 'TYPE5':
						case 'TYPE6':
						case 'TYPE7':
						case 'TYPE8':
							var selectedCategory = findTypeSelectionIndex(resource[key]);
							field.options.selectedIndex = selectedCategory;
							break;

						case 'Country':
							const countryIndex = findCountrySelectIndex(resource[key]);
							document.detailForm.countries.options.selectedIndex = countryIndex;
							break;

						default:
							field.innerHTML = "";
							field.appendChild(document.createTextNode(resource[key]));
							break;
					}				
	
					if (field.id == 'Name') {
						field.style.color = color;
						field.title = resource[key];
					}
				}
			}
	
			var first = document.getElementById('resourceDetailFirstButton');
			first.onclick = function () {self.displayRecord(self.bySortOrder, 0);};
			var pre = document.getElementById('resourceDetailPreviousButton');
			pre.onclick = function () {self.displayRecord(self.bySortOrder, self.indexNo - 1);};
			var next = document.getElementById('resourceDetailNextButton');
			next.onclick = function () {self.displayRecord(self.bySortOrder, self.indexNo + 1);};

			var resourceShowListButton = document.getElementById('resourceShowListButton');
			if(self.bySortOrder.length > 1) {	
				resourceShowListButton.style.visibility = null;
				resourceShowListButton.onclick = function () {viewControl('resourceList');};
			} else {
				resourceShowListButton.style.visibility = 'hidden';
			}
			
			const duplicateCurrentRecordButton = document.getElementById('duplicateCurrentRecordButton');
			duplicateCurrentRecordButton.onclick = duplicateCurrentRecord;
			
			
			const deleteButton = document.getElementById('deleteButton');
			deleteButton.onclick = function() {
				deleteRecord(document.getElementById("dIdnum").value);
			};
					
			document.getElementById("dName").focus();

			// Store coordinates and geolocation data from database for map display and geo info modal
			storedLatitude = parseFloat(resource['LATITUDE']) || null;
			storedLongitude = parseFloat(resource['LONGITUDE']) || null;
			storedGeoAccuracy = resource['GEOACCURACY'] || null;
			storedResourceName = resource['NAME'] || '';

			// Build full address for geo info modal
			var addressParts = [];
			if (resource['ADDRESS1']) addressParts.push(resource['ADDRESS1']);
			if (resource['ADDRESS2']) addressParts.push(resource['ADDRESS2']);
			if (resource['CITY']) addressParts.push(resource['CITY']);
			if (resource['STATE']) addressParts.push(resource['STATE']);
			if (resource['ZIP']) addressParts.push(resource['ZIP']);
			if (resource['COUNTRY'] && resource['COUNTRY'] !== 'USA') addressParts.push(resource['COUNTRY']);
			storedAddress = addressParts.join(', ');

			// Display map using stored coordinates if available
			if (storedLatitude && storedLongitude &&
			    storedLatitude !== 0 && storedLongitude !== 0) {
				displayMapWithCoordinates(storedLatitude, storedLongitude, resource['NAME']);
			}

			// Update the geo accuracy overlay on the map
			updateGeoAccuracyOverlay();

			// If no stored coordinates, map will be empty until record is saved
		    //Set Links to Bring Streetview to the front and send to the back of the display
			streetviewLink = document.getElementById("lStreetview");
			streetviewLink.onclick=	showStreetViewResults;

			// Set up Geo Info button click handler
			var geoInfoLink = document.getElementById("lGeoInfo");
			if (geoInfoLink) {
				geoInfoLink.onclick = showGeoInfoModal;
			} 
	
			detailviewLink = document.getElementById("ldetailview");
			detailviewLink.onclick=function() {
				TopPane("DetailPane");
			};
			TopPane("DetailPane");

			return;
		};
	};
	
	
	//Detail Panel Control Button Section 
	
	
	
	this.FailedToSaveOrCancel = function() {
       alert ("You have not saved the changes you've made to this record.  Please save the changes or press cancel.");
	};


	this.addNewRecord = function() {
		if (changedForm == 1) {
			FailedToSaveOrCancel();
			return;
		}
	
		clearForm();
		field = document.getElementById("searchParameters");
		field.innerHTML = "";						
		field.innerHTML = "New Record";
		
		document.getElementById('dName').focus();
	};



	function clearControls() {
	}
	
	
	function addBulkEmailButton() {
        var button = document.getElementById("resourceDetailBulkEmailButton");      
		button.onclick = bulkEmails;
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

	function sendEmail(idnum) {
		var url = "bulkEmail.php";
		var params = "idnum=" + idnum;

		bulkEmailRequest = createRequest();

		bulkEmailRequest.open("POST", url, false);
		bulkEmailRequest.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
		bulkEmailRequest.send(params);
	
		if (bulkEmailRequest.status == 200) {
			var status =  bulkEmailRequest.responseText;
			return status;
		} else {
			return "Bulk Email AJAX Request Status Error";
		}				
	}


	function bulkEmails() {
	
		var okToSend = confirm ("Send an email to every resource listed?");

		if (okToSend==true) {
			var url = "preBulk.php";
			var bulkProgress = false;
			bulkEmailRequest = createRequest();

			bulkEmailRequest.open("POST", url, false);
			bulkEmailRequest.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
			bulkEmailRequest.send(params);
	
			if (bulkEmailRequest.status == 200) {
				var status =  bulkEmailRequest.responseText;
				if (status != "ERROR") {
					bulkProgress = markBulkEmails(status);
				} else {
					alert("Error sending bulk emails.");
				}	
				
			} else {
				return "Bulk Email AJAX Request Status Error";
			}
		} else {
			alert ("No Emails Were Sent.");
		}				
	}
	
	
	function markBulkEmails(bulkSetID) {
		var resources = adminSearch.resources;
		var resourceList = [];

		Object.entries(resources).forEach(function(record) {
			if(record[1].Closed == "N") {
				resourceList.push(record[1].Idnum);
			}
		});
		
		resourceListObject = JSON.stringify(resourceList);

		var url = "markBulkEmails.php";
		var params = "bulkSetID=" + bulkSetID + "&resources=" + resourceListObject;

		var bulkSendResults = function(results, searchObject) {
			if(results == "OK") {
				alert("EMAILING IN PROCESS.  \n\n The emails you selected are being sent out.  The VCC will email you with the final results.  \n\n You can go on using the VCC or log off.  It will not affect the emails being sent out.");
			} else {
				alert("Error marking emails to send: \n\n" + results);
			}
		}

		var markBulkEmailRequest = new AjaxRequest(url, params, bulkSendResults, this);		

	}		

	
	
/*	function markBulkEmails(bulkSetID) {
		var resources = adminSearch.resources;
		bulkTest(resources);
		return;
	
		Object.entries(resources).forEach(function(record) {
			if(record[1].Closed !== "Y") {

				var url = "markBulkEmails.php";
				var params = "bulkSetID=" + bulkSetID + "&resourceID=" + record[1].Idnum;

				markBulkEmailRequest = createRequest();

				markBulkEmailRequest.open("POST", url, false);
				markBulkEmailRequest.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
				markBulkEmailRequest.send(params);
	
				if (markBulkEmailRequest.status == 200) {
					var status =  markBulkEmailRequest.responseText;
					if (status) {
						alert("Error Marking Emails to Send.  Call Tim.");
						return false;
					}
				}
			}
		});
		return true;	
	}
	

	
	
	
	
	function sendBulkEmails(bulkSetID) {

		var url = "sendBulkEmailSet.php";
		var params = "bulkSetID=" + bulkSetID;

		sendBulkEmailSetRequest = createRequest();

		sendBulkEmailSetRequest.open("POST", url, false);
		sendBulkEmailSetRequest.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
		sendBulkEmailSetRequest.send(params);

		if (sendBulkEmailSetRequest.status == 200) {
			var status =  sendBulkEmailSetRequest.responseText;
			return status;	

			if (!status) {
				alert("Error Sending Marked Emails.  Call Tim.");
				return false;
			}
		}
		return status;	
	}

*/	


	function clearForm() {
		if (changedForm == 1) {
			FailedToSaveOrCancel();
			return;
		}
	
		document.detailForm.reset();	
		document.getElementById("dStreetview").innerHTML = "";

		document.getElementById("dIdnum").innerHTML = "New";
		document.getElementById("dEdate").innerHTML = "";
		document.getElementById("dNote").innerHTML = "";
	
		field = document.getElementById("searchParameters");
		field.innerHTML = "";						
		
		document.getElementById('dName').focus();	
	}
	

	function duplicateCurrentRecord() {
		if (changedForm == 1) {
			FailedToSaveOrCancel();
			return;
		}
			
		document.getElementById("dIdnum").value = "New";
		document.getElementById("dEdate").value = "";
	
		field = document.getElementById("searchParameters");
		field.innerHTML = "";
		const span = document.createElement("span");
		span.style.fontSize = "150%";		
		span.style.fontWeight = "bold";	
		span.style.color = "yellow";			
		span.innerHTML = "COPIED RECORD";
		field.appendChild(span);
		var resourceListCategory = document.getElementById("resourceListCategory");
		resourceListCategory.innerHTML = "COPIED RECORD";	
	}

	
	
	function deleteRecord(idnum) {

		var okToDelete = confirm ("Delete the record \n with ID Number: " + idnum + "?");

		if (okToDelete==true) {

			var url = "deleteRecord.php";
			var params = "idnum=" + encodeURIComponent(idnum);
			var searchRequest = new AjaxRequest(url, params, confirmDeleteRecord, this);		
		} 
	}
	
  
	function confirmDeleteRecord(results, searchObject) {	
		if(results.trim() == 'OK') {
			clearForm();
			viewControl('searchBox');
		} else {
			alert("Problem deleting this record.  \n\n" + results + "\n\nPlease pull up the record and check to see if it deleted properly.");
		}
	}



	this.saveRecord = function() {
  
		validated = validateRecord();

		if(!validated) {
			return
		}
	};




	function completeSave(confirmRecordSave) {
		if(confirmRecordSave) {
			alert("Save Completed Successfully.");
		}
		changedForm = 0;
		clearForm();

		viewControl('searchBox');
	}




	function validateRecord() {     
	
		NAME = document.getElementById("dName").value.trim();
		NAME2 = document.getElementById("dName2").value.trim();
		CONTACT = document.getElementById("dContact").value.trim();
		ADDRESS1 = document.getElementById("dAddress1").value.trim();
		ADDRESS2 = document.getElementById("dAddress2").value.trim();
		CITY = document.getElementById("dCity").value.trim();
		STATE = document.getElementById("dState").value.trim();
		ZIP = document.getElementById("dZip").value.trim();
		idnum = document.getElementById("dIdnum").value.trim();

		COUNTRYElement = document.detailForm.countries;
		COUNTRY = countries[COUNTRYElement.selectedIndex];
	
		if(COUNTRY == " " || !COUNTRY) {
			var countryText = "NULL";
		} else {
			var countryText = COUNTRY;
		}
	
		TYPE1Element = 	document.detailForm.Type1SelectMenu;
		TYPE2Element = 	document.detailForm.Type2SelectMenu;
		TYPE3Element = 	document.detailForm.Type3SelectMenu;
		TYPE4Element = 	document.detailForm.Type4SelectMenu;
		TYPE5Element = 	document.detailForm.Type5SelectMenu;
		TYPE6Element = 	document.detailForm.Type6SelectMenu;
		TYPE7Element = 	document.detailForm.Type7SelectMenu;
		TYPE8Element = 	document.detailForm.Type8SelectMenu;
							
		TYPE1 = categories[TYPE1Element.selectedIndex];
		TYPE2 = categories[TYPE2Element.selectedIndex];
		TYPE3 = categories[TYPE3Element.selectedIndex];
		TYPE4 = categories[TYPE4Element.selectedIndex];
		TYPE5 = categories[TYPE5Element.selectedIndex];	
		TYPE6 = categories[TYPE6Element.selectedIndex];
		TYPE7 = categories[TYPE7Element.selectedIndex];
		TYPE8 = categories[TYPE8Element.selectedIndex];
		
		const typeSelected = 	TYPE1Element.selectedIndex +
								TYPE2Element.selectedIndex +
								TYPE3Element.selectedIndex +
								TYPE4Element.selectedIndex +
								TYPE5Element.selectedIndex +
								TYPE6Element.selectedIndex +
								TYPE7Element.selectedIndex +
								TYPE8Element.selectedIndex;
		if(!typeSelected) {
			alert("There are no categories selected for this resource.");
			return false;		
		}
	
		if(idnum == "New") {
			EDATE = "Update";
		} else if (document.detailForm.updateEdate.checked) {
			EDATE = "Update";
		} else {
			EDATE = "DO NOTHING";
		}

        if(CONTACT.length > 100) {
			alert("The CONTACT field cannot be longer than 100 characters.");
			return false;
		}

		//Ensure that Critical Fields are completed	
		var newZIP = ZIP.trim();	
		if (newZIP.length < 5 && (!COUNTRY || COUNTRY == "" || COUNTRY == " ")) {
			alert("The ZIP CODE field must be at least 5 characters long.");
			return false;
		}
	
		if (NAME == "" || NAME == " ") {
			alert("No NAME has been entered for this resource.");
			return false;
		}
	
		PHONE = document.getElementById("dPhone").value.trim();
		EXT = document.getElementById("dExt").value.trim();
		HOTLINE = document.getElementById("dHotline").value.trim();
		FAX = document.getElementById("dFax").value.trim();
		INTERNET = document.getElementById("dInternet").value.trim();
	

		hideEmailElement = document.getElementById("dShowmail");   
		if (hideEmailElement.checked) {
			SHOWMAIL = "Y";
		} else {
			SHOWMAIL = "N";
		}


		DESCRIPT = document.getElementById("dDescript").value;
		WWWEB = document.getElementById("dWwweb").value;
		WWWEB2 = document.getElementById("dWwweb2").value;
		WWWEB3 = document.getElementById("dWwweb3").value;

		dhideaddressElement = document.getElementById("dGive_addr");   
		if (dhideaddressElement.checked) {
			GIVE_ADDR = "Y";
		} else {
			GIVE_ADDR = "N";
		}

		dnationalElement = document.getElementById("dCnational");   
		if (dnationalElement.checked) {
			CNATIONAL = "Y";
		} else {
			CNATIONAL = "N";
		}

		localElement = document.getElementById("dLocal");   
		if (localElement.checked) {
			LOCAL = "Y";
		} else {
			LOCAL = "N";
		}

		dclosedElement = document.getElementById("dClosed");   
		if (dclosedElement.checked) {
			CLOSED = "Y";
		} else {
			CLOSED = "N";
		}
   
		MAILPAGE = document.getElementById("dMailpage").value;
	
		STATEWIDE = "N";
		
		WEBSITE = document.getElementById("dWebSite").value;
		dpublishElement = document.getElementById("dWebSite");   
		if (dpublishElement.checked) {
			WEBSITE = "Y";
		} else {
			WEBSITE = "N";
		}
	
	
		dnonLGBTElement = document.getElementById("dNonlgbt");
		if (dnonLGBTElement.checked) {
			NonLGBT = "Y";
		} else {
			NonLGBT = "N";
		}

		dYouthBlockElement = document.getElementById("dYouthblock");
		if (dYouthBlockElement.checked) {
			YOUTHBLOCK = "Y";
		} else {
			YOUTHBLOCK = "N";
		}

		AREACODE = PHONE.substr(0,3);
		NOTE = encodeURIComponent(document.getElementById("dNote").value);
		LinkableZip = ZIP.substring(0,5);

		var MapAddress = ADDRESS1 + " " + ADDRESS2 + " " + CITY + ", " + STATE + " " + ZIP;
		
		fields1 = "IdNum=" + idnum + 
				  "&NAME=" + encodeURIComponent(NAME) + 
				  "&NAME2=" + encodeURIComponent(NAME2) + 
				  "&CONTACT=" + encodeURIComponent(CONTACT) + 
				  "&ADDRESS1=" + encodeURIComponent(ADDRESS1) + 
				  "&ADDRESS2=" + encodeURIComponent(ADDRESS2) + 
				  "&CITY=" + encodeURIComponent(CITY) + 
				  "&STATE=" + encodeURIComponent(STATE) + 
				  "&ZIP=" + encodeURIComponent(ZIP) + 
				  "&COUNTRY=" + encodeURIComponent(countryText) + 
				  "&TYPE1=" + encodeURIComponent(TYPE1);
		
		fields2 = "&TYPE2=" + encodeURIComponent(TYPE2) + 
				  "&TYPE3=" + encodeURIComponent(TYPE3) + 
				  "&TYPE4=" + encodeURIComponent(TYPE4) + 
				  "&TYPE5=" + encodeURIComponent(TYPE5) + 
				  "&TYPE6=" + encodeURIComponent(TYPE6) + 
				  "&TYPE7=" + encodeURIComponent(TYPE7) + 
				  "&TYPE8=" + encodeURIComponent(TYPE8) + 
				  "&EDATE=" + encodeURIComponent(EDATE) + 
				  "&PHONE=" + encodeURIComponent(PHONE) + 
				  "&EXT=" + encodeURIComponent(EXT) + 
				  "&HOTLINE=" + encodeURIComponent(HOTLINE) + 
				  "&FAX=" + encodeURIComponent(FAX) + 
				  "&INTERNET=" + encodeURIComponent(INTERNET) + 
				  "&SHOWMAIL=" + encodeURIComponent(SHOWMAIL) + 
				  "&DESCRIPT=" + encodeURIComponent(DESCRIPT) + 
				  "&WWWEB=" + encodeURIComponent(WWWEB) + 
				  "&WWWEB2=" + encodeURIComponent(WWWEB2) + 
				  "&WWWEB3=" + encodeURIComponent(WWWEB3);
		
		fields3 = "&GIVE_ADDR=" + encodeURIComponent(GIVE_ADDR) +
				  "&CNATIONAL=" + encodeURIComponent(CNATIONAL) +
				  "&LOCAL=" + encodeURIComponent(LOCAL) +
				  "&CLOSED=" + encodeURIComponent(CLOSED) +
				  "&MAILPAGE=" + encodeURIComponent(MAILPAGE) +
				  "&STATEWIDE=" + encodeURIComponent(STATEWIDE) +
				  "&WEBSITE=" + encodeURIComponent(WEBSITE) +
				  "&AREACODE=" + encodeURIComponent(AREACODE) +
				  "&NOTE=" + encodeURIComponent(NOTE) +
				  "&NONLGBT=" + encodeURIComponent(NonLGBT) +
				  "&YOUTHBLOCK=" + encodeURIComponent(YOUTHBLOCK);

		fields = fields1 + fields2 + fields3;
		updateDatabase(fields, MapAddress);
		return true;
	}
		


	function updateDatabase(fields, MapAddress) {
		const idnum = document.getElementById("dIdnum").value.trim();
		if (!idnum || idnum == "New") {
			var url = "insertRecord.php";
		} else {
			var url= "updateRecord.php";
		}

		var params = fields;
		var searchRequest = new AjaxRequest(url, params, confirmRecordUpdate, this);		
	}    
	
	
	
	
	function confirmRecordUpdate(result, resultObject) {

		var response = JSON.parse(result);

		if(response.status !== "OK") {
			alert(response.message);
			return;
		}

		// Note: Geocoding is now handled server-side in updateRecord.php
		// No need to call updateLocationData() - coordinates are already saved

		completeSave(true);
	}


	this.cancelNewRecord = function() {
		changedForm = 0;
		document.detailForm.reset();
		viewControl('searchBox');	
	};



	function detailFormZipLookup()  {
		zipLookup(document.getElementById("dzip").value);
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
	
	
	function printProfiles () {

		var fromDateValue = new Date(document.getElementById("SearchFromDate").value);
		var toDateValue = new Date(document.getElementById("SearchToDate").value);

		var fromDate = fromDateValue.toMysqlDate();
		var toDate = toDateValue.toMysqlDate();

		if (fromDateValue == "Invalid Date") {
			alert ("You entered an invalid date in the 'From' field.  Please entere a valid date.");
			document.getElementById("SearchFromDate").focus();
			return;
		}
		if (toDateValue == "Invalid Date") {
			alert ("You entered an invalid date in the 'To' field.  Please entere a valid date.");
			document.getElementById("SearchToDate").focus();
			return;
		}

		url = "profile.php?From=" + fromDate + "&To=" + toDate;
		mywindow = window.open (url , "Profiles", "resizable=yes,titlebar=1,toolbar=1,scrollbars=yes,status=no,height=840,width=750,addressbar=0,menubar=0,location=0");  
		mywindow.moveTo(250,250);
		mywindow.focus();
	}

	
	
	
	//Streetview Functions

	/**
	 * Display map using stored coordinates (no geocoding needed)
	 * This uses coordinates from the database instead of re-geocoding the address
	 */
	function displayMapWithCoordinates(lat, lng, name) {
		if (map) {
			try {
				map.destroy();
			} catch {
			}
		}

		var location = new mapkit.Coordinate(lat, lng);

		const center = new mapkit.Coordinate(lat, lng),
		  span = new mapkit.CoordinateSpan(0.0085, 0.0085),
		  region = new mapkit.CoordinateRegion(center, span);

		map = new mapkit.Map("dStreetview", {
		  region: region,
		  showsCompass: mapkit.FeatureVisibility.Hidden,
		  showsZoomControl: true,
		  showsMapTypeControl: false
		});

		const annotation = new mapkit.MarkerAnnotation(location, {
		  title: name || document.getElementById("dName").value,
		  color: "green",
		  displayPriority: 1000
		});
		map.addAnnotation(annotation);
	}



	
	function showStreetViewResults() {
		if (changedForm == 0) {
			// Form unchanged - use stored coordinates from database
			if (storedLatitude && storedLongitude &&
			    storedLatitude !== 0 && storedLongitude !== 0) {
				displayMapWithCoordinates(storedLatitude, storedLongitude);
			}
			TopPane("Streetview");
		} else {
			// Form was changed - re-geocode using server-side Apple Maps API
			var Address1 = document.getElementById("dAddress1").value;
			var Address2 = document.getElementById("dAddress2").value;
			var City = document.getElementById("dCity").value;
			var State = document.getElementById("dState").value;
			var Zip = document.getElementById("dZip").value;

			var MapAddress = Address1 + " " + Address2 + ", " + City + " " + State + " " + Zip;
			serverGeocodeAddress(MapAddress, Zip);

			TopPane("Streetview");
		}
	}

	/**
	 * Geocode address using server-side Apple Maps API
	 * This ensures preview matches what will be saved
	 */
	function serverGeocodeAddress(address, zip) {
		var xhr = new XMLHttpRequest();
		xhr.open("POST", "geocodePreview.php", true);
		xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

		xhr.onreadystatechange = function() {
			if (xhr.readyState === 4) {
				if (xhr.status === 200) {
					try {
						var result = JSON.parse(xhr.responseText);
						if (result.success && result.latitude && result.longitude) {
							displayMapWithCoordinates(result.latitude, result.longitude);
						} else {
							console.warn("Server geocoding failed:", result.error);
						}
					} catch (e) {
						console.error("Error parsing geocode response:", e);
					}
				} else {
					console.error("Geocode request failed:", xhr.status);
				}
			}
		};

		xhr.send("address=" + encodeURIComponent(address) + "&zip=" + encodeURIComponent(zip));
	}

	function TopPane(Pane) {
		var detailPane = document.getElementById("block3detailPane");
		var StreetviewPane = document.getElementById("StreetviewPane");

		if(Pane == "DetailPane") {
			detailPane.style.visibility = 'visible';
			StreetviewPane.style.visibility = 'hidden';

		} else if (Pane == 'Streetview') {
			detailPane.style.visibility = 'hidden';
			StreetviewPane.style.visibility = 'visible';

		} else { 
			detailPane.style.visibility = 'visible';
			StreetviewPane.style.visibility = 'hidden';
		}

	}
	
// END Streetview Functions
	
}	
