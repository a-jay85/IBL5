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

    /** @var array{topics: list<array{topicId: int, topicText: string}>, categories: list<array{catId: int, title: string}>, authors: list<string>, articleComm: bool} */
    private array $searchFilters;

    protected function setUp(): void
    {
        $this->view = new TopicsView();
        $this->searchFilters = [
            'topics' => [['topicId' => 1, 'topicText' => 'IBL News']],
            'categories' => [['catId' => 1, 'title' => 'Trades']],
            'authors' => ['admin'],
            'articleComm' => false,
        ];
    }

    public function testImplementsViewInterface(): void
    {
        $this->assertInstanceOf(TopicsViewInterface::class, $this->view);
    }

    public function testRenderShowsEmptyStateWhenNoTopics(): void
    {
        $html = $this->view->render([], 'themes/IBL/images/topics/', $this->searchFilters);

        $this->assertStringContainsString('ibl-empty-state', $html);
    }

    public function testRenderShowsPageHeader(): void
    {
        $topics = [self::createTopic()];

        $html = $this->view->render($topics, 'themes/IBL/images/topics/', $this->searchFilters);

        $this->assertStringContainsString('Active Topics', $html);
    }

    public function testRenderShowsSearchForm(): void
    {
        $topics = [self::createTopic()];

        $html = $this->view->render($topics, 'themes/IBL/images/topics/', $this->searchFilters);

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('Search', $html);
    }

    public function testRenderShowsFilterDropdowns(): void
    {
        $topics = [self::createTopic()];

        $html = $this->view->render($topics, 'themes/IBL/images/topics/', $this->searchFilters);

        $this->assertStringContainsString('search-form__select', $html);
        $this->assertStringContainsString('name="topic"', $html);
        $this->assertStringContainsString('name="category"', $html);
        $this->assertStringContainsString('name="author"', $html);
        $this->assertStringContainsString('name="days"', $html);
    }

    public function testRenderShowsSearchTypeRadios(): void
    {
        $topics = [self::createTopic()];

        $html = $this->view->render($topics, 'themes/IBL/images/topics/', $this->searchFilters);

        $this->assertStringContainsString('name="type"', $html);
        $this->assertStringContainsString('value="stories"', $html);
        $this->assertStringContainsString('value="users"', $html);
        $this->assertStringContainsString('checked', $html);
    }

    public function testRenderShowsCommentsRadioWhenArticleCommEnabled(): void
    {
        $topics = [self::createTopic()];
        $filters = $this->searchFilters;
        $filters['articleComm'] = true;

        $html = $this->view->render($topics, 'themes/IBL/images/topics/', $filters);

        $this->assertStringContainsString('value="comments"', $html);
    }

    public function testRenderHidesCommentsRadioWhenArticleCommDisabled(): void
    {
        $topics = [self::createTopic()];

        $html = $this->view->render($topics, 'themes/IBL/images/topics/', $this->searchFilters);

        $this->assertStringNotContainsString('value="comments"', $html);
    }

    public function testRenderShowsTopicName(): void
    {
        $topics = [self::createTopic(['topicText' => 'Basketball News'])];

        $html = $this->view->render($topics, 'themes/IBL/images/topics/', $this->searchFilters);

        $this->assertStringContainsString('Basketball News', $html);
    }

    public function testRenderShowsTopicImage(): void
    {
        $topics = [self::createTopic(['topicImage' => 'basketball.gif'])];

        $html = $this->view->render($topics, 'themes/IBL/images/topics/', $this->searchFilters);

        $this->assertStringContainsString('basketball.gif', $html);
    }

    public function testRenderShowsStoryCount(): void
    {
        $topics = [self::createTopic(['storyCount' => 42])];

        $html = $this->view->render($topics, 'themes/IBL/images/topics/', $this->searchFilters);

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

        $html = $this->view->render($topics, 'themes/IBL/images/topics/', $this->searchFilters);

        $this->assertStringContainsString('Test Article', $html);
        $this->assertStringContainsString('sid=100', $html);
    }

    public function testRenderShowsMoreLinkWhenOverTenStories(): void
    {
        $topics = [self::createTopic(['storyCount' => 15])];

        $html = $this->view->render($topics, 'themes/IBL/images/topics/', $this->searchFilters);

        $this->assertStringContainsString('More', $html);
    }

    public function testRenderShowsNoNewsForTopicWithZeroStories(): void
    {
        $topics = [self::createTopic(['storyCount' => 0])];

        $html = $this->view->render($topics, 'themes/IBL/images/topics/', $this->searchFilters);

        $this->assertStringContainsString('No news yet', $html);
    }

    public function testRenderPopulatesFilterDropdownOptions(): void
    {
        $topics = [self::createTopic()];
        $filters = [
            'topics' => [
                ['topicId' => 5, 'topicText' => 'Trade News'],
                ['topicId' => 8, 'topicText' => 'Draft Coverage'],
            ],
            'categories' => [['catId' => 3, 'title' => 'Extensions']],
            'authors' => ['commissioner', 'admin'],
            'articleComm' => false,
        ];

        $html = $this->view->render($topics, 'themes/IBL/images/topics/', $filters);

        $this->assertStringContainsString('Trade News', $html);
        $this->assertStringContainsString('Draft Coverage', $html);
        $this->assertStringContainsString('Extensions', $html);
        $this->assertStringContainsString('commissioner', $html);
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
