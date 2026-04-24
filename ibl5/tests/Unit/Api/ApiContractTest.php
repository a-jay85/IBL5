<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use Api\Transformer\BoxscoreTransformer;
use Api\Transformer\GameTransformer;
use Api\Transformer\InjuryTransformer;
use Api\Transformer\LeaderTransformer;
use Api\Transformer\PlayerStatsTransformer;
use Api\Transformer\PlayerTransformer;
use Api\Transformer\StandingsTransformer;
use Api\Transformer\TeamTransformer;
use PHPUnit\Framework\TestCase;

/**
 * API backward compatibility tests.
 *
 * Validates that each transformer produces output matching a committed schema.
 * Breaking changes (field removals, renames, type changes) fail this test.
 * Additive changes (new fields) pass — they're backward-compatible.
 *
 * To update a contract after an intentional breaking change:
 * 1. Update the corresponding SCHEMA constant in this file
 * 2. The PR diff makes the breaking change visible to reviewers
 */
class ApiContractTest extends TestCase
{
    // ================================================================
    // Contract schemas — field => expected PHP type(s)
    //
    // Allowed type strings: string, integer, double, boolean, array, NULL
    // Use pipe for union types: 'array|NULL'
    // ================================================================

    /** @var array<string, string> */
    private const PLAYER_LIST_SCHEMA = [
        'uuid' => 'string',
        'pid' => 'integer',
        'name' => 'string',
        'position' => 'string',
        'age' => 'integer',
        'height' => 'string',
        'experience' => 'integer',
        'team' => 'array|NULL',
        'contract' => 'array',
        'stats' => 'array',
    ];

    /** @var array<string, string> */
    private const PLAYER_DETAIL_SCHEMA = [
        'uuid' => 'string',
        'pid' => 'integer',
        'name' => 'string',
        'position' => 'string',
        'age' => 'integer',
        'height' => 'string',
        'experience' => 'integer',
        'team' => 'array|NULL',
        'contract' => 'array',
        'stats' => 'array',
        'bird_rights' => 'integer',
    ];

    /** @var array<string, string> */
    private const TEAM_NESTED_SCHEMA = [
        'uuid' => 'string',
        'city' => 'string',
        'name' => 'string',
        'full_name' => 'string',
        'team_id' => 'integer',
    ];

    /** @var array<string, string> */
    private const CONTRACT_SCHEMA = [
        'current_salary' => 'integer',
        'year1' => 'integer',
        'year2' => 'integer',
    ];

    /** @var array<string, string> */
    private const PLAYER_LIST_STATS_SCHEMA = [
        'games_played' => 'integer',
        'points_per_game' => 'string',
        'fg_percentage' => 'string',
        'ft_percentage' => 'string',
        'three_pt_percentage' => 'string',
    ];

    /** @var array<string, string> */
    private const TEAM_LIST_SCHEMA = [
        'uuid' => 'string',
        'city' => 'string',
        'name' => 'string',
        'full_name' => 'string',
        'team_id' => 'integer',
        'owner' => 'string',
        'owner_discord_id' => 'integer|NULL',
        'arena' => 'string',
        'conference' => 'string',
        'division' => 'string',
    ];

    /** @var array<string, string> */
    private const TEAM_DETAIL_SCHEMA = [
        'uuid' => 'string',
        'city' => 'string',
        'name' => 'string',
        'full_name' => 'string',
        'team_id' => 'integer',
        'owner' => 'string',
        'owner_discord_id' => 'integer|NULL',
        'arena' => 'string',
        'conference' => 'string',
        'division' => 'string',
        'record' => 'array',
        'standings' => 'array',
    ];

    /** @var array<string, string> */
    private const GAME_SCHEMA = [
        'uuid' => 'string',
        'season' => 'integer',
        'date' => 'string',
        'status' => 'string',
        'box_score_id' => 'integer|NULL',
        'game_of_that_day' => 'integer',
        'visitor' => 'array',
        'home' => 'array',
    ];

    /** @var array<string, string> */
    private const GAME_TEAM_SCHEMA = [
        'uuid' => 'string',
        'city' => 'string',
        'name' => 'string',
        'full_name' => 'string',
        'score' => 'integer|NULL',
        'team_id' => 'integer',
    ];

    /** @var array<string, string> */
    private const STANDINGS_SCHEMA = [
        'team' => 'array',
        'conference' => 'string',
        'division' => 'string',
        'record' => 'array',
        'win_percentage' => 'string',
        'games_back' => 'array',
        'games_remaining' => 'integer',
        'clinched' => 'array',
    ];

