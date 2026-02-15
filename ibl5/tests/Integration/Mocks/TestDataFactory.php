<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

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
            'teamname' => 'Test Team',
            'tid' => 1,
            'position' => 'G',
            'pos' => 'G',
            'age' => 25,
            'ordinal' => 1,
            'cy' => 1,
            'cy1' => 500,
            'cy2' => 550,
            'cy3' => 600,
            'cy4' => 0,
            'cy5' => 0,
            'cy6' => 0,
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
            'do' => 50,
            'dd' => 50,
            'po' => 50,
            'pd' => 50,
            'to' => 50,
            'td' => 50,
            'talent' => 50,
            'skill' => 50,
            'intangibles' => 50,
            'r_fga' => 50,
            'r_fgp' => 50,
            'r_fta' => 50,
            'r_ftp' => 50,
            'r_tga' => 50,
            'r_tgp' => 50,
            'r_orb' => 50,
            'r_drb' => 50,
            'r_ast' => 50,
            'r_stl' => 50,
            'r_to' => 50,
            'r_blk' => 50,
            'r_foul' => 50,
            'Clutch' => 50,
            'Consistency' => 50,
            'intangibles' => 50,
            'talent' => 50,
            'skill' => 50,
            'sta' => 50,
            'Used_Extension_This_Season' => 0,
            'Used_Extension_This_Chunk' => 0,
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
            'HasMLE' => 1,
            'HasLLE' => 1,
            'color1' => 'FF0000',
            'color2' => '000000',
            'owner_email' => 'test@example.com',
            'owner_name' => 'Test Owner',
            'team_city' => 'Test City',
            'discordID' => '123456789',
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
            'sta' => 50,
            'talent' => 50,
            'skill' => 50,
            'intangibles' => 50,
            'fga' => 50,
            'fgp' => 50,
            'fta' => 50,
            'ftp' => 50,
            'tga' => 50,
            'tgp' => 50,
            'orb' => 50,
            'drb' => 50,
            'ast' => 50,
            'stl' => 50,
            'tvr' => 50,
            'blk' => 50,
            'oo' => 50,
            'do' => 50,
            'po' => 50,
            'to' => 50,
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
            'itemtype' => '1', // '1' = player, '0' = pick, 'cash' = cash
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
            'MLE' => 0,
            'LLE' => 0,
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
