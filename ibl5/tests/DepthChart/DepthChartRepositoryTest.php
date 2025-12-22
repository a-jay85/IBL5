<?php

declare(strict_types=1);

namespace Tests\DepthChart;

use PHPUnit\Framework\TestCase;
use DepthChart\DepthChartRepository;

class DepthChartRepositoryTest extends TestCase
{
    private object $mockDb;
    private DepthChartRepository $repository;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMockDatabase();
        $this->repository = new DepthChartRepository($this->mockDb);
    }

    public function testGetPlayersOnTeamReturnsArrayOfPlayers(): void
    {
        $teamName = 'Test Team';
        $teamID = 1;
        
        $mockPlayers = [
            ['pid' => 1, 'name' => 'Player One', 'teamname' => 'Test Team', 'tid' => 1, 'retired' => '0', 'ordinal' => 5],
            ['pid' => 2, 'name' => 'Player Two', 'teamname' => 'Test Team', 'tid' => 1, 'retired' => '0', 'ordinal' => 6],
        ];
        
        $this->mockDb->setMockData($mockPlayers);
        
        $result = $this->repository->getPlayersOnTeam($teamName, $teamID);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Player One', $result[0]['name']);
        $this->assertEquals('Player Two', $result[1]['name']);
    }

    public function testGetPlayersOnTeamReturnsEmptyArrayForNoPlayers(): void
    {
        $teamName = 'Empty Team';
        $teamID = 99;
        
        $this->mockDb->setMockData([]);
        
        $result = $this->repository->getPlayersOnTeam($teamName, $teamID);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testUpdatePlayerDepthChartSuccessfullyUpdatesAllFields(): void
    {
        $playerName = 'Test Player';
        $depthChartValues = [
            'pg' => 1,
            'sg' => 0,
            'sf' => 2,
            'pf' => 0,
            'c' => 0,
            'active' => 1,
            'min' => 30,
            'of' => 2,
            'df' => 1,
            'oi' => 1,
            'di' => -1,
            'bh' => 0,
        ];
        
        // Set affected rows to 1 to simulate successful update
        $this->mockDb->setAffectedRows(1);
        
        $result = $this->repository->updatePlayerDepthChart($playerName, $depthChartValues);

        $this->assertTrue($result);
        
        // Verify the query was executed
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertNotEmpty($queries);
        
        // Verify the UPDATE statement contains all the expected fields
        $lastQuery = end($queries);
        $this->assertStringContainsString('UPDATE ibl_plr SET', $lastQuery);
        $this->assertStringContainsString('dc_PGDepth', $lastQuery);
        $this->assertStringContainsString('dc_SGDepth', $lastQuery);
        $this->assertStringContainsString('dc_SFDepth', $lastQuery);
        $this->assertStringContainsString('dc_PFDepth', $lastQuery);
        $this->assertStringContainsString('dc_CDepth', $lastQuery);
        $this->assertStringContainsString('dc_active', $lastQuery);
        $this->assertStringContainsString('dc_minutes', $lastQuery);
        $this->assertStringContainsString('dc_of', $lastQuery);
        $this->assertStringContainsString('dc_df', $lastQuery);
        $this->assertStringContainsString('dc_oi', $lastQuery);
        $this->assertStringContainsString('dc_di', $lastQuery);
        $this->assertStringContainsString('dc_bh', $lastQuery);
        $this->assertStringContainsString("WHERE name = '$playerName'", $lastQuery);
    }

    public function testUpdatePlayerDepthChartSucceedsEvenWhenNoRowsAffected(): void
    {
        // This is the key test for the bug fix - when values don't change,
        // MySQL returns 0 affected rows, but this should still be considered success
        $playerName = 'Test Player';
        $depthChartValues = [
            'pg' => 1,
            'sg' => 0,
            'sf' => 0,
            'pf' => 0,
            'c' => 0,
            'active' => 1,
            'min' => 30,
            'of' => 0,
            'df' => 0,
            'oi' => 0,
            'di' => 0,
            'bh' => 0,
        ];
        
        // Set affected rows to 0 to simulate no change (values already match)
        $this->mockDb->setAffectedRows(0);
        
        $result = $this->repository->updatePlayerDepthChart($playerName, $depthChartValues);

        // This should return true because 0 affected rows means the player exists
        // but the values didn't change, which is not an error
        $this->assertTrue($result);
    }

    public function testUpdatePlayerDepthChartHandlesStringNumbers(): void
    {
        // Test that the method properly converts string numbers to integers
        $playerName = 'Test Player';
        $depthChartValues = [
            'pg' => '1',  // String instead of int
            'sg' => '0',
            'sf' => '2',
            'pf' => '0',
            'c' => '0',
            'active' => '1',
            'min' => '30',
            'of' => '2',
            'df' => '1',
            'oi' => '1',
            'di' => '-1',
            'bh' => '0',
        ];
        
        $this->mockDb->setAffectedRows(1);
        
        $result = $this->repository->updatePlayerDepthChart($playerName, $depthChartValues);

        $this->assertTrue($result);
    }

    public function testUpdatePlayerDepthChartHandlesNegativeValues(): void
    {
        // Test that negative values for oi, di, bh are handled correctly
        $playerName = 'Test Player';
        $depthChartValues = [
            'pg' => 0,
            'sg' => 0,
            'sf' => 0,
            'pf' => 0,
            'c' => 0,
            'active' => 1,
            'min' => 30,
            'of' => 0,
            'df' => 0,
            'oi' => -2,  // Negative value
            'di' => -2,  // Negative value
            'bh' => -2,  // Negative value
        ];
        
        $this->mockDb->setAffectedRows(1);
        
        $result = $this->repository->updatePlayerDepthChart($playerName, $depthChartValues);

        $this->assertTrue($result);
        
        // Verify negative values are in the query
        $queries = $this->mockDb->getExecutedQueries();
        $lastQuery = end($queries);
        $this->assertStringContainsString('-2', $lastQuery);
    }

    public function testUpdateTeamHistorySuccessfullyUpdatesTimestamps(): void
    {
        $teamName = 'Test Team';
        
        // Set affected rows to 1 to simulate successful update
        $this->mockDb->setAffectedRows(1);
        
        $result = $this->repository->updateTeamHistory($teamName);

        $this->assertTrue($result);
        
        // Verify the query was executed
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertNotEmpty($queries);
        
        // Verify the UPDATE statement contains both timestamp fields
        $lastQuery = end($queries);
        $this->assertStringContainsString('UPDATE ibl_team_history SET', $lastQuery);
        $this->assertStringContainsString('depth = NOW()', $lastQuery);
        $this->assertStringContainsString('sim_depth = NOW()', $lastQuery);
        $this->assertStringContainsString("WHERE team_name = '$teamName'", $lastQuery);
    }

    public function testUpdateTeamHistorySucceedsEvenWhenNoRowsAffected(): void
    {
        // This is another key test for the bug fix - when NOW() equals the existing timestamp,
        // MySQL returns 0 affected rows, but this should still be considered success
        $teamName = 'Test Team';
        
        // Set affected rows to 0 to simulate no change (timestamps already match NOW())
        $this->mockDb->setAffectedRows(0);
        
        $result = $this->repository->updateTeamHistory($teamName);

        // This should return true because 0 affected rows doesn't mean an error
        $this->assertTrue($result);
    }

    public function testUpdateTeamHistoryHandlesSpecialCharacters(): void
    {
        // Test that team names with special characters are properly escaped
        $teamName = "Team O'Brien";
        
        $this->mockDb->setAffectedRows(1);
        
        $result = $this->repository->updateTeamHistory($teamName);

        $this->assertTrue($result);
        
        // Verify the team name was escaped in the query
        $queries = $this->mockDb->getExecutedQueries();
        $lastQuery = end($queries);
        // The mock database uses addslashes for escaping
        $this->assertStringContainsString("Team O\\'Brien", $lastQuery);
    }

    private function createMockDatabase(): object
    {
        // Use the centralized MockDatabase that supports both legacy and mysqli interfaces
        return new \MockDatabase();
    }
}