    /** @var array<string, string> */
    private const LEADER_SCHEMA = [
        'player' => 'array',
        'team' => 'array',
        'season' => 'integer',
        'stats' => 'array',
    ];

    /** @var array<string, string> */
    private const INJURY_SCHEMA = [
        'player' => 'array',
        'team' => 'array',
        'injury' => 'array',
    ];

    /** @var array<string, string> */
    private const BOXSCORE_PLAYER_SCHEMA = [
        'uuid' => 'string',
        'name' => 'string',
        'position' => 'string',
        'minutes' => 'integer',
        'two_pt_made' => 'integer',
        'two_pt_attempted' => 'integer',
        'ft_made' => 'integer',
        'ft_attempted' => 'integer',
        'three_pt_made' => 'integer',
        'three_pt_attempted' => 'integer',
        'fg_made' => 'integer',
        'fg_attempted' => 'integer',
        'offensive_rebounds' => 'integer',
        'defensive_rebounds' => 'integer',
        'rebounds' => 'integer',
        'assists' => 'integer',
        'steals' => 'integer',
        'turnovers' => 'integer',
        'blocks' => 'integer',
        'personal_fouls' => 'integer',
        'points' => 'integer',
    ];

    /** @var array<string, string> */
    private const BOXSCORE_TEAM_SCHEMA = [
        'name' => 'string',
        'quarter_scoring' => 'array',
        'totals' => 'array',
        'attendance' => 'integer|NULL',
        'capacity' => 'integer|NULL',
        'records' => 'array',
    ];

    /** @var array<string, string> */
    private const PLAYER_CAREER_SCHEMA = [
        'uuid' => 'string',
        'pid' => 'integer',
        'name' => 'string',
        'career_totals' => 'array',
        'career_averages' => 'array',
        'career_percentages' => 'array',
        'playoff_minutes' => 'integer',
        'draft' => 'array',
    ];

    /** @var array<string, string> */
    private const PLAYER_SEASON_SCHEMA = [
        'year' => 'integer',
        'pid' => 'integer',
        'player_name' => 'string',
        'team' => 'array',
        'games' => 'integer',
        'minutes' => 'integer',
        'stats' => 'array',
        'per_game' => 'array',
        'percentages' => 'array',
        'salary' => 'integer',
    ];

    // ================================================================
    // Contract assertion helper
    // ================================================================

    /**
     * Assert that $actual has all keys from $schema with matching types.
     * Extra keys in $actual are allowed (additive = non-breaking).
     *
     * @param array<string, string> $schema
     * @param array<string, mixed> $actual
     */
    private function assertMatchesContract(array $schema, array $actual, string $context): void
    {
        foreach ($schema as $field => $expectedType) {
            self::assertArrayHasKey($field, $actual, "{$context}: missing required field '{$field}'");

            $value = $actual[$field];
            $allowedTypes = explode('|', $expectedType);
            $actualType = $value === null ? 'NULL' : gettype($value);

            self::assertContains(
                $actualType,
                $allowedTypes,
                "{$context}.{$field}: expected {$expectedType}, got {$actualType}",
            );
        }
    }

    // ================================================================
    // Mock data builders
    // ================================================================

    /** @return array<string, mixed> */
    private function mockPlayerRow(): array
    {
        return [
            'player_uuid' => 'plr-uuid-test',
            'pid' => 1,
            'name' => 'Test Player',
            'position' => 'SG',
            'age' => 28,
            'htft' => 6,
            'htin' => 3,
            'experience' => 8,
            'teamid' => 1,
            'team_uuid' => 'team-uuid-test',
            'team_city' => 'Test City',
            'team_name' => 'Testers',
            'full_team_name' => 'Test City Testers',
            'current_salary' => 1500,
            'year1_salary' => 1600,
            'year2_salary' => 1700,
            'games_played' => 50,
            'points_per_game' => '22.5',
            'fg_percentage' => '.489',
            'ft_percentage' => '.856',
            'three_pt_percentage' => '.387',
            'bird_rights' => 1,
            'minutes_played' => 1600,
            'field_goals_made' => 400,
            'field_goals_attempted' => 818,
            'free_throws_made' => 200,
            'free_throws_attempted' => 234,
            'three_pointers_made' => 100,
            'three_pointers_attempted' => 258,
            'offensive_rebounds' => 30,
            'defensive_rebounds' => 150,
            'assists' => 200,
            'steals' => 50,
            'turnovers' => 100,
            'blocks' => 10,
            'personal_fouls' => 80,
        ];
    }

