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
            'gameOfThatDay' => 0,
            'oppTid' => 20,
            'opp_team_name' => 'Grizzlies',
            'value' => 80,
        ];

        // Batch method returns all stat categories at once
        $batchResult = $this->buildBatchPlayerResult([$playerRecord]);
        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($batchResult);
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
            'gameOfThatDay' => 0,
            'oppTid' => 11,
            'opp_team_name' => 'Pacers',
            'value' => 65,
        ];

        $batchResult = $this->buildBatchPlayerResult([$heatRecord]);
        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($batchResult);
        $this->configureOtherMocksEmpty();

        $result = $this->service->getAllRecords();

        $heatRecords = $result['playerSingleGame']['heat'];
        $firstCategory = array_values($heatRecords)[0];
        $this->assertSame("HEAT\nOctober 12, 1994", $firstCategory[0]['dateDisplay']);
    }

    public function testDetectsTiesInRecords(): void
    {
        $record1 = $this->createPlayerRecord(1, 'Player A', 34);
        $record2 = $this->createPlayerRecord(2, 'Player B', 34);
        $record3 = $this->createPlayerRecord(3, 'Player C', 33);

        $batchResult = $this->buildBatchPlayerResult([$record1, $record2, $record3]);
        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($batchResult);
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

        $batchResult = $this->buildBatchPlayerResult([$record]);
        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($batchResult);
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

        $batchResult = $this->buildBatchSeasonResult([$seasonRecord]);
        $this->mockRepository->method('getTopSeasonAverageBatch')
            ->willReturn($batchResult);
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
            'gameOfThatDay' => 0,
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

        $batchResult = $this->buildBatchPlayerResult([$record]);
        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($batchResult);
        $this->configureOtherMocksEmpty();

        $result = $this->service->getAllRecords();

        $regSeason = $result['playerSingleGame']['regularSeason'];
        $firstCategory = array_values($regSeason)[0];
        $this->assertSame('bos', $firstCategory[0]['teamAbbr']);
    }

    public function testBoxScoreUrlGeneratedWhenGameOfThatDayAvailable(): void
    {
        $record = $this->createPlayerRecord(927, 'Bob Pettit', 80);
        $record['gameOfThatDay'] = 3;

        $batchResult = $this->buildBatchPlayerResult([$record]);
        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($batchResult);
        $this->configureOtherMocksEmpty();

        $result = $this->service->getAllRecords();

        $regSeason = $result['playerSingleGame']['regularSeason'];
        $firstCategory = array_values($regSeason)[0];
        $this->assertStringContainsString('1996-01-16-game-3/boxscore', $firstCategory[0]['boxScoreUrl']);
    }

    public function testBoxScoreUrlFallsBackToLegacyWhenNoGameOfThatDay(): void
    {
        $record = $this->createPlayerRecord(927, 'Bob Pettit', 80);
        $record['BoxID'] = 1731;

        $batchResult = $this->buildBatchPlayerResult([$record]);
        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($batchResult);
        $this->configureOtherMocksEmpty();

        $result = $this->service->getAllRecords();

        $regSeason = $result['playerSingleGame']['regularSeason'];
        $firstCategory = array_values($regSeason)[0];
        $this->assertSame('./ibl/IBL/box1731.htm', $firstCategory[0]['boxScoreUrl']);
    }

    public function testBoxScoreUrlEmptyWhenNeitherAvailable(): void
    {
        $record = $this->createPlayerRecord(927, 'Bob Pettit', 80);

        $batchResult = $this->buildBatchPlayerResult([$record]);
        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($batchResult);
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

    public function testTeamSeasonRecordsIncludeTeamTidAndTeamYr(): void
    {
        $seasonRecord = [
            'team_name' => 'Bulls',
            'year' => 1993,
            'wins' => 71,
            'losses' => 11,
        ];

        $this->mockRepository->method('getBestWorstSeasonRecord')
            ->willReturn([$seasonRecord]);
        $this->mockRepository->method('getBestWorstSeasonStart')->willReturn([]);
        $this->mockRepository->method('getLongestStreak')->willReturn([]);
        $this->configureNonSeasonMocksEmpty();

        $result = $this->service->getAllRecords();

        $bestRecord = $result['teamSeasonRecords']['Best Season Record'];
        $this->assertCount(1, $bestRecord);
        $this->assertSame(7, $bestRecord[0]['teamTid']);
        $this->assertSame('1993', $bestRecord[0]['teamYr']);
        $this->assertSame('chi', $bestRecord[0]['teamAbbr']);
        $this->assertSame('1992-93', $bestRecord[0]['season']);
    }

    public function testFranchiseRecordsIncludeTeamTid(): void
    {
        $franchiseRecord = [
            'team_name' => 'Nets',
            'count' => 7,
            'years' => '1989, 1990, 1991',
        ];

        $this->mockRepository->method('getMostPlayoffAppearances')
            ->willReturn([$franchiseRecord]);
        $this->mockRepository->method('getMostTitlesByType')->willReturn([]);
        $this->configureNonFranchiseMocksEmpty();

        $result = $this->service->getAllRecords();

        $playoffKey = array_key_first($result['teamFranchise']);
        $this->assertNotNull($playoffKey);
        $playoffRecords = $result['teamFranchise'][$playoffKey];
        $this->assertCount(1, $playoffRecords);
        $this->assertSame(4, $playoffRecords[0]['teamTid']);
        $this->assertSame('bkn', $playoffRecords[0]['teamAbbr']);
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
     * @return array{pid: int, name: string, tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int}
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
            'gameOfThatDay' => 0,
            'oppTid' => 20,
            'opp_team_name' => 'Grizzlies',
            'value' => $value,
        ];
    }

    /**
     * Build a batch player result where every stat category has the same records.
     *
     * @param list<array{pid: int, name: string, tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int}> $records
     * @return array<string, list<array{pid: int, name: string, tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int}>>
     */
    private function buildBatchPlayerResult(array $records): array
    {
        $categories = [
            'Most Points in a Single Game',
            'Most Rebounds in a Single Game',
            'Most Assists in a Single Game',
            'Most Steals in a Single Game',
            'Most Blocks in a Single Game',
            'Most Turnovers in a Single Game',
            'Most Field Goals in a Single Game',
            'Most Free Throws in a Single Game',
            'Most Three Pointers in a Single Game',
        ];

        $result = [];
        foreach ($categories as $category) {
            $result[$category] = $records;
        }
        return $result;
    }

    /**
     * Build a batch season result where every stat category has the same records.
     *
     * @param list<array{pid: int, name: string, teamid: int, team: string, year: int, value: float}> $records
     * @return array<string, list<array{pid: int, name: string, teamid: int, team: string, year: int, value: float}>>
     */
    private function buildBatchSeasonResult(array $records): array
    {
        $categories = [
            'Highest Scoring Average in a Regular Season',
            'Highest Rebounding Average in a Regular Season',
            'Highest Assist Average in a Regular Season',
            'Highest Steals Average in a Regular Season',
            'Highest Blocks Average in a Regular Season',
        ];

        $result = [];
        foreach ($categories as $category) {
            $result[$category] = $records;
        }
        return $result;
    }

    /**
     * Build a batch team result where every stat category has the same records.
     *
     * @param list<array{tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int}> $records
     * @return array<string, list<array{tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int}>>
     */
    private function buildBatchTeamResult(array $records): array
    {
        $categories = [
            'Most Points in a Single Game',
            'Most Rebounds in a Single Game',
            'Most Assists in a Single Game',
            'Most Steals in a Single Game',
            'Most Blocks in a Single Game',
            'Most Field Goals in a Single Game',
            'Most Free Throws in a Single Game',
            'Most Three Pointers in a Single Game',
            'Fewest Points in a Single Game',
        ];

        $result = [];
        foreach ($categories as $category) {
            $result[$category] = $records;
        }
        return $result;
    }

    /**
     * Configure all repository mocks to return empty arrays.
     */
    private function configureEmptyMocks(): void
    {
        $this->mockRepository->method('getTopPlayerSingleGameBatch')->willReturn($this->buildBatchPlayerResult([]));
        $this->mockRepository->method('getTopSeasonAverageBatch')->willReturn($this->buildBatchSeasonResult([]));
        $this->mockRepository->method('getTopTeamSingleGameBatch')->willReturn($this->buildBatchTeamResult([]));
        $this->mockRepository->method('getQuadrupleDoubles')->willReturn([]);
        $this->mockRepository->method('getMostAllStarAppearances')->willReturn([]);
        $this->mockRepository->method('getTopTeamHalfScore')->willReturn([]);
        $this->mockRepository->method('getLargestMarginOfVictory')->willReturn([]);
        $this->mockRepository->method('getBestWorstSeasonRecord')->willReturn([]);
        $this->mockRepository->method('getLongestStreak')->willReturn([]);
        $this->mockRepository->method('getBestWorstSeasonStart')->willReturn([]);
        $this->mockRepository->method('getMostPlayoffAppearances')->willReturn([]);
        $this->mockRepository->method('getMostTitlesByType')->willReturn([]);
    }

    /**
     * Configure all mocks except getTopPlayerSingleGameBatch to return empty.
     */
    private function configureOtherMocksEmpty(): void
    {
        $this->mockRepository->method('getTopSeasonAverageBatch')->willReturn($this->buildBatchSeasonResult([]));
        $this->mockRepository->method('getTopTeamSingleGameBatch')->willReturn($this->buildBatchTeamResult([]));
        $this->mockRepository->method('getQuadrupleDoubles')->willReturn([]);
        $this->mockRepository->method('getMostAllStarAppearances')->willReturn([]);
        $this->mockRepository->method('getTopTeamHalfScore')->willReturn([]);
        $this->mockRepository->method('getLargestMarginOfVictory')->willReturn([]);
        $this->mockRepository->method('getBestWorstSeasonRecord')->willReturn([]);
        $this->mockRepository->method('getLongestStreak')->willReturn([]);
        $this->mockRepository->method('getBestWorstSeasonStart')->willReturn([]);
        $this->mockRepository->method('getMostPlayoffAppearances')->willReturn([]);
        $this->mockRepository->method('getMostTitlesByType')->willReturn([]);
    }

    /**
     * Configure all mocks except team season record mocks to return empty.
     */
    private function configureNonSeasonMocksEmpty(): void
    {
        $this->mockRepository->method('getTopPlayerSingleGameBatch')->willReturn($this->buildBatchPlayerResult([]));
        $this->mockRepository->method('getTopSeasonAverageBatch')->willReturn($this->buildBatchSeasonResult([]));
        $this->mockRepository->method('getTopTeamSingleGameBatch')->willReturn($this->buildBatchTeamResult([]));
        $this->mockRepository->method('getQuadrupleDoubles')->willReturn([]);
        $this->mockRepository->method('getMostAllStarAppearances')->willReturn([]);
        $this->mockRepository->method('getTopTeamHalfScore')->willReturn([]);
        $this->mockRepository->method('getLargestMarginOfVictory')->willReturn([]);
        $this->mockRepository->method('getMostPlayoffAppearances')->willReturn([]);
        $this->mockRepository->method('getMostTitlesByType')->willReturn([]);
    }

    /**
     * Configure all mocks except franchise record mocks to return empty.
     */
    private function configureNonFranchiseMocksEmpty(): void
    {
        $this->mockRepository->method('getTopPlayerSingleGameBatch')->willReturn($this->buildBatchPlayerResult([]));
        $this->mockRepository->method('getTopSeasonAverageBatch')->willReturn($this->buildBatchSeasonResult([]));
        $this->mockRepository->method('getTopTeamSingleGameBatch')->willReturn($this->buildBatchTeamResult([]));
        $this->mockRepository->method('getQuadrupleDoubles')->willReturn([]);
        $this->mockRepository->method('getMostAllStarAppearances')->willReturn([]);
        $this->mockRepository->method('getTopTeamHalfScore')->willReturn([]);
        $this->mockRepository->method('getLargestMarginOfVictory')->willReturn([]);
        $this->mockRepository->method('getBestWorstSeasonRecord')->willReturn([]);
        $this->mockRepository->method('getLongestStreak')->willReturn([]);
        $this->mockRepository->method('getBestWorstSeasonStart')->willReturn([]);
    }
}
