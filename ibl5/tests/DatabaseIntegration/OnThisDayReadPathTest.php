<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use League\LeagueContext;
use OnThisDay\MomentKind;
use OnThisDay\OnThisDayFactory;
use OnThisDay\OnThisDayRepository;
use PHPUnit\Framework\Attributes\Group;

/**
 * The homepage read path reads only the precomputed ibl_on_this_day_moments
 * table. RecordingMysqli wraps the test connection and records every statement,
 * so these tests prove the request never touches ibl_box_scores and that a
 * missing table degrades to an empty selection instead of a fatal.
 *
 * Fixture: sims 900101 and 900102 (end dates 2031-03-10 and 2031-03-17), and
 * three stored fifty-point moments in seasons 2029, 2030 and 2031 (current).
 */
#[Group('database')]
class OnThisDayReadPathTest extends DatabaseTestCase
{
    private const PID = 200_930_001;

    protected function setUp(): void
    {
        parent::setUp();

        $this->execSql(
            "INSERT INTO `ibl_settings` (setting_key, setting_value, league)
             VALUES ('Current Season Phase', 'Regular Season', 'ibl')
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $this->execSql('DELETE FROM `ibl_on_this_day_moments`');

        $this->insertRow('ibl_sim_dates', [
            'sim' => 900101,
            'start_date' => '2031-03-04',
            'end_date' => '2031-03-10',
        ]);
        $this->insertRow('ibl_sim_dates', [
            'sim' => 900102,
            'start_date' => '2031-03-11',
            'end_date' => '2031-03-17',
        ]);

        $this->insertMoment('2029-03-17', 2029);
        $this->insertMoment('2030-03-18', 2030);
        $this->insertMoment('2031-03-17', 2031);
    }

    public function testHomepageReadPathNeverQueriesBoxScores(): void
    {
        $recording = new RecordingMysqli($this->db);

        $selection = OnThisDayFactory::createService($recording)->getMomentsForSim();

        self::assertFalse($selection->isEmpty());

        $boxScoreStatements = array_filter(
            $recording->statements,
            static fn (string $sql): bool => str_contains($sql, 'ibl_box_scores'),
        );
        self::assertSame([], array_values($boxScoreStatements));

        $momentStatements = count(array_filter(
            $recording->statements,
            static fn (string $sql): bool => str_contains($sql, 'ibl_on_this_day_moments'),
        ));
        self::assertGreaterThanOrEqual(1, $momentStatements);
        self::assertLessThanOrEqual(8, $momentStatements);
    }

    public function testCurrentSeasonMomentIsNotShown(): void
    {
        $selection = OnThisDayFactory::createService($this->db)->getMomentsForSim();

        $seasons = array_map(static fn ($moment): int => $moment->seasonYear, $selection->moments);

        self::assertNotSame([], $seasons);
        self::assertNotContains(2031, $seasons);
    }

    public function testEmptyTableYieldsEmptySelection(): void
    {
        $this->execSql('DELETE FROM `ibl_on_this_day_moments`');

        $selection = OnThisDayFactory::createService($this->db)->getMomentsForSim();

        self::assertTrue($selection->isEmpty());
        self::assertSame(7, $selection->radiusUsed);
    }

    public function testMissingTableReturnsNoMoments(): void
    {
        $recording = new RecordingMysqli($this->db, 1146);

        $moments = (new OnThisDayRepository($recording))->findMoments(['03-17'], 2031);

        self::assertSame([], $moments);
        self::assertNotSame([], $recording->statements);
        foreach ($recording->statements as $sql) {
            self::assertStringNotContainsString('ibl_box_scores', $sql);
        }
    }

    public function testOtherSqlErrorsPropagate(): void
    {
        $repository = new OnThisDayRepository(new RecordingMysqli($this->db, 1064));

        $this->expectException(\mysqli_sql_exception::class);

        $repository->findMoments(['03-17'], 2031);
    }

    public function testOlympicsContextReturnsNoMoments(): void
    {
        $context = new LeagueContext();
        $context->setLeague(LeagueContext::LEAGUE_OLYMPICS);
        $recording = new RecordingMysqli($this->db);

        $moments = (new OnThisDayRepository($recording, $context))->findMoments(['03-17'], 2031);

        self::assertSame([], $moments);
        self::assertSame([], $recording->statements);
    }

    public function testMalformedRowIsSkipped(): void
    {
        $this->insertRow('ibl_on_this_day_moments', [
            'moment_key' => 'bogus:2028-03-17:' . self::PID,
            'kind' => 'bogus',
            'game_date' => '2028-03-17',
            'month_day' => '03-17',
            'season_year' => 2028,
            'pid' => self::PID,
            'player_name' => 'Bogus Kind',
            'facts_json' => '{"points":50}',
        ]);

        $moments = (new OnThisDayRepository($this->db))->findMoments(['03-17'], 2031);

        self::assertCount(1, $moments);
        self::assertSame(2029, $moments[0]->seasonYear);
        self::assertSame(MomentKind::FiftyPointGame, $moments[0]->kind);
    }

    public function testMonthDayListAbove15Throws(): void
    {
        $monthDays = array_fill(0, 16, '03-17');

        $this->expectException(\InvalidArgumentException::class);

        (new OnThisDayRepository($this->db))->findMoments($monthDays, 2031);
    }

    private function insertMoment(string $date, int $seasonYear): void
    {
        $this->insertRow('ibl_on_this_day_moments', [
            'moment_key' => MomentKind::FiftyPointGame->value . ':' . $date . ':' . self::PID,
            'kind' => MomentKind::FiftyPointGame->value,
            'game_date' => $date,
            'month_day' => substr($date, 5, 5),
            'season_year' => $seasonYear,
            'pid' => self::PID,
            'player_name' => 'Read Path Scorer',
            'teamid' => 1,
            'team_name' => 'Home Team',
            'opponent_teamid' => 2,
            'opponent_name' => 'Away Team',
            'facts_json' => '{"points":50,"rebounds":5,"assists":5,"steals":1,"blocks":0}',
        ]);
    }

    private function execSql(string $sql): void
    {
        self::assertTrue($this->db->query($sql), 'Fixture query failed: ' . $this->db->error);
    }
}
