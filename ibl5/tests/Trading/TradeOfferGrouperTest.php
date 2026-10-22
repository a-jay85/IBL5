<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradeOfferGrouper;
use Trading\Contracts\TradeAssetRepositoryInterface;
use Trading\Contracts\TradeCashRepositoryInterface;

class TradeOfferGrouperTest extends TestCase
{
    /**
     * @return array{tradeofferid: int, itemid: int, itemtype: string, trade_from: string, trade_to: string, approval: string, created_at: string, updated_at: string}
     */
    private function makeRow(
        int $offerId,
        int $itemId,
        string $itemType,
        string $from,
        string $to,
        string $approval = 'Celtics'
    ): array {
        return [
            'tradeofferid' => $offerId,
            'itemid' => $itemId,
            'itemtype' => $itemType,
            'trade_from' => $from,
            'trade_to' => $to,
            'approval' => $approval,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ];
    }

    public function testGroupOffersReturnsEmptyForNoRows(): void
    {
        $assetRepo = self::createStub(TradeAssetRepositoryInterface::class);
        $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
        $grouper = new TradeOfferGrouper($assetRepo, $cashRepo);

        $result = $grouper->groupOffers([], 'Lakers', 2025);

        $this->assertSame([], $result);
    }

    public function testGroupOffersFiltersRowsNotInvolvingUserTeam(): void
    {
        $assetRepo = self::createStub(TradeAssetRepositoryInterface::class);
        $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
        $assetRepo->method('getPlayersByIds')->willReturn([]);
        $assetRepo->method('getDraftPicksByIds')->willReturn([]);
        $cashRepo->method('getCashTransactionsByOfferIds')->willReturn([]);
        $grouper = new TradeOfferGrouper($assetRepo, $cashRepo);

        $rows = [
            $this->makeRow(1, 100, '1', 'Lakers', 'Celtics', 'Celtics'),
            $this->makeRow(2, 200, '1', 'Heat', 'Bulls', 'Bulls'),   // user not involved
        ];

        $result = $grouper->groupOffers($rows, 'Lakers', 2025);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayNotHasKey(2, $result);
    }

    public function testGroupOffersResolvesPlayerItem(): void
    {
        $assetRepo = self::createStub(TradeAssetRepositoryInterface::class);
        $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
        $assetRepo->method('getPlayersByIds')->willReturn([
            100 => ['name' => 'LeBron James', 'pos' => 'SF', 'pid' => 100],
        ]);
        $assetRepo->method('getDraftPicksByIds')->willReturn([]);
        $cashRepo->method('getCashTransactionsByOfferIds')->willReturn([]);
        $grouper = new TradeOfferGrouper($assetRepo, $cashRepo);

        $rows = [
            $this->makeRow(1, 100, '1', 'Lakers', 'Celtics', 'Celtics'),
        ];

        $result = $grouper->groupOffers($rows, 'Lakers', 2025);

        $this->assertCount(1, $result[1]['items']);
        $item = $result[1]['items'][0];
        $this->assertSame('player', $item['type']);
        $this->assertStringContainsString('LeBron James', $item['description']);
        $this->assertStringContainsString('Lakers', $item['description']);
        $this->assertStringContainsString('Celtics', $item['description']);
    }

    public function testGroupOffersResolvesPickItem(): void
    {
        $assetRepo = self::createStub(TradeAssetRepositoryInterface::class);
        $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
        $assetRepo->method('getPlayersByIds')->willReturn([]);
        $assetRepo->method('getDraftPicksByIds')->willReturn([
            501 => ['teampick' => 'Lakers', 'year' => 2026, 'round' => 1, 'notes' => ''],
        ]);
        $cashRepo->method('getCashTransactionsByOfferIds')->willReturn([]);
        $grouper = new TradeOfferGrouper($assetRepo, $cashRepo);

        $rows = [
            $this->makeRow(1, 501, '0', 'Lakers', 'Celtics', 'Celtics'),
        ];

        $result = $grouper->groupOffers($rows, 'Lakers', 2025);

        $this->assertCount(1, $result[1]['items']);
        $item = $result[1]['items'][0];
        $this->assertSame('pick', $item['type']);
        $this->assertStringContainsString('2026', $item['description']);
        $this->assertStringContainsString('Round 1', $item['description']);
    }

