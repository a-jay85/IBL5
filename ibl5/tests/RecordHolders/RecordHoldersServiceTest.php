<?php

declare(strict_types=1);

namespace Tests\RecordHolders;

use PHPUnit\Framework\TestCase;
use RecordHolders\RecordHoldersService;
use RecordHolders\Contracts\RecordHoldersRepositoryInterface;

final class RecordHoldersServiceTest extends TestCase
{
    /** @var RecordHoldersRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private RecordHoldersRepositoryInterface $mockRepository;
    private RecordHoldersService $service;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createStub(RecordHoldersRepositoryInterface::class);
        $this->service = new RecordHoldersService($this->mockRepository);
    }

    public function testGetAllRecordsReturnsExpectedStructure(): void
    {
        $this->configureEmptyMocks();

        $result = $this->service->getAllRecords();

        $this->assertArrayHasKey('playerSingleGame', $result);
        $this->assertArrayHasKey('regularSeason', $result['playerSingleGame']);
        $this->assertArrayHasKey('playoffs', $result['playerSingleGame']);
        $this->assertArrayHasKey('heat', $result['playerSingleGame']);
        $this->assertArrayHasKey('quadrupleDoubles', $result);
        $this->assertArrayHasKey('allStarRecord', $result);
        $this->assertArrayHasKey('playerFullSeason', $result);
        $this->assertArrayHasKey('teamGameRecords', $result);
        $this->assertArrayHasKey('teamSeasonRecords', $result);
        $this->assertArrayHasKey('teamFranchise', $result);
    }

    public function testPlayerSingleGameRecordsContainsAllStatCategories(): void
    {
        $this->configureEmptyMocks();

        $result = $this->service->getAllRecords();

        $regSeason = $result['playerSingleGame']['regularSeason'];
        // All 9 stat categories should be present
        $this->assertCount(9, $regSeason);
    }

    public function testFormatsPlayerRecordCorrectly(): void
    {
        $playerRecord = [
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
            ->willReturn([$playerRecord]);
        $this->configureOtherMocksEmpty();

        $result = $this->service->getAllRecords();

        $regSeason = $result['playerSingleGame']['regularSeason'];
        $firstCategory = array_values($regSeason)[0];
        $this->assertCount(1, $firstCategory);

        $record = $firstCategory[0];
        $this->assertSame(927, $record['pid']);
        $this->assertSame('Bob Pettit', $record['name']);
        $this->assertSame('min', $record['teamAbbr']);
        $this->assertSame(14, $record['teamTid']);
        $this->assertSame('1996', $record['teamYr']);
        $this->assertSame('January 16, 1996', $record['dateDisplay']);
        $this->assertSame('van', $record['oppAbbr']);
        $this->assertSame(20, $record['oppTid']);
        $this->assertSame('80', $record['amount']);
    }

    public function testFormatsHeatDateCorrectly(): void
    {
        $heatRecord = [
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
            ->willReturn([$heatRecord]);
        $this->configureOtherMocksEmpty();

        $result = $this->service->getAllRecords();

        $heatRecords = $result['playerSingleGame']['heat'];
        $firstCategory = array_values($heatRecords)[0];
        $this->assertSame('1994 HEAT', $firstCategory[0]['dateDisplay']);
    }

    public function testDetectsTiesInRecords(): void
    {
        $record1 = $this->createPlayerRecord(1, 'Player A', 34);
        $record2 = $this->createPlayerRecord(2, 'Player B', 34);
        $record3 = $this->createPlayerRecord(3, 'Player C', 33);

        $this->mockRepository->method('getTopPlayerSingleGame')
            ->willReturn([$record1, $record2, $record3]);
        $this->configureOtherMocksEmpty();

        $result = $this->service->getAllRecords();

        $regSeason = $result['playerSingleGame']['regularSeason'];
        // First category should have a [tie] label
        $firstKey = array_key_first($regSeason);
        $this->assertNotFalse($firstKey);
        $this->assertStringContainsString('[tie]', $firstKey);
        $this->assertCount(2, $regSeason[$firstKey]);
    }

    public function testSingleRecordDoesNotGetTieLabel(): void
    {
        $record = $this->createPlayerRecord(1, 'Player A', 80);

        $this->mockRepository->method('getTopPlayerSingleGame')
            ->willReturn([$record]);
        $this->configureOtherMocksEmpty();

        $result = $this->service->getAllRecords();

        $regSeason = $result['playerSingleGame']['regularSeason'];
        $firstKey = array_key_first($regSeason);
        $this->assertNotFalse($firstKey);
        $this->assertStringNotContainsString('[tie]', $firstKey);
    }

    public function testFormatsSeasonYearRangeCorrectly(): void
    {
        $seasonRecord = [
            'pid' => 304,
            'name' => 'Mitch Richmond',
            'teamid' => 2,
            'team' => 'Heat',
            'year' => 1994,
            'value' => 34.2,
        ];

        $this->mockRepository->method('getTopSeasonAverage')
            ->willReturn([$seasonRecord]);
        $this->configureOtherMocksEmpty();

        $result = $this->service->getAllRecords();

        $fullSeason = $result['playerFullSeason'];
        $firstCategory = array_values($fullSeason)[0];
        $this->assertSame('1993-94', $firstCategory[0]['season']);
    }

    public function testFormatsQuadrupleDoublesMultiLineAmount(): void
    {
        $qdRecord = [
            'pid' => 1481,
            'name' => 'Lenny Wilkens',
            'tid' => 2,
            'team_name' => 'Heat',
            'date' => '1992-12-12',
            'BoxID' => 0,
            'oppTid' => 25,
            'opp_team_name' => 'Pistons',
            'points' => 12,
            'rebounds' => 10,
            'assists' => 14,
            'steals' => 10,
            'blocks' => 3,
        ];

        $this->mockRepository->method('getQuadrupleDoubles')
            ->willReturn([$qdRecord]);
        $this->configureOtherMocksEmpty();

        $result = $this->service->getAllRecords();

        $qd = $result['quadrupleDoubles'];
        $this->assertCount(1, $qd);
        $this->assertStringContainsString("12pts\n10rbs\n14ast\n10stl", $qd[0]['amount']);
        // Blocks < 10, so not included
        $this->assertStringNotContainsString('blk', $qd[0]['amount']);
    }

    public function testAllStarRecordFormatsCorrectly(): void
    {
        $allStarRecord = [
            'name' => 'Mitch Richmond',
            'pid' => 304,
            'appearances' => 10,
        ];

        $this->mockRepository->method('getMostAllStarAppearances')
            ->willReturn([$allStarRecord]);
        $this->configureOtherMocksEmpty();

        $result = $this->service->getAllRecords();

        $allStar = $result['allStarRecord'];
        $this->assertSame('Mitch Richmond', $allStar['name']);
        $this->assertSame(304, $allStar['pid']);
        $this->assertSame(10, $allStar['amount']);
    }

    public function testEmptyAllStarRecordHandledGracefully(): void
    {
        $this->mockRepository->method('getMostAllStarAppearances')
            ->willReturn([]);
        $this->configureOtherMocksEmpty();

        $result = $this->service->getAllRecords();

        $allStar = $result['allStarRecord'];
        $this->assertSame('', $allStar['name']);
        $this->assertNull($allStar['pid']);
        $this->assertSame(0, $allStar['amount']);
    }

    public function testTeamAbbreviationMapping(): void
    {
        $record = $this->createPlayerRecord(927, 'Bob Pettit', 80, 1);

        $this->mockRepository->method('getTopPlayerSingleGame')
            ->willReturn([$record]);
        $this->configureOtherMocksEmpty();

        $result = $this->service->getAllRecords();

        $regSeason = $result['playerSingleGame']['regularSeason'];
        $firstCategory = array_values($regSeason)[0];
        $this->assertSame('bos', $firstCategory[0]['teamAbbr']);
    }

    public function testBoxScoreUrlGeneratedWhenBoxIdAvailable(): void
    {
        $record = $this->createPlayerRecord(927, 'Bob Pettit', 80);
        $record['BoxID'] = 1731;

        $this->mockRepository->method('getTopPlayerSingleGame')
            ->willReturn([$record]);
        $this->configureOtherMocksEmpty();

        $result = $this->service->getAllRecords();

        $regSeason = $result['playerSingleGame']['regularSeason'];
        $firstCategory = array_values($regSeason)[0];
        $this->assertSame('modules.php?name=Scores&pa=boxscore&boxid=1731', $firstCategory[0]['boxScoreUrl']);
    }

    public function testBoxScoreUrlEmptyWhenNoBoxId(): void
    {
        $record = $this->createPlayerRecord(927, 'Bob Pettit', 80);

        $this->mockRepository->method('getTopPlayerSingleGame')
            ->willReturn([$record]);
        $this->configureOtherMocksEmpty();

        $result = $this->service->getAllRecords();

        $regSeason = $result['playerSingleGame']['regularSeason'];
        $firstCategory = array_values($regSeason)[0];
        $this->assertSame('', $firstCategory[0]['boxScoreUrl']);
    }

    public function testTeamSeasonRecordsContainsExpectedCategories(): void
    {
        $this->configureEmptyMocks();

        $result = $this->service->getAllRecords();

        $seasonRecords = $result['teamSeasonRecords'];
        $this->assertArrayHasKey('Best Season Record', $seasonRecords);
        $this->assertArrayHasKey('Worst Season Record', $seasonRecords);
        $this->assertArrayHasKey('Longest Winning Streak', $seasonRecords);
        $this->assertArrayHasKey('Longest Losing Streak', $seasonRecords);
    }

    public function testTeamFranchiseRecordsContainsExpectedCategories(): void
    {
        $this->configureEmptyMocks();

        $result = $this->service->getAllRecords();

        $franchise = $result['teamFranchise'];
        $this->assertArrayHasKey('Most Playoff Appearances', $franchise);
        $this->assertArrayHasKey('Most Division Championships', $franchise);
        $this->assertArrayHasKey('Most IBL Finals Appearances', $franchise);
        $this->assertArrayHasKey('Most IBL Championships', $franchise);
    }

    /**
     * Helper to create a player record with customizable values.
     *
     * @return array{pid: int, name: string, tid: int, team_name: string, date: string, BoxID: int, oppTid: int, opp_team_name: string, value: int}
     */
    private function createPlayerRecord(int $pid, string $name, int $value, int $tid = 14): array
    {
        return [
            'pid' => $pid,
            'name' => $name,
            'tid' => $tid,
            'team_name' => 'Test Team',
            'date' => '1996-01-16',
            'BoxID' => 0,
            'oppTid' => 20,
            'opp_team_name' => 'Grizzlies',
            'value' => $value,
        ];
    }

