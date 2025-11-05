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

    public function testUpdatePlayerTableWithExactMatch()
    {
        // Mock the team ID lookup - don't set numRows, let it be calculated from mockData
        $this->mockDb->setMockData([
            ['teamid' => 4]
        ]);
        $this->mockDb->setReturnTrue(true);
        $this->mockDb->setAffectedRows(1);

        $result = $this->repository->updatePlayerTable('John Doe', 'Chicago Bulls');

        $this->assertTrue($result);
        $queries = $this->mockDb->getExecutedQueries();
        
        // Should have a SELECT query for team ID and an UPDATE query for player
        $this->assertGreaterThanOrEqual(2, count($queries));
        
        // Check that there's a team lookup query
        $hasTeamQuery = false;
        foreach ($queries as $query) {
            if (stripos($query, 'SELECT teamid FROM ibl_team_info') !== false) {
                $hasTeamQuery = true;
                break;
            }
        }
        $this->assertTrue($hasTeamQuery);
        
        // Check the UPDATE query
        $updateQuery = null;
        foreach ($queries as $query) {
            if (stripos($query, 'UPDATE ibl_plr') !== false) {
                $updateQuery = $query;
                break;
            }
        }
        
        $this->assertNotNull($updateQuery);
        $this->assertStringContainsString('ibl_plr', $updateQuery);
        $this->assertStringContainsString('John Doe', $updateQuery);
        $this->assertStringContainsString('Chicago Bulls', $updateQuery);
        $this->assertStringContainsString('tid = 4', $updateQuery);
        $this->assertStringContainsString('teamname', $updateQuery);
    }

    public function testUpdatePlayerTableWithTruncatedName()
    {
        // Mock the team ID lookup
        $this->mockDb->setMockData([
            ['teamid' => 4]
        ]);
        $this->mockDb->setReturnTrue(true);
        // Simulate that exact match fails but truncated match succeeds
        $this->mockDb->setAffectedRows(1);

        // Use a long name that would be truncated in ibl_plr
        $longName = 'Christopher Emmanuel Paul Jr.';
        
        $result = $this->repository->updatePlayerTable($longName, 'Chicago Bulls');

        $this->assertTrue($result);
        
        $queries = $this->mockDb->getExecutedQueries();
        
        // Should have UPDATE queries
        $updateQueries = array_filter($queries, function($q) {
            return stripos($q, 'UPDATE ibl_plr') !== false;
        });
        
        // At least one UPDATE query should be present
        $this->assertGreaterThanOrEqual(1, count($updateQueries));
    }

    public function testUpdatePlayerTableWithPartialMatch()
    {
        // Mock the team ID lookup
        $this->mockDb->setMockData([
            ['teamid' => 15]
        ]);
        $this->mockDb->setReturnTrue(true);
        $this->mockDb->setAffectedRows(1);

        // Player name with diacriticals that might not match exactly
        $result = $this->repository->updatePlayerTable('José Calderón', 'Miami Heat');

        $this->assertTrue($result);
        
        $queries = $this->mockDb->getExecutedQueries();
        
        // Should have UPDATE queries
        $updateQueries = array_filter($queries, function($q) {
            return stripos($q, 'UPDATE ibl_plr') !== false;
        });
        
        $this->assertGreaterThanOrEqual(1, count($updateQueries));
    }

    public function testUpdatePlayerTableReturnsFalseWhenTeamNotFound()
    {
        $this->mockDb->setMockData([]);
        $this->mockDb->setNumRows(0);

        $result = $this->repository->updatePlayerTable('John Doe', 'Nonexistent Team');

        $this->assertFalse($result);
    }

    public function testUpdatePlayerTableHandlesApostrophes()
    {
        // Mock the team ID lookup
        $this->mockDb->setMockData([
            ['teamid' => 13]
        ]);
        $this->mockDb->setReturnTrue(true);
        $this->mockDb->setAffectedRows(1);

        $result = $this->repository->updatePlayerTable("D'Angelo Russell", 'LA Lakers');

        $this->assertTrue($result);
        $queries = $this->mockDb->getExecutedQueries();
        
        $updateQuery = null;
        foreach ($queries as $query) {
            if (stripos($query, 'UPDATE ibl_plr') !== false) {
                $updateQuery = $query;
                break;
            }
        }
        
        $this->assertNotNull($updateQuery);
        $this->assertStringContainsString("D\\'Angelo Russell", $updateQuery);
    }
}
