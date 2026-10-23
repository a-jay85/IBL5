<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\UpdateAllTheThings;

use FranchiseHistory\FranchiseHistoryRepository;
use HeadToHeadRecords\HeadToHeadRecordsRepository;
use League\League;
use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Updater\Steps\RefreshTeamSeasonRecordsStep;

/**
 * NULL-quarter games count in every record SQL expression.
 *
 * A game whose quarter and OT columns are all NULL (the restored 2004 Suns at Heat
 * game) must still give one team a win and the other a loss. Each side's total
 * falls back to its own row's calc_points: the visitor row carries the lower id,
 * the home row the higher id. The seeds give the two rows different calc_points
 * (108 and 136), so a fallback that reads the same row for both sides ties the
 * game and fails these assertions.
 *
 * Never call execute() in this file. DatabaseTestCase wraps each test in
 * begin_transaction()/rollback(), and execute() would commit that outer
 * transaction. Every rebuild goes through rebuildWithinCurrentTransaction().
 * Every assertion is a delta against a baseline taken inside the same test.
 */
#[Group('database')]
final class NullQuarterGameFallbackTest extends DatabaseTestCase
{
    private const REGULAR_DATE = '2098-11-09';
    private const REGULAR_YEAR = 2099;
    private const HEAT_DATE = '2098-10-20';
    private const HEAT_YEAR = 2098;
    private const H2H_DATE = '1901-03-15';
    private const H2H_VISITOR = 17;
    private const H2H_HOME = 22;

    public function testNullQuarterGameCountsHomeWinFromCalcPoints(): void
    {
        [$v, $h] = $this->realTeams(2);
        $this->assertTripleEmpty(self::REGULAR_DATE, $v, $h);

        $this->rebuild();
        $beforeV = $this->record($v, self::REGULAR_YEAR, 1);
        $beforeH = $this->record($h, self::REGULAR_YEAR, 1);

        $this->seedNullQuarterGame(self::REGULAR_DATE, 3, $v, $h, 45, 55);
        $this->rebuild();

        $afterV = $this->record($v, self::REGULAR_YEAR, 1);
        $afterH = $this->record($h, self::REGULAR_YEAR, 1);

        self::assertSame(1, $afterH['wins'] - $beforeH['wins'], 'home wins delta');
        self::assertSame(0, $afterH['losses'] - $beforeH['losses'], 'home losses delta');
        self::assertSame(0, $afterV['wins'] - $beforeV['wins'], 'visitor wins delta');
        self::assertSame(1, $afterV['losses'] - $beforeV['losses'], 'visitor losses delta');
    }

    public function testNullQuarterGameCountsVisitorWinFromCalcPoints(): void
    {
        [$v, $h] = $this->realTeams(2);
        $this->assertTripleEmpty(self::REGULAR_DATE, $v, $h);

        $this->rebuild();
        $beforeV = $this->record($v, self::REGULAR_YEAR, 1);
        $beforeH = $this->record($h, self::REGULAR_YEAR, 1);

        $this->seedNullQuarterGame(self::REGULAR_DATE, 4, $v, $h, 55, 45);
        $this->rebuild();

        $afterV = $this->record($v, self::REGULAR_YEAR, 1);
        $afterH = $this->record($h, self::REGULAR_YEAR, 1);

        self::assertSame(1, $afterV['wins'] - $beforeV['wins'], 'visitor wins delta');
        self::assertSame(0, $afterV['losses'] - $beforeV['losses'], 'visitor losses delta');
        self::assertSame(0, $afterH['wins'] - $beforeH['wins'], 'home wins delta');
        self::assertSame(1, $afterH['losses'] - $beforeH['losses'], 'home losses delta');
    }

    public function testRealQuarterGameIgnoresDifferingCalcPoints(): void
    {
        [$v, $h] = $this->realTeams(2);
        $this->assertTripleEmpty(self::REGULAR_DATE, $v, $h);

        $this->rebuild();
        $beforeV = $this->record($v, self::REGULAR_YEAR, 1);
        $beforeH = $this->record($h, self::REGULAR_YEAR, 1);

        $this->seedRealQuarterGameWithMisleadingCalcPoints(self::REGULAR_DATE, 5, $v, $h);
        $this->rebuild();

        $afterV = $this->record($v, self::REGULAR_YEAR, 1);
        $afterH = $this->record($h, self::REGULAR_YEAR, 1);

        self::assertSame(1, $afterH['wins'] - $beforeH['wins'], 'home wins delta');
        self::assertSame(0, $afterH['losses'] - $beforeH['losses'], 'home losses delta');
        self::assertSame(0, $afterV['wins'] - $beforeV['wins'], 'visitor wins delta');
        self::assertSame(1, $afterV['losses'] - $beforeV['losses'], 'visitor losses delta');
    }

