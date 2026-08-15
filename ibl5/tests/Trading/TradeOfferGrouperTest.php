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
