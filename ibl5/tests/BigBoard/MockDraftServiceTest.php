<?php

declare(strict_types=1);

namespace Tests\BigBoard;

use BigBoard\Contracts\BigBoardRepositoryInterface;
use BigBoard\Contracts\MockDraftServiceInterface;
use BigBoard\MockDraftService;
use PHPUnit\Framework\TestCase;
use ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface;

/**
 * Pure-algorithm coverage for the deterministic mock-draft builder (no DB).
 *
 * @phpstan-import-type MockSlot from \BigBoard\Contracts\MockDraftServiceInterface
 * @phpstan-import-type RankedProspect from \BigBoard\Contracts\MockDraftServiceInterface
 * @phpstan-import-type DraftSlot from \ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface
 */
class MockDraftServiceTest extends TestCase
{
    private const GM_TEAM = 1;
    private const OTHER_TEAM = 2;

    public function testImplementsInterface(): void
    {
        $interfaces = class_implements(MockDraftService::class);
        self::assertContains(
            MockDraftServiceInterface::class,
            $interfaces ? $interfaces : [],
        );
    }

    public function testHappyPathAssignsTopRankedProspectsToOwnedSlotsInOrder(): void
    {
        $slots = [
            $this->slot(1, 1, self::GM_TEAM, self::GM_TEAM),
            $this->slot(1, 2, self::OTHER_TEAM, self::OTHER_TEAM),
            $this->slot(2, 1, self::GM_TEAM, self::GM_TEAM),
        ];
        $prospects = [
            $this->prospect(10, 'Alpha', 1),
            $this->prospect(11, 'Bravo', 2),
            $this->prospect(12, 'Charlie', 3),
        ];

        $result = $this->service()->buildMockDraft($slots, self::GM_TEAM, $prospects);

        // Only the two GM-owned slots come back, in walk order, top-2 by rank.
        self::assertCount(2, $result);
        self::assertSame(1, $result[0]['round']);
        self::assertSame(1, $result[0]['pick']);
        self::assertNotNull($result[0]['suggestion']);
        self::assertSame('Alpha', $result[0]['suggestion']['name']);
        self::assertSame(2, $result[1]['round']);
        self::assertNotNull($result[1]['suggestion']);
        self::assertSame('Bravo', $result[1]['suggestion']['name']);
    }

    public function testBoardExhaustedYieldsNullSuggestion(): void
    {
        $slots = [
            $this->slot(1, 1, self::GM_TEAM, self::GM_TEAM),
            $this->slot(1, 2, self::GM_TEAM, self::GM_TEAM),
            $this->slot(2, 1, self::GM_TEAM, self::GM_TEAM),
        ];
        $prospects = [
            $this->prospect(10, 'Alpha', 1),
            $this->prospect(11, 'Bravo', 2),
        ];

        $result = $this->service()->buildMockDraft($slots, self::GM_TEAM, $prospects);

        self::assertCount(3, $result);
        self::assertNotNull($result[0]['suggestion']);
        self::assertNotNull($result[1]['suggestion']);
        // Third owned slot has no prospect left.
        self::assertNull($result[2]['suggestion']);
    }

    public function testProspectNeverReusedAcrossOwnedSlots(): void
    {
        $slots = [
            $this->slot(1, 1, self::GM_TEAM, self::GM_TEAM),
            $this->slot(1, 2, self::GM_TEAM, self::GM_TEAM),
        ];
        $prospects = [
            $this->prospect(10, 'Alpha', 1),
            $this->prospect(11, 'Bravo', 2),
        ];

        $result = $this->service()->buildMockDraft($slots, self::GM_TEAM, $prospects);

        self::assertNotNull($result[0]['suggestion']);
        self::assertNotNull($result[1]['suggestion']);
        self::assertNotSame($result[0]['suggestion']['name'], $result[1]['suggestion']['name']);
    }

    public function testConsumesProspectsInProvidedOrderDeterministically(): void
    {
        // The repo pre-sorts rank ASC, id ASC; the builder must honor that order
        // exactly so equal ranks are tie-broken deterministically (id ASC).
        $slots = [
            $this->slot(1, 1, self::GM_TEAM, self::GM_TEAM),
            $this->slot(1, 2, self::GM_TEAM, self::GM_TEAM),
        ];
        $prospects = [
            $this->prospect(10, 'LowerId', 5),
            $this->prospect(11, 'HigherId', 5),
        ];

        $result = $this->service()->buildMockDraft($slots, self::GM_TEAM, $prospects);

        self::assertNotNull($result[0]['suggestion']);
        self::assertNotNull($result[1]['suggestion']);
        self::assertSame('LowerId', $result[0]['suggestion']['name']);
        self::assertSame('HigherId', $result[1]['suggestion']['name']);
    }