    public function testFiveSeasonWindowCountsNullQuarterGame(): void
    {
        [$v, $h] = $this->realTeams(2);
        $this->assertTripleEmpty(self::REGULAR_DATE, $v, $h);

        $names = [$v => $this->teamName($v), $h => $this->teamName($h)];
        $repo = new FranchiseHistoryRepository($this->db);

        $before = $this->windowFor($repo, $names);
        $this->seedNullQuarterGame(self::REGULAR_DATE, 6, $v, $h, 45, 55);
        $after = $this->windowFor($repo, $names);

        self::assertSame(1, $after[$h]['wins'] - $before[$h]['wins'], 'home five-season wins delta');
        self::assertSame(0, $after[$h]['losses'] - $before[$h]['losses'], 'home five-season losses delta');
        self::assertSame(0, $after[$v]['wins'] - $before[$v]['wins'], 'visitor five-season wins delta');
        self::assertSame(1, $after[$v]['losses'] - $before[$v]['losses'], 'visitor five-season losses delta');
    }

    public function testHeadToHeadCountsNullQuarterGame(): void
    {
        $this->insertRow('ibl_franchise_seasons', [
            'franchise_id'       => self::H2H_VISITOR,
            'season_year'        => 1900,
            'season_ending_year' => 1901,
            'team_city'          => 'San Antonio',
            'team_name'          => 'Spurs',
        ]);
        $this->insertRow('ibl_franchise_seasons', [
            'franchise_id'       => self::H2H_HOME,
            'season_year'        => 1900,
            'season_ending_year' => 1901,
            'team_city'          => 'Seattle',
            'team_name'          => 'Supersonics',
        ]);
        $this->assertTripleEmpty(self::H2H_DATE, self::H2H_VISITOR, self::H2H_HOME);

        $repo = new HeadToHeadRecordsRepository($this->db, 1901);

        $before = $this->h2hCells($repo->buildFranchisesMatrix('regular', 'current')['records']);
        $this->seedNullQuarterGame(self::H2H_DATE, 1, self::H2H_VISITOR, self::H2H_HOME, 45, 55);
        $after = $this->h2hCells($repo->buildFranchisesMatrix('regular', 'current')['records']);

        self::assertSame(1, $after['home']['wins'] - $before['home']['wins'], '22 vs 17 wins delta');
        self::assertSame(0, $after['home']['losses'] - $before['home']['losses'], '22 vs 17 losses delta');
        self::assertSame(0, $after['visitor']['wins'] - $before['visitor']['wins'], '17 vs 22 wins delta');
        self::assertSame(1, $after['visitor']['losses'] - $before['visitor']['losses'], '17 vs 22 losses delta');
    }

    public function testTeamWinLossViewCountsNullQuarterGame(): void
    {
        [$v, $h] = $this->realTeams(2);
        $this->assertTripleEmpty(self::REGULAR_DATE, $v, $h);

        $nameV = $this->teamName($v);
        $nameH = $this->teamName($h);

        $beforeV = $this->teamWinLoss(self::REGULAR_YEAR, $nameV);
        $beforeH = $this->teamWinLoss(self::REGULAR_YEAR, $nameH);

        $this->seedNullQuarterGame(self::REGULAR_DATE, 7, $v, $h, 45, 55);

        $afterV = $this->teamWinLoss(self::REGULAR_YEAR, $nameV);
        $afterH = $this->teamWinLoss(self::REGULAR_YEAR, $nameH);

        self::assertSame(1, $afterH['wins'] - $beforeH['wins'], 'home view wins delta');
        self::assertSame(0, $afterH['losses'] - $beforeH['losses'], 'home view losses delta');
        self::assertSame(0, $afterV['wins'] - $beforeV['wins'], 'visitor view wins delta');
        self::assertSame(1, $afterV['losses'] - $beforeV['losses'], 'visitor view losses delta');
    }

