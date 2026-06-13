<?php

declare(strict_types=1);

namespace BigBoard;

use BigBoard\Contracts\BigBoardRepositoryInterface;
use BigBoard\Contracts\MockDraftServiceInterface;
use ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface;

/**
 * @see MockDraftServiceInterface
 *
 * @phpstan-import-type MockSlot from MockDraftServiceInterface
 * @phpstan-import-type RankedProspect from MockDraftServiceInterface
 * @phpstan-import-type MockResultRow from MockDraftServiceInterface
 * @phpstan-import-type BigBoardRow from \BigBoard\Contracts\BigBoardRepositoryInterface
 */
class MockDraftService implements MockDraftServiceInterface
{
    private ProjectedDraftOrderServiceInterface $draftOrderService;
    private BigBoardRepositoryInterface $boardRepo;

    public function __construct(
        ProjectedDraftOrderServiceInterface $draftOrderService,
        BigBoardRepositoryInterface $boardRepo
    ) {
        $this->draftOrderService = $draftOrderService;
        $this->boardRepo = $boardRepo;
    }

    /**
     * @see MockDraftServiceInterface::buildMockDraft()
     *
     * @param list<MockSlot> $orderedSlots
     * @param list<RankedProspect> $rankedProspects
     * @return list<MockResultRow>
     */
    public function buildMockDraft(array $orderedSlots, int $gmTeamId, array $rankedProspects): array
    {
        $result = [];
        $cursor = 0;
        $count = count($rankedProspects);

        foreach ($orderedSlots as $slot) {
            if ($slot['ownerId'] !== $gmTeamId) {
                continue;
            }

            if ($cursor < $count) {
                $prospect = $rankedProspects[$cursor];
                $suggestion = [
                    'name' => $prospect['name'],
                    'pos' => $prospect['pos'],
                    'note' => $prospect['note'],
                ];
                $cursor++;
            } else {
                $suggestion = null;
            }

            $result[] = [
                'round' => $slot['round'],
                'pick' => $slot['pick'],
                'suggestion' => $suggestion,
            ];
        }

        return $result;
    }

    /**
     * @see MockDraftServiceInterface::getMockDraftForTeam()
     *
     * @return list<MockResultRow>
     */
    public function getMockDraftForTeam(int $gmTeamId, int $seasonYear): array
    {
        $draftOrder = $this->draftOrderService->getFinalOrProjectedDraftOrder($seasonYear);

        $orderedSlots = [];
        foreach ($draftOrder['round1'] as $slot) {
            $orderedSlots[] = [
                'round' => 1,
                'pick' => $slot['pick'],
                'teamId' => $slot['teamId'],
                'ownerId' => $slot['ownerId'],
            ];
        }
        foreach ($draftOrder['round2'] as $slot) {
            $orderedSlots[] = [
                'round' => 2,
                'pick' => $slot['pick'],
                'teamId' => $slot['teamId'],
                'ownerId' => $slot['ownerId'],
            ];
        }

        $rankedProspects = [];
        foreach ($this->boardRepo->getAvailableProspects($gmTeamId) as $row) {
            $rankedProspects[] = [
                'prospect_id' => $row['prospect_id'],
                'name' => $row['name'],
                'pos' => $row['pos'],
                'rank' => $row['rank'],
                'note' => $row['note'],
            ];
        }

        return $this->buildMockDraft($orderedSlots, $gmTeamId, $rankedProspects);
    }
}
