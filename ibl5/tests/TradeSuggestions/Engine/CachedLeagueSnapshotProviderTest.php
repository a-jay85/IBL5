<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use Cache\Contracts\DatabaseCacheInterface;
use PHPUnit\Framework\TestCase;
use ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use Trading\Contracts\TradeFormRepositoryInterface;
use TradeSuggestions\Contracts\SuggestionAssetRepositoryInterface;
use TradeSuggestions\Contracts\TeamModeRepositoryInterface;
use TradeSuggestions\Engine\CachedLeagueSnapshotProvider;
use TradeSuggestions\Engine\Contracts\LeagueSnapshotProviderInterface;
use TradeSuggestions\Engine\Contracts\SnapshotKeyRepositoryInterface;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\ModeResolver;
use TradeSuggestions\Engine\SnapshotCacheKey;
use TradeSuggestions\Engine\SnapshotCodec;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\TeamModeGuesser;
use TradeSuggestions\TeamModeService;
use TradeSuggestions\TeamModeSetting;

final class CachedLeagueSnapshotProviderTest extends TestCase
{
    private static ?LeagueSnapshot $league = null;

    private static function league(): LeagueSnapshot
    {
        return self::$league ??= SnapshotFactory::league(4, 8);
    }

    public function testColdCallBuildsAndWrites(): void
    {
        $cache = new InMemorySnapshotCache();
        $inner = new CountingSnapshotProvider(self::league());
        $provider = $this->provider($inner, $cache);

        $provider->snapshot();

        self::assertSame(1, $inner->calls);
        self::assertCount(1, $cache->sets);
        self::assertSame($provider->lastKey(), $cache->sets[0]['key']);
        self::assertSame(3600, $cache->sets[0]['ttl']);
    }

    public function testWarmCallNeverTouchesInner(): void
    {
        $cache = new InMemorySnapshotCache();
        $inner = new CountingSnapshotProvider(self::league());
        $provider = $this->provider($inner, $cache);

        $first = $provider->snapshot();
        $second = $provider->snapshot();

        self::assertSame(1, $inner->calls);
        self::assertEquals($first, $second);
    }

    public function testCorruptCachedPayloadIsAMiss(): void
    {
        $cache = new InMemorySnapshotCache();
        $inner = new CountingSnapshotProvider(self::league());
        $provider = $this->provider($inner, $cache);
        $key = $provider->currentKey();
        $cache->store[$key] = ['v' => 0];

        $provider->snapshot();

        self::assertSame(1, $inner->calls);
        self::assertSame($key, $cache->sets[0]['key'] ?? null);
        self::assertNotSame(['v' => 0], $cache->get($key));
    }

    public function testPayloadThatFailsATypedConstructorIsAMiss(): void
    {
        $cache = new InMemorySnapshotCache();
        $inner = new CountingSnapshotProvider(self::league());
        $provider = $this->provider($inner, $cache);
        $key = $provider->currentKey();
        $payload = SnapshotCodec::encode(self::league());
        $payload['draftOrder']['round1'] = [['teamId' => []]];
        $cache->store[$key] = $payload;

        $snapshot = $provider->snapshot();

        self::assertSame(1, $inner->calls);
        self::assertSame($key, $cache->sets[0]['key'] ?? null);
        self::assertSame(self::league(), $snapshot);
    }

    public function testKeyIsBuiltFromPhaseSimNumberDigestTradeMarkerAndModes(): void
    {
        $modeRepo = self::createStub(TeamModeRepositoryInterface::class);
        $modeRepo->method('getAllStoredSettings')->willReturn([]);
        $season = new \Tests\WideUnit\Mocks\Season(self::createStub(\mysqli::class));
        $season->endingYear = 2027;
        $season->lastSimNumber = 4;
        $keys = self::createStub(SnapshotKeyRepositoryInterface::class);
        $keys->method('rosterDigest')->willReturn('digest');
        $keys->method('tradeMarker')->willReturn('2026-11-01 10:00:00');
        $provider = new CachedLeagueSnapshotProvider(
            new CountingSnapshotProvider(self::league()),
            new InMemorySnapshotCache(),
            $keys,
            $this->modeResolver($modeRepo, $season),
            $season,
        );

        $expected = SnapshotCacheKey::build(
            'Regular Season:4',
            'Regular Season',
            'digest',
            '2026-11-01 10:00:00',
            $this->modeResolver($modeRepo, $season)->resolveAll(),
        );

        self::assertSame($expected, $provider->currentKey());
    }

