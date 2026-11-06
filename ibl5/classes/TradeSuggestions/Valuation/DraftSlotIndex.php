<?php

declare(strict_types=1);

namespace TradeSuggestions\Valuation;

/**
 * Each original team's 1-based list position in the projected draft order.
 * List position is used, never `DraftSlot['pick']`.
 *
 * @phpstan-import-type ProjectedDraftOrderResult from \ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface
 */
final readonly class DraftSlotIndex
{
    /**
     * @param array<int, int> $round1 original tid => list position
     * @param array<int, int> $round2 original tid => list position
     */
    public function __construct(
        public array $round1,
        public array $round2,
        public int $teamCount,
    ) {
    }

    /**
     * @param ProjectedDraftOrderResult $draftOrder
     */
    public static function fromDraftOrder(array $draftOrder): self
    {
        $round1 = [];
        foreach ($draftOrder['round1'] as $position => $slot) {
            $round1[$slot['teamId']] = $position + 1;
        }

        $round2 = [];
        foreach ($draftOrder['round2'] as $position => $slot) {
            $round2[$slot['teamId']] = $position + 1;
        }

        return new self($round1, $round2, count($draftOrder['round1']));
    }

    /**
     * Null for an unknown team or a round other than 1-2.
     */
    public function position(int $round, int $originalTid): ?int
    {
        return match ($round) {
            1 => $this->round1[$originalTid] ?? null,
            2 => $this->round2[$originalTid] ?? null,
            default => null,
        };
    }
}
