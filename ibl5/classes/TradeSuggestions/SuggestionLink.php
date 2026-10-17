<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Builds the "Open in Trading" link for a suggestion.
 *
 * The link is a plain GET to the existing offer screen. It never targets
 * maketradeoffer.php, so following it can never submit an offer. The prefill
 * keys use the previousFormData['checkedItems'] format: "1:<pid>" for a player,
 * "0:<pickId>" for a draft pick.
 */
final class SuggestionLink
{
    public static function forCandidate(string $partnerTeamName, TradeCandidate $c): string
    {
        $keys = [];
        foreach ([...$c->userPids, ...$c->partnerPids] as $pid) {
            $keys[] = '1:' . $pid;
        }
        foreach ([...$c->userPickIds, ...$c->partnerPickIds] as $pickId) {
            $keys[] = '0:' . $pickId;
        }
        sort($keys, SORT_STRING);

        return 'modules.php?' . http_build_query([
            'name' => 'Trading',
            'op' => 'offertrade',
            'partner' => $partnerTeamName,
            'prefill' => implode(',', $keys),
        ]);
    }
}
