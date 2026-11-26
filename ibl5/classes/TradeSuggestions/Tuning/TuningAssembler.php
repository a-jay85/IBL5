<?php

declare(strict_types=1);

namespace TradeSuggestions\Tuning;

use TradeSuggestions\Engine\CandidateBudget;
use TradeSuggestions\Engine\CandidateGenerator;
use TradeSuggestions\Engine\Contracts\LeagueSnapshotProviderInterface;
use TradeSuggestions\Engine\DealRanker;
use TradeSuggestions\Engine\DealScorer;
use TradeSuggestions\Engine\ExposureFilter;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\RankGate;
use TradeSuggestions\Engine\RankGateCallback;
use TradeSuggestions\Engine\RankOutcome;
use TradeSuggestions\Engine\RankTrace;
use TradeSuggestions\Engine\ScoredDeal;
use TradeSuggestions\Feedback\DealKey;
use TradeSuggestions\Feedback\HiddenDealFilter;
use TradeSuggestions\SuggestionValidationFilter;
use TradeSuggestions\TeamMode;
use TradeSuggestions\Tuning\Contracts\TuningAssemblerInterface;
use TradeSuggestions\Tuning\Contracts\TuningFeedbackRepositoryInterface;
use TradeSuggestions\Valuation\GainWeights;
use TradeSuggestions\Veto\DealView;
use TradeSuggestions\Veto\VetoReport;
use TradeSuggestions\Veto\VetoSet;
use Trading\Contracts\TradeFormRepositoryInterface;

/**
 * @see TuningAssemblerInterface
 *
 * Runs the same generate, score, hide, rank steps the GM panel runs, with a
 * `RankTrace` so every scored deal gets a row and an outcome.
 */
final class TuningAssembler implements TuningAssemblerInterface
{
    private readonly \Closure $clock;

