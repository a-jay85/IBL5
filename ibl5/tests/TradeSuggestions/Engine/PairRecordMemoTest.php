<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Engine\PairRecordMemo;
use TradeSuggestions\Projection\RatingSet;

final class PairRecordMemoTest extends TestCase
{
    public function testSamePidIsZeroAndNeverStored(): void
    {
        $memo = new PairRecordMemo();
        $set = RatingSet::fromRow(['r_3gp' => 80]);

        self::assertSame(0, $memo->diff(0, 'PG', 5, $set, 5, $set));
        self::assertSame(0, $memo->size());
    }

    public function testSwappedPairNegatesAndSharesOneEntry(): void
    {
        $memo = new PairRecordMemo();
        $high = RatingSet::fromRow(['r_3gp' => 90]);
        $low = RatingSet::fromRow(['r_3gp' => 10]);

        $forward = $memo->diff(0, 'PG', 1, $high, 2, $low);
        $backward = $memo->diff(0, 'PG', 2, $low, 1, $high);

        self::assertGreaterThan(0, $forward);
        self::assertSame(-$forward, $backward);
        self::assertSame(1, $memo->size());
    }
}
