<?php

declare(strict_types=1);

namespace Tests\Topics;

use PHPUnit\Framework\TestCase;
use Search\Contracts\SearchRepositoryInterface;
use Topics\Contracts\TopicsRepositoryInterface;
use Topics\TopicsService;

class TopicsServiceTest extends TestCase
{
    // ============================================
    // HAPPY PATH
    // ============================================

    public function testGetPageDataReturnsMappedShapeWithArticleCommTrue(): void
    {
        $topicRow = [
            'topicId' => 1,
            'topicName' => 'Basketball',
            'topicImage' => 'bball.png',
            'topicText' => 'Basketball News',
            'storyCount' => 5,
            'totalReads' => 100,
            'recentArticles' => [
                ['sid' => 10, 'title' => 'Big Game', 'catId' => 2, 'catTitle' => 'Sports'],
            ],
        ];
        $topicFilterRow = ['topicId' => 1, 'topicText' => 'Basketball News'];
        $categoryRow = ['catId' => 2, 'title' => 'Sports'];

        $topicsRepo = self::createStub(TopicsRepositoryInterface::class);
        $topicsRepo->method('getTopicsWithArticles')->willReturn([$topicRow]);

        $searchRepo = self::createStub(SearchRepositoryInterface::class);
        $searchRepo->method('getTopics')->willReturn([$topicFilterRow]);
        $searchRepo->method('getCategories')->willReturn([$categoryRow]);
        $searchRepo->method('getAuthors')->willReturn([1 => 'A. Reporter']);

        $service = new TopicsService(
            self::createStub(\mysqli::class),
            'nuke',
            $topicsRepo,
            $searchRepo,
        );

        $result = $service->getPageData(true);

        $this->assertArrayHasKey('topics', $result);
        $this->assertArrayHasKey('searchFilters', $result);
        $this->assertCount(1, $result['topics']);
        $this->assertSame($topicRow, $result['topics'][0]);
        $this->assertTrue($result['searchFilters']['articleComm']);
        $this->assertSame([$topicFilterRow], $result['searchFilters']['topics']);
        $this->assertSame([$categoryRow], $result['searchFilters']['categories']);
        $this->assertSame([1 => 'A. Reporter'], $result['searchFilters']['authors']);
    }

    // ============================================
    // NEGATIVE / EMPTY PATH
    // ============================================

    public function testGetPageDataReturnsWellShapedEmptyStructureWhenAllCollaboratorsEmpty(): void
    {
        $topicsRepo = self::createStub(TopicsRepositoryInterface::class);
        $topicsRepo->method('getTopicsWithArticles')->willReturn([]);

        $searchRepo = self::createStub(SearchRepositoryInterface::class);
        $searchRepo->method('getTopics')->willReturn([]);
        $searchRepo->method('getCategories')->willReturn([]);
        $searchRepo->method('getAuthors')->willReturn([]);

        $service = new TopicsService(
            self::createStub(\mysqli::class),
            'nuke',
            $topicsRepo,
            $searchRepo,
        );

        $result = $service->getPageData(false);

        $this->assertSame([], $result['topics']);
        $this->assertSame([], $result['searchFilters']['topics']);
        $this->assertSame([], $result['searchFilters']['categories']);
        $this->assertSame([], $result['searchFilters']['authors']);
        $this->assertFalse($result['searchFilters']['articleComm']);
    }

    // ============================================
    // DELEGATION: service calls the injected interface
    // ============================================

    public function testGetPageDataDelegatesFiltersToInjectedSearchRepository(): void
    {
        $topicsRepo = self::createStub(TopicsRepositoryInterface::class);
        $topicsRepo->method('getTopicsWithArticles')->willReturn([]);

        $searchRepo = $this->createMock(SearchRepositoryInterface::class);
        $searchRepo->expects($this->once())->method('getTopics')->willReturn([]);
        $searchRepo->expects($this->once())->method('getCategories')->willReturn([]);
        $searchRepo->expects($this->once())->method('getAuthors')->willReturn([]);

        $service = new TopicsService(
            self::createStub(\mysqli::class),
            'nuke',
            $topicsRepo,
            $searchRepo,
        );

        $service->getPageData(false);
    }

    public function testGetPageDataDelegatesTopicsToInjectedTopicsRepository(): void
    {
        $topicsRepo = $this->createMock(TopicsRepositoryInterface::class);
        $topicsRepo->expects($this->once())->method('getTopicsWithArticles')->willReturn([]);

        $searchRepo = self::createStub(SearchRepositoryInterface::class);
        $searchRepo->method('getTopics')->willReturn([]);
        $searchRepo->method('getCategories')->willReturn([]);
        $searchRepo->method('getAuthors')->willReturn([]);

        $service = new TopicsService(
            self::createStub(\mysqli::class),
            'nuke',
            $topicsRepo,
            $searchRepo,
        );

        $service->getPageData(true);
    }
}
