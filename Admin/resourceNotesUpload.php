<?php


require_once('../../private_html/db_login.php');
session_start();
if (@$_SESSION["auth"] != "yes") {
    die("Unauthorized");
}

// Release session lock after authentication check
session_write_close();

// Clear existing notes
$clearNotes = "DELETE FROM Notes";

if (!dataQuery($clearNotes)) {
    header("Location: index.php?error=clear_failed");
    exit();
}

// Load data from CSV
$loadNotes = "LOAD DATA LOCAL INFILE '" . $_FILES["Notes"]["tmp_name"] . "'
    INTO TABLE notes
    FIELDS TERMINATED BY ',' 
    ENCLOSED BY '\"' 
    LINES TERMINATED BY '\r'";

if (!dataQuery($loadNotes)) {
    header("Location: index.php?error=load_failed");
    exit();
}

// Clean up line endings in notes
$cleanNotes = "UPDATE notes SET notes = REPLACE(notes,'\r\r','\r')";

if (!dataQuery($cleanNotes)) {
    header("Location: index.php?error=update_failed");
    exit();
}

header("Location: index.php");
exit();
?>
