<?php

use Player\Player;

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Player Archives";

function showpage($playerID, $pageView)
{
    global $db, $cookie;
    $sharedFunctions = new Shared($db);
    $commonRepository = new Services\CommonRepository($db);
    $season = new Season($db);
    
    $player = Player::withPlayerID($db, $playerID);
    $playerStats = PlayerStats::withPlayerID($db, $playerID);
    $pageView = ($pageView !== null) ? intval($pageView) : null;

    // DISPLAY PAGE

    Nuke\Header::header();
    OpenTable();
    UI::playerMenu();

    echo "<table>
        <tr>
            <td valign=top><font class=\"title\">$player->position $player->name ";

    if ($player->nickname != NULL) {
        echo "- Nickname: \"$player->nickname\" ";
    }

    echo "(<a href=\"modules.php?name=Team&op=team&teamID=$player->teamID\">$player->teamName</a>)</font>
        <hr>
        <table>
            <tr>
                <td valign=center><img src=\"images/player/$playerID.jpg\" height=\"90\" width=\"65\"></td>
                <td>";

    // RENEGOTIATION BUTTON START

    $userTeamName = $commonRepository->getTeamnameFromUsername($cookie[1]);
    $userTeam = Team::initialize($db, $userTeamName);

    if ($player->wasRookieOptioned()) {
        echo "<table align=right bgcolor=#ff0000>
                <tr>
                    <td align=center>ROOKIE OPTION<br>USED; RENEGOTIATION<br>IMPOSSIBLE</td>
                </tr>
            </table>";
    } elseif (
        $userTeam->name != "Free Agents"
        AND $userTeam->hasUsedExtensionThisSeason == 0
        AND $player->canRenegotiateContract()
        AND $player->teamName == $userTeam->name
        AND $season->phase != 'Draft'
        AND $season->phase != 'Free Agency'
    ) {
        echo "<table align=right bgcolor=#ff0000>
                <tr>
                    <td align=center><a href=\"modules.php?name=Player&pa=negotiate&pid=$playerID\">RENEGOTIATE<BR>CONTRACT</a></td>
                </tr>
            </table>";
    }

    // RENEGOTIATION BUTTON END

    if (
        $userTeam->name != "Free Agents"
        AND $player->canRookieOption($season->phase)
        AND $player->teamName == $userTeam->name
        ) {
            echo "<table align=right bgcolor=#ffbb00>
                <tr>
                    <td align=center><a href=\"modules.php?name=Player&pa=rookieoption&pid=$playerID\">ROOKIE<BR>OPTION</a></td>
                </tr>
            </table>";
    }

    $contract_display = implode("/", $player->getRemainingContractArray());

    echo "<font class=\"content\">Age: $player->age | Height: $player->heightFeet-$player->heightInches | Weight: $player->weightPounds | College: $player->collegeName<br>
        <i>Drafted by the $player->draftTeamOriginalName with the # $player->draftPickNumber pick of round $player->draftRound in the <a href=\"draft.php?year=$player->draftYear\">$player->draftYear Draft</a></i><br>
        <center><table>
            <tr>
                <td align=center><b>2ga</b></td>
                <td align=center><b>2gp</b></td>
                <td align=center><b>fta</b></td>
                <td align=center><b>ftp</b></td>
                <td align=center><b>3ga</b></td>
                <td align=center><b>3gp</b></td>
                <td align=center><b>orb</b></td>
                <td align=center><b>drb</b></td>
                <td align=center><b>ast</b></td>
                <td align=center><b>stl</b></td>
                <td align=center><b>tvr</b></td>
                <td align=center><b>blk</b></td>
                <td align=center><b>foul</b></td>
                <td align=center><b>oo</b></td>
                <td align=center><b>do</b></td>
                <td align=center><b>po</b></td>
                <td align=center><b>to</b></td>
                <td align=center><b>od</b></td>
                <td align=center><b>dd</b></td>
                <td align=center><b>pd</b></td>
                <td align=center><b>td</b></td>
            </tr>
            <tr>
                <td align=center>$player->ratingFieldGoalAttempts</td>
                <td align=center>$player->ratingFieldGoalPercentage</td>
                <td align=center>$player->ratingFreeThrowAttempts</td>
                <td align=center>$player->ratingFreeThrowPercentage</td>
                <td align=center>$player->ratingThreePointAttempts</td>
                <td align=center>$player->ratingThreePointPercentage</td>
                <td align=center>$player->ratingOffensiveRebounds</td>
                <td align=center>$player->ratingDefensiveRebounds</td>
                <td align=center>$player->ratingAssists</td>
                <td align=center>$player->ratingSteals</td>
                <td align=center>$player->ratingTurnovers</td>
                <td align=center>$player->ratingBlocks</td>
                <td align=center>$player->ratingFouls</td>
                <td align=center>$player->ratingOutsideOffense</td>
                <td align=center>$player->ratingDriveOffense</td>
                <td align=center>$player->ratingPostOffense</td>
                <td align=center>$player->ratingTransitionOffense</td>
                <td align=center>$player->ratingOutsideDefense</td>
                <td align=center>$player->ratingDriveDefense</td>
                <td align=center>$player->ratingPostDefense</td>
                <td align=center>$player->ratingTransitionDefense</td>
            </tr>
        </table></center>
    <b>BIRD YEARS:</b> $player->birdYears | <b>Remaining Contract:</b> $contract_display </td>";

    echo "<td rowspan=3 valign=top>
        <table border=1 cellspacing=0 cellpadding=0>
            <tr bgcolor=#0000cc>
                <td align=center colspan=3><font color=#ffffff><b>PLAYER HIGHS</b></font></td>
            </tr>
            <tr bgcolor=#0000cc>
                <td align=center colspan=3><font color=#ffffff><b>Regular-Season</b></font></td>
            </tr>
            <tr bgcolor=#0000cc>
                <td></td>
                <td><font color=#ffffff>Ssn</font></td>
                <td><font color=#ffffff>Car</td>
            </tr>
            <tr>
                <td><b>Points</b></td>
                <td>$playerStats->seasonHighPoints</td>
                <td>$playerStats->careerSeasonHighPoints</td>
            </tr>
            <tr>
                <td><b>Rebounds</b></td>
                <td>$playerStats->seasonHighRebounds</td>
                <td>$playerStats->careerSeasonHighRebounds</td>
            </tr>
            <tr>
                <td><b>Assists</b></td>
                <td>$playerStats->seasonHighAssists</td>
                <td>$playerStats->careerSeasonHighAssists</td>
            </tr>
            <tr>
                <td><b>Steals</b></td>
                <td>$playerStats->seasonHighSteals</td>
                <td>$playerStats->careerSeasonHighSteals</td>
            </tr>
            <tr>
                <td><b>Blocks</b></td>
                <td>$playerStats->seasonHighBlocks</td>
                <td>$playerStats->careerSeasonHighBlocks</td>
            </tr>
            <tr>
                <td>Double-Doubles</td>
                <td>$playerStats->seasonDoubleDoubles</td>
                <td>$playerStats->careerDoubleDoubles</td>
            </tr>
            <tr>
                <td>Triple-Doubles</td>
                <td>$playerStats->seasonTripleDoubles</td>
                <td>$playerStats->careerTripleDoubles</td>
            </tr>
            <tr bgcolor=#0000cc>
                <td align=center colspan=3><font color=#ffffff><b>Playoffs</b></font></td>
            </tr>
            <tr bgcolor=#0000cc>
                <td></td>
                <td><font color=#ffffff>Ssn</font></td>
                <td><font color=#ffffff>Car</td>
            </tr>
            <tr>
                <td><b>Points</b></td>
                <td>$playerStats->seasonPlayoffHighPoints</td>
                <td>$playerStats->careerPlayoffHighPoints</td>
            </tr>
            <tr>
                <td><b>Rebounds</b></td>
                <td>$playerStats->seasonPlayoffHighRebounds</td>
                <td>$playerStats->careerPlayoffHighRebounds</td>
            </tr>
            <tr>
                <td><b>Assists</b></td>
                <td>$playerStats->seasonPlayoffHighAssists</td>
                <td>$playerStats->careerPlayoffHighAssists</td>
            </tr>
            <tr>
                <td><b>Steals</b></td>
                <td>$playerStats->seasonPlayoffHighSteals</td>
                <td>$playerStats->careerPlayoffHighSteals</td>
            </tr>
            <tr>
                <td><b>Blocks</b></td>
                <td>$playerStats->seasonPlayoffHighBlocks</td>
                <td>$playerStats->careerPlayoffHighBlocks</td>
            </tr>
        </table></td>";

    echo "<tr>
        <td colspan=2><hr></td>
    </tr>
    <tr>
        <td colspan=2><b><center>PLAYER MENU</center></b><br>
            <center>" .
            "<a href=\"" . PlayerPageType::getUrl($playerID, PlayerPageType::OVERVIEW) . "\">" . PlayerPageType::getDescription(PlayerPageType::OVERVIEW) . "</a> | " .
            "<a href=\"" . PlayerPageType::getUrl($playerID, PlayerPageType::AWARDS_AND_NEWS) . "\">" . PlayerPageType::getDescription(PlayerPageType::AWARDS_AND_NEWS) . "</a><br>" .
            "<a href=\"" . PlayerPageType::getUrl($playerID, PlayerPageType::ONE_ON_ONE) . "\">" . PlayerPageType::getDescription(PlayerPageType::ONE_ON_ONE) . "</a> | " .
            "<a href=\"" . PlayerPageType::getUrl($playerID, PlayerPageType::SIM_STATS) . "\">" . PlayerPageType::getDescription(PlayerPageType::SIM_STATS) . "</a><br>" .
            "<a href=\"" . PlayerPageType::getUrl($playerID, PlayerPageType::REGULAR_SEASON_TOTALS) . "\">" . PlayerPageType::getDescription(PlayerPageType::REGULAR_SEASON_TOTALS) . "</a> | " .
            "<a href=\"" . PlayerPageType::getUrl($playerID, PlayerPageType::REGULAR_SEASON_AVERAGES) . "\">" . PlayerPageType::getDescription(PlayerPageType::REGULAR_SEASON_AVERAGES) . "</a><br>" .
            "<a href=\"" . PlayerPageType::getUrl($playerID, PlayerPageType::PLAYOFF_TOTALS) . "\">" . PlayerPageType::getDescription(PlayerPageType::PLAYOFF_TOTALS) . "</a> | " .
            "<a href=\"" . PlayerPageType::getUrl($playerID, PlayerPageType::PLAYOFF_AVERAGES) . "\">" . PlayerPageType::getDescription(PlayerPageType::PLAYOFF_AVERAGES) . "</a><br>" .
            "<a href=\"" . PlayerPageType::getUrl($playerID, PlayerPageType::HEAT_TOTALS) . "\">" . PlayerPageType::getDescription(PlayerPageType::HEAT_TOTALS) . "</a> | " .
            "<a href=\"" . PlayerPageType::getUrl($playerID, PlayerPageType::HEAT_AVERAGES) . "\">" . PlayerPageType::getDescription(PlayerPageType::HEAT_AVERAGES) . "</a><br>" .
            "<a href=\"" . PlayerPageType::getUrl($playerID, PlayerPageType::OLYMPIC_TOTALS) . "\">" . PlayerPageType::getDescription(PlayerPageType::OLYMPIC_TOTALS) . "</a> | " .
            "<a href=\"" . PlayerPageType::getUrl($playerID, PlayerPageType::OLYMPIC_AVERAGES) . "\">" . PlayerPageType::getDescription(PlayerPageType::OLYMPIC_AVERAGES) . "</a><br>" .
            "<a href=\"" . PlayerPageType::getUrl($playerID, PlayerPageType::RATINGS_AND_SALARY) . "\">" . PlayerPageType::getDescription(PlayerPageType::RATINGS_AND_SALARY) . "</a>
            </center>
        </td>
    </tr>
    <tr>
        <td colspan=3><hr></td>
    </tr>";

    if ($pageView == PlayerPageType::OVERVIEW) {
        require_once __DIR__ . '/views/OverviewView.php';
        $view = new OverviewView($db, $player, $playerStats, $season, $sharedFunctions);
        $view->render();
    } elseif ($pageView == PlayerPageType::SIM_STATS) {
        require_once __DIR__ . '/views/SimStatsView.php';
        $view = new SimStatsView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::REGULAR_SEASON_TOTALS) {
        require_once __DIR__ . '/views/RegularSeasonTotalsView.php';
        $view = new RegularSeasonTotalsView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::REGULAR_SEASON_AVERAGES) {
        require_once __DIR__ . '/views/RegularSeasonAveragesView.php';
        $view = new RegularSeasonAveragesView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::PLAYOFF_TOTALS) {
        require_once __DIR__ . '/views/PlayoffTotalsView.php';
        $view = new PlayoffTotalsView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::PLAYOFF_AVERAGES) {
        require_once __DIR__ . '/views/PlayoffAveragesView.php';
        $view = new PlayoffAveragesView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::HEAT_TOTALS) {
        require_once __DIR__ . '/views/HeatTotalsView.php';
        $view = new HeatTotalsView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::HEAT_AVERAGES) {
        require_once __DIR__ . '/views/HeatAveragesView.php';
        $view = new HeatAveragesView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::OLYMPIC_TOTALS) {
        require_once __DIR__ . '/views/OlympicTotalsView.php';
        $view = new OlympicTotalsView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::OLYMPIC_AVERAGES) {
        require_once __DIR__ . '/views/OlympicAveragesView.php';
        $view = new OlympicAveragesView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::RATINGS_AND_SALARY) {
        require_once __DIR__ . '/views/RatingsAndSalaryView.php';
        $view = new RatingsAndSalaryView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::AWARDS_AND_NEWS) {
        require_once __DIR__ . '/views/AwardsAndNewsView.php';
        $view = new AwardsAndNewsView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::ONE_ON_ONE) {
        require_once __DIR__ . '/views/OneOnOneView.php';
        $view = new OneOnOneView($db, $player, $playerStats);
        $view->render();
    }

    echo "</table>";

    CloseTable();
    Nuke\Footer::footer();

    // END OF DISPLAY PAGE
}

