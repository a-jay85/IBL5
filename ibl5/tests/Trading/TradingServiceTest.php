<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Trading\TradingService;
use Trading\Contracts\TradingRepositoryInterface;
use Trading\Contracts\TradeCashRepositoryInterface;

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
            $this->createPlayerRow(cy: 1, cy1: 500, cy2: 600, cy3: 0, cy4: 0, cy5: 0, cy6: 0),
            $this->createPlayerRow(cy: 1, cy1: 300, cy2: 400, cy3: 500, cy4: 0, cy5: 0, cy6: 0),
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
            $this->createPlayerRow(cy: 1, cy1: 500, cy2: 600, cy3: 0, cy4: 0, cy5: 0, cy6: 0),
            $this->createPlayerRow(cy: 1, cy1: 300, cy2: 0, cy3: 0, cy4: 0, cy5: 0, cy6: 0),
        ];

        $result = $service->calculateFutureSalaries($players, $season);

        $this->assertSame(2, $result['hold'][0]); // Both have cy1 > 0
        $this->assertSame(1, $result['hold'][1]); // Only first has cy2 > 0
        $this->assertSame(0, $result['hold'][2]); // Neither has cy3 > 0
    }

    public function testCalculateFutureSalariesAdvancesContractYearInPlayoffs(): void
    {
        $service = $this->createServiceWithStubs();
        $season = $this->createSeasonStub('Playoffs');

        $players = [
            $this->createPlayerRow(cy: 1, cy1: 500, cy2: 600, cy3: 700, cy4: 0, cy5: 0, cy6: 0),
        ];

        $result = $service->calculateFutureSalaries($players, $season);

        // In Playoffs, cy is incremented: cy=1 -> cy=2, so starts reading from cy2
        $this->assertSame(600, $result['player'][0]); // cy2
        $this->assertSame(700, $result['player'][1]); // cy3
    }

    public function testCalculateFutureSalariesAdvancesContractYearInDraft(): void
    {
        $service = $this->createServiceWithStubs();
        $season = $this->createSeasonStub('Draft');

        $players = [
            $this->createPlayerRow(cy: 2, cy1: 100, cy2: 200, cy3: 300, cy4: 400, cy5: 0, cy6: 0),
        ];

        $result = $service->calculateFutureSalaries($players, $season);

        // In Draft, cy is incremented: cy=2 -> cy=3, so starts reading from cy3
        $this->assertSame(300, $result['player'][0]); // cy3
        $this->assertSame(400, $result['player'][1]); // cy4
    }

    public function testCalculateFutureSalariesAdvancesContractYearInFreeAgency(): void
    {
        $service = $this->createServiceWithStubs();
        $season = $this->createSeasonStub('Free Agency');

        $players = [
            $this->createPlayerRow(cy: 1, cy1: 500, cy2: 600, cy3: 0, cy4: 0, cy5: 0, cy6: 0),
        ];

        $result = $service->calculateFutureSalaries($players, $season);

        // In Free Agency, cy is incremented: cy=1 -> cy=2, so starts reading from cy2
        $this->assertSame(600, $result['player'][0]); // cy2
    }

    public function testCalculateFutureSalariesHandlesZeroContractYear(): void
    {
        $service = $this->createServiceWithStubs();
        $season = $this->createSeasonStub('Regular Season');

        $players = [
            $this->createPlayerRow(cy: 0, cy1: 500, cy2: 600, cy3: 0, cy4: 0, cy5: 0, cy6: 0),
        ];

        $result = $service->calculateFutureSalaries($players, $season);

        // cy=0 gets clamped to cy=1
        $this->assertSame(500, $result['player'][0]); // cy1
        $this->assertSame(600, $result['player'][1]); // cy2
    }

    public function testCalculateFutureSalariesHandlesExpiredContract(): void
    {
        $service = $this->createServiceWithStubs();
        $season = $this->createSeasonStub('Regular Season');

        $players = [
            $this->createPlayerRow(cy: 6, cy1: 100, cy2: 200, cy3: 300, cy4: 400, cy5: 500, cy6: 600),
        ];

        $result = $service->calculateFutureSalaries($players, $season);

        // cy=6, last year — only cy6 contributes
        $this->assertSame(600, $result['player'][0]); // cy6
        $this->assertSame(0, $result['player'][1]); // nothing beyond cy6
    }

    // ============================================
    // GROUP TRADE OFFERS TESTS (via getTradeReviewPageData)
    // ============================================

    public function testGetTradeReviewPageDataReturnsEmptyOffersWhenNoneExist(): void
    {
        $mockRepo = $this->createMock(TradingRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createMock(\Services\CommonMysqliRepository::class);

        $mockCommon->expects($this->atLeastOnce())->method('getTeamnameFromUsername')
            ->willReturn('Lakers');
        $mockCommon->expects($this->atLeastOnce())->method('getTidFromTeamname')
            ->willReturn(1);
        $mockRepo->expects($this->once())->method('getAllTradeOffers')
            ->willReturn([]);
        $mockRepo->expects($this->once())->method('getAllTeamsWithCity')
            ->willReturn([]);

        $service = new TradingService($mockRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $this->assertSame('Lakers', $result['userTeam']);
        $this->assertSame(1, $result['userTeamId']);
        $this->assertEmpty($result['tradeOffers']);
    }

    public function testGetTradeReviewPageDataFiltersToUserTeamOnly(): void
    {
        $mockRepo = $this->createMock(TradingRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createMock(\Services\CommonMysqliRepository::class);

        $mockCommon->expects($this->atLeastOnce())->method('getTeamnameFromUsername')
            ->willReturn('Lakers');
        $mockCommon->expects($this->atLeastOnce())->method('getTidFromTeamname')
            ->willReturn(1);
        $mockRepo->expects($this->once())->method('getAllTradeOffers')
            ->willReturn([
                ['tradeofferid' => 1, 'itemid' => 100, 'itemtype' => '1', 'from' => 'Lakers', 'to' => 'Celtics', 'approval' => 'Celtics', 'created_at' => '', 'updated_at' => ''],
                ['tradeofferid' => 2, 'itemid' => 200, 'itemtype' => '1', 'from' => 'Heat', 'to' => 'Bulls', 'approval' => 'Bulls', 'created_at' => '', 'updated_at' => ''],
            ]);
        $mockRepo->method('getPlayerById')
            ->willReturn(['name' => 'Test Player', 'pos' => 'PG']);
        $mockRepo->expects($this->once())->method('getAllTeamsWithCity')
            ->willReturn([]);

        $service = new TradingService($mockRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $this->assertCount(1, $result['tradeOffers']);
        $this->assertArrayHasKey(1, $result['tradeOffers']);
    }

    public function testGetTradeReviewPageDataSetsHasHammerCorrectly(): void
    {
        $mockRepo = $this->createMock(TradingRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createMock(\Services\CommonMysqliRepository::class);

        $mockCommon->expects($this->atLeastOnce())->method('getTeamnameFromUsername')
            ->willReturn('Lakers');
        $mockCommon->expects($this->atLeastOnce())->method('getTidFromTeamname')
            ->willReturn(1);
        $mockRepo->expects($this->once())->method('getAllTradeOffers')
            ->willReturn([
                ['tradeofferid' => 1, 'itemid' => 100, 'itemtype' => '1', 'from' => 'Celtics', 'to' => 'Lakers', 'approval' => 'Lakers', 'created_at' => '', 'updated_at' => ''],
                ['tradeofferid' => 2, 'itemid' => 200, 'itemtype' => '1', 'from' => 'Lakers', 'to' => 'Heat', 'approval' => 'Heat', 'created_at' => '', 'updated_at' => ''],
            ]);
        $mockRepo->method('getPlayerById')
            ->willReturn(['name' => 'Test Player', 'pos' => 'PG']);
        $mockRepo->expects($this->once())->method('getAllTeamsWithCity')
            ->willReturn([]);

        $service = new TradingService($mockRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $this->assertTrue($result['tradeOffers'][1]['hasHammer']);
        $this->assertFalse($result['tradeOffers'][2]['hasHammer']);
    }

    public function testGetTradeReviewPageDataBuildsTeamListExcludingFreeAgents(): void
    {
        $mockRepo = $this->createMock(TradingRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createMock(\Services\CommonMysqliRepository::class);

        $mockCommon->expects($this->atLeastOnce())->method('getTeamnameFromUsername')
            ->willReturn('Lakers');
        $mockCommon->expects($this->atLeastOnce())->method('getTidFromTeamname')
            ->willReturn(1);
        $mockRepo->expects($this->once())->method('getAllTradeOffers')
            ->willReturn([]);
        $mockRepo->expects($this->once())->method('getAllTeamsWithCity')
            ->willReturn([
                ['teamid' => 1, 'team_name' => 'Lakers', 'team_city' => 'Los Angeles', 'color1' => '552583', 'color2' => 'FDB927'],
                ['teamid' => 0, 'team_name' => 'Free Agents', 'team_city' => '', 'color1' => '333333', 'color2' => 'FFFFFF'],
            ]);

        $service = new TradingService($mockRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $this->assertCount(1, $result['teams']);
        $this->assertSame('Lakers', $result['teams'][0]['name']);
    }

    // ============================================
    // CASH RESOLUTION TESTS (per-year lines)
    // ============================================

    public function testCashWithMultipleYearsProducesMultipleItems(): void
    {
        $mockRepo = $this->createStub(TradingRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createStub(\Services\CommonMysqliRepository::class);

        $mockCommon->method('getTeamnameFromUsername')->willReturn('Lakers');
        $mockCommon->method('getTidFromTeamname')->willReturn(1);
        $mockRepo->method('getAllTradeOffers')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 0, 'itemtype' => 'cash', 'from' => 'Lakers', 'to' => 'Celtics', 'approval' => 'Celtics', 'created_at' => '', 'updated_at' => ''],
        ]);
        $mockCashRepo->method('getCashTransactionByOffer')->willReturn([
            'tradeOfferID' => 1, 'sendingTeam' => 'Lakers', 'receivingTeam' => 'Celtics',
            'cy1' => 100, 'cy2' => 200, 'cy3' => 150, 'cy4' => null, 'cy5' => null, 'cy6' => null,
        ]);
        $mockRepo->method('getAllTeamsWithCity')->willReturn([]);

        $service = new TradingService($mockRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $items = $result['tradeOffers'][1]['items'];
        $this->assertCount(3, $items);
        $this->assertSame('cash', $items[0]['type']);
        $this->assertSame('cash', $items[1]['type']);
        $this->assertSame('cash', $items[2]['type']);
    }

    public function testCashZeroAmountYearsAreOmitted(): void
    {
        $mockRepo = $this->createStub(TradingRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createStub(\Services\CommonMysqliRepository::class);

        $mockCommon->method('getTeamnameFromUsername')->willReturn('Lakers');
        $mockCommon->method('getTidFromTeamname')->willReturn(1);
        $mockRepo->method('getAllTradeOffers')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 0, 'itemtype' => 'cash', 'from' => 'Lakers', 'to' => 'Celtics', 'approval' => 'Celtics', 'created_at' => '', 'updated_at' => ''],
        ]);
        $mockCashRepo->method('getCashTransactionByOffer')->willReturn([
            'tradeOfferID' => 1, 'sendingTeam' => 'Lakers', 'receivingTeam' => 'Celtics',
            'cy1' => 0, 'cy2' => 200, 'cy3' => 0, 'cy4' => 0, 'cy5' => 300, 'cy6' => 0,
        ]);
        $mockRepo->method('getAllTeamsWithCity')->willReturn([]);

        $service = new TradingService($mockRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $items = $result['tradeOffers'][1]['items'];
        $this->assertCount(2, $items);
        $this->assertStringContainsString('200', $items[0]['description']);
        $this->assertStringContainsString('300', $items[1]['description']);
    }

    public function testCashYearLabelsAreCorrectlyComputed(): void
    {
        $mockRepo = $this->createStub(TradingRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createStub(\Services\CommonMysqliRepository::class);

        $mockCommon->method('getTeamnameFromUsername')->willReturn('Lakers');
        $mockCommon->method('getTidFromTeamname')->willReturn(1);
        $mockRepo->method('getAllTradeOffers')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 0, 'itemtype' => 'cash', 'from' => 'Lakers', 'to' => 'Celtics', 'approval' => 'Celtics', 'created_at' => '', 'updated_at' => ''],
        ]);
        $mockCashRepo->method('getCashTransactionByOffer')->willReturn([
            'tradeOfferID' => 1, 'sendingTeam' => 'Lakers', 'receivingTeam' => 'Celtics',
            'cy1' => 100, 'cy2' => 200, 'cy3' => 0, 'cy4' => 150, 'cy5' => null, 'cy6' => null,
        ]);
        $mockRepo->method('getAllTeamsWithCity')->willReturn([]);

        $service = new TradingService($mockRepo, $mockCommon, $this->mockDb, $mockCashRepo);
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

        // cy2 label is 1 year after cy1, cy4 label is 3 years after cy1
        $this->assertSame((int) $m1[1] + 1, (int) $m2[1]);
        $this->assertSame((int) $m1[1] + 3, (int) $m3[1]);
    }

    public function testCashAllZeroProducesNoItems(): void
    {
        $mockRepo = $this->createStub(TradingRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createStub(\Services\CommonMysqliRepository::class);

        $mockCommon->method('getTeamnameFromUsername')->willReturn('Lakers');
        $mockCommon->method('getTidFromTeamname')->willReturn(1);
        $mockRepo->method('getAllTradeOffers')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 0, 'itemtype' => 'cash', 'from' => 'Lakers', 'to' => 'Celtics', 'approval' => 'Celtics', 'created_at' => '', 'updated_at' => ''],
        ]);
        $mockCashRepo->method('getCashTransactionByOffer')->willReturn([
            'tradeOfferID' => 1, 'sendingTeam' => 'Lakers', 'receivingTeam' => 'Celtics',
            'cy1' => 0, 'cy2' => 0, 'cy3' => 0, 'cy4' => 0, 'cy5' => 0, 'cy6' => 0,
        ]);
        $mockRepo->method('getAllTeamsWithCity')->willReturn([]);

        $service = new TradingService($mockRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $items = $result['tradeOffers'][1]['items'];
        $this->assertCount(0, $items);
    }

    public function testCashNullDetailsProducesNoItems(): void
    {
        $mockRepo = $this->createStub(TradingRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createStub(\Services\CommonMysqliRepository::class);

        $mockCommon->method('getTeamnameFromUsername')->willReturn('Lakers');
        $mockCommon->method('getTidFromTeamname')->willReturn(1);
        $mockRepo->method('getAllTradeOffers')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 0, 'itemtype' => 'cash', 'from' => 'Lakers', 'to' => 'Celtics', 'approval' => 'Celtics', 'created_at' => '', 'updated_at' => ''],
        ]);
        $mockCashRepo->method('getCashTransactionByOffer')->willReturn(null);
        $mockRepo->method('getAllTeamsWithCity')->willReturn([]);

        $service = new TradingService($mockRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $items = $result['tradeOffers'][1]['items'];
        $this->assertCount(0, $items);
    }

    public function testCashDescriptionIncludesTeamNames(): void
    {
        $mockRepo = $this->createStub(TradingRepositoryInterface::class);
        $mockCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $mockCommon = $this->createStub(\Services\CommonMysqliRepository::class);

        $mockCommon->method('getTeamnameFromUsername')->willReturn('Lakers');
        $mockCommon->method('getTidFromTeamname')->willReturn(1);
        $mockRepo->method('getAllTradeOffers')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 0, 'itemtype' => 'cash', 'from' => 'Lakers', 'to' => 'Celtics', 'approval' => 'Celtics', 'created_at' => '', 'updated_at' => ''],
        ]);
        $mockCashRepo->method('getCashTransactionByOffer')->willReturn([
            'tradeOfferID' => 1, 'sendingTeam' => 'Lakers', 'receivingTeam' => 'Celtics',
            'cy1' => 500, 'cy2' => null, 'cy3' => null, 'cy4' => null, 'cy5' => null, 'cy6' => null,
        ]);
        $mockRepo->method('getAllTeamsWithCity')->willReturn([]);

        $service = new TradingService($mockRepo, $mockCommon, $this->mockDb, $mockCashRepo);
        $result = $service->getTradeReviewPageData('testuser');

        $items = $result['tradeOffers'][1]['items'];
        $this->assertCount(1, $items);
        $this->assertStringContainsString('The Lakers send 500 in cash to the Celtics', $items[0]['description']);
        $this->assertSame('Lakers', $items[0]['from']);
        $this->assertSame('Celtics', $items[0]['to']);
    }

    // ============================================
    // HELPERS
    // ============================================

    private function createServiceWithStubs(): TradingService
    {
        $stubRepo = $this->createStub(TradingRepositoryInterface::class);
        $stubCashRepo = $this->createStub(TradeCashRepositoryInterface::class);
        $stubCommon = $this->createStub(\Services\CommonMysqliRepository::class);

        return new TradingService($stubRepo, $stubCommon, $this->mockDb, $stubCashRepo);
    }

    private function createSeasonStub(string $phase): \Season
    {
        $season = $this->createStub(\Season::class);
        $season->phase = $phase;
        $season->endingYear = 2025;
        $season->beginningYear = 2024;
        return $season;
    }

    private function createPlayerRow(
        int $cy = 1,
        int $cy1 = 0,
        int $cy2 = 0,
        int $cy3 = 0,
        int $cy4 = 0,
        int $cy5 = 0,
        int $cy6 = 0
    ): array {
        return [
            'pos' => 'PG',
            'name' => 'Test Player',
            'pid' => 1,
            'ordinal' => 5,
            'cy' => $cy,
            'cy1' => $cy1,
            'cy2' => $cy2,
            'cy3' => $cy3,
            'cy4' => $cy4,
            'cy5' => $cy5,
            'cy6' => $cy6,
        ];
    }
}
