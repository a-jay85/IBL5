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
use TradeSuggestions\Engine\CandidateGenerator;
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

    /**
     * @return DatabaseCacheInterface&object{store: array<string, array<mixed>>, sets: list<string>, deletes: list<string>}
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

            public function get(string $key): ?array
            {
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

    private static function provider(DatabaseCacheInterface $cache): CachedLeagueSnapshotProvider
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

        return new CachedLeagueSnapshotProvider(self::fixedProvider(SnapshotFactory::league(4, 8)), $cache, $keys, $modes, $season);
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
