<?php


$to = "travelstead@mac.com";
$subject = "VCC Group Chat Transcript for ".$chatDate;
$message = $emailBody;
$envelope_from = "tatiana@lgbthotline.org";



// To send HTML mail, the Content-type header must be set
$headers  = 'MIME-Version: 1.0' . "\r\n";
$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

// Additional headers
$headers .= 'From: LGBT National Help Center <tatiana@lgbthotline.org>'. "\r\n";

$additional_parameters = "-f ".$envelope_from;

// Mail it
ini_set(sendmail_from,'tatiana@lgbthotline.org');  //used to force system to use the from address
if(mail($to, $subject, $message, $headers,$additional_parameters )) {
	echo $emailBody;
} else {
	echo "Problem";
}


?>