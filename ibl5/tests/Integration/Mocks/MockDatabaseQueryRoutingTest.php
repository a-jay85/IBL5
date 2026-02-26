<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

use PHPUnit\Framework\TestCase;
use Tests\Integration\Mocks\MockDatabase;
use Tests\Integration\Mocks\MockDatabaseResult;

/**
 * Tests for MockDatabase::onQuery() pattern-based query routing.
 */
class MockDatabaseQueryRoutingTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    public function testOnQueryMatchesPatternAndReturnsSpecificRows(): void
    {
        $this->mockDb->onQuery('SELECT COUNT', [['total' => 5]]);

        $result = $this->mockDb->sql_query('SELECT COUNT(*) AS total FROM ibl_plr');

        $this->assertInstanceOf(MockDatabaseResult::class, $result);
        $row = $result->fetchAssoc();
        $this->assertSame(5, $row['total']);
    }

    public function testOnQueryTakesPriorityOverMockData(): void
    {
        $this->mockDb->setMockData([['name' => 'Fallback Player', 'total' => 1]]);
        $this->mockDb->onQuery('SELECT COUNT', [['total' => 99]]);

        $countResult = $this->mockDb->sql_query('SELECT COUNT(*) AS total FROM ibl_plr');
        $this->assertInstanceOf(MockDatabaseResult::class, $countResult);
        $countRow = $countResult->fetchAssoc();
        $this->assertSame(99, $countRow['total']);

        // Non-matching query still falls through to mockData
        $dataResult = $this->mockDb->sql_query('SELECT * FROM ibl_plr');
        $this->assertInstanceOf(MockDatabaseResult::class, $dataResult);
        $dataRow = $dataResult->fetchAssoc();
        $this->assertSame('Fallback Player', $dataRow['name']);
    }

    public function testMultiplePatternsRouteToCorrectResults(): void
    {
        $this->mockDb->onQuery('SELECT COUNT', [['total' => 3]]);
        $this->mockDb->onQuery('SELECT.*FROM ibl_plr', [
            ['pid' => 1, 'name' => 'Player A'],
            ['pid' => 2, 'name' => 'Player B'],
            ['pid' => 3, 'name' => 'Player C'],
        ]);

        $countResult = $this->mockDb->sql_query('SELECT COUNT(*) AS total FROM ibl_plr');
        $this->assertInstanceOf(MockDatabaseResult::class, $countResult);
        $this->assertSame(3, $countResult->fetchAssoc()['total']);

        $listResult = $this->mockDb->sql_query('SELECT * FROM ibl_plr LIMIT 10');
        $this->assertInstanceOf(MockDatabaseResult::class, $listResult);
        $this->assertSame('Player A', $listResult->fetchAssoc()['name']);
    }

    public function testOnQueryIsCaseInsensitive(): void
    {
        $this->mockDb->onQuery('select count', [['total' => 7]]);

        $result = $this->mockDb->sql_query('SELECT COUNT(*) AS total FROM ibl_plr');
        $this->assertInstanceOf(MockDatabaseResult::class, $result);
        $this->assertSame(7, $result->fetchAssoc()['total']);
    }

    public function testClearQueryPatterns(): void
    {
        $this->mockDb->setMockData([['name' => 'Default']]);
        $this->mockDb->onQuery('SELECT', [['name' => 'Pattern']]);

        // Before clear: pattern match
        $result = $this->mockDb->sql_query('SELECT * FROM ibl_plr');
        $this->assertInstanceOf(MockDatabaseResult::class, $result);
        $this->assertSame('Pattern', $result->fetchAssoc()['name']);

        // After clear: falls through to mockData
        $this->mockDb->clearQueryPatterns();
        $result = $this->mockDb->sql_query('SELECT * FROM ibl_plr');
        $this->assertInstanceOf(MockDatabaseResult::class, $result);
        $this->assertSame('Default', $result->fetchAssoc()['name']);
    }

    public function testOnQueryDoesNotAffectDmlStatements(): void
    {
        $this->mockDb->onQuery('UPDATE', [['should_not_match' => true]]);

        // DML statements return bool, not MockDatabaseResult
        $result = $this->mockDb->sql_query('UPDATE ibl_plr SET name = "test" WHERE pid = 1');
        $this->assertTrue($result);
    }

    public function testFirstMatchingPatternWins(): void
    {
        $this->mockDb->onQuery('SELECT.*ibl_plr', [['source' => 'first']]);
        $this->mockDb->onQuery('SELECT', [['source' => 'second']]);

        $result = $this->mockDb->sql_query('SELECT * FROM ibl_plr');
        $this->assertInstanceOf(MockDatabaseResult::class, $result);
        $this->assertSame('first', $result->fetchAssoc()['source']);
    }

    public function testOnQueryEliminatesTotalKeyWorkaround(): void
    {
        // Before: had to include 'total' in every row
        // $this->mockDb->setMockData([['pid' => 1, 'name' => 'Player', 'total' => 1]]);

        // After: separate COUNT and data queries
        $this->mockDb->onQuery('SELECT COUNT', [['total' => 1]]);
        $this->mockDb->setMockData([['pid' => 1, 'name' => 'Player']]);

        // COUNT query gets clean count data
        $countResult = $this->mockDb->sql_query('SELECT COUNT(*) AS total FROM ibl_plr');
        $this->assertInstanceOf(MockDatabaseResult::class, $countResult);
        $this->assertSame(1, $countResult->fetchAssoc()['total']);

        // Data query gets clean player data (no 'total' key needed)
        $dataResult = $this->mockDb->sql_query('SELECT * FROM ibl_plr LIMIT 10');
        $this->assertInstanceOf(MockDatabaseResult::class, $dataResult);
        $row = $dataResult->fetchAssoc();
        $this->assertSame(1, $row['pid']);
        $this->assertSame('Player', $row['name']);
        $this->assertArrayNotHasKey('total', $row);
    }

    public function testOnQueryWorksWithPreparedStatements(): void
    {
        $this->mockDb->onQuery('SELECT COUNT', [['total' => 42]]);

        // Prepared statements go through sql_query() internally via MockPreparedStatement
        $stmt = $this->mockDb->prepare('SELECT COUNT(*) AS total FROM ibl_plr WHERE tid = ?');
        $tid = 5;
        $stmt->bind_param('i', $tid);
        $stmt->execute();
        $result = $stmt->get_result();

        $this->assertNotFalse($result);
        $row = $result->fetch_assoc();
        $this->assertNotNull($row);
        $this->assertSame(42, $row['total']);
    }
}
