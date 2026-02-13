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

    /**
     * Player stat keys used by RecordBreakingDetector::PLAYER_STATS.
     *
     * @var list<string>
     */
    private const PLAYER_STAT_KEYS = [
        'points', 'rebounds', 'assists', 'steals',
        'blocks', 'turnovers', 'fg_made', 'ft_made', '3pt_made',
    ];

    /**
     * Team stat keys used by RecordBreakingDetector::TEAM_STATS.
     *
     * @var list<string>
     */
    private const TEAM_STAT_KEYS = [
        'team_points', 'team_rebounds', 'team_assists', 'team_steals',
        'team_blocks', 'team_fg_made', 'team_ft_made', 'team_3pt_made',
        'team_fewest_points',
    ];

    protected function setUp(): void
    {
        $this->mockRepository = $this->createStub(RecordHoldersRepositoryInterface::class);
        $this->detector = new RecordBreakingDetector($this->mockRepository);
    }

    // --- Player single-game record tests ---

    public function testDetectsNewPlayerRecordWhenTopEntryIsFromTargetDate(): void
    {
        $newRecord = $this->makePlayerRecord(['name' => 'New Star', 'date' => '2007-01-15', 'value' => 85]);
        $previousRecord = $this->makePlayerRecord(['name' => 'Bob Pettit', 'date' => '1996-01-16', 'value' => 80]);

        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($this->buildPlayerBatchResult([$newRecord, $previousRecord]));

        $result = $this->detector->detectAndAnnounce(['2007-01-15']);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('NEW IBL RECORD', $result[0]);
        $this->assertStringContainsString('New Star', $result[0]);
        $this->assertStringContainsString('85', $result[0]);
        $this->assertStringContainsString('Bob Pettit', $result[0]);
    }

    public function testNoDetectionWhenPlayerRecordNotBroken(): void
    {
        $existingRecord = $this->makePlayerRecord(['name' => 'Bob Pettit', 'date' => '1996-01-16', 'value' => 80]);
        $newEntry = $this->makePlayerRecord(['name' => 'New Player', 'date' => '2007-01-15', 'value' => 60]);

        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($this->buildPlayerBatchResult([$existingRecord, $newEntry]));

        $result = $this->detector->detectAndAnnounce(['2007-01-15']);

        $this->assertEmpty($result);
    }

    public function testNoDetectionWhenNoRecordsExist(): void
    {
        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($this->buildPlayerBatchResult([]));

        $result = $this->detector->detectAndAnnounce(['2007-01-15']);

        $this->assertEmpty($result);
    }

    public function testDetectsPlayoffPlayerRecord(): void
    {
        $newRecord = $this->makePlayerRecord(['name' => 'Playoff Star', 'date' => '2007-06-15', 'value' => 70]);
        $previousRecord = $this->makePlayerRecord(['name' => 'Michael Jordan', 'date' => '2003-06-21', 'value' => 65]);

        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($this->buildPlayerBatchResult([$newRecord, $previousRecord]));

        $result = $this->detector->detectAndAnnounce(['2007-06-15']);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('playoff', $result[0]);
    }

    public function testDetectsHeatPlayerRecord(): void
    {
        $newRecord = $this->makePlayerRecord(['name' => 'HEAT Star', 'date' => '2006-10-10', 'value' => 70]);
        $previousRecord = $this->makePlayerRecord(['name' => 'Tony Dumas', 'date' => '1994-10-12', 'value' => 65]);

        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($this->buildPlayerBatchResult([$newRecord, $previousRecord]));

        $result = $this->detector->detectAndAnnounce(['2006-10-10']);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('HEAT', $result[0]);
    }

    public function testDetectsTiedPlayerRecord(): void
    {
        $tiedRecord = $this->makePlayerRecord(['name' => 'New Star', 'date' => '2007-01-15', 'value' => 80]);
        $previousRecord = $this->makePlayerRecord(['name' => 'Bob Pettit', 'date' => '1996-01-16', 'value' => 80]);

        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($this->buildPlayerBatchResult([$previousRecord, $tiedRecord]));

        $result = $this->detector->detectAndAnnounce(['2007-01-15']);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('IBL RECORD TIED', $result[0]);
        $this->assertStringContainsString('tying', $result[0]);
        $this->assertStringContainsString('New Star', $result[0]);
    }

    public function testBrokenPlayerRecordDoesNotSayTied(): void
    {
        $newRecord = $this->makePlayerRecord(['name' => 'New Star', 'date' => '2007-01-15', 'value' => 85]);
        $previousRecord = $this->makePlayerRecord(['name' => 'Bob Pettit', 'date' => '1996-01-16', 'value' => 80]);

        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($this->buildPlayerBatchResult([$newRecord, $previousRecord]));

        $result = $this->detector->detectAndAnnounce(['2007-01-15']);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('breaking', $result[0]);
        $this->assertStringNotContainsString('tying', $result[0]);
        $this->assertStringNotContainsString('TIED', $result[0]);
    }

    public function testPlayerMessageIncludesTeamName(): void
    {
        $newRecord = $this->makePlayerRecord(['name' => 'New Star', 'team_name' => 'Heat', 'date' => '2007-01-15', 'value' => 85]);
        $previousRecord = $this->makePlayerRecord(['name' => 'Bob Pettit', 'date' => '1996-01-16', 'value' => 80]);

        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($this->buildPlayerBatchResult([$newRecord, $previousRecord]));

        $result = $this->detector->detectAndAnnounce(['2007-01-15']);

        $this->assertStringContainsString('Heat', $result[0]);
    }

    // --- Multi-date detection tests ---

    public function testDetectsRecordFromEarlierDateInBatch(): void
    {
        $tiedRecord = $this->makePlayerRecord(['name' => 'Stephen Curry', 'date' => '2007-02-26', 'value' => 11]);
        $existingRecord = $this->makePlayerRecord(['name' => 'Stephen Curry', 'date' => '2005-01-06', 'value' => 11]);

        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($this->buildPlayerBatchResult([$existingRecord, $tiedRecord]));

        // Sim batch spans multiple dates; the record is on 2007-02-26, not the latest date
        $result = $this->detector->detectAndAnnounce(['2007-02-18', '2007-02-26', '2007-03-02']);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('IBL RECORD TIED', $result[0]);
        $this->assertStringContainsString('Stephen Curry', $result[0]);
    }

    public function testEmptyDatesReturnsEmpty(): void
    {
        $result = $this->detector->detectAndAnnounce([]);

        $this->assertSame([], $result);
    }

    // --- Team single-game record tests ---

    public function testDetectsNewTeamRecord(): void
    {
        $newRecord = $this->makeTeamRecord(['team_name' => 'Heat', 'date' => '2007-01-15', 'value' => 150]);
        $previousRecord = $this->makeTeamRecord(['team_name' => 'Bulls', 'date' => '2000-03-10', 'value' => 145]);

        $this->mockRepository->method('getTopTeamSingleGameBatch')
            ->willReturn($this->buildTeamBatchResult([$newRecord, $previousRecord]));

        $result = $this->detector->detectAndAnnounce(['2007-01-15']);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('IBL TEAM RECORD', $result[0]);
        $this->assertStringContainsString('Heat', $result[0]);
        $this->assertStringContainsString('most points', $result[0]);
        $this->assertStringContainsString('Bulls', $result[0]);
    }

    public function testDetectsTiedTeamRecord(): void
    {
        $tiedRecord = $this->makeTeamRecord(['team_name' => 'Heat', 'date' => '2007-01-15', 'value' => 145]);
        $previousRecord = $this->makeTeamRecord(['team_name' => 'Bulls', 'date' => '2000-03-10', 'value' => 145]);

        $this->mockRepository->method('getTopTeamSingleGameBatch')
            ->willReturn($this->buildTeamBatchResult([$previousRecord, $tiedRecord]));

        $result = $this->detector->detectAndAnnounce(['2007-01-15']);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('TEAM RECORD TIED', $result[0]);
        $this->assertStringContainsString('tying', $result[0]);
    }

    public function testDetectsFewestPointsTeamRecord(): void
    {
        // For ASC records, lower value is better
        $newRecord = $this->makeTeamRecord(['team_name' => 'Knicks', 'date' => '2007-01-15', 'value' => 50]);
        $previousRecord = $this->makeTeamRecord(['team_name' => 'Pacers', 'date' => '1999-02-10', 'value' => 55]);

        // Only populate team_fewest_points; others empty
        $batchResult = $this->buildTeamBatchResult([]);
        $batchResult['team_fewest_points'] = [$newRecord, $previousRecord];

        $this->mockRepository->method('getTopTeamSingleGameBatch')
            ->willReturn($batchResult);

        $result = $this->detector->detectAndAnnounce(['2007-01-15']);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('NEW IBL TEAM RECORD', $result[0]);
        $this->assertStringContainsString('fewest points', $result[0]);
        $this->assertStringContainsString('Knicks', $result[0]);
    }

    // --- Quadruple double tests ---

    public function testDetectsNewQuadrupleDouble(): void
    {
        $qd = [
            'pid' => 3282,
            'name' => 'Brandon Tomyoy',
            'tid' => 5,
            'team_name' => 'Magic',
            'date' => '2007-02-21',
            'BoxID' => 0,
            'gameOfThatDay' => 0,
            'oppTid' => 3,
            'opp_team_name' => 'Knicks',
            'points' => 18,
            'rebounds' => 10,
            'assists' => 10,
            'steals' => 10,
            'blocks' => 1,
        ];

        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($this->buildPlayerBatchResult([]));
        $this->mockRepository->method('getQuadrupleDoubles')
            ->willReturn([$qd]);

        $result = $this->detector->detectAndAnnounce(['2007-02-21']);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('QUADRUPLE DOUBLE', $result[0]);
        $this->assertStringContainsString('Brandon Tomyoy', $result[0]);
        $this->assertStringContainsString('18pts/10reb/10ast/10stl', $result[0]);
    }

    public function testQuadrupleDoubleWithBlocksIncludesBlocks(): void
    {
        $qd = [
            'pid' => 100,
            'name' => 'Test Player',
            'tid' => 1,
            'team_name' => 'Celtics',
            'date' => '2007-01-15',
            'BoxID' => 0,
            'gameOfThatDay' => 0,
            'oppTid' => 2,
            'opp_team_name' => 'Heat',
            'points' => 20,
            'rebounds' => 12,
            'assists' => 11,
            'steals' => 10,
            'blocks' => 10,
        ];

        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($this->buildPlayerBatchResult([]));
        $this->mockRepository->method('getQuadrupleDoubles')
            ->willReturn([$qd]);

        $result = $this->detector->detectAndAnnounce(['2007-01-15']);

        $this->assertStringContainsString('10blk', $result[0]);
    }

    public function testIgnoresQuadrupleDoubleFromNonTargetDate(): void
    {
        $qd = [
            'pid' => 100,
            'name' => 'Old Player',
            'tid' => 1,
            'team_name' => 'Celtics',
            'date' => '2000-01-01',
            'BoxID' => 0,
            'gameOfThatDay' => 0,
            'oppTid' => 2,
            'opp_team_name' => 'Heat',
            'points' => 20,
            'rebounds' => 12,
            'assists' => 11,
            'steals' => 10,
            'blocks' => 1,
        ];

        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturn($this->buildPlayerBatchResult([]));
        $this->mockRepository->method('getQuadrupleDoubles')
            ->willReturn([$qd]);

        $result = $this->detector->detectAndAnnounce(['2007-01-15']);

        $this->assertEmpty($result);
    }

    // --- Helper methods ---

    /**
     * Create a player record with sensible defaults.
     *
     * @param array<string, int|string> $overrides
     * @return array{pid: int, name: string, tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int}
     */
    private function makePlayerRecord(array $overrides = []): array
    {
        /** @var array{pid: int, name: string, tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int} */
        return array_merge([
            'pid' => 100,
            'name' => 'Test Player',
            'tid' => 2,
            'team_name' => 'Heat',
            'date' => '2007-01-15',
            'BoxID' => 0,
            'gameOfThatDay' => 0,
            'oppTid' => 3,
            'opp_team_name' => 'Knicks',
            'value' => 50,
        ], $overrides);
    }

    /**
     * Create a team record with sensible defaults.
     *
     * @param array<string, int|string> $overrides
     * @return array{tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int}
     */
    private function makeTeamRecord(array $overrides = []): array
    {
        /** @var array{tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int} */
        return array_merge([
            'tid' => 2,
            'team_name' => 'Heat',
            'date' => '2007-01-15',
            'BoxID' => 0,
            'gameOfThatDay' => 0,
            'oppTid' => 3,
            'opp_team_name' => 'Knicks',
            'value' => 100,
        ], $overrides);
    }

    /**
     * Build a player batch result keyed by all PLAYER_STAT_KEYS.
     *
     * @param list<array{pid: int, name: string, tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int}> $records
     * @return array<string, list<array{pid: int, name: string, tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int}>>
     */
    private function buildPlayerBatchResult(array $records): array
    {
        $result = [];
        foreach (self::PLAYER_STAT_KEYS as $key) {
            $result[$key] = $records;
        }
        return $result;
    }

    /**
     * Build a team batch result keyed by all TEAM_STAT_KEYS.
     *
     * @param list<array{tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int}> $records
     * @return array<string, list<array{tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int}>>
     */
    private function buildTeamBatchResult(array $records): array
    {
        $result = [];
        foreach (self::TEAM_STAT_KEYS as $key) {
            $result[$key] = $records;
        }
        return $result;
    }
}
