<?php

declare(strict_types=1);

/**
 * Schedule Module - Display league or team game schedule
 *
 * Shows the full league schedule, or a specific team's schedule if teamID is provided.
 * Team schedules include team colors, logo banner, and win/loss tracking.
 *
 * @see TeamSchedule\TeamScheduleService For team-specific business logic
 * @see TeamSchedule\TeamScheduleView For team-specific HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use TeamSchedule\TeamScheduleService;
use TeamSchedule\TeamScheduleView;

global $cookie, $mysqli_db;

$commonRepository = new Services\CommonMysqliRepository($mysqli_db);
$season = new Season($mysqli_db);
$league = new League($mysqli_db);

// Check for team ID parameter
$teamID = isset($_GET['teamID']) ? (int)$_GET['teamID'] : 0;

// Validate team ID exists (if provided)
$isValidTeam = false;
if ($teamID > 0) {
    $team = Team::initialize($mysqli_db, $teamID);
    $isValidTeam = ($team->teamID > 0);
}

Nuke\Header::header();
OpenTable();

if ($isValidTeam) {
    // Team-specific schedule with colors, logo, and win/loss tracking
    $service = new TeamScheduleService($mysqli_db);
    $view = new TeamScheduleView();
    $games = $service->getProcessedSchedule($teamID, $season);
    echo $view->render($team, $games, $league->getSimLengthInDays());
} else {
    // League-wide schedule
    renderLeagueSchedule($mysqli_db, $commonRepository, $season, $league);
}

CloseTable();
Nuke\Footer::footer();

/**
 * Render the league-wide schedule using MySQLi
 */
