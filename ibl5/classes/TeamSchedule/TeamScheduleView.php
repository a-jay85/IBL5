<?php

declare(strict_types=1);

namespace TeamSchedule;

use TeamSchedule\Contracts\TeamScheduleViewInterface;
use TeamSchedule\Contracts\TeamScheduleServiceInterface;
use Utilities\HtmlSanitizer;

/**
 * TeamScheduleView - HTML rendering for team schedule
 *
 * Generates HTML displaying a team's game schedule using the shared schedule
 * layout with team-specific color theming.
 *
 * @phpstan-import-type ScheduleGameRow from TeamScheduleServiceInterface
 *
 * @phpstan-type MonthData array{label: string, dates: array<string, list<ScheduleGameRow>>}
 *
 * @see TeamScheduleViewInterface For the interface contract
 */
class TeamScheduleView implements TeamScheduleViewInterface
{
    /** @var array{remaining_sos: float|string, remaining_sos_rank: int}|null */
    private ?array $sosSummary = null;

    /**
     * Set remaining SOS summary data for display
     *
     * @param array{remaining_sos: float|string, remaining_sos_rank: int} $sosSummary
     */
    public function setSosSummary(array $sosSummary): void
    {
        $this->sosSummary = $sosSummary;
    }
    /**
     * @see TeamScheduleViewInterface::render()
     *
     * @param list<ScheduleGameRow> $games
     */
    public function render(\Team $team, array $games, int $simLengthInDays, string $seasonPhase): string
    {
        /** @var string $color1 */
        $color1 = HtmlSanitizer::safeHtmlOutput($team->color1);
        /** @var string $color2 */
        $color2 = HtmlSanitizer::safeHtmlOutput($team->color2);
        $teamId = $team->teamID;
        /** @var string $teamName */
        $teamName = HtmlSanitizer::safeHtmlOutput($team->name);

        // Organize games by month and date
        $gamesByMonth = $this->organizeGamesByMonth($games);
        $firstUpcomingId = $this->findFirstUpcomingGameId($games);

        // In playoff phases, relabel June as "Playoffs" and move to front
        $isPlayoffPhase = in_array($seasonPhase, ['Playoffs', 'Draft', 'Free Agency'], true);
        $playoffMonthKey = null;
        if ($isPlayoffPhase) {
            foreach (array_keys($gamesByMonth) as $key) {
                $monthTimestamp = strtotime($key . '-01');
                if ($monthTimestamp !== false && (int)date('n', $monthTimestamp) === \Season::IBL_PLAYOFF_MONTH) {
                    $playoffMonthKey = $key;
                    break;
                }
            }
            if ($playoffMonthKey !== null && isset($gamesByMonth[$playoffMonthKey])) {
                $gamesByMonth[$playoffMonthKey]['label'] = 'Playoffs';
                $reordered = [$playoffMonthKey => $gamesByMonth[$playoffMonthKey]];
                unset($gamesByMonth[$playoffMonthKey]);
                $gamesByMonth = $reordered + $gamesByMonth;
            }
        }

        $html = $this->renderTeamColorStyles($color1, $color2);
        $html .= '<div class="schedule-container schedule-container--team">';
        $html .= $this->renderTeamBanner($teamId, $teamName);
        $html .= $this->renderHeader($simLengthInDays, $firstUpcomingId);
        $html .= $this->renderMonthNav($gamesByMonth, $isPlayoffPhase, $playoffMonthKey);
        $html .= $this->renderGamesByMonth($gamesByMonth, $games, $teamId, $team->name, $isPlayoffPhase, $playoffMonthKey);
        $html .= '</div>';
        $html .= $this->renderScrollScripts($firstUpcomingId);

        return $html;
    }

    /**
     * Render CSS custom properties for team colors
     *
     * Sets --team-primary and --team-secondary on the container element.
     * The corresponding style rules are in design/components/existing-components.css.
     */
    private function renderTeamColorStyles(string $color1, string $color2): string
    {
        return '<style>.schedule-container--team{--team-primary:#' . $color1 . ';--team-secondary:#' . $color2 . ';}</style>';
    }

    /**
     * Render responsive team banner with logo
     */
    private function renderTeamBanner(int $teamId, string $teamName): string
    {
        return '<div class="schedule-team-banner">'
            . '<img class="schedule-team-banner__logo" src="./images/logo/' . $teamId . '.jpg" alt="' . $teamName . '" width="415" height="50">'
            . '</div>';
    }

