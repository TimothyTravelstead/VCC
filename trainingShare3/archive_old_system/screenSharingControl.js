function SignalingServer(role) {
	var self = this;
	var trainer = document.getElementById("trainer").value;
	var trainee = document.getElementById("trainee").value;
	var volunteerID = document.getElementById("volunteerID").value;
	var answer = 0;

	this.localVideo = document.getElementById("localVideo");
	this.remoteVideo = document.getElementById("remoteVideo");
	this.remoteVideo.poster="trainingShare/poster.png";
	var ws=null;	
	
	if(role == 'Trainer') {
		var listeningURL = '/trainingShare/signalingServer.php?trainingShareRoom=' + trainee;
		var speakingURL = '/trainingShare/signalingServer.php?trainingShareRoom=' + volunteerID;
	} else {
		var speakingURL = '/trainingShare/signalingServer.php?trainingShareRoom=' + volunteerID;
		var listeningURL = '/trainingShare/signalingServer.php?trainingShareRoom=' + trainer;
	}


	var configuration = {
		'iceServers': [
			// Existing STUN servers for initial NAT traversal attempts
			{'urls': 'stun:stun.stunprotocol.org:3478'},
			{'urls': 'stun:stun.l.google.com:19302'},
			{'urls': 'stun:stun1.l.google.com:19302'},
			{'urls': 'stun:stun2.l.google.com:19302'},
	
			// TURNS server as a fallback for relaying media
			{
				'urls': 'turns:match.volunteerlogin.org:5349',
				'username': 'travelstead@mac.com',
				'credential': 'BarbMassapequa99+'
			}
		]
	};

	//Peer Connection Creation
	var pc = new RTCPeerConnection(configuration);
	var constraints = {
		video: {
			cursor: "always",
			displaySurface: "application",
			logicalSurface: true
		},
		audio: false
	};

	navigator.mediaDevices.getDisplayMedia(constraints).then(function (stream) {
            localVideo.srcObject = stream;
            localStream = stream;


//Signaling Server Handling
	try {
		ws = new EventSource(listeningURL);
	} catch(e) {
		console.error("Could not create eventSource ",e);
	}

	// Websocket-hack: EventSource does not have a 'send()'
	// so I use an ajax-xmlHttpRequest for posting data
	ws.send = function send(message) {
		 var xhttp = new XMLHttpRequest();
		 xhttp.onreadystatechange = function() {
			 if (this.readyState!=4) {
			   return;
			 }
			 if (this.status != 200) {
			   console.log("Error sending to "+ speakingURL+ " with message: " +message);
			 } 
		 };
		 xhttp.open("POST", speakingURL, true);
		 xhttp.setRequestHeader("Content-Type","Application/X-Www-Form-Urlencoded");
		 xhttp.send(message);
	}

	// Websocket-hack: onmessage is extended for receiving 
	// multiple events at once for speed, because the polling 
	// frequency of EventSource is low.
	ws.onmessage = function(e) {	
		if (e.data.includes("_MULTIPLEVENTS_")) {
			multiple = e.data.split("_MULTIPLEVENTS_");
			for (x=0;x<multiple.length;x++) {
				self.onSingleMessage(multiple[x]);
			}
		} else {
			self.onSingleMessage(e.data);
		}
	}

   // Go show myself
	localVideo.addEventListener('loadedmetadata', 
		function () {
			publish('client-call', null);
		}
	);
		
	}).catch(function (e) {
		console.log("Problem while getting audio video stuff ",e);
	});
	
	this.reestablish = function() {
		publish('client-call', null);
	};
	
	
	this.shareMyScreen = function() {

		var mainElements = document.body.getElementsByTagName("div");
		for (i = 0; i < mainElements.length; i++) {
			mainElements[i].style.display = null;
		}
		self.localVideo.style.display = "none";
		self.remoteVideo.style.display = "none";
	};
	
	this.getSharedScreen = function() {		
		self.shareMyScreen();
		var mainElements = document.body.getElementsByTagName("div");
		for (i = 0; i < mainElements.length; i++) {
			mainElements[i].style.display = "none";
		}
		self.remoteVideo.style.display = "block";
	};	
	
	
	this.closeConnection = function() {
		pc.close();
		pc = null;
		localStream = null;
		localVideo.srcObject = null;
		self.localVideo.srcObject = null;
		remoteVideo.srcObject = null;
		self.remoteVideo.srcObject = null;
	};
	
	this.onSingleMessage = function(data) {
        var package = JSON.parse(data);
        var data = package.data;
        
        console.log("received single message: " + package.event);
        switch (package.event) {
            case 'client-call':
                icecandidate(localStream);
                pc.createOffer({
                    offerToReceiveAudio: 0,
                    offerToReceiveVideo: 1
                }).then(function (desc) {
                    pc.setLocalDescription(desc).then(
                        function () {
                            publish('client-offer', pc.localDescription);
                        }
                    ).catch(function (e) {
                        console.log("Problem with publishing client offer"+e);
                    });
                }).catch(function (e) {
                    console.log("Problem while doing client-call: "+e);
                });
                break;
            case 'client-answer':
                if (pc==null) {
                    console.error('Before processing the client-answer, I need a client-offer');
                    break;
                }
                pc.setRemoteDescription(new RTCSessionDescription(data),function(){}, 
                    function(e) { console.log("Problem while doing client-answer: ",e);
                });
                break;
            case 'client-offer':
                icecandidate(localStream);
                
                pc.setRemoteDescription(new RTCSessionDescription(data), function(){
                    if (!answer) {
                        pc.createAnswer(function (desc) {
                                pc.setLocalDescription(desc, function () {
                                    publish('client-answer', pc.localDescription);
                                }, function(e){
                                    console.log("Problem getting client answer: ",e);
                                });
                            }
                        ,function(e){
                            console.log("Problem while doing client-offer: ",e);
                        });
                        answer = 1;
                    }
                }, function(e){
                    console.log("Problem while doing client-offer2: ",e);
                });
                break;
            case 'client-candidate':
               if (pc==null) {
                    console.error('Before processing the client-answer, I need a client-offer');
                    break;
                }
                pc.addIceCandidate(new RTCIceCandidate(data), function(){}, 
                    function(e) { console.log("Problem adding ice candidate: "+e);});
                break;
        }
    };  
    
	function icecandidate(localStream) {
	
		pc.onnegotiationneeded = function() {
			pc.createOffer({
				offerToReceiveAudio: 0,
				offerToReceiveVideo: 1
			}).then(function (desc) {
				pc.setLocalDescription(desc).then(
					function () {
						publish('client-offer', pc.localDescription);
					}
				).catch(function (e) {
					console.log("Problem with publishing client offer"+e);
				});
			}).catch(function (e) {
				console.log("Problem while doing client-call: "+e);
			});
		};
	
	
        pc.onicecandidate = function (event) {
            if (event.candidate) {
                publish('client-candidate', event.candidate);
            }
        };
        try {
            pc.addStream(localStream);
        }catch(e){
            var tracks = localStream.getTracks();
            for(var i=0;i<tracks.length;i++){
                pc.addTrack(tracks[i], localStream);
            }
        }
        pc.ontrack = function (e) {
            remoteVideo.srcObject = e.streams[0];
        };
    }

    function publish(event, data) {
        console.log("sending ws.send: " + event);
        ws.send(JSON.stringify({
            event:event,
            data:data
        }));
    }

}