    /** @return array<string, mixed> */
    private function mockTeamListRow(): array
    {
        return [
            'uuid' => 'team-uuid-test',
            'team_city' => 'Test City',
            'team_name' => 'Testers',
            'teamid' => 1,
            'owner_name' => 'Test Owner',
            'discord_id' => 123456789,
            'arena' => 'Test Arena',
            'conference' => 'Eastern',
            'division' => 'Atlantic',
        ];
    }

    /** @return array<string, mixed> */
    private function mockTeamDetailRow(): array
    {
        return array_merge($this->mockTeamListRow(), [
            'league_record' => '45-10',
            'conference_record' => '25-5',
            'division_record' => '15-2',
            'home_wins' => 23,
            'home_losses' => 4,
            'away_wins' => 22,
            'away_losses' => 6,
            'win_percentage' => '.818',
            'conference_games_back' => '2.5',
            'division_games_back' => '0.0',
            'games_remaining' => 27,
        ]);
    }

    /** @return array<string, mixed> */
    private function mockGameRow(): array
    {
        return [
            'game_uuid' => 'game-uuid-test',
            'season_year' => 2026,
            'game_date' => '2026-03-07',
            'game_status' => 'final',
            'box_score_id' => 12345,
            'game_of_that_day' => 1,
            'visitor_uuid' => 'team-uuid-v',
            'visitor_city' => 'Visitor City',
            'visitor_name' => 'Visitors',
            'visitor_full_name' => 'Visitor City Visitors',
            'visitor_score' => 105,
            'visitor_team_id' => 1,
            'home_uuid' => 'team-uuid-h',
            'home_city' => 'Home City',
            'home_name' => 'Homers',
            'home_full_name' => 'Home City Homers',
            'home_score' => 98,
            'home_team_id' => 2,
        ];
    }

    /** @return array<string, mixed> */
    private function mockStandingsRow(): array
    {
        return [
            'team_uuid' => 'team-uuid-test',
            'team_city' => 'Test City',
            'team_name' => 'Testers',
            'full_team_name' => 'Test City Testers',
            'teamid' => 1,
            'conference' => 'Eastern',
            'division' => 'Atlantic',
            'league_record' => '45-10',
            'conference_record' => '25-5',
            'division_record' => '15-2',
            'home_record' => '23-4',
            'away_record' => '22-6',
            'win_percentage' => '.818',
            'conference_games_back' => '2.5',
            'division_games_back' => '0.0',
            'games_remaining' => 27,
            'clinched_conference' => 1,
            'clinched_division' => 1,
            'clinched_playoffs' => 1,
        ];
    }

    /** @return array<string, mixed> */
    private function mockLeaderRow(): array
    {
        return [
            'player_uuid' => 'plr-uuid-test',
            'pid' => 1,
            'name' => 'Test Player',
            'team_uuid' => 'team-uuid-test',
            'team_city' => 'Test City',
            'team_name' => 'Testers',
            'teamid' => 1,
            'year' => 2026,
            'games' => 50,
            'minutes' => 1600,
            'fgm' => 400,
            'fga' => 818,
            'ftm' => 200,
            'fta' => 234,
            'tgm' => 100,
            'tga' => 258,
            'reb' => 180,
            'ast' => 200,
            'stl' => 50,
            'blk' => 10,
            'tvr' => 100,
        ];
    }

    /** @return array<string, mixed> */
    private function mockInjuryRow(): array
    {
        return [
            'player_uuid' => 'plr-uuid-test',
            'pid' => 1,
            'name' => 'Test Player',
            'pos' => 'SG',
            'team_uuid' => 'team-uuid-test',
            'team_city' => 'Test City',
            'team_name' => 'Testers',
            'teamid' => 1,
            'injured' => 7,
        ];
    }

    /** @return array<string, mixed> */
    private function mockBoxscoreTeamRow(): array
    {
        return [
            'name' => 'Testers',
            'visitorQ1points' => 25, 'homeQ1points' => 28,
            'visitorQ2points' => 30, 'homeQ2points' => 26,
            'visitorQ3points' => 22, 'homeQ3points' => 24,
            'visitorQ4points' => 28, 'homeQ4points' => 20,
            'visitorOTpoints' => 0, 'homeOTpoints' => 0,
            'calc_fg_made' => 40,
            'game2GM' => 25, 'game2GA' => 50,
            'gameFTM' => 18, 'gameFTA' => 22,
            'game3GM' => 15, 'game3GA' => 35,
            'gameORB' => 8, 'gameDRB' => 35, 'calc_rebounds' => 43,
            'gameAST' => 28, 'gameSTL' => 6, 'gameTOV' => 12,
            'gameBLK' => 4, 'gamePF' => 18, 'calc_points' => 113,
            'attendance' => 19000,
            'capacity' => 20000,
            'visitorWins' => 45, 'visitorLosses' => 10,
            'homeWins' => 40, 'homeLosses' => 15,
        ];
    }