    public function testGroupOffersSetsHasHammerCorrectly(): void
    {
        $assetRepo = self::createStub(TradeAssetRepositoryInterface::class);
        $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
        $assetRepo->method('getPlayersByIds')->willReturn([
            100 => ['name' => 'Player A', 'pos' => 'PG', 'pid' => 100],
            200 => ['name' => 'Player B', 'pos' => 'SG', 'pid' => 200],
        ]);
        $assetRepo->method('getDraftPicksByIds')->willReturn([]);
        $cashRepo->method('getCashTransactionsByOfferIds')->willReturn([]);
        $grouper = new TradeOfferGrouper($assetRepo, $cashRepo);

        $rows = [
            // Offer 1: approval is Lakers (user has hammer)
            $this->makeRow(1, 100, '1', 'Celtics', 'Lakers', 'Lakers'),
            // Offer 2: approval is Celtics (user does not have hammer)
            $this->makeRow(2, 200, '1', 'Lakers', 'Celtics', 'Celtics'),
        ];

        $result = $grouper->groupOffers($rows, 'Lakers', 2025);

        $this->assertTrue($result[1]['hasHammer']);
        $this->assertFalse($result[2]['hasHammer']);
    }

    public function testTwoRowsSharingOfferIdMergeIntoOneGroup(): void
    {
        $assetRepo = self::createStub(TradeAssetRepositoryInterface::class);
        $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
        $assetRepo->method('getPlayersByIds')->willReturn([
            100 => ['name' => 'Player A', 'pos' => 'PG', 'pid' => 100],
            200 => ['name' => 'Player B', 'pos' => 'SG', 'pid' => 200],
        ]);
        $assetRepo->method('getDraftPicksByIds')->willReturn([]);
        $cashRepo->method('getCashTransactionsByOfferIds')->willReturn([]);
        $grouper = new TradeOfferGrouper($assetRepo, $cashRepo);

        $rows = [
            $this->makeRow(1, 100, '1', 'Lakers', 'Celtics', 'Celtics'),
            $this->makeRow(1, 200, '1', 'Celtics', 'Lakers', 'Celtics'),
        ];

        $result = $grouper->groupOffers($rows, 'Lakers', 2025);

        $this->assertCount(1, $result);
        $this->assertCount(2, $result[1]['items']);
    }

    public function testUnresolvedItemIdEmitsDocumentedFallbackShape(): void
    {
        $assetRepo = self::createStub(TradeAssetRepositoryInterface::class);
        $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
        $assetRepo->method('getPlayersByIds')->willReturn([]);
        $assetRepo->method('getDraftPicksByIds')->willReturn([]);
        $cashRepo->method('getCashTransactionsByOfferIds')->willReturn([]);
        $grouper = new TradeOfferGrouper($assetRepo, $cashRepo);

        $rows = [
            $this->makeRow(1, 999, '1', 'Lakers', 'Celtics', 'Celtics'),
        ];

        $result = $grouper->groupOffers($rows, 'Lakers', 2025);

        $item = $result[1]['items'][0];
        $this->assertSame('player', $item['type']);
        $this->assertSame('', $item['description']);
        $this->assertNull($item['notes']);
    }

    public function testCashStartYearShiftsWhenSeasonAdvancesContractYears(): void
    {
        $assetRepo = self::createStub(TradeAssetRepositoryInterface::class);
        $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
        $cashRepo->method('getCashTransactionsByOfferIds')->willReturn([]);
        $grouper = new TradeOfferGrouper($assetRepo, $cashRepo);

        $tradeOffers = [
            1 => [
                'from' => 'Lakers',
                'to' => 'Celtics',
                'approval' => 'Celtics',
                'oppositeTeam' => 'Celtics',
                'hasHammer' => false,
                'items' => [],
                'previewData' => ['fromPids' => [], 'toPids' => []],
            ],
        ];

        $allTeams = [
            ['teamid' => 5, 'team_name' => 'Lakers', 'team_city' => 'Los Angeles', 'color1' => '552583', 'color2' => 'FDB927'],
            ['teamid' => 9, 'team_name' => 'Celtics', 'team_city' => 'Boston', 'color1' => '007A33', 'color2' => 'BA9653'],
        ];

        $season = self::createStub(\Season\Season::class);
        $season->method('advancesContractYears')->willReturn(true);
        $season->endingYear = 2025;

        $result = $grouper->enrichWithPreviewData($tradeOffers, $allTeams, $season);

        $this->assertSame(2, $result[1]['previewData']['cashStartYear']);
    }

