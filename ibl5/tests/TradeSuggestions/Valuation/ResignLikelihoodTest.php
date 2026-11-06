<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Valuation;

use FreeAgency\FreeAgencyMarketDemandCalculator;
use League\ContractRules;
use PHPUnit\Framework\TestCase;
use TradeSuggestions\Valuation\ResignLikelihood;
use TradeSuggestions\Valuation\TeamFactors;

final class ResignLikelihoodTest extends TestCase
{
    public function testSecurityConstantsMatchFreeAgencyCalculator(): void
    {
        foreach (['SECURITY_BASE_FACTOR', 'SECURITY_YEAR_FACTOR'] as $name) {
            $original = (new \ReflectionClassConstant(FreeAgencyMarketDemandCalculator::class, $name))->getValue();
            $copy = (new \ReflectionClassConstant(ResignLikelihood::class, $name))->getValue();
            self::assertSame($original, $copy);
        }

        self::assertEqualsWithDelta(
            (ResignLikelihood::SECURITY_YEAR_FACTOR * 4 + ResignLikelihood::SECURITY_BASE_FACTOR) * 2,
            ResignLikelihood::securityTerm(5, 3),
            1e-12,
        );
        self::assertEqualsWithDelta(0.0, ResignLikelihood::securityTerm(5, 1), 1e-12);
    }

    public function testEdgeIsZeroForIndifferentPlayerOnAverageTeam(): void
    {
        $team = new TeamFactors(1, 60, 22, 900, 500);
        $rival = new TeamFactors(0, 41, 41, 700, 700);

        self::assertEqualsWithDelta(0.0, ResignLikelihood::edge(self::prefs(), 0, $team, $team, 500, 500), 1e-12);
        self::assertEqualsWithDelta(0.0, ResignLikelihood::edge(self::prefs(), 0, $team, $rival, 2000, 0), 1e-12);
        self::assertEqualsWithDelta(0.0, ResignLikelihood::edge(self::prefs(), 2, $team, $rival, 2000, 0), 1e-12);
    }

    public function testLoyaltyEdgeIsTwiceTheOwnTeamBonus(): void
    {
        $team = new TeamFactors(1, 41, 41, 700, 700);

        self::assertEqualsWithDelta(
            2 * 0.025 * 4,
            ResignLikelihood::edge(self::prefs(loyalty: 5), 0, $team, $team, 0, 0),
            1e-9,
        );
    }

    public function testWinnerEdgeUsesOwnMinusAverageRecord(): void
    {
        $edge = ResignLikelihood::edge(
            self::prefs(winner: 5),
            0,
            new TeamFactors(1, 60, 22, 0, 0),
            new TeamFactors(0, 45, 37, 0, 0),
            0,
            0,
        );

        self::assertEqualsWithDelta(0.000153 * (38 - 8) * 4, $edge, 1e-9);
    }

    public function testTraditionEdgeUsesOwnMinusAverageRecord(): void
    {
        $edge = ResignLikelihood::edge(
            self::prefs(tradition: 3),
            0,
            new TeamFactors(1, 0, 0, 900, 500),
            new TeamFactors(0, 0, 0, 700, 600),
            0,
            0,
        );

        self::assertEqualsWithDelta(
            ContractRules::calculateTraditionModifier(900, 500, 3) - ContractRules::calculateTraditionModifier(700, 600, 3),
            $edge,
            1e-9,
        );
        self::assertNotEqualsWithDelta(0.0, $edge, 1e-9);
    }

    public function testPlayingTimeMoneyIsCappedAtPositionSalaryCap(): void
    {
        $team = new TeamFactors(1, 41, 41, 700, 700);
        $prefs = self::prefs(playingTime: 5);

        $atCap = ResignLikelihood::edge($prefs, 0, $team, $team, 2000, 500);

        self::assertEqualsWithDelta($atCap, ResignLikelihood::edge($prefs, 0, $team, $team, 3000, 500), 1e-12);
        self::assertEqualsWithDelta(
            ContractRules::calculatePlayingTimeModifier(2000, 5) - ContractRules::calculatePlayingTimeModifier(500, 5),
            $atCap,
            1e-12,
        );
        self::assertLessThan(0.0, $atCap);
    }

