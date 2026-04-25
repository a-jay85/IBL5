<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradingService;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradeAssetRepositoryInterface;
use Trading\Contracts\TradeFormRepositoryInterface;
use Trading\Contracts\TradeCashRepositoryInterface;
use Season\Season;

class TradingServiceTest extends TestCase
{
    private \mysqli $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new class extends \mysqli {
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct()
            {
                // Don't call parent::__construct() to avoid real DB connection
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $query): \mysqli_stmt|false
            {
                return false;
            }

            #[\ReturnTypeWillChange]
            public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): \mysqli_result|bool
            {
                return false;
            }
        };
    }

    // ============================================
    // CALCULATE FUTURE SALARIES TESTS
    // ============================================

    public function testCalculateFutureSalariesReturnsEmptyTotalsForNoPlayers(): void
    {
        $service = $this->createServiceWithStubs();
        $season = $this->createSeasonStub('Regular Season');

        $result = $service->calculateFutureSalaries([], $season);

        $this->assertSame([0, 0, 0, 0, 0, 0], $result['player']);
        $this->assertSame([0, 0, 0, 0, 0, 0], $result['hold']);
    }

    public function testCalculateFutureSalariesSumsPlayerContracts(): void
    {
        $service = $this->createServiceWithStubs();
        $season = $this->createSeasonStub('Regular Season');

        $players = [
            $this->createPlayerRow(cy: 1, salaryYr1: 500, salaryYr2: 600, salaryYr3: 0, salaryYr4: 0, salaryYr5: 0, salaryYr6: 0),
            $this->createPlayerRow(cy: 1, salaryYr1: 300, salaryYr2: 400, salaryYr3: 500, salaryYr4: 0, salaryYr5: 0, salaryYr6: 0),
        ];

        $result = $service->calculateFutureSalaries($players, $season);

        $this->assertSame(800, $result['player'][0]); // Year 1: 500 + 300
        $this->assertSame(1000, $result['player'][1]); // Year 2: 600 + 400
        $this->assertSame(500, $result['player'][2]); // Year 3: 0 + 500
    }

    public function testCalculateFutureSalariesCountsHoldsCorrectly(): void
    {
        $service = $this->createServiceWithStubs();
        $season = $this->createSeasonStub('Regular Season');

        $players = [
            $this->createPlayerRow(cy: 1, salaryYr1: 500, salaryYr2: 600, salaryYr3: 0, salaryYr4: 0, salaryYr5: 0, salaryYr6: 0),
            $this->createPlayerRow(cy: 1, salaryYr1: 300, salaryYr2: 0, salaryYr3: 0, salaryYr4: 0, salaryYr5: 0, salaryYr6: 0),
        ];

        $result = $service->calculateFutureSalaries($players, $season);

        $this->assertSame(2, $result['hold'][0]); // Both have salary_yr1 > 0
        $this->assertSame(1, $result['hold'][1]); // Only first has salary_yr2 > 0
        $this->assertSame(0, $result['hold'][2]); // Neither has salary_yr3 > 0
    }

    public function testCalculateFutureSalariesAdvancesContractYearInPlayoffs(): void
    {
        $service = $this->createServiceWithStubs();
        $season = $this->createSeasonStub('Playoffs');

        $players = [
            $this->createPlayerRow(cy: 1, salaryYr1: 500, salaryYr2: 600, salaryYr3: 700, salaryYr4: 0, salaryYr5: 0, salaryYr6: 0),
        ];

        $result = $service->calculateFutureSalaries($players, $season);

        // In Playoffs, cy is incremented: cy=1 -> cy=2, so starts reading from salary_yr2
        $this->assertSame(600, $result['player'][0]); // salary_yr2
        $this->assertSame(700, $result['player'][1]); // salary_yr3
    }

    public function testCalculateFutureSalariesAdvancesContractYearInDraft(): void
    {
        $service = $this->createServiceWithStubs();
        $season = $this->createSeasonStub('Draft');

        $players = [
            $this->createPlayerRow(cy: 2, salaryYr1: 100, salaryYr2: 200, salaryYr3: 300, salaryYr4: 400, salaryYr5: 0, salaryYr6: 0),
        ];

        $result = $service->calculateFutureSalaries($players, $season);

        // In Draft, cy is incremented: cy=2 -> cy=3, so starts reading from salary_yr3
        $this->assertSame(300, $result['player'][0]); // salary_yr3
        $this->assertSame(400, $result['player'][1]); // salary_yr4
    }

    public function testCalculateFutureSalariesAdvancesContractYearInFreeAgency(): void
    {
        $service = $this->createServiceWithStubs();
        $season = $this->createSeasonStub('Free Agency');

        $players = [
            $this->createPlayerRow(cy: 1, salaryYr1: 500, salaryYr2: 600, salaryYr3: 0, salaryYr4: 0, salaryYr5: 0, salaryYr6: 0),
        ];

        $result = $service->calculateFutureSalaries($players, $season);

        // In Free Agency, cy is incremented: cy=1 -> cy=2, so starts reading from salary_yr2
        $this->assertSame(600, $result['player'][0]); // salary_yr2
    }

    public function testCalculateFutureSalariesHandlesZeroContractYear(): void
    {
        $service = $this->createServiceWithStubs();
        $season = $this->createSeasonStub('Regular Season');

        $players = [
            $this->createPlayerRow(cy: 0, salaryYr1: 500, salaryYr2: 600, salaryYr3: 0, salaryYr4: 0, salaryYr5: 0, salaryYr6: 0),
        ];

        $result = $service->calculateFutureSalaries($players, $season);

        // cy=0 gets clamped to cy=1
        $this->assertSame(500, $result['player'][0]); // salary_yr1
        $this->assertSame(600, $result['player'][1]); // salary_yr2
    }

    public function testCalculateFutureSalariesHandlesExpiredContract(): void
    {
        $service = $this->createServiceWithStubs();
        $season = $this->createSeasonStub('Regular Season');

        $players = [
            $this->createPlayerRow(cy: 6, salaryYr1: 100, salaryYr2: 200, salaryYr3: 300, salaryYr4: 400, salaryYr5: 500, salaryYr6: 600),
        ];

        $result = $service->calculateFutureSalaries($players, $season);

        // cy=6, last year — only salary_yr6 contributes
        $this->assertSame(600, $result['player'][0]); // salary_yr6
        $this->assertSame(0, $result['player'][1]); // nothing beyond salary_yr6
    }

    // ============================================
    // GROUP TRADE OFFERS TESTS (via getTradeReviewPageData)
    // ============================================

    public function testGetTradeReviewPageDataReturnsEmptyOffersWhenNoneExist(): void
    {
        $mockOfferRepo = $this->createMock(TradeOfferRepositoryInterface::class);
        $mockAssetRepo = $this->createStub(TradeAssetRepositoryInterface::class);
        $mockFormRepo = $this->createMock(TradeFormRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createMock(\Services\CommonMysqliRepository::class);

        $mockCommon->expects($this->atLeastOnce())->method('getTeamnameFromUsername')
            ->willReturn('Lakers');
        $mockCommon->expects($this->atLeastOnce())->method('getTidFromTeamname')
            ->willReturn(1);
        $mockOfferRepo->expects($this->once())->method('getAllTradeOffers')
            ->willReturn([]);
        $mockFormRepo->expects($this->once())->method('getAllTeamsWithCity')
            ->willReturn([]);

        $service = new TradingService($mockOfferRepo, $mockAssetRepo, $mockFormRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $this->assertSame('Lakers', $result['userTeam']);
        $this->assertSame(1, $result['userTeamId']);
        $this->assertEmpty($result['tradeOffers']);
    }

    public function testGetTradeReviewPageDataFiltersToUserTeamOnly(): void
    {
        $mockOfferRepo = $this->createMock(TradeOfferRepositoryInterface::class);
        $mockAssetRepo = $this->createStub(TradeAssetRepositoryInterface::class);
        $mockFormRepo = $this->createMock(TradeFormRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createMock(\Services\CommonMysqliRepository::class);

        $mockCommon->expects($this->atLeastOnce())->method('getTeamnameFromUsername')
            ->willReturn('Lakers');
        $mockCommon->expects($this->atLeastOnce())->method('getTidFromTeamname')
            ->willReturn(1);
        $mockOfferRepo->expects($this->once())->method('getAllTradeOffers')
            ->willReturn([
                ['tradeofferid' => 1, 'itemid' => 100, 'itemtype' => '1', 'trade_from' => 'Lakers', 'trade_to' => 'Celtics', 'approval' => 'Celtics', 'created_at' => '', 'updated_at' => ''],
                ['tradeofferid' => 2, 'itemid' => 200, 'itemtype' => '1', 'trade_from' => 'Heat', 'trade_to' => 'Bulls', 'approval' => 'Bulls', 'created_at' => '', 'updated_at' => ''],
            ]);
        $mockAssetRepo->method('getPlayersByIds')
            ->willReturn([100 => ['name' => 'Test Player', 'pos' => 'PG', 'pid' => 100], 200 => ['name' => 'Test Player', 'pos' => 'PG', 'pid' => 200]]);
        $mockFormRepo->expects($this->once())->method('getAllTeamsWithCity')
            ->willReturn([]);

        $service = new TradingService($mockOfferRepo, $mockAssetRepo, $mockFormRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $this->assertCount(1, $result['tradeOffers']);
        $this->assertArrayHasKey(1, $result['tradeOffers']);
    }

    public function testGetTradeReviewPageDataSetsHasHammerCorrectly(): void
    {
        $mockOfferRepo = $this->createMock(TradeOfferRepositoryInterface::class);
        $mockAssetRepo = $this->createStub(TradeAssetRepositoryInterface::class);
        $mockFormRepo = $this->createMock(TradeFormRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createMock(\Services\CommonMysqliRepository::class);

        $mockCommon->expects($this->atLeastOnce())->method('getTeamnameFromUsername')
            ->willReturn('Lakers');
        $mockCommon->expects($this->atLeastOnce())->method('getTidFromTeamname')
            ->willReturn(1);
        $mockOfferRepo->expects($this->once())->method('getAllTradeOffers')
            ->willReturn([
                ['tradeofferid' => 1, 'itemid' => 100, 'itemtype' => '1', 'trade_from' => 'Celtics', 'trade_to' => 'Lakers', 'approval' => 'Lakers', 'created_at' => '', 'updated_at' => ''],
                ['tradeofferid' => 2, 'itemid' => 200, 'itemtype' => '1', 'trade_from' => 'Lakers', 'trade_to' => 'Heat', 'approval' => 'Heat', 'created_at' => '', 'updated_at' => ''],
            ]);
        $mockAssetRepo->method('getPlayersByIds')
            ->willReturn([100 => ['name' => 'Test Player', 'pos' => 'PG', 'pid' => 100], 200 => ['name' => 'Test Player', 'pos' => 'PG', 'pid' => 200]]);
        $mockFormRepo->expects($this->once())->method('getAllTeamsWithCity')
            ->willReturn([]);

        $service = new TradingService($mockOfferRepo, $mockAssetRepo, $mockFormRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $this->assertTrue($result['tradeOffers'][1]['hasHammer']);
        $this->assertFalse($result['tradeOffers'][2]['hasHammer']);
    }

    public function testGetTradeReviewPageDataBuildsTeamListExcludingFreeAgents(): void
    {
        $mockOfferRepo = $this->createMock(TradeOfferRepositoryInterface::class);
        $mockAssetRepo = $this->createStub(TradeAssetRepositoryInterface::class);
        $mockFormRepo = $this->createMock(TradeFormRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createMock(\Services\CommonMysqliRepository::class);

        $mockCommon->expects($this->atLeastOnce())->method('getTeamnameFromUsername')
            ->willReturn('Lakers');
        $mockCommon->expects($this->atLeastOnce())->method('getTidFromTeamname')
            ->willReturn(1);
        $mockOfferRepo->expects($this->once())->method('getAllTradeOffers')
            ->willReturn([]);
        $mockFormRepo->expects($this->once())->method('getAllTeamsWithCity')
            ->willReturn([
                ['teamid' => 1, 'team_name' => 'Lakers', 'team_city' => 'Los Angeles', 'color1' => '552583', 'color2' => 'FDB927'],
                ['teamid' => 0, 'team_name' => 'Free Agents', 'team_city' => '', 'color1' => '333333', 'color2' => 'FFFFFF'],
            ]);

        $service = new TradingService($mockOfferRepo, $mockAssetRepo, $mockFormRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $this->assertCount(1, $result['teams']);
        $this->assertSame('Lakers', $result['teams'][0]['name']);
    }

    // ============================================
    // CASH RESOLUTION TESTS (per-year lines)
    // ============================================

    public function testCashWithMultipleYearsProducesMultipleItems(): void
    {
        $mockOfferRepo = $this->createStub(TradeOfferRepositoryInterface::class);
        $mockAssetRepo = $this->createStub(TradeAssetRepositoryInterface::class);
        $mockFormRepo = $this->createStub(TradeFormRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createStub(\Services\CommonMysqliRepository::class);

        $mockCommon->method('getTeamnameFromUsername')->willReturn('Lakers');
        $mockCommon->method('getTidFromTeamname')->willReturn(1);
        $mockOfferRepo->method('getAllTradeOffers')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 0, 'itemtype' => 'cash', 'trade_from' => 'Lakers', 'trade_to' => 'Celtics', 'approval' => 'Celtics', 'created_at' => '', 'updated_at' => ''],
        ]);
        $mockCashRepo->method('getCashTransactionsByOfferIds')->willReturn([
            '1:Lakers' => ['tradeOfferID' => 1, 'sendingTeam' => 'Lakers', 'receivingTeam' => 'Celtics',
                'salary_yr1' => 100, 'salary_yr2' => 200, 'salary_yr3' => 150, 'salary_yr4' => null, 'salary_yr5' => null, 'salary_yr6' => null],
        ]);
        $mockFormRepo->method('getAllTeamsWithCity')->willReturn([]);

        $service = new TradingService($mockOfferRepo, $mockAssetRepo, $mockFormRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $items = $result['tradeOffers'][1]['items'];
        $this->assertCount(3, $items);
        $this->assertSame('cash', $items[0]['type']);
        $this->assertSame('cash', $items[1]['type']);
        $this->assertSame('cash', $items[2]['type']);
    }

    public function testCashZeroAmountYearsAreOmitted(): void
    {
        $mockOfferRepo = $this->createStub(TradeOfferRepositoryInterface::class);
        $mockAssetRepo = $this->createStub(TradeAssetRepositoryInterface::class);
        $mockFormRepo = $this->createStub(TradeFormRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createStub(\Services\CommonMysqliRepository::class);

        $mockCommon->method('getTeamnameFromUsername')->willReturn('Lakers');
        $mockCommon->method('getTidFromTeamname')->willReturn(1);
        $mockOfferRepo->method('getAllTradeOffers')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 0, 'itemtype' => 'cash', 'trade_from' => 'Lakers', 'trade_to' => 'Celtics', 'approval' => 'Celtics', 'created_at' => '', 'updated_at' => ''],
        ]);
        $mockCashRepo->method('getCashTransactionsByOfferIds')->willReturn([
            '1:Lakers' => ['tradeOfferID' => 1, 'sendingTeam' => 'Lakers', 'receivingTeam' => 'Celtics',
                'salary_yr1' => 0, 'salary_yr2' => 200, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 300, 'salary_yr6' => 0],
        ]);
        $mockFormRepo->method('getAllTeamsWithCity')->willReturn([]);

        $service = new TradingService($mockOfferRepo, $mockAssetRepo, $mockFormRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $items = $result['tradeOffers'][1]['items'];
        $this->assertCount(2, $items);
        $this->assertStringContainsString('200', $items[0]['description']);
        $this->assertStringContainsString('300', $items[1]['description']);
    }

    public function testCashYearLabelsAreCorrectlyComputed(): void
    {
        $mockOfferRepo = $this->createStub(TradeOfferRepositoryInterface::class);
        $mockAssetRepo = $this->createStub(TradeAssetRepositoryInterface::class);
        $mockFormRepo = $this->createStub(TradeFormRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createStub(\Services\CommonMysqliRepository::class);

        $mockCommon->method('getTeamnameFromUsername')->willReturn('Lakers');
        $mockCommon->method('getTidFromTeamname')->willReturn(1);
        $mockOfferRepo->method('getAllTradeOffers')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 0, 'itemtype' => 'cash', 'trade_from' => 'Lakers', 'trade_to' => 'Celtics', 'approval' => 'Celtics', 'created_at' => '', 'updated_at' => ''],
        ]);
        $mockCashRepo->method('getCashTransactionsByOfferIds')->willReturn([
            '1:Lakers' => ['tradeOfferID' => 1, 'sendingTeam' => 'Lakers', 'receivingTeam' => 'Celtics',
                'salary_yr1' => 100, 'salary_yr2' => 200, 'salary_yr3' => 0, 'salary_yr4' => 150, 'salary_yr5' => null, 'salary_yr6' => null],
        ]);
        $mockFormRepo->method('getAllTeamsWithCity')->willReturn([]);

        $service = new TradingService($mockOfferRepo, $mockAssetRepo, $mockFormRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $items = $result['tradeOffers'][1]['items'];
        $this->assertCount(3, $items);

        // Extract year labels from descriptions using regex
        $yearPattern = '/for (\d+)-(\d+)\.$/';

        $this->assertSame(1, preg_match($yearPattern, $items[0]['description'], $m1));
        $this->assertSame(1, preg_match($yearPattern, $items[1]['description'], $m2));
        $this->assertSame(1, preg_match($yearPattern, $items[2]['description'], $m3));

        // Each label spans exactly one year
        $this->assertSame((int) $m1[1] + 1, (int) $m1[2]);
        $this->assertSame((int) $m2[1] + 1, (int) $m2[2]);
        $this->assertSame((int) $m3[1] + 1, (int) $m3[2]);

        // salary_yr2 label is 1 year after salary_yr1, salary_yr4 label is 3 years after salary_yr1
        $this->assertSame((int) $m1[1] + 1, (int) $m2[1]);
        $this->assertSame((int) $m1[1] + 3, (int) $m3[1]);
    }

    public function testCashAllZeroProducesNoItems(): void
    {
        $mockOfferRepo = $this->createStub(TradeOfferRepositoryInterface::class);
        $mockAssetRepo = $this->createStub(TradeAssetRepositoryInterface::class);
        $mockFormRepo = $this->createStub(TradeFormRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createStub(\Services\CommonMysqliRepository::class);

        $mockCommon->method('getTeamnameFromUsername')->willReturn('Lakers');
        $mockCommon->method('getTidFromTeamname')->willReturn(1);
        $mockOfferRepo->method('getAllTradeOffers')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 0, 'itemtype' => 'cash', 'trade_from' => 'Lakers', 'trade_to' => 'Celtics', 'approval' => 'Celtics', 'created_at' => '', 'updated_at' => ''],
        ]);
        $mockCashRepo->method('getCashTransactionsByOfferIds')->willReturn([
            '1:Lakers' => ['tradeOfferID' => 1, 'sendingTeam' => 'Lakers', 'receivingTeam' => 'Celtics',
                'salary_yr1' => 0, 'salary_yr2' => 0, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
        ]);
        $mockFormRepo->method('getAllTeamsWithCity')->willReturn([]);

        $service = new TradingService($mockOfferRepo, $mockAssetRepo, $mockFormRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $items = $result['tradeOffers'][1]['items'];
        $this->assertCount(0, $items);
    }

    public function testCashNullDetailsProducesNoItems(): void
    {
        $mockOfferRepo = $this->createStub(TradeOfferRepositoryInterface::class);
        $mockAssetRepo = $this->createStub(TradeAssetRepositoryInterface::class);
        $mockFormRepo = $this->createStub(TradeFormRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createStub(\Services\CommonMysqliRepository::class);

        $mockCommon->method('getTeamnameFromUsername')->willReturn('Lakers');
        $mockCommon->method('getTidFromTeamname')->willReturn(1);
        $mockOfferRepo->method('getAllTradeOffers')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 0, 'itemtype' => 'cash', 'trade_from' => 'Lakers', 'trade_to' => 'Celtics', 'approval' => 'Celtics', 'created_at' => '', 'updated_at' => ''],
        ]);
        $mockCashRepo->method('getCashTransactionsByOfferIds')->willReturn([]);
        $mockFormRepo->method('getAllTeamsWithCity')->willReturn([]);

        $service = new TradingService($mockOfferRepo, $mockAssetRepo, $mockFormRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $items = $result['tradeOffers'][1]['items'];
        $this->assertCount(0, $items);
    }

    public function testCashDescriptionIncludesTeamNames(): void
    {
        $mockOfferRepo = $this->createStub(TradeOfferRepositoryInterface::class);
        $mockAssetRepo = $this->createStub(TradeAssetRepositoryInterface::class);
        $mockFormRepo = $this->createStub(TradeFormRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createStub(\Services\CommonMysqliRepository::class);

        $mockCommon->method('getTeamnameFromUsername')->willReturn('Lakers');
        $mockCommon->method('getTidFromTeamname')->willReturn(1);
        $mockOfferRepo->method('getAllTradeOffers')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 0, 'itemtype' => 'cash', 'trade_from' => 'Lakers', 'trade_to' => 'Celtics', 'approval' => 'Celtics', 'created_at' => '', 'updated_at' => ''],
        ]);
        $mockCashRepo->method('getCashTransactionsByOfferIds')->willReturn([
            '1:Lakers' => ['tradeOfferID' => 1, 'sendingTeam' => 'Lakers', 'receivingTeam' => 'Celtics',
                'salary_yr1' => 500, 'salary_yr2' => null, 'salary_yr3' => null, 'salary_yr4' => null, 'salary_yr5' => null, 'salary_yr6' => null],
        ]);
        $mockFormRepo->method('getAllTeamsWithCity')->willReturn([]);

        $service = new TradingService($mockOfferRepo, $mockAssetRepo, $mockFormRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $items = $result['tradeOffers'][1]['items'];
        $this->assertCount(1, $items);
        $this->assertStringContainsString('The Lakers send 500 in cash to the Celtics', $items[0]['description']);
        $this->assertSame('Lakers', $items[0]['from']);
        $this->assertSame('Celtics', $items[0]['to']);
    }

    // ============================================
    // PREVIEW DATA ENRICHMENT TESTS
    // ============================================

    public function testGetTradeReviewPageDataCollectsPlayerPids(): void
    {
        $mockOfferRepo = $this->createStub(TradeOfferRepositoryInterface::class);
        $mockAssetRepo = $this->createStub(TradeAssetRepositoryInterface::class);
        $mockFormRepo = $this->createStub(TradeFormRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createStub(\Services\CommonMysqliRepository::class);

        $mockCommon->method('getTeamnameFromUsername')->willReturn('Lakers');
        $mockCommon->method('getTidFromTeamname')->willReturn(1);
        $mockOfferRepo->method('getAllTradeOffers')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 100, 'itemtype' => '1', 'trade_from' => 'Lakers', 'trade_to' => 'Celtics', 'approval' => 'Celtics', 'created_at' => '', 'updated_at' => ''],
            ['tradeofferid' => 1, 'itemid' => 200, 'itemtype' => '1', 'trade_from' => 'Celtics', 'trade_to' => 'Lakers', 'approval' => 'Celtics', 'created_at' => '', 'updated_at' => ''],
        ]);
        $mockAssetRepo->method('getPlayersByIds')
            ->willReturn([100 => ['name' => 'Test Player', 'pos' => 'PG', 'pid' => 100], 200 => ['name' => 'Test Player', 'pos' => 'PG', 'pid' => 200]]);
        $mockFormRepo->method('getAllTeamsWithCity')->willReturn([
            ['teamid' => 1, 'team_name' => 'Lakers', 'team_city' => 'Los Angeles', 'color1' => '552583', 'color2' => 'FDB927'],
            ['teamid' => 2, 'team_name' => 'Celtics', 'team_city' => 'Boston', 'color1' => '007A33', 'color2' => 'FFFFFF'],
        ]);

        $service = new TradingService($mockOfferRepo, $mockAssetRepo, $mockFormRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $preview = $result['tradeOffers'][1]['previewData'];
        $this->assertSame([100], $preview['fromPids']);
        $this->assertSame([200], $preview['toPids']);
    }

    public function testGetTradeReviewPageDataEnrichesTeamIds(): void
    {
        $mockOfferRepo = $this->createStub(TradeOfferRepositoryInterface::class);
        $mockAssetRepo = $this->createStub(TradeAssetRepositoryInterface::class);
        $mockFormRepo = $this->createStub(TradeFormRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createStub(\Services\CommonMysqliRepository::class);

        $mockCommon->method('getTeamnameFromUsername')->willReturn('Lakers');
        $mockCommon->method('getTidFromTeamname')->willReturn(1);
        $mockOfferRepo->method('getAllTradeOffers')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 100, 'itemtype' => '1', 'trade_from' => 'Lakers', 'trade_to' => 'Celtics', 'approval' => 'Celtics', 'created_at' => '', 'updated_at' => ''],
        ]);
        $mockAssetRepo->method('getPlayersByIds')
            ->willReturn([100 => ['name' => 'Test Player', 'pos' => 'PG', 'pid' => 100], 200 => ['name' => 'Test Player', 'pos' => 'PG', 'pid' => 200]]);
        $mockFormRepo->method('getAllTeamsWithCity')->willReturn([
            ['teamid' => 1, 'team_name' => 'Lakers', 'team_city' => 'Los Angeles', 'color1' => '552583', 'color2' => 'FDB927'],
            ['teamid' => 2, 'team_name' => 'Celtics', 'team_city' => 'Boston', 'color1' => '007A33', 'color2' => 'FFFFFF'],
        ]);

        $service = new TradingService($mockOfferRepo, $mockAssetRepo, $mockFormRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $preview = $result['tradeOffers'][1]['previewData'];
        $this->assertSame(1, $preview['fromTeamId']);
        $this->assertSame(2, $preview['toTeamId']);
        $this->assertSame('552583', $preview['fromColor1']);
        $this->assertSame('007A33', $preview['toColor1']);
    }

    public function testGetTradeReviewPageDataIncludesCashPreviewData(): void
    {
        $mockOfferRepo = $this->createStub(TradeOfferRepositoryInterface::class);
        $mockAssetRepo = $this->createStub(TradeAssetRepositoryInterface::class);
        $mockFormRepo = $this->createStub(TradeFormRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createStub(\Services\CommonMysqliRepository::class);

        $mockCommon->method('getTeamnameFromUsername')->willReturn('Lakers');
        $mockCommon->method('getTidFromTeamname')->willReturn(1);
        $mockOfferRepo->method('getAllTradeOffers')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 100, 'itemtype' => '1', 'trade_from' => 'Lakers', 'trade_to' => 'Celtics', 'approval' => 'Celtics', 'created_at' => '', 'updated_at' => ''],
        ]);
        $mockAssetRepo->method('getPlayersByIds')
            ->willReturn([100 => ['name' => 'Test Player', 'pos' => 'PG', 'pid' => 100], 200 => ['name' => 'Test Player', 'pos' => 'PG', 'pid' => 200]]);
        $mockFormRepo->method('getAllTeamsWithCity')->willReturn([
            ['teamid' => 1, 'team_name' => 'Lakers', 'team_city' => 'Los Angeles', 'color1' => '552583', 'color2' => 'FDB927'],
            ['teamid' => 2, 'team_name' => 'Celtics', 'team_city' => 'Boston', 'color1' => '007A33', 'color2' => 'FFFFFF'],
        ]);
        $mockCashRepo->method('getCashTransactionsByOfferIds')->willReturn([
            '1:Lakers' => ['tradeOfferID' => 1, 'sendingTeam' => 'Lakers', 'receivingTeam' => 'Celtics',
                'salary_yr1' => 300, 'salary_yr2' => null, 'salary_yr3' => null, 'salary_yr4' => null, 'salary_yr5' => null, 'salary_yr6' => null],
        ]);

        $service = new TradingService($mockOfferRepo, $mockAssetRepo, $mockFormRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $preview = $result['tradeOffers'][1]['previewData'];
        $this->assertSame(300, $preview['fromCash'][1]);
        $this->assertSame(0, $preview['fromCash'][2]);
        $this->assertSame(0, $preview['toCash'][1]);
    }

    public function testGetTradeReviewPageDataPreviewDataIncludesSeasonInfo(): void
    {
        $mockOfferRepo = $this->createStub(TradeOfferRepositoryInterface::class);
        $mockAssetRepo = $this->createStub(TradeAssetRepositoryInterface::class);
        $mockFormRepo = $this->createStub(TradeFormRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createStub(\Services\CommonMysqliRepository::class);

        $mockCommon->method('getTeamnameFromUsername')->willReturn('Lakers');
        $mockCommon->method('getTidFromTeamname')->willReturn(1);
        $mockOfferRepo->method('getAllTradeOffers')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 100, 'itemtype' => '1', 'trade_from' => 'Lakers', 'trade_to' => 'Celtics', 'approval' => 'Celtics', 'created_at' => '', 'updated_at' => ''],
        ]);
        $mockAssetRepo->method('getPlayersByIds')
            ->willReturn([100 => ['name' => 'Test Player', 'pos' => 'PG', 'pid' => 100], 200 => ['name' => 'Test Player', 'pos' => 'PG', 'pid' => 200]]);
        $mockFormRepo->method('getAllTeamsWithCity')->willReturn([
            ['teamid' => 1, 'team_name' => 'Lakers', 'team_city' => 'Los Angeles', 'color1' => '552583', 'color2' => 'FDB927'],
        ]);

        $service = new TradingService($mockOfferRepo, $mockAssetRepo, $mockFormRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $preview = $result['tradeOffers'][1]['previewData'];
        $this->assertArrayHasKey('cashStartYear', $preview);
        $this->assertArrayHasKey('cashEndYear', $preview);
        $this->assertArrayHasKey('seasonEndingYear', $preview);
        $this->assertSame(6, $preview['cashEndYear']);
    }

    // ============================================
    // HELPERS
    // ============================================

    private function createServiceWithStubs(): TradingService
    {
        $stubOfferRepo = $this->createStub(TradeOfferRepositoryInterface::class);
        $stubAssetRepo = $this->createStub(TradeAssetRepositoryInterface::class);
        $stubFormRepo = $this->createStub(TradeFormRepositoryInterface::class);
        $stubCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $stubCommon = $this->createStub(\Services\CommonMysqliRepository::class);

        return new TradingService($stubOfferRepo, $stubAssetRepo, $stubFormRepo, $stubCommon, $this->mockDb, $stubCashRepo);
    }

    private function createSeasonStub(string $phase): Season
    {
        $season = $this->createStub(Season::class);
        $season->phase = $phase;
        $season->endingYear = 2025;
        $season->beginningYear = 2024;
        return $season;
    }

    private function createPlayerRow(
        int $cy = 1,
        int $salaryYr1 = 0,
        int $salaryYr2 = 0,
        int $salaryYr3 = 0,
        int $salaryYr4 = 0,
        int $salaryYr5 = 0,
        int $salaryYr6 = 0
    ): array {
        return [
            'pos' => 'PG',
            'name' => 'Test Player',
            'pid' => 1,
            'ordinal' => 5,
            'cy' => $cy,
            'salary_yr1' => $salaryYr1,
            'salary_yr2' => $salaryYr2,
            'salary_yr3' => $salaryYr3,
            'salary_yr4' => $salaryYr4,
            'salary_yr5' => $salaryYr5,
            'salary_yr6' => $salaryYr6,
        ];
    }

    // --- Merged from TradeApprovalTest ---

    /**
     * Test that approval field is always set to the listening team (receiving team)
     * This test verifies the fix for the bug where the offering team could accept
     * their own trade offer when cash was involved.
     */
    public function testApprovalAlwaysSetToListeningTeam(): void
    {
        $db = new \MockDatabase();
        $db->setMockData([
            ['id' => 1001, 'cnt' => 10],
            ['name' => 'Test Player', 'pos' => 'PG', 'cnt' => 10]
        ]);
        $db->onQuery('ibl_cash_considerations', []);
        $_SERVER['SERVER_NAME'] = 'localhost';

        $tradeOffer = new \Trading\TradeOffer($db);

        // Prepare trade data: Team A offers to Team B
        // Team A sends cash only (no players or picks)
        // Team B sends cash back
        $tradeData = [
            'offeringTeam' => 'Atlanta Hawks',
            'listeningTeam' => 'Boston Celtics',
            'switchCounter' => 0,      // No items from offering team (only cash)
            'fieldsCounter' => 0,      // No items from listening team (only cash)
            'userSendsCash' => [0, 150, 150, 0, 0, 0, 0],    // Team A sends cash
            'partnerSendsCash' => [0, 100, 200, 0, 0, 0, 0], // Team B sends cash
            'check' => [],             // No items to check
            'contract' => [],          // No contracts
            'index' => [],             // No items
            'type' => []               // No item types
        ];

        $result = $tradeOffer->createTradeOffer($tradeData);

        $this->assertTrue($result['success'], 'Trade offer should be created successfully');

        // Get all executed queries
        $queries = $db->getExecutedQueries();

        // Filter to INSERT INTO ibl_trade_info queries
        $tradeInfoInserts = array_filter($queries, function (string $query): bool {
            return stripos($query, 'INSERT INTO ibl_trade_info') !== false;
        });

        // Check each trade info insert - all should be cash items with approval = Boston Celtics
        foreach ($tradeInfoInserts as $query) {
            // For cash items, extract the from, to, and approval teams
            if (strpos($query, "'cash'") !== false) {
                // Pattern matches: VALUES ('tradeid', 'itemid', 'cash', 'trade_from', 'trade_to', 'approval')
                if (preg_match("/VALUES\s*\(\s*'[^']+'\s*,\s*'[^']+'\s*,\s*'cash'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/i", $query, $matches)) {
                    $from = $matches[1];
                    $to = $matches[2];
                    $approval = $matches[3];

                    // Approval should ALWAYS be 'Boston Celtics' (the listening team),
                    // regardless of whether the cash is from Atlanta or Boston
                    $this->assertEquals('Boston Celtics', $approval,
                        "For cash from {$from} to {$to}, approval should always be the listening team (Boston Celtics), but got {$approval}");
                }
            }
        }

        unset($_SERVER['SERVER_NAME']);
    }

    /**
     * Test the specific bug scenario: when listening team sends cash back to offering team,
     * the approval should still be the listening team, not the offering team
     */
    public function testCashFromListeningTeamHasCorrectApproval(): void
    {
        $db = new \MockDatabase();
        $db->setMockData([
            ['id' => 1001, 'cnt' => 10],
            ['name' => 'Test Player', 'pos' => 'PG', 'cnt' => 10]
        ]);
        $db->onQuery('ibl_cash_considerations', []);
        $_SERVER['SERVER_NAME'] = 'localhost';

        $tradeOffer = new \Trading\TradeOffer($db);

        // Team A offers cash to Team B
        // Team B sends cash back to Team A
        // Expected: approval should be Team B (listening team) for ALL cash items
        $tradeData = [
            'offeringTeam' => 'Atlanta Hawks',
            'listeningTeam' => 'Boston Celtics',
            'switchCounter' => 0,
            'fieldsCounter' => 0,
            'userSendsCash' => [0, 300, 200, 0, 0, 0, 0],       // Atlanta sends cash
            'partnerSendsCash' => [0, 500, 500, 0, 0, 0, 0],    // Boston sends cash back
            'check' => [],
            'contract' => [],
            'index' => [],
            'type' => []
        ];

        $result = $tradeOffer->createTradeOffer($tradeData);
        $this->assertTrue($result['success']);

        $queries = $db->getExecutedQueries();

        // Find all cash inserts and verify approval is always Boston Celtics
        foreach ($queries as $query) {
            if (strpos($query, "'cash'") !== false) {
                // Extract approval value
                // Pattern matches: VALUES ('tradeid', 'itemid', 'cash', 'trade_from', 'trade_to', 'approval')
                if (preg_match("/VALUES\s*\(\s*'[^']+'\s*,\s*'[^']+'\s*,\s*'cash'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/i", $query, $matches)) {
                    $from = $matches[1];
                    $to = $matches[2];
                    $approval = $matches[3];

                    // This test verifies the fix: approval should always be the listening team (Boston Celtics)
                    $this->assertEquals('Boston Celtics', $approval,
                        "When {$from} sends cash to {$to}, " .
                        "approval must be Boston Celtics (the listening team). Got: {$approval}");
                }
            }
        }

        unset($_SERVER['SERVER_NAME']);
    }

    /**
     * Test trade with only players (no cash)
     * Verifies that approval is set correctly when only players are traded
     */
    public function testTradeWithOnlyPlayers(): void
    {
        $db = new QueryAwareMockDatabase();
        $_SERVER['SERVER_NAME'] = 'localhost';
        $tradeOffer = new \Trading\TradeOffer($db);

        // Team A sends Player 1 to Team B
        // Team B sends Player 2 back to Team A
        $tradeData = [
            'offeringTeam' => 'Atlanta Hawks',
            'listeningTeam' => 'Boston Celtics',
            'switchCounter' => 1,      // 1 player from offering team
            'fieldsCounter' => 2,      // 2 total (1 from each team)
            'userSendsCash' => [0, 0, 0, 0, 0, 0, 0],
            'partnerSendsCash' => [0, 0, 0, 0, 0, 0, 0],
            'check' => ['on', 'on'],   // Both players checked
            'contract' => [1000, 1500], // Player salaries
            'index' => [101, 102],      // Player IDs
            'type' => [1, 1]            // Both are players
        ];

        $result = $tradeOffer->createTradeOffer($tradeData);
        $this->assertTrue($result['success'], 'Trade with only players should succeed');

        // Verify all trade info inserts have correct approval
        $queries = $db->getExecutedQueries();
        $tradeInfoInserts = array_filter($queries, function (string $query): bool {
            return stripos($query, 'INSERT INTO ibl_trade_info') !== false;
        });

        foreach ($tradeInfoInserts as $query) {
            if (preg_match("/VALUES\s*\(\s*'[^']+'\s*,\s*'[^']+'\s*,\s*'[^']+'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/i", $query, $matches)) {
                $approval = $matches[3];
                $this->assertEquals('Boston Celtics', $approval,
                    "Player trade approval should be Boston Celtics (listening team), got {$approval}");
            }
        }

        unset($_SERVER['SERVER_NAME']);
    }

    /**
     * Test trade with mix of players and cash
     * Verifies that approval is set correctly for both player and cash items
     */
    public function testTradeWithPlayersAndCash(): void
    {
        $db = new QueryAwareMockDatabase();
        $_SERVER['SERVER_NAME'] = 'localhost';
        $tradeOffer = new \Trading\TradeOffer($db);

        // Team A sends Player 1 + cash to Team B
        // Team B sends Player 2 + cash back to Team A
        $tradeData = [
            'offeringTeam' => 'Atlanta Hawks',
            'listeningTeam' => 'Boston Celtics',
            'switchCounter' => 1,      // 1 player from offering team
            'fieldsCounter' => 2,      // 2 total (1 from each team)
            'userSendsCash' => [0, 200, 200, 0, 0, 0, 0],      // Atlanta sends cash
            'partnerSendsCash' => [0, 150, 150, 0, 0, 0, 0],   // Boston sends cash
            'check' => ['on', 'on'],   // Both players checked
            'contract' => [2000, 1800], // Player salaries
            'index' => [201, 202],      // Player IDs
            'type' => [1, 1]            // Both are players
        ];

        $result = $tradeOffer->createTradeOffer($tradeData);
        $this->assertTrue($result['success'], 'Trade with players and cash should succeed');

        // Verify all items (players and cash) have correct approval
        $queries = $db->getExecutedQueries();
        $tradeInfoInserts = array_filter($queries, function (string $query): bool {
            return stripos($query, 'INSERT INTO ibl_trade_info') !== false;
        });

        $this->assertGreaterThanOrEqual(4, count($tradeInfoInserts),
            'Should have at least 4 trade items (2 players + 2 cash)');

        foreach ($tradeInfoInserts as $query) {
            if (preg_match("/VALUES\s*\(\s*'[^']+'\s*,\s*'[^']+'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/i", $query, $matches)) {
                $itemType = $matches[1];
                $from = $matches[2];
                $to = $matches[3];
                $approval = $matches[4];

                $this->assertEquals('Boston Celtics', $approval,
                    "Mixed trade: {$itemType} from {$from} to {$to} should have approval=Boston Celtics, got {$approval}");
            }
        }

        unset($_SERVER['SERVER_NAME']);
    }

    /**
     * Test trade with draft picks only (no cash or players)
     * Verifies that approval is set correctly for pick trades
     */
    public function testTradeWithOnlyDraftPicks(): void
    {
        $db = new QueryAwareMockDatabase();
        $_SERVER['SERVER_NAME'] = 'localhost';
        $tradeOffer = new \Trading\TradeOffer($db);

        // Team A sends 2025 1st round pick to Team B
        // Team B sends 2026 1st round pick back to Team A
        $tradeData = [
            'offeringTeam' => 'Atlanta Hawks',
            'listeningTeam' => 'Boston Celtics',
            'switchCounter' => 1,      // 1 pick from offering team
            'fieldsCounter' => 2,      // 2 total (1 from each team)
            'userSendsCash' => [0, 0, 0, 0, 0, 0, 0],
            'partnerSendsCash' => [0, 0, 0, 0, 0, 0, 0],
            'check' => ['on', 'on'],   // Both picks checked
            'contract' => [0, 0],       // Picks have no salary
            'index' => [501, 502],      // Pick IDs
            'type' => [0, 0]            // Both are picks (0 = pick)
        ];

        $result = $tradeOffer->createTradeOffer($tradeData);
        $this->assertTrue($result['success'], 'Trade with only picks should succeed');

        // Verify all pick inserts have correct approval
        $queries = $db->getExecutedQueries();
        $tradeInfoInserts = array_filter($queries, function (string $query): bool {
            return stripos($query, 'INSERT INTO ibl_trade_info') !== false;
        });

        foreach ($tradeInfoInserts as $query) {
            if (preg_match("/VALUES\s*\(\s*'[^']+'\s*,\s*'[^']+'\s*,\s*'[^']+'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/i", $query, $matches)) {
                $approval = $matches[3];
                $this->assertEquals('Boston Celtics', $approval,
                    "Pick trade approval should be Boston Celtics (listening team), got {$approval}");
            }
        }

        unset($_SERVER['SERVER_NAME']);
    }
}