    /** @return array<string, mixed> */
    private function mockBoxscorePlayerRow(): array
    {
        return [
            'player_uuid' => 'plr-uuid-test',
            'name' => 'Test Player',
            'pos' => 'SG',
            'gameMIN' => 36,
            'game2GM' => 8, 'game2GA' => 15,
            'gameFTM' => 6, 'gameFTA' => 8,
            'game3GM' => 4, 'game3GA' => 10,
            'calc_fg_made' => 12,
            'gameORB' => 1, 'gameDRB' => 8, 'calc_rebounds' => 9,
            'gameAST' => 8, 'gameSTL' => 1, 'gameTOV' => 3,
            'gameBLK' => 1, 'gamePF' => 3, 'calc_points' => 30,
        ];
    }

    /** @return array<string, mixed> */
    private function mockCareerStatsRow(): array
    {
        return [
            'player_uuid' => 'plr-uuid-test',
            'pid' => 1,
            'name' => 'Test Player',
            'career_games' => 500,
            'career_minutes' => 16000,
            'career_points' => 10000,
            'career_rebounds' => 2500,
            'career_assists' => 3000,
            'career_steals' => 600,
            'career_blocks' => 150,
            'ppg_career' => '20.0',
            'rpg_career' => '5.0',
            'apg_career' => '6.0',
            'fg_pct_career' => '.488',
            'ft_pct_career' => '.851',
            'three_pt_pct_career' => '.381',
            'playoff_minutes' => 3400,
            'draft_year' => 2018,
            'draft_round' => 1,
            'draft_pick' => 5,
            'drafted_by_team' => 'Testers',
            'draft_team_id' => 1,
        ];
    }

    /** @return array<string, mixed> */
    private function mockSeasonHistoryRow(): array
    {
        return [
            'year' => 2025,
            'pid' => 1,
            'name' => 'Test Player',
            'team_uuid' => 'team-uuid-test',
            'team_city' => 'Test City',
            'team_name' => 'Testers',
            'team' => 'Testers',
            'teamid' => 1,
            'games' => 50,
            'minutes' => 1600,
            'reb' => 180,
            'orb' => 30,
            'ast' => 200,
            'stl' => 50,
            'blk' => 10,
            'tvr' => 100,
            'pf' => 80,
            'fgm' => 400,
            'fga' => 818,
            'ftm' => 200,
            'fta' => 234,
            'tgm' => 100,
            'tga' => 258,
            'salary' => 1500,
        ];
    }

    // ================================================================
    // Transformer contract tests
    // ================================================================

    public function testPlayerListContract(): void
    {
        $transformer = new PlayerTransformer();
        $result = $transformer->transform($this->mockPlayerRow());

        $this->assertMatchesContract(self::PLAYER_LIST_SCHEMA, $result, 'PlayerTransformer::transform');
        $this->assertMatchesContract(self::TEAM_NESTED_SCHEMA, $result['team'], 'PlayerTransformer::transform.team');
        $this->assertMatchesContract(self::CONTRACT_SCHEMA, $result['contract'], 'PlayerTransformer::transform.contract');
        $this->assertMatchesContract(self::PLAYER_LIST_STATS_SCHEMA, $result['stats'], 'PlayerTransformer::transform.stats');
    }

    public function testPlayerDetailContract(): void
    {
        $transformer = new PlayerTransformer();
        $result = $transformer->transformDetail($this->mockPlayerRow());

        $this->assertMatchesContract(self::PLAYER_DETAIL_SCHEMA, $result, 'PlayerTransformer::transformDetail');
    }

    public function testPlayerListFreeAgentHasNullTeam(): void
    {
        $transformer = new PlayerTransformer();
        $row = $this->mockPlayerRow();
        $row['teamid'] = null;
        $row['team_uuid'] = null;
        $result = $transformer->transform($row);

        $this->assertMatchesContract(self::PLAYER_LIST_SCHEMA, $result, 'PlayerTransformer::transform (free agent)');
        self::assertNull($result['team'], 'Free agent should have null team');
    }

    public function testTeamListContract(): void
    {
        $transformer = new TeamTransformer();
        $result = $transformer->transform($this->mockTeamListRow());

        $this->assertMatchesContract(self::TEAM_LIST_SCHEMA, $result, 'TeamTransformer::transform');
    }

