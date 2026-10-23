<?php

declare(strict_types=1);

namespace TeamSchedule;

use TeamSchedule\Contracts\TeamScheduleViewInterface;
use TeamSchedule\Contracts\TeamScheduleServiceInterface;
use Security\HtmlSanitizer;
use Team\Team;
use Season\Season;

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
    public function render(Team $team, array $games, int $simLengthInDays, string $seasonPhase): string
    {
        $color1 = HtmlSanitizer::safeHtmlOutput($team->color1);
        $color2 = HtmlSanitizer::safeHtmlOutput($team->color2);
        $teamId = $team->teamid;
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
                if ($monthTimestamp !== false && (int)date('n', $monthTimestamp) === Season::IBL_PLAYOFF_MONTH) {
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

        $html = '<div class="schedule-container schedule-container--team" style="' . \UI\TableStyles::inlineTeamVars($color1, $color2, withOnPrimary: true) . '">';
        $html .= $this->renderTeamBanner($teamId, $teamName);
        $html .= $this->renderHeader($simLengthInDays, $firstUpcomingId);
        $html .= $this->renderMonthNav($gamesByMonth, $isPlayoffPhase, $playoffMonthKey);
        $html .= $this->renderGamesByMonth($gamesByMonth, $teamId, $team->name, $isPlayoffPhase, $playoffMonthKey);
        $html .= '</div>';
        $html .= $this->renderScrollScripts($firstUpcomingId);

        return $html;
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
            $rsos = \BasketballStats\StatsFormatter::formatWithDecimals((float)$this->sosSummary['remaining_sos'], 3);
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
        $html = '<nav class="ibl-jump-menu schedule-months" aria-label="Jump to month">';
        foreach ($gamesByMonth as $monthKey => $data) {
            if ($isPlayoffPhase && $monthKey === $playoffMonthKey) {
                continue;
            }
            $monthLabel = $data['label'];
            $monthTimestamp = strtotime($monthKey . '-01');
            $abbrev = (is_int($monthTimestamp) && $monthTimestamp !== 0) ? date('M', $monthTimestamp) : '';
            $safeLabel = HtmlSanitizer::safeHtmlOutput($monthLabel);
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
     */
    private function renderGamesByMonth(array $gamesByMonth, int $userTeamId, string $userTeamName, bool $isPlayoffPhase, ?string $playoffMonthKey): string
    {
        $html = '';
        foreach ($gamesByMonth as $monthKey => $data) {
            $html .= '<div class="schedule-month" id="team-month-' . $monthKey . '">';
            $headerClass = 'ibl-card__header schedule-month__header';
            if ($isPlayoffPhase && $monthKey === $playoffMonthKey) {
                $headerClass .= ' schedule-month__header--playoffs';
            }
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
        $isUserHome = ($game->home_teamid === $userTeamId);
        $boxScoreUrl = \Utilities\BoxScoreUrlBuilder::buildUrl($game->date, $game->gameOfThatDay, $game->boxScoreID);

        [
            'visitorName' => $visitorName,
            'visitorRecord' => $visitorRecord,
            'homeName' => $homeName,
            'homeRecord' => $homeRecord,
        ] = $this->resolveSideNamesAndRecords($row, $isUserHome, $userTeamName);
        ['visitor' => $vWinClass, 'home' => $hWinClass] = $this->resolveWinClasses($row, $isUserHome);

        return $this->renderGameRowOpenTag($row)
            . $this->renderTeamSide($game->visitor_teamid, $visitorName, $visitorRecord, $vWinClass, logoFirst: false)
            . $this->renderScoreCells($row, $boxScoreUrl, $vWinClass, $hWinClass)
            . $this->renderTeamSide($game->home_teamid, $homeName, $homeRecord, $hWinClass, logoFirst: true)
            . $this->renderStreakColumn($row)
            . '</div>';
    }

    /**
     * Opening row tag; the --upcoming modifier marks the next-sim game
     *
     * @param ScheduleGameRow $row
     */
    private function renderGameRowOpenTag(array $row): string
    {
        $gameClass = 'schedule-game';
        if ($row['highlight'] === 'next-sim') {
            $gameClass .= ' schedule-game--upcoming';
        }

        return '<div class="' . $gameClass . '" id="team-game-' . $row['game']->boxScoreID . '">';
    }

    /**
     * User team record: blank for an unplayed game, otherwise "wins-losses"
     *
     * @param ScheduleGameRow $row
     */
    private function resolveUserRecord(array $row): string
    {
        return $row['isUnplayed'] ? '' : $row['wins'] . '-' . $row['losses'];
    }

    /**
     * Raw (unescaped) name and record per side; the user team is the home side when $isUserHome
     *
     * @param ScheduleGameRow $row
     * @return array{visitorName: string, visitorRecord: string, homeName: string, homeRecord: string}
     */
    private function resolveSideNamesAndRecords(array $row, bool $isUserHome, string $userTeamName): array
    {
        $opposingTeam = $row['opposingTeam'];
        $userRecord = $this->resolveUserRecord($row);
        $opponentRecord = $opposingTeam->seasonRecord ?? '';

        if ($isUserHome) {
            return [
                'visitorName' => $opposingTeam->name,
                'visitorRecord' => $opponentRecord,
                'homeName' => $userTeamName,
                'homeRecord' => $userRecord,
            ];
        }

        return [
            'visitorName' => $userTeamName,
            'visitorRecord' => $userRecord,
            'homeName' => $opposingTeam->name,
            'homeRecord' => $opponentRecord,
        ];
    }

    /**
     * Win-highlight class suffix per side; both blank for an unplayed game
     *
     * @param ScheduleGameRow $row
     * @return array{visitor: string, home: string}
     */
    private function resolveWinClasses(array $row, bool $isUserHome): array
    {
        if ($row['isUnplayed']) {
            return ['visitor' => '', 'home' => ''];
        }

        $userWon = ($row['winLossColor'] === 'green');
        $userClass = $userWon ? ' schedule-game__team--win' : '';
        $opponentClass = $userWon ? '' : ' schedule-game__team--win';

        return $isUserHome
            ? ['visitor' => $opponentClass, 'home' => $userClass]
            : ['visitor' => $userClass, 'home' => $opponentClass];
    }

    /**
     * Team name link and logo link for one side; the home side puts the logo first (mirrored layout)
     */
    private function renderTeamSide(int $teamId, string $name, string $record, string $winClass, bool $logoFirst): string
    {
        $teamUrl = 'modules.php?name=Team&amp;op=team&amp;teamid=' . $teamId;
        $safeName = HtmlSanitizer::safeHtmlOutput($name);
        $safeRecord = HtmlSanitizer::safeHtmlOutput($record);

        $nameLink = '<a href="' . $teamUrl . '" class="schedule-game__team-link">'
            . '<span class="schedule-game__team' . $winClass . '"><span class="schedule-game__team-text">' . $safeName . '</span>';
        if ($record !== '') {
            $nameLink .= ' <span class="schedule-game__record">(' . $safeRecord . ')</span>';
        }
        $nameLink .= '</span></a>';

        $logoLink = '<a href="' . $teamUrl . '" class="schedule-game__logo-link" aria-label="' . $safeName . '">'
            . '<img class="schedule-game__logo" src="images/logo/new' . $teamId . '.png" alt="" width="25" height="25" loading="lazy">'
            . '</a>';

        return $logoFirst ? $logoLink . $nameLink : $nameLink . $logoLink;
    }

    /**
     * Visitor score, "@", home score: dashes when unplayed, box-score links when a URL exists, plain spans otherwise
     *
     * @param ScheduleGameRow $row
     */
    private function renderScoreCells(array $row, string $boxScoreUrl, string $vWinClass, string $hWinClass): string
    {
        if ($row['isUnplayed']) {
            return '<span class="schedule-game__score-link">–</span>'
                . '<span class="schedule-game__vs">@</span>'
                . '<span class="schedule-game__score-link">–</span>';
        }

        $game = $row['game'];
        if ($boxScoreUrl !== '') {
            return '<a href="' . $boxScoreUrl . '" class="schedule-game__score-link' . $vWinClass . '">' . $game->visitorScore . '</a>'
                . '<a href="' . $boxScoreUrl . '" class="schedule-game__vs">@</a>'
                . '<a href="' . $boxScoreUrl . '" class="schedule-game__score-link' . $hWinClass . '">' . $game->homeScore . '</a>';
        }

        return '<span class="schedule-game__score-link' . $vWinClass . '">' . $game->visitorScore . '</span>'
            . '<span class="schedule-game__vs">@</span>'
            . '<span class="schedule-game__score-link' . $hWinClass . '">' . $game->homeScore . '</span>';
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
