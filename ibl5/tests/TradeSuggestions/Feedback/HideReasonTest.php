<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Feedback;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Feedback\HideReason;

final class HideReasonTest extends TestCase
{
    public function testFromCodesRejectsUnknownCode(): void
    {
        self::assertNull(HideReason::fromCodes(['TOO_OLD', 'LOL']));
    }

    public function testFromCodesRejectsNonArray(): void
    {
        self::assertNull(HideReason::fromCodes('TOO_OLD'));
    }

    public function testFromCodesRejectsEmpty(): void
    {
        self::assertNull(HideReason::fromCodes([]));
    }

    public function testFromCodesRejectsNonStringEntries(): void
    {
        self::assertNull(HideReason::fromCodes([1]));
        self::assertNull(HideReason::fromCodes([['TOO_OLD']]));
    }

    public function testFromCodesDedupesAndSorts(): void
    {
        self::assertSame(
            [HideReason::TooOld, HideReason::Other],
            HideReason::fromCodes(['OTHER', 'TOO_OLD', 'OTHER']),
        );
    }

    public function testJoinedLengthFitsColumn(): void
    {
        $joined = HideReason::join(HideReason::cases());

        self::assertSame(HideReason::MAX_JOINED_LENGTH, strlen($joined));
        self::assertLessThanOrEqual(HideReason::COLUMN_WIDTH, strlen($joined));
    }

    public function testAllSixCodesRoundTrip(): void
    {
        $codes = array_map(static fn (HideReason $r): string => $r->value, HideReason::cases());

        self::assertCount(6, $codes);
        $parsed = HideReason::fromCodes($codes);

        self::assertSame(HideReason::cases(), $parsed);
        self::assertSame(implode(',', $codes), HideReason::join($parsed));
    }

    public function testEveryReasonHasALabel(): void
    {
        foreach (HideReason::cases() as $reason) {
            self::assertNotSame('', $reason->label());
        }
    }
}
