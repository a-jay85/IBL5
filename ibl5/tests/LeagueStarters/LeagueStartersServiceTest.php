<?php

declare(strict_types=1);

namespace Tests\LeagueStarters;

use LeagueStarters\Contracts\LeagueStartersRepositoryInterface;
use LeagueStarters\LeagueStartersService;
use PHPUnit\Framework\TestCase;
use League\League;
use Tests\WideUnit\Mocks\TestDataFactory;
use Tests\WideUnit\Mocks\MockDatabase;

class LeagueStartersServiceTest extends TestCase
{
    private MockDatabase $mockDb;
    private League $mockLeague;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $GLOBALS['mysqli_db'] = $this->mockDb;
        $this->mockLeague = new League($this->mockDb);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    // ============================================
    // GET ALL STARTERS BY POSITION TESTS
    // ============================================

    public function testGetAllStartersByPositionReturnsEmptyPositionsWhenNoTeams(): void
    {
        $service = new LeagueStartersService($this->mockDb, $this->mockLeague);

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
        $service = new LeagueStartersService($this->mockDb, $this->mockLeague);

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
        $mockRepo = self::createStub(LeagueStartersRepositoryInterface::class);
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

        $service = new LeagueStartersService($this->mockDb, $this->mockLeague, $mockRepo);
        $result = $service->getAllStartersByPosition();

        $this->assertCount(1, $result['PG']);
        $this->assertCount(1, $result['SG']);
        $this->assertCount(1, $result['SF']);
        $this->assertCount(1, $result['PF']);
        $this->assertCount(1, $result['C']);

        $this->assertSame(101, $result['PG'][0]->getPlayerID());
        $this->assertSame(102, $result['SG'][0]->getPlayerID());
        $this->assertSame(105, $result['C'][0]->getPlayerID());
    }

    public function testMissingSlotsUsePlaceholder(): void
    {
        $mockRepo = self::createStub(LeagueStartersRepositoryInterface::class);
        $mockRepo->method('getAllStartersWithTeamData')->willReturn([
            $this->makeStarterRow(101, 1, 'PG', 'Test Team'),
        ]);
        $mockRepo->method('getPlaceholderRow')->willReturn(
            TestDataFactory::createPlayer(['pid' => 4040404, 'teamid' => 0, 'name' => 'Placeholder', 'loyalty' => 0, 'playing_time' => 0, 'winner' => 0, 'tradition' => 0, 'security' => 0])
        );

        $this->mockDb->onQuery('SELECT[\s\S]*ibl_team_info[\s\S]*teamid BETWEEN', [
            $this->makeTeamRow(1, 'Test Team', 'Test City'),
        ]);

        $service = new LeagueStartersService($this->mockDb, $this->mockLeague, $mockRepo);
        $result = $service->getAllStartersByPosition();

        $this->assertSame(101, $result['PG'][0]->getPlayerID());
        $this->assertSame(4040404, $result['SG'][0]->getPlayerID());
        $this->assertSame(4040404, $result['SF'][0]->getPlayerID());
        $this->assertSame(4040404, $result['PF'][0]->getPlayerID());
        $this->assertSame(4040404, $result['C'][0]->getPlayerID());
    }

    public function testPlaceholderSetsTeamProperties(): void
    {
        $mockRepo = self::createStub(LeagueStartersRepositoryInterface::class);
        $mockRepo->method('getAllStartersWithTeamData')->willReturn([]);
        $mockRepo->method('getPlaceholderRow')->willReturn(
            TestDataFactory::createPlayer(['pid' => 4040404, 'teamid' => 0, 'name' => 'Placeholder', 'loyalty' => 0, 'playing_time' => 0, 'winner' => 0, 'tradition' => 0, 'security' => 0])
        );

        $this->mockDb->onQuery('SELECT[\s\S]*ibl_team_info[\s\S]*teamid BETWEEN', [
            $this->makeTeamRow(1, 'Test Team', 'Test City'),
        ]);

        $service = new LeagueStartersService($this->mockDb, $this->mockLeague, $mockRepo);
        $result = $service->getAllStartersByPosition();

        $pgPlayer = $result['PG'][0];
        $this->assertSame('Test Team', $pgPlayer->getTeamName());
        $this->assertSame('#000000', $pgPlayer->getTeamColor1());
        $this->assertSame('#FFFFFF', $pgPlayer->getTeamColor2());
    }