    public function testNullQuarterHeatGameNamesCalcPointsChampion(): void
    {
        [$v, $h] = $this->realTeams(2);
        $this->assertTripleEmpty(self::HEAT_DATE, $v, $h);
        self::assertSame(0, $this->heatGameCount(self::HEAT_YEAR), 'HEAT year already has games; pick another year');

        $this->rebuild();
        $beforeH = $this->record($h, self::HEAT_YEAR, 3);

        $this->seedNullQuarterGame(self::HEAT_DATE, 1, $v, $h, 45, 55);
        $this->rebuild();

        $afterH = $this->record($h, self::HEAT_YEAR, 3);
        self::assertSame(1, $afterH['wins'] - $beforeH['wins'], 'home HEAT wins delta');

        $homeName = $this->teamName($h);
        self::assertSame(
            $homeName,
            $this->singleName('SELECT name FROM `vw_heat_champions` WHERE `year` = ?', self::HEAT_YEAR),
            'vw_heat_champions champion'
        );
        self::assertSame(
            $homeName,
            $this->singleName(
                "SELECT name FROM `vw_team_awards` WHERE `year` = ? AND award = 'IBL HEAT Champions'",
                self::HEAT_YEAR
            ),
            'vw_team_awards HEAT champion'
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Two rows for one game with every quarter and OT column NULL. calc_points is
     * game_2gm * 2 + 12 + 2 * 3, so 45 gives 108 and 55 gives 136.
     *
     * @return array{0: int, 1: int} visitor row id, home row id
     */
    private function seedNullQuarterGame(
        string $date,
        int $gameOfDay,
        int $visitorTid,
        int $homeTid,
        int $visitor2gm,
        int $home2gm,
    ): array {
        // Quarter keys are omitted on purpose: the columns default to NULL, and
        // insertRow() binds a PHP null as '', which strict mode rejects.
        $common = $this->commonColumns($date, $gameOfDay, $visitorTid, $homeTid);
        $visitorId = $this->insertRow('ibl_box_scores_teams', $common + ['name' => 'NQ-V', 'game_2gm' => $visitor2gm]);
        $homeId = $this->insertRow('ibl_box_scores_teams', $common + ['name' => 'NQ-H', 'game_2gm' => $home2gm]);
        self::assertLessThan($homeId, $visitorId, 'visitor row must carry the lower id');

        return [$visitorId, $homeId];
    }

    /**
     * Quarters say home 104 beats visitor 85. calc_points says visitor 159 beats home 99.
     */
    private function seedRealQuarterGameWithMisleadingCalcPoints(
        string $date,
        int $gameOfDay,
        int $visitorTid,
        int $homeTid,
    ): void {
        $quarters = [
            'visitor_q1_points' => 20,
            'visitor_q2_points' => 22,
            'visitor_q3_points' => 18,
            'visitor_q4_points' => 25,
            'visitor_ot_points' => 0,
            'home_q1_points' => 28,
            'home_q2_points' => 24,
            'home_q3_points' => 22,
            'home_q4_points' => 30,
            'home_ot_points' => 0,
        ];
        $common = $this->commonColumns($date, $gameOfDay, $visitorTid, $homeTid) + $quarters;
        $visitorId = $this->insertRow('ibl_box_scores_teams', $common + ['name' => 'NQ-V', 'game_2gm' => 60]);
        $homeId = $this->insertRow('ibl_box_scores_teams', $common + ['name' => 'NQ-H', 'game_2gm' => 30]);
        self::assertLessThan($homeId, $visitorId, 'visitor row must carry the lower id');
    }

    /**
     * @return array<string, int|string>
     */
    private function commonColumns(string $date, int $gameOfDay, int $visitorTid, int $homeTid): array
    {
        return [
            'game_date' => $date,
            'game_of_that_day' => $gameOfDay,
            'visitor_teamid' => $visitorTid,
            'home_teamid' => $homeTid,
            'attendance' => 10000,
            'capacity' => 15000,
            'visitor_wins' => 0,
            'visitor_losses' => 0,
            'home_wins' => 0,
            'home_losses' => 0,
            'game_ftm' => 12,
            'game_fta' => 16,
            'game_3gm' => 2,
            'game_3ga' => 8,
            'game_2ga' => 80,
            'game_orb' => 10,
            'game_drb' => 30,
            'game_ast' => 20,
            'game_stl' => 8,
            'game_tov' => 12,
            'game_blk' => 5,
            'game_pf' => 18,
        ];
    }

    /**
     * @param array<array-key, array<array-key, array{wins: int, losses: int}>> $records
     * @return array{home: array{wins: int, losses: int}, visitor: array{wins: int, losses: int}}
     */
    private function h2hCells(array $records): array
    {
        $zero = ['wins' => 0, 'losses' => 0];
        $home = $records[self::H2H_HOME][self::H2H_VISITOR] ?? $zero;
        $visitor = $records[self::H2H_VISITOR][self::H2H_HOME] ?? $zero;

        return [
            'home' => ['wins' => $home['wins'], 'losses' => $home['losses']],
            'visitor' => ['wins' => $visitor['wins'], 'losses' => $visitor['losses']],
        ];
    }

    /**
     * @return array{wins: int, losses: int}
     */
    private function teamWinLoss(int $year, string $teamName): array
    {
        $stmt = $this->db->prepare(
            'SELECT wins, losses FROM `ibl_team_win_loss` WHERE `year` = ? AND currentname = ?'
        );
        self::assertNotFalse($stmt, 'Failed to prepare view query: ' . $this->db->error);
        $stmt->bind_param('is', $year, $teamName);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row === null) {
            return ['wins' => 0, 'losses' => 0];
        }

        $wins = $row['wins'];
        $losses = $row['losses'];
        self::assertTrue(is_numeric($wins) && is_numeric($losses));

        return ['wins' => (int) $wins, 'losses' => (int) $losses];
    }

    private function heatGameCount(int $year): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS c FROM `ibl_box_scores_teams` WHERE game_type = 3 AND YEAR(game_date) = ?'
        );
        self::assertNotFalse($stmt, 'Failed to prepare HEAT count query: ' . $this->db->error);
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $stmt->close();

