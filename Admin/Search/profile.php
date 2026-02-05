<?php
// Include database connection FIRST to set session configuration before session_start()
// db_login.php sets session.gc_maxlifetime to 8 hours for all-day admin sessions
require_once('../../../private_html/db_login.php');

// Now start the session with the correct configuration
session_start();

// Set Search Type Flag to Guide Sub-Searches
$_SESSION['SearchType'] = 'NonZip';

// Release session lock to prevent blocking concurrent requests
session_write_close();

$params = [
    ':from_date' => $_REQUEST["From"],
    ':to_date' => $_REQUEST["To"]
];

$query = "SELECT 
    TRIM(resource.idnum) as idnum,
    TRIM(resource.name) as name,
    TRIM(resource.name2) as name2,
    resource.address1,
    resource.address2,
    TRIM(resource.city) as city,
    resource.state,
    TRIM(resource.linkableZip) as zip,
    TRIM(resource.type1) as type1,
    TRIM(resource.type2) as type2,
    TRIM(resource.type3) as type3,
    TRIM(resource.type4) as type4,
    TRIM(resource.type5) as type5,
    TRIM(resource.type6) as type6,
    TRIM(resource.type7) as type7,
    TRIM(resource.type8) as type8,
    TRIM(resource.contact) as contact,
    TRIM(resource.phone) as phone,
    TRIM(resource.descript) as descript,
    TRIM(resource.note) as note,
    TRIM(resource.hotline) as hotline,
    TRIM(resource.fax) as fax,
    TRIM(resource.internet) as email,
    TRIM(resource.wwweb) as web,
    TRIM(resource.edate) as last_edit,
    resource.longitude,
    resource.latitude,
    resource.ext,
    resource.mailpage,
    resource.showmail as hidemail,
    resource.website as publish,
    resource.cnational as national,
    resource.closed,
    resource.Give_Addr as hide_address,
    TRIM(resource.wwweb2) as web2,
    TRIM(resource.wwweb3) as web3
FROM resource 
LEFT JOIN resourceReview ON (resource.counter = resourceReview.counter)
WHERE resource.Closed = 'N' 
    AND (resource.edate BETWEEN :from_date AND :to_date)
    AND (resourceReview.counter IS NULL OR resourceReview.modifiedTime IS NULL)
ORDER BY resource.edate, resource.idnum";

$results = dataQuery($query, $params);

if (!$results) {
    die("There are no open resources that were last updated between the dates you entered.");
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" 
   "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
    <head>
        <title>GLBT National Help Center - Profile Update</title>
        <link type='text/css' rel='stylesheet' href='profile.css' />
    </head>
    <body>
<?php
foreach ($results as $row) {
    // Clean hotline data
    $hotline = $row->hotline === "- Ê -" ? " " : $row->hotline;
    ?>
    <br /><br /><br />
    <h3><center>GLBT NATIONAL HELP CENTER<br />Profile Update</center></h3>
    
    <div class='Label'>ID NUMBER:<span class='Data'><?php echo htmlspecialchars($row->idnum); ?></span></div><br />
    
    <div class='Label'>Resource Name:</div>
    <div class='Data'>
        <?php echo htmlspecialchars($row->name); ?><br />
        <?php if (!empty($row->name2)): ?>
            <?php echo htmlspecialchars($row->name2); ?><br />
        <?php endif; ?>
    </div><br />
    
    <div class='Label'>Contact:</div>
    <div class='Data'><?php echo htmlspecialchars($row->contact); ?></div>
    
    <div class='Label'>Address:</div>
    <div class='Data'>
        <?php echo htmlspecialchars($row->address1); ?><br />
        <?php if (!empty($row->address2)): ?>
            <?php echo htmlspecialchars($row->address2); ?><br />
        <?php endif; ?>
        <?php echo htmlspecialchars($row->city . ", " . $row->state . " " . $row->zip); ?>
    </div>
    
    <div>
        <span id='OfficeLabel'>Office Phone:</span>
        <span id='ExtLabel'>Ext:</span>
        <span id='HotlineLabel'>Hotline:</span>
        <span id='FaxLabel'>Fax:</span>
    </div><br />
    
    <div>
        <span id='OfficeData'><?php echo htmlspecialchars($row->phone); ?></span>
        <span id='ExtData'><?php echo htmlspecialchars($row->ext); ?></span>
        <span id='HotlineData'><?php echo htmlspecialchars($hotline); ?></span>
        <span id='FaxData'><?php echo htmlspecialchars($row->fax); ?></span>
    </div><br />
    
    <div>
        <span class='Label'>Email address:</span>
    </div>
    
    <div>
        <?php if (!empty($row->email)): ?>
            <span class='Data'><?php echo htmlspecialchars($row->email); ?></span><br />
            <span class='Data'><?php echo htmlspecialchars($row->mailpage); ?></span>
        <?php else: ?>
            <span class='Data'><?php echo htmlspecialchars($row->mailpage); ?></span>
        <?php endif; ?>
    </div>
    
    <div><br />
        <span class='Label'>World Wide Web:</span><br />
        <span class='Data'><?php echo htmlspecialchars($row->web); ?></span><br />
        <span class='Data'><?php echo htmlspecialchars($row->web2); ?></span><br />
        <span class='Data'><?php echo htmlspecialchars($row->web3); ?></span><br />
    </div><br />
    
    <div class='Label'>One Line Description:</div>
    <div class='Data'><?php echo htmlspecialchars($row->descript); ?></div>
    
    <div class='Label'>Descriptors:</div>
    <div class='Data'>
        <?php 
        // Output each type separately with HTML spaces between them
        $types = [];
        for ($i = 1; $i <= 8; $i++) {
            $typeField = 'type' . $i;
            if (!empty($row->$typeField)) {
                $types[] = htmlspecialchars($row->$typeField);
            }
        }
        // Join all non-empty types with 5 non-breaking spaces
        echo implode(str_repeat('&nbsp;', 5), $types);
        ?>
    </div>
    
    <div class='Label'>Hours and Other Information:</div>
    <div id='Notes' class='Data Notes'><?php echo nl2br(htmlspecialchars($row->note)); ?></div>
    
<?php
}
?>
    </body>
</html>
