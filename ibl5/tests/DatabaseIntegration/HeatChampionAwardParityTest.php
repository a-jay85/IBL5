<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use League\League;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SeasonArchive\SeasonArchiveRepository;
use Team\TeamRepository;

/**
 * Pins that the season archive and the team page name the same IBL HEAT champion.
 *
 * Both builders share Repositories\TeamAwardSql::HEAT_WINNER_SUBSELECT. The archive adds
 * a year predicate on top of it; the team page is all-time by design. The NULL-quarter
 * case proves the FIRST_VALUE(calc_points) fallback scores a game with no quarter data.
 *
 * Year 2097 stays clear of NullQuarterGameFallbackTest (2098) and the CI seed. Seeds run
 * through prepare() directly; DatabaseTestCase rolls the transaction back in tearDown().
 */
#[Group('database')]
final class HeatChampionAwardParityTest extends DatabaseTestCase
{
    private const HEAT_YEAR = 2097;
    private const HEAT_FINAL_DATE = '2097-10-21';
    private const HEAT_OPENER_DATE = '2097-10-20';
    private const HEAT_AWARD = 'IBL HEAT Champions';

    #[Test]
    public function nullQuarterHeatFinalNamesTheSameChampionOnArchiveAndTeamPage(): void
    {
        [$v, $h] = $this->realTeams(2);
        self::assertSame(0, $this->heatGameCount(self::HEAT_YEAR), 'HEAT year already has games; pick another year');

        $this->seedNullQuarterGame(self::HEAT_FINAL_DATE, 1, $v, $h, 45, 55);

        $archive = $this->heatRows(
            (new SeasonArchiveRepository($this->db))->getTeamAwardsByYear(self::HEAT_YEAR, self::HEAT_YEAR),
            self::HEAT_YEAR
        );
        self::assertCount(1, $archive);
        self::assertSame($this->teamName($h), $archive[0]['name']);
        self::assertSame(0, $archive[0]['id']);

        $team = $this->heatRows(
            (new TeamRepository($this->db))->getTeamAccomplishments($this->teamName($h)),
            self::HEAT_YEAR
        );
        self::assertCount(1, $team);
        self::assertSame($archive[0], $team[0]);

        self::assertSame(
            [],
            $this->heatRows(
                (new TeamRepository($this->db))->getTeamAccomplishments($this->teamName($v)),
                self::HEAT_YEAR
            ),
            'the losing team page carries no HEAT title for that year'
        );
    }

    #[Test]
    public function realQuartersOutrankMisleadingCalcPointsOnBothBuilders(): void
    {
        [$v, $h] = $this->realTeams(2);
        self::assertSame(0, $this->heatGameCount(self::HEAT_YEAR), 'HEAT year already has games; pick another year');

        // Opener: NULL quarters, home team $h wins on calc_points.
        $this->seedNullQuarterGame(self::HEAT_OPENER_DATE, 1, $v, $h, 45, 55);
        // Final: the helper's home side wins on quarters and its visitor side wins on
        // calc_points. $h takes the visitor seat, so $v wins on quarters and $h on calc_points.
        $this->seedRealQuarterGameWithMisleadingCalcPoints(self::HEAT_FINAL_DATE, 1, $h, $v);

        $archive = $this->heatRows(
            (new SeasonArchiveRepository($this->db))->getTeamAwardsByYear(self::HEAT_YEAR, self::HEAT_YEAR),
            self::HEAT_YEAR
        );
        self::assertCount(1, $archive);
        self::assertSame($this->teamName($v), $archive[0]['name']);

        $team = $this->heatRows(
            (new TeamRepository($this->db))->getTeamAccomplishments($this->teamName($v)),
            self::HEAT_YEAR
        );
        self::assertCount(1, $team);
        self::assertSame($this->teamName($v), $team[0]['name']);
        self::assertSame($archive[0], $team[0]);
    }

    #[Test]
    public function archiveHeatRowFollowsTheHeatYearArgumentWhileTeamPageIsAllTime(): void
    {
        [$v, $h] = $this->realTeams(2);
        self::assertSame(0, $this->heatGameCount(self::HEAT_YEAR), 'HEAT year already has games; pick another year');

        $this->seedNullQuarterGame(self::HEAT_FINAL_DATE, 1, $v, $h, 45, 55);

        $archive = (new SeasonArchiveRepository($this->db))->getTeamAwardsByYear(self::HEAT_YEAR, self::HEAT_YEAR - 1);
        self::assertSame([], $this->heatRows($archive, self::HEAT_YEAR));

        $team = (new TeamRepository($this->db))->getTeamAccomplishments($this->teamName($h));
        self::assertCount(1, $this->heatRows($team, self::HEAT_YEAR));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function heatRows(array $rows, int $year): array
    {
        // The UNION with ibl_team_awards widens `year` past a native int, so cast before comparing.
        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['award'] === self::HEAT_AWARD && (int) $row['year'] === $year
        ));
    }

    /**
     * Two rows for one game with every quarter and OT column NULL. calc_points is
     * game_2gm * 2 + 12 + 2 * 3, so 45 gives 108 and 55 gives 128.
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
     * Quarters say home 104 beats visitor 85. calc_points says visitor 138 beats home 78.
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
}
