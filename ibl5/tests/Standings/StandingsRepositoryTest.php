<?php

declare(strict_types=1);

namespace Tests\Standings;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Standings\StandingsRepository;
use Standings\Contracts\StandingsRepositoryInterface;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * StandingsRepositoryTest - Tests for StandingsRepository data access
 *
 * @covers \Standings\StandingsRepository
 */
#[AllowMockObjectsWithoutExpectations]
class StandingsRepositoryTest extends TestCase
{
    public function testGetStandingsByRegionThrowsExceptionForInvalidRegion(): void
    {
        $mockDb = $this->createMockDatabase();
        $repository = new StandingsRepository($mockDb);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Invalid region: InvalidRegion');

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

    /**
     * Literal copy of the conference names (independent oracle).
     *
     * @return array<string, array{string}>
     */
    public static function conferenceNameProvider(): array
    {
        return [
            'Eastern' => ['Eastern'],
            'Western' => ['Western'],
        ];
    }

    /**
     * Literal copy of the division names (independent oracle).
     *
     * @return array<string, array{string}>
     */
    public static function divisionNameProvider(): array
    {
        return [
            'Atlantic' => ['Atlantic'],
            'Central' => ['Central'],
            'Midwest' => ['Midwest'],
            'Pacific' => ['Pacific'],
        ];
    }

    /**
     * Raw prepared SQL text (backticks intact) for the region query.
     */
    private function preparedRegionSql(string $region): string
    {
        $mockDb = new MockDatabase();
        $repository = new StandingsRepository($mockDb);

        $repository->getStandingsByRegion($region);

        $prepared = $mockDb->getPreparedQueries();
        $this->assertCount(1, $prepared);

        return $prepared[0];
    }

    #[DataProvider('conferenceNameProvider')]
    public function testGetStandingsByRegionUsesConferenceColumnsForEachConference(string $conference): void
    {
        $sql = $this->preparedRegionSql($conference);

        $this->assertStringContainsString('WHERE s.`conference` = ?', $sql);
        $this->assertStringContainsString('s.`conf_gb` AS gamesBack', $sql);
        $this->assertStringContainsString('s.`conf_magic_number` AS magicNumber', $sql);
        $this->assertStringContainsString('ORDER BY s.`conf_gb` ASC', $sql);
        $this->assertStringNotContainsString('`division`', $sql);
        $this->assertStringNotContainsString('div_gb', $sql);
        $this->assertStringNotContainsString('div_magic_number', $sql);
    }

    #[DataProvider('divisionNameProvider')]
    public function testGetStandingsByRegionUsesDivisionColumnsForEachDivision(string $division): void
    {
        $sql = $this->preparedRegionSql($division);

        $this->assertStringContainsString('WHERE s.`division` = ?', $sql);
        $this->assertStringContainsString('s.`div_gb` AS gamesBack', $sql);
        $this->assertStringContainsString('s.`div_magic_number` AS magicNumber', $sql);
        $this->assertStringContainsString('ORDER BY s.`div_gb` ASC', $sql);
        $this->assertStringNotContainsString('`conference`', $sql);
        $this->assertStringNotContainsString('conf_gb', $sql);
        $this->assertStringNotContainsString('conf_magic_number', $sql);
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

        $result = $repository->getTeamPythagoreanStats(999, 2025);

        $this->assertNull($result);
    }

    public function testGetTeamPythagoreanStatsReturnsArrayWhenFound(): void
    {
        // Single JOIN query returns combined offense+defense columns
        $joinedData = [
            'off_fgm' => 1000, 'off_ftm' => 500, 'off_tgm' => 300,
            'def_fgm' => 900, 'def_ftm' => 450, 'def_tgm' => 250,
        ];

        // Expected points: offense = 2*1000 + 500 + 300 = 2800
        // Expected points allowed: defense = 2*900 + 450 + 250 = 2500

        $mockResult = $this->createMock(\mysqli_result::class);
        $mockResult->method('fetch_assoc')
            ->willReturn($joinedData);

        $mockStmt = $this->createMock(\mysqli_stmt::class);
        $mockStmt->method('bind_param')->willReturn(true);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($mockResult);
        $mockStmt->method('close')->willReturn(true);

        $mockDb = $this->createMock(\mysqli::class);
        $mockDb->method('prepare')->willReturn($mockStmt);

        $repository = new StandingsRepository($mockDb);
        $result = $repository->getTeamPythagoreanStats(1, 2025);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('pointsScored', $result);
        $this->assertArrayHasKey('pointsAllowed', $result);
        $this->assertSame(2800, $result['pointsScored']);
        $this->assertSame(2500, $result['pointsAllowed']);
    }

    public function testGetSeriesRecordsReturnsRows(): void
    {
        $expectedData = [
            ['self' => 1, 'opponent' => 2, 'wins' => 3, 'losses' => 2],
            ['self' => 2, 'opponent' => 1, 'wins' => 2, 'losses' => 3],
        ];

        $mockDb = $this->createMockDatabaseWithPreparedStatement($expectedData);
        $repository = new StandingsRepository($mockDb);

        $result = $repository->getSeriesRecords();

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['self']);
        $this->assertSame(2, $result[0]['opponent']);
    }

    public function testGetSeriesRecordsReturnsEmptyWhenNoRecords(): void
    {
        $mockDb = $this->createMockDatabaseWithPreparedStatement([]);
        $repository = new StandingsRepository($mockDb);

        $result = $repository->getSeriesRecords();

        $this->assertSame([], $result);
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
     * @param array<string, mixed>|list<array<string, mixed>>|null $returnData Data to return from the query
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
            // array_values() forces an int-keyed list so the unpacked spread passes
            // positional args (not named) to willReturnOnConsecutiveCalls().
            $mockResult->method('fetch_assoc')->willReturnOnConsecutiveCalls(...array_values(array_merge($returnData, [null])));
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
