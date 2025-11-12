<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

use Player\Player;

$sharedFunctions = new Shared($db);
$commonRepository = new Services\CommonRepository($db);

// Handle POST requests for button actions
$actionMessage = '';
$actionCompleted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'assign_free_agents' && isset($_POST['sql_queries'])) {
            // Execute the SQL queries from $code
            $queries = $_POST['sql_queries'];
            if (!empty($queries)) {
                // Split queries by semicolon and execute each one
                $queryArray = array_filter(array_map('trim', explode(';', $queries)));
                $successCount = 0;
                $errorCount = 0;
                
                foreach ($queryArray as $query) {
                    if (!empty($query)) {
                        if ($db->sql_query($query)) {
                            $successCount++;
                        } else {
                            $errorCount++;
                        }
                    }
                }
                
                // Add news story INSERT query
                if ($successCount > 0 && isset($_POST['news_hometext']) && isset($_POST['news_bodytext']) && isset($_POST['day'])) {
                    // Escape the text content for SQL
                    $hometext = Services\DatabaseService::escapeString($db, $_POST['news_hometext']);
                    $bodytext = Services\DatabaseService::escapeString($db, $_POST['news_bodytext']);
                    $day = (int)$_POST['day'];
                    
                    // Get current timestamp in MySQL format
                    $currentTime = date('Y-m-d H:i:s');
                    
                    // Build the INSERT query (sid will auto-increment)
                    $newsInsertQuery = "INSERT INTO `nuke_stories` 
                        (`catid`, `aid`, `title`, `time`, `hometext`, `bodytext`, `comments`, `counter`, `topic`, `informant`, `notes`, `ihome`, `alanguage`, `acomm`, `haspoll`, `pollID`, `score`, `ratings`, `rating_ip`, `associated`)
                        VALUES
                        (8, 'chibul', '2006 IBL Free Agency, Days $day-$day', '$currentTime', '$hometext', '$bodytext', 0, 2, 29, 'chibul', '', 0, 'english', 0, 0, 0, 0, 0, '0', '29-')";
                    
                    if ($db->sql_query($newsInsertQuery)) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                }
                
                if ($errorCount === 0 && $successCount > 0) {
                    $actionMessage = "Successfully executed $successCount SQL queries. Free agents have been assigned to teams.";
                    $actionCompleted = true;
                } elseif ($errorCount > 0) {
                    $actionMessage = "Completed with errors: $successCount queries succeeded, $errorCount queries failed.";
                } else {
                    $actionMessage = "No queries were executed.";
                }
            } else {
                $actionMessage = "No SQL queries found to execute.";
            }
        } elseif ($_POST['action'] === 'clear_offers') {
            // Truncate the ibl_fa_offers table
            $truncateQuery = "TRUNCATE TABLE ibl_fa_offers";
            if ($db->sql_query($truncateQuery)) {
                $actionMessage = "Successfully cleared all free agency offers from the database.";
            } else {
                $actionMessage = "Error: Failed to clear free agency offers - " . $db->sql_error();
            }
        }
    }
}

$val = $_GET['day'];

$query = "SELECT ibl_fa_offers.*, ibl_plr.bird
FROM ibl_fa_offers
JOIN ibl_plr ON ibl_fa_offers.name = ibl_plr.name
ORDER BY ibl_fa_offers.name ASC, ibl_fa_offers.perceivedvalue DESC";
$result = $db->sql_query($query);
$num = $db->sql_numrows($result);

