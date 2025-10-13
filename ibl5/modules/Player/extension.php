<?php

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

echo "<HTML><HEAD><TITLE>Contract Extension Offer Result</TITLE></HEAD><BODY>";

// Collect input data
$Team_Name = $_POST['teamname'];
$playerID = $_POST['playerID'];
$Player_Name = $_POST['playername'];
$Demands_Years = $_POST['demyrs'];
$Demands_Total = $_POST['demtot'] * 100;
$Bird = $_POST['bird'];

// Build offer array
$offer = [
    'year1' => (int) $_POST['offeryear1'],
    'year2' => (int) $_POST['offeryear2'],
    'year3' => (int) $_POST['offeryear3'],
    'year4' => (int) $_POST['offeryear4'],
    'year5' => (int) $_POST['offeryear5']
];

// Build demands array
$demands = [
    'total' => $Demands_Total,
    'years' => $Demands_Years
];

// Build extension data for processor
$extensionData = [
    'playerID' => $playerID,
    'teamName' => $Team_Name,
    'playerName' => $Player_Name,
    'offer' => $offer,
    'demands' => $demands,
    'bird' => $Bird
];

// Process extension using new architecture
$processor = new \Extension\ExtensionProcessor($db);
$result = $processor->processExtension($extensionData);

// Display results
if (!$result['success']) {
    // Validation error - offer not legal
    echo "<font color=#ff0000>" . $result['error'] . "<br>";
    echo "Your extension attempt was not legal and will not be recorded as an attempt. If you have not yet successfully extended a player this season, and have not yet made a successful offer this sim, you may press the \"Back\" Button on your browser to try again.</font>";
} else {
    // Legal offer was made
    echo "Message from the commissioner's office: <font color=#0000cc>Your offer is legal, and is therefore an extension attempt. Please note that you may make no further extension attempts until after the next sim.</font><br>";
    
    if ($result['accepted']) {
        // Offer was accepted
        echo "<table bgcolor=#cccccc><tr><td><b>Response from $Player_Name:</b> " . $result['message'] . "</td></tr></table>";
        echo "Note from the commissioner's office: <font color=#cc0000>Please note that you have used up your successful extension for this season and may not make any more extension attempts.</font><br>";
        
        if ($_SERVER['SERVER_NAME'] != "localhost") {
            echo "<center>An e-mail regarding this extension has been successfully sent to the commissioner's office. Thank you.</center>";
        }
    } else {
        // Offer was rejected
        echo "<table bgcolor=#cccccc><tr><td><b>Response from $Player_Name:</b> " . $result['message'] . "</td></tr></table>";
        echo "Note from the commissioner's office: <font color=#cc0000>Please note that you will be able to make another attempt next sim as you have not yet used up your successful extension for this season.</font><br>";
    }
}

echo "</BODY></HTML>";

