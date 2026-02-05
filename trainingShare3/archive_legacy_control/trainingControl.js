function TrainingControlChange() {
	var self = this;
	this.volunteerDetailsButtons = document.getElementById('volunteerDetailsButtons');
	
	// Initialize role and IDs from session/DOM
	this.initializeTrainingContext();

	this.volunteerDetailsButtons.onchange = function() {
		var trainingControlValue = self.volunteerDetailsButtonStatus(); 
		
		if(this.muted != trainingControlValue) {
			this.changeControl();
		}
	};
	
	this.initializeTrainingContext = function() {
		// Get role and participant IDs from DOM or global variables
		const traineeField = document.getElementById('traineeID');
		const trainerField = document.getElementById('trainerID');
		const volunteerField = document.getElementById('volunteerID');
		
		this.volunteerID = volunteerField?.value;
		this.traineeID = traineeField?.value;
		this.trainerID = trainerField?.value;
		
		// Determine role based on available fields
		if (this.traineeID && this.volunteerID === this.traineeID) {
			this.role = "Trainee";
		} else if (this.trainerID && this.volunteerID === this.trainerID) {
			this.role = "Trainer";
		} else if (window.trainingSession) {
			// Fallback to global training session
			this.role = window.trainingSession.role === 'trainer' ? 'Trainer' : 'Trainee';
			this.traineeID = window.trainingSession.role === 'trainee' ? this.volunteerID : null;
			this.trainerID = window.trainingSession.role === 'trainer' ? this.volunteerID : window.trainingSession.trainer.id;
		}
		
		console.log('Training control initialized:', {
			role: this.role,
			volunteerID: this.volunteerID,
			traineeID: this.traineeID,
			trainerID: this.trainerID
		});
	};
		
	this.volunteerDetailsButtonStatus = function() {
		var trainingControl = document.getElementsByName('trainingControl');

		for(var i = 0; i < trainingControl.length; i++){
			if(trainingControl[i].checked){
				return Number(trainingControl[i].value);
			}
		}
	};

	this.changeControl = function() {
		// Get current mute state from radio buttons
		var currentValue = this.volunteerDetailsButtonStatus();
		var shouldMute = (currentValue === 1); // 1 = Listening (muted), 0 = Talking (unmuted)
		
		var action = shouldMute ? "mute" : "unmute";
		var targetUser = this.volunteerID; // Always mute/unmute yourself
		
		console.log('Training control change:', {
			action: action,
			targetUser: targetUser,
			role: this.role,
			currentValue: currentValue
		});

		var params = "postType=trainingControl";
		var url = "../volunteerPosts.php";
		var trainingControlResults = function (results) {
			if(results != "OK") {
				alert(results);
				console.error('Training control failed:', results);
			} else {
				console.log('Training control successful:', action, targetUser);
			}
		};

		params += "&action=" + action;
		params += "&text=" + targetUser;
		
		var trainingControlUpdate = new AjaxRequest(url, params, trainingControlResults);		
	};
}

var control = new TrainingControlChange();