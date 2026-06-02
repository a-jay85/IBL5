<?php

declare(strict_types=1);

namespace Tests\WideUnit\Mocks;

/**
 * Factory for creating test data fixtures
 *
 * Provides standardized mock data for players, teams, seasons,
 * draft picks, trade offers, and free agency offers
 * used across integration tests.
 */
class TestDataFactory
{
    /**
     * Create mock player data with optional overrides
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function createPlayer(array $overrides = []): array
    {
        $defaults = [
            'pid' => 1,
            'name' => 'Test Player',
            'firstname' => 'Test',
            'lastname' => 'Player',
            'teamid' => 1,
            'position' => 'G',
            'pos' => 'G',
            'age' => 25,
            'ordinal' => 1,
            'cy' => 1,
            'salary_yr1' => 500,
            'salary_yr2' => 550,
            'salary_yr3' => 600,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'cyt' => 3,
            'ty' => 4,
            'c1' => 500,
            'c2' => 550,
            'c3' => 600,
            'c4' => 650,
            'c5' => 0,
            'c6' => 0,
            'exp' => 3,
            'bird_years' => 2,
            'bird' => 2,
            'retired' => 0,
            'injured' => 0,
            'droptime' => 0,
            'htft' => 6,
            'htin' => 3,
            'wt' => 200,
            'draftyear' => 2020,
            'draftround' => 1,
            'draftpickno' => 15,
            // Rating fields (required by PlayerRepository)
            // ibl_plr table uses these field names
            'oo' => 50,
            'od' => 50,
            'r_drive_off' => 50,
            'dd' => 50,
            'po' => 50,
            'pd' => 50,
            'r_trans_off' => 50,
            'td' => 50,
            'talent' => 50,
            'skill' => 50,
            'intangibles' => 50,
            'r_fga' => 50,
            'r_fgp' => 50,
            'r_fta' => 50,
            'r_ftp' => 50,
            'r_3ga' => 50,
            'r_3gp' => 50,
            'r_orb' => 50,
            'r_drb' => 50,
            'r_ast' => 50,
            'r_stl' => 50,
            'r_tvr' => 50,
            'r_blk' => 50,
            'r_foul' => 50,
            'clutch' => 50,
            'consistency' => 50,
            'stamina' => 50,
            'used_extension_this_season' => 0,
            'used_extension_this_chunk' => 0,
            // Free-agency rating fields (read by PlayerRepository::mapFreeAgencyFields)
            'loyalty' => 50,
            'playing_time' => 50,
            'winner' => 50,
            'tradition' => 50,
            'security' => 50,
            'nickname' => '',
            // ibl_team_info JOIN fields (color1/color2 above are on player rows but
            // production queries also LEFT JOIN ibl_team_info; provide both shapes)
            'teamname' => 'Test Team',
            'color1' => 'FFFFFF',
            'color2' => '000000',
            // Current-season stats (ibl_plr stats_* columns, default 0 in DB)
            'stats_gm' => 0,
            'stats_gs' => 0,
            'stats_min' => 0,
            'stats_fgm' => 0,
            'stats_fga' => 0,
            'stats_ftm' => 0,
            'stats_fta' => 0,
            'stats_3gm' => 0,
            'stats_3ga' => 0,
            'stats_orb' => 0,
            'stats_drb' => 0,
            'stats_ast' => 0,
            'stats_stl' => 0,
            'stats_tvr' => 0,
            'stats_blk' => 0,
            'stats_pf' => 0,
            // Career stats (ibl_plr car_* columns, default 0 in DB)
            'car_gm' => 0,
            'car_min' => 0,
            'car_fgm' => 0,
            'car_fga' => 0,
            'car_ftm' => 0,
            'car_fta' => 0,
            'car_3gm' => 0,
            'car_3ga' => 0,
            'car_orb' => 0,
            'car_drb' => 0,
            'car_reb' => 0,
            'car_ast' => 0,
            'car_stl' => 0,
            'car_tvr' => 0,
            'car_blk' => 0,
            'car_pf' => 0,
            'car_pts' => 0,
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create mock team data with optional overrides
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function createTeam(array $overrides = []): array
    {
        $defaults = [
            'teamid' => 1,
            'team_name' => 'Test Team',
            'Salary_Total' => 5000,
            'Salary_Cap' => 8250,
            'Tax_Line' => 10000,
            'Apron' => 11500,
            'Hard_Cap' => 12000,
            'has_mle' => 1,
            'has_lle' => 1,
            'color1' => 'FF0000',
            'color2' => '000000',
            'owner_email' => 'test@example.com',
            'owner_name' => 'Test Owner',
            'team_city' => 'Test City',
            'discord_id' => '123456789',
            'arena' => 'Test Arena',
            'capacity' => 20000,
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create mock season data with optional overrides
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function createSeason(array $overrides = []): array
    {
        $defaults = [
            'Phase' => 'Regular Season',
            'Beginning_Year' => 2024,
            'Ending_Year' => 2025,
            'Allow_Trades' => 'Yes',
            'Allow_Waivers' => 'Yes',
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create mock draft pick data with optional overrides
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function createDraftPick(array $overrides = []): array
    {
        $defaults = [
            'draft_id' => 1,
            'year' => 2025,
            'team' => 'Test Team',
            'player' => '',
            'round' => 1,
            'pick' => 1,
            'date' => null,
            'uuid' => 'test-draft-uuid-001',
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create mock draft class prospect data with optional overrides
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function createDraftClassProspect(array $overrides = []): array
    {
        $defaults = [
            'id' => 1,
            'name' => 'Test Prospect',
            'pos' => 'PG',
            'age' => 19,
            'team' => '',
            'drafted' => 0,
            'ranking' => 1.0,
            'invite' => '',
            'stamina' => 50,
            'talent' => 50,
            'skill' => 50,
            'intangibles' => 50,
            'fga' => 50,
            'fgp' => 50,
            'fta' => 50,
            'ftp' => 50,
            'r_3ga' => 50,
            'r_3gp' => 50,
            'orb' => 50,
            'drb' => 50,
            'ast' => 50,
            'stl' => 50,
            'tvr' => 50,
            'blk' => 50,
            'oo' => 50,
            'r_drive_off' => 50,
            'po' => 50,
            'r_trans_off' => 50,
            'od' => 50,
            'dd' => 50,
            'pd' => 50,
            'td' => 50,
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create mock trade offer data with optional overrides
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function createTradeOffer(array $overrides = []): array
    {
        $defaults = [
            'id' => 1,
            'created_at' => '2025-01-15 12:00:00',
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create mock trade item data with optional overrides
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function createTradeItem(array $overrides = []): array
    {
        $defaults = [
            'id' => 1,
            'tradeofferid' => 1,
            'itemid' => 100,
            'itemtype' => \Trading\TradeItemType::Player->value,
            'from' => 'Test Team',
            'to' => 'Other Team',
            'approval' => 'pending',
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create mock free agency offer data with optional overrides
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function createFreeAgentOffer(array $overrides = []): array
    {
        $defaults = [
            'primary_key' => 1,
            'name' => 'Test Player',
            'team' => 'Test Team',
            'offer1' => 500,
            'offer2' => 550,
            'offer3' => 600,
            'offer4' => 0,
            'offer5' => 0,
            'offer6' => 0,
            'modifier' => 1.0,
            'random' => 0.0,
            'perceivedvalue' => 550.0,
            'mle' => 0,
            'lle' => 0,
            'offer_type' => 0,
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Wrap items with a 'total' field for paginated controller tests.
     *
     * MockDatabase returns the same data for all queries, so controllers
     * that call both countX() and getX() need each row to include a 'total'
     * field so the COUNT(*) query works correctly.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public static function createPaginatedData(array $items, int $total): array
    {
        return array_map(
            static fn(array $item): array => array_merge($item, ['total' => $total]),
            $items
        );
    }
}
