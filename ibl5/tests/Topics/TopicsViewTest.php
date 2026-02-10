<?php

declare(strict_types=1);

namespace Tests\Topics;

use PHPUnit\Framework\TestCase;
use Topics\Contracts\TopicsViewInterface;
use Topics\TopicsView;

/**
 * @covers \Topics\TopicsView
 */
class TopicsViewTest extends TestCase
{
    private TopicsView $view;

    protected function setUp(): void
    {
        $this->view = new TopicsView();
    }

    public function testImplementsViewInterface(): void
    {
        $this->assertInstanceOf(TopicsViewInterface::class, $this->view);
    }

    public function testRenderShowsEmptyStateWhenNoTopics(): void
    {
        $html = $this->view->render([], 'themes/IBL/images/topics/');

        $this->assertStringContainsString('ibl-empty-state', $html);
    }

    public function testRenderShowsPageHeader(): void
    {
        $topics = [self::createTopic()];

        $html = $this->view->render($topics, 'themes/IBL/images/topics/');

        $this->assertStringContainsString('Active Topics', $html);
    }

    public function testRenderShowsSearchForm(): void
    {
        $topics = [self::createTopic()];

        $html = $this->view->render($topics, 'themes/IBL/images/topics/');

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('Search', $html);
    }

    public function testRenderShowsTopicName(): void
    {
        $topics = [self::createTopic(['topicText' => 'Basketball News'])];

        $html = $this->view->render($topics, 'themes/IBL/images/topics/');

        $this->assertStringContainsString('Basketball News', $html);
    }

    public function testRenderShowsTopicImage(): void
    {
        $topics = [self::createTopic(['topicImage' => 'basketball.gif'])];

        $html = $this->view->render($topics, 'themes/IBL/images/topics/');

        $this->assertStringContainsString('basketball.gif', $html);
    }

    public function testRenderShowsStoryCount(): void
    {
        $topics = [self::createTopic(['storyCount' => 42])];

        $html = $this->view->render($topics, 'themes/IBL/images/topics/');

        $this->assertStringContainsString('42', $html);
    }

    public function testRenderShowsArticleLinks(): void
    {
        $topics = [self::createTopic([
            'storyCount' => 1,
            'recentArticles' => [
                ['sid' => 100, 'title' => 'Test Article', 'catId' => 1, 'catTitle' => 'News'],
            ],
        ])];

        $html = $this->view->render($topics, 'themes/IBL/images/topics/');

        $this->assertStringContainsString('Test Article', $html);
        $this->assertStringContainsString('sid=100', $html);
    }

    public function testRenderShowsMoreLinkWhenOverTenStories(): void
    {
        $topics = [self::createTopic(['storyCount' => 15])];

        $html = $this->view->render($topics, 'themes/IBL/images/topics/');

        $this->assertStringContainsString('More', $html);
    }

    public function testRenderShowsNoNewsForTopicWithZeroStories(): void
    {
        $topics = [self::createTopic(['storyCount' => 0])];

        $html = $this->view->render($topics, 'themes/IBL/images/topics/');

        $this->assertStringContainsString('No news yet', $html);
    }

    /**
     * @return array{topicId: int, topicName: string, topicImage: string, topicText: string, storyCount: int, totalReads: int, recentArticles: array<int, array{sid: int, title: string, catId: int, catTitle: string}>}
     */
    private static function createTopic(array $overrides = []): array
    {
        /** @var array{topicId: int, topicName: string, topicImage: string, topicText: string, storyCount: int, totalReads: int, recentArticles: array<int, array{sid: int, title: string, catId: int, catTitle: string}>} */
        return array_merge([
            'topicId' => 1,
            'topicName' => 'Test',
            'topicImage' => 'test.gif',
            'topicText' => 'Test Topic',
            'storyCount' => 5,
            'totalReads' => 100,
            'recentArticles' => [],
        ], $overrides);
    }
}
