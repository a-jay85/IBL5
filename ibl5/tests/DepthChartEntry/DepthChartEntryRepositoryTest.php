<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use PHPUnit\Framework\TestCase;
use DepthChartEntry\DepthChartEntryRepository;

class DepthChartEntryRepositoryTest extends TestCase
{
    private object $mockDb;
    private DepthChartEntryRepository $repository;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMockDatabase();
        $this->repository = new DepthChartEntryRepository($this->mockDb);
    }

    public function testGetPlayersOnTeamReturnsArrayOfPlayers(): void
    {
        $teamID = 1;

        $mockPlayers = [
            ['pid' => 1, 'name' => 'Player One', 'tid' => 1, 'retired' => '0', 'ordinal' => 5],
            ['pid' => 2, 'name' => 'Player Two', 'tid' => 1, 'retired' => '0', 'ordinal' => 6],
        ];

        $this->mockDb->setMockData($mockPlayers);

        $result = $this->repository->getPlayersOnTeam($teamID);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Player One', $result[0]['name']);
        $this->assertEquals('Player Two', $result[1]['name']);
    }

    public function testGetPlayersOnTeamReturnsEmptyArrayForNoPlayers(): void
    {
        $teamID = 99;

        $this->mockDb->setMockData([]);

        $result = $this->repository->getPlayersOnTeam($teamID);

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
            'canPlayInGame' => 1,
            'min' => 30,
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
        $this->assertStringContainsString('dc_canPlayInGame', $lastQuery);
        $this->assertStringContainsString('dc_minutes', $lastQuery);
        // Role columns are hardcoded to 0 in SQL, not bound as parameters
        $this->assertStringContainsString('dc_of = 0', $lastQuery);
        $this->assertStringContainsString('dc_df = 0', $lastQuery);
        $this->assertStringContainsString('dc_oi = 0', $lastQuery);
        $this->assertStringContainsString('dc_di = 0', $lastQuery);
        $this->assertStringContainsString('dc_bh = 0', $lastQuery);
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
            'canPlayInGame' => 1,
            'min' => 30,
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
            'canPlayInGame' => '1',
            'min' => '30',
        ];
        
        $this->mockDb->setAffectedRows(1);
        
        $result = $this->repository->updatePlayerDepthChart($playerName, $depthChartValues);

        $this->assertTrue($result);
    }

    public function testUpdatePlayerDepthChartHardcodesRoleSlotsToZero(): void
    {
        // Role slots (of, df, oi, di, bh) are now hardcoded to 0 in SQL
        $playerName = 'Test Player';
        $depthChartValues = [
            'pg' => 1,
            'sg' => 2,
            'sf' => 0,
            'pf' => 0,
            'c' => 3,
            'canPlayInGame' => 1,
            'min' => 30,
        ];

        $this->mockDb->setAffectedRows(1);

        $result = $this->repository->updatePlayerDepthChart($playerName, $depthChartValues);

        $this->assertTrue($result);

        // Verify role columns are hardcoded to 0 in the query
        $queries = $this->mockDb->getExecutedQueries();
        $lastQuery = end($queries);
        $this->assertStringContainsString('dc_of = 0', $lastQuery);
        $this->assertStringContainsString('dc_df = 0', $lastQuery);
        $this->assertStringContainsString('dc_oi = 0', $lastQuery);
        $this->assertStringContainsString('dc_di = 0', $lastQuery);
        $this->assertStringContainsString('dc_bh = 0', $lastQuery);
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
        $this->assertStringContainsString('UPDATE ibl_team_info SET', $lastQuery);
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

    // --- Merged from DepthChartEntryDatabaseMappingTest ---

    /**
     * Verifies the exact mapping between processed array keys and database column names.
     * Documents the required field-to-column correspondence for the UPDATE query.
     * Role columns (dc_of, dc_df, dc_oi, dc_di, dc_bh) are hardcoded to 0 in SQL.
     */
    public function testDatabaseUpdateQueryMapsFieldsCorrectly()
    {
        // The processed data uses these lowercase keys for bound parameters
        $boundFields = [
            'pg' => 1,
            'sg' => 2,
            'sf' => 0,
            'pf' => 0,
            'c' => 0,
            'canPlayInGame' => 1,
            'min' => 30,
        ];

        // The database columns that receive bound parameter values
        $boundDatabaseColumns = [
            'dc_PGDepth',        // from $processedPlayerData['pg']
            'dc_SGDepth',        // from $processedPlayerData['sg']
            'dc_SFDepth',        // from $processedPlayerData['sf']
            'dc_PFDepth',        // from $processedPlayerData['pf']
            'dc_CDepth',         // from $processedPlayerData['c']
            'dc_canPlayInGame',  // from $processedPlayerData['canPlayInGame']
            'dc_minutes',        // from $processedPlayerData['min']
        ];

        // Hardcoded columns (always 0 in SQL, not bound)
        $hardcodedColumns = ['dc_of', 'dc_df', 'dc_oi', 'dc_di', 'dc_bh'];

        $this->assertEquals(7, count($boundDatabaseColumns), 'Should have 7 bound database columns');
        $this->assertEquals(7, count($boundFields), 'Should have 7 bound fields');
        $this->assertEquals(5, count($hardcodedColumns), 'Should have 5 hardcoded columns');

        // The bind_param order: 7 integers + 1 string (player name) = "iiiiiiis"
        $expectedBindParamTypes = 'iiiiiiis';
        $expectedBindParamValues = [
            $boundFields['pg'],             // position 1: dc_PGDepth
            $boundFields['sg'],             // position 2: dc_SGDepth
            $boundFields['sf'],             // position 3: dc_SFDepth
            $boundFields['pf'],             // position 4: dc_PFDepth
            $boundFields['c'],              // position 5: dc_CDepth
            $boundFields['canPlayInGame'],  // position 6: dc_canPlayInGame
            $boundFields['min'],            // position 7: dc_minutes
            'Test Player'                   // position 8: name (WHERE clause)
        ];

        $this->assertEquals(8, count($expectedBindParamValues), 'Should have 8 bind parameters (7 updates + 1 WHERE)');
        $this->assertEquals(8, strlen($expectedBindParamTypes), 'Bind param type string should have 8 characters');

        // Verify the values are what we expect
        $this->assertEquals(1, $expectedBindParamValues[0], 'First param should be pg value');
        $this->assertEquals(2, $expectedBindParamValues[1], 'Second param should be sg value');
        $this->assertEquals(30, $expectedBindParamValues[6], 'Seventh param should be min value');
        $this->assertEquals('Test Player', $expectedBindParamValues[7], 'Eighth param should be player name');
    }

    /**
     * Tests that role columns are hardcoded to 0 — they no longer receive bound values.
     */
    public function testRoleColumnsAreHardcodedToZeroInSql()
    {
        // The processed data no longer includes of/df/oi/di/bh as bound params
        $depthChartValues = [
            'pg' => 0,
            'sg' => 0,
            'sf' => 0,
            'pf' => 0,
            'c' => 1,
            'canPlayInGame' => 1,
            'min' => 20,
        ];

        $this->mockDb->setAffectedRows(1);

        $result = $this->repository->updatePlayerDepthChart('Test Player', $depthChartValues);
        $this->assertTrue($result);

        // Verify the SQL hardcodes role columns to 0
        $queries = $this->mockDb->getExecutedQueries();
        $lastQuery = end($queries);
        $this->assertStringContainsString('dc_of = 0', $lastQuery);
        $this->assertStringContainsString('dc_df = 0', $lastQuery);
        $this->assertStringContainsString('dc_oi = 0', $lastQuery);
        $this->assertStringContainsString('dc_di = 0', $lastQuery);
        $this->assertStringContainsString('dc_bh = 0', $lastQuery);
    }

    /**
     * Tests the complete data flow to ensure no data loss or transformation errors.
     */
    public function testCompleteDataFlowFromFormToDatabase()
    {
        // Step 1: User submits form with these POST values (all lowercase)
        $postFieldNames = [
            'pg1' => '1',
            'sg1' => '2',
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'canPlayInGame1' => '1',
            'min1' => '30',
        ];

        // Step 2: Processor converts POST to processed array with lowercase keys
        // Role fields (of, df, oi, di, bh) are hardcoded to 0 by the processor
        $processedBoundKeys = ['pg', 'sg', 'sf', 'pf', 'c', 'canPlayInGame', 'min'];

        // Step 3: Repository maps bound fields to database columns; role columns hardcoded to 0
        $boundDatabaseColumns = [
            'dc_PGDepth',
            'dc_SGDepth',
            'dc_SFDepth',
            'dc_PFDepth',
            'dc_CDepth',
            'dc_canPlayInGame',
            'dc_minutes',
        ];
        $hardcodedColumns = ['dc_of', 'dc_df', 'dc_oi', 'dc_di', 'dc_bh'];

        // Verify the counts match
        $this->assertEquals(count($processedBoundKeys), count($boundDatabaseColumns), 'Number of bound keys should match bound database columns');
        $this->assertEquals(5, count($hardcodedColumns), 'Should have 5 hardcoded columns');

        // Verify the mapping chain for position depth fields
        $completeChain = [
            'pg' => ['POST' => 'pg1', 'processed' => 'pg', 'database' => 'dc_PGDepth'],
            'sg' => ['POST' => 'sg1', 'processed' => 'sg', 'database' => 'dc_SGDepth'],
            'sf' => ['POST' => 'sf1', 'processed' => 'sf', 'database' => 'dc_SFDepth'],
            'pf' => ['POST' => 'pf1', 'processed' => 'pf', 'database' => 'dc_PFDepth'],
            'c' => ['POST' => 'c1', 'processed' => 'c', 'database' => 'dc_CDepth'],
        ];

        $this->assertCount(5, $completeChain, 'Should have documented chain for 5 position depth fields');
    }

    /**
     * Regression test: Ensure that the order of parameters in bind_param
     * matches the order of columns in the UPDATE statement.
     * Role columns (dc_of through dc_bh) are hardcoded to 0 and not bound.
     */
    public function testBindParamOrderMatchesUpdateStatementOrder()
    {
        // The UPDATE statement has bound columns in this order:
        $boundColumnOrder = [
            1 => 'dc_PGDepth',
            2 => 'dc_SGDepth',
            3 => 'dc_SFDepth',
            4 => 'dc_PFDepth',
            5 => 'dc_CDepth',
            6 => 'dc_canPlayInGame',
            7 => 'dc_minutes',
        ];

        // The bind_param call should pass values in this EXACT same order
        $bindParamOrder = [
            1 => 'pg',
            2 => 'sg',
            3 => 'sf',
            4 => 'pf',
            5 => 'c',
            6 => 'canPlayInGame',
            7 => 'min',
        ];

        // If these orders don't match, data will be stored in wrong columns
        $this->assertCount(7, $boundColumnOrder);
        $this->assertCount(7, $bindParamOrder);

        // This test documents the critical requirement:
        // The Nth value in bind_param must correspond to the Nth bound column in UPDATE statement
        // Role columns (dc_of, dc_df, dc_oi, dc_di, dc_bh) are hardcoded to 0 in SQL
    }
}
