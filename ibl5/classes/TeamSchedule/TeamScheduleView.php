<?php

declare(strict_types=1);

namespace TeamSchedule;

use TeamSchedule\Contracts\TeamScheduleViewInterface;
use Utilities\HtmlSanitizer;

/**
 * TeamScheduleView - HTML rendering for team schedule
 *
 * Generates modern card-based HTML displaying a team's game schedule with results.
 * Uses the design system with team-specific color theming.
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

        // Organize games by month
        $gamesByMonth = $this->organizeGamesByMonth($games);
        $firstUpcomingId = $this->findFirstUpcomingGameId($games);

        $html = $this->renderTeamColorStyles($color1, $color2);
        $html .= '<div class="team-schedule-container">';
        $html .= $this->renderTeamBanner($teamId, $teamName, $color1, $color2);
        $html .= $this->renderHeader($teamName, $simLengthInDays, $firstUpcomingId);
        $html .= $this->renderMonthNav($gamesByMonth);
        $html .= $this->renderGamesByMonth($gamesByMonth, $teamId);
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
            .team-schedule-container {
                --team-primary: #' . $color1 . ';
                --team-secondary: #' . $color2 . ';
            }
        </style>';
    }

    /**
     * Render responsive team banner with logo
     */
    private function renderTeamBanner(int $teamId, string $teamName, string $color1, string $color2): string
    {
        return '<div class="team-schedule-banner" style="background: linear-gradient(135deg, #' . $color1 . ', #' . $color1 . 'dd);">
            <img class="team-schedule-banner__logo" src="./images/logo/' . $teamId . '.jpg" alt="' . $teamName . ' Logo">
        </div>';
    }

    /**
     * Render header with title and jump button
     */
    private function renderHeader(string $teamName, int $simLengthInDays, ?string $firstUpcomingId): string
    {
        $html = '<div class="team-schedule-header">';
        $html .= '<div class="team-schedule-header__left">';
        $html .= '<h1 class="team-schedule-title">' . $teamName . ' Schedule</h1>';
        $html .= '<p class="team-schedule-note">Next sim length: ' . HtmlSanitizer::safeHtmlOutput($simLengthInDays) . ' days</p>';
        $html .= '</div>';

        if ($firstUpcomingId) {
            $html .= '<a href="#' . $firstUpcomingId . '" class="team-schedule-jump-btn" onclick="scrollToNextGames(event)">';
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
        $html = '<nav class="team-schedule-months">';
        foreach ($gamesByMonth as $monthKey => $data) {
            $monthLabel = $data['label'];
            $abbrev = date('M', strtotime($monthKey . '-01'));
            $html .= '<a href="#team-month-' . $monthKey . '" class="team-schedule-months__link" onclick="scrollToMonth(event, \'' . $monthKey . '\')">';
            $html .= '<span class="team-schedule-months__full">' . HtmlSanitizer::safeHtmlOutput($monthLabel) . '</span>';
            $html .= '<span class="team-schedule-months__abbr">' . HtmlSanitizer::safeHtmlOutput($abbrev) . '</span>';
            $html .= '</a>';
        }
        $html .= '</nav>';
        return $html;
    }

    /**
     * Render all games organized by month
     */
    private function renderGamesByMonth(array $gamesByMonth, int $userTeamId): string
    {
        $html = '';
        foreach ($gamesByMonth as $monthKey => $data) {
            $html .= '<div class="team-schedule-month" id="team-month-' . $monthKey . '">';
            $html .= '<div class="team-schedule-month__header">' . HtmlSanitizer::safeHtmlOutput($data['label']) . '</div>';

            foreach ($data['dates'] as $date => $games) {
                $dayName = date('D', strtotime($date));
                $dayNum = date('j', strtotime($date));

                $html .= '<div class="team-schedule-day">';
                $html .= '<div class="team-schedule-day__header">';
                $html .= '<span class="team-schedule-day__name">' . $dayName . '</span>';
                $html .= '<span class="team-schedule-day__num">' . $dayNum . '</span>';
                $html .= '</div>';

                $html .= '<div class="team-schedule-day__games">';
                foreach ($games as $row) {
                    $html .= $this->renderGameCard($row, $userTeamId);
                }
                $html .= '</div>';
                $html .= '</div>';
            }

            $html .= '</div>';
        }
        return $html;
    }

    /**
     * Render a single game card
     */
    private function renderGameCard(array $row, int $userTeamId): string
    {
        /** @var \Game $game */
        $game = $row['game'];
        /** @var \Team $opposingTeam */
        $opposingTeam = $row['opposingTeam'];

        $isUpcoming = ($row['highlight'] === 'next-sim');
        $gameClass = 'team-schedule-game';
        if ($isUpcoming) {
            $gameClass .= ' team-schedule-game--upcoming';
        }

        $gameId = 'team-game-' . (int)$game->boxScoreID;
        $opponentTeamId = (int)$opposingTeam->teamID;
        $boxScoreUrl = './ibl/IBL/box' . (int)$game->boxScoreID . '.htm';
        $opponentUrl = 'modules.php?name=Team&amp;op=team&amp;teamID=' . $opponentTeamId;

        // Determine if home or away
        $isHome = ($game->homeTeamID === $userTeamId);
        $locationPrefix = $isHome ? 'vs' : '@';

        $html = '<div class="' . $gameClass . '" id="' . $gameId . '">';

        // Opponent logo
        $html .= '<a href="' . $opponentUrl . '" class="team-schedule-game__logo-link">';
        $html .= '<img class="team-schedule-game__logo" src="images/logo/new' . $opponentTeamId . '.png" alt="">';
        $html .= '</a>';

        // Opponent info
        $html .= '<div class="team-schedule-game__info">';
        $html .= '<a href="' . $opponentUrl . '" class="team-schedule-game__opponent">';
        $html .= '<span class="team-schedule-game__location">' . $locationPrefix . '</span> ';
        $html .= HtmlSanitizer::safeHtmlOutput($opposingTeam->name);
        $html .= '</a>';
        $html .= '<span class="team-schedule-game__record">(' . HtmlSanitizer::safeHtmlOutput($opposingTeam->seasonRecord) . ')</span>';
        $html .= '</div>';

        // Result or upcoming indicator
        if ($row['isUnplayed']) {
            $html .= '<div class="team-schedule-game__result team-schedule-game__result--upcoming">';
            $html .= '<span class="team-schedule-game__time">TBD</span>';
            $html .= '</div>';
        } else {
            $isWin = ($row['winLossColor'] === 'green');
            $resultClass = $isWin ? 'team-schedule-game__result--win' : 'team-schedule-game__result--loss';
            $resultLetter = $isWin ? 'W' : 'L';

            $html .= '<a href="' . $boxScoreUrl . '" class="team-schedule-game__result ' . $resultClass . '">';
            $html .= '<span class="team-schedule-game__outcome">' . $resultLetter . '</span>';
            $html .= '<span class="team-schedule-game__score">' . HtmlSanitizer::safeHtmlOutput($game->visitorScore . '-' . $game->homeScore) . '</span>';
            $html .= '</a>';

            // Record and streak
            $html .= '<div class="team-schedule-game__stats">';
            $html .= '<span class="team-schedule-game__wl">' . HtmlSanitizer::safeHtmlOutput($row['wins'] . '-' . $row['losses']) . '</span>';
            $html .= '<span class="team-schedule-game__streak">' . HtmlSanitizer::safeHtmlOutput($row['streak']) . '</span>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
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
