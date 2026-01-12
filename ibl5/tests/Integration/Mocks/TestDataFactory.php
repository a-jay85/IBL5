<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

/**
 * Factory for creating test data fixtures
 *
 * Provides standardized mock data for players, teams, and seasons
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
            'cy1' => '500',
            'cy2' => '550',
            'cy3' => '600',
            'cy4' => '0',
            'cy5' => '0',
            'cy6' => '0',
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
            'formerly_known_as' => null,
            // Rating fields (required by PlayerRepository)
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
            'offo' => 50,
            'offd' => 50,
            'offp' => 50,
            'offt' => 50,
            'defo' => 50,
            'defd' => 50,
            'defp' => 50,
            'deft' => 50,
            'Clutch' => 50,
            'Consistency' => 50,
            'int' => 50,
            'tal' => 50,
            'skl' => 50,
            'sta' => 50,
            // Additional rating fields required by PlayerRepository
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
}
