<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\SuggestionLink;
use TradeSuggestions\TradeCandidate;

class SuggestionLinkTest extends TestCase
{
    public function testLinkCarriesSortedPrefillKeysAndNoSubmitTarget(): void
    {
        // User sends player 123; partner sends pick 456. Sorted keys put the pick ("0:") first.
        $candidate = new TradeCandidate(
            userTid: 1,
            partnerTid: 2,
            userPids: [123],
            userPickIds: [],
            partnerPids: [],
            partnerPickIds: [456],
        );

        $url = SuggestionLink::forCandidate('Trail Blazers', $candidate);

        self::assertSame(
            'modules.php?name=Trading&op=offertrade&partner=Trail+Blazers&prefill=0%3A456%2C1%3A123',
            $url,
        );
        self::assertStringNotContainsString('maketradeoffer', $url);
    }

    public function testLinkListsEveryAssetOnBothSides(): void
    {
        $candidate = new TradeCandidate(
            userTid: 1,
            partnerTid: 2,
            userPids: [900],
            userPickIds: [],
            partnerPids: [45],
            partnerPickIds: [],
        );

        $url = SuggestionLink::forCandidate('Heat', $candidate);

        $query = parse_url($url, PHP_URL_QUERY);
        self::assertIsString($query);
        parse_str($query, $params);
        self::assertSame('1:45,1:900', $params['prefill'] ?? null);
        self::assertSame('Heat', $params['partner'] ?? null);
    }
}
