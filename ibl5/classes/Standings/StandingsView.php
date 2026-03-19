<?php

declare(strict_types=1);

namespace Standings;

use SeriesRecords\Contracts\SeriesRecordsServiceInterface;
use Standings\Contracts\StandingsRepositoryInterface;
use Standings\Contracts\StandingsViewInterface;
use UI\TeamCellHelper;

/**
 * StandingsView - HTML rendering for team standings
 *
 * Generates sortable HTML tables for conference and division standings.
 * Handles clinched indicators (X/Y/Z) and team streak display.
 *
 * @phpstan-import-type StandingsRow from StandingsRepositoryInterface
 * @phpstan-import-type BulkStandingsRow from StandingsRepositoryInterface
 * @phpstan-import-type StreakRow from StandingsRepositoryInterface
 * @phpstan-import-type PythagoreanStats from StandingsRepositoryInterface
 *
 * @see StandingsViewInterface For the interface contract
 * @see StandingsRepository For data access
 */
class StandingsView implements StandingsViewInterface
{
    private StandingsRepositoryInterface $repository;
    private SeriesRecordsServiceInterface $seriesRecordsService;
    private int $seasonYear;

    /** @var array<int, StreakRow>|null Pre-loaded streak data keyed by team ID */
    private ?array $allStreakData = null;

    /** @var array<int, PythagoreanStats>|null Pre-loaded Pythagorean stats keyed by team ID */
    private ?array $allPythagoreanStats = null;

    /** @var array<int, array<int, array{wins: int, losses: int}>>|null Pre-loaded H2H series matrix */
    private ?array $seriesMatrix = null;

    /**
     * Constructor
     *
     * @param StandingsRepositoryInterface $repository Standings data repository
     * @param int $seasonYear Season ending year (e.g. 2025 for the 2024-25 season)
     * @param SeriesRecordsServiceInterface $seriesRecordsService Series records service for H2H data
     */
    public function __construct(
        StandingsRepositoryInterface $repository,
        int $seasonYear,
        SeriesRecordsServiceInterface $seriesRecordsService
    ) {
        $this->repository = $repository;
        $this->seasonYear = $seasonYear;
        $this->seriesRecordsService = $seriesRecordsService;
    }

    /**
     * @see StandingsViewInterface::render()
     */
    public function render(): string
    {
        // Pre-load all streak, Pythagorean, and H2H data in bulk queries
        $this->allStreakData = $this->repository->getAllStreakData();
        $this->allPythagoreanStats = $this->repository->getAllPythagoreanStats($this->seasonYear);
        $this->seriesMatrix = $this->seriesRecordsService->buildSeriesMatrix(
            $this->repository->getSeriesRecords()
        );

        // Bulk-fetch all standings in 1 query instead of 6
        $allStandings = $this->repository->getAllStandings();
        $grouped = $this->groupStandings($allStandings);

        $regions = ['Eastern', 'Western', 'Atlantic', 'Central', 'Midwest', 'Pacific'];
        $html = '';

        foreach ($regions as $region) {
            $isConference = in_array($region, \League::CONFERENCE_NAMES, true);
            $gbColumn = $isConference ? 'confGB' : 'divGB';

            $regionTeams = $grouped[$region] ?? [];
            $this->sortStandings($regionTeams, $gbColumn);

            // Convert BulkStandingsRow to StandingsRow by aliasing gamesBack/magicNumber
            $standings = $this->adaptBulkRows($regionTeams, $isConference);

            $groupingType = $isConference ? 'Conference' : 'Division';
            $html .= $this->renderStandingsTable($region, $groupingType, $standings);
        }

        // Clear pre-loaded data
        $this->allStreakData = null;
        $this->allPythagoreanStats = null;
        $this->seriesMatrix = null;

        return $html;
    }

    /**
     * @see StandingsViewInterface::renderRegion()
     */
    public function renderRegion(string $region): string
    {
        // If called standalone (not via render()), load data on demand
        if ($this->allStreakData === null) {
            $this->allStreakData = $this->repository->getAllStreakData();
        }
        if ($this->allPythagoreanStats === null) {
            $this->allPythagoreanStats = $this->repository->getAllPythagoreanStats($this->seasonYear);
        }
        if ($this->seriesMatrix === null) {
            $this->seriesMatrix = $this->seriesRecordsService->buildSeriesMatrix(
                $this->repository->getSeriesRecords()
            );
        }

        $groupingType = $this->getGroupingType($region);
        $standings = $this->repository->getStandingsByRegion($region);

        return $this->renderStandingsTable($region, $groupingType, $standings);
    }

