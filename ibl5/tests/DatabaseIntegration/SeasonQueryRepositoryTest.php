<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Season\SeasonQueryRepository;

/**
 * Tests SeasonQueryRepository against real MariaDB — settings reads, sim dates, phase calculations.
 */
class SeasonQueryRepositoryTest extends DatabaseTestCase
{
    private SeasonQueryRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SeasonQueryRepository($this->db);
    }

    public function testGetSeasonPhaseReturnsString(): void
    {
        $phase = $this->repo->getSeasonPhase();

        self::assertIsString($phase);
        self::assertNotEmpty($phase);
    }

    public function testGetSeasonEndingYearReturnsString(): void
    {
        $year = $this->repo->getSeasonEndingYear();

        self::assertIsString($year);
        self::assertNotEmpty($year);
    }

    public function testGetBulkSettingsReturnsMappedValues(): void
    {
        // Insert a custom setting within the transaction
        $this->insertRow('ibl_settings', [
            'name' => 'DB_IntTest_Setting',
            'value' => 'test_value_42',
        ]);

        $map = $this->repo->getBulkSettings(['DB_IntTest_Setting', 'Current Season Phase']);

        self::assertArrayHasKey('DB_IntTest_Setting', $map);
        self::assertSame('test_value_42', $map['DB_IntTest_Setting']);
        self::assertArrayHasKey('Current Season Phase', $map);
    }

    public function testGetBulkSettingsReturnsEmptyForUnknown(): void
    {
        $map = $this->repo->getBulkSettings(['Completely_Nonexistent_Setting_XYZ']);

        self::assertSame([], $map);
    }

    public function testGetAllowTradesStatusReturnsString(): void
    {
        $status = $this->repo->getAllowTradesStatus();

        self::assertIsString($status);
    }

    public function testGetAllowWaiversStatusReturnsString(): void
    {
        $status = $this->repo->getAllowWaiversStatus();

        self::assertIsString($status);
    }

    public function testGetLastSimDatesArrayReturnsShape(): void
    {
        $dates = $this->repo->getLastSimDatesArray();

        self::assertArrayHasKey('Sim', $dates);
        self::assertArrayHasKey('Start Date', $dates);
        self::assertArrayHasKey('End Date', $dates);
    }

    public function testSetLastSimDatesArrayInsertsRow(): void
    {
        $affected = $this->repo->setLastSimDatesArray('99999', '2099-01-01', '2099-01-07');

        self::assertSame(1, $affected);

        // Verify via direct query
        $stmt = $this->db->prepare("SELECT `Start Date`, `End Date` FROM ibl_sim_dates WHERE Sim = 99999");
        self::assertNotFalse($stmt);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame('2099-01-01', $row['Start Date']);
        self::assertSame('2099-01-07', $row['End Date']);
    }

    public function testGetLastRegularSeasonGameDateReturnsStringOrNull(): void
    {
        // Production schedule data exists, so MAX(Date) WHERE Date < playoffs_start returns a date
        // For a real season year, this should return a date string
        $year = (int) $this->repo->getSeasonEndingYear();
        $date = $this->repo->getLastRegularSeasonGameDate($year);

        if ($date !== null) {
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date);
        } else {
            self::assertNull($date);
        }
    }

    public function testGetFirstAndLastBoxScoreDateReturnStrings(): void
    {
        $first = $this->repo->getFirstBoxScoreDate();
        $last = $this->repo->getLastBoxScoreDate();

        // Production DB should have box score data
        self::assertIsString($first);
        self::assertIsString($last);
    }

    public function testCalculatePhaseSimNumberReturnsInt(): void
    {
        // Insert sim dates within regular season date range for a test season
        // Regular season: Nov (year-1) to May (year)
        // For season ending 2099: Nov 2098 to May 2099
        $this->insertRow('ibl_sim_dates', [
            'Start Date' => '2098-11-01',
            'End Date' => '2098-11-07',
        ]);
        $this->insertRow('ibl_sim_dates', [
            'Start Date' => '2098-11-08',
            'End Date' => '2098-11-14',
        ]);

        // Use a high sim number to include both
        $phaseSimNumber = $this->repo->calculatePhaseSimNumber(99999, 'Regular Season', 2099);

        self::assertIsInt($phaseSimNumber);
        self::assertGreaterThanOrEqual(2, $phaseSimNumber);
    }
}
