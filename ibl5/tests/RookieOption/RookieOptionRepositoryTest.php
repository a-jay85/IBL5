<?php

use PHPUnit\Framework\TestCase;
use RookieOption\RookieOptionRepository;

/**
 * Tests for RookieOptionRepository
 */
class RookieOptionRepositoryTest extends TestCase
{
    private $repository;
    private $mockDb;
    
    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->repository = new RookieOptionRepository($this->mockDb);
    }
    
    /**
     * Test updating player rookie option for first round pick
     */
    public function testUpdatePlayerRookieOptionFirstRound()
    {
        $this->mockDb->setReturnTrue(true);
        
        $result = $this->repository->updatePlayerRookieOption(123, 1, 200);
        
        $this->assertTrue($result);
        
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('UPDATE ibl_plr', $queries[0]);
        $this->assertStringContainsString('cy4', $queries[0]);
        $this->assertStringContainsString('200', $queries[0]);
        $this->assertStringContainsString('WHERE pid = 123', $queries[0]);
    }
    
    /**
     * Test updating player rookie option for second round pick
     */
    public function testUpdatePlayerRookieOptionSecondRound()
    {
        $this->mockDb->setReturnTrue(true);
        
        $result = $this->repository->updatePlayerRookieOption(456, 2, 150);
        
        $this->assertTrue($result);
        
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('UPDATE ibl_plr', $queries[0]);
        $this->assertStringContainsString('cy3', $queries[0]);
        $this->assertStringContainsString('150', $queries[0]);
        $this->assertStringContainsString('WHERE pid = 456', $queries[0]);
    }
    
    /**
     * Test getting topic ID by team name
     */
    public function testGetTopicIDByTeamName()
    {
        $this->mockDb->setMockData([
            ['topicid' => 5]
        ]);
        
        $result = $this->repository->getTopicIDByTeamName('Boston Celtics');
        
        $this->assertEquals(5, $result);
    }
    
    /**
     * Test getting topic ID returns null when not found
     */
    public function testGetTopicIDByTeamNameNotFound()
    {
        $this->mockDb->setMockData([]);
        $this->mockDb->setNumRows(0);
        
        $result = $this->repository->getTopicIDByTeamName('Nonexistent Team');
        
        $this->assertNull($result);
    }
    
    /**
     * Test getting rookie extension category ID
     */
    public function testGetRookieExtensionCategoryID()
    {
        $this->mockDb->setMockData([
            ['catid' => 3]
        ]);
        
        $result = $this->repository->getRookieExtensionCategoryID();
        
        $this->assertEquals(3, $result);
    }
    
    /**
     * Test getting category ID returns null when not found
     */
    public function testGetRookieExtensionCategoryIDNotFound()
    {
        $this->mockDb->setMockData([]);
        $this->mockDb->setNumRows(0);
        
        $result = $this->repository->getRookieExtensionCategoryID();
        
        $this->assertNull($result);
    }
    
    /**
     * Test incrementing rookie extension counter
     */
    public function testIncrementRookieExtensionCounter()
    {
        $this->mockDb->setReturnTrue(true);
        
        $result = $this->repository->incrementRookieExtensionCounter();
        
        $this->assertTrue($result);
        
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('UPDATE nuke_stories_cat', $queries[0]);
        $this->assertStringContainsString('counter = counter + 1', $queries[0]);
        $this->assertStringContainsString('Rookie Extension', $queries[0]);
    }
    
    /**
     * Test creating news story
     */
    public function testCreateNewsStory()
    {
        $this->mockDb->setReturnTrue(true);
        
        $result = $this->repository->createNewsStory(
            3,
            5,
            'Player extends contract',
            'The team exercises the rookie option'
        );
        
        $this->assertTrue($result);
        
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('INSERT INTO nuke_stories', $queries[0]);
        $this->assertStringContainsString('Associated Press', $queries[0]);
    }
}