    /**
     * Render a complete standings table: resolve H2H ties, render header + rows + closing tags
     *
     * @param string $region Region name (e.g. 'Eastern', 'Atlantic')
     * @param string $groupingType 'Conference' or 'Division'
     * @param list<StandingsRow> $standings Sorted standings data
     * @return string Complete HTML for one standings table
     */
    private function renderStandingsTable(string $region, string $groupingType, array $standings): string
    {
        $standings = $this->resolveH2HTiedGroups($standings);

        $html = $this->renderHeader($region, $groupingType);
        $html .= $this->renderRows($standings);
        $html .= '</tbody></table></div></div>';

        return $html;
    }

    /**
     * Group bulk standings rows by conference and division
     *
     * @param list<BulkStandingsRow> $allStandings
     * @return array<string, list<BulkStandingsRow>>
     */
    private function groupStandings(array $allStandings): array
    {
        /** @var array<string, list<BulkStandingsRow>> $grouped */
        $grouped = [];

        foreach ($allStandings as $team) {
            $grouped[$team['conference']][] = $team;
            $grouped[$team['division']][] = $team;
        }

        return $grouped;
    }

    /**
     * Sort standings in-place replicating SQL ORDER BY
     *
     * @param list<BulkStandingsRow> $teams
     * @param string $gbColumn Column name for games back sorting ('confGB' or 'divGB')
     */
    private function sortStandings(array &$teams, string $gbColumn): void
    {
        usort($teams, function (array $a, array $b) use ($gbColumn): int {
            // 1. Games back ASC
            $gbA = $gbColumn === 'confGB' ? $a['confGB'] : $a['divGB'];
            $gbB = $gbColumn === 'confGB' ? $b['confGB'] : $b['divGB'];
            $gbCmp = (float) $gbA <=> (float) $gbB;
            if ($gbCmp !== 0) {
                return $gbCmp;
            }

            // 2. Clinch priority DESC
            $clinchCmp = $this->getClinchTierScore($b) <=> $this->getClinchTierScore($a);
            if ($clinchCmp !== 0) {
                return $clinchCmp;
            }

            // 3. Wins DESC
            return $b['wins'] <=> $a['wins'];
        });
    }

    /**
     * Convert bulk standings rows to the StandingsRow format expected by renderTeamRow()
     *
     * @param list<BulkStandingsRow> $teams
     * @param bool $isConference Whether to use conference or division GB/magic columns
     * @return list<StandingsRow>
     */
    private function adaptBulkRows(array $teams, bool $isConference): array
    {
        /** @var list<StandingsRow> $result */
        $result = [];

        foreach ($teams as $team) {
            $result[] = [
                'tid' => $team['tid'],
                'team_name' => $team['team_name'],
                'leagueRecord' => $team['leagueRecord'],
                'pct' => $team['pct'],
                'gamesBack' => $isConference ? $team['confGB'] : $team['divGB'],
                'confRecord' => $team['confRecord'],
                'divRecord' => $team['divRecord'],
                'homeRecord' => $team['homeRecord'],
                'awayRecord' => $team['awayRecord'],
                'gamesUnplayed' => $team['gamesUnplayed'],
                'magicNumber' => $isConference ? $team['confMagicNumber'] : $team['divMagicNumber'],
                'clinchedConference' => $team['clinchedConference'],
                'clinchedDivision' => $team['clinchedDivision'],
                'clinchedPlayoffs' => $team['clinchedPlayoffs'],
                'clinchedLeague' => $team['clinchedLeague'],
                'wins' => $team['wins'],
                'homeGames' => $team['homeGames'],
                'awayGames' => $team['awayGames'],
                'color1' => $team['color1'],
                'color2' => $team['color2'],
            ];
        }

        return $result;
    }

    /**
     * Get the grouping type (Conference or Division) for a region
     *
     * @param string $region Region name
     * @return string 'Conference' or 'Division'
     */
    private function getGroupingType(string $region): string
    {
        if (in_array($region, \League::CONFERENCE_NAMES, true)) {
            return 'Conference';
        }

        return 'Division';
    }

