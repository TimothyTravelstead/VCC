<?php
session_start();

// Check authentication
if ($_SESSION['auth'] != 'yes') {
	die("Unauthorized");
}

// Release session lock after reading session data
session_write_close(); 


$name = strtolower($_REQUEST['name']) ?? null;
$bannedNames = explode("," , file_get_contents('./bannedNames.txt', true));
$bannedNamesArray = Array();
ksort($bannedNamesArray);


foreach ($bannedNames as $key => $value) {
	$bannedNamesArray[$value] = true;
	if (strpos($name, strtolower($value)) !== false) {
		echo 'true';
		return;
	}
}

echo 'false';

?>