<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Engine\ExposureFilter;
use TradeSuggestions\Valuation\GainWeights;

final class ExposureFilterTest extends TestCase
{
    /**
     * @return list<int>
     */
    private static function league(): array
    {
        return range(1, 28);
    }

    public function testAtMostKTeamsMayTargetAnyPartner(): void
    {
        $tids = self::league();
        foreach ($tids as $partner) {
            $count = 0;
            foreach ($tids as $user) {
                if (ExposureFilter::mayTarget('key', $partner, $user, $tids)) {
                    $count++;
                }
            }
            self::assertSame(GainWeights::EXPOSURE_K, $count, "partner {$partner}");
        }
    }

    public function testKBoundaryEightAllowedNineRefused(): void
    {
        $tids = self::league();
        $partner = 4;
        $others = array_values(array_filter($tids, static fn (int $t): bool => $t !== $partner));
        usort($others, static fn (int $a, int $b): int
            => strcmp(sha1('key|' . $partner . '|' . $a), sha1('key|' . $partner . '|' . $b)));

        self::assertTrue(ExposureFilter::mayTarget('key', $partner, $others[7], $tids));
        self::assertFalse(ExposureFilter::mayTarget('key', $partner, $others[8], $tids));
    }

    public function testOrderIsBySha1OfSimKeyPartnerUser(): void
    {
        self::assertSame(sha1('k|3|7'), ExposureFilter::rankOf('k', 3, 7));
    }

    public function testDifferentSimKeyReshuffles(): void
    {
        $tids = self::league();
        $differs = false;
        foreach ($tids as $user) {
            if (ExposureFilter::eligiblePartners('a', $user, $tids) !== ExposureFilter::eligiblePartners('b', $user, $tids)) {
                $differs = true;
                break;
            }
        }
        self::assertTrue($differs);
    }

    public function testUserNotInLeagueGetsNoPartners(): void
    {
        self::assertSame([], ExposureFilter::eligiblePartners('key', 99, self::league()));
    }

    public function testPartnerNeverTargetsItself(): void
    {
        self::assertFalse(ExposureFilter::mayTarget('key', 5, 5, self::league()));
        self::assertNotContains(5, ExposureFilter::eligiblePartners('key', 5, self::league()));
    }
}
