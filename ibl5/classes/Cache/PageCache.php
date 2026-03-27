<?php

declare(strict_types=1);

namespace Cache;

/**
 * File-based full-page HTML cache for anonymous GET requests.
 *
 * Storage: one file per cached page under {ibl5}/cache/page/.
 * File format: first line = UNIX expiry timestamp, remainder = raw HTML.
 *
 * Thread safety: writes use LOCK_EX. Reads tolerate missing/corrupt files.
 */
final class PageCache
{
    private const TTL_DEFAULT = 900;
    private const TTL_LONG = 3600;

    /** @var array<string, int> */
    private const MODULE_TTLS = [
        'Player'              => self::TTL_LONG,
        'Team'                => self::TTL_LONG,
        'Standings'           => self::TTL_LONG,
        'CareerLeaderboards'  => self::TTL_LONG,
        'RecordHolders'       => self::TTL_LONG,
        'SeasonLeaderboards'  => self::TTL_LONG,
        'DraftHistory'        => self::TTL_LONG,
        'AwardHistory'        => self::TTL_LONG,
        'FranchiseHistory'    => self::TTL_LONG,
        'FranchiseRecordBook' => self::TTL_LONG,
        'SeasonHighs'         => self::TTL_LONG,
        'Schedule'            => self::TTL_DEFAULT,
        'TransactionHistory'  => self::TTL_DEFAULT,
    ];

    private static ?string $testCacheDir = null;

    public static function isCacheable(string $moduleName): bool
    {
        return isset(self::MODULE_TTLS[$moduleName]);
    }

    public static function getTtl(string $moduleName): int
    {
        return self::MODULE_TTLS[$moduleName] ?? self::TTL_DEFAULT;
    }

    /**
     * Build a normalized cache key from a request URI and boosted flag.
     *
     * Sorts query parameters so ?a=1&b=2 and ?b=2&a=1 produce the same key.
     */
    public static function buildCacheKey(string $requestUri, bool $isBoosted): string
    {
        $queryString = parse_url($requestUri, PHP_URL_QUERY);
        $queryString = is_string($queryString) ? $queryString : '';
        parse_str($queryString, $params);
        ksort($params);
        $normalized = http_build_query($params);
        $normalized .= ':boosted=' . ($isBoosted ? '1' : '0');

        return md5($normalized);
    }

    /**
     * Retrieve a cached page. Returns null on miss, expiry, or error.
     */
    public static function get(string $key): ?string
    {
        $path = self::cacheFilePath($key);
        if (!is_file($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            return null;
        }

        $newlinePos = strpos($content, "\n");
        if ($newlinePos === false) {
            return null;
        }

        $expiry = (int) substr($content, 0, $newlinePos);
        if ($expiry < time()) {
            @unlink($path);
            return null;
        }

        return substr($content, $newlinePos + 1);
    }

    /**
     * Write a page to the cache. Silently fails on filesystem errors.
     */
    public static function set(string $key, string $html, int $ttl): void
    {
        if ($html === '') {
            return;
        }

        $dir = self::cacheDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
            if (!is_dir($dir)) {
                return;
            }
        }

        $expiry = time() + $ttl;
        $data = $expiry . "\n" . $html;
        @file_put_contents(self::cacheFilePath($key), $data, LOCK_EX);
    }

    /**
     * Delete all cached page files.
     */
    public static function purge(): int
    {
        $dir = self::cacheDir();
        if (!is_dir($dir)) {
            return 0;
        }

        $count = 0;
        $files = glob($dir . '/*.html');
        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Override the cache directory for testing.
     */
    public static function setTestCacheDir(?string $dir): void
    {
        self::$testCacheDir = $dir;
    }

    private static function cacheDir(): string
    {
        if (self::$testCacheDir !== null) {
            return self::$testCacheDir;
        }

        return dirname(__DIR__, 2) . '/cache/page';
    }

    private static function cacheFilePath(string $key): string
    {
        return self::cacheDir() . '/' . $key . '.html';
    }
}