    /**
     * Configure all repository mocks to return empty arrays.
     */
    private function configureEmptyMocks(): void
    {
        $this->mockRepository->method('getTopPlayerSingleGame')->willReturn([]);
        $this->mockRepository->method('getTopSeasonAverage')->willReturn([]);
        $this->mockRepository->method('getQuadrupleDoubles')->willReturn([]);
        $this->mockRepository->method('getMostAllStarAppearances')->willReturn([]);
        $this->mockRepository->method('getTopTeamSingleGame')->willReturn([]);
        $this->mockRepository->method('getTopTeamHalfScore')->willReturn([]);
        $this->mockRepository->method('getLargestMarginOfVictory')->willReturn([]);
        $this->mockRepository->method('getBestWorstSeasonRecord')->willReturn([]);
        $this->mockRepository->method('getLongestStreak')->willReturn([]);
        $this->mockRepository->method('getBestWorstSeasonStart')->willReturn([]);
        $this->mockRepository->method('getMostPlayoffAppearances')->willReturn([]);
        $this->mockRepository->method('getMostTitlesByType')->willReturn([]);
    }

    /**
     * Configure all mocks except getTopPlayerSingleGame to return empty.
     */
    private function configureOtherMocksEmpty(): void
    {
        $this->mockRepository->method('getTopSeasonAverage')->willReturn([]);
        $this->mockRepository->method('getQuadrupleDoubles')->willReturn([]);
        $this->mockRepository->method('getMostAllStarAppearances')->willReturn([]);
        $this->mockRepository->method('getTopTeamSingleGame')->willReturn([]);
        $this->mockRepository->method('getTopTeamHalfScore')->willReturn([]);
        $this->mockRepository->method('getLargestMarginOfVictory')->willReturn([]);
        $this->mockRepository->method('getBestWorstSeasonRecord')->willReturn([]);
        $this->mockRepository->method('getLongestStreak')->willReturn([]);
        $this->mockRepository->method('getBestWorstSeasonStart')->willReturn([]);
        $this->mockRepository->method('getMostPlayoffAppearances')->willReturn([]);
        $this->mockRepository->method('getMostTitlesByType')->willReturn([]);
    }
}