    public function testModeChangeMissesTheCache(): void
    {
        $cache = new InMemorySnapshotCache();
        $inner = new CountingSnapshotProvider(self::league());
        $modeRepo = self::createStub(TeamModeRepositoryInterface::class);
        $modeRepo->method('getAllStoredSettings')->willReturnOnConsecutiveCalls([], [2 => TeamModeSetting::Rebuild]);
        $provider = $this->provider($inner, $cache, modeRepo: $modeRepo);

        $provider->snapshot();
        $provider->snapshot();

        self::assertSame(2, $inner->calls);
    }

    public function testTradeMarkerChangeMissesTheCache(): void
    {
        $cache = new InMemorySnapshotCache();
        $inner = new CountingSnapshotProvider(self::league());
        $keys = self::createStub(SnapshotKeyRepositoryInterface::class);
        $keys->method('rosterDigest')->willReturn('digest');
        $keys->method('tradeMarker')->willReturnOnConsecutiveCalls('2026-11-01 10:00:00', '2026-11-01 10:05:00');
        $provider = $this->provider($inner, $cache, keys: $keys);

        $provider->snapshot();
        $provider->snapshot();

        self::assertSame(2, $inner->calls);
    }

    public function testLastKeyIsNullBeforeFirstSnapshotAndSetAfter(): void
    {
        $cache = new InMemorySnapshotCache();
        $provider = $this->provider(new CountingSnapshotProvider(self::league()), $cache);

        self::assertNull($provider->lastKey());
        $provider->snapshot();

        self::assertSame($cache->sets[0]['key'], $provider->lastKey());
    }

    public function testCurrentKeyEqualsTheKeySnapshotUses(): void
    {
        $cache = new InMemorySnapshotCache();
        $inner = new CountingSnapshotProvider(self::league());
        $provider = $this->provider($inner, $cache);

        $key = $provider->currentKey();
        self::assertSame(0, $cache->gets);
        self::assertSame(0, $inner->calls);
        self::assertNull($cache->get($key));

        $provider->snapshot();

        self::assertSame(2, $cache->gets);
        self::assertSame($key, $provider->lastKey());
        self::assertNotNull($cache->get($key));
    }

    public function testProviderHoldsNoTeamId(): void
    {
        $class = new \ReflectionClass(CachedLeagueSnapshotProvider::class);
        $banned = ['tid', 'teamId', 'userTid'];

        foreach ($class->getConstructor()?->getParameters() ?? [] as $param) {
            self::assertNotContains($param->getName(), $banned);
        }
        foreach ($class->getProperties() as $property) {
            self::assertNotContains($property->getName(), $banned);
        }
        self::assertGreaterThan(0, count($class->getProperties()));
    }

    private function provider(
        LeagueSnapshotProviderInterface $inner,
        DatabaseCacheInterface $cache,
        ?SnapshotKeyRepositoryInterface $keys = null,
        ?TeamModeRepositoryInterface $modeRepo = null,
    ): CachedLeagueSnapshotProvider {
        if ($keys === null) {
            $keys = self::createStub(SnapshotKeyRepositoryInterface::class);
            $keys->method('rosterDigest')->willReturn('digest');
            $keys->method('tradeMarker')->willReturn('2026-11-01 10:00:00');
        }
        if ($modeRepo === null) {
            $modeRepo = self::createStub(TeamModeRepositoryInterface::class);
            $modeRepo->method('getAllStoredSettings')->willReturn([]);
        }
        $season = new \Tests\WideUnit\Mocks\Season(self::createStub(\mysqli::class));
        $season->endingYear = 2027;
        $season->lastSimNumber = 4;

        return new CachedLeagueSnapshotProvider($inner, $cache, $keys, $this->modeResolver($modeRepo, $season), $season);
    }

