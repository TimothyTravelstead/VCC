<?php
session_start();
if ($_SESSION['auth'] != 'yes') {
	die("Unauthorized");
} 

$room = $_GET["trainingShareRoom"].".txt";


if (count($_POST)!=0) { // simulated onmessage by ajax post

	// Note that browsers that connect with the same
	// session (tabs in the same browser at the same computer)
	// will clash. This does never happen in practice, although when testing 
	// on one computer, you have to use two different browsers, in order to 
    // get a different result from session_id().
    $filename = dirname(__FILE__).'/Signals/'.$room;

    $posted = file_get_contents('php://input');
    
    // A main lock on index.php, because otherwise we can not delete the
    // file after reading its content (further down)
//	$mainlock = fopen('index.php','r');
//	flock($mainlock,LOCK_EX);
   
    // Add the new message to file
    $file = fopen($filename,'ab');
	if (filesize($filename)!=0) {
		fwrite($file,'_MULTIPLEVENTS_');
	}
  	fwrite($file,$posted);
	fclose($file);

    // Unlock main lock
//    flock($mainlock,LOCK_UN);
//    fclose($mainlock);
    

} else { // regular eventSource poll which is loaded every few seconds
	$all = [];
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache'); // recommended

    function startsWith($haystack, $needle) {
        return (substr($haystack, 0, strlen($needle) ) === $needle);
    }
    // Get a list of all files in the folder 
	$path = "Signals";
	$filename = dirname(__FILE__).'/Signals/'.$room;

	if(file_exists($path) && is_dir($path)){
		// Scan the files in this directory
		$result = scandir($path);

		// Filter out the current (.) and parent (..) directories
		$files = array_diff($result, array('.', '..'));
	
		if(count($files) > 0){
			// Loop through retuned array
			foreach($files as $file){    
				if ($file == $room) {
					$all [] .= $filename;
				}
			}
		}
    }
    // A main lock on index.php, because otherwise we can not delete the
    // file after reading its content.
//    $mainlock = fopen('index.php','r');
//	flock($mainlock,LOCK_EX);
    
    // show and empty the first one that is not empty
	if(count( $all ) > 0) {
		for($x = 0; $x < count ( $all ); $x ++) {
			$filename=$all[$x];
		
			// prevent sending empty files
			if (filesize($filename)==0) {
				unlink($filename);
				continue;
			}       
			$file = fopen($filename, 'c+b');
			flock($file, LOCK_SH);
	//		echo "event: Message\n";
			echo 'data: ', fread($file, filesize($filename)),PHP_EOL;
			fclose($file);
			unlink($filename);
			break;
		}
	}
    // Unlock main lock
//    flock($mainlock,LOCK_UN);
//    fclose($mainlock);
    echo 'retry: 1000',PHP_EOL,PHP_EOL; // shorten the 3 seconds to 1 sec
}
