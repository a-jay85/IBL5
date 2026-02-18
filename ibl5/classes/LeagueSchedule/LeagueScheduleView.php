<?php

declare(strict_types=1);

namespace LeagueSchedule;

use LeagueSchedule\Contracts\LeagueScheduleServiceInterface;
use LeagueSchedule\Contracts\LeagueScheduleViewInterface;
use Utilities\HtmlSanitizer;

/**
 * LeagueScheduleView - HTML rendering for the league-wide schedule
 *
 * @phpstan-import-type LeagueGame from LeagueScheduleServiceInterface
 * @phpstan-import-type MonthData from LeagueScheduleServiceInterface
 * @phpstan-import-type SchedulePageData from LeagueScheduleServiceInterface
 *
 * @see LeagueScheduleViewInterface For the interface contract
 */
class LeagueScheduleView implements LeagueScheduleViewInterface
{
    /**
     * @see LeagueScheduleViewInterface::render()
     *
     * @param SchedulePageData $pageData
     */
    public function render(array $pageData): string
    {
        $gamesByMonth = $pageData['gamesByMonth'];
        $firstUnplayedId = $pageData['firstUnplayedId'];
        $isPlayoffPhase = $pageData['isPlayoffPhase'];
        $playoffMonthKey = $pageData['playoffMonthKey'];
        $simLengthDays = $pageData['simLengthDays'];

        $html = '<div class="schedule-container">';
        $html .= $this->renderHeader($simLengthDays, $firstUnplayedId);
        $html .= $this->renderMonthNav($gamesByMonth, $isPlayoffPhase, $playoffMonthKey);
        $html .= $this->renderGamesByMonth($gamesByMonth, $isPlayoffPhase, $playoffMonthKey);
        $html .= '</div>';
        $html .= $this->renderScrollScripts($firstUnplayedId);

        return $html;
    }

