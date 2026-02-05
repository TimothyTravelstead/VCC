<?php

// Prevent caching

require_once('../../private_html/db_login.php');
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

if ($_SESSION['auth'] != 'yes') {
    die("Unauthorized");
} 

// Include the mysql database location and login information

$timestamp = round(microtime(true) * 1000000);

$UserID = $_SESSION['UserID'] ?? 'Popeye';
$admin = false;

$editResourceStatus = $_SESSION['editResources'];

if($editResourceStatus != "user") {
    $admin = true;
}
// Updated database query to use dataQuery
$query = "SET time_zone = ?";
$result = dataQuery($query, [$offset]);

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
?><!DOCTYPE html>
<html>
<head>
    <meta http-equiv="cache-control" content="no-cache" />
    <meta http-equiv="content-type" content="text/html;charset=utf-8" />
    <meta http-equiv="pragma" content="no-cache" />
    <meta http-equiv="expires" content="Mon, 22 Jul 2002 11:12:01 GMT" />
    <meta name="robots" content="noindex, nofollow" />
    <link rel="stylesheet" href="index3.css?v=<?php echo $timestamp; ?>">
    <script src="https://cdn.apple-mapkit.com/mk/5.x.x/mapkit.js"></script>
    <script src="../LibraryScripts/ErrorModal.js" type="text/javascript"></script>
    <script src="index3.js" type="text/javascript"></script>
