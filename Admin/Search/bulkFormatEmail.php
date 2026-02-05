<?php

require_once('../../../private_html/db_login.php');
session_start();
if (!isset($_SESSION)) {
}

function formatMessage($idNum) {
    // Include the database configuration
    
    // Get resource data using prepared statement
    $query = "SELECT 
        TRIM(idnum) as idnum,
        TRIM(name) as name,
        TRIM(name2) as name2,
        address1,
        address2,
        TRIM(city) as city,
        state,
        TRIM(linkableZip) as zip,
        TRIM(type1) as type1,
        TRIM(type2) as type2,
        TRIM(type3) as type3,
        TRIM(type4) as type4,
        TRIM(contact) as contact,
        TRIM(phone) as phone,
        TRIM(descript) as descript,
        TRIM(note) as note,
        ' ' as Distance,
        TRIM(hotline) as hotline,
        TRIM(fax) as fax,
        TRIM(internet) as email,
        TRIM(wwweb) as web,
        TRIM(edate) as edate,
        longitude,
        latitude,
        ext,
        mailpage,
        showmail,
        website,
        cnational,
        closed,
        Give_Addr,
        TRIM(wwweb2) as web2,
        TRIM(wwweb3) as web3
    FROM resource 
    WHERE idnum = ?";

    $result = dataQuery($query, [$idNum]);
    
    if (!$result || !is_array($result) || empty($result)) {
        return [null, null, "No resource found with ID: " . htmlspecialchars($idNum)];
    }

    $resource = $result[0];
    
    // Clean toll-free number if needed
    if ($resource->hotline === "-   -") {
        $resource->hotline = " ";
    }

    // Start building HTML message
    $messageText = generateEmailHeader();
    $messageText .= generateResourceInfo($resource);
    $messageText .= generateEmailFooter();

    // Generate subject line
    $subject = "Trying to update your contact info - " . $resource->name;
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    return [$resource->email, $encodedSubject, $messageText];
}

