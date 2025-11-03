<?php

use PHPUnit\Framework\TestCase;
use Services\CommonRepository;

class CommonRepositoryTest extends TestCase
{
    private $mockDb;
    private $repository;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->repository = new CommonRepository($this->mockDb);
    }

    // User lookup tests
    
    public function testGetUserByUsernameReturnsUserData()
    {
        $username = "testuser";
        $userData = ['username' => 'testuser', 'user_id' => 1, 'user_ibl_team' => 'Test Team'];
        
        $this->mockDb->setMockData([$userData]);
        $this->mockDb->setNumRows(1);
        
        $result = $this->repository->getUserByUsername($username);
        
        $this->assertIsArray($result);
        $this->assertEquals($userData['username'], $result['username']);
        $this->assertEquals($userData['user_ibl_team'], $result['user_ibl_team']);
    }

    public function testGetUserByUsernameReturnsNullWhenNotFound()
    {
        $this->mockDb->setNumRows(0);
        
        $result = $this->repository->getUserByUsername("nonexistent");
        
        $this->assertNull($result);
    }

    public function testGetTeamnameFromUsernameReturnsTeamName()
    {
        $username = "testuser";
        $teamName = "Test Team";
        
        $this->mockDb->setMockData([['user_ibl_team' => $teamName]]);
        $this->mockDb->setNumRows(1);
        
        $result = $this->repository->getTeamnameFromUsername($username);
        
        $this->assertEquals($teamName, $result);
    }

    public function testGetTeamnameFromUsernameReturnsFreeAgentsForEmptyUsername()
    {
        $result = $this->repository->getTeamnameFromUsername("");
        
        $this->assertEquals("Free Agents", $result);
    }

    // Team lookup tests
    
    public function testGetTeamByNameReturnsTeamData()
    {
        $teamName = "Test Team";
        $teamData = ['teamid' => 1, 'team_name' => 'Test Team', 'team_city' => 'Test City'];
        
        $this->mockDb->setMockData([$teamData]);
        $this->mockDb->setNumRows(1);
        
        $result = $this->repository->getTeamByName($teamName);
        
        $this->assertIsArray($result);
        $this->assertEquals($teamData['teamid'], $result['teamid']);
        $this->assertEquals($teamData['team_name'], $result['team_name']);
    }

    public function testGetTeamByNameReturnsNullWhenNotFound()
    {
        $this->mockDb->setNumRows(0);
        
        $result = $this->repository->getTeamByName("Nonexistent Team");
        
        $this->assertNull($result);
    }

    public function testGetTidFromTeamnameReturnsTeamID()
    {
        $teamName = "Test Team";
        $teamID = 5;
        
        $this->mockDb->setMockData([['teamid' => $teamID]]);
        $this->mockDb->setNumRows(1);
        
        $result = $this->repository->getTidFromTeamname($teamName);
        
        $this->assertEquals($teamID, $result);
    }

    public function testGetTidFromTeamnameReturnsNullWhenNotFound()
    {
        $this->mockDb->setNumRows(0);
        
        $result = $this->repository->getTidFromTeamname("Nonexistent Team");
        
        $this->assertNull($result);
    }

    public function testGetTeamnameFromTeamIDReturnsTeamName()
    {
        $teamID = 5;
        $teamName = "Test Team";
        
        $this->mockDb->setMockData([['team_name' => $teamName]]);
        $this->mockDb->setNumRows(1);
        
        $result = $this->repository->getTeamnameFromTeamID($teamID);
        
        $this->assertEquals($teamName, $result);
    }

    public function testGetTeamnameFromTeamIDReturnsNullWhenNotFound()
    {
        $this->mockDb->setNumRows(0);
        
        $result = $this->repository->getTeamnameFromTeamID(999);
        
        $this->assertNull($result);
    }

    public function testGetTeamDiscordIDReturnsDiscordID()
    {
        $teamName = "Test Team";
        $discordID = "123456789";
        
        $this->mockDb->setMockData([['discordID' => $discordID]]);
        $this->mockDb->setNumRows(1);
        
        $result = $this->repository->getTeamDiscordID($teamName);
        
        $this->assertEquals($discordID, $result);
    }

    public function testGetTeamDiscordIDReturnsNullWhenNotFound()
    {
        $this->mockDb->setNumRows(0);
        
        $result = $this->repository->getTeamDiscordID("Nonexistent Team");
        
        $this->assertNull($result);
    }

    // Player lookup tests
    
    public function testGetPlayerByIDReturnsPlayerData()
    {
        $playerID = 123;
        $playerData = ['pid' => 123, 'name' => 'Test Player', 'pos' => 'PG'];
        
        $this->mockDb->setMockData([$playerData]);
        $this->mockDb->setNumRows(1);
        
        $result = $this->repository->getPlayerByID($playerID);
        
        $this->assertIsArray($result);
        $this->assertEquals($playerData['pid'], $result['pid']);
        $this->assertEquals($playerData['name'], $result['name']);
    }

    public function testGetPlayerByIDReturnsNullWhenNotFound()
    {
        $this->mockDb->setNumRows(0);
        
        $result = $this->repository->getPlayerByID(999);
        
        $this->assertNull($result);
    }

    public function testGetPlayerIDFromPlayerNameReturnsPlayerID()
    {
        $playerName = "Test Player";
        $playerID = 123;
        
        $this->mockDb->setMockData([['pid' => $playerID]]);
        $this->mockDb->setNumRows(1);
        
        $result = $this->repository->getPlayerIDFromPlayerName($playerName);
        
        $this->assertEquals($playerID, $result);
    }

    public function testGetPlayerIDFromPlayerNameReturnsNullWhenNotFound()
    {
        $this->mockDb->setNumRows(0);
        
        $result = $this->repository->getPlayerIDFromPlayerName("Nonexistent Player");
        
        $this->assertNull($result);
    }

    public function testGetPlayerByNameReturnsPlayerData()
    {
        $playerName = "Test Player";
        $playerData = ['pid' => 123, 'name' => 'Test Player', 'pos' => 'PG'];
        
        $this->mockDb->setMockData([$playerData]);
        $this->mockDb->setNumRows(1);
        
        $result = $this->repository->getPlayerByName($playerName);
        
        $this->assertIsArray($result);
        $this->assertEquals($playerData['pid'], $result['pid']);
        $this->assertEquals($playerData['name'], $result['name']);
    }

    public function testGetPlayerByNameReturnsNullWhenNotFound()
    {
        $this->mockDb->setNumRows(0);
        
        $result = $this->repository->getPlayerByName("Nonexistent Player");
        
        $this->assertNull($result);
    }

    // Salary calculation tests
    
    public function testGetTeamTotalSalaryCalculatesCorrectly()
    {
        $teamName = "Test Team";
        
        $this->mockDb->setMockData([
            ['cy' => 1, 'cy1' => 5000],
            ['cy' => 2, 'cy2' => 10000],
            ['cy' => 1, 'cy1' => 8000]
        ]);
        $this->mockDb->setNumRows(3);
        
        $result = $this->repository->getTeamTotalSalary($teamName);
        
        $this->assertEquals(23000, $result);
    }

    public function testGetTeamTotalSalaryReturnsZeroForEmptyTeam()
    {
        $this->mockDb->setNumRows(0);
        
        $result = $this->repository->getTeamTotalSalary("Empty Team");
        
        $this->assertEquals(0, $result);
    }

    public function testGetTeamTotalSalaryHandlesMissingContractYearField()
    {
        $this->mockDb->setMockData([
            ['cy' => 1, 'cy1' => 5000],
            ['cy' => 7] // Missing cy7 field
        ]);
        $this->mockDb->setNumRows(2);
        
        $result = $this->repository->getTeamTotalSalary("Test Team");
        
        $this->assertEquals(5000, $result);
    }
}
