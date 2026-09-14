<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use FreeAgency\DayAlreadyProcessedException;
use FreeAgency\FreeAgencyAdminRepository;
use League\LeagueContext;

/**
 * Verifies the FA-admin signing orchestration: ibl_plr mutation and
 * nuke_stories insertion via executeSigningsTransactionally().
 *
 * Design choice: tests call FreeAgencyAdminRepository::executeSigningsTransactionally()
 * directly rather than going through FreeAgencyAdminProcessor::executeSignings().
 * The processor adds only logging and an empty-array guard on top; the
 * orchestration logic (signing loop, MLE/LLE marking, news-gating) lives
 * entirely in the repository method. Calling the repository layer exercises
 * every reachable branch without requiring Processor deps (Team, Player
 * facade loads, Logger bootstrap).
 *
 * BaseMysqliRepository::transactional() is savepoint-safe when already inside
 * a transaction (classes/BaseMysqliRepository.php:509–514), so the test's
 * outer DatabaseTestCase transaction is preserved and rolls back cleanly.
 *
 * Key sources:
 *   classes/FreeAgency/FreeAgencyAdminRepository.php:165  — executeSigningsTransactionally
 *   classes/FreeAgency/FreeAgencyAdminRepository.php:73   — updatePlayerContract
 *   classes/FreeAgency/FreeAgencyAdminRepository.php:138  — insertNewsStory
 *   classes/BaseMysqliRepository.php:509                  — transactional() savepoint guard
 *   tests/DatabaseIntegration/DatabaseTestCase.php:213    — insertTestPlayer helper
 */
