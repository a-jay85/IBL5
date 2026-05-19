<?php

declare(strict_types=1);

namespace Tests\Topics\News;

use PHPUnit\Framework\TestCase;
use Topics\News\NewsRepository;
use Tests\WideUnit\Mocks\MockDatabase;

class NewsRepositoryTest extends TestCase
{
    private NewsRepository $newsService;
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->newsService = new NewsRepository($this->mockDb);
    }

    public function testCreateNewsStoryExecutesInsert(): void
    {
        $this->newsService->createNewsStory(
            3,
            5,
            'Test Story Title',
            'Test story content'
        );

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('INSERT INTO', $queries[0]);
        $this->assertStringContainsString('nuke_stories', $queries[0]);
    }

    public function testCreateNewsStoryWithCustomAuthor(): void
    {
        $this->newsService->createNewsStory(
            3,
            5,
            'Custom Author Story',
            'Story content',
            'Custom Author'
        );

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('Custom Author', $queries[0]);
    }

    public function testGetTopicIDByTeamName(): void
    {
        $this->mockDb->setMockData([
            ['topicid' => 15],
        ]);

        $result = $this->newsService->getTopicIDByTeamName('Boston Celtics');

        $this->assertSame(15, $result);
    }

    public function testGetTopicIDByTeamNameNotFound(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->newsService->getTopicIDByTeamName('Nonexistent Team');

        $this->assertNull($result);
    }

    public function testGetCategoryIDByTitle(): void
    {
        $this->mockDb->setMockData([
            ['catid' => 7],
        ]);

        $result = $this->newsService->getCategoryIDByTitle('Trade News');

        $this->assertSame(7, $result);
    }

    public function testGetCategoryIDByTitleNotFound(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->newsService->getCategoryIDByTitle('Nonexistent Category');

        $this->assertNull($result);
    }

    public function testIncrementCategoryCounter(): void
    {
        $this->newsService->incrementCategoryCounter('Waiver Pool Moves');

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('UPDATE', $queries[0]);
        $this->assertStringContainsString('nuke_stories_cat', $queries[0]);
        $this->assertStringContainsString('counter', $queries[0]);
    }
}