    /**
     * @return array{trade_offer_id: int, sending_team: string, receiving_team: string, salary_yr1: ?int, salary_yr2: ?int, salary_yr3: ?int, salary_yr4: ?int, salary_yr5: ?int, salary_yr6: ?int}
     */
    private function makeCashRow(int $offerId, string $sendingTeam, string $receivingTeam, ?int $yr1, ?int $yr2 = null): array
    {
        return [
            'trade_offer_id' => $offerId,
            'sending_team' => $sendingTeam,
            'receiving_team' => $receivingTeam,
            'salary_yr1' => $yr1,
            'salary_yr2' => $yr2,
            'salary_yr3' => null,
            'salary_yr4' => null,
            'salary_yr5' => null,
            'salary_yr6' => null,
        ];
    }

    public function testPlayerPidsClassifiedBySendingTeamOfFirstRow(): void
    {
        $assetRepo = self::createStub(TradeAssetRepositoryInterface::class);
        $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
        $assetRepo->method('getPlayersByIds')->willReturn([]);
        $assetRepo->method('getDraftPicksByIds')->willReturn([]);
        $cashRepo->method('getCashTransactionsByOfferIds')->willReturn([]);
        $grouper = new TradeOfferGrouper($assetRepo, $cashRepo);

        $rows = [
            $this->makeRow(1, 100, '1', 'Lakers', 'Celtics'),
            $this->makeRow(1, 200, '1', 'Celtics', 'Lakers'),
            $this->makeRow(1, 101, '1', 'Lakers', 'Celtics'),
        ];

        $result = $grouper->groupOffers($rows, 'Lakers', 2025);

        self::assertSame(['fromPids' => [100, 101], 'toPids' => [200]], $result[1]['previewData']);
    }

    public function testPidClassificationIsIndependentOfWhichTeamIsTheUser(): void
    {
        $assetRepo = self::createStub(TradeAssetRepositoryInterface::class);
        $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
        $assetRepo->method('getPlayersByIds')->willReturn([]);
        $assetRepo->method('getDraftPicksByIds')->willReturn([]);
        $cashRepo->method('getCashTransactionsByOfferIds')->willReturn([]);
        $grouper = new TradeOfferGrouper($assetRepo, $cashRepo);

        $rows = [
            $this->makeRow(1, 100, '1', 'Lakers', 'Celtics'),
            $this->makeRow(1, 200, '1', 'Celtics', 'Lakers'),
            $this->makeRow(1, 101, '1', 'Lakers', 'Celtics'),
        ];

        $result = $grouper->groupOffers($rows, 'Celtics', 2025);

        self::assertSame(['fromPids' => [100, 101], 'toPids' => [200]], $result[1]['previewData']);
    }

    public function testCashAndPickRowsStayOutOfPidLists(): void
    {
        $assetRepo = self::createStub(TradeAssetRepositoryInterface::class);
        $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
        $assetRepo->method('getPlayersByIds')->willReturn([]);
        $assetRepo->method('getDraftPicksByIds')->willReturn([]);
        $cashRepo->method('getCashTransactionsByOfferIds')->willReturn([]);
        $grouper = new TradeOfferGrouper($assetRepo, $cashRepo);

        $rows = [
            $this->makeRow(1, 100, '1', 'Lakers', 'Celtics'),
            $this->makeRow(1, 501, '0', 'Lakers', 'Celtics'),
            $this->makeRow(1, 0, 'cash', 'Celtics', 'Lakers'),
            $this->makeRow(1, 502, '0', 'Celtics', 'Lakers'),
        ];

        $result = $grouper->groupOffers($rows, 'Lakers', 2025);

        self::assertSame(['fromPids' => [100], 'toPids' => []], $result[1]['previewData']);
    }

