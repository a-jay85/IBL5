<?php

declare(strict_types=1);

namespace Tests\HeadToHeadRecords;

use Cache\Contracts\DatabaseCacheInterface;
use HeadToHeadRecords\CachedHeadToHeadRecordsRepository;
use HeadToHeadRecords\Contracts\HeadToHeadRecordsRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HeadToHeadRecords\CachedHeadToHeadRecordsRepository
 */
class CachedHeadToHeadRecordsRepositoryTest extends TestCase
{
    public function testGetMatrixReturnsCachedResultOnHit(): void
    {
        $expected = [
            'axis' => [['key' => 1, 'label' => 'Warriors', 'logo' => '', 'franchise_id' => 1]],
            'matrix' => [],
        ];

        $cache = $this->createMock(DatabaseCacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with('head_to_head_records:current:active_teams:regular')
            ->willReturn($expected);

        $inner = $this->createStub(HeadToHeadRecordsRepositoryInterface::class);
        $repo = new CachedHeadToHeadRecordsRepository($inner, $cache);

        $result = $repo->getMatrix('current', 'active_teams', 'regular', 2026);

        self::assertSame($expected, $result);
    }

    public function testGetMatrixCallsInnerOnCacheMiss(): void
    {
        $expected = [
            'axis' => [['key' => 1, 'label' => 'Warriors', 'logo' => '', 'franchise_id' => 1]],
            'matrix' => [],
        ];

        $cache = $this->createMock(DatabaseCacheInterface::class);
        $cache->expects($this->once())->method('get')->willReturn(null);
        $cache->expects($this->once())->method('set');

        $inner = $this->createMock(HeadToHeadRecordsRepositoryInterface::class);
        $inner->expects($this->once())
            ->method('getMatrix')
            ->with('current', 'active_teams', 'regular', 2026)
            ->willReturn($expected);

        $repo = new CachedHeadToHeadRecordsRepository($inner, $cache);

        $result = $repo->getMatrix('current', 'active_teams', 'regular', 2026);

        self::assertSame($expected, $result);
    }

    public function testRebuildCacheWrites24Keys(): void
    {
        $inner = $this->createStub(HeadToHeadRecordsRepositoryInterface::class);
        $inner->method('getMatrix')->willReturn(['axis' => [], 'matrix' => []]);

        $cache = $this->createMock(DatabaseCacheInterface::class);
        $cache->expects($this->exactly(24))->method('set');

        $repo = new CachedHeadToHeadRecordsRepository($inner, $cache);
        $repo->rebuildCache(2026);
    }

    public function testInvalidateCacheDeletes24Keys(): void
    {
        $inner = $this->createStub(HeadToHeadRecordsRepositoryInterface::class);

        $cache = $this->createMock(DatabaseCacheInterface::class);
        $cache->expects($this->exactly(24))->method('delete');

        $repo = new CachedHeadToHeadRecordsRepository($inner, $cache);
        $repo->invalidateCache();
    }


}
