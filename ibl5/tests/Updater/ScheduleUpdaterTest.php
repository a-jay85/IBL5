<?php

declare(strict_types=1);

namespace Tests\Updater;

use League\LeagueContext;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use Season\Season;
use Updater\Contracts\JsbSourceResolverInterface;
use JsbParser\SchFileParser;

/**
 * @covers \Updater\ScheduleUpdater
 */
class ScheduleUpdaterTest extends TestCase
{
    private MockDatabase $mockDb;

    /** @var list<string> */
    private array $tempDirs = [];

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['authService']);
        foreach ($this->tempDirs as $dir) {
            if (is_dir($dir)) {
                $this->removeDir($dir);
            }
        }
        $this->tempDirs = [];
    }

    private function removeDir(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function createUpdater(
        string $phase = 'Regular Season',
        int $endingYear = 2025,
        bool $olympics = false,
        ?JsbSourceResolverInterface $sourceResolver = null,
        ?string $basePath = null,
        ?\Psr\Log\LoggerInterface $logger = null,
    ): TestableScheduleUpdater {
        $season = self::createStub(Season::class);
        $season->endingYear = $endingYear;
        $season->beginningYear = $endingYear - 1;
        $season->phase = $phase;

        $leagueContext = self::createStub(LeagueContext::class);
        $leagueContext->method('getCurrentLeague')->willReturn($olympics ? 'olympics' : 'IBL');
        $leagueContext->method('isOlympics')->willReturn($olympics);
        $leagueContext->method('getFilePrefix')->willReturn($olympics ? 'Olympics' : 'IBL5');
        $leagueContext->method('getTableName')->willReturnCallback(
            static fn (string $table): string => $olympics
                ? str_replace('ibl_', 'ibl_olympics_', $table)
                : $table,
        );

        return new TestableScheduleUpdater(
            $this->mockDb,
            $season,
            $leagueContext,
            $sourceResolver,
            $basePath,
            $logger ?? new \Psr\Log\NullLogger(),
        );
    }

    /**
     * Create a temp dir with ibl/IBL/Schedule.htm containing $html. Returns the root path.
     */
    private function makeScheduleHtm(string $html): string
    {
        $root = sys_get_temp_dir() . '/' . uniqid('sch_test_', true);
        mkdir($root . '/ibl/IBL', 0777, true);
        file_put_contents($root . '/ibl/IBL/Schedule.htm', $html);
        $this->tempDirs[] = $root;
        return $root;
    }

    /**
     * Build minimal Schedule.htm markup accepted by ScheduleHtmParser::parsePlayoffGames().
     *
     * @param list<array{visitor: string, home: string, played: bool, box_id?: int}> $games
     */
    private function playoffHtml(int $year, array $games): string
    {
        $rows = "<table>\n<tr><th>Post 1 {$year}</th></tr>\n";
        foreach ($games as $game) {
            $visitorScore = $game['played']
                ? '<a href="box' . ($game['box_id'] ?? 0) . '.htm">101</a>'
                : '';
            $homeScore = $game['played'] ? '95' : '';
            $rows .= "<tr>"
                . "<td><a href=\"team1.htm\">{$game['visitor']}</a></td>"
                . "<td>{$visitorScore}</td>"
                . "<td><a href=\"team2.htm\">{$game['home']}</a></td>"
                . "<td>{$homeScore}</td>"
                . "</tr>\n";
        }
        $rows .= "</table>";
        return $rows;
    }

    /**
     * Build raw .sch bytes (80,000 bytes) containing the given games, for driving
     * the full update() path through a stubbed resolver without touching disk.
     *
     * @param list<array{date_slot: int, game_index: int, visitor: int, home: int, visitor_score: int, home_score: int}> $games
     */
    private function buildSchBytes(array $games): string
    {
        $empty = str_repeat('0   0     ', SchFileParser::SLOTS_PER_DATE);
        $data = str_repeat($empty, SchFileParser::FILE_SIZE / (SchFileParser::SLOTS_PER_DATE * SchFileParser::RECORD_SIZE));

        foreach ($games as $game) {
            $offset = ($game['date_slot'] * SchFileParser::SLOTS_PER_DATE + $game['game_index']) * SchFileParser::RECORD_SIZE;

            $home = str_pad((string) $game['home'], 2, '0', STR_PAD_LEFT);
            $teamsField = str_pad((string) $game['visitor'] . $home, SchFileParser::TEAMS_FIELD_SIZE);

            if ($game['visitor_score'] === 0 && $game['home_score'] === 0) {
                $scoresField = str_pad('0', SchFileParser::SCORES_FIELD_SIZE);
            } else {
                $homeScore = str_pad((string) $game['home_score'], 3, '0', STR_PAD_LEFT);
                $scoresField = str_pad((string) $game['visitor_score'] . $homeScore, SchFileParser::SCORES_FIELD_SIZE);
            }

            $data = substr_replace($data, $teamsField . $scoresField, $offset, SchFileParser::RECORD_SIZE);
        }

        return $data;
    }

    /** @param list<string> $queries */
    private function firstIndexContaining(array $queries, string $needle): ?int
    {
        foreach ($queries as $i => $q) {
            if (str_contains($q, $needle)) {
                return $i;
            }
        }
        return null;
    }

    /** @param list<string> $queries */
    private function lastIndexContaining(array $queries, string $needle): ?int
    {
        $found = null;
        foreach ($queries as $i => $q) {
            if (str_contains($q, $needle)) {
                $found = $i;
            }
        }
        return $found;
    }

    /**
     * @param list<array{team_name: string, teamid: int}> $teams
     * @param list<array{date_slot: int, game_index: int, visitor: int, home: int, visitor_score: int, home_score: int}> $games
     */
    private function createUpdaterForFullRun(
        array $teams,
        array $games,
        string $phase = 'Regular Season',
        ?string $basePath = null,
    ): TestableScheduleUpdater {
        $this->mockDb->setMockData($teams);
        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn($this->buildSchBytes($games));

        return $this->createUpdater(phase: $phase, sourceResolver: $resolver, basePath: $basePath);
    }

    /** Create an empty temp root so ibl/<league>/Schedule.htm is guaranteed absent. */
    private function makeRootWithoutScheduleHtm(string $prefix): string
    {
        $root = sys_get_temp_dir() . '/' . uniqid($prefix, true);
        mkdir($root, 0777, true);
        $this->tempDirs[] = $root;

        return $root;
    }

    /** Create a temp dir with ibl/<league>/Schedule.htm containing $html. Returns the root path. */
    private function makeScheduleHtmForLeague(string $league, string $html): string
    {
        $root = sys_get_temp_dir() . '/' . uniqid('sch_test_', true);
        mkdir($root . '/ibl/' . $league, 0777, true);
        file_put_contents($root . '/ibl/' . $league . '/Schedule.htm', $html);
        $this->tempDirs[] = $root;
        return $root;
    }

    /**
     * Run the playoff import against a basePath whose Schedule.htm exists but cannot be
     * opened. The E_WARNING file_get_contents() emits on open failure is swallowed for the
     * duration of the call so PHPUnit 13 does not record it as an issue.
     */
    private function runAgainstUnreadableScheduleHtm(TestableScheduleUpdater $updater): string
    {
        set_error_handler(static fn (): bool => true, E_WARNING);
        try {
            return $updater->exposedInsertPlayoffGamesFromScheduleHtm();
        } finally {
            restore_error_handler();
        }
    }

    private function showDebugOutputAsAdmin(): void
    {
        $authStub = self::createStub(\Auth\AuthService::class);
        $authStub->method('isAdmin')->willReturn(true);
        $GLOBALS['authService'] = $authStub;
    }

    /** @return list<string> */
    private function scheduleInserts(): array
    {
        return array_values(array_filter(
            $this->mockDb->getExecutedQueries(),
            static fn (string $q): bool => str_starts_with($q, 'INSERT INTO ibl_schedule')
                || str_starts_with($q, 'INSERT INTO ibl_olympics_schedule'),
        ));
    }

    private function runUpdateCapturingOutput(TestableScheduleUpdater $updater): string
    {
        ob_start();
        try {
            $updater->update();
        } finally {
            $output = ob_get_clean();
        }

        return $output === false ? '' : $output;
    }

    private function registerUnreadableWrapper(): string
    {
        stream_wrapper_register(UnreadableScheduleHtmStreamWrapper::SCHEME, UnreadableScheduleHtmStreamWrapper::class);
        return UnreadableScheduleHtmStreamWrapper::SCHEME . '://root';
    }

    public function testUpdateWrapsRebuildInCommittedTransaction(): void
    {
        $updater = $this->createUpdaterForFullRun(
            [
                ['team_name' => 'Alpha', 'teamid' => 1],
                ['team_name' => 'Beta', 'teamid' => 2],
            ],
            [
                ['date_slot' => 103, 'game_index' => 0, 'visitor' => 1, 'home' => 2, 'visitor_score' => 100, 'home_score' => 95],
                ['date_slot' => 103, 'game_index' => 1, 'visitor' => 2, 'home' => 1, 'visitor_score' => 88, 'home_score' => 90],
            ],
        );

        ob_start();
        $updater->update();
        ob_end_clean();

        $log = $this->mockDb->getOperationLog();
        $beginIdx = array_search('BEGIN', $log, true);
        $commitIdx = array_search('COMMIT', $log, true);
        $deleteIdx = $this->firstIndexContaining($log, 'DELETE FROM ibl_schedule');
        $lastInsertIdx = $this->lastIndexContaining($log, 'INSERT INTO ibl_schedule');

        $this->assertNotFalse($beginIdx, 'expected a BEGIN');
        $this->assertNotFalse($commitIdx, 'expected a COMMIT');
        $this->assertNotContains('ROLLBACK', $log, 'a committed run must not roll back');
        $this->assertNotNull($deleteIdx);
        $this->assertNotNull($lastInsertIdx);
        $this->assertLessThan($deleteIdx, $beginIdx, 'BEGIN must precede the DELETE');
        $this->assertGreaterThan($lastInsertIdx, $commitIdx, 'COMMIT must follow the last INSERT');
    }

    public function testUpdateRollsBackWhenAnInsertFails(): void
    {
        $updater = $this->createUpdaterForFullRun(
            [
                ['team_name' => 'Alpha', 'teamid' => 1],
                ['team_name' => 'Beta', 'teamid' => 2],
            ],
            [
                ['date_slot' => 103, 'game_index' => 0, 'visitor' => 1, 'home' => 2, 'visitor_score' => 100, 'home_score' => 95],
                ['date_slot' => 103, 'game_index' => 1, 'visitor' => 2, 'home' => 1, 'visitor_score' => 88, 'home_score' => 90],
            ],
        );
        $this->mockDb->failOnNthInsert(2);

        ob_start();
        try {
            $updater->update();
            $threw = false;
        } catch (\RuntimeException) {
            $threw = true;
        } finally {
            ob_end_clean();
        }

        $log = $this->mockDb->getOperationLog();
        $this->assertTrue($threw, 'update() must rethrow when an insert fails');
        $this->assertContains('ROLLBACK', $log, 'a failed run must roll back');
        $this->assertNotContains('COMMIT', $log, 'a failed run must not commit');
    }

    /** @return list<array{team_name: string, teamid: int}> */
    private function defaultPinTeams(): array
    {
        return [
            ['team_name' => 'Alpha', 'teamid' => 1],
            ['team_name' => 'Beta', 'teamid' => 2],
            ['team_name' => 'Gamma', 'teamid' => 3],
            ['team_name' => 'Delta', 'teamid' => 4],
        ];
    }

    public function testUpdateBindsPlayedRegularGameValuesInColumnOrder(): void
    {
        $updater = $this->createUpdaterForFullRun(
            $this->defaultPinTeams(),
            [['date_slot' => 5, 'game_index' => 0, 'visitor' => 1, 'home' => 2, 'visitor_score' => 100, 'home_score' => 95]],
            'Regular Season',
            $this->makeRootWithoutScheduleHtm('sch_update_pin_'),
        );

        $this->runUpdateCapturingOutput($updater);

        $this->assertSame('iisiiiis', $this->mockDb->getLastBoundTypes());
        $params = $this->mockDb->getLastBoundParams();
        $this->assertCount(8, $params);
        $this->assertSame([2025, 75, '2024-10-6', 1, 100, 2, 95], array_slice($params, 0, 7));
        $this->assertIsString($params[7]);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $params[7]);

        $prepared = array_values(array_filter(
            $this->mockDb->getPreparedQueries(),
            static fn (string $q): bool => str_contains($q, 'INSERT INTO `ibl_schedule`'),
        ));
        $this->assertCount(1, $prepared);
        $this->assertStringContainsString('VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $prepared[0]);
        $this->assertStringNotContainsString('2024-10-6', $prepared[0]);
    }

    public function testUpdateBindsUnplayedRegularGameWithUnplayedBoxIdAndZeroScores(): void
    {
        $updater = $this->createUpdaterForFullRun(
            $this->defaultPinTeams(),
            [['date_slot' => 5, 'game_index' => 0, 'visitor' => 1, 'home' => 2, 'visitor_score' => 0, 'home_score' => 0]],
            'Regular Season',
            $this->makeRootWithoutScheduleHtm('sch_update_pin_'),
        );

        $this->runUpdateCapturingOutput($updater);

        // 100000 fits ibl_schedule.box_id int(11) (ibl5/docs/schema/current-schema.sql)
        $this->assertSame(
            [2025, 100000, '2024-10-6', 1, 0, 2, 0],
            array_slice($this->mockDb->getLastBoundParams(), 0, 7),
        );
    }

    /**
     * Kills the Infection mutants NotIdentical (IBL_HEAT_MONTH), TrueValue (skip return) and Continue_ (the dropped
     * December slot precedes a kept one, so a break loses the later insert).
     */
    public function testHeatPhaseKeepsOctoberAndNovemberGamesAndDropsDecember(): void
    {
        $updater = $this->createUpdaterForFullRun(
            $this->defaultPinTeams(),
            [
                ['date_slot' => 5, 'game_index' => 0, 'visitor' => 1, 'home' => 2, 'visitor_score' => 0, 'home_score' => 0],
                ['date_slot' => 70, 'game_index' => 0, 'visitor' => 3, 'home' => 4, 'visitor_score' => 0, 'home_score' => 0],
                ['date_slot' => 40, 'game_index' => 0, 'visitor' => 2, 'home' => 3, 'visitor_score' => 0, 'home_score' => 0],
            ],
            'HEAT',
            $this->makeRootWithoutScheduleHtm('sch_update_pin_'),
        );

        $this->runUpdateCapturingOutput($updater);

        $inserts = $this->scheduleInserts();
        $joined = implode("\n", $inserts);
        $this->assertCount(2, $inserts);
        $this->assertStringContainsString("'2024-10-6'", $joined);
        $this->assertStringContainsString("'2024-10-10'", $joined);
        $this->assertStringNotContainsString("'2024-12-9'", $joined);
    }

    /**
     * Kills the Infection mutant LogicalOr (swapped for the month-clause conjunction), and removal of either month
     * clause: January would be inserted or September dropped.
     */
    public function testPreseasonPhaseKeepsOctoberThroughDecemberAndDropsJanuary(): void
    {
        $updater = $this->createUpdaterForFullRun(
            $this->defaultPinTeams(),
            [
                ['date_slot' => 5, 'game_index' => 0, 'visitor' => 1, 'home' => 2, 'visitor_score' => 0, 'home_score' => 0],
                ['date_slot' => 103, 'game_index' => 0, 'visitor' => 3, 'home' => 4, 'visitor_score' => 0, 'home_score' => 0],
                ['date_slot' => 40, 'game_index' => 0, 'visitor' => 2, 'home' => 3, 'visitor_score' => 0, 'home_score' => 0],
                ['date_slot' => 70, 'game_index' => 0, 'visitor' => 1, 'home' => 4, 'visitor_score' => 0, 'home_score' => 0],
            ],
            'Preseason',
            $this->makeRootWithoutScheduleHtm('sch_update_pin_'),
        );

        $this->runUpdateCapturingOutput($updater);

        $inserts = $this->scheduleInserts();
        $joined = implode("\n", $inserts);
        $this->assertCount(3, $inserts);
        $this->assertStringContainsString("'2024-10-6'", $joined);
        $this->assertStringContainsString("'2024-9-10'", $joined);
        $this->assertStringContainsString("'2024-10-9'", $joined);
        $this->assertStringNotContainsString("'2025-1-11'", $joined);
    }

    /**
     * Kills the Infection mutant LogicalAnd (swapped for the combined skip guard's disjunction) and the LogicalNot
     * removal on the real-team check.
     */
    public function testOlympicsUpdateSkipsGamesInvolvingFillerTeams(): void
    {
        $this->mockDb->setMockData([
            ['team_name' => 'Eagles', 'teamid' => 1, 'is_real_team' => 1],
            ['team_name' => 'Maple', 'teamid' => 2, 'is_real_team' => 1],
            ['team_name' => 'Filler29', 'teamid' => 29, 'is_real_team' => 0],
        ]);
        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn($this->buildSchBytes([
            ['date_slot' => 5, 'game_index' => 0, 'visitor' => 29, 'home' => 1, 'visitor_score' => 0, 'home_score' => 0],
            ['date_slot' => 5, 'game_index' => 1, 'visitor' => 1, 'home' => 2, 'visitor_score' => 0, 'home_score' => 0],
            ['date_slot' => 6, 'game_index' => 0, 'visitor' => 2, 'home' => 29, 'visitor_score' => 0, 'home_score' => 0],
        ]));
        $updater = $this->createUpdater(
            phase: 'Regular Season',
            olympics: true,
            sourceResolver: $resolver,
            basePath: $this->makeRootWithoutScheduleHtm('sch_update_pin_'),
        );

        $this->runUpdateCapturingOutput($updater);

        $this->assertCount(1, $this->scheduleInserts());
        $params = $this->mockDb->getLastBoundParams();
        $this->assertSame(1, $params[3]);
        $this->assertSame(2, $params[5]);
    }

    public function testGamesInOneDateSlotShareTheDateAndANewSlotRecomputesIt(): void
    {
        $updater = $this->createUpdaterForFullRun(
            $this->defaultPinTeams(),
            [
                ['date_slot' => 10, 'game_index' => 0, 'visitor' => 1, 'home' => 2, 'visitor_score' => 0, 'home_score' => 0],
                ['date_slot' => 10, 'game_index' => 1, 'visitor' => 3, 'home' => 4, 'visitor_score' => 0, 'home_score' => 0],
                ['date_slot' => 11, 'game_index' => 0, 'visitor' => 1, 'home' => 3, 'visitor_score' => 0, 'home_score' => 0],
            ],
            'Regular Season',
            $this->makeRootWithoutScheduleHtm('sch_update_pin_'),
        );

        $this->runUpdateCapturingOutput($updater);

        $inserts = $this->scheduleInserts();
        $joined = implode("\n", $inserts);
        $this->assertCount(3, $inserts);
        $this->assertSame(2, substr_count($joined, "'2024-10-11'"));
        $this->assertSame(1, substr_count($joined, "'2024-10-12'"));
    }

    public function testInsertFailureEchoesEscapedNamesLogsErrorAndRethrowsCode1002(): void
    {
        $this->mockDb->setMockData([
            ['team_name' => 'Alpha <b>', 'teamid' => 1],
            ['team_name' => 'Beta & Co', 'teamid' => 2],
        ]);
        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn($this->buildSchBytes([
            ['date_slot' => 5, 'game_index' => 0, 'visitor' => 1, 'home' => 2, 'visitor_score' => 100, 'home_score' => 95],
        ]));
        $prefix = 'Failed to insert schedule data for game between Alpha <b> and Beta & Co: ';
        $loggedError = null;
        $logger = self::createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'ScheduleUpdater database insert error',
                self::callback(static function (array $context) use (&$loggedError, $prefix): bool {
                    $error = $context['error'] ?? null;
                    if (!is_string($error)) {
                        return false;
                    }
                    $loggedError = $error;

                    return str_starts_with($error, $prefix) && strlen($error) > strlen($prefix);
                }),
            );
        $updater = $this->createUpdater(
            sourceResolver: $resolver,
            basePath: $this->makeRootWithoutScheduleHtm('sch_update_pin_'),
            logger: $logger,
        );
        $this->mockDb->failOnNthInsert(1);

        $caught = null;
        ob_start();
        try {
            $updater->update();
        } catch (\RuntimeException $e) {
            $caught = $e;
        } finally {
            $captured = ob_get_clean();
        }
        $output = $captured === false ? '' : $captured;

        $this->assertNotNull($caught, 'update() must rethrow when an insert fails');
        $this->assertSame(1002, $caught->getCode());
        $this->assertSame($loggedError, $caught->getMessage());
        $this->assertStringContainsString(
            '<strong class="ibl-form-error">Script Error: Failed to insert schedule data for game between Alpha &lt;b&gt; and Beta &amp; Co.</strong>',
            $output,
        );
        $this->assertContains('ROLLBACK', $this->mockDb->getOperationLog());
    }

    public function testUpdateThrowsWhenResolverHasNoScheduleFile(): void
    {
        $this->mockDb->setMockData($this->defaultPinTeams());
        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn(null);
        $updater = $this->createUpdater(
            sourceResolver: $resolver,
            basePath: $this->makeRootWithoutScheduleHtm('sch_update_pin_'),
        );

        $caught = null;
        ob_start();
        try {
            $updater->update();
        } catch (\RuntimeException $e) {
            $caught = $e;
        } finally {
            ob_end_clean();
        }

        $this->assertNotNull($caught, 'update() must throw when the resolver has no schedule file');
        $this->assertSame('Schedule file not found via resolver', $caught->getMessage());
        $this->assertContains('ROLLBACK', $this->mockDb->getOperationLog());
        $this->assertSame([], $this->scheduleInserts());
    }

    public function testUpdateReadsSchFileFromBasePathWhenNoResolverIsInjected(): void
    {
        $this->mockDb->setMockData([
            ['team_name' => 'Eagles', 'teamid' => 1, 'is_real_team' => 1],
            ['team_name' => 'Maple', 'teamid' => 2, 'is_real_team' => 1],
        ]);
        $root = $this->makeRootWithoutScheduleHtm('sch_file_pin_');
        file_put_contents($root . '/Olympics.sch', $this->buildSchBytes([
            ['date_slot' => 5, 'game_index' => 0, 'visitor' => 1, 'home' => 2, 'visitor_score' => 90, 'home_score' => 80],
        ]));
        // Olympics on purpose: with the IBL5 prefix a swapped ternary would read the same file.
        $updater = $this->createUpdater(phase: 'Regular Season', olympics: true, basePath: $root);

        $this->runUpdateCapturingOutput($updater);

        $this->assertCount(1, $this->scheduleInserts());
        $this->assertSame([1, 90, 2, 80], array_slice($this->mockDb->getLastBoundParams(), 3, 4));
    }

    public function testUpdateDebugLogListsDeleteThenEachInsertedGame(): void
    {
        $this->showDebugOutputAsAdmin();
        $updater = $this->createUpdaterForFullRun(
            $this->defaultPinTeams(),
            [
                ['date_slot' => 5, 'game_index' => 0, 'visitor' => 1, 'home' => 2, 'visitor_score' => 0, 'home_score' => 0],
                ['date_slot' => 5, 'game_index' => 1, 'visitor' => 2, 'home' => 1, 'visitor_score' => 0, 'home_score' => 0],
            ],
            'Regular Season',
            $this->makeRootWithoutScheduleHtm('sch_update_pin_'),
        );

        $output = $this->runUpdateCapturingOutput($updater);

        $deletePos = strpos($output, 'DELETE FROM `ibl_schedule`&lt;p&gt;');
        $firstPos = strpos($output, 'Inserted game: Alpha @ Beta on 2024-10-6<br>');
        $secondPos = strpos($output, 'Inserted game: Beta @ Alpha on 2024-10-6<br>');
        $this->assertNotFalse($deletePos);
        $this->assertNotFalse($firstPos);
        $this->assertNotFalse($secondPos);
        $this->assertLessThan($firstPos, $deletePos);
        $this->assertLessThan($secondPos, $firstPos);
    }

    public function testExtractDateReturnsNullForEmptyString(): void
    {
        $updater = $this->createUpdater();

        $this->assertNull($updater->exposedExtractDate(''));
    }

    public function testExtractDateParsesNovemberDate(): void
    {
        $updater = $this->createUpdater();

        $result = $updater->exposedExtractDate('November 5, 2000');

        $this->assertNotNull($result);
        $this->assertSame(11, $result['month']);
        $this->assertSame(5, $result['day']);
        $this->assertSame(2024, $result['year']);
    }

    public function testExtractDateParsesAprilDate(): void
    {
        $updater = $this->createUpdater();

        $result = $updater->exposedExtractDate('April 10, 2000');

        $this->assertNotNull($result);
        $this->assertSame(4, $result['month']);
        $this->assertSame(10, $result['day']);
        $this->assertSame(2025, $result['year']);
    }

    public function testExtractDateParsesJuneDate(): void
    {
        $updater = $this->createUpdater();

        $result = $updater->exposedExtractDate('June 15, 2000');

        $this->assertNotNull($result);
        $this->assertSame(6, $result['month']);
        $this->assertSame(15, $result['day']);
        $this->assertSame(2025, $result['year']);
    }

    public function testOlympicsPreloadLoadsAllTeamsWithoutFilter(): void
    {
        $this->mockDb->setMockData([
            ['team_name' => 'Eagles', 'teamid' => 1, 'is_real_team' => 1],
            ['team_name' => 'Maple', 'teamid' => 2, 'is_real_team' => 1],
            ['team_name' => 'Filler29', 'teamid' => 29, 'is_real_team' => 0],
        ]);

        $updater = $this->createUpdater(olympics: true);
        $updater->exposedPreloadTeamNameMap();

        $map = $updater->getTeamNameToIdMap();
        $this->assertCount(3, $map);
        $this->assertSame(29, $map['Filler29']);
    }

    public function testIblPreloadFiltersToMaxRealTeamId(): void
    {
        $this->mockDb->setMockData([
            ['team_name' => 'Celtics', 'teamid' => 1],
            ['team_name' => 'Lakers', 'teamid' => 2],
        ]);

        $updater = $this->createUpdater(olympics: false);
        $updater->exposedPreloadTeamNameMap();

        $queries = $this->mockDb->getExecutedQueries();
        $matched = array_filter($queries, static fn (string $q): bool => str_contains($q, 'BETWEEN 1 AND'));
        $this->assertNotSame([], $matched);
    }

    // -------------------------------------------------------------------------
    // Phase 1: Characterization — matching-year playoff rows ARE inserted
    // -------------------------------------------------------------------------

    public function testPlayoffGamesFromMatchingSeasonAreInserted(): void
    {
        $html = $this->playoffHtml(2025, [
            ['visitor' => 'Metros', 'home' => 'Stars', 'played' => true, 'box_id' => 6001],
            ['visitor' => 'Stars', 'home' => 'Metros', 'played' => false],
        ]);
        $basePath = $this->makeScheduleHtm($html);

        $updater = $this->createUpdater('Playoffs', 2025, false, null, $basePath);
        $updater->setTeamNameToIdMap(['Metros' => 1, 'Stars' => 2]);
        $this->mockDb->clearQueries();

        $log = $updater->exposedInsertPlayoffGamesFromScheduleHtm();

        $queries = $this->mockDb->getExecutedQueries();
        $inserts = array_filter($queries, static fn (string $q): bool => str_contains($q, 'INSERT INTO ibl_schedule'));

        $this->assertCount(2, $inserts, 'exactly two inserts expected for two matching-year playoff rows');

        $insertStr = implode(' ', $inserts);
        $this->assertStringContainsString('2025,', $insertStr, 'season_year binding must be 2025');
        $this->assertStringContainsString('2025-6-1', $insertStr, 'game_date must be June 1 of ending year');
        $this->assertStringContainsString('6001', $insertStr, 'played game must carry its own box_id');
        $this->assertStringContainsString('100000', $insertStr, 'unplayed game must carry UNPLAYED_BOX_ID=100000');

        $this->assertSame(2, substr_count($log, 'Inserted playoff game:'), 'log must mention two inserted games');
    }

    // -------------------------------------------------------------------------
    // Phase 3: Guard tests — written post-impl (after Phase 2 guard is added)
    // -------------------------------------------------------------------------

    /**
     * Skip mechanics are phase-independent; asserted outside Playoffs because during
     * Playoffs an all-stale export is fatal (see testStaleScheduleHtmThrowsDuringPlayoffs).
     */
    public function testPlayoffRowsFromAPriorSeasonAreSkipped(): void
    {
        $html = $this->playoffHtml(2007, [
            ['visitor' => 'Metros', 'home' => 'Stars', 'played' => true, 'box_id' => 6001],
            ['visitor' => 'Stars', 'home' => 'Metros', 'played' => false],
        ]);
        $basePath = $this->makeScheduleHtm($html);

        $updater = $this->createUpdater('Regular Season', 2008, false, null, $basePath);
        $updater->setTeamNameToIdMap(['Metros' => 1, 'Stars' => 2]);
        $this->mockDb->clearQueries();

        $log = $updater->exposedInsertPlayoffGamesFromScheduleHtm();

        $queries = $this->mockDb->getExecutedQueries();
        $inserts = array_filter($queries, static fn (string $q): bool => str_contains($q, 'INSERT INTO ibl_schedule'));

        $this->assertCount(0, $inserts, 'prior-season rows must not be inserted');
        $this->assertStringContainsString('Skipped 2', $log);
        $this->assertStringContainsString('2007', $log, 'log must name the file year');
        $this->assertStringContainsString('2008', $log, 'log must name the current ending year');
        $this->assertStringNotContainsString('Inserted playoff game:', $log);
    }

    public function testPlayoffRowWithUnparseableYearLabelIsSkipped(): void
    {
        $html = "<table>\n<tr><th>Post 1 07</th></tr>\n"
            . "<tr><td><a href=\"t1.htm\">Metros</a></td><td></td>"
            . "<td><a href=\"t2.htm\">Stars</a></td><td></td></tr>\n"
            . "</table>";
        $basePath = $this->makeScheduleHtm($html);

        $updater = $this->createUpdater('Regular Season', 2025, false, null, $basePath);
        $updater->setTeamNameToIdMap(['Metros' => 1, 'Stars' => 2]);
        $this->mockDb->clearQueries();

        $log = $updater->exposedInsertPlayoffGamesFromScheduleHtm();

        $this->assertIsString($log, 'method must return a string even for unparseable label');

        $queries = $this->mockDb->getExecutedQueries();
        $inserts = array_filter($queries, static fn (string $q): bool => str_contains($q, 'INSERT INTO ibl_schedule'));
        $this->assertCount(0, $inserts, 'unparseable-year row must not be inserted');

        $this->assertStringContainsString('Skipped 1', $log);
        $this->assertStringContainsString('unparseable', $log);
    }

    /**
     * Outside the Playoffs phase a missing Schedule.htm costs nothing — there are no
     * postseason rows to lose — so the import degrades gracefully instead of throwing.
     */
    public function testMissingScheduleHtmReturnsGracefulMessageAndInsertsNothing(): void
    {
        $root = $this->makeRootWithoutScheduleHtm('sch_missing_');

        $updater = $this->createUpdater('Regular Season', 2025, false, null, $root);
        $this->mockDb->clearQueries();

        $log = $updater->exposedInsertPlayoffGamesFromScheduleHtm();

        $this->assertStringContainsString('Schedule.htm not found at', $log);

        $queries = $this->mockDb->getExecutedQueries();
        $inserts = array_filter($queries, static fn (string $q): bool => str_contains($q, 'INSERT INTO ibl_schedule'));
        $this->assertCount(0, $inserts);
    }

    /**
     * A missing Schedule.htm silently drops every playoff game from ibl_schedule
     * (the whole 2008 postseason vanished from Last Sim this way). The import still
     * reports success, so the warning is the only signal anything went wrong.
     */
    public function testMissingScheduleHtmLogsWarning(): void
    {
        $root = $this->makeRootWithoutScheduleHtm('sch_missing_log_');

        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                self::stringContains('Schedule.htm'),
                self::callback(static function (array $context) use ($root): bool {
                    return ($context['path'] ?? null) === $root . '/ibl/IBL/Schedule.htm'
                        && ($context['reason'] ?? null) === 'missing'
                        && ($context['league'] ?? null) === 'IBL'
                        && ($context['season_phase'] ?? null) === 'Regular Season'
                        && ($context['season_ending_year'] ?? null) === 2025;
                }),
            );

        $updater = $this->createUpdater('Regular Season', 2025, false, null, $root, $logger);

        $updater->exposedInsertPlayoffGamesFromScheduleHtm();
    }

    /**
     * Playoff rows exist ONLY in Schedule.htm, so during the Playoffs phase a missing
     * export must be fatal rather than a warning: update() would otherwise commit a
     * schedule with no postseason games and ScheduleMembershipGuard would then reject
     * every real playoff boxscore as "not in ibl_schedule".
     */
    public function testMissingScheduleHtmThrowsDuringPlayoffs(): void
    {
        $root = $this->makeRootWithoutScheduleHtm('sch_missing_playoffs_');

        $updater = $this->createUpdater('Playoffs', 2025, false, null, $root);

        try {
            $updater->exposedInsertPlayoffGamesFromScheduleHtm();
            self::fail('a missing Schedule.htm during Playoffs must throw');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Schedule.htm not found at', $e->getMessage());
            $this->assertStringContainsString('Playoffs phase', $e->getMessage());
        }
    }

    /**
     * The throw must reach update()'s transaction so the DELETE is rolled back and the
     * previous schedule — playoff rows included — survives a missing export.
     */
    public function testUpdateRollsBackWhenScheduleHtmIsMissingDuringPlayoffs(): void
    {
        $root = $this->makeRootWithoutScheduleHtm('sch_missing_rollback_');

        $updater = $this->createUpdaterForFullRun(
            [
                ['team_name' => 'Alpha', 'teamid' => 1],
                ['team_name' => 'Beta', 'teamid' => 2],
            ],
            [
                ['date_slot' => 103, 'game_index' => 0, 'visitor' => 1, 'home' => 2, 'visitor_score' => 100, 'home_score' => 95],
            ],
            'Playoffs',
            $root,
        );

        ob_start();
        try {
            $updater->update();
            $threw = false;
        } catch (\RuntimeException) {
            $threw = true;
        } finally {
            ob_end_clean();
        }

        $log = $this->mockDb->getOperationLog();
        $this->assertTrue($threw, 'update() must rethrow when Schedule.htm is missing during Playoffs');
        $this->assertContains('ROLLBACK', $log, 'the DELETE must be rolled back');
        $this->assertNotContains('COMMIT', $log, 'a schedule with no playoff rows must not commit');
    }

    /**
     * A stale export is as destructive as a missing one — every row belongs to another
     * season, so the rebuild would commit a playoff-less schedule and the boxscore guard
     * would reject the whole postseason. Must be fatal during Playoffs.
     */
    public function testStaleScheduleHtmThrowsDuringPlayoffs(): void
    {
        $html = $this->playoffHtml(2007, [
            ['visitor' => 'Metros', 'home' => 'Stars', 'played' => true, 'box_id' => 6001],
            ['visitor' => 'Stars', 'home' => 'Metros', 'played' => false],
        ]);
        $basePath = $this->makeScheduleHtm($html);

        $updater = $this->createUpdater('Playoffs', 2008, false, null, $basePath);
        $updater->setTeamNameToIdMap(['Metros' => 1, 'Stars' => 2]);

        try {
            $updater->exposedInsertPlayoffGamesFromScheduleHtm();
            self::fail('an all-stale Schedule.htm during Playoffs must throw');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('only stale playoff rows', $e->getMessage());
            $this->assertStringContainsString('2007', $e->getMessage(), 'message must name the file year');
            $this->assertStringContainsString('2008', $e->getMessage(), 'message must name the current ending year');
        }
    }

    /**
     * Same fatal treatment when the year label cannot be parsed at all — an unreadable
     * label is not evidence the rows belong to the current season.
     */
    public function testUnparseableYearOnlyScheduleHtmThrowsDuringPlayoffs(): void
    {
        $html = "<table>\n<tr><th>Post 1 07</th></tr>\n"
            . "<tr><td><a href=\"t1.htm\">Metros</a></td><td></td>"
            . "<td><a href=\"t2.htm\">Stars</a></td><td></td></tr>\n"
            . "</table>";
        $basePath = $this->makeScheduleHtm($html);

        $updater = $this->createUpdater('Playoffs', 2025, false, null, $basePath);
        $updater->setTeamNameToIdMap(['Metros' => 1, 'Stars' => 2]);

        try {
            $updater->exposedInsertPlayoffGamesFromScheduleHtm();
            self::fail('an unparseable-year-only Schedule.htm during Playoffs must throw');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('only stale playoff rows', $e->getMessage());
        }
    }

    /**
     * The stale throw must reach update()'s transaction so the DELETE is rolled back.
     */
    public function testUpdateRollsBackWhenScheduleHtmIsStaleDuringPlayoffs(): void
    {
        $stale = $this->playoffHtml(2007, [
            ['visitor' => 'Alpha', 'home' => 'Beta', 'played' => false],
        ]);
        $basePath = $this->makeScheduleHtm($stale);

        $updater = $this->createUpdaterForFullRun(
            [
                ['team_name' => 'Alpha', 'teamid' => 1],
                ['team_name' => 'Beta', 'teamid' => 2],
            ],
            [
                ['date_slot' => 103, 'game_index' => 0, 'visitor' => 1, 'home' => 2, 'visitor_score' => 100, 'home_score' => 95],
            ],
            'Playoffs',
            $basePath,
        );

        ob_start();
        try {
            $updater->update();
            $threw = false;
        } catch (\RuntimeException) {
            $threw = true;
        } finally {
            ob_end_clean();
        }

        $log = $this->mockDb->getOperationLog();
        $this->assertTrue($threw, 'update() must rethrow when Schedule.htm is stale during Playoffs');
        $this->assertContains('ROLLBACK', $log, 'the DELETE must be rolled back');
        $this->assertNotContains('COMMIT', $log, 'a schedule with no playoff rows must not commit');
    }

    /**
     * An export carrying BOTH current and stale rows still imports the current ones.
     * Nothing is lost, so warn rather than abort.
     */
    public function testMixedCurrentAndStaleScheduleHtmImportsCurrentRowsDuringPlayoffs(): void
    {
        $current = $this->playoffHtml(2008, [
            ['visitor' => 'Metros', 'home' => 'Stars', 'played' => true, 'box_id' => 6001],
        ]);
        $stale = $this->playoffHtml(2007, [
            ['visitor' => 'Stars', 'home' => 'Metros', 'played' => false],
        ]);
        $basePath = $this->makeScheduleHtm($current . "\n" . $stale);

        $updater = $this->createUpdater('Playoffs', 2008, false, null, $basePath);
        $updater->setTeamNameToIdMap(['Metros' => 1, 'Stars' => 2]);
        $this->mockDb->clearQueries();

        $log = $updater->exposedInsertPlayoffGamesFromScheduleHtm();

        $queries = $this->mockDb->getExecutedQueries();
        $inserts = array_filter($queries, static fn (string $q): bool => str_contains($q, 'INSERT INTO ibl_schedule'));

        $this->assertCount(1, $inserts, 'the current-season row must still be inserted');
        $this->assertStringContainsString('Skipped 1', $log, 'the stale row must still be reported');
    }

    /**
     * Early in the Playoffs phase JSB has not seeded the bracket, so an export with no
     * playoff rows at all is expected. Nothing is lost, so it must not be fatal.
     */
    public function testEmptyPlayoffScheduleHtmDoesNotThrowDuringPlayoffs(): void
    {
        $basePath = $this->makeScheduleHtm("<table>\n</table>");

        $updater = $this->createUpdater('Playoffs', 2025, false, null, $basePath);
        $updater->setTeamNameToIdMap(['Metros' => 1, 'Stars' => 2]);
        $this->mockDb->clearQueries();

        $log = $updater->exposedInsertPlayoffGamesFromScheduleHtm();

        $queries = $this->mockDb->getExecutedQueries();
        $inserts = array_filter($queries, static fn (string $q): bool => str_contains($q, 'INSERT INTO ibl_schedule'));

        $this->assertCount(0, $inserts, 'an empty bracket inserts nothing');
        $this->assertStringNotContainsString('Skipped', $log, 'no rows means nothing was skipped');
    }

    // -------------------------------------------------------------------------
    // Characterization pins for the branches the tests above leave unobserved
    // -------------------------------------------------------------------------

    public function testPlayoffRowWithUnparseableDateIsSkippedSilently(): void
    {
        $html = "<table>\n<tr><th>Post 32 2025</th></tr>\n"
            . '<tr><td><a href="t1.htm">Metros</a></td><td></td><td><a href="t2.htm">Stars</a></td><td></td></tr>'
            . "\n</table>";
        $basePath = $this->makeScheduleHtm($html);

        $logger = self::createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $updater = $this->createUpdater('Regular Season', 2025, false, null, $basePath, $logger);
        $updater->setTeamNameToIdMap(['Metros' => 1, 'Stars' => 2]);
        $this->mockDb->clearQueries();

        $log = $updater->exposedInsertPlayoffGamesFromScheduleHtm();

        $queries = $this->mockDb->getExecutedQueries();
        $inserts = array_filter($queries, static fn (string $q): bool => str_contains($q, 'INSERT INTO ibl_schedule'));

        $this->assertCount(0, $inserts, 'a row whose date cannot be parsed is not inserted');
        $this->assertSame('', $log, 'an unparseable date is skipped without any log line');
    }

    public function testPlayoffGameWithUnknownTeamIsLoggedAndSkipped(): void
    {
        $html = $this->playoffHtml(2025, [
            ['visitor' => 'Nobody', 'home' => 'Stars', 'played' => false],
            ['visitor' => 'Metros', 'home' => 'Stars', 'played' => false],
        ]);
        $basePath = $this->makeScheduleHtm($html);

        $updater = $this->createUpdater('Regular Season', 2025, false, null, $basePath);
        $updater->setTeamNameToIdMap(['Metros' => 1, 'Stars' => 2]);
        $this->mockDb->clearQueries();

        $log = $updater->exposedInsertPlayoffGamesFromScheduleHtm();

        $queries = $this->mockDb->getExecutedQueries();
        $inserts = array_filter($queries, static fn (string $q): bool => str_contains($q, 'INSERT INTO ibl_schedule'));

        $this->assertCount(1, $inserts);
        $this->assertSame(
            "Unknown team in playoff game: Nobody @ Stars<br>Inserted playoff game: Metros @ Stars on 2025-6-1<br>",
            $log,
        );
    }

    public function testOlympicsPlayoffGameWithFillerTeamIsSkippedSilently(): void
    {
        $this->mockDb->setMockData([
            ['team_name' => 'Eagles', 'teamid' => 1, 'is_real_team' => 1],
            ['team_name' => 'Maple', 'teamid' => 2, 'is_real_team' => 1],
            ['team_name' => 'Filler29', 'teamid' => 29, 'is_real_team' => 0],
        ]);
        $html = $this->playoffHtml(2025, [
            ['visitor' => 'Filler29', 'home' => 'Eagles', 'played' => false],
            ['visitor' => 'Eagles', 'home' => 'Maple', 'played' => false],
        ]);
        $basePath = $this->makeScheduleHtmForLeague('olympics', $html);

        $updater = $this->createUpdater('Regular Season', 2025, true, null, $basePath);
        $updater->exposedPreloadTeamNameMap();
        $this->mockDb->clearQueries();

        $log = $updater->exposedInsertPlayoffGamesFromScheduleHtm();

        $queries = $this->mockDb->getExecutedQueries();
        $inserts = array_filter($queries, static fn (string $q): bool => str_contains($q, 'INSERT INTO ibl_olympics_schedule') || str_contains($q, 'INSERT INTO ibl_schedule'));

        $this->assertCount(1, $inserts, 'only the row between real teams is inserted');
        $this->assertSame(1, substr_count($log, 'Inserted playoff game: Eagles @ Maple'));
        $this->assertStringNotContainsString('Filler29', $log);
        $this->assertStringNotContainsString('Unknown team', $log);
    }

    public function testPlayedPlayoffGameWithoutBoxLinkUsesUnplayedBoxId(): void
    {
        // The visitor score links to game1.htm, not boxN.htm, so no box id is parsed.
        $html = "<table>\n<tr><th>Post 2 2025</th></tr>\n"
            . '<tr><td><a href="t1.htm">Metros</a></td><td><a href="game1.htm">101</a></td>'
            . '<td><a href="t2.htm">Stars</a></td><td><a href="game2.htm">95</a></td></tr>'
            . "\n</table>";
        $basePath = $this->makeScheduleHtm($html);

        $updater = $this->createUpdater('Playoffs', 2025, false, null, $basePath);
        $updater->setTeamNameToIdMap(['Metros' => 1, 'Stars' => 2]);
        $this->mockDb->clearQueries();

        $updater->exposedInsertPlayoffGamesFromScheduleHtm();

        $queries = $this->mockDb->getExecutedQueries();
        $inserts = array_filter($queries, static fn (string $q): bool => str_contains($q, 'INSERT INTO ibl_schedule'));

        $this->assertCount(1, $inserts);
        $bound = $this->mockDb->getLastBoundParams();
        $this->assertSame(100000, $bound[1], 'a played row with no box link falls back to UNPLAYED_BOX_ID');
        $this->assertSame(101, $bound[4]);
        $this->assertSame(95, $bound[6]);
    }

    public function testUnreadableScheduleHtmReturnsGracefulMessageOutsidePlayoffs(): void
    {
        $basePath = $this->registerUnreadableWrapper();

        $logger = self::createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                self::stringContains('Schedule.htm'),
                self::callback(static fn (array $context): bool => $context['path'] === $basePath . '/ibl/IBL/Schedule.htm'
                    && $context['reason'] === 'unreadable'
                    && $context['league'] === 'IBL'
                    && $context['season_phase'] === 'Regular Season'
                    && $context['season_ending_year'] === 2025),
            );

        $updater = $this->createUpdater('Regular Season', 2025, false, null, $basePath, $logger);
        $this->mockDb->clearQueries();

        try {
            $log = $this->runAgainstUnreadableScheduleHtm($updater);
        } finally {
            stream_wrapper_unregister(UnreadableScheduleHtmStreamWrapper::SCHEME);
        }

        $queries = $this->mockDb->getExecutedQueries();
        $inserts = array_filter($queries, static fn (string $q): bool => str_contains($q, 'INSERT INTO ibl_schedule'));

        $this->assertSame("Failed to read Schedule.htm — skipping playoff games<br>", $log);
        $this->assertCount(0, $inserts);
    }

    public function testUnreadableScheduleHtmThrowsDuringPlayoffs(): void
    {
        $basePath = $this->registerUnreadableWrapper();

        $logger = self::createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $updater = $this->createUpdater('Playoffs', 2025, false, null, $basePath, $logger);
        $this->mockDb->clearQueries();

        try {
            $this->runAgainstUnreadableScheduleHtm($updater);
            self::fail('an unreadable Schedule.htm during Playoffs must throw');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Failed to read Schedule.htm at', $e->getMessage());
            $this->assertStringContainsString('Playoffs phase', $e->getMessage());
        } finally {
            stream_wrapper_unregister(UnreadableScheduleHtmStreamWrapper::SCHEME);
        }
    }

    public function testStaleRowsLogWarningWithFileYearsOutsidePlayoffs(): void
    {
        $html = $this->playoffHtml(2007, [
            ['visitor' => 'Metros', 'home' => 'Stars', 'played' => true, 'box_id' => 6001],
            ['visitor' => 'Stars', 'home' => 'Metros', 'played' => false],
        ]);
        $basePath = $this->makeScheduleHtm($html);

        $logger = self::createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'ScheduleUpdater skipped stale playoff rows',
                self::callback(static fn (array $context): bool => $context['skipped'] === 2
                    && $context['file_years'] === [2007]
                    && $context['season_ending_year'] === 2008),
            );

        $updater = $this->createUpdater('Regular Season', 2008, false, null, $basePath, $logger);
        $updater->setTeamNameToIdMap(['Metros' => 1, 'Stars' => 2]);
        $this->mockDb->clearQueries();

        $log = $updater->exposedInsertPlayoffGamesFromScheduleHtm();

        $this->assertSame(
            "Skipped 2 stale playoff row(s) from Schedule.htm year(s) 2007 — current season ending year is 2008<br>",
            $log,
        );
    }

    public function testInsertBindsPlayoffRowValuesInColumnOrder(): void
    {
        // The parser reads a score only from inside a link, so the home score is linked too.
        $html = "<table>\n<tr><th>Post 1 2025</th></tr>\n"
            . '<tr><td><a href="t1.htm">Metros</a></td><td><a href="box6001.htm">101</a></td>'
            . '<td><a href="t2.htm">Stars</a></td><td><a href="game2.htm">95</a></td></tr>'
            . "\n</table>";
        $basePath = $this->makeScheduleHtm($html);

        $updater = $this->createUpdater('Playoffs', 2025, false, null, $basePath);
        $updater->setTeamNameToIdMap(['Metros' => 1, 'Stars' => 2]);
        $this->mockDb->clearQueries();

        $updater->exposedInsertPlayoffGamesFromScheduleHtm();

        // season_year, box_id, game_date, visitor_teamid, visitor_score, home_teamid, home_score
        $bound = $this->mockDb->getLastBoundParams();
        $this->assertCount(8, $bound);
        $this->assertSame([2025, 6001, '2025-6-1', 1, 101, 2, 95], array_slice($bound, 0, 7));
        $this->assertIsString($bound[7]);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $bound[7]);

        $prepared = array_values(array_filter(
            $this->mockDb->getPreparedQueries(),
            static fn (string $q): bool => str_contains($q, 'INSERT INTO `ibl_schedule`'),
        ));
        $this->assertCount(1, $prepared);
        $this->assertStringContainsString('VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $prepared[0]);
        $this->assertStringNotContainsString('6001', $prepared[0], 'no value may reach the SQL text');
    }

    public function testUnplayedPlayoffRowBindsZeroScoresAndUnplayedBoxId(): void
    {
        $html = $this->playoffHtml(2025, [
            ['visitor' => 'Stars', 'home' => 'Metros', 'played' => false],
        ]);
        $basePath = $this->makeScheduleHtm($html);

        $updater = $this->createUpdater('Playoffs', 2025, false, null, $basePath);
        $updater->setTeamNameToIdMap(['Metros' => 1, 'Stars' => 2]);
        $this->mockDb->clearQueries();

        $updater->exposedInsertPlayoffGamesFromScheduleHtm();

        $this->assertSame(
            [2025, 100000, '2025-6-1', 2, 0, 1, 0],
            array_slice($this->mockDb->getLastBoundParams(), 0, 7),
        );
    }

    public function testMissingScheduleHtmWarnsBeforeThrowingDuringPlayoffs(): void
    {
        $basePath = $this->makeRootWithoutScheduleHtm('sch_missing_order_');

        $logger = self::createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $updater = $this->createUpdater('Playoffs', 2025, false, null, $basePath, $logger);
        $this->mockDb->clearQueries();

        try {
            $updater->exposedInsertPlayoffGamesFromScheduleHtm();
            self::fail('a missing Schedule.htm during Playoffs must throw');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Schedule.htm not found at', $e->getMessage());
        }

        $queries = $this->mockDb->getExecutedQueries();
        $inserts = array_filter($queries, static fn (string $q): bool => str_contains($q, 'INSERT INTO ibl_schedule'));
        $this->assertCount(0, $inserts);
    }

    public function testStaleSummaryIsAppendedAfterInsertLines(): void
    {
        $current = $this->playoffHtml(2008, [
            ['visitor' => 'Metros', 'home' => 'Stars', 'played' => true, 'box_id' => 6001],
        ]);
        $stale = $this->playoffHtml(2007, [
            ['visitor' => 'Stars', 'home' => 'Metros', 'played' => false],
        ]);
        $basePath = $this->makeScheduleHtm($current . "\n" . $stale);

        $updater = $this->createUpdater('Playoffs', 2008, false, null, $basePath);
        $updater->setTeamNameToIdMap(['Metros' => 1, 'Stars' => 2]);
        $this->mockDb->clearQueries();

        $log = $updater->exposedInsertPlayoffGamesFromScheduleHtm();

        $this->assertSame(
            "Inserted playoff game: Metros @ Stars on 2008-6-1<br>"
            . "Skipped 1 stale playoff row(s) from Schedule.htm year(s) 2007 — current season ending year is 2008<br>",
            $log,
        );
    }
}