    public function testBatchPreloadReceivesDeduplicatedIdsFromInvolvedRowsOnly(): void
    {
        $assetRepo = self::createMock(TradeAssetRepositoryInterface::class);
        $cashRepo = self::createMock(TradeCashRepositoryInterface::class);
        $assetRepo->expects(self::once())
            ->method('getPlayersByIds')
            ->with(self::identicalTo([100, 200]))
            ->willReturn([]);
        $assetRepo->expects(self::once())
            ->method('getDraftPicksByIds')
            ->with(self::identicalTo([501, 502]))
            ->willReturn([]);
        $cashRepo->expects(self::once())
            ->method('getCashTransactionsByOfferIds')
            ->with(self::identicalTo([1, 3]))
            ->willReturn([]);
        $grouper = new TradeOfferGrouper($assetRepo, $cashRepo);

        $rows = [
            $this->makeRow(1, 100, '1', 'Lakers', 'Celtics'),
            $this->makeRow(3, 100, '1', 'Lakers', 'Celtics'),
            $this->makeRow(1, 200, '1', 'Celtics', 'Lakers'),
            $this->makeRow(1, 501, '0', 'Lakers', 'Celtics'),
            $this->makeRow(3, 501, '0', 'Lakers', 'Celtics'),
            $this->makeRow(1, 502, '0', 'Celtics', 'Lakers'),
            $this->makeRow(1, 0, 'cash', 'Lakers', 'Celtics'),
            $this->makeRow(1, 0, 'cash', 'Celtics', 'Lakers'),
            $this->makeRow(3, 0, 'cash', 'Lakers', 'Celtics'),
            $this->makeRow(9, 300, '1', 'Heat', 'Bulls', 'Bulls'),
            $this->makeRow(9, 503, '0', 'Heat', 'Bulls', 'Bulls'),
            $this->makeRow(9, 0, 'cash', 'Heat', 'Bulls', 'Bulls'),
        ];

        $grouper->groupOffers($rows, 'Lakers', 2025);
    }

    public function testCashOnlyOfferResolvesCashItemsAndLeavesPidListsEmpty(): void
    {
        $assetRepo = self::createMock(TradeAssetRepositoryInterface::class);
        $cashRepo = self::createMock(TradeCashRepositoryInterface::class);
        $assetRepo->expects(self::once())
            ->method('getPlayersByIds')
            ->with(self::identicalTo([]))
            ->willReturn([]);
        $assetRepo->expects(self::once())
            ->method('getDraftPicksByIds')
            ->with(self::identicalTo([]))
            ->willReturn([]);
        $cashRepo->expects(self::once())
            ->method('getCashTransactionsByOfferIds')
            ->with(self::identicalTo([1]))
            ->willReturn(['1:Lakers' => $this->makeCashRow(1, 'Lakers', 'Celtics', 500)]);
        $grouper = new TradeOfferGrouper($assetRepo, $cashRepo);

        $result = $grouper->groupOffers([$this->makeRow(1, 0, 'cash', 'Lakers', 'Celtics')], 'Lakers', 2025);

        self::assertSame(
            [['type' => 'cash', 'description' => 'The Lakers send 500 in cash to the Celtics for 2024-2025.', 'notes' => null, 'from' => 'Lakers', 'to' => 'Celtics']],
            $result[1]['items']
        );
        self::assertSame(['fromPids' => [], 'toPids' => []], $result[1]['previewData']);
    }

    public function testOppositeTeamIsTheOtherTeamForBothDirections(): void
    {
        $assetRepo = self::createStub(TradeAssetRepositoryInterface::class);
        $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
        $assetRepo->method('getPlayersByIds')->willReturn([]);
        $assetRepo->method('getDraftPicksByIds')->willReturn([]);
        $cashRepo->method('getCashTransactionsByOfferIds')->willReturn([]);
        $grouper = new TradeOfferGrouper($assetRepo, $cashRepo);

        $rows = [
            $this->makeRow(1, 100, '1', 'Lakers', 'Celtics'),
            $this->makeRow(2, 200, '1', 'Celtics', 'Lakers'),
        ];

        $result = $grouper->groupOffers($rows, 'Lakers', 2025);

        self::assertSame('Celtics', $result[1]['oppositeTeam']);
        self::assertSame('Celtics', $result[2]['oppositeTeam']);
    }