echo "<HTML>
	<HEAD>
        <TITLE>Free Agent Processing</TITLE>
        <style>
            .modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: none;
                justify-content: center;
                align-items: center;
                z-index: 1000;
            }
            .modal-overlay.active {
                display: flex;
            }
            .modal-content {
                background: white;
                padding: 20px;
                border-radius: 5px;
                text-align: center;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }
            .modal-buttons {
                margin-top: 20px;
            }
            .btn-run {
                background: #dc3545;
                color: white;
                border: none;
                padding: 10px 20px;
                margin: 0 10px;
                border-radius: 5px;
                cursor: pointer;
            }
            .btn-cancel {
                background: white;
                color: black;
                border: 1px solid #ccc;
                padding: 10px 20px;
                margin: 0 10px;
                border-radius: 5px;
                cursor: pointer;
            }
            .action-button {
                background: #007bff;
                color: white;
                border: none;
                padding: 10px 20px;
                margin-top: 10px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
            }
            .action-button:hover {
                background: #0056b3;
            }
            .action-button-red {
                background: #dc3545;
                color: white;
                border: none;
                padding: 10px 20px;
                margin-top: 10px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
            }
            .action-button-red:hover {
                background: #c82333;
            }
        </style>
    </HEAD>
	<BODY>
        <H1>You are viewing <font color=red>Day " . ($val) . "</font> results!</H1>
        <H2>Total number of offers: $num</H2>
		<TABLE BORDER=1>
			<TR style=\"font-weight:bold\">
				<TD COLSPAN=8>Free Agent Signings</TD>
                <TD>Bird</TD>
				<TD>MLE</TD>
				<TD>LLE</TD>
			</TR>";

