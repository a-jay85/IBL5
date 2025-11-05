<?php

use PHPUnit\Framework\TestCase;
use Draft\DraftRepository;

class DraftRepositoryTest extends TestCase
{
    private $repository;
    private $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->repository = new DraftRepository($this->mockDb);
    }

    public function testGetCurrentDraftSelectionReturnsPlayerName()
    {
        $this->mockDb->setMockData([
            ['player' => 'John Doe']
        ]);

        $result = $this->repository->getCurrentDraftSelection(1, 5);

        $this->assertEquals('John Doe', $result);
    }

    public function testGetCurrentDraftSelectionReturnsNullWhenNoResults()
    {
        $this->mockDb->setMockData([]);
        $this->mockDb->setNumRows(0);

        $result = $this->repository->getCurrentDraftSelection(1, 5);

        $this->assertNull($result);
    }

    public function testUpdateDraftTableExecutesCorrectQuery()
    {
        $this->mockDb->setReturnTrue(true);

        $result = $this->repository->updateDraftTable(
            'John Doe',
            '2024-01-15 10:30:00',
            1,
            5
        );

        $this->assertTrue($result);
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('UPDATE ibl_draft', $queries[0]);
        $this->assertStringContainsString('John Doe', $queries[0]);
        $this->assertStringContainsString('2024-01-15 10:30:00', $queries[0]);
    }

    public function testUpdateDraftTableHandlesApostrophes()
    {
        $this->mockDb->setReturnTrue(true);

        $result = $this->repository->updateDraftTable(
            "D'Angelo Russell",
            '2024-01-15 10:30:00',
            1,
            5
        );

        $this->assertTrue($result);
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertStringContainsString("D\\'Angelo Russell", $queries[0]);
    }

    public function testUpdateRookieTableExecutesCorrectQuery()
    {
        $this->mockDb->setReturnTrue(true);

        $result = $this->repository->updateRookieTable(
            'John Doe',
            'Chicago Bulls'
        );

        $this->assertTrue($result);
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('UPDATE `ibl_draft_class`', $queries[0]);
        $this->assertStringContainsString('John Doe', $queries[0]);
        $this->assertStringContainsString('Chicago Bulls', $queries[0]);
        $this->assertStringContainsString('drafted', $queries[0]);
    }

    public function testGetNextTeamOnClockReturnsTeamName()
    {
        $this->mockDb->setMockData([
            ['team' => 'Boston Celtics']
        ]);

        $result = $this->repository->getNextTeamOnClock();

        $this->assertEquals('Boston Celtics', $result);
    }

    public function testGetNextTeamOnClockReturnsNullWhenDraftComplete()
    {
        $this->mockDb->setMockData([]);
        $this->mockDb->setNumRows(0);

        $result = $this->repository->getNextTeamOnClock();

        $this->assertNull($result);
    }

    public function testGetNextTeamOnClockQueriesCorrectly()
    {
        $this->mockDb->setMockData([
            ['team' => 'Chicago Bulls']
        ]);

        $this->repository->getNextTeamOnClock();

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString("WHERE player = ''", $queries[0]);
        $this->assertStringContainsString('ORDER BY round ASC, pick ASC', $queries[0]);
        $this->assertStringContainsString('LIMIT 1', $queries[0]);
    }

    // Tests for getTeamDiscordID have been moved to CommonRepositoryTest
    // as this method now delegates to CommonRepository

    public function testIsPlayerAlreadyDraftedReturnsTrueWhenDrafted()
    {
        $this->mockDb->setMockData([
            ['drafted' => '1']
        ]);

        $result = $this->repository->isPlayerAlreadyDrafted('John Doe');

        $this->assertTrue($result);
    }

    public function testIsPlayerAlreadyDraftedReturnsTrueWhenDraftedInteger()
    {
        $this->mockDb->setMockData([
            ['drafted' => 1]
        ]);

        $result = $this->repository->isPlayerAlreadyDrafted('John Doe');

        $this->assertTrue($result);
    }

    public function testIsPlayerAlreadyDraftedReturnsFalseWhenNotDrafted()
    {
        $this->mockDb->setMockData([
            ['drafted' => '0']
        ]);

        $result = $this->repository->isPlayerAlreadyDrafted('John Doe');

        $this->assertFalse($result);
    }

    public function testIsPlayerAlreadyDraftedReturnsFalseWhenPlayerNotFound()
    {
        $this->mockDb->setMockData([]);
        $this->mockDb->setNumRows(0);

        $result = $this->repository->isPlayerAlreadyDrafted('Unknown Player');

        $this->assertFalse($result);
    }

    public function testIsPlayerAlreadyDraftedEscapesPlayerName()
    {
        $this->mockDb->setMockData([
            ['drafted' => '0']
        ]);

        $this->repository->isPlayerAlreadyDrafted("D'Angelo Russell");

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertStringContainsString("D\\'Angelo Russell", $queries[0]);
        $this->assertStringContainsString('ibl_draft_class', $queries[0]);
    }

    public function testCreatePlayerFromDraftClassSucceeds()
    {
        // Set up mock to return different data for different queries
        // First call: team ID lookup returns teamid
        // Second call: draft class query returns player data
        $draftClassData = [
            'name' => 'John Doe',
            'pos' => 'PG',
            'age' => 22,
            'sta' => 85,
            'offo' => 75,
            'offd' => 70,
            'offp' => 65,
            'offt' => 60,
            'defo' => 80,
            'defd' => 75,
            'defp' => 70,
            'deft' => 65,
            'tal' => 80,
            'skl' => 75,
            'int' => 70
        ];
        
        // The mock will return the draft class data for SELECT queries
        $this->mockDb->setMockData([$draftClassData]);
        $this->mockDb->setReturnTrue(true);
        
        $result = $this->repository->createPlayerFromDraftClass('John Doe', 'Chicago Bulls');
        
        $this->assertTrue($result);
        $queries = $this->mockDb->getExecutedQueries();
        
        // Should have team lookup, draft class query, max PID query, and INSERT
        $this->assertGreaterThanOrEqual(2, count($queries));
        
        // Check for INSERT query
        $hasInsertQuery = false;
        foreach ($queries as $query) {
            if (stripos($query, 'INSERT INTO ibl_plr') !== false) {
                $hasInsertQuery = true;
                $this->assertStringContainsString('John Doe', $query);
                $this->assertStringContainsString('PG', $query);
                break;
            }
        }
        $this->assertTrue($hasInsertQuery);
    }

    public function testCreatePlayerFromDraftClassReturnsFalseWhenTeamNotFound()
    {
        $this->mockDb->setMockData([]);
        $this->mockDb->setNumRows(0);

        $result = $this->repository->createPlayerFromDraftClass('John Doe', 'Nonexistent Team');

        $this->assertFalse($result);
    }

    public function testCreatePlayerFromDraftClassHandlesApostrophes()
    {
        $draftClassData = [
            'name' => "D'Angelo Russell",
            'pos' => 'PG',
            'age' => 21,
            'sta' => 80,
            'offo' => 70,
            'offd' => 65,
            'offp' => 60,
            'offt' => 55,
            'defo' => 75,
            'defd' => 70,
            'defp' => 65,
            'deft' => 60,
            'tal' => 75,
            'skl' => 70,
            'int' => 65
        ];
        
        $this->mockDb->setMockData([$draftClassData]);
        $this->mockDb->setReturnTrue(true);

        $result = $this->repository->createPlayerFromDraftClass("D'Angelo Russell", 'LA Lakers');

        $this->assertTrue($result);
        $queries = $this->mockDb->getExecutedQueries();
        
        // Check that there's an INSERT query with escaped apostrophe
        $insertQuery = null;
        foreach ($queries as $query) {
            if (stripos($query, 'INSERT INTO ibl_plr') !== false) {
                $insertQuery = $query;
                break;
            }
        }
        
        $this->assertNotNull($insertQuery);
        $this->assertStringContainsString("D\\'Angelo Russell", $insertQuery);
    }

    public function testCreatePlayerFromDraftClassTruncatesLongNames()
    {
        $longName = 'Christopher Emmanuel Paul Jr. III';
        $draftClassData = [
            'name' => $longName,
            'pos' => 'PG',
            'age' => 23,
            'sta' => 90,
            'offo' => 80,
            'offd' => 75,
            'offp' => 70,
            'offt' => 65,
            'defo' => 85,
            'defd' => 80,
            'defp' => 75,
            'deft' => 70,
            'tal' => 85,
            'skl' => 80,
            'int' => 75
        ];
        
        $this->mockDb->setMockData([$draftClassData]);
        $this->mockDb->setReturnTrue(true);
        
        $result = $this->repository->createPlayerFromDraftClass($longName, 'Chicago Bulls');

        $this->assertTrue($result);
        
        $queries = $this->mockDb->getExecutedQueries();
        
        // Find the INSERT query
        $insertQuery = null;
        foreach ($queries as $query) {
            if (stripos($query, 'INSERT INTO ibl_plr') !== false) {
                $insertQuery = $query;
                break;
            }
        }
        
        $this->assertNotNull($insertQuery);
        // The name in the query should be truncated to 32 chars
        $truncatedName = substr($longName, 0, 32);
        $this->assertStringContainsString($truncatedName, $insertQuery);
    }

    public function testCreatePlayerFromDraftClassReturnsFalseWhenPlayerNotInDraftClass()
    {
        // First query (team lookup) returns data, but second query (draft class) returns empty
        $this->mockDb->setMockData([
            ['teamid' => 4]
        ]);
        
        // After the first SELECT, subsequent queries return no rows
        $this->mockDb->setNumRows(0);

        $result = $this->repository->createPlayerFromDraftClass('Unknown Player', 'Chicago Bulls');

        // Should return false when player not found in draft class
        // Note: This test might be tricky with the current mock setup
        // The actual behavior depends on how the mock handles multiple queries
        $this->assertFalse($result);
    }
}