    public function testTeamDetailContract(): void
    {
        $transformer = new TeamTransformer();
        $result = $transformer->transformDetail($this->mockTeamDetailRow());

        $this->assertMatchesContract(self::TEAM_DETAIL_SCHEMA, $result, 'TeamTransformer::transformDetail');
    }

    public function testGameContract(): void
    {
        $transformer = new GameTransformer();
        $result = $transformer->transform($this->mockGameRow());

        $this->assertMatchesContract(self::GAME_SCHEMA, $result, 'GameTransformer::transform');
        $this->assertMatchesContract(self::GAME_TEAM_SCHEMA, $result['visitor'], 'GameTransformer::transform.visitor');
        $this->assertMatchesContract(self::GAME_TEAM_SCHEMA, $result['home'], 'GameTransformer::transform.home');
    }

    public function testStandingsContract(): void
    {
        $transformer = new StandingsTransformer();
        $result = $transformer->transform($this->mockStandingsRow());

        $this->assertMatchesContract(self::STANDINGS_SCHEMA, $result, 'StandingsTransformer::transform');
    }

    public function testLeaderContract(): void
    {
        $transformer = new LeaderTransformer();
        $result = $transformer->transform($this->mockLeaderRow());

        $this->assertMatchesContract(self::LEADER_SCHEMA, $result, 'LeaderTransformer::transform');
    }

    public function testInjuryContract(): void
    {
        $transformer = new InjuryTransformer();
        $result = $transformer->transform($this->mockInjuryRow());

        $this->assertMatchesContract(self::INJURY_SCHEMA, $result, 'InjuryTransformer::transform');
    }

    public function testBoxscoreTeamContract(): void
    {
        $transformer = new BoxscoreTransformer();
        $result = $transformer->transformTeamStats($this->mockBoxscoreTeamRow());

        $this->assertMatchesContract(self::BOXSCORE_TEAM_SCHEMA, $result, 'BoxscoreTransformer::transformTeamStats');
    }

    public function testBoxscorePlayerContract(): void
    {
        $transformer = new BoxscoreTransformer();
        $result = $transformer->transformPlayerLine($this->mockBoxscorePlayerRow());

        $this->assertMatchesContract(self::BOXSCORE_PLAYER_SCHEMA, $result, 'BoxscoreTransformer::transformPlayerLine');
    }

    public function testPlayerCareerStatsContract(): void
    {
        $transformer = new PlayerStatsTransformer();
        $result = $transformer->transformCareer($this->mockCareerStatsRow());

        $this->assertMatchesContract(self::PLAYER_CAREER_SCHEMA, $result, 'PlayerStatsTransformer::transformCareer');
    }

    public function testPlayerSeasonHistoryContract(): void
    {
        $transformer = new PlayerStatsTransformer();
        $result = $transformer->transformSeason($this->mockSeasonHistoryRow());

        $this->assertMatchesContract(self::PLAYER_SEASON_SCHEMA, $result, 'PlayerStatsTransformer::transformSeason');
    }

    // ================================================================
    // Response envelope contract test
    // ================================================================

    public function testSuccessEnvelopeContract(): void
    {
        $responder = new \Api\Response\JsonResponder();

        // Capture output instead of sending headers
        ob_start();
        @$responder->success(['test' => true], ['extra' => 'meta']);
        $json = ob_get_clean();

        self::assertNotFalse($json);
        self::assertNotEmpty($json);

        $decoded = json_decode($json, true);
        self::assertIsArray($decoded);

        self::assertArrayHasKey('status', $decoded, 'Envelope missing status');
        self::assertSame('success', $decoded['status']);

        self::assertArrayHasKey('data', $decoded, 'Envelope missing data');
        self::assertArrayHasKey('meta', $decoded, 'Envelope missing meta');

        $meta = $decoded['meta'];
        self::assertArrayHasKey('timestamp', $meta, 'Meta missing timestamp');
        self::assertArrayHasKey('version', $meta, 'Meta missing version');
        self::assertSame('v1', $meta['version']);
    }

    public function testErrorEnvelopeContract(): void
    {
        $responder = new \Api\Response\JsonResponder();

        ob_start();
        @$responder->error(404, 'not_found', 'Resource not found');
        $json = ob_get_clean();

        self::assertNotFalse($json);
        $decoded = json_decode($json, true);
        self::assertIsArray($decoded);

        self::assertSame('error', $decoded['status']);
        self::assertArrayHasKey('error', $decoded, 'Error envelope missing error');
        self::assertArrayHasKey('code', $decoded['error'], 'Error missing code');
        self::assertArrayHasKey('message', $decoded['error'], 'Error missing message');
        self::assertArrayHasKey('meta', $decoded, 'Error envelope missing meta');
    }
}
