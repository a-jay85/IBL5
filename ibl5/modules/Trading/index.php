<?php

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Team Pages";

function menu()
{
    global $mysqli_db;

    Nuke\Header::header();
    OpenTable();
    CloseTable();
    Nuke\Footer::footer();
}

function buildTeamFutureSalary($resultTeamPlayers, $k)
{
    global $mysqli_db;
    $uiHelper = new Trading\UIHelper($mysqli_db);
    return $uiHelper->buildTeamFutureSalary($resultTeamPlayers, $k);
}

function buildTeamFuturePicks($resultTeamPicks, $future_salary_array)
{
    global $mysqli_db;
    $uiHelper = new Trading\UIHelper($mysqli_db);
    return $uiHelper->buildTeamFuturePicks($resultTeamPicks, $future_salary_array);
}

function tradeoffer($username)
{
    global $partner, $mysqli_db;
    $commonRepository = new \Services\CommonMysqliRepository($mysqli_db);
    $season = new Season($mysqli_db);

    $teamlogo = $commonRepository->getTeamnameFromUsername($username);
    $teamID = $commonRepository->getTidFromTeamname($teamlogo);
    $currentSeasonEndingYear = $season->endingYear; // we use this as an incrementer

    Nuke\Header::header();
    OpenTable();

    $queryUserTeamPlayers = "SELECT pos, name, pid, ordinal, cy, cy1, cy2, cy3, cy4, cy5, cy6
		FROM ibl_plr
		WHERE tid = $teamID
		AND retired = '0'
		ORDER BY ordinal ASC ";
    $resultUserTeamPlayers = $mysqli_db->query($queryUserTeamPlayers);

    $queryUserTeamDraftPicks = "SELECT *
		FROM ibl_draft_picks
		WHERE ownerofpick = '$teamlogo'
		ORDER BY year, round ASC ";
    $resultUserTeamDraftPicks = $mysqli_db->query($queryUserTeamDraftPicks);

    echo "<form name=\"Trade_Offer\" method=\"post\" action=\"/ibl5/modules/Trading/maketradeoffer.php\">
		<input type=\"hidden\" name=\"offeringTeam\" value=\"$teamlogo\">
		<div style=\"text-align: center;\">
			<img src=\"images/logo/$teamID.jpg\" alt=\"Team Logo\" class=\"team-logo-banner\"><br>
			<h2 class=\"ibl-table-title\">Trading Menu</h2>
			<table class=\"trading-layout\">
				<tr>
					<td style=\"vertical-align: top;\">
						<table class=\"ibl-data-table trading-roster\">
							<thead>
								<tr>
									<th colspan=\"4\">$teamlogo</th>
								</tr>
								<tr>
									<th>Select</th>
									<th>Pos</th>
									<th>Name</th>
									<th>Salary</th>
								</tr>
							</thead>
							<tbody>";

    $future_salary_array = buildTeamFutureSalary($resultUserTeamPlayers, 0);
    $future_salary_array = buildTeamFuturePicks($resultUserTeamDraftPicks, $future_salary_array);
    $k = $future_salary_array['k']; // pull $k value out to populate $Fields_Counter in maketradeoffer.php

    echo "</tbody></table>
					</td>
					<td style=\"vertical-align: top;\">
						<input type=\"hidden\" name=\"switchCounter\" value=\"$k\">
						<input type=\"hidden\" name=\"listeningTeam\" value=\"$partner\">
						<table class=\"ibl-data-table trading-roster\">
							<thead>
								<tr>
									<th colspan=\"4\">$partner</th>
								</tr>
								<tr>
									<th>Select</th>
									<th>Pos</th>
									<th>Name</th>
									<th>Salary</th>
								</tr>
							</thead>
							<tbody>";

    $partnerTeamID = $commonRepository->getTidFromTeamname($partner);
    $queryPartnerTeamPlayers = "SELECT pos, name, pid, ordinal, cy, cy1, cy2, cy3, cy4, cy5, cy6
		FROM ibl_plr
		WHERE tid = $partnerTeamID
		AND retired = '0'
		ORDER BY ordinal ASC ";
    $resultPartnerTeamPlayers = $mysqli_db->query($queryPartnerTeamPlayers);

    $queryPartnerTeamDraftPicks = "SELECT *
		FROM ibl_draft_picks
		WHERE ownerofpick = '$partner'
		ORDER BY year, round ASC ";
    $resultPartnerTeamDraftPicks = $mysqli_db->query($queryPartnerTeamDraftPicks);

    $future_salary_arrayb = buildTeamFutureSalary($resultPartnerTeamPlayers, $k);
    $future_salary_arrayb = buildTeamFuturePicks($resultPartnerTeamDraftPicks, $future_salary_arrayb);
    $k = $future_salary_arrayb['k']; // pull $k value out to populate $Fields_Counter in maketradeoffer.php

    $k--;
    echo "</tbody></table>
					</td>
					<td style=\"vertical-align: top;\">";

    $uiHelper = new Trading\UIHelper($mysqli_db);
    $teams = $uiHelper->getAllTeamsForTrading();
    echo $uiHelper->renderTeamSelectionLinks($teams);

    echo "</td>";
    echo "</tr>";

    // Cap totals section
    echo "<tr><td colspan=\"3\"><table class=\"ibl-data-table trading-cap-totals\" style=\"width: 100%; margin-top: 1rem;\">
        <thead><tr><th colspan=\"2\">Cap Totals</th></tr></thead>
        <tbody>";

    $z = 0;
    $seasonsToDisplay = 6;
    if (
        $season->phase === "Playoffs"
        || $season->phase === "Draft"
        || $season->phase === "Free Agency"
    ) {
        $currentSeasonEndingYear++;
        $seasonsToDisplay--;
    }
    while ($z < $seasonsToDisplay) {
        $yearLabel = ($currentSeasonEndingYear + $z - 1) . "-" . ($currentSeasonEndingYear + $z);
        echo "<tr>
            <td style=\"text-align: left;\"><strong>{$teamlogo}</strong> in {$yearLabel}: " . $future_salary_array['player'][$z] . "</td>
            <td style=\"text-align: right;\"><strong>{$partner}</strong> in {$yearLabel}: " . $future_salary_arrayb['player'][$z] . "</td>
        </tr>";
        $z++;
    }
    echo "</tbody></table></td></tr>";

    // Cash exchange section
    echo "<tr><td colspan=\"3\"><table class=\"ibl-data-table trading-cash-exchange\" style=\"width: 100%; margin-top: 1rem;\">
        <thead><tr><th colspan=\"2\">Cash Exchange</th></tr></thead>
        <tbody>";

    $currentSeasonEndingYear = $season->endingYear; // This resets the incrementation from the last block.
    $i = 1; // We need to start at 1 because of the "xSendsCash" value names.
    if (
        $season->phase === "Playoffs"
        || $season->phase === "Draft"
        || $season->phase === "Free Agency"
    ) {
        $i++;
    }
    while ($i <= 6) {
        // Because we start $i = 1, the math to derive the years to display increases by 1 too.
        $yearLabel = ($currentSeasonEndingYear - 2 + $i) . "-" . ($currentSeasonEndingYear - 1 + $i);
        echo "<tr>
            <td style=\"text-align: left;\">
                <strong>{$teamlogo}</strong> send
                <input type=\"number\" name=\"userSendsCash$i\" value=\"0\" min=\"0\" max=\"2000\" style=\"width: 80px;\">
                for {$yearLabel}
            </td>
            <td style=\"text-align: right;\">
                <strong>{$partner}</strong> send
                <input type=\"number\" name=\"partnerSendsCash$i\" value=\"0\" min=\"0\" max=\"2000\" style=\"width: 80px;\">
                for {$yearLabel}
            </td>
        </tr>";
        $i++;
    }
    echo "</tbody></table></td></tr>";

    echo "<tr>
            <td colspan=\"3\" style=\"text-align: center; padding: 1rem;\">
                <input type=\"hidden\" name=\"fieldsCounter\" value=\"$k\">
                <button type=\"submit\" class=\"ibl-btn ibl-btn--primary\">Make Trade Offer</button>
            </td>
        </tr>
    </table></form></div>";

    CloseTable();

    Nuke\Footer::footer();
}

