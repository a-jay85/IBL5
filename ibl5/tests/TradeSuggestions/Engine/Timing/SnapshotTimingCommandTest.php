<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine\Timing;

use Cache\Contracts\DatabaseCacheInterface;
use PHPUnit\Framework\TestCase;
use ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface;
use TradeSuggestions\Engine\Contracts\LeagueSnapshotProviderInterface;
use TradeSuggestions\Engine\LeagueSnapshot;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use Trading\Contracts\TradeFormRepositoryInterface;
use TradeSuggestions\Contracts\SuggestionAssetRepositoryInterface;
use TradeSuggestions\Contracts\TeamModeRepositoryInterface;
use TradeSuggestions\Engine\CachedLeagueSnapshotProvider;
use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\CandidateBudget;
use TradeSuggestions\Engine\CandidateGenerator;
use TradeSuggestions\Engine\DealScorer;
use TradeSuggestions\Engine\PairRecordMemo;
use TradeSuggestions\Engine\Shape\Contracts\DealShapeInterface;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Engine\Contracts\SnapshotKeyRepositoryInterface;
use TradeSuggestions\Engine\ModeResolver;
use TradeSuggestions\Engine\Timing\SnapshotTimingCommand;
use TradeSuggestions\TeamModeGuesser;
use TradeSuggestions\TeamModeService;

final class SnapshotTimingCommandTest extends TestCase
{
    private const array PARSED = ['tid' => 1, 'repeat' => 2, 'host' => 'db', 'db' => 'league', 'user' => 'u', 'pass' => 'p'];

    public function testOutputCarriesEveryEvidenceKey(): void
    {
        $cache = self::recordingCache();
        $provider = self::provider($cache);
        $out = fopen('php://memory', 'w+');
        self::assertIsResource($out);

        $code = (new SnapshotTimingCommand($provider, new CandidateGenerator([]), $cache))->run(self::PARSED, $out);

        rewind($out);
        $text = (string) stream_get_contents($out);
        self::assertSame(0, $code);
        foreach (['cold_snapshot_s', 'warm_snapshot_s', 'generate_score_s', 'raw', 'legal', 'capped', 'partners', 'memo_entries', 'TOTAL_WARM_S', 'TOTAL_COLD_S'] as $name) {
            self::assertMatchesRegularExpression('/(^|\s)' . $name . '=[0-9.]+/', $text, $name);
        }
    }

    public function testCacheKeyIsDeletedBeforeAndAfterTheRun(): void
    {
        $cache = self::recordingCache();
        $provider = self::provider($cache);
        $out = fopen('php://memory', 'w+');
        self::assertIsResource($out);

        (new SnapshotTimingCommand($provider, new CandidateGenerator([]), $cache))->run(self::PARSED, $out);

        $key = $provider->currentKey();
        self::assertSame([$key, $key], $cache->deletes);
        self::assertSame([], $cache->store, 'the run leaves no snapshot in the shared cache table');
        self::assertSame([$key], $cache->sets, 'the cold snapshot was written once before the exit delete');
    }

    public function testCacheKeyIsDeletedEvenWhenTheRunThrows(): void
    {
        $cache = self::recordingCache();
        $failing = new class implements LeagueSnapshotProviderInterface {
            public function snapshot(): LeagueSnapshot
            {
                throw new \RuntimeException('snapshot build failed');
            }

            public function lastKey(): ?string
            {
                return null;
            }
        };
        $provider = self::provider($cache, $failing);
        $out = fopen('php://memory', 'w+');
        self::assertIsResource($out);

        try {
            (new SnapshotTimingCommand($provider, new CandidateGenerator([]), $cache))->run(self::PARSED, $out);
            self::fail('the run did not rethrow');
        } catch (\RuntimeException $e) {
            self::assertSame('snapshot build failed', $e->getMessage());
        }

        $key = $provider->currentKey();
        self::assertSame([$key, $key], $cache->deletes);
    }

    public function testSnapshotIsTimedColdOnceWarmRepeatTimesAndOnceMoreForTheGenerator(): void
    {
        $cache = self::recordingCache();
        $provider = self::provider($cache);
        $out = fopen('php://memory', 'w+');
        self::assertIsResource($out);

        (new SnapshotTimingCommand($provider, new CandidateGenerator([]), $cache))->run(self::PARSED, $out);

        self::assertSame(self::PARSED['repeat'] + 2, $cache->gets);
    }

