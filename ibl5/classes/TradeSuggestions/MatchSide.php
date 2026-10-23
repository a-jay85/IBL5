<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * One team as NeedPairMatcher sees it: needs, assets and starters.
 */
final readonly class MatchSide
{
    public function __construct(
        public NeedsCard $card,
        public TeamAssets $assets,
        public TeamDepth $depth,
    ) {
    }

    public function player(int $pid): ?SuggestionPlayer
    {
        foreach ($this->assets->players as $player) {
            if ($player->pid === $pid) {
                return $player;
            }
        }

        return null;
    }

    /**
     * Tradeable players who do not start, by dcMinutes desc, salary desc, pid asc.
     *
     * @return list<SuggestionPlayer>
     */
    public function tradeableBench(): array
    {
        return array_values(array_filter(
            SlotMapper::byMinutes($this->assets->players),
            fn (SuggestionPlayer $p): bool => $p->tradeable && $this->depth->slotOf($p->pid) === null,
        ));
    }

    /**
     * Round 2 before round 1, then the later year first.
     *
     * @return list<SuggestionPick>
     */
    public function cheapestPicks(): array
    {
        $picks = $this->assets->picks;
        usort($picks, static fn (SuggestionPick $a, SuggestionPick $b): int
            => [$b->round, $b->year, $a->pickId] <=> [$a->round, $a->year, $b->pickId]);

        return $picks;
    }

    /**
     * What this side can pay with: nothing, its cheapest pick, its two
     * cheapest picks, or its youngest bench player aged YOUNG_MAX_AGE or under.
     *
     * @return list<array{pids: list<int>, picks: list<int>}>
     */
    public function paymentLadder(): array
    {
        $ladder = [['pids' => [], 'picks' => []]];
        $picks = $this->cheapestPicks();
        if (isset($picks[0])) {
            $ladder[] = ['pids' => [], 'picks' => [$picks[0]->pickId]];
        }
        if (isset($picks[0], $picks[1])) {
            $ladder[] = ['pids' => [], 'picks' => [$picks[0]->pickId, $picks[1]->pickId]];
        }
        $young = array_values(array_filter(
            $this->tradeableBench(),
            static fn (SuggestionPlayer $p): bool => $p->age <= NeedThresholds::YOUNG_MAX_AGE,
        ));
        usort($young, static fn (SuggestionPlayer $a, SuggestionPlayer $b): int => [$a->age, $a->pid] <=> [$b->age, $b->pid]);
        if (isset($young[0])) {
            $ladder[] = ['pids' => [$young[0]->pid], 'picks' => []];
        }

        return $ladder;
    }
}
