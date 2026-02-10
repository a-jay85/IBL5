<?php

declare(strict_types=1);

namespace Tests\Topics;

use Tests\Integration\IntegrationTestCase;
use Topics\Contracts\TopicsServiceInterface;
use Topics\TopicsService;

/**
 * @covers \Topics\TopicsService
 */
class TopicsServiceTest extends IntegrationTestCase
{
    private TopicsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TopicsService($this->mockDb);
    }

    public function testImplementsServiceInterface(): void
    {
        $this->assertInstanceOf(TopicsServiceInterface::class, $this->service);
    }

    public function testGetTopicsWithArticlesReturnsEmptyWhenNoTopics(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->service->getTopicsWithArticles();

        $this->assertSame([], $result);
    }

    public function testGetTopicsWithArticlesReturnsTopicData(): void
    {
        // MockDatabase returns same data for all queries, so include article keys
        // for when fetchRecentArticles() is called (stories > 0)
        $this->mockDb->setMockData([
            [
                'topicid' => 1,
                'topicname' => 'Basketball',
                'topicimage' => 'basketball.gif',
                'topictext' => 'Basketball News',
                'stories' => 5,
                'total_reads' => 100,
                'sid' => 1,
                'catid' => 1,
                'title' => 'Test Article',
                'cat_title' => 'News',
            ],
        ]);

        $result = $this->service->getTopicsWithArticles();

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['topicId']);
        $this->assertSame('Basketball News', $result[0]['topicText']);
        $this->assertSame(5, $result[0]['storyCount']);
        $this->assertSame(100, $result[0]['totalReads']);
    }

    public function testGetTopicsWithArticlesConvertsTypesFromStrings(): void
    {
        $this->mockDb->setMockData([
            [
                'topicid' => '3',
                'topicname' => 'Trades',
                'topicimage' => 'trades.gif',
                'topictext' => 'Trade News',
                'stories' => '10',
                'total_reads' => '500',
                'sid' => '1',
                'catid' => '1',
                'title' => 'Test',
                'cat_title' => '',
            ],
        ]);

        $result = $this->service->getTopicsWithArticles();

        $this->assertSame(3, $result[0]['topicId']);
        $this->assertSame(10, $result[0]['storyCount']);
        $this->assertSame(500, $result[0]['totalReads']);
    }

    public function testGetTopicsWithArticlesSkipsArticleFetchWhenZeroStories(): void
    {
        $this->mockDb->setMockData([
            [
                'topicid' => 1,
                'topicname' => 'Empty',
                'topicimage' => 'empty.gif',
                'topictext' => 'Empty Topic',
                'stories' => 0,
                'total_reads' => 0,
            ],
        ]);

        $result = $this->service->getTopicsWithArticles();

        $this->assertSame([], $result[0]['recentArticles']);
    }
}