function generateEmailHeader() {
    return <<<HTML
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body {
            font-family: Calibri, Candara, Segoe, 'Segoe UI', Optima, Arial, sans-serif;
        }
        h3 {
            color: rgb(251, 2, 7);
            font-style: italic;
            font-weight: bold;
        }
        p, span {
            color: rgb(13, 13, 20);
            line-height: 1.6;
        }
        a {
            color: rgb(58, 85, 216);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h3>
        This is a call of action: update your contact information to ensure the LGBTQ+ community can find community, support, and resources in their cities, towns, and neighborhoods.
    </h3>
    <p>
        <span style='color: rgb(251, 0, 7); font-weight: bold;'>
            It's vital that the community knows they are not alone in their area, which is why we're reaching out to confirm the contact information we have for you is still accurate and up to date.
        </span>
        <span>
            Please check the data listed below and let us know if it is correct, if you would like to make any changes, or if you would like to expand on any of the information. You can either email us with those changes by replying to this email, or you can call our office at 415-355-0003. If there has been any name change, please include the previous name in your reply.
        </span>
    </p>
    <p>
        <span style='color: black; font-weight: bold;'>
            EVEN IF THE INFO IS CORRECT, WE NEED TO RECEIVE A CONFIRMATION FROM YOU IN ORDER TO CONTINUE TO INCLUDE YOU IN LGBTNEARME, THE LGBTQ+ DATABASE.
        </span>
        <span style='color: rgb(251, 0, 7); font-weight: bold;'>
            PLEASE KNOW THAT BEING LISTED AND UPDATING YOUR INFORMATION IS <span style='text-decoration: underline;'>FREE</span>.
        </span>
    </p>
    <p>
        We are the <strong>LGBT National Help Center</strong>. For nearly 30 years, we have been offering vital support and community connections to countless thousands in need. We are a non-profit support agency and civil rights organization. Our mission is to provide a safe space to explore feelings and concerns and to provide community connections to the entire LGBTQ+ community. We operate the LGBT National Hotline, LGBT Coming Out Support Hotline, LGBT Youth Talkline, and LGBT Senior Hotline, answering calls in the US and Canada. We also have the LGBTQ 1-to-1 Online Chat, offering text-based support for those in the US, Canada, and internationally. You can learn more at:
        <strong><a href='http://www.lgbthotline.org/'>www.LGBThotline.org</a></strong>.
    </p>
    <p>
        We also maintain <strong>LGBTnearMe</strong>, the LGBTQ+ Database, the largest LGBTQ+ resource database of its kind in the United States, Canada, and internationally. With nearly 19,000 resources, it's used in our peer support conversations to help those in need find local LGBTQ+ resources, organizations, support, and businesses. LGBTnearMe is also available for easy and fast access to the public at any time to find community resources like you at
        <strong><a href='http://www.lgbtnearme.org/'>www.LGBTnearMe.org</a></strong>. Together, we can keep the community strong and connected!
    </p>
    <p>Thank you for being an essential part of the LGBTQ+ community. Here's the information we have for your listing:</p>
HTML;
}

function generateResourceInfo($resource) {
    $info = '<p style="color: red;">' . htmlspecialchars($resource->name);

    // Add optional fields if they exist
    if (!empty($resource->name2)) {
        $info .= "<br />" . htmlspecialchars($resource->name2);
    }

    // Address information
    if (!empty($resource->address1)) {
        $info .= "<br />" . htmlspecialchars($resource->address1);
    } else {
        $info .= "<br />Address: None (please provide if applicable)";
    }

    if (!empty($resource->address2)) {
        $info .= "<br />" . htmlspecialchars($resource->address2);
    }

    // City, State, Zip
    if (empty($resource->city) && empty($resource->state)) {
        $info .= "<br />City and State: none (please provide if applicable)<br />Zip: ";
    }

    if (!empty($resource->city)) {
        $info .= "<br />" . htmlspecialchars($resource->city);
    }

    if (!empty($resource->state)) {
        $info .= ", " . htmlspecialchars($resource->state);
    }

    if (!empty($resource->zip)) {
        $info .= "  " . htmlspecialchars($resource->zip);
    }

    $info .= "<br />";

    // Contact information
    if (strlen($resource->contact) < 2) {
        $info .= "<br />Contact: None (please provide if applicable)";
    } else {
        $info .= "<br />Contact Name: " . htmlspecialchars($resource->contact);
    }

    // Phone numbers
    if (strlen($resource->phone) < 6) {
        $info .= "<br />Phone: None (please provide if applicable)";
    } else {
        $info .= "<br />Phone: " . htmlspecialchars($resource->phone);
    }

    if (strlen($resource->hotline) < 6) {
        $info .= "<br />Toll Free: None (please provide if applicable)";
    } else {
        $info .= "<br />Toll Free: " . htmlspecialchars($resource->hotline);
    }

    if (strlen($resource->fax) < 6) {
        $info .= "<br />Fax: None (please provide if applicable)";
    } else {
        $info .= "<br />Fax: " . htmlspecialchars($resource->fax);
    }

    $info .= "<br />";

    // Online presence
    if (!empty($resource->email)) {
        $info .= "<br />Email: <a href='mailto:" . htmlspecialchars($resource->email) . "' style='color: blue;'>" 
               . htmlspecialchars($resource->email) . "</a>";
    } else {
        $info .= "<br />Email: None (please provide if applicable)";
    }

    if (!empty($resource->web)) {
        $info .= "<br />Website: <a href='" . htmlspecialchars($resource->web) . "' style='color: blue;'>" 
               . htmlspecialchars($resource->web) . "</a>";
    } else {
        $info .= "<br />Website: None (please provide if applicable)";
    }

    if (!empty($resource->web2)) {
        $info .= "<br />Website 2: <a href='" . htmlspecialchars($resource->web2) . "' style='color: blue;'>" 
               . htmlspecialchars($resource->web2) . "</a>";
    }

    if (!empty($resource->web3)) {
        $info .= "<br />Website 3: <a href='" . htmlspecialchars($resource->web3) . "' style='color: blue;'>" 
               . htmlspecialchars($resource->web3) . "</a>";
    }

    // Description and notes
    if (!empty($resource->descript)) {
        $info .= "<br />" . htmlspecialchars($resource->descript);
    }

    if (!empty($resource->note)) {
        $info .= "<br /><br />" . nl2br(htmlspecialchars($resource->note));
    }

    return $info;
}

function generateEmailFooter() {
    return <<<HTML
    <p><img src='https://vcctest.org/Admin/Search2/3ProudlyListed.jpg' alt='Proudly Listed Logo'></p>
    <p>
        <em>Tatiana Fiermonte<br />
        Administrative Assistant<br />
        LGBT National Help Center<br />
        Direct Phone: 415-355-0003<br />
        Email: <a href='mailto:tatiana@LGBTHotline.org'>tatiana@LGBTHotline.org</a><br />
        Website: <a href='http://LGBThotline.org'>www.LGBThotline.org</a><br />
        Resources: <a href='http://LGBTnearMe.org'>www.LGBTnearMe.org</a><br />
        Facebook: <a href='http://facebook.com/LGBTNationalHelpCenter'>facebook.com/LGBTNationalHelpCenter</a><br />
        Twitter: <a href='http://twitter.com/LGBTNatlHelpCtr'>twitter.com/LGBTNatlHelpCtr</a><br />
        Instagram: <a href='http://www.instagram.com/lgbt_national_hotline'>www.instagram.com/lgbt_national_hotline</a>
    </p>
</body>
</html>
HTML;
}
?>
