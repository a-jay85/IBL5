<?php

declare(strict_types=1);

namespace Tests\Standings;

use Eris\Generators;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Standings\AggregateTiebreaker;
use Tests\Support\PinnedErisSeed;

#[CoversClass(AggregateTiebreaker::class)]
final class AggregateTiebreakerPropertyTest extends TestCase
{
    use PinnedErisSeed;

    /**
     * Builds a head-to-head matrix for teams 1..$teamCount. Every game has one
     * winner and one loser, so record(a, b) mirrors record(b, a). The matrix
     * comes from an object-scoped Mt19937 so it never touches the global
     * mt_rand state that Eris draws from.
     *
     * @return array<int, array<int, array{wins: int, losses: int}>>
     */
    private static function buildMirroredMatrix(int $teamCount, int $matrixSeed): array
    {
        $random = new Randomizer(new Mt19937($matrixSeed));
        $matrix = [];
        for ($i = 1; $i <= $teamCount; $i++) {
            for ($j = $i + 1; $j <= $teamCount; $j++) {
                $games = $random->getInt(0, 4);
                $wins = $random->getInt(0, $games);
                $matrix[$i][$j] = ['wins' => $wins, 'losses' => $games - $wins];
                $matrix[$j][$i] = ['wins' => $games - $wins, 'losses' => $wins];
            }
        }
        return $matrix;
    }

    public function testAggregateWinsBalanceLossesAcrossGroup(): void
    {
        $this->pinErisSeed(150);
        $this->forAll(Generators::choose(2, 6), Generators::choose(0, 2147483647))
            ->then(function (int $teamCount, int $matrixSeed): void {
                $matrix = self::buildMirroredMatrix($teamCount, $matrixSeed);
                $ids = range(1, $teamCount);
                $pcts = AggregateTiebreaker::computeAggregateH2HPcts(
                    $ids,
                    static fn (int $a, int $b): array => $matrix[$a][$b],
                );
                $weightedWins = 0.0;
                $totalGames = 0;
                foreach ($ids as $tid) {
                    $games = 0;
                    foreach ($ids as $opp) {
                        if ($opp !== $tid) {
                            $games += $matrix[$tid][$opp]['wins'] + $matrix[$tid][$opp]['losses'];
                        }
                    }
                    $weightedWins += $pcts[$tid] * $games;
                    $totalGames += $games;
                }
                self::assertEqualsWithDelta($totalGames / 2, $weightedWins, 1e-9, 'group wins do not equal group losses');
            });
    }

    public function testAggregatePctsIgnoreTeamOrder(): void
    {
        $this->pinErisSeed(150);
        $this->forAll(Generators::choose(2, 6), Generators::choose(0, 2147483647))
            ->then(function (int $teamCount, int $matrixSeed): void {
                $matrix = self::buildMirroredMatrix($teamCount, $matrixSeed);
                $pair = static fn (int $a, int $b): array => $matrix[$a][$b];
                $forward = AggregateTiebreaker::computeAggregateH2HPcts(range(1, $teamCount), $pair);
                $reversed = AggregateTiebreaker::computeAggregateH2HPcts(range($teamCount, 1), $pair);
                ksort($reversed);
                self::assertSame($forward, $reversed);
            });
    }
}