</head>
<body>
    <div id="titleBar" class="redGradient">
        <h1>LGBT National Help Center</h1><br><br>
        <?php 
            if ($admin) {
                echo "<h2>Updated Resource Review</h2>";
            } else {
                echo "<h2>Resource Update Program</h2>";
            }
        ?>
        <input type="text" name="resourceDetailIDNUM" id="resourceDetailIDNUM" value="" required />
        <div class="volunteerUpdateInfo">
            <label class="resourceDetailNormalLabelWidth volunteerUpdateInfo">Updated By: </label>
            <div type="text" id="webSiteUpdateVolunteer" class="volunteerUpdateInfo" readonly></div>
            <label class="resourceDetailNormalLabelWidth volunteerUpdateInfo">Updated On: </label>
            <div type="text" id="webSiteUpdateVolunteerDate" class="volunteerUpdateInfo"></div>
        </div>
    </div>            
    <div id="resourcePane" class="redGradient">
        <form id="resourceDetailForm" name="resourceDetailForm" onsubmit="return false;" method="POST">
            <div class="fieldBox">
                <div class="fieldRow">
                    <div class="editGroup">
                        <input type="button" name="dateUnknownButton" id="dateUnknownButton" value="Skip" />
                    </div>
                    <div class="fieldContent">
                        <label class="resourceDetailEditLabelWidth">Date: </label>
                        <input type="date" class="resourceDetailEditNarrowWidth" id="webSiteUpdateDate" required oninvalid="this.setCustomValidity('Please enter the most recent date the website was updated')" oninput="this.setCustomValidity('')" />
                        <label class="resourceDetailNormalLabelWidth">Type: </label>
                        <input type="text" class="resourceDetailEditNarrowWidth" id="webSiteUpdateType" required oninvalid="this.setCustomValidity('Please enter the reason you can see that the website was updated')" oninput="this.setCustomValidity('')" />    
                    </div>
                </div>
            </div>

            <div class="fieldBox" id="NameBox">
                <div class="fieldRow">
                    <div class="editGroup">
                        <input type="checkbox" name="checkboxNAME" value="Edited" />
                        <label class="checkboxLabel">Edit</label>
                        <input type="checkbox" name="deleteNAME" value="Remove" />
                        <label class="checkboxLabel">Delete</label>
                    </div>
                    <div class="fieldTitle">Name</div>
                    <div class="fieldContent">
                        <input type="text" class="resourceDetailNormalWidth" id="resourceDetailNAME" readonly />
                        <input type="text" class="resourceDetailNormalWidth" id="resourceDetailNAME2" readonly />
                    </div>
                </div>
                <div class="editBlock" id="NAME">
                    <input type="text" class="resourceDetailEditNormalWidth" id="resourceDetailEditNAME" required oninvalid="this.setCustomValidity('Please enter the corrected Name')" oninput="this.setCustomValidity('')" />
                    <input type="text" class="resourceDetailEditNormalWidth" id="resourceDetailEditNAME2" required oninvalid="this.setCustomValidity('Please enter the corrected Name')" oninput="this.setCustomValidity('')" />
                </div>
            </div>

            <div class="fieldBox">
                <div class="fieldRow">
                    <div class="editGroup">
                        <input type="checkbox" name="checkboxWWWEB" value="Edited" />
                        <label class="checkboxLabel">Edit</label>
                        <input type="checkbox" name="deleteWWWEB" value="Remove" />
                        <label class="checkboxLabel">Delete</label>
                    </div>
                    <div class="fieldTitle">Website 1</div>
                    <div class="fieldContent">
                        <input type="text" class="resourceDetailNormalWidth" id="resourceDetailWWWEB" readonly/>
                    </div>
                </div>
                <div class="editBlock" id="WWWEB">
                    <input type="text" class="resourceDetailEditNormalWidth" id="resourceDetailEditWWWEB" required oninvalid="this.setCustomValidity('Please enter the corrected Website 1')" oninput="this.setCustomValidity('')" />
                </div>
            </div>

            <div class="fieldBox">
                <div class="fieldRow">
                    <div class="editGroup">
                        <input type="checkbox" name="checkboxWWWEB2" value="Edited" />
                        <label class="checkboxLabel">Edit</label>
                        <input type="checkbox" name="deleteWWWEB2" value="Remove" />
                        <label class="checkboxLabel">Delete</label>
                    </div>
                    <div class="fieldTitle">Website 2</div>
                    <div class="fieldContent">
                        <input type="text" class="resourceDetailNormalWidth" id="resourceDetailWWWEB2" readonly/>
                    </div>
                </div>
                <div class="editBlock" id="WWWEB2">
                    <input type="text" class="resourceDetailEditNormalWidth" id="resourceDetailEditWWWEB2" required oninvalid="this.setCustomValidity('Please enter the corrected Website 2')" oninput="this.setCustomValidity('')" />
                </div>
            </div>

            <div class="fieldBox">
                <div class="fieldRow">
                    <div class="editGroup">
                        <input type="checkbox" name="checkboxWWWEB3" value="Edited" />
                        <label class="checkboxLabel">Edit</label>
                        <input type="checkbox" name="deleteWWWEB3" value="Remove" />
                        <label class="checkboxLabel">Delete</label>
                    </div>
                    <div class="fieldTitle">Website 3</div>
                    <div class="fieldContent">
                        <input type="text" class="resourceDetailNormalWidth" id="resourceDetailWWWEB3" readonly/>
                    </div>
                </div>
                <div class="editBlock" id="WWWEB3">
                    <input type="text" class="resourceDetailEditNormalWidth" id="resourceDetailEditWWWEB3" required oninvalid="this.setCustomValidity('Please enter the corrected Website 3')" oninput="this.setCustomValidity('')" />
                </div>
            </div>

            <div class="fieldBox" id="AddressBox">
                <div class="fieldRow">
                    <div class="editGroup">
                        <input type="checkbox" name="checkboxADDRESS" value="Edited" />
                        <label class="checkboxLabel">Edit</label>
                        <input type="checkbox" name="deleteADDRESS" value="Remove" />
                        <label class="checkboxLabel">Delete</label>
                    </div>
                    <div class="fieldTitle">Address</div>
                    <div class="fieldContent">
                        <input type="text" class="resourceDetailNormalWidth" id="resourceDetailADDRESS1" readonly />
                        <input type="text" class="resourceDetailNormalWidth" id="resourceDetailADDRESS2" readonly />
                    </div>
                </div>
                <div class="editBlock" id="ADDRESS">
                    <input type="text" class="resourceDetailEditNormalWidth" id="resourceDetailEditADDRESS1" required oninvalid="this.setCustomValidity('Please enter the corrected Address')" oninput="this.setCustomValidity('')" />
                    <input type="text" class="resourceDetailEditNormalWidth" id="resourceDetailEditADDRESS2" required oninvalid="this.setCustomValidity('Please enter the corrected Address')" oninput="this.setCustomValidity('')" />
                </div>
            </div>

            <div class="fieldBox">
                <div class="fieldRow">
                    <div class="editGroup">
                        <input type="checkbox" name="checkboxCITY" value="Edited" />
                        <label class="checkboxLabel">Edit</label>
                        <input type="checkbox" name="deleteCITY" value="Remove" />
                        <label class="checkboxLabel">Delete</label>
                    </div>
                    <div class="fieldTitle">City</div>
                    <div class="fieldContent">
                        <input type="text" class="resourceDetailNormalWidth" id="resourceDetailCITY" readonly/>
                    </div>
                </div>
                <div class="editBlock" id="CITY">
                    <input type="text" class="resourceDetailEditNormalWidth" id="resourceDetailEditCITY" required oninvalid="this.setCustomValidity('Please enter the corrected City')" oninput="this.setCustomValidity('')" />
                </div>
            </div>

            <div class="fieldBox">
                <div class="fieldRow">
                    <div class="editGroup">
                        <input type="checkbox" name="checkboxSTATE" value="Edited" />
                        <label class="checkboxLabel">Edit</label>
                        <input type="checkbox" name="deleteSTATE" value="Remove" />
                        <label class="checkboxLabel">Delete</label>
                    </div>
                    <div class="fieldTitle">State</div>
                    <div class="fieldContent">
                        <input type="text" class="resourceDetailNormalWidth" id="resourceDetailSTATE" readonly/>
                    </div>
                </div>
                <div class="editBlock" id="STATE">
                    <input type="text" class="resourceDetailEditNormalWidth" id="resourceDetailEditSTATE" required oninvalid="this.setCustomValidity('Please enter the corrected State')" oninput="this.setCustomValidity('')" />
                </div>
            </div>

            <div class="fieldBox">
                <div class="fieldRow">
                    <div class="editGroup">
                        <input type="checkbox" name="checkboxZIP" value="Edited" />
                        <label class="checkboxLabel">Edit</label>
                        <input type="checkbox" name="deleteZIP" value="Remove" />
                        <label class="checkboxLabel">Delete</label>
                    </div>
                    <div class="fieldTitle">Zip Code</div>
                    <div class="fieldContent">
                        <input type="text" class="resourceDetailNormalWidth" id="resourceDetailZIP" readonly/>
                    </div>
                </div>
                <div class="editBlock" id="ZIP">
                    <input type="text" class="resourceDetailEditNormalWidth" id="resourceDetailEditZIP" required oninvalid="this.setCustomValidity('Please enter the corrected Zip Code')" oninput="this.setCustomValidity('')" />
                </div>
            </div>

            <div class="fieldBox">
                <div class="fieldRow">
                    <div class="editGroup">
                        <input type="checkbox" name="checkboxCONTACT" value="Edited" />
                        <label class="checkboxLabel">Edit</label>
                        <input type="checkbox" name="deleteCONTACT" value="Remove" />
                        <label class="checkboxLabel">Delete</label>
                    </div>
                    <div class="fieldTitle">Contact</div>
                    <div class="fieldContent">
                        <input type="text" class="resourceDetailNormalWidth" id="resourceDetailCONTACT" readonly />
                    </div>
                </div>
                <div class="editBlock" id="CONTACT">
                    <input type="text" class="resourceDetailEditNormalWidth" id="resourceDetailEditCONTACT" required oninvalid="this.setCustomValidity('Please enter the corrected Contact Name')" oninput="this.setCustomValidity('')" />
                </div>
            </div>

            <div class="fieldBox">
                <div class="fieldRow">
                    <div class="editGroup">
                        <input type="checkbox" name="checkboxPHONE" value="Edited" />
                        <label class="checkboxLabel">Edit</label>
                        <input type="checkbox" name="deletePHONE" value="Remove" />
                        <label class="checkboxLabel">Delete</label>
                    </div>
                    <div class="fieldTitle">Phone</div>
                    <div class="fieldContent">
                        <input type="text" class="resourceDetailNormalWidth" id="resourceDetailPHONE" readonly />
                    </div>
                </div>
                <div class="editBlock" id="PHONE">
                    <input type="text" class="resourceDetailEditNormalWidth" id="resourceDetailEditPHONE" required oninvalid="this.setCustomValidity('Please enter the corrected Phone')" oninput="this.setCustomValidity('')" />
                </div>
            </div>

            <div class="fieldBox">
                <div class="fieldRow">
                    <div class="editGroup">
                        <input type="checkbox" name="checkboxEXT" value="Edited" />
                        <label class="checkboxLabel">Edit</label>
                        <input type="checkbox" name="deleteEXT" value="Remove" />
                        <label class="checkboxLabel">Delete</label>
                    </div>
                    <div class="fieldTitle">Extension</div>
                    <div class="fieldContent">
                        <input type="text" class="resourceDetailNormalWidth" id="resourceDetailEXT" readonly />
                    </div>
                </div>
                <div class="editBlock" id="EXT">
                    <input type="text" class="resourceDetailEditNormalWidth" id="resourceDetailEditEXT" required oninvalid="this.setCustomValidity('Please enter the corrected Phone Extension')" oninput="this.setCustomValidity('')" />
                </div>
            </div>

            <div class="fieldBox">
                <div class="fieldRow">
                    <div class="editGroup">
                        <input type="checkbox" name="checkboxHOTLINE" value="Edited" />
                        <label class="checkboxLabel">Edit</label>
                        <input type="checkbox" name="deleteHOTLINE" value="Remove" />
                        <label class="checkboxLabel">Delete</label>
                    </div>
                    <div class="fieldTitle">Toll-Free Phone</div>
                    <div class="fieldContent">
                        <input type="text" class="resourceDetailNormalWidth" id="resourceDetailHOTLINE" readonly />
                    </div>
                </div>
                <div class="editBlock" id="HOTLINE">
                    <input type="text" class="resourceDetailEditNormalWidth" id="resourceDetailEditHOTLINE" required oninvalid="this.setCustomValidity('Please enter the corrected Toll Free Phone Number')" oninput="this.setCustomValidity('')" />
                </div>
            </div>

            <div class="fieldBox">
                <div class="fieldRow">
                    <div class="editGroup">
                        <input type="checkbox" name="checkboxINTERNET" value="Edited" />
                        <label class="checkboxLabel">Edit</label>
                        <input type="checkbox" name="deleteINTERNET" value="Remove" />
                        <label class="checkboxLabel">Delete</label>
                    </div>
                    <div class="fieldTitle">Email Address</div>
                    <div class="fieldContent">
                        <input type="text" class="resourceDetailNormalWidth" id="resourceDetailINTERNET" readonly />
                    </div>
                </div>
                <div class="editBlock" id="INTERNET">
                    <input type="text" class="resourceDetailEditNormalWidth" id="resourceDetailEditINTERNET" required oninvalid="this.setCustomValidity('Please enter the corrected Email Address')" oninput="this.setCustomValidity('')" />
                </div>
            </div>

            <div class="fieldBox">
                <div class="fieldRow">
                    <div class="editGroup">
                        <input type="checkbox" name="checkboxDESCRIPT" value="Edited" />
                        <label class="checkboxLabel">Edit</label>
                        <input type="checkbox" name="deleteDESCRIPT" value="Remove" />
                        <label class="checkboxLabel">Delete</label>
                    </div>
                    <div class="fieldTitle">Description</div>
                    <div class="fieldContent">
                        <textarea id="resourceDetailDESCRIPT" readonly></textarea>
                    </div>
                </div>
                <div class="editBlock" id="DESCRIPT">
                    <textarea id="resourceDetailEditDESCRIPT" maxlength="105" required oninvalid="this.setCustomValidity('Please enter the corrected Description')" oninput="this.setCustomValidity('')"></textarea>
                </div>
            </div>

            <div class="fieldBox">
                <div class="fieldRow">
                    <div class="editGroup">
                        <input type="checkbox" name="checkboxNOTE" value="Edited" />
                        <label class="checkboxLabel">Edit</label>
                        <input type="checkbox" name="deleteNOTE" value="Remove" />
                        <label class="checkboxLabel">Delete</label>
                    </div>
                    <div class="fieldTitle">Note</div>
                    <div class="fieldContent">
                        <textarea class="resourceDetailNote" id="resourceDetailNOTE" readonly></textarea>
                    </div>
                </div>
                <div class="editBlock" id="NOTE">
                    <textarea class="resourceDetailNote" id="resourceDetailEditNOTE" required oninvalid="this.setCustomValidity('Please enter the corrected Note for this resource')" oninput="this.setCustomValidity('')"></textarea>
                </div>
            </div>

            <div class="fieldBox">
                <div class="fieldTitle">Categories</div>
                <div class="fieldContent">
                    <select id="resourceDetailTYPE1" class="nextCategory" name="resourceDetailTYPE1" required></select>
                    <select id="resourceDetailTYPE2" class="nextCategory" name="resourceDetailTYPE2" ></select>
                    <select id="resourceDetailTYPE3" class="nextCategory" name="resourceDetailTYPE3" ></select>
                    <select id="resourceDetailTYPE4" class="nextCategory" name="resourceDetailTYPE4" ></select>
                    <select id="resourceDetailTYPE5" class="nextCategory" name="resourceDetailTYPE5" ></select>
                    <select id="resourceDetailTYPE6" class="nextCategory" name="resourceDetailTYPE6" ></select>
                    <select id="resourceDetailTYPE7" class="nextCategory" name="resourceDetailTYPE7" ></select>
                    <select id="resourceDetailTYPE8" class="nextCategory" name="resourceDetailTYPE8" ></select>
                </div>
            </div>

            <input type="hidden" id="resourceDetailCLOSED" name="resourceDetailCLOSED" value="" />

            <div class="fieldBox">
                <h3>NOTES TO AARON/TANYA</h3>
                <div class="fieldContent">
                    <textarea id="notesToAaron"></textarea>
                </div>
            </div>
            <div id="resourcePaneControl">
                <input type="button" id="Cancel" class="smallButton" value="Skip" title="Skip this resource and load another one."/>
                <input type="button" id="Close" class="smallButton" value="Close Record" title="Close this Resource." onclick="closeRecord(event)"/>
                <input type="submit" id="Update" class="smallButton" value="SAVE" title="Save your changes and load another resource."/>
                <input type="button" id="ExitButton" class="smallButton" value="Exit Program" title="Exit the Program.  Your changes will not be saved."/>      
            </div>
        </form>
    </div>
    <div id="validationErrorWindow"></div>
    <input type="hidden" id="adminFlag" value="<?php echo $editResourceStatus; ?>" />
    <input type="hidden" id="UserID" value="<?php echo $UserID; ?>" />
</body>
</html>
