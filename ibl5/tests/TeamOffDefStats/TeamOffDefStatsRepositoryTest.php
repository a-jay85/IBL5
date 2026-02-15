<?php

declare(strict_types=1);

namespace Tests\TeamOffDefStats;

use PHPUnit\Framework\TestCase;
use TeamOffDefStats\TeamOffDefStatsRepository;
use TeamOffDefStats\Contracts\TeamOffDefStatsRepositoryInterface;

/**
 * Tests for TeamOffDefStatsRepository
 *
 * Verifies bulk query functionality for fetching all team statistics
 * in a single database query.
 */
class TeamOffDefStatsRepositoryTest extends TestCase
{
    /**
     * Test that repository implements the interface
     */
    public function testImplementsInterface(): void
    {
        $mockDb = $this->createMockDatabase([]);
        $repository = new TeamOffDefStatsRepository($mockDb);

        $this->assertInstanceOf(TeamOffDefStatsRepositoryInterface::class, $repository);
    }

    /**
     * Test getAllTeamStats returns array
     */
    public function testGetAllTeamStatsReturnsArray(): void
    {
        $mockDb = $this->createMockDatabase([]);
        $repository = new TeamOffDefStatsRepository($mockDb);

        $result = $repository->getAllTeamStats(2025);

        $this->assertIsArray($result);
    }

    /**
     * Test getAllTeamStats returns expected structure
     */
    public function testGetAllTeamStatsReturnsExpectedStructure(): void
    {
        $testData = [
            $this->createTeamStatsRow(1, 'Boston', 'Celtics', '#007A33', '#FFFFFF'),
            $this->createTeamStatsRow(2, 'Los Angeles', 'Lakers', '#552583', '#FDB927'),
        ];

        $mockDb = $this->createMockDatabase($testData);
        $repository = new TeamOffDefStatsRepository($mockDb);

        $result = $repository->getAllTeamStats(2025);

        $this->assertCount(2, $result);

        // Verify first team has expected keys
        $firstTeam = $result[0];
        $this->assertArrayHasKey('teamid', $firstTeam);
        $this->assertArrayHasKey('team_city', $firstTeam);
        $this->assertArrayHasKey('team_name', $firstTeam);
        $this->assertArrayHasKey('color1', $firstTeam);
        $this->assertArrayHasKey('color2', $firstTeam);
        $this->assertArrayHasKey('offense_games', $firstTeam);
        $this->assertArrayHasKey('offense_fgm', $firstTeam);
        $this->assertArrayHasKey('offense_pts', $firstTeam);
        $this->assertArrayHasKey('defense_games', $firstTeam);
        $this->assertArrayHasKey('defense_fgm', $firstTeam);
    }

    /**
     * Test that empty result set is handled correctly
     */
    public function testEmptyResultSet(): void
    {
        $mockDb = $this->createMockDatabase([]);
        $repository = new TeamOffDefStatsRepository($mockDb);

        $result = $repository->getAllTeamStats(2025);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test that null stats are handled (team with no games played)
     */
    public function testNullStatsHandling(): void
    {
        $testData = [
            [
                'teamid' => 1,
                'team_city' => 'New',
                'team_name' => 'Team',
                'color1' => '#000000',
                'color2' => '#FFFFFF',
                'offense_games' => null,
                'offense_fgm' => null,
                'offense_fga' => null,
                'offense_ftm' => null,
                'offense_fta' => null,
                'offense_tgm' => null,
                'offense_tga' => null,
                'offense_orb' => null,
                'offense_reb' => null,
                'offense_ast' => null,
                'offense_stl' => null,
                'offense_tvr' => null,
                'offense_blk' => null,
                'offense_pf' => null,
                'offense_pts' => null,
                'defense_games' => null,
                'defense_fgm' => null,
                'defense_fga' => null,
                'defense_ftm' => null,
                'defense_fta' => null,
                'defense_tgm' => null,
                'defense_tga' => null,
                'defense_orb' => null,
                'defense_reb' => null,
                'defense_ast' => null,
                'defense_stl' => null,
                'defense_tvr' => null,
                'defense_blk' => null,
                'defense_pf' => null,
                'defense_pts' => null,
            ],
        ];

        $mockDb = $this->createMockDatabase($testData);
        $repository = new TeamOffDefStatsRepository($mockDb);

        $result = $repository->getAllTeamStats(2025);

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['offense_games']);
    }