    /**
     * Render header with title, jump button, and sim length note
     */
    private function renderHeader(int $simLengthDays, ?string $firstUnplayedId): string
    {
        $html = '<div class="schedule-header">';
        $html .= '<h1 class="ibl-title">Schedule</h1>';
        $html .= '<div class="schedule-header__center">';

        if ($firstUnplayedId !== null) {
            $html .= '<a href="#' . $firstUnplayedId . '" class="schedule-jump-btn" onclick="scrollToNextGames(event)">';
            $html .= '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12l7 7 7-7"/></svg>';
            $html .= 'Next Games';
            $html .= '</a>';
        }

        /** @var string $safeDays */
        $safeDays = HtmlSanitizer::safeHtmlOutput($simLengthDays);
        $html .= '<p class="schedule-highlight-note">Next sim length: ' . $safeDays . ' days</p>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="sos-legend">';
        $html .= '<span class="sos-legend__item"><span class="sos-tier-dot sos-tier--elite"></span> Elite</span>';
        $html .= '<span class="sos-legend__item"><span class="sos-tier-dot sos-tier--strong"></span> Strong</span>';
        $html .= '<span class="sos-legend__item"><span class="sos-tier-dot sos-tier--average"></span> Average</span>';
        $html .= '<span class="sos-legend__item"><span class="sos-tier-dot sos-tier--weak"></span> Weak</span>';
        $html .= '<span class="sos-legend__item"><span class="sos-tier-dot sos-tier--bottom"></span> Bottom</span>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render month navigation links
     *
     * @param array<string, MonthData> $gamesByMonth
     */
    private function renderMonthNav(array $gamesByMonth, bool $isPlayoffPhase, ?string $playoffMonthKey): string
    {
        $html = '<nav class="ibl-jump-menu schedule-months">';
        foreach ($gamesByMonth as $key => $data) {
            if ($isPlayoffPhase && $key === $playoffMonthKey) {
                continue;
            }
            $monthTimestamp = strtotime($key . '-01');
            $abbrev = ($monthTimestamp !== false) ? date('M', $monthTimestamp) : '';
            $html .= '<a href="#month-' . $key . '" class="ibl-jump-menu__link schedule-months__link" onclick="scrollToMonth(event, \'' . $key . '\')">';
            $html .= '<span class="schedule-months__full">' . $data['label'] . '</span>';
            $html .= '<span class="schedule-months__abbr">' . $abbrev . '</span>';
            $html .= '</a>';
        }
        $html .= '</nav>';

        return $html;
    }

    /**
     * Render all games organized by month
     *
     * @param array<string, MonthData> $gamesByMonth
     */
    private function renderGamesByMonth(array $gamesByMonth, bool $isPlayoffPhase, ?string $playoffMonthKey): string
    {
        $html = '';
        foreach ($gamesByMonth as $monthKey => $data) {
            $monthLabel = $data['label'];

            $html .= '<div class="schedule-month" id="month-' . $monthKey . '">';
            $headerClass = 'schedule-month__header';
            if ($isPlayoffPhase && $monthKey === $playoffMonthKey) {
                $headerClass .= ' schedule-month__header--playoffs';
            }
            $html .= '<div class="' . $headerClass . '">' . $monthLabel . '</div>';

            foreach ($data['dates'] as $date => $games) {
                $dateTimestamp = strtotime($date);
                $dayNum = $dateTimestamp !== false ? date('j', $dateTimestamp) : '';

                $html .= '<div class="schedule-day">';
                $html .= '<div class="schedule-day__header">';
                $html .= '<span class="schedule-day__num">' . $dayNum . '</span>';
                $html .= '</div>';

                $html .= '<div class="schedule-day__games">';
                foreach ($games as $game) {
                    $html .= $this->renderGameRow($game);
                }
                $html .= '</div>';
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Render a single game row
     *
     * @param LeagueGame $game
     */
    private function renderGameRow(array $game): string
    {
        $gameClass = 'schedule-game';
        if ($game['isUpcoming']) {
            $gameClass .= ' schedule-game--upcoming';
        }

        $gameId = 'game-' . $game['boxid'];
        /** @var string $boxScoreUrl */
        $boxScoreUrl = HtmlSanitizer::safeHtmlOutput($game['boxScoreUrl']);
        $visitorTeamUrl = 'modules.php?name=Team&amp;op=team&amp;teamID=' . $game['visitor'];
        $homeTeamUrl = 'modules.php?name=Team&amp;op=team&amp;teamID=' . $game['home'];

        $html = '<div class="' . $gameClass . '" id="' . $gameId . '" data-home-team-id="' . $game['home'] . '" data-visitor-team-id="' . $game['visitor'] . '">';

        // Far left: visitor SOS tier indicator
        $visitorTier = $game['visitorTier'] ?? '';
        $html .= '<span class="schedule-game__sos-indicator">';
        if ($game['isUnplayed'] && $visitorTier !== '') {
            $html .= '<span class="sos-tier-dot--sm sos-tier--' . $visitorTier . '" title="' . $visitorTier . '"></span>';
        }
        $html .= '</span>';

        // Visitor team + logo
        $vClass = $game['visitorWon'] ? ' schedule-game__team--win' : '';
        /** @var string $safeVisitorTeam */
        $safeVisitorTeam = HtmlSanitizer::safeHtmlOutput($game['visitorTeam']);
        /** @var string $safeVisitorRecord */
        $safeVisitorRecord = HtmlSanitizer::safeHtmlOutput($game['visitorRecord']);

        $html .= '<a href="' . $visitorTeamUrl . '" class="schedule-game__team-link">';
        $html .= '<span class="schedule-game__team' . $vClass . '"><span class="schedule-game__team-text">' . $safeVisitorTeam . '</span> <span class="schedule-game__record">(' . $safeVisitorRecord . ')</span></span>';
        $html .= '</a>';
        $html .= '<a href="' . $visitorTeamUrl . '" class="schedule-game__logo-link"><img class="schedule-game__logo" src="images/logo/new' . $game['visitor'] . '.png" alt="" width="25" height="25" loading="lazy"></a>';

        // Scores + @
        $hClass = $game['homeWon'] ? ' schedule-game__team--win' : '';
        if ($boxScoreUrl !== '') {
            $html .= '<a href="' . $boxScoreUrl . '" class="schedule-game__score-link' . $vClass . '">' . ($game['isUnplayed'] ? '–' : (string)$game['visitorScore']) . '</a>';
            $html .= '<a href="' . $boxScoreUrl . '" class="schedule-game__vs">@</a>';
            $html .= '<a href="' . $boxScoreUrl . '" class="schedule-game__score-link' . $hClass . '">' . ($game['isUnplayed'] ? '–' : (string)$game['homeScore']) . '</a>';
        } else {
            $html .= '<span class="schedule-game__score-link' . $vClass . '">' . ($game['isUnplayed'] ? '–' : (string)$game['visitorScore']) . '</span>';
            $html .= '<span class="schedule-game__vs">@</span>';
            $html .= '<span class="schedule-game__score-link' . $hClass . '">' . ($game['isUnplayed'] ? '–' : (string)$game['homeScore']) . '</span>';
        }

        // Home logo + team
        /** @var string $safeHomeTeam */
        $safeHomeTeam = HtmlSanitizer::safeHtmlOutput($game['homeTeam']);
        /** @var string $safeHomeRecord */
        $safeHomeRecord = HtmlSanitizer::safeHtmlOutput($game['homeRecord']);

        $html .= '<a href="' . $homeTeamUrl . '" class="schedule-game__logo-link"><img class="schedule-game__logo" src="images/logo/new' . $game['home'] . '.png" alt="" width="25" height="25" loading="lazy"></a>';
        $html .= '<a href="' . $homeTeamUrl . '" class="schedule-game__team-link">';
        $html .= '<span class="schedule-game__team' . $hClass . '"><span class="schedule-game__team-text">' . $safeHomeTeam . '</span> <span class="schedule-game__record">(' . $safeHomeRecord . ')</span></span>';
        $html .= '</a>';

        // Far right: home SOS tier indicator
        $homeTier = $game['homeTier'] ?? '';
        $html .= '<span class="schedule-game__sos-indicator">';
        if ($game['isUnplayed'] && $homeTier !== '') {
            $html .= '<span class="sos-tier-dot--sm sos-tier--' . $homeTier . '" title="' . $homeTier . '"></span>';
        }
        $html .= '</span>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Render JavaScript for smooth scrolling
     */
    private function renderScrollScripts(?string $firstUnplayedId): string
    {
        return '<script>
var headerOffset = 70;

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
}