        self::assertIsArray($row);

        return (int) $row['c'];
    }

    private function singleName(string $sql, int $year): ?string
    {
        $stmt = $this->db->prepare($sql);
        self::assertNotFalse($stmt, 'Failed to prepare name query: ' . $this->db->error);
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        self::assertCount(1, $rows, 'expected exactly one champion row');
        $name = $rows[0]['name'];
        self::assertIsString($name);

        return $name;
    }

    /**
     * @return list<int>
     */
    private function realTeams(int $n): array
    {
        $stmt = $this->db->prepare(
            'SELECT teamid FROM `ibl_team_info` WHERE teamid BETWEEN 1 AND ? ORDER BY teamid LIMIT ?'
        );
        self::assertNotFalse($stmt, 'Failed to prepare team query: ' . $this->db->error);
        $max = League::MAX_REAL_TEAMID;
        $stmt->bind_param('ii', $max, $n);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);

        $ids = [];
        while (($row = $result->fetch_assoc()) !== null) {
            $id = $row['teamid'];
            self::assertIsInt($id);
            $ids[] = $id;
        }
        $stmt->close();

        self::assertCount($n, $ids, 'Test DB lacks enough real teams');

        return $ids;
    }

    private function teamName(int $teamId): string
    {
        $stmt = $this->db->prepare('SELECT team_name FROM `ibl_team_info` WHERE teamid = ?');
        self::assertNotFalse($stmt, 'Failed to prepare team name query: ' . $this->db->error);
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $stmt->close();

        self::assertIsArray($row);
        $name = $row['team_name'];
        self::assertIsString($name);

        return $name;
    }

    private function assertTripleEmpty(string $date, int $visitor, int $home): void
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS c FROM `ibl_box_scores_teams`
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?'
        );
        self::assertNotFalse($stmt, 'Failed to prepare triple query: ' . $this->db->error);
        $stmt->bind_param('sii', $date, $visitor, $home);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $stmt->close();

        self::assertIsArray($row);
        self::assertSame(0, (int) $row['c'], 'seed triple already has box scores; pick another date');
    }

    private function rebuild(): void
    {
        (new RefreshTeamSeasonRecordsStep($this->db))->rebuildWithinCurrentTransaction();
    }

    /**
     * @return array{wins: int, losses: int}
     */
    private function record(int $teamId, int $year, int $gameType): array
    {
        $stmt = $this->db->prepare(
            'SELECT wins, losses FROM `ibl_team_season_records`
             WHERE team_id = ? AND `year` = ? AND game_type = ?'
        );
        self::assertNotFalse($stmt, 'Failed to prepare record query: ' . $this->db->error);
        $stmt->bind_param('iii', $teamId, $year, $gameType);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row === null) {
            return ['wins' => 0, 'losses' => 0];
        }

        $wins = $row['wins'];
        $losses = $row['losses'];
        self::assertTrue(is_numeric($wins) && is_numeric($losses));

        return ['wins' => (int) $wins, 'losses' => (int) $losses];
    }

    /**
     * @param array<int, string> $names team id => team_name
     * @return array<int, array{wins: int, losses: int}>
     */
    private function windowFor(FranchiseHistoryRepository $repo, array $names): array
    {
        $byName = [];
        foreach ($repo->getFiveSeasonWindowRows(self::REGULAR_YEAR) as $row) {
            $byName[$row['currentname']] = [
                'wins' => (int) $row['five_season_wins'],
                'losses' => (int) $row['five_season_losses'],
            ];
        }

        $out = [];
        foreach ($names as $tid => $name) {
            $out[$tid] = $byName[$name] ?? ['wins' => 0, 'losses' => 0];
        }

        return $out;
    }
}
