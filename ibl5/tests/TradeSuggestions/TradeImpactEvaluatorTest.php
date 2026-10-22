<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\RejectReason;
use TradeSuggestions\ScoredCandidate;
use TradeSuggestions\SuggestionPick;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\TeamAssets;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\TradeImpactEvaluator;

class TradeImpactEvaluatorTest extends TestCase
{
    private TradeImpactEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new TradeImpactEvaluator();
    }

    /**
     * The owner's prod example: a rebuilding team giving a 23-year-old 70 on a
     * five-year deal for a 21-year-old minimum player. The value-balance check
     * stops it before the roster scores are even compared.
     */
    public function testRebuildingTeamWillNotGiveYoungStarForMinimumPlayer(): void
    {
        $user = self::team(1, [self::player(100, 'PF', 70, 23, 5, 4800, 1196)]);
        $partner = self::team(2, [self::player(200, 'SF', 48, 21, 2, 35, 35)]);
        $candidate = new TradeCandidate(1, 2, [100], [], [200], []);

        $result = $this->evaluator->evaluate($candidate, $user, $partner, TeamMode::Rebuild, TeamMode::WinNow);

        self::assertSame(RejectReason::UserGivesTooMuch, $result);
    }

    public function testBalanceProtectsThePartnerToo(): void
    {
        $user = self::team(1, [self::player(100, 'SF', 48, 21, 2, 35, 35)]);
        $partner = self::team(2, [self::player(200, 'PF', 70, 23, 5, 4800, 1196)]);
        $candidate = new TradeCandidate(1, 2, [100], [], [200], []);

        $result = $this->evaluator->evaluate($candidate, $user, $partner, TeamMode::WinNow, TeamMode::Rebuild);

        self::assertSame(RejectReason::PartnerGivesTooMuch, $result);
    }

    public function testBenchForBenchSwapIsNotMeaningful(): void
    {
        // A ninth 56 on each side pushes the swapped players out of the top eight.
        $user = self::team(1, [self::player(99, 'C', 56, 27, 2, 1000), self::player(100, 'PF', 50, 25, 2, 500)]);
        $partner = self::team(2, [self::player(199, 'C', 56, 27, 2, 1000), self::player(200, 'C', 51, 25, 2, 500)]);
        $candidate = new TradeCandidate(1, 2, [100], [], [200], []);

        self::assertSame(RejectReason::NotMeaningful, $this->evaluator->evaluate($candidate, $user, $partner, TeamMode::WinNow, TeamMode::WinNow));
    }

    public function testRosterAtFifteenCannotTakeTwoForOne(): void
    {
        [$user, $partner, $candidate] = self::consolidation();
        $full = new TeamAssets($partner->tid, $partner->players, $partner->picks, 15);

        self::assertSame(RejectReason::RosterFull, $this->evaluator->evaluate($candidate, $user, $full, TeamMode::WinNow, TeamMode::Rebuild));
        self::assertInstanceOf(ScoredCandidate::class, $this->evaluator->evaluate($candidate, $user, $partner, TeamMode::WinNow, TeamMode::Rebuild));
    }

    public function testDealThatBreaksTheHardCapIsDropped(): void
    {
        [$user, $partner, $candidate] = self::consolidation();
        // The partner takes on 2 x 1000 and sends out 1500: +500 against 499 of room.
        $tight = new TeamAssets($partner->tid, $partner->players, $partner->picks, null, 499);
        $roomy = new TeamAssets($partner->tid, $partner->players, $partner->picks, null, 500);

        self::assertSame(RejectReason::OverHardCap, $this->evaluator->evaluate($candidate, $user, $tight, TeamMode::WinNow, TeamMode::Rebuild));
        self::assertInstanceOf(ScoredCandidate::class, $this->evaluator->evaluate($candidate, $user, $roomy, TeamMode::WinNow, TeamMode::Rebuild));
    }

    public function testUntradeablePlayerIsDropped(): void
    {
        [$user, $partner] = self::consolidation();
        $locked = new SuggestionPlayer(150, 'Locked', 'C', 27, 70, false, 1000, 2, 1000, tradeable: false);
        $user = new TeamAssets(1, [...$user->players, $locked], []);
        $candidate = new TradeCandidate(1, 2, [150], [], [200], []);

        self::assertSame(RejectReason::Untradeable, $this->evaluator->evaluate($candidate, $user, $partner, TeamMode::WinNow, TeamMode::Rebuild));
    }

    public function testFairConsolidationHelpsBothWithDistinctTrueReasons(): void
    {
        [$user, $partner, $candidate] = self::consolidation();

        $result = $this->evaluator->evaluate($candidate, $user, $partner, TeamMode::WinNow, TeamMode::Rebuild);

        self::assertInstanceOf(ScoredCandidate::class, $result);
        self::assertGreaterThanOrEqual(TradeImpactEvaluator::MIN_GAIN, $result->userGain);
        self::assertGreaterThanOrEqual(TradeImpactEvaluator::MIN_GAIN, $result->partnerGain);
        self::assertSame('Turns Player 101 (ovr 62) and Player 102 (ovr 61) into one better player, Player 200 (ovr 68).', $result->userReason);
        self::assertNotSame($result->userReason, $result->partnerReason);
        self::assertStringContainsString('Player 101', $result->partnerReason);
        self::assertSame(52.9, round($result->significance, 1));
    }

    public function testPickForPlayerDeal(): void
    {
        $user = self::team(1, [], [new SuggestionPick(900, 1, 2027, 1, 20.0)]);
        $partner = self::team(2, [self::player(200, 'C', 64, 31, 2, 1500)]);
        $candidate = new TradeCandidate(1, 2, [], [900], [200], []);

        $result = $this->evaluator->evaluate($candidate, $user, $partner, TeamMode::WinNow, TeamMode::Rebuild);

        self::assertInstanceOf(ScoredCandidate::class, $result);
        self::assertSame('Adds a 2027 first-round pick.', $result->partnerReason);
    }

    /**
     * Win-now user sends two 60s for the rebuilding partner's 68.
     *
     * @return array{TeamAssets, TeamAssets, TradeCandidate}
     */
    private static function consolidation(): array
    {
        $user = self::team(1, [
            self::player(101, 'SF', 62, 24, 3, 2000),
            self::player(102, 'PF', 61, 24, 3, 2000),
        ]);
        $partner = self::team(2, [self::player(200, 'C', 68, 30, 2, 1500, 1500)]);

        return [$user, $partner, new TradeCandidate(1, 2, [101, 102], [], [200], [])];
    }

    /**
     * Seven filler players (two per position group, ovr 56, age 27) plus $extra.
     *
     * @param list<SuggestionPlayer> $extra
     * @param list<SuggestionPick> $picks
     */
    private static function team(int $tid, array $extra, array $picks = []): TeamAssets
    {
        $players = [];
        foreach (['PG', 'SG', 'SF', 'GF', 'PF', 'C', 'G'] as $i => $pos) {
            $players[] = self::player($tid * 1000 + $i, $pos, 56, 27, 2, 1000);
        }

        return new TeamAssets($tid, [...$players, ...$extra], $picks);
    }

    private static function player(int $pid, string $pos, int $ovr, int $age, int $years, int $futureSalary, int $salary = 1000): SuggestionPlayer
    {
        return new SuggestionPlayer($pid, 'Player ' . $pid, $pos, $age, $ovr, false, $salary, $years, $futureSalary);
    }
}