$discordText = "";
$offerText = "";
$outcomeText = "";
$autoRejectedText = "These offers have been **auto-rejected** for being under half of the player's demands:";
$lastPlayerIteratedOn = "";
$i = 0;
while ($i < $num) {
    $name = $db->sql_result($result, $i, "name");
    $playerID = $commonRepository->getPlayerIDFromPlayerName($name);
    $player = Player::withPlayerID($db, $playerID);
    $teamOfPlayer = Team::initialize($db, $player->teamName);
    $offeringTeamName = $db->sql_result($result, $i, "team");
    $offeringTeam = Team::initialize($db, $offeringTeamName);
    $perceivedvalue = $db->sql_result($result, $i, "perceivedvalue");

    if ($lastPlayerIteratedOn != $player->name) {
        $discordText .= $offerText;
        $offerText = "";
        if ($outcomeText) {
            $discordText .= $outcomeText;
            if ($offerAccepted) {
                $discordText .= " <@!$acceptedTeamDiscordID>\n\n";
            }
        }
        $discordText .= "**" . strtoupper("$player->name, $teamOfPlayer->city $player->teamName") . "** <@!$teamOfPlayer->discordID>\n";
    }

    $offer1 = $db->sql_result($result, $i, "offer1");
    $offer2 = $db->sql_result($result, $i, "offer2");
    $offer3 = $db->sql_result($result, $i, "offer3");
    $offer4 = $db->sql_result($result, $i, "offer4");
    $offer5 = $db->sql_result($result, $i, "offer5");
    $offer6 = $db->sql_result($result, $i, "offer6");

    $birdYears = $db->sql_result($result, $i, "bird");
    $MLE = $db->sql_result($result, $i, "MLE");
    $LLE = $db->sql_result($result, $i, "LLE");
    $random = $db->sql_result($result, $i, "random");

    $query2 = "SELECT * FROM `ibl_demands` WHERE name = '$name'";
    $result2 = $db->sql_query($query2);
    $num2 = $db->sql_numrows($result2);

    $dem1 = $db->sql_result($result2, 0, "dem1");
    $dem2 = $db->sql_result($result2, 0, "dem2");
    $dem3 = $db->sql_result($result2, 0, "dem3");
    $dem4 = $db->sql_result($result2, 0, "dem4");
    $dem5 = $db->sql_result($result2, 0, "dem5");
    $dem6 = $db->sql_result($result2, 0, "dem6");

    $offeryears = 6;
    if ($offer6 == 0) {
        $offeryears = 5;
    }
    if ($offer5 == 0) {
        $offeryears = 4;
    }
    if ($offer4 == 0) {
        $offeryears = 3;
    }
    if ($offer3 == 0) {
        $offeryears = 2;
    }
    if ($offer2 == 0) {
        $offeryears = 1;
    }
    $offertotal = ($offer1 + $offer2 + $offer3 + $offer4 + $offer5 + $offer6) / 100;

    $demyrs = 6;
    if ($dem6 == 0) {
        $demyrs = 5;
    }
    if ($dem5 == 0) {
        $demyrs = 4;
    }
    if ($dem4 == 0) {
        $demyrs = 3;
    }
    if ($dem3 == 0) {
        $demyrs = 2;
    }
    if ($dem2 == 0) {
        $demyrs = 1;
    }
    $demands = ($dem1 + $dem2 + $dem3 + $dem4 + $dem5 + $dem6) / $demyrs * ((11 - $val) / 10);

    if ($perceivedvalue > $demands / 2) {
        $offerText .= "$offeringTeamName - $offer1";
        if ($offer2 != 0) {$offerText .= "/$offer2";}
        if ($offer3 != 0) {$offerText .= "/$offer3";}
        if ($offer4 != 0) {$offerText .= "/$offer4";}
        if ($offer5 != 0) {$offerText .= "/$offer5";}
        if ($offer6 != 0) {$offerText .= "/$offer6";}
        $offerText .= " <@!$offeringTeam->discordID>\n";
    } else {
        $autoRejectedText .= "\n<@!$offeringTeam->discordID>'s offer for $player->name: ";
        $autoRejectedText .= "$offeringTeamName - $offer1";
        if ($offer2 != 0) {$autoRejectedText .= "/$offer2";}
        if ($offer3 != 0) {$autoRejectedText .= "/$offer3";}
        if ($offer4 != 0) {$autoRejectedText .= "/$offer4";}
        if ($offer5 != 0) {$autoRejectedText .= "/$offer5";}
        if ($offer6 != 0) {$autoRejectedText .= "/$offer6";}
    }

    if ($lastPlayerIteratedOn != $name) {
        if ($perceivedvalue > $demands) {
            echo " <TR>
                <TD>$name</TD>
                <TD>$offeringTeamName</TD>
                <TD>$offer1</TD>
                <TD>$offer2</TD>
                <TD>$offer3</TD>
                <TD>$offer4</TD>
                <TD>$offer5</TD>
                <TD>$offer6</TD>
                <TD>$birdYears</TD>
                <TD>$MLE</TD>
                <TD>$LLE</TD>
            </TR>";
            $offerAccepted = TRUE;
            $acceptedTeamDiscordID = $offeringTeam->discordID;
            $outcomeText = $name . " accepts the " . $offeringTeamName . " offer of a " . $offeryears . "-year deal worth a total of " . $offertotal . " million dollars.";
            $text .= $outcomeText . "<br>\n";
            $code .= "UPDATE `ibl_plr`
				SET `cy` = '0',
					`cy1` = '" . $offer1 . "',
					`cy2` = '" . $offer2 . "',
					`cy3` = '" . $offer3 . "',
					`cy4` = '" . $offer4 . "',
					`cy5` = '" . $offer5 . "',
					`cy6` = '" . $offer6 . "',
					`teamname` = '" . $offeringTeamName . "',
					`cyt` = '" . $offeryears . "',
					`tid` = $offeringTeam->teamID
				WHERE `name` = '" . $name . "'
				LIMIT 1;";
            if ($MLE == 1) {
                $code .= "UPDATE `ibl_team_info` SET `HasMLE` = '0' WHERE `team_name` = '" . $offeringTeamName . "' LIMIT 1;";
            }
            if ($LLE == 1) {
                $code .= "UPDATE `ibl_team_info` SET `HasLLE` = '0' WHERE `team_name` = '" . $offeringTeamName . "' LIMIT 1;";
            }
        } else {
            $outcomeText = "**REJECTED**\n\n";
            $offerAccepted = FALSE;
        }
    }

    $nameholder = $name;
    $lastPlayerIteratedOn = $name;
    $i++;
}

$discordText .= $offerText;
$discordText .= $outcomeText;
if ($offerAccepted) {
    $discordText .= " <@!$acceptedTeamDiscordID>";
}

$i = 0;
echo "<TR style=\"font-weight:bold\">
    <TD COLSPAN=8>ALL OFFERS MADE</TD>
    <TD>Bird</TD>
    <TD>MLE</TD>
    <TD>LLE</TD>
    <TD>RANDOM</TD>
