<?php

declare(strict_types=1);

namespace Tests\Topics\News;

use PHPUnit\Framework\TestCase;
use Topics\News\Contracts\NewsRepositoryInterface;
use Topics\News\NewsService;

class NewsServiceTest extends TestCase
{
    // ============================================
    // DELEGATION / PASSTHROUGH
    // ============================================

    public function testGetStoryReturnsNullForMissingSid(): void
    {
        $repo = self::createStub(NewsRepositoryInterface::class);
        $repo->method('getStoryById')->willReturn(null);

        $service = new NewsService(self::createStub(\mysqli::class), $repo);

        $this->assertNull($service->getStory(99999));
    }

    public function testGetHomePageStoriesDelegatesToRepository(): void
    {
        $rows = [['sid' => 1], ['sid' => 2]];

        $repo = self::createStub(NewsRepositoryInterface::class);
        $repo->method('getHomePageStories')->willReturn($rows);

        $service = new NewsService(self::createStub(\mysqli::class), $repo);

        $result = $service->getHomePageStories(10, '');
        $this->assertCount(2, $result);
        $this->assertSame($rows, $result);
    }

    public function testBumpStoryDelegates(): void
    {
        $repo = $this->createMock(NewsRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('incrementStoryCounter')
            ->with(5)
            ->willReturn(1);

        $service = new NewsService(self::createStub(\mysqli::class), $repo);

        $service->bumpStory(5);
    }

    // ============================================
    // normalizeStoryTime
    // ============================================

    public function testNormalizeStoryTimeParsesDatetimeString(): void
    {
        $service = new NewsService(self::createStub(\mysqli::class), self::createStub(NewsRepositoryInterface::class));

        $input = '2026-05-13 12:00:00';
        $expected = (int) gmmktime(12, 0, 0, 5, 13, 2026) - (int) date('Z');

        $this->assertSame($expected, $service->normalizeStoryTime($input));
    }

    public function testNormalizeStoryTimePassesThroughNumericTimestamp(): void
    {
        $service = new NewsService(self::createStub(\mysqli::class), self::createStub(NewsRepositoryInterface::class));

        $input = 1700000000;
        $expected = 1700000000 - (int) date('Z');

        $this->assertSame($expected, $service->normalizeStoryTime($input));
    }

    // ============================================
    // computeByteCounts
    // ============================================

    public function testComputeByteCounts(): void
    {
        $service = new NewsService(self::createStub(\mysqli::class), self::createStub(NewsRepositoryInterface::class));

        $result = $service->computeByteCounts('abc', 'de');

        $this->assertSame(3, $result['intro']);
        $this->assertSame(2, $result['full']);
        $this->assertSame(5, $result['total']);
        $this->assertSame($result['intro'] + $result['full'], $result['total']);
    }
}
