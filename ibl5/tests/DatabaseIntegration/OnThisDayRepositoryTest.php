<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use League\League;
use OnThisDay\Moment;
use OnThisDay\MomentKind;
use OnThisDay\OnThisDayFactory;
use OnThisDay\OnThisDayMomentBuilder;
use OnThisDay\OnThisDayRepository;
use PHPUnit\Framework\Attributes\Group;

/**
 * DB-integration tests for the rebuild round trip: box-score fixtures go in,
 * OnThisDayMomentBuilder::rebuild() stores them in ibl_on_this_day_moments, and
 * OnThisDayRepository::findMoments() reads them back.
 *
 * Fixtures sit in seasons 1890-1901 so they never collide with seed data, and
 * every test passes beforeSeasonYear explicitly. Dates outside June/Sept/Oct are
 * regular season (game_type 1); June dates are playoffs (game_type 2).
 */
#[Group('database')]
class OnThisDayRepositoryTest extends DatabaseTestCase
{
    private const PID_A = 200_910_001;

    private OnThisDayRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new OnThisDayRepository($this->db);
    }

    public function testFindsFiftyPointGameInWindow(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Fifty Scorer');
        $this->insertGame('1901-01-10', self::PID_A, 1, 2, 1, points2m: 16, ftm: 6, points3m: 4);
        $this->rebuild();

        $moments = $this->only($this->stored(['01-09', '01-10', '01-11'], 1902, MomentKind::FiftyPointGame), self::PID_A);

        self::assertCount(1, $moments);
        self::assertSame(MomentKind::FiftyPointGame, $moments[0]->kind);
        self::assertSame('fifty_point:1901-01-10:' . self::PID_A, $moments[0]->key);
        self::assertSame(50, $moments[0]->facts['points']);
        self::assertSame(1901, $moments[0]->seasonYear);
        self::assertSame(1, $moments[0]->teamId);
        self::assertSame(2, $moments[0]->opponentTeamId);
        self::assertSame('Fifty Scorer', $moments[0]->playerName);
    }

    public function testIgnoresFortyNinePointGame(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Almost Fifty');
        $this->insertGame('1901-01-10', self::PID_A, 1, 2, 1, points2m: 16, ftm: 5, points3m: 4);
        $this->rebuild();

        self::assertSame([], $this->only($this->stored(['01-10'], 1902, MomentKind::FiftyPointGame), self::PID_A));
    }

    public function testPhantomDuplicateBoxScoreRowsCountOnce(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Phantom Scorer');
        $this->insertGame('1901-01-10', self::PID_A, 1, 2, 1, points2m: 16, ftm: 8, points3m: 4);
        $this->insertGame('1901-01-10', self::PID_A, 1, 2, 1, points2m: 16, ftm: 8, points3m: 4, gameOfDay: 2);
        $this->rebuild();

        $moments = $this->only($this->stored(['01-10'], 1902, MomentKind::FiftyPointGame), self::PID_A);

        self::assertCount(1, $moments);
        self::assertSame(52, $moments[0]->facts['points']);
    }

    public function testExcludesCurrentSeasonYear(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Current Season');
        $this->insertGame('1901-01-10', self::PID_A, 1, 2, 1, points2m: 16, ftm: 11, points3m: 4);
        $this->rebuild();

        self::assertSame([], $this->only($this->stored(['01-10'], 1901, MomentKind::FiftyPointGame), self::PID_A));
    }

    public function testFindsTripleDoubleKeyedOnPidNotName(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Right Name');
        // 20 pts, 12 reb, 11 ast; the box-score name is deliberately wrong.
        $this->insertGame('1901-01-12', self::PID_A, 1, 2, 1, orb: 4, drb: 8, ast: 11, name: 'Wrong Name');
        $this->rebuild();

        $moments = $this->only($this->stored(['01-12'], 1902, MomentKind::TripleDouble), self::PID_A);

        self::assertCount(1, $moments);
        self::assertSame(MomentKind::TripleDouble, $moments[0]->kind);
        self::assertSame('Right Name', $moments[0]->playerName);
        self::assertSame(20, $moments[0]->facts['points']);
        self::assertSame(12, $moments[0]->facts['rebounds']);
        self::assertSame(11, $moments[0]->facts['assists']);
    }

    public function testRecordGameRequiresStrictlyExceedingPriorHigh(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Rebounder');
        // 1889 precedes every other box score, so it is the league's first season and
        // sets the prior high of 20. 1890-01-03 ties it, so only 01-05 is a record.
        $this->insertGame('1889-01-03', self::PID_A, 1, 2, 1, orb: 5, drb: 15);
        $this->insertGame('1890-01-03', self::PID_A, 1, 2, 1, orb: 5, drb: 15);
        $this->insertGame('1890-01-05', self::PID_A, 1, 2, 1, orb: 5, drb: 20);
        $this->insertGame('1890-01-07', self::PID_A, 1, 2, 1, orb: 5, drb: 20);
        $this->rebuild();

        $moments = array_values(array_filter(
            $this->only($this->stored(['01-03', '01-05', '01-07'], 1891, MomentKind::RecordGame), self::PID_A),
            static fn (Moment $m): bool => $m->statLabel === 'rebounds',
        ));

        self::assertCount(1, $moments);
        self::assertSame('1890-01-05', $moments[0]->date);
        self::assertSame(25, $moments[0]->facts['value']);
        self::assertSame('record_game:1890-01-05:' . self::PID_A . ':rebounds', $moments[0]->key);
    }

    public function testFirstSeasonGameIsNeverARecord(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Early Rebounder');
        // 1889 precedes every other box score, so 01-05 beats only a one-game history.
        $this->insertGame('1889-01-03', self::PID_A, 1, 2, 1, orb: 5, drb: 10);
        $this->insertGame('1889-01-05', self::PID_A, 1, 2, 1, orb: 5, drb: 20);
        $this->rebuild();

        $moments = array_values(array_filter(
            $this->only($this->stored(['01-05'], 1890, MomentKind::RecordGame), self::PID_A),
            static fn (Moment $m): bool => $m->statLabel === 'rebounds',
        ));

        self::assertSame([], $moments);
    }

    public function testSeriesClincherUsesLastPlayoffGameBetweenPair(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Playoff Guard');
        $this->insertGame('1901-06-03', self::PID_A, 1, 2, 1);
        $this->insertGame('1901-06-05', self::PID_A, 2, 1, 1);
        $this->insertGame('1901-06-07', self::PID_A, 1, 2, 1);
        $this->insertPlayoffSeriesResultRow(1901, 4, 1, 2, 'Winners', 'Losers', 4, 3);
        $this->rebuild();

        $hits = $this->season1901($this->stored(['06-07'], 1902, MomentKind::SeriesClincher));
        $misses = $this->season1901($this->stored(['06-05'], 1902, MomentKind::SeriesClincher));

        self::assertCount(1, $hits);
        self::assertSame('1901-06-07', $hits[0]->date);
        self::assertSame('series_clincher:1901-06-07:1-2', $hits[0]->key);
        self::assertSame(1, $hits[0]->teamId);
        self::assertSame(2, $hits[0]->opponentTeamId);
        self::assertSame(['round' => 4, 'wins' => 4, 'losses' => 3], $hits[0]->facts);
        self::assertSame([], $misses);
    }

    public function testTradeDetectedFromTeamChangeOfScorer(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Moved Scorer');
        // Five games at exactly 15.0 ppg for team 1, then a debut for team 2.
        foreach (['1901-01-02', '1901-01-04', '1901-01-06', '1901-01-08', '1901-01-10'] as $date) {
            $this->insertGame($date, self::PID_A, 1, 3, 1, points2m: 7, ftm: 1, points3m: 0);
        }
        $this->insertGame('1901-02-10', self::PID_A, 2, 3, 2);
        $this->rebuild();

        $moments = $this->season1901($this->stored(['02-10'], 1902, MomentKind::Trade));

        self::assertCount(1, $moments);
        self::assertSame(MomentKind::Trade, $moments[0]->kind);
        self::assertSame('trade:1901-02-10:1-2', $moments[0]->key);
        self::assertSame('1901-02-10', $moments[0]->date);
        self::assertSame('Moved Scorer', $moments[0]->playerName);
        self::assertSame(2, $moments[0]->teamId);
        self::assertSame(1, $moments[0]->opponentTeamId);
        self::assertSame($this->teamName(2), $moments[0]->teamName);
        self::assertSame($this->teamName(1), $moments[0]->opponentName);
        self::assertSame(['players_moved' => 1, 'mover_pid' => self::PID_A], $moments[0]->facts);
        self::assertSame(0, $moments[0]->pid);
    }

    public function testTradeIgnoresLowScoringMover(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Bench Mover');
        // Ten games totalling 149 points: 14.9 ppg, just under the threshold.
        foreach (range(1, 10) as $day) {
            $this->insertGame(
                sprintf('1901-01-%02d', $day * 2),
                self::PID_A,
                1,
                3,
                1,
                points2m: 7,
                ftm: $day === 1 ? 0 : 1,
                points3m: 0,
            );
        }
        $this->insertGame('1901-02-10', self::PID_A, 2, 3, 2);
        $this->rebuild();

        self::assertSame([], $this->season1901($this->stored(['02-10'], 1902, MomentKind::Trade)));
    }

    public function testRookieGameFiftyPointGameIsIgnored(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Rookie Scorer');
        // February is game_type 1, but teams 40/41 are the Rookie/Sophomore game.
        $this->insertGame(
            '1901-02-10',
            self::PID_A,
            League::ROOKIES_TEAMID,
            League::ROOKIES_TEAMID + 1,
            League::ROOKIES_TEAMID,
            points2m: 16,
            ftm: 6,
            points3m: 4,
        );
        $this->rebuild();

        self::assertSame([], $this->only($this->stored(['02-10'], 1902, MomentKind::FiftyPointGame), self::PID_A));
    }

    public function testAllStarGameIsNeverARecord(): void
    {
        $this->insertTestPlayer(self::PID_A, 'All-Star Rebounder');
        // 1889 is the first season and sets a real-game high of 20; the All-Star game beats it.
        $this->insertGame('1889-01-03', self::PID_A, 1, 2, 1, orb: 5, drb: 15);
        $this->insertGame(
            '1890-02-10',
            self::PID_A,
            League::ALL_STAR_AWAY_TEAMID,
            League::ALL_STAR_HOME_TEAMID,
            League::ALL_STAR_AWAY_TEAMID,
            orb: 10,
            drb: 30,
        );
        $this->rebuild();

        $moments = array_values(array_filter(
            $this->only($this->stored(['02-10'], 1891, MomentKind::RecordGame), self::PID_A),
            static fn (Moment $m): bool => $m->statLabel === 'rebounds',
        ));

        self::assertSame([], $moments);
    }

    public function testAllStarGameIsNeverATrade(): void
    {
        $this->insertTestPlayer(self::PID_A, 'All-Star Scorer');
        // Five 15-ppg games for team 1, then an All-Star game for team 50.
        foreach (['1901-01-02', '1901-01-04', '1901-01-06', '1901-01-08', '1901-01-10'] as $date) {
            $this->insertGame($date, self::PID_A, 1, 3, 1, points2m: 7, ftm: 1, points3m: 0);
        }
        $this->insertGame(
            '1901-02-10',
            self::PID_A,
            League::ALL_STAR_AWAY_TEAMID,
            League::ALL_STAR_HOME_TEAMID,
            League::ALL_STAR_AWAY_TEAMID,
        );
        $this->rebuild();

        self::assertSame([], $this->season1901($this->stored(['02-10'], 1902, MomentKind::Trade)));
    }

    public function testLeapDayGameMatchedOnlyByFebruary29(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Leap Scorer');
        $this->insertGame('1896-02-29', self::PID_A, 1, 2, 1, points2m: 16, ftm: 6, points3m: 4);
        $this->rebuild();

        self::assertCount(1, $this->only($this->stored(['02-29'], 1897, MomentKind::FiftyPointGame), self::PID_A));
        self::assertSame([], $this->only($this->stored(['02-28', '03-01'], 1897, MomentKind::FiftyPointGame), self::PID_A));
    }

    public function testRebuildReplacesEarlierRows(): void
    {
        $this->insertStaleMoment();

        OnThisDayFactory::createRebuilder($this->db)->rebuild();

        self::assertSame(0, $this->staleMomentCount());
    }

    public function testFailedRebuildKeepsEarlierRows(): void
    {
        $this->insertStaleMoment();
        $builder = new class ($this->db) extends OnThisDayMomentBuilder {
            /**
             * Two moments sharing one key: the second INSERT violates the unique key.
             *
             * @return list<Moment>
             */
            public function collect(): array
            {
                $moment = new Moment(
                    kind: MomentKind::FiftyPointGame,
                    key: 'fifty_point:1901-01-10:1',
                    date: '1901-01-10',
                    seasonYear: 1901,
                    pid: 1,
                    playerName: 'Duplicate Key',
                    teamId: 1,
                    teamName: 'Home',
                    opponentTeamId: 2,
                    opponentName: 'Away',
                    facts: ['points' => 50],
                );

                return [$moment, $moment];
            }
        };

        try {
            $builder->rebuild();
            self::fail('rebuild() should have thrown on the duplicate moment key');
        } catch (\RuntimeException | \mysqli_sql_exception) {
            self::assertSame(1, $this->staleMomentCount());
        }
    }

    public function testRebuildStoresMonthDayFromDate(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Month Day Scorer');
        $this->insertGame('2029-03-17', self::PID_A, 1, 2, 1, points2m: 16, ftm: 6, points3m: 4);

        OnThisDayFactory::createRebuilder($this->db)->rebuild();

        $result = $this->db->query(
            "SELECT month_day FROM `ibl_on_this_day_moments` WHERE moment_key = 'fifty_point:2029-03-17:" . self::PID_A . "'"
        );
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        self::assertIsArray($row);
        self::assertSame('03-17', $row['month_day']);
    }

    public function testRecordStreamTieIsNotARecord(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Tie Scorer');
        // 1889 is the first season (20 points). Both 1890 games score 34, above that
        // high; the later one only ties the new high, so it is not a record.
        $this->insertGame('1889-01-03', self::PID_A, 1, 2, 1);
        $this->insertGame('1890-01-05', self::PID_A, 1, 2, 1, points2m: 12);
        $this->insertGame('1890-01-07', self::PID_A, 1, 2, 1, points2m: 12);

        $this->rebuild();

        $moments = array_values(array_filter(
            $this->only($this->stored(['01-05', '01-07'], 1891, MomentKind::RecordGame), self::PID_A),
            static fn (Moment $m): bool => $m->statLabel === 'points',
        ));

        self::assertCount(1, $moments);
        self::assertSame('1890-01-05', $moments[0]->date);
        self::assertSame(34, $moments[0]->facts['value']);
    }

    public function testPhantomDuplicateGameLinksLowestOrdinal(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Phantom Linker');
        $this->insertGame('1901-01-10', self::PID_A, 1, 2, 1, points2m: 16, ftm: 8, points3m: 4, gameOfDay: 2);
        $this->insertGame('1901-01-10', self::PID_A, 1, 2, 1, points2m: 16, ftm: 8, points3m: 4, gameOfDay: 5);
        $this->insertTeamPair('1901-01-10', 2, 1, 2);
        $this->insertTeamPair('1901-01-10', 5, 1, 2);
        $this->rebuild();

        $moments = $this->only($this->stored(['01-10'], 1902, MomentKind::FiftyPointGame), self::PID_A);

        self::assertCount(1, $moments);
        self::assertSame(2, $moments[0]->gameOfThatDay);
    }

    public function testGameWithoutTeamBoxScoreHasZeroOrdinal(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Unlinked Scorer');
        $this->insertGame('1901-01-10', self::PID_A, 1, 2, 1, points2m: 16, ftm: 6, points3m: 4, gameOfDay: 3);
        $this->rebuild();

        $moments = $this->only($this->stored(['01-10'], 1902, MomentKind::FiftyPointGame), self::PID_A);

        self::assertCount(1, $moments);
        self::assertSame(0, $moments[0]->gameOfThatDay);
    }

    public function testRecordGameCarriesOrdinal(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Record Linker');
        $this->insertGame('1889-01-03', self::PID_A, 1, 2, 1, orb: 5, drb: 15);
        $this->insertGame('1890-01-05', self::PID_A, 1, 2, 1, orb: 5, drb: 20, gameOfDay: 4);
        $this->insertTeamPair('1890-01-05', 4, 1, 2);
        $this->rebuild();

        $moments = array_values(array_filter(
            $this->only($this->stored(['01-05'], 1891, MomentKind::RecordGame), self::PID_A),
            static fn (Moment $m): bool => $m->statLabel === 'rebounds',
        ));

        self::assertCount(1, $moments);
        self::assertSame(4, $moments[0]->gameOfThatDay);
    }

    public function testSeriesClincherCarriesClinchGameOrdinal(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Playoff Linker');
        $this->insertGame('1901-06-03', self::PID_A, 1, 2, 1);
        $this->insertGame('1901-06-05', self::PID_A, 2, 1, 1);
        $this->insertGame('1901-06-07', self::PID_A, 1, 2, 1);
        $this->insertPlayoffSeriesResultRow(1901, 4, 1, 2, 'Winners', 'Losers', 4, 3);
        $this->insertTeamPair('1901-06-03', 1, 1, 2);
        $this->insertTeamPair('1901-06-05', 1, 2, 1);
        $this->insertTeamPair('1901-06-07', 3, 1, 2);
        $this->rebuild();

        $hits = $this->season1901($this->stored(['06-07'], 1902, MomentKind::SeriesClincher));

        self::assertCount(1, $hits);
        self::assertSame(3, $hits[0]->gameOfThatDay);
    }

    public function testTradeCarriesDebutGameOrdinal(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Moved Linker');
        foreach (['1901-01-02', '1901-01-04', '1901-01-06', '1901-01-08', '1901-01-10'] as $date) {
            $this->insertGame($date, self::PID_A, 1, 3, 1, points2m: 7, ftm: 1, points3m: 0);
        }
        $this->insertGame('1901-02-10', self::PID_A, 2, 3, 2);
        $this->insertTeamPair('1901-01-10', 2, 3, 1);
        $this->insertTeamPair('1901-02-10', 6, 3, 2);
        $this->rebuild();

        $moments = $this->season1901($this->stored(['02-10'], 1902, MomentKind::Trade));

        self::assertCount(1, $moments);
        self::assertSame(6, $moments[0]->gameOfThatDay);
    }

    public function testTradeStoresMoverPidFact(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Moved Fact');
        foreach (['1901-01-02', '1901-01-04', '1901-01-06', '1901-01-08', '1901-01-10'] as $date) {
            $this->insertGame($date, self::PID_A, 1, 3, 1, points2m: 7, ftm: 1, points3m: 0);
        }
        $this->insertGame('1901-02-10', self::PID_A, 2, 3, 2);
        $this->rebuild();

        $moments = $this->season1901($this->stored(['02-10'], 1902, MomentKind::Trade));

        self::assertCount(1, $moments);
        self::assertSame(self::PID_A, $moments[0]->facts['mover_pid']);
        self::assertSame(0, $moments[0]->pid);
    }

    public function testRebuildStoresGameOfThatDayColumn(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Stored Ordinal');
        $this->insertGame('2029-03-17', self::PID_A, 1, 2, 1, points2m: 16, ftm: 6, points3m: 4, gameOfDay: 2);
        $this->insertTeamPair('2029-03-17', 2, 1, 2);

        OnThisDayFactory::createRebuilder($this->db)->rebuild();

        $result = $this->db->query(
            "SELECT game_of_that_day FROM `ibl_on_this_day_moments` WHERE moment_key = 'fifty_point:2029-03-17:" . self::PID_A . "'"
        );
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        self::assertIsArray($row);
        self::assertSame(2, (int) $row['game_of_that_day']);
    }

    /**
     * Seed the visitor and home team box-score rows of one game.
     */
    private function insertTeamPair(string $date, int $gameOfDay, int $visitorTid, int $homeTid): void
    {
        $this->insertTeamBoxscoreRow($date, 'Visitor', $gameOfDay, $visitorTid, $homeTid);
        $this->insertTeamBoxscoreRow($date, 'Home', $gameOfDay, $visitorTid, $homeTid);
    }

    private function rebuild(): void
    {
        OnThisDayFactory::createRebuilder($this->db)->rebuild();
    }

    /**
     * Read stored moments of one kind back through the production reader.
     *
     * @param list<string> $window
     * @return list<Moment>
     */
    private function stored(array $window, int $beforeSeasonYear, MomentKind $kind): array
    {
        return array_values(array_filter(
            $this->repo->findMoments($window, $beforeSeasonYear),
            static fn (Moment $m): bool => $m->kind === $kind,
        ));
    }

    private function insertStaleMoment(): void
    {
        $this->insertRow('ibl_on_this_day_moments', [
            'moment_key' => 'stale:2001-01-01:1',
            'kind' => MomentKind::FiftyPointGame->value,
            'game_date' => '2001-01-01',
            'month_day' => '01-01',
            'season_year' => 2001,
            'facts_json' => '{"points":50}',
        ]);
    }

    private function staleMomentCount(): int
    {
        $result = $this->db->query(
            "SELECT COUNT(*) AS n FROM `ibl_on_this_day_moments` WHERE moment_key = 'stale:2001-01-01:1'"
        );
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        self::assertIsArray($row);

        return (int) $row['n'];
    }

    /**
     * 20 points, 8 rebounds, 5 assists by default (the helper's stat line).
     */
    private function insertGame(
        string $date,
        int $pid,
        int $visitorTid,
        int $homeTid,
        int $teamId,
        int $points2m = 5,
        int $ftm = 4,
        int $points3m = 2,
        int $orb = 2,
        int $drb = 6,
        int $ast = 5,
        int $gameOfDay = 1,
        string $name = 'Box Name',
    ): void {
        $this->insertPlayerBoxscoreRow(
            $date,
            $pid,
            $name,
            'PG',
            $visitorTid,
            $homeTid,
            $teamId,
            points2m: $points2m,
            points2a: 20,
            ftm: $ftm,
            fta: 12,
            points3m: $points3m,
            points3a: 8,
            orb: $orb,
            drb: $drb,
            ast: $ast,
            overrides: ['game_of_that_day' => $gameOfDay],
        );
    }

    /**
     * @param list<Moment> $moments
     * @return list<Moment>
     */
    private function only(array $moments, int $pid): array
    {
        return array_values(array_filter($moments, static fn (Moment $m): bool => $m->pid === $pid));
    }

    /**
     * @param list<Moment> $moments
     * @return list<Moment>
     */
    private function season1901(array $moments): array
    {
        return array_values(array_filter($moments, static fn (Moment $m): bool => $m->seasonYear === 1901));
    }

    private function teamName(int $teamId): string
    {
        $stmt = $this->db->prepare("SELECT CONCAT(team_city, ' ', team_name) AS n FROM ibl_team_info WHERE teamid = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $stmt->close();

        return is_array($row) && is_string($row['n']) ? $row['n'] : '';
    }
}