    /**
     * Create a mock database object that returns the specified data
     *
     * @param array $data Data to return from query
     * @return object Mock database
     */
    private function createMockDatabase(array $data): \mysqli
    {
        // Create wrapper object that mimics the database wrapper
        return new class ($data) extends \mysqli {
            private array $data;

            public function __construct(array $data)
            {
                // Don't call parent::__construct() to avoid real DB connection
                $this->data = $data;
            }

            #[\ReturnTypeWillChange]
            public function query(string $sql, int $resultMode = MYSQLI_STORE_RESULT): \mysqli_result|bool
            {
                return false;
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $sql): \mysqli_stmt|false
            {
                $data = $this->data;
                return new class ($data) extends \mysqli_stmt {
                    private array $data;

                    public function __construct(array $data)
                    {
                        // Don't call parent::__construct()
                        $this->data = $data;
                    }

                    public function bind_param(string $types, mixed &...$vars): bool
                    {
                        return true;
                    }

                    public function execute(?array $params = null): bool
                    {
                        return true;
                    }

                    public function get_result(): \mysqli_result|false
                    {
                        $data = $this->data;
                        return new class ($data) extends \mysqli_result {
                            private array $data;
                            private int $index = 0;

                            public function __construct(array $data)
                            {
                                // Don't call parent::__construct()
                                $this->data = $data;
                            }

                            public function fetch_assoc(): array|null|false
                            {
                                if ($this->index < count($this->data)) {
                                    return $this->data[$this->index++];
                                }
                                return null;
                            }

                            public function fetch_all(int $mode = MYSQLI_ASSOC): array
                            {
                                return $this->data;
                            }
                        };
                    }

                    public function close(): true
                    {
                        return true;
                    }
                };
            }
        };
    }

    /**
     * Create a test team stats row
     *
     * @param int $teamId Team ID
     * @param string $city City name
     * @param string $name Team name
     * @param string $color1 Primary color
     * @param string $color2 Secondary color
     * @return array Team stats row
     */
    private function createTeamStatsRow(
        int $teamId,
        string $city,
        string $name,
        string $color1,
        string $color2
    ): array {
        return [
            'teamid' => $teamId,
            'team_city' => $city,
            'team_name' => $name,
            'color1' => $color1,
            'color2' => $color2,
            'offense_games' => 82,
            'offense_fgm' => 3200,
            'offense_fga' => 7000,
            'offense_ftm' => 1500,
            'offense_fta' => 2000,
            'offense_tgm' => 1000,
            'offense_tga' => 2800,
            'offense_orb' => 900,
            'offense_reb' => 3600,
            'offense_ast' => 2000,
            'offense_stl' => 600,
            'offense_tvr' => 1200,
            'offense_blk' => 400,
            'offense_pf' => 1700,
            'offense_pts' => 8900,
            'defense_games' => 82,
            'defense_fgm' => 3100,
            'defense_fga' => 6900,
            'defense_ftm' => 1400,
            'defense_fta' => 1900,
            'defense_tgm' => 950,
            'defense_tga' => 2700,
            'defense_orb' => 850,
            'defense_reb' => 3500,
            'defense_ast' => 1900,
            'defense_stl' => 580,
            'defense_tvr' => 1250,
            'defense_blk' => 380,
            'defense_pf' => 1650,
            'defense_pts' => 8600,
        ];
    }
}
