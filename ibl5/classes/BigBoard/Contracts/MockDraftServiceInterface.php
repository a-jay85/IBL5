<?php

declare(strict_types=1);

namespace BigBoard\Contracts;

/**
 * MockDraftServiceInterface - Contract for the deterministic mock-draft builder.
 *
 * The mock walks the projected/final draft order and, at every slot the GM's
 * team OWNS (post-trade ownerId, not the original franchise teamId), suggests
 * the GM's highest-ranked still-available prospect. No opponent modeling: a
 * prospect leaves the pool only when (a) it was drafted for real (filtered
 * upstream) or (b) the GM already "spent" it at an earlier owned slot.
 *
 * @phpstan-type MockSlot array{round: int, pick: int, teamId: int, ownerId: int}
 * @phpstan-type RankedProspect array{prospect_id: int, name: string, pos: string, rank: int, note: string}
 * @phpstan-type MockResultRow array{round: int, pick: int, suggestion: array{name: string, pos: string, note: string}|null}
 */
interface MockDraftServiceInterface
{
    /**
     * Pure, DB-free mock builder. Returns one row per GM-owned slot (in walk
     * order); suggestion is the next unconsumed ranked prospect, or null once
     * the GM's board is exhausted.
     *
     * @param list<MockSlot> $orderedSlots round1 ++ round2, already pick-ordered
     * @param int $gmTeamId
     * @param list<RankedProspect> $rankedProspects Pre-filtered to available
     *        (drafted=0), pre-sorted rank ASC, id ASC.
     * @return list<MockResultRow>
     */
    public function buildMockDraft(array $orderedSlots, int $gmTeamId, array $rankedProspects): array;

    /**
     * Orchestration: assemble the ordered slots from the projected/final draft
     * order and the ranked prospects from the GM's board, then build the mock.
     *
     * @return list<MockResultRow>
     */
    public function getMockDraftForTeam(int $gmTeamId, int $seasonYear): array;
}
