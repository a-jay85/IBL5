<?php

declare(strict_types=1);

namespace TradeSuggestions\Tuning;

/**
 * The page's stored hides, looked up by team and key. Every lookup is scoped
 * by tid, so one GM's hide never shows on another team's section for the
 * mirrored deal.
 */
final class TuningFeedbackIndex
{
    /** @var array<int, array<string, TuningFeedback>> tid => deal key => row */
    private array $byDeal = [];
    /** @var array<int, array<string, TuningFeedback>> tid => core key => newest row */
    private array $byCore = [];
    /** @var array<int, list<TuningFeedback>> */
    private array $byTid = [];

    /**
     * @param list<TuningFeedback> $rows newest first within a team
     */
    public function __construct(array $rows)
    {
        foreach ($rows as $row) {
            $this->byDeal[$row->tid][$row->dealKey] ??= $row;
            $this->byCore[$row->tid][$row->coreKey] ??= $row;
            $this->byTid[$row->tid][] = $row;
        }
    }

    /**
     * The hide whose deal key matches, else the newest whose core key matches.
     */
    public function lookup(int $tid, string $dealKey, string $coreKey): ?TuningFeedback
    {
        return $this->byDeal[$tid][$dealKey] ?? $this->byCore[$tid][$coreKey] ?? null;
    }

    /**
     * This team's hidden keys, in the shape `HiddenDealFilter::apply` takes.
     *
     * @return array{deal: list<string>, core: list<string>}
     */
    public function hiddenKeys(int $tid): array
    {
        $deal = [];
        $core = [];
        foreach ($this->byTid[$tid] ?? [] as $row) {
            $deal[] = $row->dealKey;
            $core[] = $row->coreKey;
        }

        return ['deal' => $deal, 'core' => $core];
    }

    /**
     * This team's hides, newest first.
     *
     * @return list<TuningFeedback>
     */
    public function forTeam(int $tid): array
    {
        return $this->byTid[$tid] ?? [];
    }
}
