<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Engine\CandidateKey;
use TradeSuggestions\TradeCandidate;

final class CandidateKeyTest extends TestCase
{
    public function testKeyJoinsTidsAndSortedAssetListsWithSemicolons(): void
    {
        $key = CandidateKey::of(new TradeCandidate(7, 12, [3, 1], [12, 11], [205, 201], [22]));

        self::assertSame('7;12;1,3;11,12;201,205;22', $key);
    }

    public function testEmptyListsKeepTheirSeparators(): void
    {
        self::assertSame('1;2;;;;', CandidateKey::of(new TradeCandidate(1, 2, [], [], [], [])));
    }
}
