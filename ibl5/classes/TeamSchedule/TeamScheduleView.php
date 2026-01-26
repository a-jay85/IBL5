<?php

declare(strict_types=1);

namespace TeamSchedule;

use TeamSchedule\Contracts\TeamScheduleViewInterface;
use Utilities\HtmlSanitizer;

/**
 * TeamScheduleView - HTML rendering for team schedule
 *
 * Generates HTML displaying a team's game schedule using the shared schedule
 * layout with team-specific color theming.
 *
 * @see TeamScheduleViewInterface For the interface contract
 */
class TeamScheduleView implements TeamScheduleViewInterface
{
    /**
     * @see TeamScheduleViewInterface::render()
     */
    public function render(\Team $team, array $games, int $simLengthInDays): string
    {
        $color1 = HtmlSanitizer::safeHtmlOutput($team->color1);
        $color2 = HtmlSanitizer::safeHtmlOutput($team->color2);
        $teamId = (int)$team->teamID;
        $teamName = HtmlSanitizer::safeHtmlOutput($team->name);

        // Organize games by month and date
        $gamesByMonth = $this->organizeGamesByMonth($games);
        $firstUpcomingId = $this->findFirstUpcomingGameId($games);

        $html = $this->renderTeamColorStyles($color1, $color2);
        $html .= '<div class="schedule-container schedule-container--team">';
        $html .= $this->renderTeamBanner($teamId, $teamName, $color1);
        $html .= $this->renderHeader($simLengthInDays, $firstUpcomingId);
        $html .= $this->renderMonthNav($gamesByMonth);
        $html .= $this->renderGamesByMonth($gamesByMonth, $games, $teamId, $team->name);
        $html .= '</div>';
        $html .= $this->renderScrollScripts($firstUpcomingId);

        return $html;
    }

    /**
     * Render CSS custom properties for team colors
     */
    private function renderTeamColorStyles(string $color1, string $color2): string
    {
        return '<style>
            .schedule-container--team {
                --team-primary: #' . $color1 . ';
                --team-secondary: #' . $color2 . ';
            }
            .schedule-container--team .schedule-month__header {
                background: var(--team-primary);
                color: var(--team-secondary);
            }
            .schedule-container--team .schedule-months__link:hover {
                background: var(--team-primary);
                color: var(--team-secondary);
            }
            .schedule-container--team .schedule-jump-btn {
                background: var(--team-primary) !important;
                color: var(--team-secondary) !important;
                box-shadow: 0 2px 8px color-mix(in srgb, var(--team-primary) 40%, transparent);
            }
            .schedule-container--team .schedule-jump-btn:hover {
                background: var(--team-primary) !important;
                opacity: 0.9;
                box-shadow: 0 4px 12px color-mix(in srgb, var(--team-primary) 50%, transparent);
            }
        </style>';
    }

    /**
     * Render responsive team banner with logo
     */
    private function renderTeamBanner(int $teamId, string $teamName, string $color1): string
    {
        return '<div class="schedule-team-banner" style="background: linear-gradient(135deg, #' . $color1 . ', #' . $color1 . 'cc);">
            <img class="schedule-team-banner__logo" src="./images/logo/' . $teamId . '.jpg" alt="' . $teamName . '">
        </div>';
    }

