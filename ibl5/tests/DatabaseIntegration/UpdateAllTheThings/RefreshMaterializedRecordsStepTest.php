<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\UpdateAllTheThings;

use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Updater\Steps\RefreshPlayoffSeriesResultsStep;
use Updater\Steps\RefreshTeamSeasonRecordsStep;

/**
 * Characterization pins for RefreshTeamSeasonRecordsStep and
 * RefreshPlayoffSeriesResultsStep. The test DB is production-synced.
 *
 * game_type is GENERATED from MONTH(game_date):
 *   month=6 → type=2 (playoffs), month=10 → type=3 (HEAT), else → type=1.
 *
 * RefreshTeamSeasonRecordsStep inserts BOTH game_type=1 (regular season) AND
 * game_type=3 (HEAT) rows into ibl_team_season_records.
 *
 * Test ordering by declaration is load-bearing:
 *   1+2  happy-path: real data for game_type=1; insert fixtures for game_type=2.
 *   3+4  empty-source: DELETE ALL box scores first so both INSERTs produce 0 rows.
 *
 * Because the step's begin_transaction() commits the outer test transaction,
 * DELETE operations issued before the step are permanently committed —
 * intentional for the empty-source boundary tests.
 */
#[Group('database')]
class RefreshMaterializedRecordsStepTest extends DatabaseTestCase
{
    // -------------------------------------------------------------------------
    // Happy-path tests (declared first — production source data present)
    // -------------------------------------------------------------------------

    public function testRefreshTeamSeasonRecordsStepPopulatesFromBoxScores(): void
    {
        // Production DB has real game_type=1 (and game_type=3) box scores.
        $step = new RefreshTeamSeasonRecordsStep($this->db);
        $result = $step->execute();

        self::assertTrue($result->success, 'Step must succeed with real source rows: ' . $result->detail);

        $row = $this->db->query('SELECT COUNT(*) AS cnt FROM ibl_team_season_records')->fetch_assoc();
        self::assertGreaterThan(0, (int) ($row['cnt'] ?? 0), 'Expected > 0 rows in target after step');
    }

    public function testRefreshPlayoffSeriesResultsStepPopulatesFromBoxScores(): void
    {
        // Production DB may have no game_type=2 (playoff / June) box scores.
        // Insert a minimal 4-0 series between the first two real teams in the DB.
        $r1 = $this->db->query(
            'SELECT teamid FROM ibl_team_info WHERE teamid > 0 ORDER BY teamid LIMIT 1'
        )->fetch_assoc();
        $r2 = $this->db->query(
            'SELECT teamid FROM ibl_team_info WHERE teamid > 0 ORDER BY teamid LIMIT 1 OFFSET 1'
        )->fetch_assoc();
        $tid1 = (int) ($r1['teamid'] ?? 1);
        $tid2 = (int) ($r2['teamid'] ?? 2);

        // June dates → game_type=2. Home team ($tid1) wins every game.
        $this->insertTeamBoxscoreRow('2025-06-01', 'TestGame', 1, $tid2, $tid1);
        $this->insertTeamBoxscoreRow('2025-06-03', 'TestGame', 1, $tid2, $tid1);
        $this->insertTeamBoxscoreRow('2025-06-05', 'TestGame', 1, $tid2, $tid1);
        $this->insertTeamBoxscoreRow('2025-06-07', 'TestGame', 1, $tid2, $tid1);

        $step = new RefreshPlayoffSeriesResultsStep($this->db);
        $result = $step->execute();

        self::assertTrue($result->success, 'Step must succeed with playoff games: ' . $result->detail);

        $row = $this->db->query('SELECT COUNT(*) AS cnt FROM ibl_playoff_series_results')->fetch_assoc();
        self::assertGreaterThan(0, (int) ($row['cnt'] ?? 0), 'Expected > 0 rows in target after step');
    }

    // -------------------------------------------------------------------------
    // Empty-source boundary tests (declared after happy-path)
    // -------------------------------------------------------------------------

    public function testRefreshTeamSeasonRecordsStepEmptySourceProducesZeroRowsNoError(): void
    {
        // Delete ALL box-score rows so both the regular-season (game_type=1)
        // and HEAT (game_type=3) INSERTs inside the step produce 0 rows.
        // The step's begin_transaction() commits this DELETE permanently.
        $this->db->query('DELETE FROM ibl_box_scores_teams');

        $step = new RefreshTeamSeasonRecordsStep($this->db);
        $result = $step->execute();

        self::assertTrue($result->success, 'Step must succeed on empty source: ' . $result->detail);

        $row = $this->db->query('SELECT COUNT(*) AS cnt FROM ibl_team_season_records')->fetch_assoc();
        self::assertSame(0, (int) ($row['cnt'] ?? -1), 'Expected 0 rows in target on empty source');
    }

    public function testRefreshPlayoffSeriesResultsStepEmptySourceProducesZeroRowsNoError(): void
    {
        // ibl_box_scores_teams was already cleared by the previous test; DELETE is
        // a no-op here but kept for self-documentation and test independence.
        $this->db->query('DELETE FROM ibl_box_scores_teams WHERE MONTH(game_date) = 6');

        $step = new RefreshPlayoffSeriesResultsStep($this->db);
        $result = $step->execute();

        self::assertTrue($result->success, 'Step must succeed on empty source: ' . $result->detail);

        $row = $this->db->query('SELECT COUNT(*) AS cnt FROM ibl_playoff_series_results')->fetch_assoc();
        self::assertSame(0, (int) ($row['cnt'] ?? -1), 'Expected 0 rows in target on empty source');
    }
}