</TR> ";

while ($i < $num) {
    $name = $db->sql_result($result, $i, "name");
    $perceivedvalue = $db->sql_result($result, $i, "perceivedvalue");
    $offeringTeamName = $db->sql_result($result, $i, "team");
    $offeringTeam = Team::initialize($db, $offeringTeamName);

    $offer1 = $db->sql_result($result, $i, "offer1");
    $offer2 = $db->sql_result($result, $i, "offer2");
    $offer3 = $db->sql_result($result, $i, "offer3");
    $offer4 = $db->sql_result($result, $i, "offer4");
    $offer5 = $db->sql_result($result, $i, "offer5");
    $offer6 = $db->sql_result($result, $i, "offer6");

    $birdYears = $db->sql_result($result, $i, "bird");
    $MLE = $db->sql_result($result, $i, "MLE");
    $LLE = $db->sql_result($result, $i, "LLE");
    $random = $db->sql_result($result, $i, "random");

    echo "<TR>
        <TD>$name</TD>
        <TD>$offeringTeamName</TD>
        <TD>$offer1</TD>
        <TD>$offer2</TD>
        <TD>$offer3</TD>
        <TD>$offer4</TD>
        <TD>$offer5</TD>
        <TD>$offer6</TD>
        <TD>$birdYears</TD>
        <TD>$MLE</TD>
        <TD>$LLE</TD>
        <TD>$random</TD>
        <TD>$perceivedvalue</TD>
    </TR>";
    $offeryears = 6;
    if ($offer6 == 0) {
        $offeryears = 5;
    }
    if ($offer5 == 0) {
        $offeryears = 4;
    }
    if ($offer4 == 0) {
        $offeryears = 3;
    }
    if ($offer3 == 0) {
        $offeryears = 2;
    }
    if ($offer2 == 0) {
        $offeryears = 1;
    }
    $offertotal = ($offer1 + $offer2 + $offer3 + $offer4 + $offer5 + $offer6) / 100;

    $exttext .= "The " . $offeringTeamName . " offered " . $name . " a " . $offeryears . "-year deal worth a total of " . $offertotal . " million dollars.<br>\n";
    $i++;
}

echo "</TABLE>
    <hr>
    <FORM>";

if ($val < 12) {
    echo "<h2 style=\"color:red\">AUTO-REJECTED OFFERS</H2>
        <TEXTAREA COLS=85 ROWS=20>$autoRejectedText</TEXTAREA>
        <hr>";
}

echo "  <h2 style=\"color:#7289da\">ALL REMAINING OFFERS IN DISCORD FORMAT (FOR <a href=\"https://discord.com/channels/666986450889474053/682990441641279531\">#live-sims</a>)</h2>
        <TEXTAREA style=\"font-size: 24px\" COLS=85 ROWS=20>$discordText</TEXTAREA>
        <hr>
        <h2 id=\"sqlQueryBoxHeader\">SQL QUERY BOX</h2>";

// Display action message if any action was completed
if (!empty($actionMessage)) {
    echo "<TEXTAREA id=\"sqlQueryBox\" style=\"color: #007bff;\" COLS=125 ROWS=20>$actionMessage</TEXTAREA>";
} else {
    echo "<TEXTAREA id=\"sqlQueryBox\" COLS=125 ROWS=20>$code</TEXTAREA>";
}

// Show the appropriate button based on whether action was completed
if ($actionCompleted) {
    echo '<br><button type="button" class="action-button-red" onclick="showClearOffersModal()">Clear All Free Agency Offers</button>';
} else {
    echo '<br><button type="button" class="action-button" onclick="showAssignFreeAgentsModal()">Assign Free Agents to Teams and Insert News Story</button>';
}

echo "  <hr>
        <h2>ACCEPTED OFFERS IN HTML FORMAT (FOR NEWS ARTICLE)</h2>";

// Display action message in news textareas if action was completed
if (!empty($actionMessage)) {
    echo "<TEXTAREA id=\"newsHometextArea\" style=\"color: #007bff;\" COLS=125 ROWS=20>News story successfully posted to the database.</TEXTAREA>";
} else {
    echo "<TEXTAREA id=\"newsHometextArea\" COLS=125 ROWS=20>$text</TEXTAREA>";
}

