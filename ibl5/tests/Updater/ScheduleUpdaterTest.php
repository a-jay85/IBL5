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

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    private function createUpdater(
        string $phase = 'Regular Season',
        int $endingYear = 2025,
        bool $olympics = false,
        ?JsbSourceResolverInterface $sourceResolver = null,
    ): TestableScheduleUpdater {
        $season = self::createStub(Season::class);
        $season->endingYear = $endingYear;
        $season->beginningYear = $endingYear - 1;
        $season->phase = $phase;

        $leagueContext = self::createStub(LeagueContext::class);
        $leagueContext->method('getCurrentLeague')->willReturn($olympics ? 'olympics' : 'IBL');
        $leagueContext->method('isOlympics')->willReturn($olympics);
        $leagueContext->method('getTableName')->willReturnCallback(
            static fn (string $table): string => $olympics
                ? str_replace('ibl_', 'ibl_olympics_', $table)
                : $table,
        );

        return new TestableScheduleUpdater($this->mockDb, $season, $leagueContext, $sourceResolver);
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
    private function createUpdaterForFullRun(array $teams, array $games): TestableScheduleUpdater
    {
        $this->mockDb->setMockData($teams);
        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')->willReturn($this->buildSchBytes($games));

        return $this->createUpdater(sourceResolver: $resolver);
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
        $this->assertNotEmpty($matched);
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

    /** @return array<string, int> */
    public function getTeamNameToIdMap(): array
    {
        $prop = new \ReflectionProperty(parent::class, 'teamNameToIdMap');
        /** @var array<string, int> */
        return $prop->getValue($this);
    }
}