    /**
     * @param (\Closure(): float)|null $clock seconds; defaults to the monotonic clock
     */
    public function __construct(
        private readonly LeagueSnapshotProviderInterface $snapshots,
        private readonly CandidateGenerator $generator,
        private readonly VetoSet $vetoes,
        private readonly DealRanker $ranker,
        private readonly SuggestionValidationFilter $filter,
        private readonly TradeFormRepositoryInterface $teams,
        private readonly TuningFeedbackRepositoryInterface $feedback,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): float => hrtime(true) / 1e9;
    }

    /**
     * @see TuningAssemblerInterface::build()
     */
    public function build(TuningFilter $f): TuningReport
    {
        $start = $this->now();
        $teamNames = [];
        foreach ($this->teams->getAllTeamsWithCity() as $team) {
            $teamNames[$team['teamid']] = $team['team_name'];
        }

        $s = $this->snapshots->snapshot();
        // lastKey() is null until the first snapshot() call, so read it after.
        $simKey = $this->snapshots->lastKey() ?? '';

        $tids = $s->teamIds;
        sort($tids);
        $teams = $f->tid !== null ? [$f->tid] : $tids;
        // One query for the whole run.
        $index = new TuningFeedbackIndex($this->feedback->forTeams($teams));
        $gate = RankGateCallback::make($this->filter, $teamNames);

        $sections = [];
        $truncated = false;
        $teamsNotRun = [];
        foreach ($teams as $n => $tid) {
            if ($n > 0 && $this->now() - $start > TuningBudget::TIME_BUDGET_SECONDS) {
                $truncated = true;
                $teamsNotRun = array_values(array_slice($teams, $n));
                break;
            }

            // One method call per team, so its candidates and trace are freed
            // before the next team runs (the all-teams page ran out of memory).
            $sections[] = $this->section($s, $simKey, $tid, $f, $teamNames, $index, $gate);
        }

        return new TuningReport(
            simKey: $simKey,
            filter: $f,
            teamNames: $teamNames,
            config: $this->config(),
            sections: $sections,
            truncated: $truncated,
            teamsNotRun: $teamsNotRun,
            elapsedSeconds: $this->now() - $start,
        );
    }

    /**
     * @param array<int, string> $teamNames
     * @param callable(ScoredDeal, DealView): array{0: RankGate, 1: string, 2: ?string, 3: ?string} $gate
     */
    private function section(
        LeagueSnapshot $s,
        string $simKey,
        int $tid,
        TuningFilter $f,
        array $teamNames,
        TuningFeedbackIndex $index,
        callable $gate,
    ): TuningTeamSection {
        $g = $this->generator->generate($s, $tid, $simKey);
        $scored = DealScorer::scoreAll($s, $g, $g->shapeIdByKey);
        // Hidden ideas leave before the ranker, exactly as on the GM panel.
        $split = HiddenDealFilter::apply($scored, $index->hiddenKeys($tid));
        $kept = $split['kept'];
        $trace = new RankTrace();
        $result = $this->ranker->select($s, $simKey, $tid, $kept, $this->vetoes, $gate, DealRanker::MAX_SUGGESTIONS, $trace);

        $eligible = ExposureFilter::eligiblePartners($simKey, $tid, $s->teamIds);
        $entries = $trace->entries();
        $rows = [];
        foreach ($kept as $i => $deal) {
            $entry = $entries[$i] ?? null;
            $rows[] = self::row(
                $tid,
                $deal,
                $entry['outcome'] ?? RankOutcome::NotReached,
                $entry['rank'] ?? null,
                $entry['veto'] ?? VetoReport::none(),
                $entry['detail'] ?? null,
                $eligible,
                $index,
            );
        }
        foreach ($split['dropped'] as $deal) {
            $rows[] = self::row($tid, $deal, RankOutcome::Hidden, null, VetoReport::none(), null, $eligible, $index);
        }

        $unmatched = self::unmatched($index->forTeam($tid), $rows);
        $rows = self::sorted(self::byStatus($rows, $f->status));
        $shown = array_slice($rows, 0, TuningBudget::MAX_ROWS_PER_TEAM);

        return new TuningTeamSection(
            tid: $tid,
            teamName: $teamNames[$tid] ?? '',
            mode: $s->mode($tid),
            eligiblePartners: $eligible,
            generation: $g,
            diagnostics: $result->diagnostics,
            rows: $shown,
            omittedRows: count($rows) - count($shown),
            unmatchedFeedback: $unmatched,
        );
    }

    /**
     * The team's stored hides that no row of this run picked up, because the
     * engine did not regenerate that deal this sim.
     *
     * @param list<TuningFeedback> $feedback
     * @param list<TuningRow> $rows every row, before the status filter
     * @return list<TuningFeedback>
     */
    private static function unmatched(array $feedback, array $rows): array
    {
        $used = [];
        foreach ($rows as $row) {
            if ($row->feedback !== null) {
                $used[spl_object_id($row->feedback)] = true;
            }
        }

        return array_values(array_filter(
            $feedback,
            static fn (TuningFeedback $fb): bool => !isset($used[spl_object_id($fb)]),
        ));
    }

    private function now(): float
    {
        $t = ($this->clock)();

        if (is_float($t)) {
            return $t;
        }

        return is_int($t) ? (float) $t : 0.0;
    }

    /**
     * @param list<int> $eligible
     */
    private static function row(
        int $tid,
        ScoredDeal $deal,
        RankOutcome $outcome,
        ?int $rank,
        VetoReport $veto,
        ?string $detail,
        array $eligible,
        TuningFeedbackIndex $index,
    ): TuningRow {
        $key = DealKey::forCandidate($deal->candidate);

        return new TuningRow(
            tid: $tid,
            partnerTid: $deal->candidate->partnerTid,
            shapeId: $deal->shapeId,
            candidate: $deal->candidate,
            dealKey: $key->deal,
            coreKey: $key->core,
            user: $deal->user,
            partner: $deal->partner,
            weakerSideTotal: $deal->weakerSideTotal(),
            outcome: $outcome,
            rank: $rank,
            firedVetoIds: $veto->firedIds(),
            detail: $detail,
            exposureEligible: in_array($deal->candidate->partnerTid, $eligible, true),
            feedback: $index->lookup($tid, $key->deal, $key->core),
        );
    }

    /**
     * @param list<TuningRow> $rows
     * @return list<TuningRow>
     */
    private static function byStatus(array $rows, TuningStatus $status): array
    {
        $keep = match ($status) {
            TuningStatus::All => null,
            TuningStatus::Shown => RankOutcome::Selected,
            TuningStatus::Vetoed => RankOutcome::Vetoed,
            TuningStatus::Hidden => RankOutcome::Hidden,
        };
        if ($keep === null) {
            return $rows;
        }

        return array_values(array_filter($rows, static fn (TuningRow $r): bool => $r->outcome === $keep));
    }

    /**
     * Selected by rank, then Eligible by rank, then the other outcomes in
     * `RankOutcome` case order, each by weaker-side total descending.
     *
     * @param list<TuningRow> $rows
     * @return list<TuningRow>
     */
    private static function sorted(array $rows): array
    {
        $order = array_flip(array_map(static fn (RankOutcome $o): string => $o->value, RankOutcome::cases()));
        usort($rows, static function (TuningRow $a, TuningRow $b) use ($order): int {
            $byOutcome = $order[$a->outcome->value] <=> $order[$b->outcome->value];
            if ($byOutcome !== 0) {
                return $byOutcome;
            }
            if ($a->rank !== null && $b->rank !== null) {
                return $a->rank <=> $b->rank;
            }

            return $b->weakerSideTotal <=> $a->weakerSideTotal;
        });

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        $weights = [];
        foreach (TeamMode::cases() as $mode) {
            $weights[GainWeights::modeKey($mode)] = GainWeights::seasonWeights($mode);
        }

        return [
            'season_weights' => $weights,
            'exposure_k' => GainWeights::EXPOSURE_K,
            'candidate_budget' => CandidateBudget::toArray(),
            'ranker' => [
                'max_suggestions' => DealRanker::MAX_SUGGESTIONS,
                'max_validations' => DealRanker::MAX_VALIDATIONS,
                'keep_top' => DealRanker::KEEP_TOP,
            ],
            'lopsided_ratio' => GainWeights::LOPSIDED_RATIO,
            'veto_ids' => $this->vetoes->ids(),
            'tuning_budget' => TuningBudget::toArray(),
        ];
    }
}
