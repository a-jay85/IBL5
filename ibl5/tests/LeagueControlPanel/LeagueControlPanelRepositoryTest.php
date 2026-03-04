<?php

declare(strict_types=1);

namespace Tests\LeagueControlPanel;

use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;
use LeagueControlPanel\LeagueControlPanelRepository;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LeagueControlPanel\LeagueControlPanelRepository
 *
 * Note: Write methods (updateSetting, setSeasonPhase, etc.) cannot be unit-tested
 * with mocked mysqli because $stmt->affected_rows is a virtual property inaccessible
 * on PHPUnit mocks. Write behavior is tested through LeagueControlPanelProcessorTest
 * via the interface mock.
 */
class LeagueControlPanelRepositoryTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $mockDb = $this->createMockDatabase();
        $repository = new LeagueControlPanelRepository($mockDb);

        $this->assertInstanceOf(LeagueControlPanelRepositoryInterface::class, $repository);
    }

    public function testGetSettingReturnsValue(): void
    {
        $mockDb = $this->createMockDatabaseWithPreparedStatement(['value' => 'Regular Season']);
        $repository = new LeagueControlPanelRepository($mockDb);

        $result = $repository->getSetting('Current Season Phase');

        $this->assertSame('Regular Season', $result);
    }

    public function testGetSettingReturnsNullForMissing(): void
    {
        $mockDb = $this->createMockDatabaseWithPreparedStatement(null);
        $repository = new LeagueControlPanelRepository($mockDb);

        $result = $repository->getSetting('Nonexistent Setting');

        $this->assertNull($result);
    }

    public function testGetBulkSettingsReturnsMappedArray(): void
    {
        $rows = [
            ['name' => 'Current Season Phase', 'value' => 'Playoffs'],
            ['name' => 'Allow Trades', 'value' => 'Yes'],
        ];

        $mockDb = $this->createMockDatabaseWithPreparedStatement($rows);
        $repository = new LeagueControlPanelRepository($mockDb);

        $result = $repository->getBulkSettings(['Current Season Phase', 'Allow Trades']);

        $this->assertSame([
            'Current Season Phase' => 'Playoffs',
            'Allow Trades' => 'Yes',
        ], $result);
    }

    public function testGetBulkSettingsReturnsEmptyForEmptyInput(): void
    {
        $mockDb = $this->createMockDatabase();
        $repository = new LeagueControlPanelRepository($mockDb);

        $result = $repository->getBulkSettings([]);

        $this->assertSame([], $result);
    }

    public function testGetSimLengthInDaysCastsToInt(): void
    {
        $mockDb = $this->createMockDatabaseWithPreparedStatement(['value' => '5']);
        $repository = new LeagueControlPanelRepository($mockDb);

        $result = $repository->getSimLengthInDays();

        $this->assertSame(5, $result);
    }

    public function testGetSimLengthInDaysDefaultsToThree(): void
    {
        $mockDb = $this->createMockDatabaseWithPreparedStatement(null);
        $repository = new LeagueControlPanelRepository($mockDb);

        $result = $repository->getSimLengthInDays();

        $this->assertSame(3, $result);
    }

    private function createMockDatabase(): \mysqli
    {
        return $this->createStub(\mysqli::class);
    }

    /**
     * @param array<string, mixed>|list<array<string, mixed>>|null $returnData
     */
    private function createMockDatabaseWithPreparedStatement(array|null $returnData): \mysqli
    {
        $mockResult = $this->createStub(\mysqli_result::class);

        if ($returnData === null) {
            $mockResult->method('fetch_assoc')->willReturn(null);
        } elseif ($returnData !== [] && !array_is_list($returnData)) {
            // Single row result
            $mockResult->method('fetch_assoc')->willReturnOnConsecutiveCalls($returnData, null);
        } else {
            // Multiple rows result
            $mockResult->method('fetch_assoc')->willReturnOnConsecutiveCalls(...array_merge($returnData, [null]));
        }

        $stubStmt = $this->createStub(\mysqli_stmt::class);
        $stubStmt->method('bind_param')->willReturn(true);
        $stubStmt->method('execute')->willReturn(true);
        $stubStmt->method('get_result')->willReturn($mockResult);
        $stubStmt->method('close')->willReturn(true);

        $stubDb = $this->createStub(\mysqli::class);
        $stubDb->method('prepare')->willReturn($stubStmt);

        return $stubDb;
    }
}
