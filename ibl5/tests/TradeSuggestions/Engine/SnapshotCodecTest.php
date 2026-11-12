<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use TradeSuggestions\Engine\LeagueSnapshotBuilder;
use TradeSuggestions\Engine\SnapshotCodec;

/**
 * Decode strictness: every malformed field in a cached payload must surface as
 * an `UnexpectedValueException`, which `CachedLeagueSnapshotProvider` treats as
 * a cache miss. A TypeError or silent coercion would be a different outcome.
 */
final class SnapshotCodecTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private static ?array $payload = null;

    /**
     * @return array<string, mixed>
     */
    private static function payload(): array
    {
        return self::$payload ??= SnapshotCodec::encode(LeagueSnapshotBuilder::build(SnapshotFactory::inputs(2, 8)));
    }

    private static function firstPid(): int
    {
        $players = self::payload()['players'];
        self::assertIsArray($players);
        $pid = array_key_first($players);
        self::assertIsInt($pid);

        return $pid;
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $mutate
     */
    private function assertRejected(callable $mutate): void
    {
        $payload = $mutate(self::payload());

        $this->expectException(\UnexpectedValueException::class);
        SnapshotCodec::decode($payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @param callable(array<mixed>): array<mixed> $mutate
     * @return array<string, mixed>
     */
    private static function withPlayer(array $payload, callable $mutate): array
    {
        $players = $payload['players'];
        self::assertIsArray($players);
        $pid = self::firstPid();
        $players[$pid] = $mutate(is_array($players[$pid]) ? $players[$pid] : []);
        $payload['players'] = $players;

        return $payload;
    }

    public function testTeamIdsDecodeAsAListWhateverTheStoredKeys(): void
    {
        $payload = self::payload();
        $teamIds = $payload['teamIds'];
        self::assertIsArray($teamIds);
        $payload['teamIds'] = array_combine(array_map(static fn (int $i): int => $i + 10, array_keys($teamIds)), $teamIds);

        $decoded = SnapshotCodec::decode($payload);

        self::assertSame(array_values($teamIds), $decoded->teamIds);
    }

    public function testNonIntTeamIdIsRejected(): void
    {
        $this->assertRejected(static function (array $p): array {
            $p['teamIds'] = ['x'];

            return $p;
        });
    }

    public function testNonIntPlayerKeyIsRejected(): void
    {
        $this->assertRejected(function (array $p): array {
            $players = $p['players'];
            self::assertIsArray($players);
            $p['players'] = ['abc' => $players[self::firstPid()]];

            return $p;
        });
    }

    public function testNonArrayPlayerRowIsRejected(): void
    {
        $this->assertRejected(function (array $p): array {
            $players = $p['players'];
            self::assertIsArray($players);
            $players[self::firstPid()] = 'x';
            $p['players'] = $players;

            return $p;
        });
    }

    public function testNonIntSeasonYearIsRejected(): void
    {
        $this->assertRejected(static function (array $p): array {
            $p['seasonYear'] = '2026';

            return $p;
        });
    }

    public function testNonBoolRegularSeasonIsRejected(): void
    {
        $this->assertRejected(static function (array $p): array {
            $p['regularSeason'] = 1;

            return $p;
        });
    }

    public function testNonStringPlayerNameIsRejected(): void
    {
        $this->assertRejected(fn (array $p): array => self::withPlayer($p, static function (array $row): array {
            $row['name'] = 5;

            return $row;
        }));
    }

    public function testNonIntPrefsValueIsRejected(): void
    {
        $this->assertRejected(fn (array $p): array => self::withPlayer($p, static function (array $row): array {
            $row['prefs'] = ['loyalty' => 'x'];

            return $row;
        }));
    }

    public function testNonIntCashValueIsRejected(): void
    {
        $this->assertRejected(static function (array $p): array {
            $p['cash'] = [1 => [0 => 'x']];

            return $p;
        });
    }

    public function testNonNumericResignLikelihoodIsRejected(): void
    {
        $this->assertRejected(function (array $p): array {
            $p['resignLikelihood'] = [self::firstPid() => 'x'];

            return $p;
        });
    }

    public function testIntAndFloatResignLikelihoodDecodeAsFloats(): void
    {
        $pid = self::firstPid();
        $payload = self::payload();
        $payload['resignLikelihood'] = [$pid => 1, $pid + 1 => 0.25];

        $decoded = SnapshotCodec::decode($payload);

        self::assertSame(1.0, $decoded->resignLikelihood[$pid]);
        self::assertSame(0.25, $decoded->resignLikelihood[$pid + 1]);
    }
}
