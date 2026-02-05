//  RESOURCE SEARCH OBJECT AND FUNCTIONS

function Search(zip, category, distance, city, state, name, requestType) {
	this.zipCode = 		zip;
	this.category = 	category;
	this.range = 		distance;
	this.city = 		city;
	this.state =		state;
	this.name = 		name;
	this.requestType =	requestType;
	this.params = 		'';
	this.sortOrder = 	'Distance';
	this.url = 			'../zipOnly.php';
	this.countriesList = 	new Array();


	this.searchRequest = function() {
		if(!this.requestType || this.requestType == "") {
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
			} else {
				alert("Please complete the search criteria");
				return false;
			}
		}
		this.postSearch();
		return;
	};
	

	this.postSearch =	function () {
		this.params = "ZipCode=" + escape(this.zipCode) + "&Range=" + escape(this.range) + 
			"&City=" + escape(this.city) + "&State=" + escape(this.state) + "&Name=" + 
			escape(this.name) + "&Category=" + escape(this.category) + "&SearchType=" + escape(this.requestType);
		var searchRequest = new AjaxRequest(this.url, this.params, this.displayResults, this);
	};

	this.displayResults = function (results, searchObject) {	
		if(!searchObject) {
			searchObject = this;
		}
		
		if(!searchObject.results) {
			if(results == "INVALID") {
				alert("No Such ZipCode Found.  Please try another Zip Code.");
				return;
			} else if (results == "NONE") {
				alert("No results found.");
				return;
			} else if (results == "No ZipCode Located") {
				alert("No Zip Code found for " + searchObject.city + ", " + searchObject.state + ".");
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

			field = document.getElementById("searchParameters");
			field.innerHTML = "";						
			field.innerHTML = searchParameters.place + "<br />" + searchParameters.range;
			
		}

		var resources = searchObject.results.Resources;
		var bySortOrder = sortBy(resources, searchObject.sortOrder);				
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
						td = document.createElement('td');
						div = document.createElement('div');
						div.setAttribute('class',key);
						div.style.color = color;
						if (key == 'Distance' && resource['Local'] == "Y" && resource['Distance'] == -1) {
							div.appendChild(document.createTextNode('n/a'));
						} else {
							div.appendChild(document.createTextNode(resource[key]));
						}
						td.appendChild(div);
						tr.appendChild(td);
						break;
				}
			}
			
			if (resource['Description']) {
				title = resource['Description'];
			}
			tr.title = resource['Name'] + "\n\n" + title;
			tr.onclick = function() {displayRecord(bySortOrder, count);};
			table.appendChild(tr);
		});
		
		frag.appendChild(table);
		serverResponse.appendChild(frag);
		displayRecord(bySortOrder,0);


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
	};
	
	this.copyableList = function() {
		var resources = newSearch.results.Resources;
		var bySortOrder = sortBy(resources, newSearch.sortOrder);				
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
}


	
function displayRecord(searchObject, indexNo) {
	var resource = searchObject[indexNo].resource;
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
		field = document.getElementById("resourceDetail" + key);
		if (field) {
			if(field.tagName === "INPUT" || field.tagName === "TEXTAREA") {
				field.value = resource[key];
			} else {
				if(key=="WWWEB" || key=="WWWEB2" || key=="WWWEB3") {
					if(resource[key]) {
						var web = document.createElement("a");
						web.appendChild(document.createTextNode(resource[key]));
						web.setAttribute("href","http://" + resource[key]);
						web.setAttribute("target","_blank");
						field.innerHTML = "";
						field.setAttribute("title",resource[key]);
						field.appendChild(web);
					} else {
						field.innerHTML = "";
						field.appendChild(document.createTextNode(" "));
					}
				} else if (key == "idnum" ) {
					var idField = "Id. No.: " + resource[key];
					field.innerHTML = "";
					field.appendChild(document.createTextNode(idField));					
				} else if (key == "Note" ) {
				    resource[key] = resource[key].replace(/\n/g, "\n").replace(/\r/g, "\r").replace(/\t/g, "\t");				
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
	
			if (field.id == 'resourceDetailName') {
				field.style.color = color;
				field.title = resource[key];
			}
			if (field.id == 'resourceDetailDistance') {
				if(resource[key] == -1) {
					field.innerHTML = 'n/a';
				} else if(resource[key] == 1 && searchObject.requestType != 'national') {
					field.appendChild(document.createTextNode(" Mile"));
				} else if (resource[key] != "N/A") {
					field.appendChild(document.createTextNode(" Miles"));
				}
			}
		}
		if (resource[key]) {
			switch(key) {
				case 'Country':			
					resource[key] = resource[key].toUpperCase();
				case 'Address2':
				case 'Location':
				case 'Zip':			
					document.getElementById("resourceDetailAddress1").appendChild(document.createTextNode(resource[key] + " "));
					break;
			}
			switch(key) {		
				case 'Address1':
				case 'Address2':
					document.getElementById("resourceDetailAddress1").appendChild(document.createElement("br"));
					break;
			}
		}
	}
	
	var first = document.getElementById('resourceDetailFirstButton');
	first.onclick = function () {displayRecord(searchObject, 0);};
	var pre = document.getElementById('resourceDetailPreviousButton');
	pre.onclick = function () {displayRecord(searchObject, indexNo - 1);};
	var next = document.getElementById('resourceDetailNextButton');
	next.onclick = function () {displayRecord(searchObject, indexNo + 1);};
	var resourceShowListButton = document.getElementById('resourceShowListButton');
	resourceShowListButton.onclick = function () {viewControl('resourceList');};
	
	//Display resourceDetail form and hide resource list
//	viewControl('resourceDetail');
		
	var location = resource['Address1'] + " " + resource['Address2'] + " " + resource['City'] + ", " + resource['State'];
	var streetviewLabel = document.getElementById("resourceDetailLabelStreetview");
	document.getElementById("resourceDetailLabelNote").click();
	document.getElementById("resourceDetailStreetview").innerHTML = "";
	streetviewLabel.onclick = function() {
		viewControl("resourceDetailLabelStreetview");
		geocodeAddress(location);
	};

	return;
}
	
	
//Streetview Functions

function geocodeAddress(address) {

  accuracy = null;
  geocoder.geocode( { 'address': address}, function(results, status) {

    if (status == google.maps.GeocoderStatus.OK) {
	    endLocation = results[0].geometry.location;
      
		//Check the area of the returned object to see if results are accurate enough to display streetview
		var north = results[0].geometry.viewport.getNorthEast().lat();
		var south = results[0].geometry.viewport.getSouthWest().lat();
		var east = results[0].geometry.viewport.getNorthEast().lng();
		var west = results[0].geometry.viewport.getSouthWest().lng();

		var accuracyArea = ((east-west)*( 6378137*Math.PI/180 ) )*Math.cos( north*Math.PI/180 );

      if(results[0].geometry.location_type != "ROOFTOP" && accuracyArea > 500) {
		accuracy = results[0];
	  }
	  var addressParts = results[0].address_components;
	  
      for (var i=0; i<addressParts.length; i++) {
		if (addressParts[i].types[0] == "locality") { 
			addressCity = addressParts[i].long_name; 
		} else if (addressParts[i].types[0] == "administrative_area_level_1") {
			var addressState = addressParts[i].long_name;
		}
	  }  

	  addressCity = addressCity + ", " + addressState;    

	  var request = {
		  origin: addressCity,
		  destination: endLocation,
		  travelMode: google.maps.TravelMode.DRIVING
	  };

	  // Route the directions and pass the response to a
	  // function to find the final street stop.
	  directionsService.route(request, function(response, status) {
		  if (status == google.maps.DirectionsStatus.OK) {
			  endLocation = findEndLocation(response, accuracy);
		}
	  });
	}
	});
}

function findEndLocation(directionResult, accuracy) {
  	var myRoute = directionResult.routes[0].legs[0];
  	var lastStep = myRoute.steps.length - 1;
	var cameraLocation =  myRoute.steps[lastStep].end_location;
	var angle = 0;
	var angle = computeAngle(endLocation, cameraLocation);

	if (!accuracy) {
		var panoramaOptions = {
			position: cameraLocation,
			pov: {
			  heading: angle,
			  pitch: 0
			}
		}
		var panorama = new  google.maps.StreetViewPanorama(document.getElementById('resourceDetailStreetview'),panoramaOptions);
	} else {
		var mapOptions = {
			zoom: 15,
			center: accuracy.geometry.location,
			mapTypeId: google.maps.MapTypeId.ROADMAP
		}

		var map = new google.maps.Map(document.getElementById('resourceDetailStreetview'),mapOptions);
		
//		var boundsData = accuracy.geometry.viewport;
//		accuracyArea = new google.maps.Rectangle({
//			strokeColor: '#FF0000',
//			strokeOpacity: 0.8,
//			strokeWeight: 2,
//			fillColor: '#FF0000',
//			fillOpacity: 0.35,
//			map: map,
//			bounds: boundsData
//		});
		
//		accuracyArea.setMap(map);

	}	
}

function computeAngle(endLatLng, startLatLng) {
      var DEGREE_PER_RADIAN = 57.2957795;
      var RADIAN_PER_DEGREE = 0.017453;
 
      var dlat = endLatLng.lat() - startLatLng.lat();
      var dlng = endLatLng.lng() - startLatLng.lng();
      // We multiply dlng with cos(endLat), since the two points are very closeby,
      // so we assume their cos values are approximately equal.
      var yaw = Math.atan2(dlng * Math.cos(endLatLng.lat() * RADIAN_PER_DEGREE), dlat)
             * DEGREE_PER_RADIAN;
      return wrapAngle(yaw);
}
 
function wrapAngle(angle) {
    if (angle >= 360) {
        angle -= 360;
    } else if (angle < 0) {
        angle += 360;
    }
    return angle;
}

	
// END Streetview Functions

	
	
	
	
function sortBy(searchObject, sortKey) {
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

	return arr; // returns array
}



function viewControl(showPanel, oldPanel) {
	var resourceDetail = document.getElementById('resourceDetail');
	var resourceList = document.getElementById('resourceList');
	var searchBox = document.getElementById("searchBox");
	var searchBoxPane = document.getElementById("newSearchPane");
	var resourceDetailControl = document.getElementById("resourceDetailControl");
	var mainControls = document.getElementById("newSearchPaneControls");
	var StreetPanel = document.getElementById("resourceDetailStreetview");
	var NotePanel = document.getElementById("resourceDetailNote");			
	var StreetLabel = document.getElementById("resourceDetailLabelStreetview");
	var NoteLabel = document.getElementById("resourceDetailLabelNote");
	var mainNewSearchButton = document.getElementById("mainNewSearchButton");

	if(showPanel === "Close") {
		showPanel = olderPanel;
		currentPanel = showPanel;
	} else {
		priorOldPanel = olderPanel;
		olderPanel = currentPanel;
		currentPanel = showPanel;
	
	}
	
	if(currentPanel === olderPanel) {
		olderPanel = priorOldPanel;
	}
	
	resourceDetail.style.display = 'none';
	resourceList.style.display = 'none';
	searchBox.style.display = 'none';
	searchBoxPane.style.display = 'none';
	searchBoxPane.style.color = null;
	searchBoxPane.style.background = null;
	resourceDetailControl.style.visibility = 'hidden';
	
	mainControls.style.visibility = "visible";

	if(!oldPanel) {
		oldPanel = "main";
	}
	
	switch(showPanel) {
		case 'resourceList':
			resourceList.style.display = 'block';
//			if (copyableList) {
//				copyableList.style.visibility = "visible";
//			}
			break;
		case 'resourceDetail':
			resourceDetail.style.display = 'block';
			resourceDetailControl.style.visibility = 'visible';
			mainNewSearchButton.style.visibility = 'visible';
			break;
		case 'searchBox':
			searchBox.style.display = 'block';
			searchBoxPane.style.display = 'block';
			document.getElementById("Distance").value = "100";
			document.getElementById("ZipCode").focus();
			break;

		case 'main':
			searchBoxPane.style.display = 'block';
			searchBoxPane.style.color = null;
			searchBoxPane.style.background = null;
			viewControl('searchBox');
			mainNewSearchButton.style.visibility = 'hidden';
			break;

		case 'resourceDetailLabelNote':
			viewControl("resourceDetail");
			NoteLabel.style.background = "maroon";
			NoteLabel.style.color = "white";
			StreetLabel.style.background = null;
			StreetLabel.style.color = null;
			var StreetPanel = document.getElementById("resourceDetailStreetview");			
			NotePanel.style.display = "inline-block";
			StreetPanel.style.display = "none";
			break;
			
		case 'resourceDetailLabelStreetview':
			viewControl("resourceDetail");
			StreetPanel.style.display = "inline-block";
			NotePanel.style.display = "none";
			StreetLabel.style.background = "maroon";
			StreetLabel.style.color = "white";
			NoteLabel.style.background = null;
			NoteLabel.style.color = null;
			break;
			
		default:
			viewControl(oldPanel,oldPanel);
			break;

	}
}



function sleep(delay) {
    var start = new Date().getTime();
    while (new Date().getTime() < start + delay);  
}

function validate(button) {
	var zip = 		document.getElementById("ZipCode").value;
	var distance = 	document.getElementById("Distance").value;
	var city =		document.getElementById("City").value;
	var state = 	document.getElementById("State").value;
	var name = 		document.getElementById("Name").value;

	document.getElementById("Distance").blur();

	switch(button) {
		case 'nationalSearch':
			newSearch = new Search("","All",0,"","","","national");
			newSearch.searchRequest();
			break;
			
		case 'internationalSearch':
			var countryName = countriesList[document.getElementById("internationalSearch").selectedIndex];
			if (countryName == "Canada") {
				alert("Please use the normal search boxes for Canadian resources.  Canadian postal codes work the same as Zip Codes.");
				return;
			}
			newSearch = new Search("","All",0,"","",countryName,"international");
			newSearch.searchRequest();
			break;
			
		case 'findZipSearch':
			newSearch = new Search("","All",distance,city,state,name,"findZip");
			newSearch.searchRequest();
			break;
			
		default:
			newSearch = new Search(zip,"All",distance,city,state,name);
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


	var CategoryMenus = document.getElementById("Categories").getElementsByTagName("select");
	for (var i = 0; i<CategoryMenus.length; i++) {
		var Category = CategoryMenus[i];
		Category.onchange= function () {
			var CategoryIndex = this.selectedIndex;
			
			markCallLog(this.name);

            var Category = this.getElementsByTagName("option")[CategoryIndex].value;
			newSearch.category = Category;
			delete(newSearch.results);
			document.getElementById("Menus").reset();
			newSearch.postSearch();
		};
		Category.onmousedown= function () {
			var CategoryIndex = this.selectedIndex;
			
			markCallLog(this.name);

            var Category = this.getElementsByTagName("option")[CategoryIndex].value;
			newSearch.category = Category;
			delete(newSearch.results);
			document.getElementById("Menus").reset();
			newSearch.postSearch();
		};		
	}
}


function markCallLog(Category) {

}


//  END RESOURCE SEARCH OBJECT AND FUNCTIONS








//GLOBALS

var geocoder = "";
var directionsService = "";
var dragObject = "";
var newCall = "";
var newChat = "";
var newMonitorChats = "";
var countriesList = new Array();
var chatMonitorKeepAlive = null;
var trainer = "";
var trainee = "";
var monitor = "";
var trainingSession = "";
var monitorChatInterval = "";
var welcomeSlideRotation = "";
var mytrainingChatwindow = "";
var myVideo = document.getElementById("video1"); 
var videoFinished = "";
var priorOldPanel = "";
var olderPanel = "";
var currentPanel = "";


function improperExit() {
	var improperExit = true;
	 return "Hi";
}

var inactivityTime = function () {
    var time;
    window.onload = resetTimer;
    // DOM Events
    document.onmousemove = resetTimer;
    document.onkeydown = resetTimer;


    function resetTimer() {
        clearTimeout(time);
        if(!trainingSession || !trainingSession.muted) {
        	if(!newCall || newCall.callStatus === 'open') {
		        time = setTimeout(exitProgram, 1800000);
		    }
		}
                
        // 1000 milliseconds = 1 second
    }
};





window.onload = function() {

 	geocoder = new google.maps.Geocoder();
  	directionsService = new google.maps.DirectionsService();

	searchBox = document.getElementById("searchBox");

	resourceDetailName = document.getElementById("resourceDetailName");

	var mainNewSearchButton = document.getElementById('mainNewSearchButton');
	mainNewSearchButton.onclick = function () {
		document.getElementById("mainNewSearchButton").style.visibility = 'hidden';
		document.getElementById("resourceListCategory").innerHTML = "";
		document.getElementById("searchBoxForm").reset();
		document.getElementById("searchParameters").innerHTML = "";
		viewControl('searchBox');
	};
	
	document.getElementById("resourceDetailLabelNote").onclick = function() {
		viewControl(this.id);
	};
	
	document.getElementById("findZipSearch").onclick = function() {
		validate(this.id);
	};

	document.getElementById("nationalSearch").onclick = function() {
		validate(this.id);
	};
	

	countries();
	
	viewControl("Main");
}

function countries() {
	var url= "../countries.php";
	var countriesResultFunction = function(result) {countriesResult(result);};
	var countriesRequest = new AjaxRequest(url, " " , countriesResultFunction, this);
}


function countriesResult(results) {
	var responseDoc = JSON.parse(results);
	countryList = document.getElementById("internationalSearch");
	countryList.innerHTML = "";
	for (i=0;i<responseDoc.length;i++) {
		entry = responseDoc[i];
		if(i == 0) {
			entry = "INTERNATIONAL";
		}
		option = document.createElement("option");
		option.value = entry;
		option.appendChild(document.createTextNode(entry));
		if(entry && entry != " ") {
			countriesList[countryList.childNodes.length] = entry;
			countryList.appendChild(option);
		}
	}
	
	countryList.onchange = function () {
		validate(this.id);
		countryList.selectedIndex = 0;

	};
}

