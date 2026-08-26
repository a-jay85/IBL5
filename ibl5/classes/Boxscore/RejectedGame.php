<?php

declare(strict_types=1);

namespace Boxscore;

final readonly class RejectedGame
{
    public const REASON_NOT_IN_SCHEDULE  = 'not_in_schedule';
    public const REASON_DUPLICATE_TRIPLE = 'duplicate_triple';

    /** @param list<int> $storedGameOfThatDay */
    public function __construct(
        public string $gameDate,
        public int $visitorTeamid,
        public int $homeTeamid,
        public int $gameOfThatDay,
        public string $reason,
        public array $storedGameOfThatDay = [],
    ) {}

    /** Returns the game triple in the form "YYYY-MM-DD visitor@home", e.g. "2008-04-05 21@17". */
    public function triple(): string
    {
        return sprintf('%s %d@%d', $this->gameDate, $this->visitorTeamid, $this->homeTeamid);
    }

    /**
     * Returns a plain-text description of the rejection.
     *
     * Format: "{triple} (gotd {gameOfThatDay})"
     * For duplicate-triple rejections, appends " — already stored at gotd {n, ...}".
     *
     * This is the single formatting point consumed by the HTML reject block (Phase 6)
     * and the Discord message (Phase 7). Neither re-formats a reject.
     */
    public function describe(): string
    {
        $base = sprintf('%s (gotd %d)', $this->triple(), $this->gameOfThatDay);
        if ($this->storedGameOfThatDay !== []) {
            $base .= sprintf(' — already stored at gotd %s', implode(', ', $this->storedGameOfThatDay));
        }
        return $base;
    }
}