    /**
     * Render header with title and jump button
     */
    private function renderHeader(int $simLengthInDays, ?string $firstUpcomingId): string
    {
        $html = '<div class="schedule-header">';
        $html .= '<h1 class="ibl-title">Schedule</h1>';
        $html .= '<div class="schedule-header__center">';

        if ($firstUpcomingId !== null && $firstUpcomingId !== '') {
            $html .= '<a href="#' . $firstUpcomingId . '" class="schedule-jump-btn" onclick="scrollToNextGames(event)">';
            $html .= '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12l7 7 7-7"/></svg>';
            $html .= 'Next Games';
            $html .= '</a>';
        }

        $html .= '<p class="schedule-highlight-note">Next sim: ' . $simLengthInDays . ' days</p>';
        $html .= '</div>';
        $html .= '</div>';

        // SOS summary and tier legend
        if ($this->sosSummary !== null) {
            $rsos = number_format((float)$this->sosSummary['remaining_sos'], 3);
            $rsosRank = (int)$this->sosSummary['remaining_sos_rank'];
            $html .= '<div class="sos-summary">';
            $html .= '<span class="sos-summary__label">Remaining SOS:</span> ';
            $html .= '<span class="sos-summary__value">' . $rsos . '</span>';
            $html .= ' <span class="sos-summary__rank">(#' . $rsosRank . ')</span>';
            $html .= '</div>';
        }

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
     * Render month navigation
     *
     * @param array<string, MonthData> $gamesByMonth
     */
    private function renderMonthNav(array $gamesByMonth, bool $isPlayoffPhase, ?string $playoffMonthKey): string
    {
        $html = '<nav class="ibl-jump-menu schedule-months">';
        foreach ($gamesByMonth as $monthKey => $data) {
            if ($isPlayoffPhase && $monthKey === $playoffMonthKey) {
                continue;
            }
            $monthLabel = $data['label'];
            $monthTimestamp = strtotime($monthKey . '-01');
            $abbrev = (is_int($monthTimestamp) && $monthTimestamp !== 0) ? date('M', $monthTimestamp) : '';
            /** @var string $safeLabel */
            $safeLabel = HtmlSanitizer::safeHtmlOutput($monthLabel);
            /** @var string $safeAbbrev */
            $safeAbbrev = HtmlSanitizer::safeHtmlOutput($abbrev);
            $html .= '<a href="#team-month-' . $monthKey . '" class="ibl-jump-menu__link schedule-months__link" onclick="scrollToMonth(event, \'' . $monthKey . '\')">';
            $html .= '<span class="schedule-months__full">' . $safeLabel . '</span>';
            $html .= '<span class="schedule-months__abbr">' . $safeAbbrev . '</span>';
            $html .= '</a>';
        }
        $html .= '</nav>';
        return $html;
    }

    /**
     * Render all games organized by month using same layout as Schedule module
     *
     * @param array<string, MonthData> $gamesByMonth
     * @param list<ScheduleGameRow> $allGames
     */
    private function renderGamesByMonth(array $gamesByMonth, array $allGames, int $userTeamId, string $userTeamName, bool $isPlayoffPhase, ?string $playoffMonthKey): string
    {
        $html = '';
        foreach ($gamesByMonth as $monthKey => $data) {
            $html .= '<div class="schedule-month" id="team-month-' . $monthKey . '">';
            $headerClass = 'schedule-month__header';
            if ($isPlayoffPhase && $monthKey === $playoffMonthKey) {
                $headerClass .= ' schedule-month__header--playoffs';
            }
            /** @var string $safeLabel */
            $safeLabel = HtmlSanitizer::safeHtmlOutput($data['label']);
            $html .= '<div class="' . $headerClass . '">' . $safeLabel . '</div>';

            foreach ($data['dates'] as $date => $games) {
                $dateTimestamp = strtotime($date);
                $dayNum = $dateTimestamp !== false ? date('j', $dateTimestamp) : '';

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
     *
     * @param ScheduleGameRow $row
     */
    private function renderGameRow(array $row, int $userTeamId, string $userTeamName): string
    {
        $game = $row['game'];
        $opposingTeam = $row['opposingTeam'];

        $isUpcoming = ($row['highlight'] === 'next-sim');
        $gameClass = 'schedule-game';
        if ($isUpcoming) {
            $gameClass .= ' schedule-game--upcoming';
        }

        $gameId = 'team-game-' . $game->boxScoreID;
        $visitorTeamId = $game->visitorTeamID;
        $homeTeamId = $game->homeTeamID;
        $boxScoreUrl = \Utilities\BoxScoreUrlBuilder::buildUrl($game->date, $game->gameOfThatDay, $game->boxScoreID);

        // Determine which team is visitor/home
        $isUserHome = ($homeTeamId === $userTeamId);

        // Get team info for both sides - use actual team names with records
        $userRecord = $row['isUnplayed'] ? '' : $row['wins'] . '-' . $row['losses'];
        if ($isUserHome) {
            $visitorName = $opposingTeam->name;
            $visitorRecord = $opposingTeam->seasonRecord ?? '';
            $homeName = $userTeamName;
            $homeRecord = $userRecord;
        } else {
            $visitorName = $userTeamName;
            $visitorRecord = $userRecord;
            $homeName = $opposingTeam->name;
            $homeRecord = $opposingTeam->seasonRecord ?? '';
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
        /** @var string $safeVisitorName */
        $safeVisitorName = HtmlSanitizer::safeHtmlOutput($visitorName);
        /** @var string $safeVisitorRecord */
        $safeVisitorRecord = HtmlSanitizer::safeHtmlOutput($visitorRecord);
        $html .= '<a href="' . $visitorUrl . '" class="schedule-game__team-link">';
        $html .= '<span class="schedule-game__team' . $vWinClass . '"><span class="schedule-game__team-text">' . $safeVisitorName . '</span>';
        if ($visitorRecord !== '') {
            $html .= ' <span class="schedule-game__record">(' . $safeVisitorRecord . ')</span>';
        }
        $html .= '</span></a>';
        $html .= '<a href="' . $visitorUrl . '" class="schedule-game__logo-link">';
        $html .= '<img class="schedule-game__logo" src="images/logo/new' . $visitorTeamId . '.png" alt="" width="25" height="25" loading="lazy">';
        $html .= '</a>';

        // Scores + @ (same as League Schedule)
        if ($row['isUnplayed']) {
            $html .= '<span class="schedule-game__score-link">–</span>';
            $html .= '<span class="schedule-game__vs">@</span>';
            $html .= '<span class="schedule-game__score-link">–</span>';
        } elseif ($boxScoreUrl !== '') {
            $html .= '<a href="' . $boxScoreUrl . '" class="schedule-game__score-link' . $vWinClass . '">' . $game->visitorScore . '</a>';
            $html .= '<a href="' . $boxScoreUrl . '" class="schedule-game__vs">@</a>';
            $html .= '<a href="' . $boxScoreUrl . '" class="schedule-game__score-link' . $hWinClass . '">' . $game->homeScore . '</a>';
        } else {
            $html .= '<span class="schedule-game__score-link' . $vWinClass . '">' . $game->visitorScore . '</span>';
            $html .= '<span class="schedule-game__vs">@</span>';
            $html .= '<span class="schedule-game__score-link' . $hWinClass . '">' . $game->homeScore . '</span>';
        }

        // Home logo + team (same as League Schedule)
        /** @var string $safeHomeName */
        $safeHomeName = HtmlSanitizer::safeHtmlOutput($homeName);
        /** @var string $safeHomeRecord */
        $safeHomeRecord = HtmlSanitizer::safeHtmlOutput($homeRecord);
        $html .= '<a href="' . $homeUrl . '" class="schedule-game__logo-link">';
        $html .= '<img class="schedule-game__logo" src="images/logo/new' . $homeTeamId . '.png" alt="" width="25" height="25" loading="lazy">';
        $html .= '</a>';
        $html .= '<a href="' . $homeUrl . '" class="schedule-game__team-link">';
        $html .= '<span class="schedule-game__team' . $hWinClass . '"><span class="schedule-game__team-text">' . $safeHomeName . '</span>';
        if ($homeRecord !== '') {
            $html .= ' <span class="schedule-game__record">(' . $safeHomeRecord . ')</span>';
        }
        $html .= '</span></a>';

        // Streak column (includes tier dot for unplayed games)
        $html .= $this->renderStreakColumn($row);

        $html .= '</div>';
        return $html;
    }

    /**
     * Render the streak column on the right side of the game row (single line)
     *
     * @param ScheduleGameRow $row
     */
    private function renderStreakColumn(array $row): string
    {
        if ($row['isUnplayed']) {
            $opponentTier = $row['opponentTier'] ?? '';
            if ($opponentTier !== '') {
                return '<span class="schedule-game__streak"><span class="sos-tier-dot sos-tier--' . $opponentTier . '" title="' . $opponentTier . '"></span></span>';
            }
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
     *
     * @param list<ScheduleGameRow> $games
     * @return array<string, MonthData>
     */
    private function organizeGamesByMonth(array $games): array
    {
        /** @var array<string, MonthData> $byMonth */
        $byMonth = [];

        foreach ($games as $row) {
            $game = $row['game'];
            $date = $game->date;
            $dateTimestampForMonth = strtotime($date);
            $monthKey = $dateTimestampForMonth !== false ? date('Y-m', $dateTimestampForMonth) : '1970-01';
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
     *
     * @param list<ScheduleGameRow> $games
     */
    private function findFirstUpcomingGameId(array $games): ?string
    {
        foreach ($games as $row) {
            if ($row['highlight'] === 'next-sim') {
                $game = $row['game'];
                return 'team-game-' . $game->boxScoreID;
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