    /**
     * Render header with title and jump button
     */
    private function renderHeader(int $simLengthInDays, ?string $firstUpcomingId): string
    {
        $html = '<div class="schedule-header">';
        $html .= '<div class="schedule-header__left">';
        $html .= '<h1 class="schedule-title">Schedule</h1>';
        $html .= '<p class="schedule-highlight-note">Next sim: ' . HtmlSanitizer::safeHtmlOutput($simLengthInDays) . ' days</p>';
        $html .= '</div>';

        if ($firstUpcomingId) {
            $html .= '<a href="#' . $firstUpcomingId . '" class="schedule-jump-btn" onclick="scrollToNextGames(event)">';
            $html .= '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12l7 7 7-7"/></svg>';
            $html .= 'Next Games';
            $html .= '</a>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render month navigation
     */
    private function renderMonthNav(array $gamesByMonth): string
    {
        $html = '<nav class="schedule-months">';
        foreach ($gamesByMonth as $monthKey => $data) {
            $monthLabel = $data['label'];
            $abbrev = date('M', strtotime($monthKey . '-01'));
            $html .= '<a href="#team-month-' . $monthKey . '" class="schedule-months__link" onclick="scrollToMonth(event, \'' . $monthKey . '\')">';
            $html .= '<span class="schedule-months__full">' . HtmlSanitizer::safeHtmlOutput($monthLabel) . '</span>';
            $html .= '<span class="schedule-months__abbr">' . HtmlSanitizer::safeHtmlOutput($abbrev) . '</span>';
            $html .= '</a>';
        }
        $html .= '</nav>';
        return $html;
    }

    /**
     * Render all games organized by month using same layout as Schedule module
     */
    private function renderGamesByMonth(array $gamesByMonth, array $allGames, int $userTeamId, string $userTeamName): string
    {
        $html = '';
        foreach ($gamesByMonth as $monthKey => $data) {
            $html .= '<div class="schedule-month" id="team-month-' . $monthKey . '">';
            $html .= '<div class="schedule-month__header">' . HtmlSanitizer::safeHtmlOutput($data['label']) . '</div>';

            foreach ($data['dates'] as $date => $games) {
                $dayNum = date('j', strtotime($date));

                $html .= '<div class="schedule-day">';
                $html .= '<div class="schedule-day__header">';
                $html .= '<span class="schedule-day__num">' . $dayNum . '</span>';
                $html .= '</div>';

                $html .= '<div class="schedule-day__games">';
                foreach ($games as $row) {
                    $html .= $this->renderGameRow($row, $userTeamId, $userTeamName);
                }
                $html .= '</div>';
                $html .= '</div>';
            }

            $html .= '</div>';
        }
        return $html;
    }

    /**
     * Render a single game row matching Schedule module format exactly
     * Same layout as League Schedule, plus streak column on the right
     */
    private function renderGameRow(array $row, int $userTeamId, string $userTeamName): string
    {
        /** @var \Game $game */
        $game = $row['game'];
        /** @var \Team $opposingTeam */
        $opposingTeam = $row['opposingTeam'];

        $isUpcoming = ($row['highlight'] === 'next-sim');
        $gameClass = 'schedule-game';
        if ($isUpcoming) {
            $gameClass .= ' schedule-game--upcoming';
        }

        $gameId = 'team-game-' . (int)$game->boxScoreID;
        $visitorTeamId = (int)$game->visitorTeamID;
        $homeTeamId = (int)$game->homeTeamID;
        $boxScoreUrl = './ibl/IBL/box' . (int)$game->boxScoreID . '.htm';

        // Determine which team is visitor/home
        $isUserHome = ($homeTeamId === $userTeamId);

        // Get team info for both sides - use actual team names with records
        $userRecord = $row['isUnplayed'] ? '' : $row['wins'] . '-' . $row['losses'];
        if ($isUserHome) {
            $visitorName = $opposingTeam->name;
            $visitorRecord = $opposingTeam->seasonRecord;
            $homeName = $userTeamName;
            $homeRecord = $userRecord;
        } else {
            $visitorName = $userTeamName;
            $visitorRecord = $userRecord;
            $homeName = $opposingTeam->name;
            $homeRecord = $opposingTeam->seasonRecord;
        }

        $visitorUrl = 'modules.php?name=Team&amp;op=team&amp;teamID=' . $visitorTeamId;
        $homeUrl = 'modules.php?name=Team&amp;op=team&amp;teamID=' . $homeTeamId;

        $html = '<div class="' . $gameClass . '" id="' . $gameId . '">';

        // Determine win classes
        $userWon = ($row['winLossColor'] === 'green');
        if ($row['isUnplayed']) {
            $vWinClass = '';
            $hWinClass = '';
        } elseif ($isUserHome) {
            $vWinClass = $userWon ? '' : ' schedule-game__team--win';
            $hWinClass = $userWon ? ' schedule-game__team--win' : '';
        } else {
            $vWinClass = $userWon ? ' schedule-game__team--win' : '';
            $hWinClass = $userWon ? '' : ' schedule-game__team--win';
        }

        // Visitor team + logo (same as League Schedule)
        $html .= '<a href="' . $visitorUrl . '" class="schedule-game__team-link">';
        $html .= '<span class="schedule-game__team' . $vWinClass . '">' . HtmlSanitizer::safeHtmlOutput($visitorName);
        if ($visitorRecord) {
            $html .= ' <span class="schedule-game__record">(' . HtmlSanitizer::safeHtmlOutput($visitorRecord) . ')</span>';
        }
        $html .= '</span></a>';
        $html .= '<a href="' . $visitorUrl . '" class="schedule-game__logo-link">';
        $html .= '<img class="schedule-game__logo" src="images/logo/new' . $visitorTeamId . '.png" alt="">';
        $html .= '</a>';

        // Scores + @ (same as League Schedule)
        if ($row['isUnplayed']) {
            $html .= '<a href="' . $boxScoreUrl . '" class="schedule-game__score-link">–</a>';
            $html .= '<a href="' . $boxScoreUrl . '" class="schedule-game__vs">@</a>';
            $html .= '<a href="' . $boxScoreUrl . '" class="schedule-game__score-link">–</a>';
        } else {
            $html .= '<a href="' . $boxScoreUrl . '" class="schedule-game__score-link' . $vWinClass . '">' . HtmlSanitizer::safeHtmlOutput($game->visitorScore) . '</a>';
            $html .= '<a href="' . $boxScoreUrl . '" class="schedule-game__vs">@</a>';
            $html .= '<a href="' . $boxScoreUrl . '" class="schedule-game__score-link' . $hWinClass . '">' . HtmlSanitizer::safeHtmlOutput($game->homeScore) . '</a>';
        }

        // Home logo + team (same as League Schedule)
        $html .= '<a href="' . $homeUrl . '" class="schedule-game__logo-link">';
        $html .= '<img class="schedule-game__logo" src="images/logo/new' . $homeTeamId . '.png" alt="">';
        $html .= '</a>';
        $html .= '<a href="' . $homeUrl . '" class="schedule-game__team-link">';
        $html .= '<span class="schedule-game__team' . $hWinClass . '">' . HtmlSanitizer::safeHtmlOutput($homeName);
        if ($homeRecord) {
            $html .= ' <span class="schedule-game__record">(' . HtmlSanitizer::safeHtmlOutput($homeRecord) . ')</span>';
        }
        $html .= '</span></a>';

        // Streak column on right (single line)
        $html .= $this->renderStreakColumn($row);

        $html .= '</div>';
        return $html;
    }

    /**
     * Render the streak column on the right side of the game row (single line)
     */
    private function renderStreakColumn(array $row): string
    {
        if ($row['isUnplayed']) {
            return '<span class="schedule-game__streak"></span>';
        }

        $isWin = ($row['winLossColor'] === 'green');
        $resultClass = $isWin ? 'schedule-game__streak--win' : 'schedule-game__streak--loss';
        $resultLetter = $isWin ? 'W' : 'L';
        $streakNum = trim(substr($row['streak'], 2)); // Extract number from "W 3" or "L 2"

        return '<span class="schedule-game__streak ' . $resultClass . '">'
            . $resultLetter . $streakNum
            . '</span>';
    }

    /**
     * Organize games by month for easier rendering
     */
    private function organizeGamesByMonth(array $games): array
    {
        $byMonth = [];

        foreach ($games as $row) {
            /** @var \Game $game */
            $game = $row['game'];
            $date = $game->date;
            $monthKey = date('Y-m', strtotime($date));
            $monthLabel = $row['currentMonth'];

            if (!isset($byMonth[$monthKey])) {
                $byMonth[$monthKey] = [
                    'label' => $monthLabel,
                    'dates' => [],
                ];
            }

            if (!isset($byMonth[$monthKey]['dates'][$date])) {
                $byMonth[$monthKey]['dates'][$date] = [];
            }

            $byMonth[$monthKey]['dates'][$date][] = $row;
        }

        return $byMonth;
    }

    /**
     * Find the first upcoming game ID for the jump button
     */
    private function findFirstUpcomingGameId(array $games): ?string
    {
        foreach ($games as $row) {
            if ($row['highlight'] === 'next-sim') {
                /** @var \Game $game */
                $game = $row['game'];
                return 'team-game-' . (int)$game->boxScoreID;
            }
        }
        return null;
    }

    /**
     * Render JavaScript for smooth scrolling
     */
    private function renderScrollScripts(?string $firstUpcomingId): string
    {
        return '<script>
var headerOffset = 70;

function scrollToMonth(e, monthKey) {
    e.preventDefault();
    var el = document.getElementById("team-month-" + monthKey);
    if (el) {
        var rect = el.getBoundingClientRect();
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var targetY = scrollTop + rect.top - headerOffset;
        window.scrollTo({ top: targetY, behavior: "smooth" });
    }
}

function scrollToNextGames(e) {
    e.preventDefault();
    var el = document.getElementById("' . ($firstUpcomingId ?? '') . '");
    if (el) {
        var rect = el.getBoundingClientRect();
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var targetY = scrollTop + rect.top - (window.innerHeight / 2) + (rect.height / 2);
        window.scrollTo({ top: targetY, behavior: "smooth" });
    }
}
</script>';
    }
}