    private function modeResolver(TeamModeRepositoryInterface $modeRepo, \Season\Season $season): ModeResolver
    {
        $assets = self::createStub(SuggestionAssetRepositoryInterface::class);
        $assets->method('getRostersForTeams')->willReturn([1 => self::rotation(), 2 => self::rotation()]);
        $assets->method('getRegularSeasonRecords')->willReturn([]);

        $draftOrder = self::createStub(ProjectedDraftOrderServiceInterface::class);
        $draftOrder->method('calculateDraftOrder')->willReturn([
            'round1' => [self::slot(1), self::slot(2)],
            'round2' => [],
        ]);

        $formRepo = self::createStub(TradeFormRepositoryInterface::class);
        $formRepo->method('getAllTeamsWithCity')->willReturn([
            ['teamid' => 1, 'team_name' => 'One', 'team_city' => 'A', 'color1' => '000000', 'color2' => 'FFFFFF'],
            ['teamid' => 2, 'team_name' => 'Two', 'team_city' => 'B', 'color1' => '000000', 'color2' => 'FFFFFF'],
        ]);

        return new ModeResolver(
            $assets,
            $draftOrder,
            $modeRepo,
            $formRepo,
            new TeamModeService(self::createStub(TeamModeRepositoryInterface::class), new TeamModeGuesser()),
            $season,
        );
    }

    /**
     * @return list<SuggestionPlayer>
     */
    private static function rotation(): array
    {
        $players = [];
        foreach ([1, 2] as $i) {
            $players[] = new SuggestionPlayer(
                pid: $i,
                name: 'Player ' . $i,
                pos: 'SF',
                age: $i === 1 ? 26 : 27,
                injured: false,
                currentSalary: 1000,
                yearsRemaining: $i === 1 ? 2 : 1,
                futureSalary: 0,
                dcMinutes: 30,
            );
        }

        return $players;
    }

    /**
     * A .500 record, so an Auto team resolves Middle and a stored override changes the mode.
     *
     * @return array{pick: int, teamId: int, teamName: string, wins: int, losses: int, color1: string, color2: string, ownerId: int, ownerName: string, ownerColor1: string, ownerColor2: string, isTraded: bool, notes: string, movement: int|null, player: string}
     */
    private static function slot(int $teamId): array
    {
        return [
            'pick' => $teamId, 'teamId' => $teamId, 'teamName' => 'T' . $teamId, 'wins' => 41, 'losses' => 41,
            'color1' => '000000', 'color2' => 'FFFFFF', 'ownerId' => $teamId, 'ownerName' => 'T' . $teamId,
            'ownerColor1' => '000000', 'ownerColor2' => 'FFFFFF', 'isTraded' => false, 'notes' => '', 'movement' => null, 'player' => '',
        ];
    }
}

/**
 * Array-backed cache that records every `get` and `set`.
 */
final class InMemorySnapshotCache implements DatabaseCacheInterface
{
    /** @var array<string, array<mixed>> */
    public array $store = [];
    /** @var list<array{key: string, ttl: int}> */
    public array $sets = [];
    public int $gets = 0;

    public function get(string $key): ?array
    {
        $this->gets++;

        return $this->store[$key] ?? null;
    }

    public function set(string $key, array $data, int $ttlSeconds): void
    {
        $this->sets[] = ['key' => $key, 'ttl' => $ttlSeconds];
        $this->store[$key] = json_decode(json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true, 512, JSON_THROW_ON_ERROR);
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }

    public function getStale(string $key): ?array
    {
        return $this->store[$key] ?? null;
    }
}

/**
 * Returns a fixed snapshot and counts calls.
 */
final class CountingSnapshotProvider implements LeagueSnapshotProviderInterface
{
    public int $calls = 0;

    public function __construct(private readonly LeagueSnapshot $snapshot)
    {
    }

    public function snapshot(): LeagueSnapshot
    {
        $this->calls++;

        return $this->snapshot;
    }

    public function lastKey(): ?string
    {
        return null;
    }
}
