<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\TradeCandidate;

/**
 * The one dedupe key for a candidate deal (D12). Asset order inside a side
 * does not matter, so each list is sorted before it is joined.
 */
final class CandidateKey
{
    public static function of(TradeCandidate $c): string
    {
        return implode(';', [
            (string) $c->userTid,
            (string) $c->partnerTid,
            self::join($c->userPids),
            self::join($c->userPickIds),
            self::join($c->partnerPids),
            self::join($c->partnerPickIds),
        ]);
    }

    /**
     * @param list<int> $ids
     */
    private static function join(array $ids): string
    {
        sort($ids);

        return implode(',', $ids);
    }
}