    public function testHasHammerIsTrueWhenApprovalIsTestAccount(): void
    {
        $assetRepo = self::createStub(TradeAssetRepositoryInterface::class);
        $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
        $assetRepo->method('getPlayersByIds')->willReturn([]);
        $assetRepo->method('getDraftPicksByIds')->willReturn([]);
        $cashRepo->method('getCashTransactionsByOfferIds')->willReturn([]);
        $grouper = new TradeOfferGrouper($assetRepo, $cashRepo);

        $result = $grouper->groupOffers([$this->makeRow(1, 100, '1', 'Lakers', 'Celtics', 'test')], 'Lakers', 2025);

        self::assertTrue($result[1]['hasHammer']);
    }

    public function testUnknownItemTypeIsTreatedAsPlayerItemWithoutPidOrPreload(): void
    {
        $assetRepo = self::createMock(TradeAssetRepositoryInterface::class);
        $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
        $assetRepo->expects(self::once())
            ->method('getPlayersByIds')
            ->with(self::identicalTo([]))
            ->willReturn([]);
        $assetRepo->expects(self::once())
            ->method('getDraftPicksByIds')
            ->with(self::identicalTo([]))
            ->willReturn([]);
        $cashRepo->method('getCashTransactionsByOfferIds')->willReturn([]);
        $grouper = new TradeOfferGrouper($assetRepo, $cashRepo);

        $result = $grouper->groupOffers([$this->makeRow(1, 900, '2', 'Lakers', 'Celtics')], 'Lakers', 2025);

        self::assertSame(
            [['type' => 'player', 'description' => '', 'notes' => null, 'from' => 'Lakers', 'to' => 'Celtics']],
            $result[1]['items']
        );
        self::assertSame(['fromPids' => [], 'toPids' => []], $result[1]['previewData']);
    }

    public function testGroupOffersPinsFullResultShapeKeyOrderAndGroupOrder(): void
    {
        $assetRepo = self::createStub(TradeAssetRepositoryInterface::class);
        $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
        $assetRepo->method('getPlayersByIds')->willReturn([
            100 => ['name' => 'LeBron James', 'pos' => 'SF', 'pid' => 100],
            200 => ['name' => 'Austin Reaves', 'pos' => 'SG', 'pid' => 200],
        ]);
        $assetRepo->method('getDraftPicksByIds')->willReturn([
            501 => ['teampick' => 'Celtics', 'year' => 2026, 'round' => 1, 'notes' => ''],
        ]);
        $cashRepo->method('getCashTransactionsByOfferIds')->willReturn([
            '1:Celtics' => $this->makeCashRow(1, 'Celtics', 'Lakers', 300, 400),
        ]);
        $grouper = new TradeOfferGrouper($assetRepo, $cashRepo);

        $rows = [
            $this->makeRow(2, 501, '0', 'Celtics', 'Lakers', 'Lakers'),
            $this->makeRow(1, 100, '1', 'Lakers', 'Celtics', 'Celtics'),
            $this->makeRow(2, 200, '1', 'Lakers', 'Celtics', 'Lakers'),
            $this->makeRow(1, 0, 'cash', 'Celtics', 'Lakers', 'Celtics'),
        ];

        $result = $grouper->groupOffers($rows, 'Lakers', 2025);

        self::assertSame([
            2 => [
                'from' => 'Celtics',
                'to' => 'Lakers',
                'approval' => 'Lakers',
                'oppositeTeam' => 'Celtics',
                'hasHammer' => true,
                'items' => [
                    ['type' => 'pick', 'description' => 'The Celtics send the Celtics 2026 Round 1 draft pick to the Lakers.', 'notes' => null, 'from' => 'Celtics', 'to' => 'Lakers'],
                    ['type' => 'player', 'description' => 'The Lakers send SG Austin Reaves to the Celtics.', 'notes' => null, 'from' => 'Lakers', 'to' => 'Celtics'],
                ],
                'previewData' => ['fromPids' => [], 'toPids' => [200]],
            ],
            1 => [
                'from' => 'Lakers',
                'to' => 'Celtics',
                'approval' => 'Celtics',
                'oppositeTeam' => 'Celtics',
                'hasHammer' => false,
                'items' => [
                    ['type' => 'player', 'description' => 'The Lakers send SF LeBron James to the Celtics.', 'notes' => null, 'from' => 'Lakers', 'to' => 'Celtics'],
                    ['type' => 'cash', 'description' => 'The Celtics send 300 in cash to the Lakers for 2024-2025.', 'notes' => null, 'from' => 'Celtics', 'to' => 'Lakers'],
                    ['type' => 'cash', 'description' => 'The Celtics send 400 in cash to the Lakers for 2025-2026.', 'notes' => null, 'from' => 'Celtics', 'to' => 'Lakers'],
                ],
                'previewData' => ['fromPids' => [100], 'toPids' => []],
            ],
        ], $result);
    }

