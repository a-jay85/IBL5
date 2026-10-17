<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\TeamAssets;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\TradeImpactEvaluator;

class TradeImpactEvaluatorTest extends TestCase
{
    private const int PROSPECT = 109;
    private const int OLD_BENCH_GUARD = 110;
    private const int VETERAN_CENTER = 207;

    private TradeImpactEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new TradeImpactEvaluator();
    }

    public function testKeepsDealWhereBothSidesGain(): void
    {
        $candidate = new TradeCandidate(1, 2, [self::PROSPECT], [], [self::VETERAN_CENTER], []);

        $result = $this->evaluator->evaluate($candidate, self::winNowUser(), self::rebuildingPartner(), TeamMode::WinNow, TeamMode::Rebuild);

        $this->assertNotNull($result);
        $this->assertSame($candidate, $result->candidate);
        // User: now +9 (hole filled), short -5, long -12.5 under WinNow weights.
        $this->assertEqualsWithDelta(2.65, $result->userGain, 1e-9);
        // Partner: now -10, short -2, long +12.5 under Rebuild weights.
        $this->assertEqualsWithDelta(5.9, $result->partnerGain, 1e-9);
        $this->assertSame('Fills a hole at big.', $result->userReason);
        $this->assertSame('Clears 2500K of future salary.', $result->partnerReason);
    }

    public function testRejectsOneSidedDeal(): void
    {
        $candidate = new TradeCandidate(1, 2, [self::OLD_BENCH_GUARD], [], [self::VETERAN_CENTER], []);

        $result = $this->evaluator->evaluate($candidate, self::winNowUser(), self::rebuildingPartner(), TeamMode::WinNow, TeamMode::Rebuild);

        $this->assertNull($result);
    }

    public function testRejectsFairValueSwapBelowMinGain(): void
    {
        $user = new TeamAssets(1, [...self::balancedCore(1), self::player(150, 'SF', 14, 26, 2, 1000)], []);
        $partner = new TeamAssets(2, [...self::balancedCore(2), self::player(250, 'SF', 14, 26, 2, 1000)], []);
        $candidate = new TradeCandidate(1, 2, [150], [], [250], []);

        $this->assertNull($this->evaluator->evaluate($candidate, $user, $partner, TeamMode::WinNow, TeamMode::WinNow));
    }

    public function testPartnerScoredByItsOwnMode(): void
    {
        $candidate = new TradeCandidate(1, 2, [self::PROSPECT], [], [self::VETERAN_CENTER], []);

        $asRebuild = $this->evaluator->evaluate($candidate, self::winNowUser(), self::rebuildingPartner(), TeamMode::WinNow, TeamMode::Rebuild);
        $asWinNow = $this->evaluator->evaluate($candidate, self::winNowUser(), self::rebuildingPartner(), TeamMode::WinNow, TeamMode::WinNow);

        $this->assertNotNull($asRebuild);
        $this->assertNull($asWinNow);
    }

    /**
     * Four guards, three wings, one big in the rotation (a big-man hole), plus a
     * young prospect and an old bench guard outside it.
     */
    private static function winNowUser(): TeamAssets
    {
        return new TeamAssets(1, [
            self::player(101, 'PG', 18, 27, 3, 1000),
            self::player(102, 'SG', 18, 27, 3, 1000),
            self::player(103, 'G', 18, 27, 3, 1000),
            self::player(104, 'PG', 17, 27, 3, 1000),
            self::player(105, 'SF', 18, 27, 3, 1000),
            self::player(106, 'GF', 18, 27, 3, 1000),
            self::player(107, 'F', 18, 27, 3, 1000),
            self::player(108, 'C', 18, 27, 3, 1000),
            self::player(self::PROSPECT, 'SF', 10, 21, 3, 500),
            self::player(self::OLD_BENCH_GUARD, 'SG', 5, 33, 1, 0),
        ], []);
    }

    private static function rebuildingPartner(): TeamAssets
    {
        return new TeamAssets(2, [
            self::player(201, 'PG', 15, 27, 3, 1000),
            self::player(202, 'SG', 15, 27, 3, 1000),
            self::player(203, 'F', 15, 27, 3, 1000),
            self::player(204, 'SF', 15, 27, 3, 1000),
            self::player(205, 'PF', 15, 27, 3, 1000),
            self::player(206, 'C', 12, 27, 3, 1000),
            self::player(self::VETERAN_CENTER, 'C', 20, 32, 2, 3000),
            self::player(208, 'G', 10, 27, 3, 1000),
        ], []);
    }

    /**
     * @return list<SuggestionPlayer>
     */
    private static function balancedCore(int $tid): array
    {
        $base = $tid * 100;

        return [
            self::player($base + 1, 'PG', 15, 27, 3, 1000),
            self::player($base + 2, 'SG', 15, 27, 3, 1000),
            self::player($base + 3, 'SF', 15, 27, 3, 1000),
            self::player($base + 4, 'F', 15, 27, 3, 1000),
            self::player($base + 5, 'PF', 15, 27, 3, 1000),
            self::player($base + 6, 'C', 15, 27, 3, 1000),
            self::player($base + 7, 'G', 15, 27, 3, 1000),
        ];
    }

    private static function player(int $pid, string $pos, int $quality, int $age, int $yearsRemaining, int $futureSalary): SuggestionPlayer
    {
        return new SuggestionPlayer($pid, 'Player ' . $pid, $pos, $age, $quality, false, 1000, $yearsRemaining, $futureSalary);
    }
}
