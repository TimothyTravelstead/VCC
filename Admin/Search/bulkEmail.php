<?php


require_once('../../../private_html/db_login.php');
session_start();
ini_set('memory_limit', '2G');


set_include_path('/opt/homebrew/bin/pear');

header('Access-Control-Allow-Origin: https://www.volunteerlogin.org');

require_once 'Mail.php';
require_once 'Mail/mime.php';
require_once 'Mail/mail.php';

require_once ('formatemail.php');

$VolunteerID = $_SESSION["UserID"];

$idnum = $_POST['idnum'];

list ($to, $subject, $message) = formatMessage($idnum);
$email_from = "database@lgbthotline.org";


if(!$to || $to == "") {
    echo "NoEmail";
    die();
} else {
	$host = "smtp.office365.com";
	$username = 'database@lgbthotline.org';
	$password = 'WoodWood11!!';
	$port = "587";

	$headers = array ('From' => $email_from,
					'To' => $to,
					'Subject' => $subject);

	$params = array  ('host' => $host,
					'port' => $port,
					'auth' => 'LOGIN',
					'socket_options' => array('ssl' => array('verify_peer_name' => false)),
					'debug' => false,
					'username' => $username,
					'password' => $password);

	 $crlf = "\n";
	 $mime = new Mail_mime($crlf);
	 $mime->setHTMLBody($message);
	 $body = $mime->get();
	 $headers = $mime->headers($headers);
	 $smtp = Mail::factory('smtp', $params);
	 $mail = $smtp->send($to, $headers, $body);
	 if (PEAR::isError($mail)) {
		 echo "<p>" . $mail->getMessage() . "</p>";
	 } else {
		$query3 = "INSERT into resourceEditLog (UserName, resourceIDNUM, Action) VALUES ('".$VolunteerID."', '".$idnum."' , 'Email')";
		$result3 = mysqli_query($connection,$query3);
		 echo "OK";
	 }
} 


?>
