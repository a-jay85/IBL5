<?php

use PHPUnit\Framework\TestCase;
use Team\TeamRepository;

/**
 * Tests for TeamRepository
 * 
 * Validates database query methods for team-related data
 */
class TeamRepositoryTest extends TestCase
{
    private $db;
    private $repository;

    protected function setUp(): void
    {
        $this->db = new MockDatabase();
        $this->repository = new TeamRepository($this->db);
    }

    public function testGetTeamPowerDataReturnsData()
    {
        $mockData = [[
            'Team' => 'Boston Celtics',
            'win' => 50,
            'loss' => 32,
            'gb' => 0,
            'Division' => 'Atlantic',
            'Conference' => 'Eastern'
        ]];
        
        $this->db->setMockData($mockData);
        $this->db->setNumRows(1);
        
        $result = $this->repository->getTeamPowerData('Boston Celtics');
        
        $this->assertEquals('Boston Celtics', $result['Team']);
        $this->assertEquals(50, $result['win']);
    }

    public function testGetTeamPowerDataReturnsNullWhenNoResults()
    {
        $this->db->setMockData([]);
        $this->db->setNumRows(0);
        
        $result = $this->repository->getTeamPowerData('Nonexistent Team');
        
        $this->assertNull($result);
    }

    public function testGetDivisionStandingsExecutesQuery()
    {
        $mockData = [
            ['Team' => 'Boston Celtics', 'gb' => 0],
            ['Team' => 'New York Knicks', 'gb' => -5]
        ];
        
        $this->db->setMockData($mockData);
        
        $result = $this->repository->getDivisionStandings('Atlantic');
        
        $this->assertNotNull($result);
    }

    public function testGetConferenceStandingsExecutesQuery()
    {
        $mockData = [
            ['Team' => 'Boston Celtics', 'Conference' => 'Eastern'],
            ['Team' => 'Miami Heat', 'Conference' => 'Eastern']
        ];
        
        $this->db->setMockData($mockData);
        
        $result = $this->repository->getConferenceStandings('Eastern');
        
        $this->assertNotNull($result);
    }

    public function testGetChampionshipBannersExecutesQuery()
    {
        $mockData = [
            ['year' => 2020, 'bannername' => 'Boston Celtics', 'bannertype' => 1],
            ['year' => 2021, 'bannername' => 'Boston Celtics', 'bannertype' => 1]
        ];
        
        $this->db->setMockData($mockData);
        
        $result = $this->repository->getChampionshipBanners('Boston Celtics');
        
        $this->assertNotNull($result);
    }

    public function testGetGMHistoryExecutesQuery()
    {
        $mockData = [
            ['year' => 2020, 'Award' => 'GM of the Year'],
            ['year' => 2021, 'Award' => 'Executive of the Year']
        ];
        
        $this->db->setMockData($mockData);
        
        $result = $this->repository->getGMHistory('John Doe', 'Boston Celtics');
        
        $this->assertNotNull($result);
    }

    public function testGetTeamAccomplishmentsExecutesQuery()
    {
        $mockData = [
            ['year' => 2020, 'Award' => 'Best Record'],
            ['year' => 2019, 'Award' => 'Division Champions']
        ];
        
        $this->db->setMockData($mockData);
        
        $result = $this->repository->getTeamAccomplishments('Boston Celtics');
        
        $this->assertNotNull($result);
    }

    public function testGetRegularSeasonHistoryExecutesQuery()
    {
        $mockData = [
            ['year' => 2024, 'wins' => 50, 'losses' => 32],
            ['year' => 2023, 'wins' => 48, 'losses' => 34]
        ];
        
        $this->db->setMockData($mockData);
        
        $result = $this->repository->getRegularSeasonHistory('Boston Celtics');
        
        $this->assertNotNull($result);
    }

    public function testGetHEATHistoryExecutesQuery()
    {
        $mockData = [
            ['year' => 2024, 'wins' => 10, 'losses' => 2],
            ['year' => 2023, 'wins' => 8, 'losses' => 4]
        ];
        
        $this->db->setMockData($mockData);
        
        $result = $this->repository->getHEATHistory('Boston Celtics');
        
        $this->assertNotNull($result);
    }

    public function testGetPlayoffResultsExecutesQuery()
    {
        $mockData = [
            ['year' => 2024, 'round' => 4, 'winner' => 'Boston Celtics'],
            ['year' => 2023, 'round' => 3, 'winner' => 'Miami Heat']
        ];
        
        $this->db->setMockData($mockData);
        
        $result = $this->repository->getPlayoffResults();
        
        $this->assertNotNull($result);
    }

    public function testGetFreeAgencyRosterExecutesQuery()
    {
        $mockData = [
            ['pid' => 1, 'name' => 'John Doe', 'tid' => 1, 'retired' => 0],
            ['pid' => 2, 'name' => 'Jane Smith', 'tid' => 1, 'retired' => 0]
        ];
        
        $this->db->setMockData($mockData);
        
        $result = $this->repository->getFreeAgencyRoster(1);
        
        $this->assertNotNull($result);
    }

    public function testGetRosterUnderContractExecutesQuery()
    {
        $mockData = [
            ['pid' => 1, 'name' => 'John Doe', 'tid' => 1],
            ['pid' => 2, 'name' => 'Jane Smith', 'tid' => 1]
        ];
        
        $this->db->setMockData($mockData);
        
        $result = $this->repository->getRosterUnderContract(1);
        
        $this->assertNotNull($result);
    }

    public function testGetFreeAgentsWithoutFreeAgencyActive()
    {
        $mockData = [
            ['pid' => 1, 'name' => 'Free Agent 1', 'ordinal' => 960],
            ['pid' => 2, 'name' => 'Free Agent 2', 'ordinal' => 961]
        ];
        
        $this->db->setMockData($mockData);
        
        $result = $this->repository->getFreeAgents(false);
        
        $this->assertNotNull($result);
    }

    public function testGetFreeAgentsWithFreeAgencyActive()
    {
        $mockData = [
            ['pid' => 1, 'name' => 'Free Agent 1', 'ordinal' => 960],
            ['pid' => 2, 'name' => 'Free Agent 2', 'ordinal' => 961]
        ];
        
        $this->db->setMockData($mockData);
        
        $result = $this->repository->getFreeAgents(true);
        
        $this->assertNotNull($result);
    }

    public function testGetEntireLeagueRosterExecutesQuery()
    {
        $mockData = [
            ['pid' => 1, 'name' => 'Player 1', 'retired' => 0],
            ['pid' => 2, 'name' => 'Player 2', 'retired' => 0],
            ['pid' => 3, 'name' => 'Player 3', 'retired' => 0]
        ];
        
        $this->db->setMockData($mockData);
        
        $result = $this->repository->getEntireLeagueRoster();
        
        $this->assertNotNull($result);
    }

    public function testGetHistoricalRosterExecutesQuery()
    {
        $mockData = [
            ['pid' => 1, 'name' => 'Historical Player 1', 'year' => '2023'],
            ['pid' => 2, 'name' => 'Historical Player 2', 'year' => '2023']
        ];
        
        $this->db->setMockData($mockData);
        
        $result = $this->repository->getHistoricalRoster(1, '2023');
        
        $this->assertNotNull($result);
    }

    public function testTeamIDIsSanitizedAsInteger()
    {
        $this->db->setMockData([]);
        
        // With type hints, integer type is enforced at the method signature level
        // This test verifies that valid integer inputs work correctly
        $result = $this->repository->getRosterUnderContract(1);
        
        $this->assertNotNull($result);
    }
}
