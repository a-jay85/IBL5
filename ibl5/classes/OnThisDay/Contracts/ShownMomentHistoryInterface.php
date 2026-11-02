<?php

declare(strict_types=1);

namespace OnThisDay\Contracts;

/**
 * Which moment keys each sim showed. A moment shown in an earlier sim is kept
 * out of later sims, so the card and the Discord post do not repeat each season.
 */
interface ShownMomentHistoryInterface
{
    /**
     * Record that $sim showed each key. Idempotent per (moment_key, sim): a
     * re-run for the same sim inserts nothing. Returns the number of new rows.
     *
     * @param list<string> $momentKeys
     */
    public function recordShown(int $sim, int $seasonYear, array $momentKeys): int;

    /**
     * Every key shown in a sim strictly before $sim, mapped to the latest such
     * sim. The current sim's own rows never appear. Returns [] when the history
     * table is empty or missing.
     *
     * @return array<string, int> moment_key => last shown sim
     */
    public function findShownBefore(int $sim): array;
}
