<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\SuggestionValidationFilter;
use TradeSuggestions\Veto\DealView;

/**
 * The ranker's per-deal check for the homepage panel: the real trade
 * validation through `SuggestionValidationFilter`, then a reason for each
 * side from `ReasonWriter`. A side with no reason is no motive.
 */
final class RankGateCallback
{
    /**
     * @param array<int, string> $teamNames tid => name
     * @return callable(ScoredDeal, DealView): array{0: RankGate, 1: string, 2: ?string, 3: ?string}
     */
    public static function make(SuggestionValidationFilter $filter, array $teamNames): callable
    {
        return static function (ScoredDeal $deal, DealView $view) use ($filter, $teamNames): array {
            $c = $deal->candidate;
            $tradeData = $filter->buildTradeFormData(
                $teamNames[$c->userTid] ?? '',
                $teamNames[$c->partnerTid] ?? '',
                $c->userTid,
                $c->partnerTid,
                $c,
            );
            $failure = $filter->rejection($tradeData, $c->userTid, $c->partnerTid, $c);
            if ($failure !== null) {
                return [RankGate::Validator, $failure, null, null];
            }

            $userReason = ReasonWriter::forSide($view, $view->user, $teamNames);
            if ($userReason === null) {
                return [RankGate::NoMotive, 'user', null, null];
            }
            $partnerReason = ReasonWriter::forSide($view, $view->partner, $teamNames);
            if ($partnerReason === null) {
                return [RankGate::NoMotive, 'partner', null, null];
            }

            return [RankGate::Pass, '', $userReason, $partnerReason];
        };
    }
}
