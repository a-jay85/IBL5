<?php

declare(strict_types=1);

namespace Standings;

use League\League;
use SeriesRecords\Contracts\SeriesRecordsServiceInterface;
use Standings\Contracts\StandingsRepositoryInterface;
use Standings\Contracts\StandingsViewInterface;

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

    private readonly StandingsRowView $rowView;
    private readonly StandingsTiebreakerResolver $tiebreakerResolver;

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
        $this->rowView = new StandingsRowView();
        $this->tiebreakerResolver = new StandingsTiebreakerResolver();
    }

    /**
     * @see StandingsViewInterface::render()
     */
    public function render(): string
    {
        // Pre-load all streak, Pythagorean, and H2H data in bulk queries
        $this->ensureBulkDataLoaded();

        // Bulk-fetch all standings in 1 query instead of 6
        $allStandings = $this->repository->getAllStandings();
        $grouped = $this->groupStandings($allStandings);

        $regions = ['Eastern', 'Western', 'Atlantic', 'Central', 'Midwest', 'Pacific'];
        $html = '<h1 class="ibl-title">Standings</h1>';

        foreach ($regions as $region) {
            $isConference = in_array($region, League::CONFERENCE_NAMES, true);
            $gbColumn = $isConference ? 'conf_gb' : 'div_gb';

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
     * Lazily load streak, Pythagorean, and H2H series-matrix data into the
     * shared cache properties if not already populated. Idempotent: a second
     * call is a no-op because each property is non-null after the first.
     */
    private function ensureBulkDataLoaded(): void
    {
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
    }

    /**
     * @see StandingsViewInterface::renderRegion()
     */
    public function renderRegion(string $region): string
    {
        // If called standalone (not via render()), load data on demand
        $this->ensureBulkDataLoaded();

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
        $standings = $this->tiebreakerResolver->resolveH2HTiedGroups($standings, $this->seriesMatrix);

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
     * @param string $gbColumn Column name for games back sorting ('conf_gb' or 'div_gb')
     */
    private function sortStandings(array &$teams, string $gbColumn): void
    {
        usort($teams, function (array $a, array $b) use ($gbColumn): int {
            // 1. Games back ASC
            $gbA = $gbColumn === 'conf_gb' ? $a['conf_gb'] : $a['div_gb'];
            $gbB = $gbColumn === 'conf_gb' ? $b['conf_gb'] : $b['div_gb'];
            $gbCmp = (float) $gbA <=> (float) $gbB;
            if ($gbCmp !== 0) {
                return $gbCmp;
            }

            // 2. Clinch priority DESC
            $clinchCmp = StandingsRowView::getClinchTierScore($b) <=> StandingsRowView::getClinchTierScore($a);
            if ($clinchCmp !== 0) {
                return $clinchCmp;
            }

            // 3. Wins DESC
            $winsCmp = $b['wins'] <=> $a['wins'];
            if ($winsCmp !== 0) {
                return $winsCmp;
            }

            return $a['teamid'] <=> $b['teamid'];
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
                'teamid' => $team['teamid'],
                'team_name' => $team['team_name'],
                'league_record' => $team['league_record'],
                'pct' => $team['pct'],
                'gamesBack' => $isConference ? $team['conf_gb'] : $team['div_gb'],
                'conf_record' => $team['conf_record'],
                'div_record' => $team['div_record'],
                'home_record' => $team['home_record'],
                'away_record' => $team['away_record'],
                'games_unplayed' => $team['games_unplayed'],
                'magicNumber' => $isConference ? $team['conf_magic_number'] : $team['div_magic_number'],
                'clinched_conference' => $team['clinched_conference'],
                'clinched_division' => $team['clinched_division'],
                'clinched_playoffs' => $team['clinched_playoffs'],
                'clinched_league' => $team['clinched_league'],
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
        if (in_array($region, League::CONFERENCE_NAMES, true)) {
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
        ob_start();
        ?>
        <h2 class="ibl-title"><?= \Security\HtmlSanitizer::e($region) . ' ' . \Security\HtmlSanitizer::e($groupingType); ?></h2>
        <div class="table-scroll-wrapper">
        <div class="table-scroll-container" tabindex="0" role="region" aria-label="<?= \Security\HtmlSanitizer::e($region) . ' ' . \Security\HtmlSanitizer::e($groupingType) . ' Standings'; ?>">
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
        $bottomLocked = $this->rowView->getBottomLockedIndexes($standings);

        foreach ($standings as $index => $team) {
            $isBottomLocked = isset($bottomLocked[$index]);
            $teamId = $team['teamid'];
            $html .= $this->rowView->renderTeamRow($team, $isBottomLocked, $this->allStreakData[$teamId] ?? null, $this->allPythagoreanStats[$teamId] ?? null);
        }

        return $html;
    }

}