function renderLeagueSchedule(
    mysqli $mysqli_db,
    Services\CommonMysqliRepository $commonRepository,
    Season $season,
    League $league
): void {
    // Use Season's projectedNextSimEndDate which handles All-Star break correctly
    $projectedNextSimEndDate = $season->projectedNextSimEndDate;
    $simLengthDays = $league->getSimLengthInDays();

    // Get all games using MySQLi
    $query = "SELECT SchedID, Date, Visitor, VScore, Home, HScore, BoxID
              FROM ibl_schedule
              ORDER BY Date ASC, SchedID ASC";
    $result = $mysqli_db->query($query);

    // Get team records using MySQLi
    $teamRecordsQuery = "SELECT tid, leagueRecord FROM ibl_standings ORDER BY tid ASC";
    $teamRecordsResult = $mysqli_db->query($teamRecordsQuery);
    $teamRecords = [];
    while ($row = $teamRecordsResult->fetch_assoc()) {
        $teamRecords[(int)$row['tid']] = $row['leagueRecord'];
    }
    $teamRecordsResult->free();

    // Organize games by month and date
    $gamesByMonth = [];
    $months = [];
    $firstUnplayedId = null;

    while ($row = $result->fetch_assoc()) {
        $date = $row['Date'];
        $visitor = (int)$row['Visitor'];
        $visitorScore = (int)$row['VScore'];
        $home = (int)$row['Home'];
        $homeScore = (int)$row['HScore'];
        $boxid = (int)$row['BoxID'];

        $monthKey = date('Y-m', strtotime($date));
        $monthLabel = date('F', strtotime($date));

        if (!isset($gamesByMonth[$monthKey])) {
            $gamesByMonth[$monthKey] = [];
            $months[$monthKey] = $monthLabel;
        }

        if (!isset($gamesByMonth[$monthKey][$date])) {
            $gamesByMonth[$monthKey][$date] = [];
        }

        $gameDate = date_create($date);
        $isUpcoming = \Utilities\ScheduleHighlighter::shouldHighlight(
            $visitorScore,
            $homeScore,
            $gameDate,
            $projectedNextSimEndDate
        );
        $isUnplayed = \Utilities\ScheduleHighlighter::isGameUnplayed($visitorScore, $homeScore);

        // Track first upcoming game (next sim)
        if ($isUpcoming && $firstUnplayedId === null) {
            $firstUnplayedId = 'game-' . $boxid;
        }

        $gamesByMonth[$monthKey][$date][] = [
            'date' => $date,
            'visitor' => $visitor,
            'visitorScore' => $visitorScore,
            'visitorTeam' => $commonRepository->getTeamnameFromTeamID((int) $visitor),
            'visitorRecord' => $teamRecords[$visitor] ?? '',
            'home' => $home,
            'homeScore' => $homeScore,
            'homeTeam' => $commonRepository->getTeamnameFromTeamID((int) $home),
            'homeRecord' => $teamRecords[$home] ?? '',
            'boxid' => $boxid,
            'isUnplayed' => $isUnplayed,
            'isUpcoming' => $isUpcoming,
            'visitorWon' => ($visitorScore > $homeScore),
            'homeWon' => ($homeScore > $visitorScore),
        ];
    }
    $result->free();

    // Output schedule container
    echo '<div class="schedule-container">';

    // Header with month nav and jump button
    echo '<div class="schedule-header">';
    echo '<div class="schedule-header__left">';
    echo '<h1 class="schedule-title">Schedule</h1>';
    echo '<p class="schedule-highlight-note">Next sim length: ' . \Utilities\HtmlSanitizer::safeHtmlOutput($simLengthDays) . ' days</p>';
    echo '</div>';
    if ($firstUnplayedId) {
        echo '<a href="#' . $firstUnplayedId . '" class="schedule-jump-btn" onclick="scrollToNextGames(event)">';
        echo '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12l7 7 7-7"/></svg>';
        echo 'Next Games';
        echo '</a>';
    }
    echo '</div>';

    // Month navigation
    echo '<nav class="schedule-months">';
    foreach ($months as $key => $label) {
        $abbrev = date('M', strtotime($key . '-01')); // 3-letter abbreviation
        echo '<a href="#month-' . $key . '" class="schedule-months__link" onclick="scrollToMonth(event, \'' . $key . '\')">';
        echo '<span class="schedule-months__full">' . $label . '</span>';
        echo '<span class="schedule-months__abbr">' . $abbrev . '</span>';
        echo '</a>';
    }
    echo '</nav>';

    // Output games by month
    foreach ($gamesByMonth as $monthKey => $dates) {
        $monthLabel = $months[$monthKey];

        echo '<div class="schedule-month" id="month-' . $monthKey . '">';
        echo '<div class="schedule-month__header">' . $monthLabel . '</div>';

        foreach ($dates as $date => $games) {
            $dayNum = date('j', strtotime($date));

            echo '<div class="schedule-day">';
            echo '<div class="schedule-day__header">';
            echo '<span class="schedule-day__num">' . $dayNum . '</span>';
            echo '</div>';

            echo '<div class="schedule-day__games">';
            foreach ($games as $game) {
                $gameClass = 'schedule-game';
                if ($game['isUpcoming']) {
                    $gameClass .= ' schedule-game--upcoming';
                }

                $gameId = 'game-' . $game['boxid'];
                $boxScoreUrl = 'ibl/IBL/box' . \Utilities\HtmlSanitizer::safeHtmlOutput($game['boxid']) . '.htm';
                $visitorTeamUrl = 'modules.php?name=Team&amp;op=team&amp;teamID=' . \Utilities\HtmlSanitizer::safeHtmlOutput($game['visitor']);
                $homeTeamUrl = 'modules.php?name=Team&amp;op=team&amp;teamID=' . \Utilities\HtmlSanitizer::safeHtmlOutput($game['home']);

                echo '<div class="' . $gameClass . '" id="' . $gameId . '">';

                // Visitor team + logo (links to team page)
                $vClass = $game['visitorWon'] ? ' schedule-game__team--win' : '';
                echo '<a href="' . $visitorTeamUrl . '" class="schedule-game__team-link">';
                echo '<span class="schedule-game__team' . $vClass . '">' . \Utilities\HtmlSanitizer::safeHtmlOutput($game['visitorTeam']) . ' <span class="schedule-game__record">(' . \Utilities\HtmlSanitizer::safeHtmlOutput($game['visitorRecord']) . ')</span></span>';
                echo '</a>';
                echo '<a href="' . $visitorTeamUrl . '" class="schedule-game__logo-link"><img class="schedule-game__logo" src="images/logo/new' . \Utilities\HtmlSanitizer::safeHtmlOutput($game['visitor']) . '.png" alt=""></a>';

                // Scores + @ (links to box score)
                echo '<a href="' . $boxScoreUrl . '" class="schedule-game__score-link' . $vClass . '">' . ($game['isUnplayed'] ? '–' : \Utilities\HtmlSanitizer::safeHtmlOutput($game['visitorScore'])) . '</a>';
                echo '<a href="' . $boxScoreUrl . '" class="schedule-game__vs">@</a>';
                $hClass = $game['homeWon'] ? ' schedule-game__team--win' : '';
                echo '<a href="' . $boxScoreUrl . '" class="schedule-game__score-link' . $hClass . '">' . ($game['isUnplayed'] ? '–' : \Utilities\HtmlSanitizer::safeHtmlOutput($game['homeScore'])) . '</a>';

                // Home logo + team (links to team page)
                echo '<a href="' . $homeTeamUrl . '" class="schedule-game__logo-link"><img class="schedule-game__logo" src="images/logo/new' . \Utilities\HtmlSanitizer::safeHtmlOutput($game['home']) . '.png" alt=""></a>';
                echo '<a href="' . $homeTeamUrl . '" class="schedule-game__team-link">';
                echo '<span class="schedule-game__team' . $hClass . '">' . \Utilities\HtmlSanitizer::safeHtmlOutput($game['homeTeam']) . ' <span class="schedule-game__record">(' . \Utilities\HtmlSanitizer::safeHtmlOutput($game['homeRecord']) . ')</span></span>';
                echo '</a>';

                echo '</div>';
            }
            echo '</div>'; // day__games
            echo '</div>'; // day
        }

        echo '</div>'; // month
    }

    echo '</div>'; // schedule-container

    // Scroll scripts
    echo '<script>
var headerOffset = 70; // Offset for sticky header

function scrollToMonth(e, monthKey) {
    e.preventDefault();
    var el = document.getElementById("month-" + monthKey);
    if (el) {
        var rect = el.getBoundingClientRect();
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var targetY = scrollTop + rect.top - headerOffset;
        window.scrollTo({ top: targetY, behavior: "smooth" });
    }
}

function scrollToNextGames(e) {
    e.preventDefault();
    var el = document.getElementById("' . ($firstUnplayedId ?? '') . '");
    if (el) {
        var rect = el.getBoundingClientRect();
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var targetY = scrollTop + rect.top - (window.innerHeight / 2) + (rect.height / 2);
        window.scrollTo({ top: targetY, behavior: "smooth" });
    }
}
</script>';
}