/**
 * Testable subclass that exposes protected methods for unit testing.
 */
class TestableScheduleUpdater extends \Updater\ScheduleUpdater
{
    /**
     * @return array{date: string, year: int, month: int, day: int}|null
     */
    public function exposedExtractDate(string $rawDate): ?array
    {
        return $this->extractDate($rawDate);
    }

    public function exposedPreloadTeamNameMap(): void
    {
        $reflection = new \ReflectionMethod(parent::class, 'preloadTeamNameMap');
        $reflection->invoke($this);
    }

    public function exposedInsertPlayoffGamesFromScheduleHtm(): string
    {
        $method = new \ReflectionMethod(parent::class, 'insertPlayoffGamesFromScheduleHtm');
        /** @var string */
        return $method->invoke($this);
    }

    /** @return array<string, int> */
    public function getTeamNameToIdMap(): array
    {
        $prop = new \ReflectionProperty(parent::class, 'teamNameToIdMap');
        /** @var array<string, int> */
        return $prop->getValue($this);
    }

    /** @param array<string, int> $map */
    public function setTeamNameToIdMap(array $map): void
    {
        $prop = new \ReflectionProperty(parent::class, 'teamNameToIdMap');
        $prop->setValue($this, $map);
    }
}

/**
 * Stream wrapper whose files exist (url_stat succeeds) but cannot be opened
 * (stream_open fails), so file_get_contents() returns false deterministically.
 */
final class UnreadableScheduleHtmStreamWrapper
{
    public const SCHEME = 'schunreadable';

    /** @var resource|null */
    public $context;

    /** @return array<string, int> */
    public function url_stat(string $path, int $flags): array
    {
        return ['mode' => 0100644, 'size' => 1];
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return false;
    }
}