function negotiate($playerID)
{
    global $prefix, $db, $cookie;

    $playerID = intval($playerID);
    
    // Get user's team name using existing CommonRepository
    $commonRepository = new Services\CommonRepository($db);
    $userTeamName = $commonRepository->getTeamnameFromUsername($cookie[1]);

    Nuke\Header::header();
    OpenTable();
    UI::playerMenu();

    // Use NegotiationProcessor to handle all business logic
    $processor = new Negotiation\NegotiationProcessor($db);
    echo $processor->processNegotiation($playerID, $userTeamName, $prefix);

    CloseTable();
    Nuke\Footer::footer();
}

function rookieoption($pid)
{
    global $db, $cookie;
    
    // Initialize dependencies
    $commonRepository = new \Services\CommonRepository($db);
    $season = new Season($db);
    $validator = new \RookieOption\RookieOptionValidator();
    $processor = new \RookieOption\RookieOptionProcessor();
    $formView = new \RookieOption\RookieOptionFormView();
    
    // Get user's team name
    $userTeamName = $commonRepository->getTeamnameFromUsername($cookie[1]);
    
    // Load player
    $player = Player::withPlayerID($db, $pid);
    
    // Validate player ownership
    $ownershipValidation = $validator->validatePlayerOwnership($player, $userTeamName);
    if (!$ownershipValidation['valid']) {
        $formView->renderError($ownershipValidation['error']);
        return;
    }
    
    // Validate eligibility and get final year salary
    $eligibilityValidation = $validator->validateEligibilityAndGetSalary($player, $season->phase);
    if (!$eligibilityValidation['valid']) {
        $formView->renderError($eligibilityValidation['error']);
        return;
    }
    
    // Calculate rookie option value
    $rookieOptionValue = $processor->calculateRookieOptionValue($eligibilityValidation['finalYearSalary']);
    
    // Render form
    $formView->renderForm($player, $userTeamName, $rookieOptionValue);
}

switch ($pa) {

    case "negotiate":
        negotiate($pid);
        break;

    case "rookieoption":
        rookieoption($pid);
        break;

    case "showpage":
        showpage($pid, $pageView);
        break;
}
