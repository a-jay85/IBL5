<?php

declare(strict_types=1);

namespace Tests\RecordHolders;

use PHPUnit\Framework\TestCase;
use RecordHolders\NullAnnouncementDispatcher;
use RecordHolders\RecordBreakingDetector;
use RecordHolders\Contracts\AnnouncementDispatcherInterface;
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
        $this->mockRepository = self::createStub(RecordHoldersRepositoryInterface::class);
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
            'teamid' => 5,
            'team_name' => 'Magic',
            'date' => '2007-02-21',
            'box_id' => 0,
            'game_of_that_day' => 0,
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
            'teamid' => 1,
            'team_name' => 'Celtics',
            'date' => '2007-01-15',
            'box_id' => 0,
            'game_of_that_day' => 0,
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
            'teamid' => 1,
            'team_name' => 'Celtics',
            'date' => '2000-01-01',
            'box_id' => 0,
            'game_of_that_day' => 0,
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

    // --- Canonical stat-definition characterization ---

    /**
     * Characterization (single-source guard): the exact player stat-expression
     * map and the per-game-type date filters this detector hands to the
     * repository. Captures live arguments so the assertion is byte-identical to
     * what the SQL layer receives — the values the RecordStatDefinitions
     * refactor must preserve.
     */
    public function testProducesCanonicalPlayerExpressionsAndDateFilters(): void
    {
        /** @var list<array<string, string>> $playerExpressionCalls */
        $playerExpressionCalls = [];
        /** @var list<string> $playerDateFilters */
        $playerDateFilters = [];

        $this->mockRepository->method('getTopPlayerSingleGameBatch')
            ->willReturnCallback(
                function (array $expressions, string $dateFilter) use (&$playerExpressionCalls, &$playerDateFilters): array {
                    $playerExpressionCalls[] = $expressions;
                    $playerDateFilters[] = $dateFilter;
                    return $this->buildPlayerBatchResult([]);
                }
            );
        $this->mockRepository->method('getTopTeamSingleGameBatch')->willReturn($this->buildTeamBatchResult([]));
        $this->mockRepository->method('getQuadrupleDoubles')->willReturn([]);

        // One date per game type: regular season (Jan), playoffs (Jun), HEAT (Oct).
        $this->detector->detectAndAnnounce(['2007-01-15', '2007-06-15', '2006-10-10']);

        $expectedExpressions = [
            'points' => 'bs.calc_points',
            'rebounds' => 'bs.calc_rebounds',
            'assists' => 'bs.game_ast',
            'steals' => 'bs.game_stl',
            'blocks' => 'bs.game_blk',
            'turnovers' => 'bs.game_tov',
            'fg_made' => 'bs.calc_fg_made',
            'ft_made' => 'bs.game_ftm',
            '3pt_made' => 'bs.game_3gm',
        ];

        $this->assertCount(3, $playerExpressionCalls);
        foreach ($playerExpressionCalls as $expressions) {
            $this->assertSame($expectedExpressions, $expressions);
        }

        $uniqueFilters = array_values(array_unique($playerDateFilters));
        sort($uniqueFilters);
        $this->assertSame(['bs.game_type = 1', 'bs.game_type = 2', 'bs.game_type = 3'], $uniqueFilters);
    }

    /**
     * Characterization (single-source guard): the exact team batch config
     * (8 DESC stats + ASC "team_fewest_points") this detector hands to the
     * repository, including sort order per stat.
     */
    public function testProducesCanonicalTeamBatchConfig(): void
    {
        /** @var list<array<string, array{expression: string, order: string}>> $teamConfigs */
        $teamConfigs = [];

        $this->mockRepository->method('getTopTeamSingleGameBatch')
            ->willReturnCallback(
                function (array $config, string $dateFilter) use (&$teamConfigs): array {
                    $teamConfigs[] = $config;
                    return $this->buildTeamBatchResult([]);
                }
            );
        $this->mockRepository->method('getTopPlayerSingleGameBatch')->willReturn($this->buildPlayerBatchResult([]));
        $this->mockRepository->method('getQuadrupleDoubles')->willReturn([]);

        $this->detector->detectAndAnnounce(['2007-01-15']);

        $expectedConfig = [
            'team_points' => ['expression' => 'bs.calc_points', 'order' => 'DESC'],
            'team_rebounds' => ['expression' => 'bs.calc_rebounds', 'order' => 'DESC'],
            'team_assists' => ['expression' => 'bs.game_ast', 'order' => 'DESC'],
            'team_steals' => ['expression' => 'bs.game_stl', 'order' => 'DESC'],
            'team_blocks' => ['expression' => 'bs.game_blk', 'order' => 'DESC'],
            'team_fg_made' => ['expression' => 'bs.calc_fg_made', 'order' => 'DESC'],
            'team_ft_made' => ['expression' => 'bs.game_ftm', 'order' => 'DESC'],
            'team_3pt_made' => ['expression' => 'bs.game_3gm', 'order' => 'DESC'],
            'team_fewest_points' => ['expression' => 'bs.calc_points', 'order' => 'ASC'],
        ];

        $this->assertCount(1, $teamConfigs);
        $this->assertSame($expectedConfig, $teamConfigs[0]);
    }

    // --- Pre-impl contract pin (characterization) ---

    /**
     * Pins the exact announcement-list contract detectAndAnnounce() returns for a
     * broken player record — count, header, player name, value, previous holder.
     * Runs green on master before any production edit; the refactor must keep it green.
     */
    public function testDetectAndAnnounceReturnsExactAnnouncementContract(): void
    {
        $newRecord = $this->makePlayerRecord(['name' => 'New Star', 'date' => '2007-01-15', 'value' => 85]);
        $previousRecord = $this->makePlayerRecord(['name' => 'Bob Pettit', 'date' => '1996-01-16', 'value' => 80]);

        // Populate only 'points' so exactly one announcement is produced.
        $batchResult = $this->buildPlayerBatchResult([]);
        $batchResult['points'] = [$newRecord, $previousRecord];

        $this->mockRepository->method('getTopPlayerSingleGameBatch')->willReturn($batchResult);
        $this->mockRepository->method('getTopTeamSingleGameBatch')->willReturn($this->buildTeamBatchResult([]));
        $this->mockRepository->method('getQuadrupleDoubles')->willReturn([]);

        $result = $this->detector->detectAndAnnounce(['2007-01-15']);

        $expectedMessage = "**NEW IBL RECORD!**\n"
            . '[New Star](https://iblhoops.net/modules.php?name=Player&pa=showpage&pid=100) (Heat) '
            . 'just recorded **85 points** in a regular season game, '
            . "breaking Bob Pettit's all-time record of 80 points!";

        $this->assertCount(1, $result);
        $this->assertSame($expectedMessage, $result[0]);
    }

    // --- Post-impl dispatch integration tests ---

    /**
     * Matrix row 2: each returned announcement is dispatched exactly once, in order.
     */
    public function testDispatchesEachAnnouncementExactlyOnce(): void
    {
        $newRecord = $this->makePlayerRecord(['name' => 'New Star', 'date' => '2007-01-15', 'value' => 85]);
        $previousRecord = $this->makePlayerRecord(['name' => 'Bob Pettit', 'date' => '1996-01-16', 'value' => 80]);

        $batchResult = $this->buildPlayerBatchResult([]);
        $batchResult['points'] = [$newRecord, $previousRecord];

        $this->mockRepository->method('getTopPlayerSingleGameBatch')->willReturn($batchResult);
        $this->mockRepository->method('getTopTeamSingleGameBatch')->willReturn($this->buildTeamBatchResult([]));
        $this->mockRepository->method('getQuadrupleDoubles')->willReturn([]);

        $spy = new class implements AnnouncementDispatcherInterface {
            /** @var list<string> */
            public array $captured = [];

            public function dispatch(string $message): void
            {
                $this->captured[] = $message;
            }
        };

        $detector = new RecordBreakingDetector($this->mockRepository, $spy);
        $result = $detector->detectAndAnnounce(['2007-01-15']);

        $this->assertNotEmpty($result);
        $this->assertSame($result, $spy->captured, 'Each announcement must be dispatched exactly once, in order');
    }

    /**
     * Matrix row 3: NullDispatcher dispatches nothing, yet detectAndAnnounce()
     * still returns the full announcement list — detection is unaffected by no-op dispatch.
     */
    public function testNullDispatcherDispatchesNothingButDetectionStillReturnsFullResult(): void
    {
        $newRecord = $this->makePlayerRecord(['name' => 'New Star', 'date' => '2007-01-15', 'value' => 85]);
        $previousRecord = $this->makePlayerRecord(['name' => 'Bob Pettit', 'date' => '1996-01-16', 'value' => 80]);

        $batchResult = $this->buildPlayerBatchResult([]);
        $batchResult['points'] = [$newRecord, $previousRecord];

        $this->mockRepository->method('getTopPlayerSingleGameBatch')->willReturn($batchResult);
        $this->mockRepository->method('getTopTeamSingleGameBatch')->willReturn($this->buildTeamBatchResult([]));
        $this->mockRepository->method('getQuadrupleDoubles')->willReturn([]);

        $detector = new RecordBreakingDetector($this->mockRepository, new NullAnnouncementDispatcher());
        $result = $detector->detectAndAnnounce(['2007-01-15']);

        $expectedMessage = "**NEW IBL RECORD!**\n"
            . '[New Star](https://iblhoops.net/modules.php?name=Player&pa=showpage&pid=100) (Heat) '
            . 'just recorded **85 points** in a regular season game, '
            . "breaking Bob Pettit's all-time record of 80 points!";

        $this->assertCount(1, $result, 'Detection must still return full results with NullDispatcher');
        $this->assertSame($expectedMessage, $result[0]);
    }

    /**
     * Matrix row 4: a dispatcher that throws on the first announcement does NOT abort
     * the rest — later announcements are still dispatched and no exception propagates.
     */
    public function testFailingDispatchDoesNotAbortRemainingAnnouncements(): void
    {
        // Two separate announcements: a broken player record and a broken team record.
        $playerRecord = $this->makePlayerRecord(['name' => 'New Star', 'date' => '2007-01-15', 'value' => 85]);
        $prevPlayerRecord = $this->makePlayerRecord(['name' => 'Bob Pettit', 'date' => '1996-01-16', 'value' => 80]);
        $teamRecord = $this->makeTeamRecord(['team_name' => 'Heat', 'date' => '2007-01-15', 'value' => 150]);
        $prevTeamRecord = $this->makeTeamRecord(['team_name' => 'Bulls', 'date' => '2000-03-10', 'value' => 145]);

        $playerBatch = $this->buildPlayerBatchResult([]);
        $playerBatch['points'] = [$playerRecord, $prevPlayerRecord];

        $teamBatch = $this->buildTeamBatchResult([]);
        $teamBatch['team_points'] = [$teamRecord, $prevTeamRecord];

        $this->mockRepository->method('getTopPlayerSingleGameBatch')->willReturn($playerBatch);
        $this->mockRepository->method('getTopTeamSingleGameBatch')->willReturn($teamBatch);
        $this->mockRepository->method('getQuadrupleDoubles')->willReturn([]);

        // Throws on first dispatch, records every subsequent message.
        $throwingDispatcher = new class implements AnnouncementDispatcherInterface {
            /** @var list<string> */
            public array $capturedAfterFirst = [];
            private bool $isFirst = true;

            public function dispatch(string $message): void
            {
                if ($this->isFirst) {
                    $this->isFirst = false;
                    throw new \RuntimeException('Simulated dispatch failure on first message');
                }
                $this->capturedAfterFirst[] = $message;
            }
        };

        $detector = new RecordBreakingDetector($this->mockRepository, $throwingDispatcher);
        $result = $detector->detectAndAnnounce(['2007-01-15']);

        $this->assertCount(2, $result, 'detectAndAnnounce must return all announcements regardless of dispatch failures');
        $this->assertCount(1, $throwingDispatcher->capturedAfterFirst, 'Announcements after a failed dispatch must still be dispatched');
        $this->assertSame($result[1], $throwingDispatcher->capturedAfterFirst[0], 'Second announcement must be dispatched after the first one failed');
    }

    // --- Helper methods ---

    /**
     * Create a player record with sensible defaults.
     *
     * @param array<string, int|string> $overrides
     * @return array{pid: int, name: string, teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}
     */
    private function makePlayerRecord(array $overrides = []): array
    {
        /** @var array{pid: int, name: string, teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int} */
        return array_merge([
            'pid' => 100,
            'name' => 'Test Player',
            'teamid' => 2,
            'team_name' => 'Heat',
            'date' => '2007-01-15',
            'box_id' => 0,
            'game_of_that_day' => 0,
            'oppTid' => 3,
            'opp_team_name' => 'Knicks',
            'value' => 50,
        ], $overrides);
    }

    /**
     * Create a team record with sensible defaults.
     *
     * @param array<string, int|string> $overrides
     * @return array{teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}
     */
    private function makeTeamRecord(array $overrides = []): array
    {
        /** @var array{teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int} */
        return array_merge([
            'teamid' => 2,
            'team_name' => 'Heat',
            'date' => '2007-01-15',
            'box_id' => 0,
            'game_of_that_day' => 0,
            'oppTid' => 3,
            'opp_team_name' => 'Knicks',
            'value' => 100,
        ], $overrides);
    }

    /**
     * Build a player batch result keyed by all PLAYER_STAT_KEYS.
     *
     * @param list<array{pid: int, name: string, teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}> $records
     * @return array<string, list<array{pid: int, name: string, teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}>>
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
     * @param list<array{teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}> $records
     * @return array<string, list<array{teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}>>
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