echo "  <hr>
        <h2>ALL OFFERS IN HTML FORMAT (FOR NEWS ARTICLE EXTENDED TEXT)</h2>";

if (!empty($actionMessage)) {
    echo "<TEXTAREA id=\"newsBodytextArea\" style=\"color: #007bff;\" COLS=125 ROWS=20>News story successfully posted to the database.</TEXTAREA>";
} else {
    echo "<TEXTAREA id=\"newsBodytextArea\" COLS=125 ROWS=20>$exttext</TEXTAREA>";
}

echo "  </FORM>
    
    <!-- Modal for Assign Free Agents -->
    <div id=\"assignFreeAgentsModal\" class=\"modal-overlay\">
        <div class=\"modal-content\">
            <h2>WARNING</h2>
            <p>Are you sure you want to assign the free agents to teams?</p>
            <p>This will execute the SQL queries and update player contracts.</p>
            <div class=\"modal-buttons\">
                <form method=\"POST\" id=\"assignFreeAgentsForm\">
                    <input type=\"hidden\" name=\"action\" value=\"assign_free_agents\">
                    <input type=\"hidden\" name=\"sql_queries\" id=\"sqlQueriesInput\" value=\"\">
                    <input type=\"hidden\" name=\"news_hometext\" id=\"newsHometextInput\" value=\"\">
                    <input type=\"hidden\" name=\"news_bodytext\" id=\"newsBodytextInput\" value=\"\">
                    <input type=\"hidden\" name=\"day\" id=\"dayInput\" value=\"\">
                    <button type=\"submit\" class=\"btn-run\">Yes</button>
                    <button type=\"button\" class=\"btn-cancel\" onclick=\"closeModal('assignFreeAgentsModal')\">No</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal for Clear Offers -->
    <div id=\"clearOffersModal\" class=\"modal-overlay\">
        <div class=\"modal-content\">
            <h2>WARNING</h2>
            <p>Are you sure you want to clear all Free Agency Offers?</p>
            <p>This will truncate the ibl_fa_offers table and remove all offers.</p>
            <div class=\"modal-buttons\">
                <form method=\"POST\">
                    <input type=\"hidden\" name=\"action\" value=\"clear_offers\">
                    <button type=\"submit\" class=\"btn-run\">Yes</button>
                    <button type=\"button\" class=\"btn-cancel\" onclick=\"closeModal('clearOffersModal')\">No</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function showAssignFreeAgentsModal() {
            // Get the SQL queries from the textarea
            var sqlQueries = document.getElementById('sqlQueryBox').value;
            document.getElementById('sqlQueriesInput').value = sqlQueries;
            
            // Get the news text from the textareas
            var newsHometext = document.getElementById('newsHometextArea') ? document.getElementById('newsHometextArea').value : '';
            var newsBodytext = document.getElementById('newsBodytextArea') ? document.getElementById('newsBodytextArea').value : '';
            document.getElementById('newsHometextInput').value = newsHometext;
            document.getElementById('newsBodytextInput').value = newsBodytext;
            
            // Get the day parameter from URL
            var urlParams = new URLSearchParams(window.location.search);
            var day = urlParams.get('day') || '';
            document.getElementById('dayInput').value = day;
            
            // Show the modal
            document.getElementById('assignFreeAgentsModal').classList.add('active');
        }
        
        function showClearOffersModal() {
            document.getElementById('clearOffersModal').classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            var modals = document.getElementsByClassName('modal-overlay');
            for (var i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].classList.remove('active');
                }
            }
        }
        
        // Scroll to SQL QUERY BOX header after action is completed
        window.addEventListener('load', function() {
            var textarea = document.getElementById('sqlQueryBox');
            if (textarea && textarea.style.color === 'rgb(0, 123, 255)') {
                var header = document.getElementById('sqlQueryBoxHeader');
                if (header) {
                    header.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    </script>
</HTML>";
