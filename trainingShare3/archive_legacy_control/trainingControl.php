<?php
// NO AUTHENTICATION - DEVELOPMENT MODE
?>
<html>
	<head>
		<script src="trainingControl.js" type="text/javascript"></script>
		<script src="../LibraryScripts/Ajax.js" type="text/javascript"></script>
		<style>
			#volunteerDetailsButtons {
				position:			relative;
				top:				0px;
				visibility:			hidden;
			}
		</style>

	</head>
	<body>
		<h1>Training Session Control</h1>
		<form name='volunteerDetailsButtons' id='volunteerDetailsButtons'>
			<span>I am: <input type='radio' id='trainingSessionTalkButton' name='trainingControl' value='0' checked='checked'>Talking</span><br />
			<input type='radio' id='trainingSessionListenButton' name='trainingControl' value='1' ><span>Listening</span>;
		</form>
	</body>
</html>