    public function testMatchesOnOwnerIdNotOriginalTeamId(): void
    {
        $slots = [
            // Traded-IN pick: original franchise is OTHER_TEAM, but the GM owns it.
            $this->slot(1, 1, self::OTHER_TEAM, self::GM_TEAM),
            // Traded-AWAY pick: GM is the original franchise, but OTHER_TEAM owns it.
            $this->slot(1, 2, self::GM_TEAM, self::OTHER_TEAM),
        ];
        $prospects = [$this->prospect(10, 'Alpha', 1)];

        $result = $this->service()->buildMockDraft($slots, self::GM_TEAM, $prospects);

        // Exactly the traded-in slot is matched; the traded-away one is excluded.
        self::assertCount(1, $result);
        self::assertSame(1, $result[0]['pick']);
        self::assertNotNull($result[0]['suggestion']);
        self::assertSame('Alpha', $result[0]['suggestion']['name']);
    }

    public function testNoOwnedSlotsYieldsEmptyResult(): void
    {
        $slots = [
            $this->slot(1, 1, self::OTHER_TEAM, self::OTHER_TEAM),
            $this->slot(1, 2, self::OTHER_TEAM, self::OTHER_TEAM),
        ];
        $prospects = [$this->prospect(10, 'Alpha', 1)];

        $result = $this->service()->buildMockDraft($slots, self::GM_TEAM, $prospects);

        self::assertSame([], $result);
    }

    public function testGetMockDraftForTeamFlattensRoundsAndExcludesDraftedProspects(): void
    {
        // The repo returns ONLY available (drafted=0) prospects — a drafted
        // prospect is absent upstream and can never be suggested.
        $draftOrder = [
            'round1' => [
                $this->draftSlot(1, self::GM_TEAM, self::GM_TEAM),
                $this->draftSlot(2, self::OTHER_TEAM, self::OTHER_TEAM),
            ],
            'round2' => [
                $this->draftSlot(1, self::GM_TEAM, self::GM_TEAM),
            ],
        ];

        $orderService = self::createStub(ProjectedDraftOrderServiceInterface::class);
        $orderService->method('getFinalOrProjectedDraftOrder')->willReturn($draftOrder);

        $repo = self::createStub(BigBoardRepositoryInterface::class);
        $repo->method('getAvailableProspects')->willReturn([
            ['id' => 1, 'prospect_id' => 10, 'rank' => 1, 'note' => 'great', 'name' => 'Alpha', 'pos' => 'PG', 'drafted' => 0],
        ]);

        $service = new MockDraftService($orderService, $repo);
        $result = $service->getMockDraftForTeam(self::GM_TEAM, 2026);

        // Two GM-owned slots across both rounds; only one available prospect.
        self::assertCount(2, $result);
        self::assertSame(1, $result[0]['round']);
        self::assertNotNull($result[0]['suggestion']);
        self::assertSame('Alpha', $result[0]['suggestion']['name']);
        self::assertSame(2, $result[1]['round']);
        self::assertNull($result[1]['suggestion']);
    }

    private function service(): MockDraftService
    {
        return new MockDraftService(
            self::createStub(ProjectedDraftOrderServiceInterface::class),
            self::createStub(BigBoardRepositoryInterface::class),
        );
    }

    /**
     * @return MockSlot
     */
    private function slot(int $round, int $pick, int $teamId, int $ownerId): array
    {
        return ['round' => $round, 'pick' => $pick, 'teamId' => $teamId, 'ownerId' => $ownerId];
    }

    /**
     * @return RankedProspect
     */
    private function prospect(int $prospectId, string $name, int $rank): array
    {
        return ['prospect_id' => $prospectId, 'name' => $name, 'pos' => 'PG', 'rank' => $rank, 'note' => ''];
    }

    /**
     * @return DraftSlot
     */
    private function draftSlot(int $pick, int $teamId, int $ownerId): array
    {
        return [
            'pick' => $pick,
            'teamId' => $teamId,
            'teamName' => 'Team ' . $teamId,
            'wins' => 0,
            'losses' => 0,
            'color1' => '000000',
            'color2' => 'FFFFFF',
            'ownerId' => $ownerId,
            'ownerName' => 'Owner ' . $ownerId,
            'ownerColor1' => '000000',
            'ownerColor2' => 'FFFFFF',
            'isTraded' => $teamId !== $ownerId,
            'notes' => '',
            'movement' => 0,
            'player' => '',
        ];
    }
}
