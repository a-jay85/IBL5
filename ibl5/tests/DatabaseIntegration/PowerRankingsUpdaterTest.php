<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use Season\Season;
use Updater\PowerRankingsUpdater;

/**
 * Pins deterministic same-date tie ordering of the two raw schedule fetches in
 * PowerRankingsUpdater. Both methods are private; ReflectionMethod exercises the
 * ORDER BY directly instead of through the full update() run. Also pins the SOS store
 * (calculateAndStoreSos): the four stored ibl_power columns and the echoed text.
 */
#[Group('database')]
final class PowerRankingsUpdaterTest extends DatabaseTestCase
{
    /** The `<pre>` body DebugOutput renders for the seedSosSlice() fixture (captured from a run). */
    private const EXPECTED_SOS_LOG = 'teamid 1: SOS=0.45 (rank 4), RSOS=0.3 (rank 4)<br>teamid 2: SOS=0.533 (rank 2), RSOS=0.8 (rank 1)<br>teamid 3: SOS=0.467 (rank 3), RSOS=0.4 (rank 3)<br>teamid 4: SOS=0 (rank 5), RSOS=0.55 (rank 2)<br>teamid 5: SOS=0.6 (rank 1), RSOS=0 (rank 5)<br>';

    private PowerRankingsUpdater $updater;

