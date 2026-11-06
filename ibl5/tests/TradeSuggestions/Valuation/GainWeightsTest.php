<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Valuation;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Projection\Horizon;
use TradeSuggestions\TeamMode;
use TradeSuggestions\Valuation\GainWeights;

final class GainWeightsTest extends TestCase
{
    public function testSeasonWeightsSumToOnePerMode(): void
    {
        self::assertSame(['win_now', 'middle', 'rebuild'], array_keys(GainWeights::SEASON_WEIGHTS));
        foreach (GainWeights::SEASON_WEIGHTS as $weights) {
            self::assertCount(Horizon::SEASONS, $weights);
            self::assertEqualsWithDelta(1.0, array_sum($weights), 1e-9);
        }
    }

    public function testWinNowFrontLoadsAndRebuildBackLoads(): void
    {
        $winNow = GainWeights::seasonWeights(TeamMode::WinNow);
        $rebuild = GainWeights::seasonWeights(TeamMode::Rebuild);

        self::assertSame([0.55, 0.25, 0.12, 0.08], $winNow);
        self::assertSame([0.10, 0.20, 0.30, 0.40], $rebuild);
        self::assertGreaterThan($rebuild[0], $winNow[0]);
        self::assertGreaterThan($winNow[3], $rebuild[3]);
        foreach ([1, 2, 3] as $offset) {
            self::assertLessThanOrEqual($winNow[$offset - 1], $winNow[$offset]);
            self::assertGreaterThanOrEqual($rebuild[$offset - 1], $rebuild[$offset]);
        }
    }

    public function testSeasonWeightsForUnknownModeIsImpossible(): void
    {
        foreach (TeamMode::cases() as $mode) {
            self::assertCount(Horizon::SEASONS, GainWeights::seasonWeights($mode));
            self::assertArrayHasKey(GainWeights::modeKey($mode), GainWeights::SEASON_WEIGHTS);
        }
        self::assertSame('win_now', GainWeights::modeKey(TeamMode::WinNow));
        self::assertSame('rebuild', GainWeights::modeKey(TeamMode::Rebuild));
        self::assertCount(Horizon::SEASONS, GainWeights::SEASON_WEIGHTS['middle']);
    }

    public function testStarterOutweighsBench(): void
    {
        $weights = GainWeights::toArray();

        self::assertGreaterThan($weights['bench_weight'], $weights['starter_weight']);
    }

    public function testRentalScaleIsOneAtOrAboveLateSeasonShare(): void
    {
        self::assertSame(1.0, GainWeights::rentalScale(21));
        self::assertSame(1.0, GainWeights::rentalScale(41));
        self::assertSame(1.0, GainWeights::rentalScale(82, 82));
        self::assertSame(1.0, GainWeights::rentalScale(25, 100));
    }

    public function testRentalScaleFallsLinearlyInsideLateSeasonWindow(): void
    {
        self::assertEqualsWithDelta(10 / 20.5, GainWeights::rentalScale(10), 1e-9);
        self::assertEqualsWithDelta(0.5, GainWeights::rentalScale(5, 40), 1e-9);
    }

    public function testRentalScaleZeroWhenNoGamesLeft(): void
    {
        self::assertSame(0.0, GainWeights::rentalScale(0));
        self::assertSame(0.0, GainWeights::rentalScale(-3));
        self::assertSame(0.0, GainWeights::rentalScale(10, 0));
        self::assertSame(0.0, GainWeights::rentalScale(10, -82));
    }

    public function testToArrayExposesEveryPublicConstant(): void
    {
        $array = GainWeights::toArray();
        $constants = (new \ReflectionClass(GainWeights::class))->getConstants(\ReflectionClassConstant::IS_PUBLIC);

        self::assertNotSame([], $constants);
        self::assertCount(count($constants), $array);
        foreach ($constants as $name => $value) {
            self::assertArrayHasKey(strtolower($name), $array);
            self::assertSame($value, $array[strtolower($name)]);
        }
        self::assertSame(GainWeights::SEASON_WEIGHTS, $array['season_weights']);
    }

    /**
     * Adding playerValue(ProjectionPlayer) to GainWeights turns this red twice:
     * the name matches the pattern and the parameter type is banned.
     */
    public function testNoMethodAcceptsAPlayerOrRatingObject(): void
    {
        $banned = ['ProjectionPlayer', 'SuggestionPlayer', 'RatingSet', 'ProjectedSeason'];
        $methods = (new \ReflectionClass(GainWeights::class))->getMethods(\ReflectionMethod::IS_PUBLIC);

        self::assertNotSame([], $methods);
        foreach ($methods as $method) {
            self::assertDoesNotMatchRegularExpression('/score|value|rating|overall/i', $method->getName());
            foreach ($method->getParameters() as $parameter) {
                $type = (string) $parameter->getType();
                foreach ($banned as $name) {
                    self::assertStringNotContainsString($name, $type);
                }
            }
        }
    }
}
