<?php

declare(strict_types=1);

namespace Tests\Standings;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Standings\StandingsRepository;
use Standings\Contracts\StandingsRepositoryInterface;

/**
 * StandingsRepositoryTest - Tests for StandingsRepository data access
 *
 * @covers \Standings\StandingsRepository
 */
#[AllowMockObjectsWithoutExpectations]
class StandingsRepositoryTest extends TestCase
{
    public function testImplementsStandingsRepositoryInterface(): void
    {
        $mockDb = $this->createMockDatabase();
        $repository = new StandingsRepository($mockDb);

        $this->assertInstanceOf(StandingsRepositoryInterface::class, $repository);
    }

    public function testGetStandingsByRegionThrowsExceptionForInvalidRegion(): void
    {
        $mockDb = $this->createMockDatabase();
        $repository = new StandingsRepository($mockDb);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid region: InvalidRegion');

        $repository->getStandingsByRegion('InvalidRegion');
    }

    public function testGetStandingsByRegionAcceptsValidConference(): void
    {
        $mockDb = $this->createMockDatabaseWithPreparedStatement([]);
        $repository = new StandingsRepository($mockDb);

        $result = $repository->getStandingsByRegion('Eastern');

        $this->assertIsArray($result);
    }

    public function testGetStandingsByRegionAcceptsValidDivision(): void
    {
        $mockDb = $this->createMockDatabaseWithPreparedStatement([]);
        $repository = new StandingsRepository($mockDb);

        $result = $repository->getStandingsByRegion('Atlantic');

        $this->assertIsArray($result);
    }

    public function testGetTeamStreakDataReturnsNullWhenNotFound(): void
    {
        $mockDb = $this->createMockDatabaseWithPreparedStatement(null);
        $repository = new StandingsRepository($mockDb);

        $result = $repository->getTeamStreakData(999);

        $this->assertNull($result);
    }

    public function testGetTeamStreakDataReturnsArrayWhenFound(): void
    {
        $expectedData = [
            'last_win' => 7,
            'last_loss' => 3,
            'streak_type' => 'W',
            'streak' => 4,
        ];

        $mockDb = $this->createMockDatabaseWithPreparedStatement($expectedData);
        $repository = new StandingsRepository($mockDb);

        $result = $repository->getTeamStreakData(1);

        $this->assertEquals($expectedData, $result);
    }

    public function testGetTeamPythagoreanStatsReturnsNullWhenOffenseStatsNotFound(): void
    {
        $mockDb = $this->createMockDatabaseWithPreparedStatement(null);
        $repository = new StandingsRepository($mockDb);

        $result = $repository->getTeamPythagoreanStats(999);

        $this->assertNull($result);
    }

    public function testGetTeamPythagoreanStatsReturnsArrayWhenFound(): void
    {
        $offenseData = ['fgm' => 1000, 'ftm' => 500, 'tgm' => 300];
        $defenseData = ['fgm' => 900, 'ftm' => 450, 'tgm' => 250];

        // Expected points: offense = 2*1000 + 500 + 300 = 2800
        // Expected points allowed: defense = 2*900 + 450 + 250 = 2500

        $mockResult = $this->createMock(\mysqli_result::class);
        $mockResult->method('fetch_assoc')
            ->willReturnOnConsecutiveCalls($offenseData, $defenseData);

        $mockStmt = $this->createMock(\mysqli_stmt::class);
        $mockStmt->method('bind_param')->willReturn(true);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($mockResult);
        $mockStmt->method('close')->willReturn(true);

        $mockDb = $this->createMock(\mysqli::class);
        $mockDb->method('prepare')->willReturn($mockStmt);

        $repository = new StandingsRepository($mockDb);
        $result = $repository->getTeamPythagoreanStats(1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('pointsScored', $result);
        $this->assertArrayHasKey('pointsAllowed', $result);
        $this->assertEquals(2800, $result['pointsScored']);
        $this->assertEquals(2500, $result['pointsAllowed']);
    }

    /**
     * Create a basic mock database object
     */
    private function createMockDatabase(): object
    {
        $mockDb = $this->createMock(\mysqli::class);
        return $mockDb;
    }

    /**
     * Create a mock database with prepared statement support
     *
     * @param mixed $returnData Data to return from the query
     */
    private function createMockDatabaseWithPreparedStatement($returnData): object
    {
        $mockResult = $this->createMock(\mysqli_result::class);

        if ($returnData === null) {
            $mockResult->method('fetch_assoc')->willReturn(null);
            $mockResult->method('fetch_all')->willReturn([]);
        } elseif (is_array($returnData) && !isset($returnData[0])) {
            // Single row result
            $mockResult->method('fetch_assoc')->willReturn($returnData);
            $mockResult->method('fetch_all')->willReturn([$returnData]);
        } else {
            // Multiple rows result
            $mockResult->method('fetch_assoc')->willReturnOnConsecutiveCalls(...array_merge($returnData, [null]));
            $mockResult->method('fetch_all')->willReturn($returnData);
        }

        $mockStmt = $this->createMock(\mysqli_stmt::class);
        $mockStmt->method('bind_param')->willReturn(true);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($mockResult);
        $mockStmt->method('close')->willReturn(true);

        $mockDb = $this->createMock(\mysqli::class);
        $mockDb->method('prepare')->willReturn($mockStmt);

        return $mockDb;
    }
}