    public function testPlaceholderLoadedOnlyOnce(): void
    {
        $mockRepo = self::createStub(LeagueStartersRepositoryInterface::class);
        $mockRepo->method('getAllStartersWithTeamData')->willReturn([]);
        $mockRepo->method('getPlaceholderRow')->willReturn(
            TestDataFactory::createPlayer(['pid' => 4040404, 'teamid' => 0, 'name' => 'Placeholder', 'loyalty' => 0, 'playing_time' => 0, 'winner' => 0, 'tradition' => 0, 'security' => 0])
        );

        $this->mockDb->onQuery('SELECT[\s\S]*ibl_team_info[\s\S]*teamid BETWEEN', [
            $this->makeTeamRow(1, 'Team A', 'City A'),
            $this->makeTeamRow(2, 'Team B', 'City B'),
        ]);

        $service = new LeagueStartersService($this->mockDb, $this->mockLeague, $mockRepo);
        $result = $service->getAllStartersByPosition();

        $this->assertCount(2, $result['PG']);
        $this->assertCount(2, $result['SG']);

        $this->assertSame('Team A', $result['PG'][0]->getTeamName());
        $this->assertSame('Team B', $result['PG'][1]->getTeamName());
        $this->assertNotSame($result['PG'][0], $result['PG'][1]);
    }

    public function testNonIntTeamIdRowIsSkipped(): void
    {
        $mockRepo = self::createStub(LeagueStartersRepositoryInterface::class);

        $row = $this->makeStarterRow(101, 1, 'PG', 'Test Team');
        $row['teamid'] = '1';
        $mockRepo->method('getAllStartersWithTeamData')->willReturn([$row]);
        $mockRepo->method('getPlaceholderRow')->willReturn(
            TestDataFactory::createPlayer(['pid' => 4040404, 'teamid' => 0, 'name' => 'Placeholder', 'loyalty' => 0, 'playing_time' => 0, 'winner' => 0, 'tradition' => 0, 'security' => 0])
        );

        $this->mockDb->onQuery('SELECT[\s\S]*ibl_team_info[\s\S]*teamid BETWEEN', [
            $this->makeTeamRow(1, 'Test Team', 'Test City'),
        ]);

        $service = new LeagueStartersService($this->mockDb, $this->mockLeague, $mockRepo);
        $result = $service->getAllStartersByPosition();

        $this->assertSame(4040404, $result['PG'][0]->getPlayerID());
    }

    public function testFirstStarterWinsPerTeamPosition(): void
    {
        $mockRepo = self::createStub(LeagueStartersRepositoryInterface::class);
        $mockRepo->method('getAllStartersWithTeamData')->willReturn([
            $this->makeStarterRow(101, 1, 'PG', 'Test Team'),
            $this->makeStarterRow(102, 1, 'PG', 'Test Team'),
        ]);
        $mockRepo->method('getPlaceholderRow')->willReturn(
            TestDataFactory::createPlayer(['pid' => 4040404, 'teamid' => 0, 'name' => 'Placeholder', 'loyalty' => 0, 'playing_time' => 0, 'winner' => 0, 'tradition' => 0, 'security' => 0])
        );

        $this->mockDb->onQuery('SELECT[\s\S]*ibl_team_info[\s\S]*teamid BETWEEN', [
            $this->makeTeamRow(1, 'Test Team', 'Test City'),
        ]);

        $service = new LeagueStartersService($this->mockDb, $this->mockLeague, $mockRepo);
        $result = $service->getAllStartersByPosition();

        $this->assertSame(101, $result['PG'][0]->getPlayerID());
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleServicesCanBeInstantiated(): void
    {
        $service1 = new LeagueStartersService($this->mockDb, $this->mockLeague);
        $service2 = new LeagueStartersService($this->mockDb, $this->mockLeague);

        $this->assertNotSame($service1, $service2);
    }

    // ============================================
    // HELPERS
    // ============================================

    /**
     * @return array<string, mixed>
     */
    private function makeStarterRow(int $pid, int $teamid, string $position, string $teamname): array
    {
        $row = TestDataFactory::createPlayer([
            'pid' => $pid,
            'teamid' => $teamid,
            'teamname' => $teamname,
            'color1' => '#000000',
            'color2' => '#FFFFFF',
            'loyalty' => 3,
            'playing_time' => 3,
            'winner' => 3,
            'tradition' => 3,
            'security' => 3,
        ]);
        $row[strtolower($position) . '_depth'] = 1;
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
            'used_extension_this_chunk' => 0,
            'used_extension_this_season' => 0,
            'league_record' => '0-0',
        ]);
    }
}
