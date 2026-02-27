<?php

declare(strict_types=1);

namespace Tests\Season;

use PHPUnit\Framework\TestCase;
use Season\SeasonQueryRepository;

class SeasonQueryRepositoryTest extends TestCase
{
    private \MockDatabase $mockDb;
    private SeasonQueryRepository $repository;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->repository = new SeasonQueryRepository($this->mockDb);
    }

    // ============================================
    // GET SEASON PHASE TESTS
    // ============================================

    public function testGetSeasonPhaseReturnsValue(): void
    {
        $this->mockDb->setMockData([
            ['value' => 'Regular Season'],
        ]);

        $result = $this->repository->getSeasonPhase();

        $this->assertSame('Regular Season', $result);
    }

    public function testGetSeasonPhaseReturnsEmptyStringWhenNoData(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getSeasonPhase();

        $this->assertSame('', $result);
    }

    // ============================================
    // GET SEASON ENDING YEAR TESTS
    // ============================================

    public function testGetSeasonEndingYearReturnsValue(): void
    {
        $this->mockDb->setMockData([
            ['value' => '2025'],
        ]);

        $result = $this->repository->getSeasonEndingYear();

        $this->assertSame('2025', $result);
    }

    public function testGetSeasonEndingYearReturnsEmptyWhenNoData(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getSeasonEndingYear();

        $this->assertSame('', $result);
    }

    // ============================================
    // GET FIRST/LAST BOX SCORE DATE TESTS
    // ============================================

    public function testGetFirstBoxScoreDateReturnsDate(): void
    {
        $this->mockDb->setMockData([
            ['Date' => '2024-11-01'],
        ]);

        $result = $this->repository->getFirstBoxScoreDate();

        $this->assertSame('2024-11-01', $result);
    }

    public function testGetFirstBoxScoreDateReturnsEmptyWhenNoData(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getFirstBoxScoreDate();

        $this->assertSame('', $result);
    }

    public function testGetLastBoxScoreDateReturnsDate(): void
    {
        $this->mockDb->setMockData([
            ['Date' => '2025-05-15'],
        ]);

        $result = $this->repository->getLastBoxScoreDate();

        $this->assertSame('2025-05-15', $result);
    }

    // ============================================
    // GET LAST SIM DATES ARRAY TESTS
    // ============================================

    public function testGetLastSimDatesArrayReturnsData(): void
    {
        $this->mockDb->setMockData([
            ['Sim' => 10, 'Start Date' => '2025-01-01', 'End Date' => '2025-01-07'],
        ]);

        $result = $this->repository->getLastSimDatesArray();

        $this->assertSame(10, $result['Sim']);
        $this->assertSame('2025-01-01', $result['Start Date']);
        $this->assertSame('2025-01-07', $result['End Date']);
    }

    public function testGetLastSimDatesArrayReturnsDefaultsWhenNoData(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getLastSimDatesArray();

        $this->assertSame(0, $result['Sim']);
        $this->assertSame('', $result['Start Date']);
        $this->assertSame('', $result['End Date']);
    }

    // ============================================
    // GET ALLOW TRADES/WAIVERS STATUS TESTS
    // ============================================

    public function testGetAllowTradesStatusReturnsYes(): void
    {
        $this->mockDb->setMockData([
            ['value' => 'Yes'],
        ]);

        $result = $this->repository->getAllowTradesStatus();

        $this->assertSame('Yes', $result);
    }

    public function testGetAllowWaiversStatusReturnsNo(): void
    {
        $this->mockDb->setMockData([
            ['value' => 'No'],
        ]);

        $result = $this->repository->getAllowWaiversStatus();

        $this->assertSame('No', $result);
    }

    // ============================================
    // GET FREE AGENCY NOTIFICATIONS STATE TESTS
    // ============================================

    public function testGetFreeAgencyNotificationsStateReturnsOn(): void
    {
        $this->mockDb->setMockData([
            ['value' => 'On'],
        ]);

        $result = $this->repository->getFreeAgencyNotificationsState();

        $this->assertSame('On', $result);
    }

    // ============================================
    // GET LAST REGULAR SEASON GAME DATE TESTS
    // ============================================

    public function testGetLastRegularSeasonGameDateReturnsDate(): void
    {
        $this->mockDb->setMockData([
            ['max_date' => '2025-05-28'],
        ]);

        $result = $this->repository->getLastRegularSeasonGameDate(2025);

        $this->assertSame('2025-05-28', $result);
    }

    public function testGetLastRegularSeasonGameDateReturnsNullWhenNoSchedule(): void
    {
        $this->mockDb->setMockData([
            ['max_date' => null],
        ]);

        $result = $this->repository->getLastRegularSeasonGameDate(2025);

        $this->assertNull($result);
    }

    // ============================================
    // CALCULATE PHASE SIM NUMBER TESTS
    // ============================================

    public function testCalculatePhaseSimNumberReturnsCount(): void
    {
        $this->mockDb->setMockData([
            ['cnt' => 5],
        ]);

        $result = $this->repository->calculatePhaseSimNumber(10, 'Regular Season', 2025);

        $this->assertSame(5, $result);
    }

    public function testCalculatePhaseSimNumberFallsBackToOverallWhenZero(): void
    {
        $this->mockDb->setMockData([
            ['cnt' => 0],
        ]);

        $result = $this->repository->calculatePhaseSimNumber(10, 'Free Agency', 2025);

        $this->assertSame(10, $result);
    }

    public function testCalculatePhaseSimNumberHandlesPlayoffsPhase(): void
    {
        $this->mockDb->setMockData([
            ['cnt' => 3],
        ]);

        $result = $this->repository->calculatePhaseSimNumber(15, 'Playoffs', 2025);

        $this->assertSame(3, $result);
    }

    public function testCalculatePhaseSimNumberHandlesHeatPhase(): void
    {
        $this->mockDb->setMockData([
            ['cnt' => 2],
        ]);

        $result = $this->repository->calculatePhaseSimNumber(5, 'HEAT', 2025);

        $this->assertSame(2, $result);
    }

    public function testCalculatePhaseSimNumberHandlesPreseasonPhase(): void
    {
        $this->mockDb->setMockData([
            ['cnt' => 4],
        ]);

        $result = $this->repository->calculatePhaseSimNumber(4, 'Preseason', 2025);

        $this->assertSame(4, $result);
    }

    // ============================================
    // GET BULK SETTINGS TESTS
    // ============================================

    public function testGetBulkSettingsReturnsMap(): void
    {
        $this->mockDb->setMockData([
            ['name' => 'Allow Trades', 'value' => 'Yes'],
            ['name' => 'Allow Waiver Moves', 'value' => 'No'],
        ]);

        $result = $this->repository->getBulkSettings(['Allow Trades', 'Allow Waiver Moves']);

        $this->assertSame('Yes', $result['Allow Trades']);
        $this->assertSame('No', $result['Allow Waiver Moves']);
    }

    public function testGetBulkSettingsReturnsEmptyArrayWhenNoMatches(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getBulkSettings(['NonExistent']);

        $this->assertSame([], $result);
    }
}