    public function testEnrichWithPreviewDataHandlesTeamMissingFromAllTeams(): void
    {
        $assetRepo = self::createStub(TradeAssetRepositoryInterface::class);
        $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
        $cashRepo->method('getCashTransactionsByOfferIds')->willReturn([]);
        $grouper = new TradeOfferGrouper($assetRepo, $cashRepo);

        $tradeOffers = [
            1 => [
                'from' => 'Lakers',
                'to' => 'UnknownTeam',
                'approval' => 'UnknownTeam',
                'oppositeTeam' => 'UnknownTeam',
                'hasHammer' => false,
                'items' => [],
                'previewData' => ['fromPids' => [], 'toPids' => []],
            ],
        ];

        $allTeams = [
            ['teamid' => 5, 'team_name' => 'Lakers', 'team_city' => 'Los Angeles', 'color1' => '552583', 'color2' => 'FDB927'],
        ];

        $season = self::createStub(\Season\Season::class);
        $season->method('advancesContractYears')->willReturn(false);
        $season->endingYear = 2025;

        $result = $grouper->enrichWithPreviewData($tradeOffers, $allTeams, $season);

        $preview = $result[1]['previewData'];
        $this->assertSame(5, $preview['fromTeamId']);
        $this->assertSame(0, $preview['toTeamId']);
        $this->assertSame('000000', $preview['toColor1']);
    }

    public function testEnrichWithPreviewDataAddsTeamIds(): void
    {
        $assetRepo = self::createStub(TradeAssetRepositoryInterface::class);
        $cashRepo = self::createStub(TradeCashRepositoryInterface::class);
        $cashRepo->method('getCashTransactionsByOfferIds')->willReturn([]);
        $grouper = new TradeOfferGrouper($assetRepo, $cashRepo);

        $tradeOffers = [
            1 => [
                'from' => 'Lakers',
                'to' => 'Celtics',
                'approval' => 'Celtics',
                'oppositeTeam' => 'Celtics',
                'hasHammer' => false,
                'items' => [],
                'previewData' => ['fromPids' => [], 'toPids' => []],
            ],
        ];

        $allTeams = [
            ['teamid' => 5, 'team_name' => 'Lakers', 'team_city' => 'Los Angeles', 'color1' => '552583', 'color2' => 'FDB927'],
            ['teamid' => 9, 'team_name' => 'Celtics', 'team_city' => 'Boston', 'color1' => '007A33', 'color2' => 'BA9653'],
        ];

        $season = self::createStub(\Season\Season::class);
        $season->method('advancesContractYears')->willReturn(false);
        $season->endingYear = 2025;

        $result = $grouper->enrichWithPreviewData($tradeOffers, $allTeams, $season);

        $preview = $result[1]['previewData'];
        $this->assertSame(5, $preview['fromTeamId']);
        $this->assertSame(9, $preview['toTeamId']);
        $this->assertSame('552583', $preview['fromColor1']);
        $this->assertSame('007A33', $preview['toColor1']);
        $this->assertSame(2025, $preview['seasonEndingYear']);
    }
}
