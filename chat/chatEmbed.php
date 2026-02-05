<?php
		$protocol = strpos(strtolower($_SERVER['SERVER_PROTOCOL']),'https') 
						=== FALSE ? 'http' : 'https';
		$host     = $_SERVER['HTTP_HOST'];
		$script   = $_SERVER['SCRIPT_NAME'];

		$WebAddress = $protocol . '://' . $host;
?>	

<html>
<head>
</head>
<body>
	<?php 
		echo "<image id='LGBTNationalHelpCenterButton' src='".$WebAddress."/chat/chatavailable.gif'>";
	?>
		
	<script>
		var button = document.getElementById('LGBTNationalHelpCenterButton');
		button.onclick = function() {
			<?php 
				echo "myWindow = window.open('".$WebAddress."/chat/index.php' , '', 'width=490 height=720')";  
			?>
		}
	</script>
	<style>
		#LGBTNationalHelpCenterButton { 
			cursor: pointer; 
			cursor: hand; 
		}
	</style>
</body>
</html>