#[Group('database')]
class FreeAgencyAdminAssignTest extends DatabaseTestCase
{
    private FreeAgencyAdminRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new FreeAgencyAdminRepository($this->db);
    }

    /**
     * Happy-path: a single signing updates ibl_plr (teamid, fa_signing_flag,
     * salary columns, cy=0) AND inserts a nuke_stories row when homeText/bodyText
     * are non-empty.
     */
    public function testExecuteSigningsTransactionallyUpdatesPlayerAndInsertsNews(): void
    {
        $pid = 900001;
        $this->insertTestPlayer($pid, 'FA Assign Player', [
            'teamid' => 0,
            'cy' => 5,
            'cyt' => 0,
            'salary_yr1' => 0,
            'fa_signing_flag' => 0,
        ]);

        $signings = [
            [
                'playerId' => $pid,
                'teamId' => 1,
                'offerYears' => 3,
                'offers' => [
                    'offer1' => 1500,
                    'offer2' => 1600,
                    'offer3' => 1700,
                    'offer4' => 0,
                    'offer5' => 0,
                    'offer6' => 0,
                ],
                'usedMle' => false,
                'usedLle' => false,
                'teamName' => 'Metros',
            ],
        ];

        $newsTitle = '2006 IBL Free Agency, Days 1-2';

        $counts = $this->repo->executeSigningsTransactionally(
            2,
            $signings,
            $newsTitle,
            'Player accepted a deal.',
            'Full body of the story.'
        );

        // Both the player update and the news insert count as successes
        self::assertSame(2, $counts['successCount']);
        self::assertSame(0, $counts['errorCount']);
        self::assertGreaterThan(0, $counts['newsSid'], 'newsSid must be positive when a story is inserted');

        // (a) ibl_plr mutation
        $stmt = $this->db->prepare('SELECT teamid, fa_signing_flag, cy, cyt, salary_yr1, salary_yr2, salary_yr3 FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(1, (int) $row['teamid']);
        self::assertSame(1, (int) $row['fa_signing_flag']);
        self::assertSame(0, (int) $row['cy']);
        self::assertSame(3, (int) $row['cyt']);
        self::assertSame(1500, (int) $row['salary_yr1']);
        self::assertSame(1600, (int) $row['salary_yr2']);
        self::assertSame(1700, (int) $row['salary_yr3']);

        // (b) nuke_stories row
        $stmt = $this->db->prepare('SELECT title, hometext FROM nuke_stories WHERE title = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $newsTitle);
        $stmt->execute();
        $news = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($news);
        self::assertSame($newsTitle, $news['title']);
        self::assertSame('Player accepted a deal.', $news['hometext']);
    }

    /**
     * News-gating guard: when homeText or bodyText is empty, no nuke_stories row
     * is inserted even though the player contract update succeeds. Kills the
     * mutant that would always call insertNewsStory().
     *
     * @see classes/FreeAgency/FreeAgencyAdminRepository.php:203
     */
    public function testExecuteSigningsTransactionallySkipsNewsWhenTextsAreEmpty(): void
    {
        $pid = 900002;
        $this->insertTestPlayer($pid, 'FA NoNews Player', [
            'teamid' => 0,
            'cy' => 0,
            'cyt' => 0,
            'salary_yr1' => 0,
        ]);

        $signings = [
            [
                'playerId' => $pid,
                'teamId' => 2,
                'offerYears' => 1,
                'offers' => [
                    'offer1' => 800,
                    'offer2' => 0,
                    'offer3' => 0,
                    'offer4' => 0,
                    'offer5' => 0,
                    'offer6' => 0,
                ],
                'usedMle' => false,
                'usedLle' => false,
                'teamName' => 'Stars',
            ],
        ];

        $counts = $this->repo->executeSigningsTransactionally(
            4,
            $signings,
            '2006 IBL Free Agency, Days 3-4',
            '',   // empty homeText → news-insert gated out
            ''    // empty bodyText
        );

        // Only the player update contributes to successCount (news not attempted)
        self::assertSame(1, $counts['successCount']);
        self::assertSame(0, $counts['errorCount']);
        self::assertSame(0, $counts['newsSid'], 'newsSid must be 0 when news texts are empty');

        // Player was updated
        $stmt = $this->db->prepare('SELECT teamid FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(2, (int) $row['teamid']);
    }

    /**
     * Empty signings list: no ibl_plr mutation, no news insert, zero counts.
     * Kills the mutant that would skip the empty-array check in executeSignings()
     * (FreeAgencyAdminProcessor). At the repository level this confirms
     * executeSigningsTransactionally handles an empty list gracefully.
     */
    public function testExecuteSigningsTransactionallyWithNoSigningsReturnsZeroCounts(): void
    {
        $counts = $this->repo->executeSigningsTransactionally(
            6,
            [],
            '2006 IBL Free Agency, Days 5-6',
            'Some home text.',
            'Some body text.'
        );

        // No signings → successCount=0, news-insert gated (successCount was 0)
        self::assertSame(0, $counts['successCount']);
        self::assertSame(0, $counts['errorCount']);
        self::assertSame(0, $counts['newsSid'], 'newsSid must be 0 when there are no signings');
    }

    /**
     * Matrix row 3 - immediate replay. Executing the identical payload twice leaves
     * exactly one marker row, exactly one nuke_stories row, and has_mle/has_lle
     * decremented exactly once.
     *
     * The repository throws DayAlreadyProcessedException; the processor-level
     * translation to ['success' => false, ...] is covered by the unit test
     * testExecuteSigningsReturnsBlockedResultWhenDayAlreadyProcessed(), which keeps
     * this test free of the Processor's Team/Player/Logger bootstrap.
     *
     * Mutation killed: reducing the composite PRIMARY KEY in migration 176 to a
     * surrogate id - the second insert would then succeed and every count below doubles.
     */
    public function testRunningTheSameDayTwiceWritesOneMarkerAndOneNewsStory(): void
    {
        $day = 12;
        $pid = 900010;
        $teamName = 'Metros';
        $this->insertTestPlayer($pid, 'FA Replay Player', [
            'teamid' => 0,
            'cy' => 5,
            'cyt' => 0,
            'salary_yr1' => 0,
            'fa_signing_flag' => 0,
        ]);
        $this->seedMleLle($teamName, 1, 1);

        $newsTitle = '2006 IBL Free Agency, Days 11-12';
        $signings = [$this->signingPayload($pid, 1, $teamName, true, true)];

        $first = $this->repo->executeSigningsTransactionally(
            $day,
            $signings,
            $newsTitle,
            'Player accepted a deal.',
            'Full body of the story.'
        );
        self::assertSame(0, $first['errorCount']);
        self::assertGreaterThan(0, $first['newsSid']);

        self::assertSame(0, $this->readMleLle($teamName)['has_mle'], 'first run must consume the MLE');
        self::assertSame(0, $this->readMleLle($teamName)['has_lle'], 'first run must consume the LLE');

        // Re-play the identical payload.
        try {
            $this->repo->executeSigningsTransactionally(
                $day,
                $signings,
                $newsTitle,
                'Player accepted a deal.',
                'Full body of the story.'
            );
            self::fail('The second run must be rejected by the marker PRIMARY KEY.');
        } catch (DayAlreadyProcessedException $e) {
            self::assertStringContainsString('already been processed', $e->getMessage());
        }

        self::assertSame(1, $this->countMarkers($day), 'exactly one marker row for the triple');
        self::assertSame(1, $this->countStories($newsTitle), 'exactly one news story');
        self::assertSame(0, $this->readMleLle($teamName)['has_mle'], 'MLE must not be decremented twice');
        self::assertSame(0, $this->readMleLle($teamName)['has_lle'], 'LLE must not be decremented twice');
    }

    /**
     * Matrix row 5 (season half) - a marker for one season-ending year does not block
     * the same day in the next cycle.
     *
     * Mutation killed: dropping season_ending_year from the composite PRIMARY KEY, or
     * from the getDayProcessedMarker() WHERE clause.
     */
    public function testMarkerForOneSeasonDoesNotBlockTheSameDayInAnotherSeason(): void
    {
        $day = 12;
        $year = $this->currentSeasonEndingYear();

        $this->insertMarker(LeagueContext::LEAGUE_IBL, $year, $day);
        self::assertNotNull($this->repo->getDayProcessedMarker($day), 'marker is visible in its own season');

        $this->setSeasonEndingYear($year + 1);
        self::assertNull(
            $this->repo->getDayProcessedMarker($day),
            'a marker from the previous cycle must be invisible in the new one'
        );

        // And the day genuinely re-executes under the new key.
        $pid = 900011;
        $this->insertTestPlayer($pid, 'FA NextCycle Player', [
            'teamid' => 0,
            'cy' => 5,
            'cyt' => 0,
            'salary_yr1' => 0,
            'fa_signing_flag' => 0,
        ]);

        $counts = $this->repo->executeSigningsTransactionally(
            $day,
            [$this->signingPayload($pid, 1, 'Metros')],
            '2006 IBL Free Agency, Days 11-12 (next cycle)',
            'Player accepted a deal.',
            'Full body of the story.'
        );
        self::assertSame(0, $counts['errorCount']);
        self::assertNotNull($this->repo->getDayProcessedMarker($day));
    }

    /**
     * Matrix row 5 (league half) - an ibl marker is invisible to olympics.
     *
     * D1 payoff: LeagueContext::setLeague() sets the in-memory value only, with no
     * superglobal or cookie write, so this does not leak into sibling tests.
     * Neither ibl_settings nor ibl_fa_days_processed is in LeagueContext::TABLE_MAP,
     * so both are scoped by column, not by table rewrite.
     *
     * Mutation killed: resolveLeague() hardcoding LEAGUE_IBL instead of reading
     * $this->leagueContext, or league being dropped from the PRIMARY KEY.
     */
    public function testMarkerForIblDoesNotBlockOlympics(): void
    {
        $day = 12;
        $year = $this->currentSeasonEndingYear();
        $this->seedSeasonEndingYear('olympics', $year);
        $this->insertMarker(LeagueContext::LEAGUE_IBL, $year, $day);

        self::assertNotNull($this->repo->getDayProcessedMarker($day), 'ibl sees its own marker');

        $olympicsContext = new LeagueContext();
        $olympicsContext->setLeague('olympics');
        $olympicsRepo = new FreeAgencyAdminRepository($this->db, $olympicsContext);

        self::assertNull(
            $olympicsRepo->getDayProcessedMarker($day),
            'the ibl marker must be invisible to olympics'
        );
    }

    /**
     * Matrix row 11 - D8 fail-closed boundary. With the season setting absent the run
     * aborts and the transaction rolls back: no marker row, no news story.
     *
     * Mutation killed: defaulting getSeasonEndingYear() to 0 instead of throwing, which
     * would write a marker under a key no later run reproduces.
     */
    public function testExecutionAbortsWhenSeasonSettingIsMissing(): void
    {
        $day = 12;
        $year = $this->currentSeasonEndingYear();
        $pid = 900012;
        $this->insertTestPlayer($pid, 'FA NoSetting Player', [
            'teamid' => 0,
            'cy' => 5,
            'cyt' => 0,
            'salary_yr1' => 0,
            'fa_signing_flag' => 0,
        ]);

        $key = 'Current Season Ending Year';
        $league = LeagueContext::LEAGUE_IBL;
        $stmt = $this->db->prepare("DELETE FROM `ibl_settings` WHERE setting_key = ? AND league = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('ss', $key, $league);
        $stmt->execute();
        $stmt->close();

        $newsTitle = '2006 IBL Free Agency, Days 11-12 (no setting)';

        try {
            $this->repo->executeSigningsTransactionally(
                $day,
                [$this->signingPayload($pid, 1, 'Metros')],
                $newsTitle,
                'Player accepted a deal.',
                'Full body of the story.'
            );
            self::fail('A missing season setting must abort the run (D8, fail closed).');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Current Season Ending Year', $e->getMessage());
        }

        self::assertSame(0, $this->countMarkersFor($league, $year, $day), 'no marker may be written');
        self::assertSame(0, $this->countStories($newsTitle), 'no news story may be written');
    }

    // ---- helpers -------------------------------------------------------------

    /**
     * @return array{playerId: int, teamId: int, teamName: string, offers: array{offer1: int, offer2: int, offer3: int, offer4: int, offer5: int, offer6: int}, offerYears: int, usedMle: bool, usedLle: bool}
     */
    private function signingPayload(
        int $pid,
        int $teamId,
        string $teamName,
        bool $usedMle = false,
        bool $usedLle = false
    ): array {
        return [
            'playerId' => $pid,
            'teamId' => $teamId,
            'offerYears' => 3,
            'offers' => [
                'offer1' => 1500,
                'offer2' => 1600,
                'offer3' => 1700,
                'offer4' => 0,
                'offer5' => 0,
                'offer6' => 0,
            ],
            'usedMle' => $usedMle,
            'usedLle' => $usedLle,
            'teamName' => $teamName,
        ];
    }

    private function currentSeasonEndingYear(): int
    {
        $key = 'Current Season Ending Year';
        $league = LeagueContext::LEAGUE_IBL;
        $stmt = $this->db->prepare("SELECT value FROM `ibl_settings` WHERE setting_key = ? AND league = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('ss', $key, $league);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row, "the test database must carry a '{$key}' row for '{$league}'");

        return (int) $row['value'];
    }

    private function setSeasonEndingYear(int $year): void
    {
        $value = (string) $year;
        $key = 'Current Season Ending Year';
        $league = LeagueContext::LEAGUE_IBL;
        $stmt = $this->db->prepare("UPDATE `ibl_settings` SET value = ? WHERE setting_key = ? AND league = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('sss', $value, $key, $league);
        $stmt->execute();
        $stmt->close();
    }

    private function seedSeasonEndingYear(string $league, int $year): void
    {
        $key = 'Current Season Ending Year';
        $value = (string) $year;
        $stmt = $this->db->prepare(
            "INSERT INTO `ibl_settings` (setting_key, value, league) VALUES (?, ?, ?)"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('sss', $key, $value, $league);
        $stmt->execute();
        $stmt->close();
    }

    private function seedMleLle(string $teamName, int $hasMle, int $hasLle): void
    {
        $stmt = $this->db->prepare("UPDATE `ibl_team_info` SET has_mle = ?, has_lle = ? WHERE team_name = ? LIMIT 1");
        self::assertNotFalse($stmt);
        $stmt->bind_param('iis', $hasMle, $hasLle, $teamName);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * @return array{has_mle: int, has_lle: int}
     */
    private function readMleLle(string $teamName): array
    {
        $stmt = $this->db->prepare("SELECT has_mle, has_lle FROM `ibl_team_info` WHERE team_name = ? LIMIT 1");
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $teamName);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row, "team '{$teamName}' must exist in ibl_team_info");

        return ['has_mle' => (int) $row['has_mle'], 'has_lle' => (int) $row['has_lle']];
    }

    private function insertMarker(string $league, int $year, int $day): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO `ibl_fa_days_processed` (league, season_ending_year, day) VALUES (?, ?, ?)"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('sii', $league, $year, $day);
        $stmt->execute();
        $stmt->close();
    }

    private function countMarkers(int $day): int
    {
        return $this->countMarkersFor(LeagueContext::LEAGUE_IBL, $this->currentSeasonEndingYear(), $day);
    }

    private function countMarkersFor(string $league, int $year, int $day): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS c FROM `ibl_fa_days_processed`
             WHERE league = ? AND season_ending_year = ? AND day = ?"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('sii', $league, $year, $day);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['c'] ?? 0);
    }

    private function countStories(string $title): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS c FROM `nuke_stories` WHERE title = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $title);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['c'] ?? 0);
    }
}
