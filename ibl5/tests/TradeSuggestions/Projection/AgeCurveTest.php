<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Projection;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Projection\AgeCurve;

final class AgeCurveTest extends TestCase
{
    public function testAthleticGrowsAtTwentyFourAndHoldsAtTwentyFive(): void
    {
        self::assertSame(2.0, AgeCurve::stepDelta('r_stl', 24, 50));
        self::assertSame(0.0, AgeCurve::stepDelta('r_stl', 25, 50));
    }

    public function testAthleticHoldsAtTwentySevenAndDeclinesAtTwentyEight(): void
    {
        self::assertSame(0.0, AgeCurve::stepDelta('r_stl', 27, 50));
        self::assertSame(-1.0, AgeCurve::stepDelta('r_stl', 28, 50));
    }

    public function testAthleticDeclineSteepensAndCapsAtThirtyFour(): void
    {
        self::assertSame(-1.5, AgeCurve::stepDelta('dd', 29, 50));
        self::assertSame(-2.0, AgeCurve::stepDelta('dd', 30, 50));
        self::assertSame(-3.5, AgeCurve::stepDelta('dd', 33, 50));
        self::assertSame(-4.0, AgeCurve::stepDelta('dd', 34, 50));
        self::assertSame(-4.0, AgeCurve::stepDelta('dd', 36, 50));
    }

    public function testSkillGrowsAtTwentySixAndHoldsAtTwentySeven(): void
    {
        self::assertSame(1.5, AgeCurve::stepDelta('r_fgp', 26, 50));
        self::assertSame(0.0, AgeCurve::stepDelta('r_fgp', 27, 50));
    }

    public function testSkillHoldsAtThirtyAndDeclinesAtThirtyOne(): void
    {
        self::assertSame(0.0, AgeCurve::stepDelta('r_fgp', 30, 50));
        self::assertSame(-0.5, AgeCurve::stepDelta('r_fgp', 31, 50));
    }

    public function testSkillDeclineCapsAtMinusThree(): void
    {
        self::assertSame(-2.5, AgeCurve::stepDelta('oo', 35, 50));
        self::assertSame(-3.0, AgeCurve::stepDelta('oo', 36, 50));
        self::assertSame(-3.0, AgeCurve::stepDelta('oo', 38, 50));
    }

    public function testTalentScalesGrowthOnlyAtTwentyFourOrYounger(): void
    {
        self::assertSame(3.0, AgeCurve::stepDelta('r_stl', 22, 100));
        self::assertSame(1.0, AgeCurve::stepDelta('r_stl', 22, 0));
        self::assertSame(1.5, AgeCurve::stepDelta('r_fgp', 25, 100));
        self::assertSame(2.25, AgeCurve::stepDelta('r_fgp', 24, 100));
    }

    public function testTalentFactorClampedToHalfAndOneAndAHalf(): void
    {
        self::assertSame(1.5, AgeCurve::talentFactor(255));
        self::assertSame(0.5, AgeCurve::talentFactor(0));
        self::assertSame(1.0, AgeCurve::talentFactor(50));
        self::assertSame(1.2, AgeCurve::talentFactor(60));
    }

    public function testTalentNeverScalesDecline(): void
    {
        self::assertSame(-2.0, AgeCurve::stepDelta('r_stl', 30, 100));
        self::assertSame(-2.0, AgeCurve::stepDelta('r_stl', 30, 0));
        self::assertSame(-0.5, AgeCurve::stepDelta('r_fgp', 31, 100));
    }

    /** Sums stepDelta over ages age..age+offset-1, so a shifted range changes the 23 and 27 totals. */
    public function testCumulativeDeltaOffsetZeroIsZeroAndSumsSteps(): void
    {
        self::assertSame(4.0, AgeCurve::cumulativeDelta('r_stl', 23, 50, 3));
        self::assertSame(0.0, AgeCurve::cumulativeDelta('r_stl', 23, 50, 0));
        self::assertSame(-2.5, AgeCurve::cumulativeDelta('r_stl', 27, 50, 3));
    }

    public function testUnknownRatingKeyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AgeCurve::stepDelta('peak', 25, 50);
    }

    /**
     * Pins all 12 curve numbers through stepDelta at talent midpoint (factor 1.0),
     * so a silent retune shows up in this test's diff. Asserting the constants
     * directly would be a constant restatement (phpunit-tests.md).
     */
    public function testPinnedTableMatchesConstants(): void
    {
        $athletic = [];
        $skill = [];
        foreach (range(20, 40) as $age) {
            $athletic[$age] = AgeCurve::stepDelta('r_drb', $age, 50);
            $skill[$age] = AgeCurve::stepDelta('r_3gp', $age, 50);
        }

        self::assertSame([
            20 => 2.0, 21 => 2.0, 22 => 2.0, 23 => 2.0, 24 => 2.0,
            25 => 0.0, 26 => 0.0, 27 => 0.0,
            28 => -1.0, 29 => -1.5, 30 => -2.0, 31 => -2.5, 32 => -3.0, 33 => -3.5,
            34 => -4.0, 35 => -4.0, 36 => -4.0, 37 => -4.0, 38 => -4.0, 39 => -4.0, 40 => -4.0,
        ], $athletic);
        self::assertSame([
            20 => 1.5, 21 => 1.5, 22 => 1.5, 23 => 1.5, 24 => 1.5, 25 => 1.5, 26 => 1.5,
            27 => 0.0, 28 => 0.0, 29 => 0.0, 30 => 0.0,
            31 => -0.5, 32 => -1.0, 33 => -1.5, 34 => -2.0, 35 => -2.5,
            36 => -3.0, 37 => -3.0, 38 => -3.0, 39 => -3.0, 40 => -3.0,
        ], $skill);
    }
}
