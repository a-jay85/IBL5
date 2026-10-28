<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * League-wide rank of each starter within his slot by points per 36 minutes.
 *
 * Points per 36 is one real box-score stat, not a composite. Starters with no
 * production line are left unranked and out of the count.
 */
final readonly class LeagueStarterRanks
{
    /**
     * @param array<string, list<SuggestionPlayer>> $ranked Per slot, best first
     */
    private function __construct(private array $ranked)
    {
    }

    /**
     * @param array<int, TeamDepth> $depths Keyed by tid
     */
    public static function build(array $depths): self
    {
        $ranked = [];
        foreach (SlotMapper::SLOTS as $slot) {
            $starters = [];
            foreach ($depths as $depth) {
                $starter = $depth->starterAt($slot);
                if ($starter !== null && $starter->hasProduction()) {
                    $starters[] = $starter;
                }
            }
            usort($starters, static fn (SuggestionPlayer $a, SuggestionPlayer $b): int
                => [self::per36($b), $a->pid] <=> [self::per36($a), $b->pid]);
            $ranked[$slot] = $starters;
        }

        return new self($ranked);
    }

    public function rankOf(int $pid, string $slot): ?int
    {
        foreach ($this->ranked[$slot] ?? [] as $i => $player) {
            if ($player->pid === $pid) {
                return $i + 1;
            }
        }

        return null;
    }

    public function countFor(string $slot): int
    {
        return count($this->ranked[$slot] ?? []);
    }

    /**
     * Where $p would rank if he started at $slot: 1 plus the ranked starters
     * there (other than $p) with strictly higher points per 36.
     */
    public function rankIfStarting(SuggestionPlayer $p, string $slot): ?int
    {
        if (!$p->hasProduction()) {
            return null;
        }

        $mine = self::per36($p);
        $better = 0;
        foreach ($this->ranked[$slot] ?? [] as $player) {
            if ($player->pid !== $p->pid && self::per36($player) > $mine) {
                $better++;
            }
        }

        return $better + 1;
    }

    private static function per36(SuggestionPlayer $p): float
    {
        return $p->production?->pointsPer36() ?? 0.0;
    }
}
