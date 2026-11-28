<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Engine\RankDiagnostics;

final class RankDiagnosticsTest extends TestCase
{
    public function testToArrayMapsEveryFieldToItsKey(): void
    {
        $diagnostics = new RankDiagnostics(10, 1, 2, 3, 4, ['V1' => 5], 6, ['FAIL_X' => 7], 8, 9);

        self::assertSame([
            'generated' => 10,
            'not_both_positive' => 1,
            'lopsided' => 2,
            'exposure' => 3,
            'vetoed' => 4,
            'vetoed_by_id' => ['V1' => 5],
            'validated' => 6,
            'validator_rejections' => ['FAIL_X' => 7],
            'no_motive' => 8,
            'selected' => 9,
        ], $diagnostics->toArray());
    }
}
