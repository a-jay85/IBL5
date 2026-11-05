<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradeFormContract;

/**
 * @phpstan-import-type TradingPlayerRow from \Trading\Contracts\TradeFormRepositoryInterface
 */
class TradeFormContractTest extends TestCase
{
    public function testAmountForUsesCurrentContractYear(): void
    {
        $this->assertSame(200, TradeFormContract::amountFor(self::row(2), false));
    }

    public function testAmountForAdvancesYearWhenFlagged(): void
    {
        $this->assertSame(300, TradeFormContract::amountFor(self::row(2), true));
    }

    public function testAmountForUnsignedTreatsAsYearOne(): void
    {
        $this->assertSame(100, TradeFormContract::amountFor(self::row(0), false));
    }

    public function testAmountForPastSixthYearIsZero(): void
    {
        $this->assertSame(0, TradeFormContract::amountFor(self::row(6), true));
    }

    /**
     * @return TradingPlayerRow
     */
    private static function row(int $cy): array
    {
        return [
            'pos' => 'C', 'name' => 'Test Player', 'pid' => 1, 'ordinal' => 1, 'cy' => $cy,
            'salary_yr1' => 100, 'salary_yr2' => 200, 'salary_yr3' => 300,
            'salary_yr4' => 400, 'salary_yr5' => 500, 'salary_yr6' => 600,
        ];
    }
}
