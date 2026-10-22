<?php

declare(strict_types=1);

namespace Tests\HeadToHeadRecords;

use Cache\Contracts\DatabaseCacheInterface;
use HeadToHeadRecords\CachedHeadToHeadRecordsRepository;
use HeadToHeadRecords\Contracts\HeadToHeadRecordsRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CachedHeadToHeadRecordsRepositoryTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Minimal MatrixPayload fixture for use in stubs.
     *
     * @return array{dimension: string, phase: string, scope: string, axis: list<mixed>, records: array<string, array<string, array{wins: int, losses: int}>>}
     */
    private function minimalPayload(string $dimension = 'franchises', string $phase = 'regular', string $scope = 'current'): array
    {
        return [
            'dimension' => $dimension,
            'phase'     => $phase,
            'scope'     => $scope,
            'axis'      => [],
            'records'   => [],
        ];
    }

    // ---------------------------------------------------------------------------
    // Cache-hit: inner repository is never called
    // ---------------------------------------------------------------------------

    /**
     * When the cache already holds a value for the requested key, the inner
     * repository must not be called at all.
     */
    public function testCacheHitSkipsInnerRepository(): void
    {
        $payload = $this->minimalPayload('franchises', 'regular', 'current');

        $cache = new H2hInMemoryCache();
        $cache->set('h2h_records:franchises:regular:current', $payload, 86400);

        $inner = $this->createMock(HeadToHeadRecordsRepositoryInterface::class);
        $inner->expects($this->never())->method('buildFranchisesMatrix');
        $inner->expects($this->never())->method('buildTeamsMatrix');
        $inner->expects($this->never())->method('buildGmsMatrix');

        $repo = new CachedHeadToHeadRecordsRepository($inner, $cache);
        $result = $repo->buildFranchisesMatrix('regular', 'current');

        $this->assertSame($payload, $result);
    }

    // ---------------------------------------------------------------------------
    // Cache-miss: inner called exactly once, result stored with correct TTL
    // ---------------------------------------------------------------------------

    /**
     * On a cache miss, the inner repository is called once and the result is
     * stored with a TTL of exactly 86 400 seconds (one day).
     */
    public function testCacheMissCallsInnerAndStoresWithDayTtl(): void
    {
        $payload = $this->minimalPayload('teams', 'playoffs', 'all');

        $cache = new H2hInMemoryCache();

        $inner = $this->createMock(HeadToHeadRecordsRepositoryInterface::class);
        $inner->expects($this->once())
            ->method('buildTeamsMatrix')
            ->with('playoffs', 'all')
            ->willReturn($payload);

        $repo = new CachedHeadToHeadRecordsRepository($inner, $cache);
        $result = $repo->buildTeamsMatrix('playoffs', 'all');

        $this->assertSame($payload, $result);

        // The key must be stored in the cache with the correct TTL.
        $stored = $cache->getStoredEntry('h2h_records:teams:playoffs:all');
        $this->assertNotNull($stored);
        $this->assertSame(86400, $stored['ttl']);
    }

    // ---------------------------------------------------------------------------
    // rebuildCache writes all 24 keys
    // ---------------------------------------------------------------------------

    /**
     * rebuildCache() must write exactly 24 distinct cache keys
     * (2 scopes × 3 dimensions × 4 phases).
     */
    public function testRebuildCacheWritesAllTwentyFourKeys(): void
    {
        $cache = new H2hInMemoryCache();

        $inner = self::createStub(HeadToHeadRecordsRepositoryInterface::class);
        $inner->method('buildFranchisesMatrix')->willReturn($this->minimalPayload('franchises'));
        $inner->method('buildTeamsMatrix')->willReturn($this->minimalPayload('teams'));
        $inner->method('buildGmsMatrix')->willReturn($this->minimalPayload('gms'));

        $repo = new CachedHeadToHeadRecordsRepository($inner, $cache);
        $count = $repo->rebuildCache();

        $this->assertSame(24, $count);
        $this->assertCount(24, $cache->allKeys());
    }

    // ---------------------------------------------------------------------------
    // Cache keys are distinct per dimension/phase/scope
    // ---------------------------------------------------------------------------

    /**
     * Each unique dimension/phase/scope combination produces a distinct cache key,
     * so a hit for one filter does not bleed into a different filter's lookup.
     */
    public function testCacheKeysAreDistinctPerDimensionPhaseScope(): void
    {
        $dimensions = ['franchises', 'teams', 'gms'];
        $phases     = ['heat', 'regular', 'playoffs', 'all'];
        $scopes     = ['current', 'all'];

        $keys = [];
        foreach ($scopes as $scope) {
            foreach ($dimensions as $dimension) {
                foreach ($phases as $phase) {
                    $keys[] = "h2h_records:{$dimension}:{$phase}:{$scope}";
                }
            }
        }

        // All 24 keys must be unique.
        $this->assertCount(24, array_unique($keys));

        // Verify the repository actually writes to these exact keys by seeding each
        // and confirming a hit for a specific combination returns the right payload.
        $cache = new H2hInMemoryCache();

        $franchisesRegularCurrent = $this->minimalPayload('franchises', 'regular', 'current');
        $teamsHeatAll             = $this->minimalPayload('teams', 'heat', 'all');

        $cache->set('h2h_records:franchises:regular:current', $franchisesRegularCurrent, 86400);
        $cache->set('h2h_records:teams:heat:all', $teamsHeatAll, 86400);

        $inner = $this->createMock(HeadToHeadRecordsRepositoryInterface::class);
        // Inner must NOT be called for either key since both are cache hits.
        $inner->expects($this->never())->method('buildFranchisesMatrix');
        $inner->expects($this->never())->method('buildTeamsMatrix');

        $repo = new CachedHeadToHeadRecordsRepository($inner, $cache);

        $this->assertSame($franchisesRegularCurrent, $repo->buildFranchisesMatrix('regular', 'current'));
        $this->assertSame($teamsHeatAll, $repo->buildTeamsMatrix('heat', 'all'));
    }

    // ---------------------------------------------------------------------------
    // Source fingerprint guard
    // ---------------------------------------------------------------------------

    /**
     * Stale payload stored under a fingerprint that no longer matches the live source.
     *
     * @return array<string, mixed>
     */
    private function stalePayload(string $phase): array
    {
        return [
            'dimension' => 'franchises',
            'phase'     => $phase,
            'scope'     => 'current',
            'axis'      => [],
            'records'   => ['a' => ['b' => ['wins' => 1, 'losses' => 0]]],
        ];
    }

    /**
     * Fresh payload the inner repository returns on a recompute.
     *
     * @return array<string, mixed>
     */
    private function freshPayload(): array
    {
        return [
            'axis'    => [],
            'records' => ['a' => ['b' => ['wins' => 3, 'losses' => 1]]],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function currentScopePhasesProvider(): array
    {
        return [
            'heat' => ['heat'],
            'all'  => ['all'],
        ];
    }

    /**
     * A hit stamped with a fingerprint that differs from the live one is rebuilt
     * from the inner repository and restamped with the live fingerprint.
     */
    #[DataProvider('currentScopePhasesProvider')]
    public function testStaleFingerprintHitIsRecomputedForCurrentScope(string $phase): void
    {
        $key   = 'h2h_records:franchises:' . $phase . ':current';
        $fresh = $this->freshPayload();

        $cache = new H2hInMemoryCache();
        $cache->set($key, ['fingerprint' => 'fp-stale', 'payload' => $this->stalePayload($phase)], 86400);

        $inner = new H2hFakeInnerRepository();
        $inner->payload     = $fresh;
        $inner->fingerprint = 'fp-live';

        $repo   = new CachedHeadToHeadRecordsRepository($inner, $cache);
        $result = $repo->buildFranchisesMatrix($phase, 'current');

        $this->assertSame($fresh['records'], $result['records'] ?? null);
        $this->assertSame(1, $inner->buildCalls);

        $stored = $cache->get($key);
        $this->assertIsArray($stored);
        $this->assertSame('fp-live', $stored['fingerprint'] ?? null);
        $this->assertIsArray($stored['payload'] ?? null);
        $this->assertSame($fresh['records'], $stored['payload']['records'] ?? null);
    }

    /**
     * A master-shaped bare payload (no fingerprint key) is a miss, so no cache
     * migration is needed when the envelope format deploys.
     */
    public function testLegacyBarePayloadHitIsTreatedAsMiss(): void
    {
        $fresh = $this->freshPayload();

        $cache = new H2hInMemoryCache();
        $cache->set('h2h_records:franchises:heat:current', $this->stalePayload('heat'), 86400);

        $inner = new H2hFakeInnerRepository();
        $inner->payload = $fresh;

        $repo   = new CachedHeadToHeadRecordsRepository($inner, $cache);
        $result = $repo->buildFranchisesMatrix('heat', 'current');

        $this->assertSame($fresh['records'], $result['records'] ?? null);
        $this->assertSame(1, $inner->buildCalls);
    }

    /**
     * A hit whose stamp matches the live fingerprint is served without calling
     * the inner builders, and the envelope is unwrapped to the bare payload.
     */
    public function testMatchingFingerprintHitSkipsInnerAndUnwrapsPayload(): void
    {
        $stale = $this->stalePayload('heat');

        $cache = new H2hInMemoryCache();
        $cache->set('h2h_records:franchises:heat:current', ['fingerprint' => 'fp-live', 'payload' => $stale], 86400);

        $inner = new H2hFakeInnerRepository();
        $inner->payload     = $this->freshPayload();
        $inner->fingerprint = 'fp-live';

        $repo   = new CachedHeadToHeadRecordsRepository($inner, $cache);
        $result = $repo->buildFranchisesMatrix('heat', 'current');

        $this->assertSame($stale, $result);
        $this->assertSame(0, $inner->buildCalls);
    }

    /**
     * rebuildCache() stamps all 24 entries with the live fingerprint.
     */
    public function testRebuildCacheStampsEveryEntryWithTheLiveFingerprint(): void
    {
        $fresh = $this->freshPayload();

        $cache = new H2hInMemoryCache();

        $inner = new H2hFakeInnerRepository();
        $inner->payload     = $fresh;
        $inner->fingerprint = 'fp-rebuild';

        $repo = new CachedHeadToHeadRecordsRepository($inner, $cache);
        $repo->rebuildCache();

        foreach (['current', 'all'] as $scope) {
            foreach (['franchises', 'teams', 'gms'] as $dimension) {
                foreach (['heat', 'regular', 'playoffs', 'all'] as $phase) {
                    $stored = $cache->get("h2h_records:{$dimension}:{$phase}:{$scope}");
                    $this->assertIsArray($stored);
                    $this->assertSame('fp-rebuild', $stored['fingerprint'] ?? null);
                    $this->assertIsArray($stored['payload'] ?? null);
                    $this->assertSame($fresh['records'], $stored['payload']['records'] ?? null);
                }
            }
        }

        $this->assertSame(24, $inner->buildCalls);
    }
}

// ---------------------------------------------------------------------------
// In-memory cache stub
// ---------------------------------------------------------------------------

/**
 * Simple in-memory implementation of DatabaseCacheInterface for HeadToHeadRecords tests.
 *
 * Tracks every set() call so tests can inspect stored TTLs and key lists without
 * requiring a database connection.
 */
class H2hInMemoryCache implements DatabaseCacheInterface
{
    /** @var array<string, array{data: array<mixed>, expiration: int, ttl: int}> */
    private array $store = [];

    /**
     * @return array<mixed>|null
     */
    public function get(string $key): ?array
    {
        if (!isset($this->store[$key])) {
            return null;
        }

        if ($this->store[$key]['expiration'] < time()) {
            return null;
        }

        return $this->store[$key]['data'];
    }

    /**
     * @return array<mixed>|null
     */
    public function getStale(string $key): ?array
    {
        return $this->store[$key]['data'] ?? null;
    }

    /**
     * @param array<mixed> $data
     */
    public function set(string $key, array $data, int $ttlSeconds): void
    {
        $this->store[$key] = [
            'data'       => $data,
            'expiration' => time() + $ttlSeconds,
            'ttl'        => $ttlSeconds,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }

    /**
     * Return the raw stored entry so tests can inspect the TTL.
     *
     * @return array{data: array<mixed>, expiration: int, ttl: int}|null
     */
    public function getStoredEntry(string $key): ?array
    {
        return $this->store[$key] ?? null;
    }

    /**
     * Return all stored cache keys.
     *
     * @return list<string>
     */
    public function allKeys(): array
    {
        return array_keys($this->store);
    }
}

// ---------------------------------------------------------------------------
// Inner repository fake
// ---------------------------------------------------------------------------

/**
 * Inner-repository fake: returns one configurable payload for every builder,
 * counts builder calls, and reports a configurable source fingerprint.
 */
class H2hFakeInnerRepository implements HeadToHeadRecordsRepositoryInterface
{
    public string $fingerprint = 'fp-live';
    public int $buildCalls = 0;
    /** @var array<string, mixed> */
    public array $payload = [];

    /** @return array<string, mixed> */
    public function buildFranchisesMatrix(string $phase, string $scope): array
    {
        $this->buildCalls++;
        return $this->payload + ['dimension' => 'franchises', 'phase' => $phase, 'scope' => $scope];
    }

    /** @return array<string, mixed> */
    public function buildTeamsMatrix(string $phase, string $scope): array
    {
        $this->buildCalls++;
        return $this->payload + ['dimension' => 'teams', 'phase' => $phase, 'scope' => $scope];
    }

    /** @return array<string, mixed> */
    public function buildGmsMatrix(string $phase, string $scope): array
    {
        $this->buildCalls++;
        return $this->payload + ['dimension' => 'gms', 'phase' => $phase, 'scope' => $scope];
    }

    public function currentSeasonHasGames(): bool
    {
        return true;
    }

    public function sourceFingerprint(): string
    {
        return $this->fingerprint;
    }
}
