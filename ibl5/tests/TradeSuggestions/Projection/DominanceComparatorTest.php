<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Projection;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Projection\Dominance;
use TradeSuggestions\Projection\DominanceComparator;
use TradeSuggestions\Projection\Rating;
use TradeSuggestions\Projection\SlotRelevance;
use TradeSuggestions\SlotMapper;

final class DominanceComparatorTest extends TestCase
{
    public function testBetterWhenAWinsAllRelevantRatings(): void
    {
        $a = ProjectionFactory::ratingSet(['r_tvr' => 40.0, 'r_foul' => 40.0], 60.0);
        $b = ProjectionFactory::ratingSet([], 50.0);

        self::assertSame(Dominance::Better, DominanceComparator::compare($a, $b, 'PG'));
        self::assertSame(['wins' => 13, 'losses' => 0], DominanceComparator::record($a, $b, 'PG'));
    }

    public function testWorseIsMirrorOfBetter(): void
    {
        $a = ProjectionFactory::ratingSet(['r_tvr' => 40.0, 'r_foul' => 40.0], 60.0);
        $b = ProjectionFactory::ratingSet([], 50.0);

        self::assertSame(Dominance::Worse, DominanceComparator::compare($b, $a, 'PG'));
        self::assertSame(Dominance::Worse, Dominance::Better->mirror());
        self::assertSame(Dominance::Mixed, Dominance::Mixed->mirror());
    }

    public function testMixedWhenAllRatingsInsideTolerance(): void
    {
        $a = ProjectionFactory::ratingSet([], 51.0);
        $b = ProjectionFactory::ratingSet([], 50.0);

        self::assertSame(Dominance::Mixed, DominanceComparator::compare($a, $b, 'PG'));
        self::assertSame(['wins' => 0, 'losses' => 0], DominanceComparator::record($a, $b, 'PG'));
    }

    public function testMixedWhenWinsBelowSixtyPercentShare(): void
    {
        // PG has 13 keys: a wins 7 and loses 6 (7/13 = 0.54 < 0.6).
        $higher = self::pgHigherIsBetterKeys();
        $overrides = ['r_tvr' => 60.0];
        foreach ($higher as $i => $key) {
            $overrides[$key] = $i < 7 ? 60.0 : 40.0;
        }
        $a = ProjectionFactory::ratingSet($overrides);
        $b = ProjectionFactory::ratingSet();

        self::assertSame(['wins' => 7, 'losses' => 6], DominanceComparator::record($a, $b, 'PG'));
        self::assertSame(Dominance::Mixed, DominanceComparator::compare($a, $b, 'PG'));
    }

    public function testBetterAtExactlySixtyPercentShare(): void
    {
        // 6 wins, 4 losses, 3 ties: 6 of 10 decided is exactly 60%.
        $higher = self::pgHigherIsBetterKeys();
        $overrides = [];
        foreach ($higher as $i => $key) {
            if ($i < 6) {
                $overrides[$key] = 60.0;
            } elseif ($i < 10) {
                $overrides[$key] = 40.0;
            }
        }
        $a = ProjectionFactory::ratingSet($overrides);
        $b = ProjectionFactory::ratingSet();

        self::assertSame(['wins' => 6, 'losses' => 4], DominanceComparator::record($a, $b, 'PG'));
        self::assertSame(Dominance::Better, DominanceComparator::compare($a, $b, 'PG'));
        self::assertSame(Dominance::Worse, DominanceComparator::compare($b, $a, 'PG'));
    }

    public function testLowerIsBetterRatingCountsAsWinWhenLower(): void
    {
        $a = ProjectionFactory::ratingSet(['r_tvr' => 40.0]);
        $b = ProjectionFactory::ratingSet(['r_tvr' => 60.0]);

        self::assertSame(Dominance::Better, DominanceComparator::compare($a, $b, 'C'));
    }

    public function testTieAtToleranceBoundaryIsUndecided(): void
    {
        $a = ProjectionFactory::ratingSet(['r_ast' => 52.0]);
        $b = ProjectionFactory::ratingSet(['r_ast' => 50.0]);

        self::assertSame(['wins' => 0, 'losses' => 0], DominanceComparator::record($a, $b, 'PG'));
        self::assertSame(Dominance::Mixed, DominanceComparator::compare($a, $b, 'PG'));

        $c = ProjectionFactory::ratingSet(['r_ast' => 52.5]);
        self::assertSame(Dominance::Better, DominanceComparator::compare($c, $b, 'PG'));
    }

    public function testIrrelevantRatingIsIgnored(): void
    {
        $a = ProjectionFactory::ratingSet(['r_orb' => 80.0]);
        $b = ProjectionFactory::ratingSet(['r_orb' => 50.0]);

        self::assertSame(Dominance::Mixed, DominanceComparator::compare($a, $b, 'PG'));
        self::assertSame(Dominance::Better, DominanceComparator::compare($a, $b, 'C'));
    }

    public function testUnknownSlotThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DominanceComparator::compare(ProjectionFactory::ratingSet(), ProjectionFactory::ratingSet(), 'G');
    }

    public function testEverySlotMapHasAtLeastElevenKnownKeys(): void
    {
        self::assertSame(SlotMapper::SLOTS, array_keys(SlotRelevance::MAP));
        foreach (SlotMapper::SLOTS as $slot) {
            $keys = SlotRelevance::keysFor($slot);
            self::assertGreaterThanOrEqual(11, count($keys), $slot);
            self::assertSame($keys, array_values(array_unique($keys)), $slot);
            foreach ($keys as $key) {
                self::assertContains($key, Rating::ALL, "{$slot}: {$key}");
            }
        }
    }

    public function testRecordReturnsWinsAndLossesSymmetrically(): void
    {
        $a = ProjectionFactory::ratingSet(['r_fgp' => 70.0, 'r_blk' => 30.0, 'r_foul' => 40.0]);
        $b = ProjectionFactory::ratingSet();

        $ab = DominanceComparator::record($a, $b, 'C');
        $ba = DominanceComparator::record($b, $a, 'C');

        self::assertSame(['wins' => 2, 'losses' => 1], $ab);
        self::assertSame($ab['wins'], $ba['losses']);
        self::assertSame($ab['losses'], $ba['wins']);
    }

    /**
     * @return list<string>
     */
    private static function pgHigherIsBetterKeys(): array
    {
        return array_values(array_filter(
            SlotRelevance::keysFor('PG'),
            static fn (string $k): bool => $k !== 'r_tvr'
        ));
    }
}
