<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\ScoredCandidate;
use TradeSuggestions\SuggestionRanker;
use TradeSuggestions\TradeCandidate;

class SuggestionRankerTest extends TestCase
{
    public function testSelectsAtMostThreeOnePerPartner(): void
    {
        $scored = [
            self::scored(2, 11, 5.0, 5.0),
            self::scored(2, 12, 9.0, 9.0),
            self::scored(3, 13, 4.0, 8.0),
            self::scored(4, 14, 7.0, 3.0),
            self::scored(5, 15, 2.0, 2.0),
            self::scored(6, 11, 10.0, 10.0),
        ];

        $kept = (new SuggestionRanker())->select($scored, static fn (ScoredCandidate $s): bool => true);

        $this->assertCount(3, $kept);
        // Partner 6 (min 10) first, then partner 2's best (min 9). Partner 2's other
        // deal (min 5) is skipped: partner 2 and user pid 11 are both used. Partner 3
        // (min 4) beats partner 4 (min 3) for the last slot.
        $this->assertSame([6, 2, 3], array_map(static fn (ScoredCandidate $s): int => $s->candidate->partnerTid, $kept));
        $this->assertSame([[11], [12], [13]], array_map(static fn (ScoredCandidate $s): array => $s->candidate->userPids, $kept));
    }

    public function testSkipsCandidatesFailingValidation(): void
    {
        $scored = [
            self::scored(2, 11, 9.0, 9.0),
            self::scored(3, 12, 8.0, 8.0),
            self::scored(4, 13, 7.0, 7.0),
        ];

        $kept = (new SuggestionRanker())->select(
            $scored,
            static fn (ScoredCandidate $s): bool => $s->candidate->partnerTid !== 3
        );

        $this->assertSame([2, 4], array_map(static fn (ScoredCandidate $s): int => $s->candidate->partnerTid, $kept));
    }

    public function testStopsAfterMaxValidations(): void
    {
        $scored = [];
        for ($i = 0; $i < SuggestionRanker::MAX_VALIDATIONS + 5; $i++) {
            $scored[] = self::scored(2 + $i, 100 + $i, 5.0, 5.0);
        }

        $calls = 0;
        $kept = (new SuggestionRanker())->select($scored, static function (ScoredCandidate $s) use (&$calls): bool {
            $calls++;

            return false;
        });

        $this->assertSame(SuggestionRanker::MAX_VALIDATIONS, $calls);
        $this->assertSame([], $kept);
    }

    /**
     * A deal that moves a meaningful player outranks a slightly better-scoring
     * bench swap.
     */
    public function testSignificanceLiftsDealsThatMoveARealPlayer(): void
    {
        $bench = self::scored(2, 11, 3.0, 3.0);
        $star = self::scored(3, 12, 2.0, 2.0, significance: 40.0);

        $kept = (new SuggestionRanker())->select([$bench, $star], static fn (ScoredCandidate $s): bool => true);

        $this->assertSame([3, 2], array_map(static fn (ScoredCandidate $s): int => $s->candidate->partnerTid, $kept));
    }

    public function testSelectCapsAtThreeWithOnePerPartnerInRankOrder(): void
    {
        // Partner 2 holds ranks 1 and 2, so dropping the one-per-partner guard
        // would keep partner 2 twice.
        $scored = [
            self::scoredFor(4, 7),
            self::scoredFor(2, 2),
            self::scoredFor(5, 5),
            self::scoredFor(3, 6),
            self::scoredFor(2, 1),
            self::scoredFor(4, 4),
            self::scoredFor(3, 3),
        ];

        $kept = (new SuggestionRanker())->select($scored, static fn (ScoredCandidate $s): bool => true);

        $this->assertCount(3, $kept);
        $partners = array_map(static fn (ScoredCandidate $s): int => $s->candidate->partnerTid, $kept);
        $this->assertSame($partners, array_values(array_unique($partners)));
        $this->assertSame([2, 3, 4], $partners);
        $this->assertSame([1, 3, 4], array_map(self::rankOrderOf(...), $kept));
    }

    public function testSelectConsultsValidatorInRankOrderAndSkipsRejected(): void
    {
        $scored = [
            self::scoredFor(3, 3),
            self::scoredFor(2, 2),
            self::scoredFor(4, 4),
            self::scoredFor(2, 1),
        ];

        $seen = [];
        $kept = (new SuggestionRanker())->select($scored, static function (ScoredCandidate $s) use (&$seen): bool {
            $seen[] = self::rankOrderOf($s);

            return self::rankOrderOf($s) !== 1;
        });

        $sorted = $seen;
        sort($sorted);
        $this->assertSame($sorted, $seen);
        $this->assertSame(array_values(array_unique($seen)), $seen);
        $this->assertNotContains(1, array_map(self::rankOrderOf(...), $kept));
        $this->assertSame([2, 3, 4], array_map(self::rankOrderOf(...), $kept));
        $this->assertSame(2, $kept[0]->candidate->partnerTid);
    }

    /**
     * Builds a candidate that ranks at $rankOrder (lower = better). Pins build
     * candidates only through here, so a ranking-field change edits this body.
     */
    private static function scoredFor(int $partnerTid, int $rankOrder): ScoredCandidate
    {
        return self::scored($partnerTid, 1000 + $rankOrder, 100.0 - $rankOrder, 100.0 - $rankOrder);
    }

    private static function rankOrderOf(ScoredCandidate $s): int
    {
        return $s->candidate->userPids[0] - 1000;
    }

    private static function scored(int $partnerTid, int $userPid, float $userGain, float $partnerGain, float $significance = 0.0): ScoredCandidate
    {
        return new ScoredCandidate(
            new TradeCandidate(1, $partnerTid, [$userPid], [], [$partnerTid * 100], []),
            $userGain,
            $partnerGain,
            'user reason',
            'partner reason',
            $significance,
        );
    }
}
