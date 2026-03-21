<?php

declare(strict_types=1);

namespace Tests\LeagueStarters;

use LeagueStarters\Contracts\LeagueStartersRepositoryInterface;
use LeagueStarters\LeagueStartersService;
use PHPUnit\Framework\TestCase;
use League\League;
use Tests\Integration\Mocks\TestDataFactory;

class LeagueStartersServiceTest extends TestCase
{
    private \MockDatabase $mockDb;
    private object $mockMysqliDb;
    private League $mockLeague;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->setupMockMysqliDb();
        $this->mockLeague = new League($this->mockMysqliDb);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    private function setupMockMysqliDb(): void
    {
        $mockDb = $this->mockDb;

        $this->mockMysqliDb = new class ($mockDb) extends \mysqli {
            private \MockDatabase $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct(\MockDatabase $mockDb)
            {
                $this->mockDb = $mockDb;
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $query): \MockPreparedStatement|false
            {
                return new \MockPreparedStatement($this->mockDb, $query);
            }

            #[\ReturnTypeWillChange]
            public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): \mysqli_result|bool
            {
                return false;
            }

            public function real_escape_string(string $string): string
            {
                return addslashes($string);
            }
        };

        $GLOBALS['mysqli_db'] = $this->mockMysqliDb;
    }

    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    public function testServiceCanBeInstantiated(): void
    {
        $service = new LeagueStartersService($this->mockMysqliDb, $this->mockLeague);

        $this->assertInstanceOf(LeagueStartersService::class, $service);
    }

    public function testServiceImplementsCorrectInterface(): void
    {
        $service = new LeagueStartersService($this->mockMysqliDb, $this->mockLeague);

        $this->assertInstanceOf(
            \LeagueStarters\Contracts\LeagueStartersServiceInterface::class,
            $service
        );
    }

    // ============================================
    // GET ALL STARTERS BY POSITION TESTS
    // ============================================

    public function testGetAllStartersByPositionReturnsEmptyPositionsWhenNoTeams(): void
    {
        $service = new LeagueStartersService($this->mockMysqliDb, $this->mockLeague);

        $result = $service->getAllStartersByPosition();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('PG', $result);
        $this->assertArrayHasKey('SG', $result);
        $this->assertArrayHasKey('SF', $result);
        $this->assertArrayHasKey('PF', $result);
        $this->assertArrayHasKey('C', $result);
    }

    public function testGetAllStartersByPositionReturnsEmptyArraysForEachPosition(): void
    {
        $service = new LeagueStartersService($this->mockMysqliDb, $this->mockLeague);

        $result = $service->getAllStartersByPosition();

        $this->assertEmpty($result['PG']);
        $this->assertEmpty($result['SG']);
        $this->assertEmpty($result['SF']);
        $this->assertEmpty($result['PF']);
        $this->assertEmpty($result['C']);
    }

    // ============================================
    // BATCH LOAD TESTS
    // ============================================

    public function testBatchLoadReturnsCorrectPositionBuckets(): void
    {
        $mockRepo = $this->createStub(LeagueStartersRepositoryInterface::class);
        $mockRepo->method('getAllStartersWithTeamData')->willReturn([
            $this->makeStarterRow(101, 1, 'PG', 'Test Team'),
            $this->makeStarterRow(102, 1, 'SG', 'Test Team'),
            $this->makeStarterRow(103, 1, 'SF', 'Test Team'),
            $this->makeStarterRow(104, 1, 'PF', 'Test Team'),
            $this->makeStarterRow(105, 1, 'C', 'Test Team'),
        ]);

        $this->mockDb->onQuery('SELECT[\s\S]*ibl_team_info[\s\S]*teamid BETWEEN', [
            $this->makeTeamRow(1, 'Test Team', 'Test City'),
        ]);

        $service = new LeagueStartersService($this->mockMysqliDb, $this->mockLeague, $mockRepo);
        $result = $service->getAllStartersByPosition();

        $this->assertCount(1, $result['PG']);
        $this->assertCount(1, $result['SG']);
        $this->assertCount(1, $result['SF']);
        $this->assertCount(1, $result['PF']);
        $this->assertCount(1, $result['C']);

        $this->assertSame(101, $result['PG'][0]->playerID);
        $this->assertSame(102, $result['SG'][0]->playerID);
        $this->assertSame(105, $result['C'][0]->playerID);
    }

    public function testMissingSlotsUsePlaceholder(): void
    {
        $mockRepo = $this->createStub(LeagueStartersRepositoryInterface::class);
        $mockRepo->method('getAllStartersWithTeamData')->willReturn([
            $this->makeStarterRow(101, 1, 'PG', 'Test Team'),
        ]);

        $this->mockDb->onQuery('SELECT[\s\S]*ibl_team_info[\s\S]*teamid BETWEEN', [
            $this->makeTeamRow(1, 'Test Team', 'Test City'),
        ]);

        $this->mockDb->onQuery('SELECT[\s\S]*ibl_plr[\s\S]*pid', [
            TestDataFactory::createPlayer(['pid' => 4040404, 'tid' => 0, 'name' => 'Placeholder', 'loyalty' => 0, 'playingTime' => 0, 'winner' => 0, 'tradition' => 0, 'security' => 0]),
        ]);

        $service = new LeagueStartersService($this->mockMysqliDb, $this->mockLeague, $mockRepo);
        $result = $service->getAllStartersByPosition();

        $this->assertSame(101, $result['PG'][0]->playerID);
        $this->assertSame(4040404, $result['SG'][0]->playerID);
        $this->assertSame(4040404, $result['SF'][0]->playerID);
        $this->assertSame(4040404, $result['PF'][0]->playerID);
        $this->assertSame(4040404, $result['C'][0]->playerID);
    }

    public function testPlaceholderSetsTeamProperties(): void
    {
        $mockRepo = $this->createStub(LeagueStartersRepositoryInterface::class);
        $mockRepo->method('getAllStartersWithTeamData')->willReturn([]);

        $this->mockDb->onQuery('SELECT[\s\S]*ibl_team_info[\s\S]*teamid BETWEEN', [
            $this->makeTeamRow(1, 'Test Team', 'Test City'),
        ]);

        $this->mockDb->onQuery('SELECT[\s\S]*ibl_plr[\s\S]*pid', [
            TestDataFactory::createPlayer(['pid' => 4040404, 'tid' => 0, 'name' => 'Placeholder', 'loyalty' => 0, 'playingTime' => 0, 'winner' => 0, 'tradition' => 0, 'security' => 0]),
        ]);

        $service = new LeagueStartersService($this->mockMysqliDb, $this->mockLeague, $mockRepo);
        $result = $service->getAllStartersByPosition();

        $pgPlayer = $result['PG'][0];
        $this->assertSame('Test Team', $pgPlayer->teamName);
        $this->assertSame('Test City', $pgPlayer->teamCity);
        $this->assertSame('#000000', $pgPlayer->teamColor1);
        $this->assertSame('#FFFFFF', $pgPlayer->teamColor2);
    }

    public function testPlaceholderLoadedOnlyOnce(): void
    {
        $mockRepo = $this->createStub(LeagueStartersRepositoryInterface::class);
        $mockRepo->method('getAllStartersWithTeamData')->willReturn([]);

        $this->mockDb->onQuery('SELECT[\s\S]*ibl_team_info[\s\S]*teamid BETWEEN', [
            $this->makeTeamRow(1, 'Team A', 'City A'),
            $this->makeTeamRow(2, 'Team B', 'City B'),
        ]);

        $this->mockDb->onQuery('SELECT[\s\S]*ibl_plr[\s\S]*pid', [
            TestDataFactory::createPlayer(['pid' => 4040404, 'tid' => 0, 'name' => 'Placeholder', 'loyalty' => 0, 'playingTime' => 0, 'winner' => 0, 'tradition' => 0, 'security' => 0]),
        ]);

        $service = new LeagueStartersService($this->mockMysqliDb, $this->mockLeague, $mockRepo);
        $result = $service->getAllStartersByPosition();

        $this->assertCount(2, $result['PG']);
        $this->assertCount(2, $result['SG']);

        $this->assertSame('Team A', $result['PG'][0]->teamName);
        $this->assertSame('Team B', $result['PG'][1]->teamName);
        $this->assertNotSame($result['PG'][0], $result['PG'][1]);
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleServicesCanBeInstantiated(): void
    {
        $service1 = new LeagueStartersService($this->mockMysqliDb, $this->mockLeague);
        $service2 = new LeagueStartersService($this->mockMysqliDb, $this->mockLeague);

        $this->assertNotSame($service1, $service2);
    }

    // ============================================
    // HELPERS
    // ============================================

    /**
     * @return array<string, mixed>
     */
    private function makeStarterRow(int $pid, int $tid, string $position, string $teamname): array
    {
        $row = TestDataFactory::createPlayer([
            'pid' => $pid,
            'tid' => $tid,
            'teamname' => $teamname,
            'color1' => '#000000',
            'color2' => '#FFFFFF',
            'loyalty' => 3,
            'playingTime' => 3,
            'winner' => 3,
            'tradition' => 3,
            'security' => 3,
        ]);
        $row[$position . 'Depth'] = 1;
        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function makeTeamRow(int $teamid, string $teamName, string $city): array
    {
        return TestDataFactory::createTeam([
            'teamid' => $teamid,
            'team_name' => $teamName,
            'team_city' => $city,
            'color1' => '#000000',
            'color2' => '#FFFFFF',
            'Used_Extension_This_Chunk' => 0,
            'Used_Extension_This_Season' => 0,
            'leagueRecord' => '0-0',
        ]);
    }
}