function tradereview($username)
{
    global $mysqli_db;
    $commonRepository = new \Services\CommonMysqliRepository($mysqli_db);

    $teamlogo = $commonRepository->getTeamnameFromUsername($username);
    $teamID = $commonRepository->getTidFromTeamname($teamlogo);

    Nuke\Header::header();
    OpenTable();

    echo "<div style=\"text-align: center;\">
        <img src=\"images/logo/$teamID.jpg\" alt=\"Team Logo\" class=\"team-logo-banner\">
        <h2 class=\"ibl-table-title\">Review Trade Offers</h2>
    </div>";

    $sql3 = "SELECT * FROM ibl_trade_info ORDER BY tradeofferid ASC";
    $result3 = $mysqli_db->query($sql3);

    $tradeworkingonnow = 0;
    $tradeOffers = [];

    // Group trade items by offer ID
    while ($row3 = $result3->fetch_assoc()) {
        $offerid = $row3['tradeofferid'];
        $itemid = $row3['itemid'];
        $itemtype = $row3['itemtype'];
        $from = $row3['from'];
        $to = $row3['to'];
        $approval = $row3['approval'];

        $isinvolvedintrade = ($from === $teamlogo || $to === $teamlogo);

        if ($isinvolvedintrade) {
            if (!isset($tradeOffers[$offerid])) {
                $tradeOffers[$offerid] = [
                    'from' => $from,
                    'to' => $to,
                    'approval' => $approval,
                    'oppositeTeam' => ($from === $teamlogo) ? $to : $from,
                    'hashammer' => ($approval === $teamlogo || $approval === 'test'),
                    'items' => []
                ];
            }
            $tradeOffers[$offerid]['items'][] = [
                'itemid' => $itemid,
                'itemtype' => $itemtype,
                'from' => $from,
                'to' => $to
            ];
        }
    }

    echo "<table class=\"trading-layout\" style=\"margin: 0 auto;\">
        <tr>
            <td style=\"vertical-align: top;\">";

    if (empty($tradeOffers)) {
        echo "<p style=\"padding: 1rem;\">No pending trade offers.</p>";
    } else {
        foreach ($tradeOffers as $offerid => $offer) {
            echo "<div class=\"trade-offer-card\" style=\"margin-bottom: 1rem; padding: 1rem; border: 1px solid var(--gray-200); border-radius: var(--radius-md); background: white;\">
                <div style=\"display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;\">
                    <strong>Trade Offer #{$offerid}</strong>
                    <div style=\"display: flex; gap: 0.5rem;\">";

            if ($offer['hashammer']) {
                echo "<form name=\"tradeaccept\" method=\"post\" action=\"/ibl5/modules/Trading/accepttradeoffer.php\" style=\"margin: 0;\">
                    <input type=\"hidden\" name=\"offer\" value=\"$offerid\">
                    <button type=\"submit\" class=\"ibl-btn ibl-btn--success\" onclick=\"this.disabled=true;this.textContent='Submitting...'; this.form.submit();\">Accept</button>
                </form>";
            } else {
                echo "<span style=\"color: var(--gray-500); font-style: italic;\">Awaiting Approval</span>";
            }

            $oppositeTeam = \Utilities\HtmlSanitizer::safeHtmlOutput($offer['oppositeTeam']);
            echo "<form name=\"tradereject\" method=\"post\" action=\"/ibl5/modules/Trading/rejecttradeoffer.php\" style=\"margin: 0;\">
                    <input type=\"hidden\" name=\"offer\" value=\"$offerid\">
                    <input type=\"hidden\" name=\"teamRejecting\" value=\"$teamlogo\">
                    <input type=\"hidden\" name=\"teamReceiving\" value=\"{$oppositeTeam}\">
                    <button type=\"submit\" class=\"ibl-btn ibl-btn--danger\">Reject</button>
                </form>
                    </div>
                </div>
                <div class=\"trade-offer-items\">";

            foreach ($offer['items'] as $item) {
                $itemid = $item['itemid'];
                $itemtype = $item['itemtype'];
                $from = \Utilities\HtmlSanitizer::safeHtmlOutput($item['from']);
                $to = \Utilities\HtmlSanitizer::safeHtmlOutput($item['to']);

                if ($itemtype === 'cash') {
                    $queryCashDetails = "SELECT * FROM ibl_trade_cash WHERE tradeOfferID = ? AND sendingTeam = ?";
                    $stmt = $mysqli_db->prepare($queryCashDetails);
                    $stmt->bind_param("is", $offerid, $item['from']);
                    $stmt->execute();
                    $cashDetails = $stmt->get_result()->fetch_assoc();

                    if ($cashDetails) {
                        $cashAmounts = [];
                        for ($y = 1; $y <= 6; $y++) {
                            if (isset($cashDetails["cy{$y}"]) && $cashDetails["cy{$y}"] > 0) {
                                $cashAmounts[] = $cashDetails["cy{$y}"];
                            }
                        }
                        $cashStr = implode(', ', $cashAmounts);
                        echo "<p>The <strong>{$from}</strong> send {$cashStr} in cash to the <strong>{$to}</strong>.</p>";
                    }
                } elseif ($itemtype == 0) {
                    $sqlgetpick = "SELECT * FROM ibl_draft_picks WHERE pickid = ?";
                    $stmtPick = $mysqli_db->prepare($sqlgetpick);
                    $stmtPick->bind_param("i", $itemid);
                    $stmtPick->execute();
                    $rowsgetpick = $stmtPick->get_result()->fetch_assoc();

                    if ($rowsgetpick) {
                        $pickteam = \Utilities\HtmlSanitizer::safeHtmlOutput($rowsgetpick['teampick']);
                        $pickyear = \Utilities\HtmlSanitizer::safeHtmlOutput($rowsgetpick['year']);
                        $pickround = \Utilities\HtmlSanitizer::safeHtmlOutput($rowsgetpick['round']);
                        $picknotes = $rowsgetpick['notes'];

                        echo "<p>The <strong>{$from}</strong> send the {$pickteam} {$pickyear} Round {$pickround} draft pick to the <strong>{$to}</strong>.</p>";
                        if ($picknotes !== null && $picknotes !== '') {
                            $escapedNotes = \Utilities\HtmlSanitizer::safeHtmlOutput($picknotes);
                            echo "<p style=\"margin-left: 1rem; font-style: italic; color: var(--gray-600);\">{$escapedNotes}</p>";
                        }
                    }
                } elseif ($itemtype == 1) {
                    $sqlgetplyr = "SELECT * FROM ibl_plr WHERE pid = ?";
                    $stmtPlyr = $mysqli_db->prepare($sqlgetplyr);
                    $stmtPlyr->bind_param("i", $itemid);
                    $stmtPlyr->execute();
                    $rowsgetplyr = $stmtPlyr->get_result()->fetch_assoc();

                    if ($rowsgetplyr) {
                        $plyrname = \Utilities\HtmlSanitizer::safeHtmlOutput($rowsgetplyr['name']);
                        $plyrpos = \Utilities\HtmlSanitizer::safeHtmlOutput($rowsgetplyr['pos']);

                        echo "<p>The <strong>{$from}</strong> send {$plyrpos} {$plyrname} to the <strong>{$to}</strong>.</p>";
                    }
                }
            }

            echo "</div></div>";
        }
    }

    echo "</td>
            <td style=\"vertical-align: top;\">";

    $uiHelper = new Trading\UIHelper($mysqli_db);
    $teams = $uiHelper->getAllTeamsForTrading();
    echo $uiHelper->renderTeamSelectionLinks($teams);

    echo "</td>
        </tr>
        <tr>
            <td colspan=\"2\" style=\"text-align: center; padding: 1rem;\">
                <a href=\"modules.php?name=Waivers&action=drop\" class=\"ibl-link\">Drop a player to Waivers</a>
                &nbsp;|&nbsp;
                <a href=\"modules.php?name=Waivers&action=add\" class=\"ibl-link\">Add a player from Waivers</a>
            </td>
        </tr>
    </table>";

    CloseTable();
    Nuke\Footer::footer();
}

