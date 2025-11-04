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
    
    public function testGetTeamDiscordIDEscapesTeamName()
    {
        $this->mockDb->setMockData([
            ['discordID' => '123456789']
        ]);

        $this->repository->getTeamDiscordID("Team's Name");

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertStringContainsString("Team\\'s Name", $queries[0]);
    }

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
}
