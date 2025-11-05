<?php

use PHPUnit\Framework\TestCase;
use Services\NewsService;

/**
 * Tests for NewsService
 */
class NewsServiceTest extends TestCase
{
    private $newsService;
    private $mockDb;
    
    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->newsService = new NewsService($this->mockDb);
    }
    
    /**
     * Test creating a news story
     */
    public function testCreateNewsStory()
    {
        $this->mockDb->setReturnTrue(true);
        
        $result = $this->newsService->createNewsStory(
            3,
            5,
            'Test Story Title',
            'Test story content'
        );
        
        $this->assertTrue($result);
        
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('INSERT INTO nuke_stories', $queries[0]);
        $this->assertStringContainsString('Test Story Title', $queries[0]);
        $this->assertStringContainsString('Associated Press', $queries[0]);
    }
    
    /**
     * Test creating a news story with custom author
     */
    public function testCreateNewsStoryWithCustomAuthor()
    {
        $this->mockDb->setReturnTrue(true);
        
        $result = $this->newsService->createNewsStory(
            3,
            5,
            'Custom Author Story',
            'Story content',
            'Custom Author'
        );
        
        $this->assertTrue($result);
        
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('Custom Author', $queries[0]);
    }
    
    /**
     * Test getting topic ID by team name
     */
    public function testGetTopicIDByTeamName()
    {
        $this->mockDb->setMockData([
            ['topicid' => 15]
        ]);
        
        $result = $this->newsService->getTopicIDByTeamName('Boston Celtics');
        
        $this->assertEquals(15, $result);
    }
    
    /**
     * Test getting topic ID returns null when not found
     */
    public function testGetTopicIDByTeamNameNotFound()
    {
        $this->mockDb->setMockData([]);
        $this->mockDb->setNumRows(0);
        
        $result = $this->newsService->getTopicIDByTeamName('Nonexistent Team');
        
        $this->assertNull($result);
    }
    
    /**
     * Test getting category ID by title
     */
    public function testGetCategoryIDByTitle()
    {
        $this->mockDb->setMockData([
            ['catid' => 7]
        ]);
        
        $result = $this->newsService->getCategoryIDByTitle('Trade News');
        
        $this->assertEquals(7, $result);
    }
    
    /**
     * Test getting category ID returns null when not found
     */
    public function testGetCategoryIDByTitleNotFound()
    {
        $this->mockDb->setMockData([]);
        $this->mockDb->setNumRows(0);
        
        $result = $this->newsService->getCategoryIDByTitle('Nonexistent Category');
        
        $this->assertNull($result);
    }
    
    /**
     * Test incrementing category counter
     */
    public function testIncrementCategoryCounter()
    {
        $this->mockDb->setReturnTrue(true);
        
        $result = $this->newsService->incrementCategoryCounter('Waiver Pool Moves');
        
        $this->assertTrue($result);
        
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('UPDATE nuke_stories_cat', $queries[0]);
        $this->assertStringContainsString('counter = counter + 1', $queries[0]);
        $this->assertStringContainsString('Waiver Pool Moves', $queries[0]);
    }
}
