<?php

declare(strict_types=1);

namespace Tests\Records;

use Cache\PageCache;
use PHPUnit\Framework\TestCase;

/**
 * The page cache captures a module's body without checking the response code,
 * so a cacheable redirect stub would replay an empty 200 with no Location.
 */
final class RecordsPageCacheTest extends TestCase
{
    public function testRecordsAndAllStarPagesAreCacheable(): void
    {
        $this->assertTrue(PageCache::isCacheable('Records'));
        $this->assertTrue(PageCache::isCacheable('AllStarAppearances'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('redirectStubProvider')]
    public function testRedirectStubsAreNotCacheable(string $moduleName): void
    {
        $this->assertFalse(PageCache::isCacheable($moduleName));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function redirectStubProvider(): array
    {
        return [
            'RecordHolders' => ['RecordHolders'],
            'FranchiseRecordBook' => ['FranchiseRecordBook'],
            'SeasonHighs' => ['SeasonHighs'],
        ];
    }
}
