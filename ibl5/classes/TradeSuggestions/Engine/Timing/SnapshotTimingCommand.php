<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine\Timing;

use Cache\Contracts\DatabaseCacheInterface;
use TradeSuggestions\Engine\CachedLeagueSnapshotProvider;
use TradeSuggestions\Engine\CandidateGenerator;
use TradeSuggestions\Engine\DealScorer;
use TradeSuggestions\Engine\PairRecordMemo;

/**
 * Times a cold snapshot, a warm snapshot, and one GM's generate-and-score
 * pass. It prints raw figures and never judges them against the targets
 * (warm under 1.0 s, cold under 3.0 s), so a slow run cannot hide.
 */
final class SnapshotTimingCommand
{
    public function __construct(
        private readonly CachedLeagueSnapshotProvider $provider,
        private readonly CandidateGenerator $generator,
        private readonly DatabaseCacheInterface $cache,
    ) {
    }

    /**
     * @param array{tid: int, repeat: int, host: string, db: string, user: string, pass: string} $parsed
     * @param resource $out
     */
    public function run(array $parsed, $out): int
    {
        $key = $this->provider->currentKey();
        $this->cache->delete($key);

        try {
            $start = hrtime(true);
            $this->provider->snapshot();
            $cold = self::seconds($start);

            $warm = INF;
            for ($i = 0; $i < $parsed['repeat']; $i++) {
                $start = hrtime(true);
                $this->provider->snapshot();
                $warm = min($warm, self::seconds($start));
            }

            $s = $this->provider->snapshot();
            $simKey = $this->provider->lastKey() ?? '';
            $best = INF;
            $raw = 0;
            $legal = 0;
            $capped = false;
            $partners = 0;
            $memoEntries = 0;
            for ($i = 0; $i < $parsed['repeat']; $i++) {
                $memo = new PairRecordMemo();
                $start = hrtime(true);
                $g = $this->generator->generate($s, $parsed['tid'], $simKey);
                DealScorer::scoreAll($s, $g, $g->shapeIdByKey, $memo);
                $best = min($best, self::seconds($start));
                $raw = $g->rawCount;
                $legal = count($g->candidates);
                $capped = $g->capped;
                $partners = count($g->partners);
                $memoEntries = $memo->size();
            }
        } finally {
            $this->cache->delete($key);
        }

        fwrite($out, sprintf(
            "cold_snapshot_s=%.4f warm_snapshot_s=%.4f generate_score_s=%.4f raw=%d legal=%d capped=%d partners=%d memo_entries=%d\n",
            $cold,
            $warm,
            $best,
            $raw,
            $legal,
            $capped ? 1 : 0,
            $partners,
            $memoEntries,
        ));
        fwrite($out, sprintf("TOTAL_WARM_S=%.4f\n", $warm + $best));
        fwrite($out, sprintf("TOTAL_COLD_S=%.4f\n", $cold + $best));

        return 0;
    }

    private static function seconds(int|float $start): float
    {
        return (hrtime(true) - $start) / 1e9;
    }
}
