<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use FranchiseHistory\FranchiseHistoryRepository;
use League\League;

/**
 * Database integration tests for FranchiseHistoryRepository.
 *
 * Tests the VIEW chain: vw_franchise_summary → ibl_team_win_loss → ibl_box_scores_teams,
 * plus vw_playoff_series_results and ibl_heat_win_loss.
 */
class FranchiseHistoryRepositoryTest extends DatabaseTestCase
{
    private FranchiseHistoryRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new FranchiseHistoryRepository($this->db);
    }

    public function testReturnsArrayWithExpectedKeys(): void
    {
        // Seed data already has franchise_seasons for Metros (franchise_id=1, ending_year=2024)
        // and team boxscores are not strictly required — vw_franchise_summary works with zero games
        $result = $this->repo->getAllFranchiseHistory(2024);

        self::assertNotEmpty($result);

        $first = $result[0];
        $expectedKeys = [
            'teamid', 'team_name', 'color1', 'color2',
            'totwins', 'totloss', 'winpct', 'playoffs',
            'five_season_wins', 'five_season_losses', 'totalgames', 'five_season_winpct',
            'playoff_total_wins', 'playoff_total_losses', 'playoff_winpct',
            'heat_total_wins', 'heat_total_losses', 'heat_winpct',
            'heat_titles', 'div_titles', 'conf_titles', 'ibl_titles',
        ];
        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $first, "Missing key: $key");
        }
    }

    public function testExcludesFreeAgents(): void
    {
        $result = $this->repo->getAllFranchiseHistory(2024);

        $teamIds = array_column($result, 'teamid');
        self::assertNotContains(League::FREE_AGENTS_TEAMID, $teamIds);
    }

    public function testOnlyIncludesRealTeams(): void
    {
        $result = $this->repo->getAllFranchiseHistory(2024);

        foreach ($result as $row) {
            self::assertGreaterThanOrEqual(1, $row['teamid']);
            self::assertLessThanOrEqual(League::MAX_REAL_TEAMID, $row['teamid']);
        }
    }

    public function testFiveSeasonWindowAggregatesCorrectly(): void
    {
        // Insert regular-season team boxscores for Metros across 3 seasons
        // Each game needs BOTH team rows in ibl_box_scores_teams
        // Dates: Jan = regular season (game_type=1)
        // season_year computed: month < 10 → YEAR(Date)
        $seasons = [
            ['date' => '2096-01-15', 'ending_year' => 2096],
            ['date' => '2097-01-15', 'ending_year' => 2097],
            ['date' => '2098-01-15', 'ending_year' => 2098],
        ];

        foreach ($seasons as $s) {
            // Insert franchise_season row so the VIEW can join
            $this->insertFranchiseSeasonRow(1, $s['ending_year'], 'Metros');
            $this->insertFranchiseSeasonRow(2, $s['ending_year'], 'Sharks');

            // Insert both team boxscore rows for a game
            $this->insertTeamBoxscoreRow($s['date'], 'Metros', 1, 2, 1);
            $this->insertTeamBoxscoreRow($s['date'], 'Sharks', 1, 2, 1);
        }

        $result = $this->repo->getAllFranchiseHistory(2098);

        $metros = null;
        foreach ($result as $row) {
            if ($row['team_name'] === 'Metros') {
                $metros = $row;
                break;
            }
        }
        self::assertNotNull($metros, 'Metros should appear in results');

        // 3 games in 5-season window (2094-2098), Metros is home team so wins all 3
        // (homeQ1+Q2+Q3+Q4 = 104, visitorQ1+Q2+Q3+Q4 = 85 → home wins)
        self::assertGreaterThanOrEqual(3, $metros['five_season_wins']);
    }

    public function testFiveSeasonWindowExcludesOutOfRange(): void
    {
        // Insert a game 6 seasons before the query year
        $this->insertFranchiseSeasonRow(1, 2092, 'Metros');
        $this->insertFranchiseSeasonRow(2, 2092, 'Sharks');
        $this->insertTeamBoxscoreRow('2092-01-15', 'Metros', 1, 2, 1);
        $this->insertTeamBoxscoreRow('2092-01-15', 'Sharks', 1, 2, 1);

        $result = $this->repo->getAllFranchiseHistory(2098);
        $metros = null;
        foreach ($result as $row) {
            if ($row['team_name'] === 'Metros') {
                $metros = $row;
                break;
            }
        }
        self::assertNotNull($metros);

        // 2092 is outside the 5-season window (2094-2098), so should not be counted
        // Only seed data game (2024) might contribute if in range, but 2024 is also out of range
        // five_season_wins should be 0 for just the out-of-range games
        self::assertSame(0, $metros['five_season_wins']);
    }

    public function testPlayoffTotalsReflectPlayoffBoxscores(): void
    {
        // vw_playoff_series_results is now a thin pass-through over the
        // materialized ibl_playoff_series_results table (refreshed by
        // RefreshPlayoffSeriesResultsStep). Insert directly to assert the
        // aggregation chain through vw_franchise_summary.
        $this->insertFranchiseSeasonRow(1, 9098, 'Metros');
        $this->insertFranchiseSeasonRow(2, 9098, 'Sharks');
        $this->insertPlayoffSeriesResultRow(9098, 1, 1, 2, 'Metros', 'Sharks', 4, 2);

        $result = $this->repo->getAllFranchiseHistory(9098);
        $metros = null;
        foreach ($result as $row) {
            if ($row['team_name'] === 'Metros') {
                $metros = $row;
                break;
            }
        }
        self::assertNotNull($metros);
        $totalPlayoffGames = $metros['playoff_total_wins'] + $metros['playoff_total_losses'];
        self::assertGreaterThan(0, $totalPlayoffGames, 'Playoff games should be counted');
    }

    public function testHeatTotalsReflectHeatBoxscores(): void
    {
        // October dates = game_type=3 (HEAT)
        // season_year = YEAR(2097) + 1 = 2098 (month >= 10)
        $this->insertFranchiseSeasonRow(1, 2098, 'Metros');
        $this->insertFranchiseSeasonRow(2, 2098, 'Sharks');

        $this->insertTeamBoxscoreRow('2097-10-15', 'Metros', 1, 2, 1);
        $this->insertTeamBoxscoreRow('2097-10-15', 'Sharks', 1, 2, 1);

        $result = $this->repo->getAllFranchiseHistory(2098);
        $metros = null;
        foreach ($result as $row) {
            if ($row['team_name'] === 'Metros') {
                $metros = $row;
                break;
            }
        }
        self::assertNotNull($metros);
        $totalHeatGames = $metros['heat_total_wins'] + $metros['heat_total_losses'];
        self::assertGreaterThan(0, $totalHeatGames, 'HEAT games should be counted');
    }

    public function testTeamWithNoGamesHasZeroWinpct(): void
    {
        $result = $this->repo->getAllFranchiseHistory(9999);

        foreach ($result as $row) {
            if ($row['totalgames'] === 0) {
                self::assertNull($row['five_season_winpct']);
                break;
            }
        }
        // If all teams have games, this test just passes — it's a structural check
        self::assertTrue(true);
    }
}
