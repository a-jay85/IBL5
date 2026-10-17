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
        for ($i = 0; $i < 10; $i++) {
            $scored[] = self::scored(2 + $i, 100 + $i, 5.0, 5.0);
        }

        $calls = 0;
        $kept = (new SuggestionRanker())->select($scored, static function (ScoredCandidate $s) use (&$calls): bool {
            $calls++;

            return false;
        });

        $this->assertSame(8, $calls);
        $this->assertSame([], $kept);
    }

    private static function scored(int $partnerTid, int $userPid, float $userGain, float $partnerGain): ScoredCandidate
    {
        return new ScoredCandidate(
            new TradeCandidate(1, $partnerTid, [$userPid], [], [$partnerTid * 100], []),
            $userGain,
            $partnerGain,
            'user reason',
            'partner reason',
        );
    }
}