    /**
     * Render the table header for a standings section
     *
     * @param string $region Region name
     * @param string $groupingType 'Conference' or 'Division'
     * @return string HTML for table header
     */
    private function renderHeader(string $region, string $groupingType): string
    {
        $safeRegion = \Utilities\HtmlSanitizer::safeHtmlOutput($region);
        $title = $safeRegion . ' ' . $groupingType;

        ob_start();
        ?>
        <h2 class="ibl-title"><?= $title; ?></h2>
        <div class="table-scroll-wrapper">
        <div class="table-scroll-container" tabindex="0" role="region" aria-label="Standings">
        <table class="sortable ibl-data-table">
            <thead>
                <tr>
                    <th class="sticky-col">Team</th>
                    <th>W-L</th>
                    <th>Win%</th>
                    <th>Pyth<br>W-L%</th>
                    <th>GB</th>
                    <th>Magic<br>#</th>
                    <th>Games<br>Left</th>
                    <th>Conf.</th>
                    <th>Div.</th>
                    <th>Home</th>
                    <th>Away</th>
                    <th>Home<br>Played</th>
                    <th>Away<br>Played</th>
                    <th>Last 10<br>W-L</th>
                    <th>Streak</th>
                    <th>Power<br>Rank</th>
                    <th>SOS</th>
                    <th>Rem.<br>SOS</th>
                </tr>
            </thead>
            <tbody>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render all team rows for a standings table
     *
     * @param list<StandingsRow> $standings Array of team standings data
     * @return string HTML for all team rows
     */
    private function renderRows(array $standings): string
    {
        $html = '';
        $bottomLocked = $this->getBottomLockedIndexes($standings);

        foreach ($standings as $index => $team) {
            $isBottomLocked = isset($bottomLocked[$index]);
            $html .= $this->renderTeamRow($team, $isBottomLocked);
        }

        return $html;
    }

    /**
     * Render a single team row
     *
     * @param StandingsRow $team Team standings data
     * @param bool $isBottomLocked Whether this team is mathematically locked at the bottom
     * @return string HTML for team row
     */
    private function renderTeamRow(array $team, bool $isBottomLocked = false): string
    {
        $teamId = $team['tid'];
        $teamName = $this->formatTeamName($team);
        $streakData = $this->allStreakData[$teamId] ?? null;

        $lastWin = $streakData['last_win'] ?? 0;
        $lastLoss = $streakData['last_loss'] ?? 0;
        $streakType = \Utilities\HtmlSanitizer::safeHtmlOutput($streakData['streak_type'] ?? '');
        $streak = $streakData['streak'] ?? 0;
        $streakSortKey = ($streakData['streak_type'] ?? '') === 'W' ? $streak : -$streak;
        $rating = $streakData['ranking'] ?? 0;
        $sos = $streakData['sos'] ?? 0;
        $remainingSos = $streakData['remaining_sos'] ?? 0;

        // Get Pythagorean win percentage from pre-loaded data
        $pythagoreanStats = $this->allPythagoreanStats[$teamId] ?? null;
        $pythagoreanPct = '0.000';
        if ($pythagoreanStats !== null) {
            $pythagoreanPct = \BasketballStats\StatsFormatter::calculatePythagoreanWinPercentage(
                $pythagoreanStats['pointsScored'],
                $pythagoreanStats['pointsAllowed']
            );
        }

        $leagueRecord = $team['leagueRecord'];
        $pct = $team['pct'];
        $gamesBack = $team['gamesBack'];
        $magicNumber = $team['magicNumber'];
        $gamesUnplayed = $team['gamesUnplayed'];
        $confRecord = $team['confRecord'];
        $divRecord = $team['divRecord'];
        $homeRecord = $team['homeRecord'];
        $awayRecord = $team['awayRecord'];
        $homeGames = $team['homeGames'];
        $awayGames = $team['awayGames'];

        // Build CSS class for row highlighting
        $rowClass = '';
        if ($isBottomLocked) {
            $rowClass = 'bottom-locked';
        } else {
            $rowClass = $this->getClinchTierClass($team);
        }
        $classAttr = $rowClass !== '' ? ' class="' . $rowClass . '"' : '';

        ob_start();
        ?>
        <tr data-team-id="<?= $teamId; ?>"<?= $classAttr; ?>>
            <?= TeamCellHelper::renderTeamCell($teamId, $team['team_name'], $team['color1'], $team['color2'], 'sticky-col', '', $teamName) ?>
            <td><?= $leagueRecord; ?></td>
            <td><?= \BasketballStats\StatsFormatter::formatWithDecimals((float)$pct, 3); ?></td>
            <td><?= $pythagoreanPct; ?></td>
            <td><?= $gamesBack; ?></td>
            <td><?= $magicNumber; ?></td>
            <td><?= $gamesUnplayed; ?></td>
            <td><?= $confRecord; ?></td>
            <td><?= $divRecord; ?></td>
            <td><?= $homeRecord; ?></td>
            <td><?= $awayRecord; ?></td>
            <td><?= $homeGames; ?></td>
            <td><?= $awayGames; ?></td>
            <td><?= $lastWin; ?>-<?= $lastLoss; ?></td>
            <td sorttable_customkey="<?= $streakSortKey; ?>"><?= $streakType; ?> <?= $streak; ?></td>
            <td><span class="ibl-stat-highlight"><?= $rating; ?></span></td>
            <td><?= \BasketballStats\StatsFormatter::formatWithDecimals((float)$sos, 3); ?></td>
            <td><?= \BasketballStats\StatsFormatter::formatWithDecimals((float)$remainingSos, 3); ?></td>
        </tr>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Format team name with clinched indicator
     *
     * Priority: W (league) > Z (conference) > Y (division) > X (playoffs)
     *
     * @param StandingsRow $team Team standings data
     * @return string Formatted team name with clinched prefix if applicable
     */
    private function formatTeamName(array $team): string
    {
        $teamName = \Utilities\HtmlSanitizer::safeHtmlOutput($team['team_name']);

        if ($team['clinchedLeague'] === 1) {
            return '<span class="ibl-clinched-indicator">W</span>-' . $teamName;
        }

        if ($team['clinchedConference'] === 1) {
            return '<span class="ibl-clinched-indicator">Z</span>-' . $teamName;
        }

        if ($team['clinchedDivision'] === 1) {
            return '<span class="ibl-clinched-indicator">Y</span>-' . $teamName;
        }

        if ($team['clinchedPlayoffs'] === 1) {
            return '<span class="ibl-clinched-indicator">X</span>-' . $teamName;
        }

        return $teamName;
    }

    /**
     * Get the CSS class for a team's clinch tier
     *
     * @param StandingsRow $team Team standings data
     * @return string CSS class name, or empty string if not clinched
     */
    private function getClinchTierClass(array $team): string
    {
        if ($team['clinchedLeague'] === 1) {
            return 'clinch-league';
        }

        if ($team['clinchedConference'] === 1) {
            return 'clinch-conference';
        }

        if ($team['clinchedDivision'] === 1) {
            return 'clinch-division';
        }

        if ($team['clinchedPlayoffs'] === 1) {
            return 'clinch-playoffs';
        }

        return '';
    }

    /**
     * Determine which teams are eliminated (bottom-locked) in standings
     *
     * When the season is over (all gamesUnplayed = 0), any team without a clinch
     * flag is eliminated. During the season, cascades from the bottom: a team is
     * locked if even winning all remaining games can't catch the team above.
     * The cascade stops at clinched teams or when a team can catch the one above.
     *
     * @param list<StandingsRow> $standings Standings data sorted by games back ASC
     * @return array<int, true> Map of array indexes that are bottom-locked
     */
    private function getBottomLockedIndexes(array $standings): array
    {
        $count = count($standings);
        /** @var array<int, true> $locked */
        $locked = [];

        if ($this->isSeasonOver($standings)) {
            foreach ($standings as $index => $team) {
                if (!$this->hasClinchStatus($team)) {
                    $locked[$index] = true;
                }
            }

            return $locked;
        }

        // During season: cascade from bottom, stop at clinched teams
        for ($i = $count - 1; $i >= 1; $i--) {
            if ($this->hasClinchStatus($standings[$i])) {
                break;
            }

            $maxPossibleWins = $standings[$i]['wins'] + $standings[$i]['gamesUnplayed'];
            if ($maxPossibleWins < $standings[$i - 1]['wins']) {
                $locked[$i] = true;
            } else {
                break;
            }
        }

        return $locked;
    }

    /**
     * Check if the season is over (all teams have 0 games remaining)
     *
     * @param list<StandingsRow> $standings Standings data
     */
    private function isSeasonOver(array $standings): bool
    {
        foreach ($standings as $team) {
            if ($team['gamesUnplayed'] > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compute clinch tier score for a team (higher = better clinch status)
     *
     * @param array{clinchedLeague: int, clinchedConference: int, clinchedDivision: int, clinchedPlayoffs: int, ...} $team
     */
    private function getClinchTierScore(array $team): int
    {
        return $team['clinchedLeague'] * 4
            + $team['clinchedConference'] * 3
            + $team['clinchedDivision'] * 2
            + $team['clinchedPlayoffs'];
    }

    /**
     * Resolve H2H tie-breaking for groups of teams tied on GB, clinch tier, and wins
     *
     * Walks the sorted standings list, identifies groups of teams with the same
     * games-back, clinch tier, and wins, then sorts each group by aggregate H2H
     * win percentage (best H2H first for standings).
     *
     * @param list<StandingsRow> $teams Standings sorted by SQL (GB, clinch, wins)
     * @return list<StandingsRow> Re-sorted standings with H2H tie-breaking applied
     */
    private function resolveH2HTiedGroups(array $teams): array
    {
        if (count($teams) <= 1 || $this->seriesMatrix === null || $this->seriesMatrix === []) {
            return $teams;
        }

        /** @var list<StandingsRow> $result */
        $result = [];
        $count = count($teams);
        $groupStart = 0;

        for ($i = 1; $i <= $count; $i++) {
            if ($i < $count
                && $teams[$i]['gamesBack'] === $teams[$groupStart]['gamesBack']
                && $this->getClinchTierScore($teams[$i]) === $this->getClinchTierScore($teams[$groupStart])
                && $teams[$i]['wins'] === $teams[$groupStart]['wins']
            ) {
                continue;
            }

            $group = array_slice($teams, $groupStart, $i - $groupStart);

            if (count($group) > 1) {
                $group = $this->sortTiedGroup($group);
            }

            array_push($result, ...$group);
            $groupStart = $i;
        }

        return $result;
    }

    /**
     * Sort a tied group by aggregate H2H win percentage (best first for standings)
     *
     * For each team, computes aggregate H2H record against all other teams in the
     * group, then sorts descending by H2H win pct.
     *
     * @param list<StandingsRow> $group Teams tied on GB/clinch/wins
     * @return list<StandingsRow> Sorted group (best H2H first)
     */
    private function sortTiedGroup(array $group): array
    {
        $tids = array_map(static fn (array $t): int => $t['tid'], $group);

        /** @var array<int, float> */
        $aggregateH2HPct = [];
        foreach ($group as $team) {
            $totalWins = 0;
            $totalLosses = 0;
            foreach ($tids as $opponentTid) {
                if ($opponentTid === $team['tid']) {
                    continue;
                }
                $totalWins += $this->seriesMatrix[$team['tid']][$opponentTid]['wins'] ?? 0;
                $totalLosses += $this->seriesMatrix[$team['tid']][$opponentTid]['losses'] ?? 0;
            }
            $aggregateH2HPct[$team['tid']] = $this->safeWinPct($totalWins, $totalLosses);
        }

        // Sort descending by H2H pct (best first for standings)
        usort($group, static function (array $a, array $b) use ($aggregateH2HPct): int {
            return $aggregateH2HPct[$b['tid']] <=> $aggregateH2HPct[$a['tid']];
        });

        return $group;
    }

    /**
     * Safe division for win percentage calculation
     */
    private function safeWinPct(int $wins, int $losses): float
    {
        $total = $wins + $losses;
        if ($total === 0) {
            return 0.0;
        }

        return $wins / $total;
    }

    /**
     * Check if a team has any clinch status (playoffs, division, conference, or league)
     *
     * @param StandingsRow $team Team standings data
     */
    private function hasClinchStatus(array $team): bool
    {
        return $team['clinchedLeague'] === 1
            || $team['clinchedConference'] === 1
            || $team['clinchedDivision'] === 1
            || $team['clinchedPlayoffs'] === 1;
    }
}
