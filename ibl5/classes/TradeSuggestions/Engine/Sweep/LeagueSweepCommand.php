<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine\Sweep;

use Trading\Contracts\TradeFormRepositoryInterface;
use TradeSuggestions\Contracts\TradeSuggestionsServiceInterface;
use TradeSuggestions\Engine\Contracts\LeagueSnapshotProviderInterface;
use TradeSuggestions\Engine\DealScorer;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\SuggestionItem;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Veto\DealView;
use TradeSuggestions\Veto\VetoSet;

/**
 * The laugh test over a whole league. Builds every team's panel, then checks
 * each shown deal again outside the service: no veto may fire, both sides
 * must gain, and neither side may gain more than `GainWeights::LOPSIDED_RATIO`
 * times the other. Any shown deal that fails one of these is a violation.
 */
final class LeagueSweepCommand
{
    /** A deal rebuilt from a prefill link has no shape, and `DealScorer::score` needs a label. */
    public const string SWEEP_SHAPE_ID = 'SWEEP';

    public const string NOT_BOTH_POSITIVE = 'NOT_BOTH_POSITIVE';
    public const string LOPSIDED = 'LOPSIDED';

    public function __construct(
        private readonly TradeSuggestionsServiceInterface $service,
        private readonly LeagueSnapshotProviderInterface $snapshots,
        private readonly VetoSet $vetoes,
        private readonly TradeFormRepositoryInterface $formRepo,
    ) {
    }

    /**
     * Prints one line per team and a summary line. Returns 1 when any shown
     * deal is a violation, else 0.
     *
     * @param resource $out
     */
    public function run(int $maxTeams, mixed $out): int
    {
        $tidByName = [];
        foreach ($this->formRepo->getAllTeamsWithCity() as $team) {
            $tidByName[$team['team_name']] = $team['teamid'];
        }
        $tids = array_filter($tidByName, static fn (int $tid): bool => $tid <= $maxTeams);
        sort($tids);

        $teams = 0;
        $ideas = 0;
        $vetoed = 0;
        $shown = 0;
        $violations = 0;
        foreach ($tids as $tid) {
            $panel = $this->service->buildForTeam($tid);
            if ($panel === null) {
                fwrite($out, "tid={$tid} skipped" . PHP_EOL);
                continue;
            }
            $snap = $this->snapshots->snapshot();
            $generated = $panel->diagnostics->generated ?? 0;
            $teamVetoed = 0;
            foreach ($panel->diagnostics->rejections ?? [] as $key => $count) {
                if (str_starts_with($key, 'VETO:')) {
                    $teamVetoed += $count;
                }
            }
            $ids = [];
            $teamViolations = 0;
            foreach ($panel->items as $item) {
                $found = $this->violationIds($snap, self::candidateFromPrefill($tid, $item, $tidByName, $snap));
                if ($found !== []) {
                    $teamViolations++;
                    $ids = [...$ids, ...$found];
                }
            }
            $ids = array_unique($ids);
            sort($ids);

            $teams++;
            $ideas += $generated;
            $vetoed += $teamVetoed;
            $shown += count($panel->items);
            $violations += $teamViolations;
            fwrite($out, sprintf(
                'tid=%d ideas=%d vetoed=%d shown=%d violations=%d violation_ids=%s',
                $tid,
                $generated,
                $teamVetoed,
                count($panel->items),
                $teamViolations,
                implode(',', $ids),
            ) . PHP_EOL);
        }
        fwrite($out, sprintf('TEAMS=%d IDEAS=%d VETOED=%d SHOWN=%d VIOLATIONS=%d', $teams, $ideas, $vetoed, $shown, $violations) . PHP_EOL);

        return $violations > 0 ? 1 : 0;
    }

    /**
     * Rebuilds the deal from the item's prefill link (`1:<pid>` and `0:<pickid>`
     * keys), putting each asset on the side that owns it in the snapshot.
     *
     * @param array<string, int> $tidByName
     * @throws \UnexpectedValueException when the link names an unknown partner or an asset neither side owns
     */
    public static function candidateFromPrefill(int $tid, SuggestionItem $item, array $tidByName, LeagueSnapshot $s): TradeCandidate
    {
        $partnerTid = $tidByName[$item->partnerTeamName]
            ?? throw new \UnexpectedValueException("Unknown partner '{$item->partnerTeamName}'");

        $query = parse_url($item->prefillUrl, PHP_URL_QUERY);
        parse_str(is_string($query) ? $query : '', $params);
        $prefill = is_string($params['prefill'] ?? null) ? $params['prefill'] : '';

        $userPids = [];
        $partnerPids = [];
        $userPicks = [];
        $partnerPicks = [];
        foreach (explode(',', $prefill) as $key) {
            if (preg_match('/^([01]):([0-9]+)$/', $key, $m) !== 1) {
                throw new \UnexpectedValueException("Malformed prefill key '{$key}'");
            }
            $id = (int) $m[2];
            $isPlayer = $m[1] === '1';
            $owner = $isPlayer ? ($s->teamOfPid[$id] ?? null) : ($s->picks[$id]->ownerTid ?? null);
            if ($owner !== $tid && $owner !== $partnerTid) {
                throw new \UnexpectedValueException("Prefill key '{$key}' belongs to neither side");
            }
            if ($isPlayer && $owner === $tid) {
                $userPids[] = $id;
            } elseif ($isPlayer) {
                $partnerPids[] = $id;
            } elseif ($owner === $tid) {
                $userPicks[] = $id;
            } else {
                $partnerPicks[] = $id;
            }
        }

        return new TradeCandidate($tid, $partnerTid, $userPids, $userPicks, $partnerPids, $partnerPicks);
    }

    /**
     * @return list<string> the fired veto ids plus any gain-rule breaks
     */
    private function violationIds(LeagueSnapshot $s, TradeCandidate $c): array
    {
        $deal = DealScorer::score($s, $c, self::SWEEP_SHAPE_ID);
        $ids = $this->vetoes->evaluate(DealView::fromScoredDeal($s, $deal))->firedIds();
        if (!$deal->bothSidesPositive()) {
            $ids[] = self::NOT_BOTH_POSITIVE;
        } elseif ($deal->isLopsided()) {
            $ids[] = self::LOPSIDED;
        }

        return $ids;
    }
}
