<?php

declare(strict_types=1);

namespace Tests\Cache;

use Cache\PageCache;
use PHPUnit\Framework\TestCase;

final class PageCacheTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ibl5_page_cache_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        PageCache::setTestCacheDir($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        $files = glob($this->tempDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($this->tempDir);
        PageCache::setTestCacheDir(null);
    }

    // ── isCacheable ────────────────────────────────────────────

    public function testIsCacheableReturnsTrueForPlayerModule(): void
    {
        self::assertTrue(PageCache::isCacheable('Player'));
    }

    public function testIsCacheableReturnsTrueForTeamModule(): void
    {
        self::assertTrue(PageCache::isCacheable('Team'));
    }

    public function testIsCacheableReturnsTrueForStandingsModule(): void
    {
        self::assertTrue(PageCache::isCacheable('Standings'));
    }

    public function testIsCacheableReturnsFalseForYourAccountModule(): void
    {
        self::assertFalse(PageCache::isCacheable('YourAccount'));
    }

    public function testIsCacheableReturnsFalseForTradingModule(): void
    {
        self::assertFalse(PageCache::isCacheable('Trading'));
    }

    public function testIsCacheableReturnsFalseForDraftModule(): void
    {
        self::assertFalse(PageCache::isCacheable('Draft'));
    }

    // ── getTtl ─────────────────────────────────────────────────

    public function testGetTtlReturnsLongTtlForPlayerModule(): void
    {
        self::assertSame(3600, PageCache::getTtl('Player'));
    }

    public function testGetTtlReturnsDefaultTtlForScheduleModule(): void
    {
        self::assertSame(900, PageCache::getTtl('Schedule'));
    }

    public function testGetTtlReturnsDefaultTtlForUnknownModule(): void
    {
        self::assertSame(900, PageCache::getTtl('NonexistentModule'));
    }

    // ── buildCacheKey ──────────────────────────────────────────

    public function testBuildCacheKeyNormalizesQueryParamOrder(): void
    {
        $key1 = PageCache::buildCacheKey('/ibl5/modules.php?name=Player&pid=123', false);
        $key2 = PageCache::buildCacheKey('/ibl5/modules.php?pid=123&name=Player', false);

        self::assertSame($key1, $key2);
    }

    public function testBuildCacheKeyDifferentiatesBoostedFromNonBoosted(): void
    {
        $key1 = PageCache::buildCacheKey('/ibl5/modules.php?name=Player&pid=123', false);
        $key2 = PageCache::buildCacheKey('/ibl5/modules.php?name=Player&pid=123', true);

        self::assertNotSame($key1, $key2);
    }

    public function testBuildCacheKeyHandlesEmptyUri(): void
    {
        $key = PageCache::buildCacheKey('', false);

        self::assertSame(32, strlen($key));
    }

    public function testBuildCacheKeyProduces32CharMd5(): void
    {
        $key = PageCache::buildCacheKey('/ibl5/modules.php?name=Team&teamID=5', false);

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $key);
    }

    // ── get / set round-trip ───────────────────────────────────

    public function testSetAndGetRoundTrip(): void
    {
        $html = '<html><body>Test page</body></html>';
        PageCache::set('testkey', $html, 3600);

        $result = PageCache::get('testkey');

        self::assertSame($html, $result);
    }

    public function testGetReturnsNullOnCacheMiss(): void
    {
        self::assertNull(PageCache::get('nonexistent'));
    }

    public function testGetReturnsNullOnExpiredEntry(): void
    {
        // Write with TTL of -1 (already expired)
        $expiry = time() - 1;
        $data = $expiry . "\n<html>expired</html>";
        file_put_contents($this->tempDir . '/expiredkey.html', $data);

        $result = PageCache::get('expiredkey');

        self::assertNull($result);
        // File should be cleaned up
        self::assertFileDoesNotExist($this->tempDir . '/expiredkey.html');
    }

    public function testGetReturnsNullOnCorruptFile(): void
    {
        // Write a file with no newline
        file_put_contents($this->tempDir . '/corruptkey.html', 'no-newline-here');

        self::assertNull(PageCache::get('corruptkey'));
    }

    public function testGetReturnsNullOnEmptyFile(): void
    {
        file_put_contents($this->tempDir . '/emptykey.html', '');

        self::assertNull(PageCache::get('emptykey'));
    }

    public function testSetRefusesToCacheEmptyHtml(): void
    {
        PageCache::set('emptytest', '', 3600);

        self::assertFileDoesNotExist($this->tempDir . '/emptytest.html');
    }

    public function testSetCreatesDirectoryIfMissing(): void
    {
        $nestedDir = $this->tempDir . '/nested/subdir';
        PageCache::setTestCacheDir($nestedDir);

        PageCache::set('nestedkey', '<html>test</html>', 3600);

        self::assertFileExists($nestedDir . '/nestedkey.html');

        // Clean up nested dirs
        @unlink($nestedDir . '/nestedkey.html');
        @rmdir($nestedDir);
        @rmdir($this->tempDir . '/nested');
    }

    // ── purge ──────────────────────────────────────────────────

    public function testPurgeDeletesAllCacheFiles(): void
    {
        PageCache::set('key1', '<html>page1</html>', 3600);
        PageCache::set('key2', '<html>page2</html>', 3600);
        PageCache::set('key3', '<html>page3</html>', 3600);

        $count = PageCache::purge();

        self::assertSame(3, $count);
        self::assertSame([], glob($this->tempDir . '/*.html') ?: []);
    }

    public function testPurgeReturnsZeroOnEmptyCache(): void
    {
        self::assertSame(0, PageCache::purge());
    }

    public function testPurgeReturnsZeroWhenDirectoryMissing(): void
    {
        PageCache::setTestCacheDir('/tmp/nonexistent_dir_' . uniqid());

        self::assertSame(0, PageCache::purge());
    }
}
