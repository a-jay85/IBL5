<?php

declare(strict_types=1);

namespace Tests\SeriesRecords;

use PHPUnit\Framework\TestCase;
use SeriesRecords\SeriesRecordsService;

/**
 * Tests for SeriesRecordsService
 * 
 * @covers \SeriesRecords\SeriesRecordsService
 */
class SeriesRecordsServiceTest extends TestCase
{
    private SeriesRecordsService $service;

    protected function setUp(): void
    {
        $this->service = new SeriesRecordsService();
    }

    // =========================================================================
    // buildSeriesMatrix Tests
    // =========================================================================

    public function testBuildSeriesMatrixReturnsEmptyArrayForEmptyInput(): void
    {
        $result = $this->service->buildSeriesMatrix([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testBuildSeriesMatrixBuildsSingleMatchup(): void
    {
        $records = [
            ['self' => 1, 'opponent' => 2, 'wins' => 10, 'losses' => 5],
        ];

        $result = $this->service->buildSeriesMatrix($records);

        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result[1]);
        $this->assertEquals(['wins' => 10, 'losses' => 5], $result[1][2]);
    }

    public function testBuildSeriesMatrixBuildsMultipleMatchups(): void
    {
        $records = [
            ['self' => 1, 'opponent' => 2, 'wins' => 10, 'losses' => 5],
            ['self' => 1, 'opponent' => 3, 'wins' => 8, 'losses' => 7],
            ['self' => 2, 'opponent' => 1, 'wins' => 5, 'losses' => 10],
            ['self' => 2, 'opponent' => 3, 'wins' => 12, 'losses' => 3],
        ];

        $result = $this->service->buildSeriesMatrix($records);

        $this->assertCount(2, $result);
        $this->assertEquals(['wins' => 10, 'losses' => 5], $result[1][2]);
        $this->assertEquals(['wins' => 8, 'losses' => 7], $result[1][3]);
        $this->assertEquals(['wins' => 5, 'losses' => 10], $result[2][1]);
        $this->assertEquals(['wins' => 12, 'losses' => 3], $result[2][3]);
    }

    public function testBuildSeriesMatrixConvertsTypesToInt(): void
    {
        $records = [
            ['self' => '1', 'opponent' => '2', 'wins' => '10', 'losses' => '5'],
        ];

        $result = $this->service->buildSeriesMatrix($records);

        $this->assertSame(10, $result[1][2]['wins']);
        $this->assertSame(5, $result[1][2]['losses']);
    }

    // =========================================================================
    // getRecordStatus Tests
    // =========================================================================

    public function testGetRecordStatusReturnsWinningWhenWinsGreater(): void
    {
        $this->assertEquals('winning', $this->service->getRecordStatus(10, 5));
        $this->assertEquals('winning', $this->service->getRecordStatus(1, 0));
        $this->assertEquals('winning', $this->service->getRecordStatus(100, 99));
    }

    public function testGetRecordStatusReturnsLosingWhenLossesGreater(): void
    {
        $this->assertEquals('losing', $this->service->getRecordStatus(5, 10));
        $this->assertEquals('losing', $this->service->getRecordStatus(0, 1));
        $this->assertEquals('losing', $this->service->getRecordStatus(99, 100));
    }

    public function testGetRecordStatusReturnsTiedWhenEqual(): void
    {
        $this->assertEquals('tied', $this->service->getRecordStatus(5, 5));
        $this->assertEquals('tied', $this->service->getRecordStatus(0, 0));
        $this->assertEquals('tied', $this->service->getRecordStatus(100, 100));
    }

    // =========================================================================
    // getRecordBackgroundColor Tests
    // =========================================================================

    public function testGetRecordBackgroundColorReturnsGreenForWinning(): void
    {
        $this->assertEquals('#8f8', $this->service->getRecordBackgroundColor(10, 5));
    }

    public function testGetRecordBackgroundColorReturnsRedForLosing(): void
    {
        $this->assertEquals('#f88', $this->service->getRecordBackgroundColor(5, 10));
    }

    public function testGetRecordBackgroundColorReturnsGrayForTied(): void
    {
        $this->assertEquals('#bbb', $this->service->getRecordBackgroundColor(5, 5));
    }

    public function testGetRecordBackgroundColorHandlesZeroZero(): void
    {
        $this->assertEquals('#bbb', $this->service->getRecordBackgroundColor(0, 0));
    }

    // =========================================================================
    // getRecordFromMatrix Tests
    // =========================================================================

    public function testGetRecordFromMatrixReturnsRecordWhenExists(): void
    {
        $matrix = [
            1 => [
                2 => ['wins' => 10, 'losses' => 5],
            ],
        ];

        $result = $this->service->getRecordFromMatrix($matrix, 1, 2);

        $this->assertEquals(['wins' => 10, 'losses' => 5], $result);
    }

    public function testGetRecordFromMatrixReturnsZerosForMissingMatchup(): void
    {
        $matrix = [
            1 => [
                2 => ['wins' => 10, 'losses' => 5],
            ],
        ];

        $result = $this->service->getRecordFromMatrix($matrix, 1, 3);

        $this->assertEquals(['wins' => 0, 'losses' => 0], $result);
    }

    public function testGetRecordFromMatrixReturnsZerosForMissingTeam(): void
    {
        $matrix = [
            1 => [
                2 => ['wins' => 10, 'losses' => 5],
            ],
        ];

        $result = $this->service->getRecordFromMatrix($matrix, 3, 1);

        $this->assertEquals(['wins' => 0, 'losses' => 0], $result);
    }

    public function testGetRecordFromMatrixReturnsZerosForEmptyMatrix(): void
    {
        $result = $this->service->getRecordFromMatrix([], 1, 2);

        $this->assertEquals(['wins' => 0, 'losses' => 0], $result);
    }
}