    public function testReportsGeneratorCountsAndTheSimKeyItGenerateWith(): void
    {
        $snapshot = SnapshotFactory::league(28, 13);
        $cache = self::recordingCache();
        $provider = self::provider($cache, self::fixedProvider($snapshot));
        $shape = self::swapShape(0);
        $out = fopen('php://memory', 'w+');
        self::assertIsResource($out);

        (new SnapshotTimingCommand($provider, new CandidateGenerator([$shape]), $cache))->run(self::PARSED, $out);

        $expected = (new CandidateGenerator([$shape]))->generate($snapshot, 1, $provider->currentKey());
        $withoutKey = (new CandidateGenerator([$shape]))->generate($snapshot, 1, '');
        $memo = new PairRecordMemo();
        DealScorer::scoreAll($snapshot, $expected, $expected->shapeIdByKey, $memo);
        self::assertNotSame(count($withoutKey->partners), count($expected->partners), 'the fixture must tell the sim key apart from an empty one');
        self::assertGreaterThan(0, $memo->size());
        self::assertGreaterThan(0, count($expected->candidates));
        rewind($out);
        $text = (string) stream_get_contents($out);
        self::assertMatchesRegularExpression(
            sprintf(
                '/^cold_snapshot_s=[0-9.]+ warm_snapshot_s=[0-9.]+ generate_score_s=[0-9.]+ raw=%d legal=%d capped=0 partners=%d memo_entries=%d$/m',
                $expected->rawCount,
                count($expected->candidates),
                count($expected->partners),
                $memo->size(),
            ),
            $text,
        );
    }

    public function testReportsCappedRunAsOne(): void
    {
        $cache = self::recordingCache();
        $provider = self::provider($cache);
        $out = fopen('php://memory', 'w+');
        self::assertIsResource($out);
        $flood = self::swapShape(CandidateBudget::MAX_RAW_CANDIDATES + 1000);

        (new SnapshotTimingCommand($provider, new CandidateGenerator([$flood]), $cache))->run([...self::PARSED, 'repeat' => 1], $out);

        rewind($out);
        self::assertMatchesRegularExpression(
            '/ raw=' . CandidateBudget::MAX_RAW_CANDIDATES . ' legal=0 capped=1 partners=\d+ memo_entries=0$/m',
            (string) stream_get_contents($out),
        );
    }

    public function testZeroRepeatPrintsEmptyCountsAndNoGeneratorRuns(): void
    {
        $cache = self::recordingCache();
        $provider = self::provider($cache);
        $out = fopen('php://memory', 'w+');
        self::assertIsResource($out);
        $calls = 0;
        $shape = self::countingShape($calls);

        (new SnapshotTimingCommand($provider, new CandidateGenerator([$shape]), $cache))->run([...self::PARSED, 'repeat' => 0], $out);

        rewind($out);
        $text = (string) stream_get_contents($out);
        self::assertSame(0, $calls);
        self::assertSame(2, $cache->gets);
        self::assertMatchesRegularExpression('/ warm_snapshot_s=INF generate_score_s=INF raw=0 legal=0 capped=0 partners=0 memo_entries=0$/m', $text);
    }

    public function testTotalsAreTheColdOrWarmSnapshotPlusTheGeneratorTime(): void
    {
        $cache = self::recordingCache();
        $provider = self::provider($cache);
        $out = fopen('php://memory', 'w+');
        self::assertIsResource($out);
        $slow = self::swapShape(0, sleepMicros: 20000);

        (new SnapshotTimingCommand($provider, new CandidateGenerator([$slow]), $cache))->run(self::PARSED, $out);

        rewind($out);
        $text = (string) stream_get_contents($out);
        self::assertSame(1, preg_match('/cold_snapshot_s=([0-9.]+) warm_snapshot_s=([0-9.]+) generate_score_s=([0-9.]+)/', $text, $m));
        self::assertSame(1, preg_match('/^TOTAL_WARM_S=([0-9.]+)$/m', $text, $warm));
        self::assertSame(1, preg_match('/^TOTAL_COLD_S=([0-9.]+)$/m', $text, $cold));
        self::assertGreaterThan(0.015, (float) $m[3]);
        self::assertLessThan(100.0, (float) $m[1], 'cold seconds are an elapsed time, not a sum of clock readings');
        self::assertEqualsWithDelta((float) $m[2] + (float) $m[3], (float) $warm[1], 0.0003);
        self::assertEqualsWithDelta((float) $m[1] + (float) $m[3], (float) $cold[1], 0.0003);
    }