function reviewtrade($user)
{
    global $stop, $mysqli_db;
    $season = new Season($mysqli_db);

    if (!is_user($user)) {
        Nuke\Header::header();
        if ($stop) {
            OpenTable();
            echo "<center><font class=\"title\"><b>" . _LOGININCOR . "</b></font></center>\n";
            CloseTable();
        } else {
            OpenTable();
            echo "<center><font class=\"title\"><b>" . _USERREGLOGIN . "</b></font></center>\n";
            CloseTable();
        }
        if (!is_user($user)) {
            OpenTable();
            loginbox();
            CloseTable();
        }
        Nuke\Footer::footer();
    } elseif (is_user($user)) {
        if ($season->allowTrades == 'Yes') {
            global $cookie;
            cookiedecode($user);
            tradereview(strval($cookie[1] ?? ''));
        } else {
            Nuke\Header::header();
            OpenTable();
            echo "Sorry, but trades are not allowed right now.";
            if ($season->allowWaivers == 'Yes') {
                echo "<br>
				Players may still be <a href=\"modules.php?name=Waivers&action=add\">Added From Waivers</a> or they may be <a href=\"modules.php?name=Waivers&action=drop\">Dropped to Waivers</a>.";
            } else {
                echo "<br>The waiver wire is also closed.";
            }
            CloseTable();
            Nuke\Footer::footer();
        }
    }
}

function offertrade($user)
{
    global $mysqli_db, $stop;

    if (!is_user($user)) {
        Nuke\Header::header();
        if ($stop) {
            OpenTable();
            echo "<center><font class=\"title\"><b>" . _LOGININCOR . "</b></font></center>\n";
            CloseTable();
        } else {
            OpenTable();
            echo "<center><font class=\"title\"><b>" . _USERREGLOGIN . "</b></font></center>\n";
            CloseTable();
        }
        if (!is_user($user)) {
            OpenTable();
            loginbox();
            CloseTable();
        }
        Nuke\Footer::footer();
    } elseif (is_user($user)) {
        global $cookie;
        cookiedecode($user);
        tradeoffer(strval($cookie[1] ?? ''));
    }
}

switch ($op) {
    case "reviewtrade":
        reviewtrade($user);
        break;

    case "offertrade":
        offertrade($user);
        break;

    default:
        menu();
        break;
}
