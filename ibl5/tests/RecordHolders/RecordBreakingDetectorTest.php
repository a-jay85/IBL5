<?php

declare(strict_types=1);

namespace Tests\RecordHolders;

use PHPUnit\Framework\TestCase;
use RecordHolders\RecordBreakingDetector;
use RecordHolders\Contracts\RecordHoldersRepositoryInterface;

final class RecordBreakingDetectorTest extends TestCase
{
    /** @var RecordHoldersRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private RecordHoldersRepositoryInterface $mockRepository;
    private RecordBreakingDetector $detector;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createStub(RecordHoldersRepositoryInterface::class);
        $this->detector = new RecordBreakingDetector($this->mockRepository);
    }

    public function testDetectsNewRecordWhenTopEntryIsFromGivenDate(): void
    {
        $newRecord = [
            'pid' => 100,
            'name' => 'New Star',
            'tid' => 2,
            'team_name' => 'Heat',
            'date' => '2007-01-15',
            'BoxID' => 0,
            'oppTid' => 3,
            'opp_team_name' => 'Knicks',
            'value' => 85,
        ];

        $previousRecord = [
            'pid' => 927,
            'name' => 'Bob Pettit',
            'tid' => 14,
            'team_name' => 'Timberwolves',
            'date' => '1996-01-16',
            'BoxID' => 0,
            'oppTid' => 20,
            'opp_team_name' => 'Grizzlies',
            'value' => 80,
        ];

        $this->mockRepository->method('getTopPlayerSingleGame')
            ->willReturn([$newRecord, $previousRecord]);

        $result = $this->detector->detectAndAnnounce('2007-01-15');

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('NEW IBL RECORD', $result[0]);
        $this->assertStringContainsString('New Star', $result[0]);
        $this->assertStringContainsString('85', $result[0]);
        $this->assertStringContainsString('Bob Pettit', $result[0]);
        $this->assertStringContainsString('80', $result[0]);
    }

    public function testNoDetectionWhenRecordNotBroken(): void
    {
        $existingRecord = [
            'pid' => 927,
            'name' => 'Bob Pettit',
            'tid' => 14,
            'team_name' => 'Timberwolves',
            'date' => '1996-01-16',
            'BoxID' => 0,
            'oppTid' => 20,
            'opp_team_name' => 'Grizzlies',
            'value' => 80,
        ];

        $newEntry = [
            'pid' => 100,
            'name' => 'New Player',
            'tid' => 2,
            'team_name' => 'Heat',
            'date' => '2007-01-15',
            'BoxID' => 0,
            'oppTid' => 3,
            'opp_team_name' => 'Knicks',
            'value' => 60,
        ];

        $this->mockRepository->method('getTopPlayerSingleGame')
            ->willReturn([$existingRecord, $newEntry]);

        $result = $this->detector->detectAndAnnounce('2007-01-15');

        $this->assertEmpty($result);
    }

    public function testNoDetectionWhenNoRecordsExist(): void
    {
        $this->mockRepository->method('getTopPlayerSingleGame')
            ->willReturn([]);

        $result = $this->detector->detectAndAnnounce('2007-01-15');

        $this->assertEmpty($result);
    }

    public function testDetectsPlayoffRecordBreaking(): void
    {
        $newRecord = [
            'pid' => 200,
            'name' => 'Playoff Star',
            'tid' => 7,
            'team_name' => 'Bulls',
            'date' => '2007-06-15',
            'BoxID' => 0,
            'oppTid' => 1,
            'opp_team_name' => 'Celtics',
            'value' => 70,
        ];

        $previousRecord = [
            'pid' => 1230,
            'name' => 'Michael Jordan',
            'tid' => 2,
            'team_name' => 'Heat',
            'date' => '2003-06-21',
            'BoxID' => 0,
            'oppTid' => 3,
            'opp_team_name' => 'Knicks',
            'value' => 65,
        ];

        $this->mockRepository->method('getTopPlayerSingleGame')
            ->willReturn([$newRecord, $previousRecord]);

        $result = $this->detector->detectAndAnnounce('2007-06-15');

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('playoff', $result[0]);
    }

    public function testDetectsHeatRecordBreaking(): void
    {
        $newRecord = [
            'pid' => 300,
            'name' => 'HEAT Star',
            'tid' => 5,
            'team_name' => 'Magic',
            'date' => '2006-10-10',
            'BoxID' => 0,
            'oppTid' => 11,
            'opp_team_name' => 'Pacers',
            'value' => 70,
        ];

        $previousRecord = [
            'pid' => 656,
            'name' => 'Tony Dumas',
            'tid' => 5,
            'team_name' => 'Magic',
            'date' => '1994-10-12',
            'BoxID' => 0,
            'oppTid' => 11,
            'opp_team_name' => 'Pacers',
            'value' => 65,
        ];

        $this->mockRepository->method('getTopPlayerSingleGame')
            ->willReturn([$newRecord, $previousRecord]);

        $result = $this->detector->detectAndAnnounce('2006-10-10');

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('HEAT', $result[0]);
    }

    public function testMessageFormatIncludesTeamName(): void
    {
        $newRecord = [
            'pid' => 100,
            'name' => 'New Star',
            'tid' => 2,
            'team_name' => 'Heat',
            'date' => '2007-01-15',
            'BoxID' => 0,
            'oppTid' => 3,
            'opp_team_name' => 'Knicks',
            'value' => 85,
        ];

        $previousRecord = [
            'pid' => 927,
            'name' => 'Bob Pettit',
            'tid' => 14,
            'team_name' => 'Timberwolves',
            'date' => '1996-01-16',
            'BoxID' => 0,
            'oppTid' => 20,
            'opp_team_name' => 'Grizzlies',
            'value' => 80,
        ];

        $this->mockRepository->method('getTopPlayerSingleGame')
            ->willReturn([$newRecord, $previousRecord]);

        $result = $this->detector->detectAndAnnounce('2007-01-15');

        $this->assertStringContainsString('Heat', $result[0]);
    }
}