    protected function setUp(): void
    {
        parent::setUp();

        // Season\Season is class-aliased to the mock in PHPUnit runs; the years are set explicitly.
        $season = new Season($this->db);
        $season->beginningYear = 2098;
        $season->endingYear = 2099;

        $this->updater = new PowerRankingsUpdater($this->db, $season);

        // Clear the query window inside the transaction; tearDown rolls this back.
        $this->db->query("DELETE FROM ibl_schedule WHERE game_date BETWEEN '2098-11-01' AND '2099-05-30'");
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['authService']);
        parent::tearDown();
    }

    public function testFetchAllPlayedGamesBreaksSameDateTiesByScheduleIdAscending(): void
    {
        $this->insertScheduleRow(2099, '2099-01-15', 5, 101, 2, 90);
        $this->insertScheduleRow(2099, '2099-01-15', 3, 101, 4, 90);
        $this->insertScheduleRow(2099, '2099-01-15', 1, 101, 6, 90);
        $this->insertScheduleRow(2099, '2099-01-14', 7, 101, 8, 90);
        // Unplayed (0/0) row: the score filter must exclude it.
        $this->insertScheduleRow(2099, '2099-01-16', 9, 0, 10, 0);

        $rows = $this->invokeFetch('fetchAllPlayedGames');

        // Date is primary; the three same-date games follow schedule id.
        self::assertSame([7, 5, 3, 1], array_column($rows, 'visitor_teamid'));
    }

    public function testFetchAllUnplayedGamesBreaksSameDateTiesByScheduleIdAscending(): void
    {
        $this->insertScheduleRow(2099, '2099-01-15', 5, 0, 2, 0);
        $this->insertScheduleRow(2099, '2099-01-15', 3, 0, 4, 0);
        $this->insertScheduleRow(2099, '2099-01-15', 1, 0, 6, 0);
        $this->insertScheduleRow(2099, '2099-01-14', 7, 0, 8, 0);

        $rows = $this->invokeFetch('fetchAllUnplayedGames');

        self::assertSame([7, 5, 3, 1], array_column($rows, 'visitor_teamid'));
    }

    public function testCalculateAndStoreSosWritesSosAndRanksForStandingsTeams(): void
    {
        $this->invokeSos($this->seedSosSlice());

        $expected = [
            1 => ['sos' => 0.45, 'remaining_sos' => 0.3, 'sos_rank' => 4, 'remaining_sos_rank' => 4],
            2 => ['sos' => 0.533, 'remaining_sos' => 0.8, 'sos_rank' => 2, 'remaining_sos_rank' => 1],
            3 => ['sos' => 0.467, 'remaining_sos' => 0.4, 'sos_rank' => 3, 'remaining_sos_rank' => 3],
        ];

        foreach ($expected as $teamid => $values) {
            $row = $this->fetchPowerRow($teamid);
            self::assertNotNull($row, "team {$teamid} power row");
            self::assertSame($values['sos'], (float) $row['sos'], "team {$teamid} sos");
            self::assertSame($values['remaining_sos'], (float) $row['remaining_sos'], "team {$teamid} remaining_sos");
            self::assertSame($values['sos_rank'], (int) $row['sos_rank'], "team {$teamid} sos_rank");
            self::assertSame($values['remaining_sos_rank'], (int) $row['remaining_sos_rank'], "team {$teamid} remaining_sos_rank");
        }
    }

    public function testCalculateAndStoreSosScoresZeroGameTeamAndSkipsTeamWithoutPowerRow(): void
    {
        $this->invokeSos($this->seedSosSlice());

        $zeroGameTeam = $this->fetchPowerRow(4);
        self::assertNotNull($zeroGameTeam);
        self::assertSame(0.0, (float) $zeroGameTeam['sos']);
        self::assertSame(5, (int) $zeroGameTeam['sos_rank']);
        self::assertSame(0.55, (float) $zeroGameTeam['remaining_sos']);
        self::assertSame(2, (int) $zeroGameTeam['remaining_sos_rank']);

        // Team 5 is in standings but has no ibl_power row: the UPDATE is a silent no-op.
        self::assertNull($this->fetchPowerRow(5));
        $result = $this->db->query('SELECT COUNT(*) AS cnt FROM ibl_power');
        self::assertNotFalse($result);
        $countRow = $result->fetch_assoc();
        self::assertIsArray($countRow);
        self::assertSame(4, (int) $countRow['cnt']);
    }

    public function testCalculateAndStoreSosOutputTextIsUnchanged(): void
    {
        $authStub = self::createStub(\Auth\AuthService::class);
        $authStub->method('isAdmin')->willReturn(true);
        $GLOBALS['authService'] = $authStub;

        $played = $this->seedSosSlice();

        ob_start();
        $this->invokeSos($played);
        $out = (string) ob_get_clean();

        self::assertTrue(str_starts_with($out, '<p>Calculating Strength of Schedule...<p>'));
        self::assertTrue(str_ends_with($out, '<p>Strength of Schedule values have been calculated and stored.<p>'));
        self::assertStringContainsString('Strength of Schedule Update Log', $out);

        // DebugOutput::$debugId is a process-wide static, so match the panel id with \d+.
        self::assertSame(
            1,
            preg_match('#<pre id="debugContent\d+" class="debug-panel__content" hidden>(.*?)</pre>#s', $out, $m)
        );
        self::assertSame(self::EXPECTED_SOS_LOG, $m[1]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function invokeFetch(string $method): array
    {
        /** @var list<array<string, mixed>> */
        return (new \ReflectionMethod($this->updater, $method))->invoke($this->updater, 11);
    }

    /**
     * Seed a five-team standings slice and return the played games the method receives as an argument.
     *
     * @return list<array{visitor_teamid: int, visitor_score: int, home_teamid: int, home_score: int}>
     */
    private function seedSosSlice(): array
    {
        // The method reads ibl_standings unfiltered; clear seed rows so they stay out of the ranking pool.
        $this->db->query('DELETE FROM ibl_standings');
        $this->db->query('DELETE FROM ibl_power');

        $records = [1 => [8, 2], 2 => [6, 4], 3 => [3, 7], 4 => [0, 0], 5 => [5, 5]];
        foreach ($records as $teamid => [$wins, $losses]) {
            $this->insertRow('ibl_standings', [
                'teamid' => $teamid,
                'team_name' => "SOS T{$teamid}",
                'wins' => $wins,
                'losses' => $losses,
            ]);
        }

        // Team 5 deliberately has no ibl_power row. Sentinels prove the UPDATE overwrote the rest.
        foreach ([1, 2, 3, 4] as $teamid) {
            $this->insertRow('ibl_power', [
                'teamid' => $teamid,
                'last_win' => 0,
                'last_loss' => 0,
                'streak' => 0,
                'sos' => 0.999,
                'remaining_sos' => 0.999,
                'sos_rank' => 99,
                'remaining_sos_rank' => 99,
            ]);
        }

        $this->insertScheduleRow(2099, '2099-02-01', 1, 0, 4, 0);
        $this->insertScheduleRow(2099, '2099-02-02', 2, 0, 1, 0);
        $this->insertScheduleRow(2099, '2099-02-03', 4, 0, 3, 0);
        $this->insertScheduleRow(2099, '2099-02-04', 3, 0, 1, 0);
        // Outside the window (after 2099-05-30): must be excluded.
        $this->insertScheduleRow(2099, '2099-06-15', 2, 0, 3, 0);
        // Played row in the DB, in window: the score = 0 filter must exclude it from remaining SOS.
        $this->insertScheduleRow(2099, '2099-01-20', 2, 100, 4, 90);

        return [
            ['visitor_teamid' => 1, 'visitor_score' => 101, 'home_teamid' => 2, 'home_score' => 90],
            ['visitor_teamid' => 3, 'visitor_score' => 101, 'home_teamid' => 1, 'home_score' => 90],
            ['visitor_teamid' => 2, 'visitor_score' => 101, 'home_teamid' => 3, 'home_score' => 90],
            // Opponent 6 has no standings row.
            ['visitor_teamid' => 3, 'visitor_score' => 101, 'home_teamid' => 6, 'home_score' => 90],
            ['visitor_teamid' => 5, 'visitor_score' => 101, 'home_teamid' => 2, 'home_score' => 90],
        ];
    }

    /**
     * @param list<array{visitor_teamid: int, visitor_score: int, home_teamid: int, home_score: int}> $playedGames
     */
    private function invokeSos(array $playedGames): void
    {
        (new \ReflectionMethod($this->updater, 'calculateAndStoreSos'))->invoke($this->updater, $playedGames, 11);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPowerRow(int $teamid): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT sos, remaining_sos, sos_rank, remaining_sos_rank FROM ibl_power WHERE teamid = ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $teamid);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row;
    }
}