    /**
     * A shape yielding a one-for-one swap of the first pool players on each side
     * for every partner, or `$flood` distinct unowned-asset candidates.
     */
    private static function swapShape(int $flood, int $sleepMicros = 0): DealShapeInterface
    {
        return new class ($flood, $sleepMicros) implements DealShapeInterface {
            public function __construct(private readonly int $count, private readonly int $sleepMicros)
            {
            }

            public function id(): string
            {
                return 'swap';
            }

            public function generate(LeagueSnapshot $s, int $userTid, int $partnerTid, AssetPool $pool): iterable
            {
                if ($this->sleepMicros > 0) {
                    usleep($this->sleepMicros);
                }
                if ($this->count > 0) {
                    for ($i = 1; $i <= $this->count; $i++) {
                        yield new TradeCandidate($userTid, $partnerTid, [], [], [], [900000 + $i]);
                    }

                    return;
                }
                yield new TradeCandidate($userTid, $partnerTid, [$pool->userPids[0]], [], [$pool->partnerPids[0]], []);
            }
        };
    }

    private static function countingShape(int &$calls): DealShapeInterface
    {
        return new class ($calls) implements DealShapeInterface {
            public function __construct(private int &$calls)
            {
            }

            public function id(): string
            {
                return 'count';
            }

            public function generate(LeagueSnapshot $s, int $userTid, int $partnerTid, AssetPool $pool): iterable
            {
                $this->calls++;

                return [];
            }
        };
    }

    /**
     * @return DatabaseCacheInterface&object{store: array<string, array<mixed>>, sets: list<string>, deletes: list<string>, gets: int}
     */
    private static function recordingCache(): DatabaseCacheInterface
    {
        return new class implements DatabaseCacheInterface {
            /** @var array<string, array<mixed>> */
            public array $store = [];
            /** @var list<string> */
            public array $sets = [];
            /** @var list<string> */
            public array $deletes = [];
            public int $gets = 0;

            public function get(string $key): ?array
            {
                $this->gets++;

                return $this->store[$key] ?? null;
            }

            public function set(string $key, array $data, int $ttlSeconds): void
            {
                $this->sets[] = $key;
                $this->store[$key] = json_decode(json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true, 512, JSON_THROW_ON_ERROR);
            }

            public function delete(string $key): void
            {
                $this->deletes[] = $key;
                unset($this->store[$key]);
            }

            public function getStale(string $key): ?array
            {
                return $this->store[$key] ?? null;
            }
        };
    }

    private static function provider(DatabaseCacheInterface $cache, ?LeagueSnapshotProviderInterface $inner = null): CachedLeagueSnapshotProvider
    {
        $keys = self::createStub(SnapshotKeyRepositoryInterface::class);
        $keys->method('rosterDigest')->willReturn('digest');
        $keys->method('tradeMarker')->willReturn('2026-11-01 10:00:00');
        $modeRepo = self::createStub(TeamModeRepositoryInterface::class);
        $modeRepo->method('getAllStoredSettings')->willReturn([]);
        $season = new \Tests\WideUnit\Mocks\Season(self::createStub(\mysqli::class));
        $season->endingYear = 2027;
        $season->lastSimNumber = 4;

        $assets = self::createStub(SuggestionAssetRepositoryInterface::class);
        $assets->method('getRostersForTeams')->willReturn([]);
        $assets->method('getRegularSeasonRecords')->willReturn([]);
        $draftOrder = self::createStub(ProjectedDraftOrderServiceInterface::class);
        $draftOrder->method('calculateDraftOrder')->willReturn(['round1' => [], 'round2' => []]);
        $formRepo = self::createStub(TradeFormRepositoryInterface::class);
        $formRepo->method('getAllTeamsWithCity')->willReturn([]);
        $modes = new ModeResolver(
            $assets,
            $draftOrder,
            $modeRepo,
            $formRepo,
            new TeamModeService(self::createStub(TeamModeRepositoryInterface::class), new TeamModeGuesser()),
            $season,
        );

        return new CachedLeagueSnapshotProvider($inner ?? self::fixedProvider(SnapshotFactory::league(4, 8)), $cache, $keys, $modes, $season);
    }

    private static function fixedProvider(LeagueSnapshot $snapshot): LeagueSnapshotProviderInterface
    {
        return new class ($snapshot) implements LeagueSnapshotProviderInterface {
            public function __construct(private readonly LeagueSnapshot $snapshot)
            {
            }

            public function snapshot(): LeagueSnapshot
            {
                return $this->snapshot;
            }

            public function lastKey(): ?string
            {
                return null;
            }
        };
    }
}