/**
 * Enhanced MockDatabase that can return different data based on query type
 * (Merged from TradeApprovalTest)
 */
class QueryAwareMockDatabase extends \MockDatabase
{
    public function sql_query(string $query): object|bool
    {
        // Track all executed queries
        $queries = $this->getExecutedQueries();
        $queries[] = $query;
        $this->clearQueries();
        foreach ($queries as $q) {
            parent::sql_query($q);
        }

        // For queries that expect boolean return (INSERT, UPDATE, DELETE)
        if (stripos($query, 'INSERT') === 0 ||
            stripos($query, 'UPDATE') === 0 ||
            stripos($query, 'DELETE') === 0) {
            return true;
        }

        // Return appropriate mock data based on query type
        if (stripos($query, 'LAST_INSERT_ID') !== false) {
            return new \MockDatabaseResult([['id' => 1001]]);
        }

        if (stripos($query, 'ibl_plr') !== false) {
            // Return roster count for COUNT queries
            if (stripos($query, 'COUNT(*)') !== false) {
                return new \MockDatabaseResult([['cnt' => 10]]);
            }
            // Return player data
            return new \MockDatabaseResult([
                ['name' => 'Test Player', 'pos' => 'PG']
            ]);
        }

        if (stripos($query, 'ibl_draft_picks') !== false) {
            // Return draft pick data
            return new \MockDatabaseResult([
                ['teampick' => 'Test Team', 'year' => 2025, 'round' => 1, 'notes' => '']
            ]);
        }

        if (stripos($query, 'ibl_trade_cash') !== false) {
            // Return cash data
            return new \MockDatabaseResult([
                ['salary_yr1' => 100, 'salary_yr2' => 200, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0]
            ]);
        }

        // Default: return empty result
        return new \MockDatabaseResult([]);
    }
}
