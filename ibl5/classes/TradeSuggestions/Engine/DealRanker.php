<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\Valuation\GainWeights;
use TradeSuggestions\Veto\DealView;
use TradeSuggestions\Veto\VetoReport;
use TradeSuggestions\Veto\VetoSet;

/**
 * Orders scored deals and keeps the ones a GM could take seriously.
 *
 * Four gates run on every deal, in input order: both sides must gain; the
 * larger side may gain at most `GainWeights::LOPSIDED_RATIO` times the smaller;
 * the partner must be one the user may target under `ExposureFilter`; and no
 * veto may fire. No gate is skipped for a large gain. Survivors sort by the
 * weaker side's gain, then fewer assets, then the candidate itself, and the
 * caller's callback checks them one at a time, at most `MAX_VALIDATIONS`
 * times.
 */
final class DealRanker
{
    public const int MAX_SUGGESTIONS = 3;
    public const int MAX_VALIDATIONS = 40;
    /** How many passing deals a refresh can page through (F19). */
    public const int KEEP_TOP = 12;

    /**
     * @param list<ScoredDeal> $scored
     * @param callable(ScoredDeal, DealView): array{0: RankGate, 1: string, 2: ?string, 3: ?string} $passes
     * @param ?RankTrace $trace when set, gets one outcome per input deal (the tuning page); null changes nothing
     */
    public function select(LeagueSnapshot $s, string $simKey, int $userTid, array $scored, VetoSet $vetoes, callable $passes, int $limit = self::MAX_SUGGESTIONS, ?RankTrace $trace = null): RankResult
    {
        $memo = new PairRecordMemo();
        $partners = array_flip(ExposureFilter::eligiblePartners($simKey, $userTid, $s->teamIds));
        $vetoedById = array_fill_keys($vetoes->ids(), 0);
        $notBothPositive = 0;
        $lopsided = 0;
        $exposure = 0;
        $vetoed = 0;

        $survivors = [];
        foreach ($scored as $i => $deal) {
            if (!$deal->bothSidesPositive()) {
                $notBothPositive++;
                $trace?->record($i, RankOutcome::NotBothPositive, VetoReport::none(), null, null);
                continue;
            }
            $u = $deal->user->total();
            $p = $deal->partner->total();
            if (max($u, $p) > GainWeights::LOPSIDED_RATIO * min($u, $p)) {
                $lopsided++;
                $trace?->record($i, RankOutcome::Lopsided, VetoReport::none(), null, null);
                continue;
            }
            if (!isset($partners[$deal->candidate->partnerTid])) {
                $exposure++;
                $trace?->record($i, RankOutcome::Exposure, VetoReport::none(), null, null);
                continue;
            }
            $view = DealView::fromScoredDeal($s, $deal, $memo);
            $report = $vetoes->evaluate($view);
            if ($report->fired()) {
                $vetoed++;
                foreach ($report->firedIds() as $id) {
                    $vetoedById[$id]++;
                }
                $trace?->record($i, RankOutcome::Vetoed, $report, null, null);
                continue;
            }
            $survivors[] = ['deal' => $deal, 'view' => $view, 'index' => $i];
        }

        usort($survivors, static fn (array $a, array $b): int
            => [$b['deal']->weakerSideTotal(), self::assetCount($a['deal']), serialize($a['deal']->candidate)]
            <=> [$a['deal']->weakerSideTotal(), self::assetCount($b['deal']), serialize($b['deal']->candidate)]);

        $selected = [];
        $eligible = [];
        $validated = 0;
        $validatorRejections = [];
        $noMotive = 0;
        $budget = min(count($survivors), self::MAX_VALIDATIONS);
        while ($validated < $budget && count($eligible) < self::KEEP_TOP) {
            $survivor = $survivors[$validated];
            $validated++;
            [$gate, $detail, $userReason, $partnerReason] = $passes($survivor['deal'], $survivor['view']);
            if ($gate === RankGate::Validator) {
                $validatorRejections[$detail] = ($validatorRejections[$detail] ?? 0) + 1;
                $trace?->record($survivor['index'], RankOutcome::Validator, VetoReport::none(), null, $detail);
                continue;
            }
            if ($gate === RankGate::NoMotive) {
                $noMotive++;
                $trace?->record($survivor['index'], RankOutcome::NoMotive, VetoReport::none(), null, $detail);
                continue;
            }
            $ranked = new RankedDeal($survivor['deal'], $survivor['view'], count($eligible) + 1, $userReason, $partnerReason);
            $eligible[] = $ranked;
            if (count($selected) < $limit) {
                $selected[] = $ranked;
                $trace?->record($survivor['index'], RankOutcome::Selected, VetoReport::none(), $ranked->rank, null);
            } else {
                $trace?->record($survivor['index'], RankOutcome::Eligible, VetoReport::none(), $ranked->rank, null);
            }
        }
        if ($trace !== null) {
            // Sorted survivors the walk stopped before (validation budget or KEEP_TOP).
            for ($j = $validated, $n = count($survivors); $j < $n; $j++) {
                $trace->record($survivors[$j]['index'], RankOutcome::NotReached, VetoReport::none(), null, null);
            }
        }

        return new RankResult($selected, $eligible, new RankDiagnostics(
            generated: count($scored),
            notBothPositive: $notBothPositive,
            lopsided: $lopsided,
            exposure: $exposure,
            vetoed: $vetoed,
            vetoedById: $vetoedById,
            validated: $validated,
            validatorRejections: $validatorRejections,
            noMotive: $noMotive,
            selected: count($selected),
        ));
    }

    private static function assetCount(ScoredDeal $deal): int
    {
        $c = $deal->candidate;

        return count($c->userPids) + count($c->userPickIds) + count($c->partnerPids) + count($c->partnerPickIds);
    }
}
