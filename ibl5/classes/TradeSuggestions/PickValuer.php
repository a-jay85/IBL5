<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Values draft picks from the projected draft order. Pure.
 *
 * Current-year picks scale linearly by projected slot; future-year picks and
 * picks whose slot is unknown use flat values; past-year and round 3+ picks are worthless.
 *
 * @phpstan-import-type ProjectedDraftOrderResult from \ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface
 * @phpstan-import-type SuggestionPickRow from Contracts\SuggestionAssetRepositoryInterface
 * @phpstan-type SlotIndex array{round1: array<int, int>, round2: array<int, int>, teamCount: int}
 */
final class PickValuer
{
    // On AssetValuer's scale: a top projected first is worth about a 62-ovr player.
    public const float R1_FLOOR = 8.0;
    public const float R1_SPREAD = 22.0;
    public const float R2_FLOOR = 1.0;
    public const float R2_SPREAD = 3.0;
    public const float FUTURE_R1 = 14.0;
    public const float FUTURE_R2 = 2.0;

    /**
     * Map each original team to its 1-based list position within each round.
     * List position is used, never DraftSlot['pick'].
     *
     * @param ProjectedDraftOrderResult $draftOrder
     * @return SlotIndex
     */
    public function buildSlotIndex(array $draftOrder): array
    {
        $round1 = [];
        foreach ($draftOrder['round1'] as $position => $slot) {
            $round1[$slot['teamId']] = $position + 1;
        }

        $round2 = [];
        foreach ($draftOrder['round2'] as $position => $slot) {
            $round2[$slot['teamId']] = $position + 1;
        }

        return ['round1' => $round1, 'round2' => $round2, 'teamCount' => count($draftOrder['round1'])];
    }

    /**
     * @param SlotIndex $slotIndex
     */
    public function value(int $year, int $round, int $originalTid, array $slotIndex, int $endingYear): float
    {
        if ($year < $endingYear || $round < 1 || $round > 2) {
            return 0.0;
        }

        $slots = $round === 1 ? $slotIndex['round1'] : $slotIndex['round2'];
        if ($year === $endingYear && isset($slots[$originalTid])) {
            $teamCount = $slotIndex['teamCount'];
            $t = ($teamCount - $slots[$originalTid]) / max(1, $teamCount - 1);

            return $round === 1
                ? self::R1_FLOOR + self::R1_SPREAD * $t
                : self::R2_FLOOR + self::R2_SPREAD * $t;
        }

        return $round === 1 ? self::FUTURE_R1 : self::FUTURE_R2;
    }

    /**
     * Tier from the original team's projected slot. Round 1 slots 1-5 are
     * Lottery, 6-14 High, 15+ Late; every round 2 pick is Second. A pick for a
     * later year drops one tier, but a first never drops below Late. A first
     * whose team is not in the projection prices as Late. Past-year and
     * round 3+ picks have no tier.
     *
     * @param SlotIndex $slotIndex
     */
    public function tier(int $year, int $round, int $originalTid, array $slotIndex, int $endingYear): ?PickTier
    {
        if ($year < $endingYear || $round < 1 || $round > 2) {
            return null;
        }
        if ($round === 2) {
            return PickTier::Second;
        }

        $slot = $slotIndex['round1'][$originalTid] ?? null;
        $tier = match (true) {
            $slot === null => PickTier::Late,
            $slot <= 5 => PickTier::Lottery,
            $slot <= 14 => PickTier::High,
            default => PickTier::Late,
        };
        if ($year > $endingYear) {
            $tier = PickTier::from(max(PickTier::Late->value, $tier->value - 1));
        }

        return $tier;
    }

    /**
     * @param list<SuggestionPickRow> $rows
     * @param SlotIndex $slotIndex
     * @return list<SuggestionPick>
     */
    public function toPicks(array $rows, array $slotIndex, int $endingYear): array
    {
        $picks = [];
        foreach ($rows as $row) {
            $value = $this->value($row['year'], $row['round'], $row['teampick_teamid'], $slotIndex, $endingYear);
            if ($value === 0.0) {
                continue;
            }
            $tier = $this->tier($row['year'], $row['round'], $row['teampick_teamid'], $slotIndex, $endingYear);
            $picks[] = new SuggestionPick($row['pickid'], $row['teampick_teamid'], $row['year'], $row['round'], $value, $tier);
        }

        return $picks;
    }
}
