<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Projection;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Projection\Rating;
use TradeSuggestions\Projection\RatingDirection;
use TradeSuggestions\Projection\RatingScale;
use TradeSuggestions\Projection\RatingSet;

final class RatingVocabularyTest extends TestCase
{
    public function testAllHasTwentyOneDistinctKeysMatchingIblPlrColumns(): void
    {
        self::assertCount(21, Rating::ALL);
        self::assertSame(Rating::ALL, array_values(array_unique(Rating::ALL)));

        $schema = file_get_contents(__DIR__ . '/../../../docs/schema/current-schema.sql');
        self::assertIsString($schema);
        $start = strpos($schema, 'CREATE TABLE `ibl_plr` (');
        self::assertNotFalse($start);
        $end = strpos($schema, ';', $start);
        self::assertNotFalse($end);
        $block = substr($schema, $start, $end - $start);

        foreach (Rating::ALL as $key) {
            self::assertStringContainsString("`{$key}`", $block, "{$key} is not an ibl_plr column");
        }
    }

    public function testLowerIsBetterTableIsExactlyTvrAndFoul(): void
    {
        self::assertFalse(RatingDirection::higherIsBetter('r_tvr'));
        self::assertFalse(RatingDirection::higherIsBetter('r_foul'));
        $higher = array_filter(Rating::ALL, static fn (string $k): bool => RatingDirection::higherIsBetter($k));
        self::assertCount(19, $higher);
    }

    public function testFamilyThrowsOnUnknownKey(): void
    {
        self::assertSame('athletic', Rating::family('r_stl'));
        self::assertSame('skill', Rating::family('r_foul'));
        $this->expectException(\InvalidArgumentException::class);
        Rating::family('peak');
    }

    public function testRatingSetGetThrowsOnUnknownKey(): void
    {
        $set = RatingSet::fromRow(['r_fga' => 50]);
        $this->expectException(\InvalidArgumentException::class);
        $set->get('stamina');
    }

    public function testRatingSetFromRowDefaultsMissingKeyToZero(): void
    {
        $set = RatingSet::fromRow(['r_fga' => 50, 'oo' => '7']);
        self::assertSame(50.0, $set->get('r_fga'));
        self::assertSame(7.0, $set->get('oo'));
        self::assertSame(0.0, $set->get('r_blk'));
        self::assertCount(21, $set->toArray());
        self::assertSame(12.0, $set->with('r_blk', 12.0)->get('r_blk'));
        self::assertSame(0.0, $set->get('r_blk'));
    }

    /** Pins RatingScale::clamp: dropping its min() lets 120.0 through. */
    public function testRatingScaleClampsAboveObservedMaxAndBelowZero(): void
    {
        $scale = RatingScale::fromRows([['r_fga' => 80], ['r_fga' => 95], ['r_fga' => 60]]);
        self::assertSame(95.0, $scale->max('r_fga'));
        self::assertSame(95.0, $scale->clamp('r_fga', 120.0));
        self::assertSame(0.0, $scale->clamp('r_fga', -3.0));
        self::assertSame(70.0, $scale->clamp('r_fga', 70.0));
    }

    public function testRatingScaleFromEmptyRowsClampsToZero(): void
    {
        $scale = RatingScale::fromRows([]);
        foreach (Rating::ALL as $key) {
            self::assertSame(0.0, $scale->max($key));
            self::assertSame(0.0, $scale->clamp($key, 50.0));
        }
    }
}