    public function testBirdRightsEdgeStartsAtThreeYears(): void
    {
        $team = new TeamFactors(1, 41, 41, 700, 700);
        $prefs = self::prefs(security: 3);

        self::assertEqualsWithDelta(0.0, ResignLikelihood::edge($prefs, 2, $team, $team, 0, 0), 1e-12);
        self::assertEqualsWithDelta(
            ResignLikelihood::BIRD_RAISE_EDGE + ResignLikelihood::SECURITY_YEAR_FACTOR * 1 * 2,
            ResignLikelihood::edge($prefs, 3, $team, $team, 0, 0),
            1e-12,
        );
        self::assertEqualsWithDelta(0.025, ResignLikelihood::BIRD_RAISE_EDGE, 1e-12);
        self::assertSame(4, ResignLikelihood::STANDARD_YEARS);
        self::assertSame(1, ResignLikelihood::BIRD_EXTRA_YEARS);
    }

    public function testLikelihoodIsHalfAtZeroEdge(): void
    {
        self::assertEqualsWithDelta(0.5, ResignLikelihood::likelihood(0.0), 1e-12);
    }

    public function testLikelihoodAtScaleIsTanhOne(): void
    {
        self::assertSame(0.15, ResignLikelihood::SCALE);
        self::assertEqualsWithDelta(0.5 + 0.5 * tanh(1), ResignLikelihood::likelihood(0.15), 1e-12);
        self::assertEqualsWithDelta(0.5 - 0.5 * tanh(1), ResignLikelihood::likelihood(-0.15), 1e-12);
    }

    public function testLikelihoodClampedAtBothEnds(): void
    {
        self::assertSame(0.95, ResignLikelihood::likelihood(10.0));
        self::assertSame(0.05, ResignLikelihood::likelihood(-10.0));
    }

    public function testLikelihoodIsMonotonicInEdge(): void
    {
        $previous = -1.0;
        foreach ([-0.5, -0.1, 0.0, 0.1, 0.5] as $edge) {
            $likelihood = ResignLikelihood::likelihood($edge);
            self::assertGreaterThanOrEqual($previous, $likelihood);
            $previous = $likelihood;
        }
    }

    public function testLikelyToWalkBoundaries(): void
    {
        self::assertTrue(ResignLikelihood::isLikelyToWalk(0.39, 1, 2));
        self::assertTrue(ResignLikelihood::isLikelyToWalk(0.39, 1, 1));
        self::assertFalse(ResignLikelihood::isLikelyToWalk(0.4, 1, 2));
        self::assertFalse(ResignLikelihood::isLikelyToWalk(0.39, 2, 2));
        self::assertFalse(ResignLikelihood::isLikelyToWalk(0.39, 1, 3));
        self::assertFalse(ResignLikelihood::isLikelyToWalk(0.39, 1, 0));
    }

    public function testFutureValueFactorRules(): void
    {
        self::assertSame(1.0, ResignLikelihood::futureValueFactor(0, true, true, 0.3));
        self::assertSame(1.0, ResignLikelihood::futureValueFactor(0, false, true, 0.3));
        self::assertSame(0.0, ResignLikelihood::futureValueFactor(1, false, false, 0.3));
        self::assertSame(0.3, ResignLikelihood::futureValueFactor(1, true, true, 0.3));
        self::assertSame(1.0, ResignLikelihood::futureValueFactor(1, true, false, 0.3));
    }

    public function testTeamFactorsAverageRoundsMeansAndHandlesEmpty(): void
    {
        $average = TeamFactors::average([
            new TeamFactors(1, 60, 22, 41, 100),
            new TeamFactors(2, 20, 62, 42, 101),
            new TeamFactors(3, 40, 42, 45, 105),
        ]);
        self::assertEquals(new TeamFactors(0, 40, 42, 43, 102), $average);

        $halfUp = TeamFactors::average([new TeamFactors(1, 41, 0, 0, 0), new TeamFactors(2, 42, 0, 0, 0)]);
        self::assertSame(42, $halfUp->wins);

        $roundsDown = TeamFactors::average([
            new TeamFactors(1, 40, 0, 0, 0),
            new TeamFactors(2, 40, 0, 0, 0),
            new TeamFactors(3, 40, 0, 0, 0),
            new TeamFactors(4, 41, 0, 0, 0),
        ]);
        self::assertSame(40, $roundsDown->wins);

        self::assertEquals(new TeamFactors(0, 0, 0, 0, 0), TeamFactors::average([]));
    }

    /**
     * @return array<string, int>
     */
    private static function prefs(
        int $loyalty = 1,
        int $winner = 1,
        int $tradition = 1,
        int $security = 1,
        int $playingTime = 1,
    ): array {
        return [
            'loyalty' => $loyalty,
            'winner' => $winner,
            'tradition' => $tradition,
            'security' => $security,
            'playing_time' => $playingTime,
        ];
    }
}
