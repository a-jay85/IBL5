<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Services\CommonMysqliRepository;
use Trading\CashConsiderationRepository;

/**
 * Verifies that cash considerations in ibl_cash_considerations are correctly
 * included in salary totals via vw_current_salary.
 *
 * Migration 095 moved cash/buyout entries from ibl_plr to ibl_cash_considerations
 * and updated vw_current_salary with a UNION ALL. These tests ensure the UNION
 * produces correct salary sums — catching regressions if the view definition,
 * table structure, or salary calculation logic diverges.
 */
class CashConsiderationSalaryParityTest extends DatabaseTestCase
{
    private CommonMysqliRepository $commonRepo;
    private CashConsiderationRepository $cashRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commonRepo = new CommonMysqliRepository($this->db);
        $this->cashRepo = new CashConsiderationRepository($this->db);
    }

    /**
     * Helper: get the first real franchise from ibl_team_info.
     *
     * @return array{teamid: int, team_name: string}
     */
    private function getFirstTeam(): array
    {
        $row = $this->fetchAll("SELECT teamid, team_name FROM ibl_team_info WHERE teamid = 1")[0] ?? null;
        self::assertNotNull($row, 'Expected at least one team in ibl_team_info');
        return ['teamid' => (int) $row['teamid'], 'team_name' => (string) $row['team_name']];
    }

    public function testViewCurrentSalaryIncludesCashConsiderations(): void
    {
        $team = $this->getFirstTeam();
        $teamName = $team['team_name'];

        $salaryBefore = $this->commonRepo->getTeamTotalSalary($teamName);

        // Insert a cash consideration for this team
        $this->cashRepo->insertCashConsideration([
            'teamid' => $team['teamid'],

            'type' => 'cash',
            'label' => 'Test Cash Entry',
            'cy' => 1,
            'cyt' => 1,
            'cy1' => 500,
            'cy2' => 0,
            'cy3' => 0,
            'cy4' => 0,
            'cy5' => 0,
            'cy6' => 0,
        ]);

        $salaryAfter = $this->commonRepo->getTeamTotalSalary($teamName);

        // The cash entry's current_salary (cy=1 → cy1=500) must be included
        self::assertSame($salaryBefore + 500, $salaryAfter);
    }

    public function testViewNextYearSalaryIncludesCashConsiderations(): void
    {
        $team = $this->getFirstTeam();
        $teamName = $team['team_name'];

        $nextYearBefore = $this->commonRepo->getTeamNextYearSalary($teamName);

        // cy=1, cyt=2: current year is cy1, next year is cy2
        $this->cashRepo->insertCashConsideration([
            'teamid' => $team['teamid'],

            'type' => 'cash',
            'label' => 'Test Multi-Year Cash',
            'cy' => 1,
            'cyt' => 2,
            'cy1' => 300,
            'cy2' => 400,
            'cy3' => 0,
            'cy4' => 0,
            'cy5' => 0,
            'cy6' => 0,
        ]);

        $nextYearAfter = $this->commonRepo->getTeamNextYearSalary($teamName);

        // next_year_salary: cy=1 → cy2=400
        self::assertSame($nextYearBefore + 400, $nextYearAfter);
    }

    public function testNegativeCashReducesTeamSalary(): void
    {
        $team = $this->getFirstTeam();
        $teamName = $team['team_name'];

        $salaryBefore = $this->commonRepo->getTeamTotalSalary($teamName);

        // Negative cash = incoming cash from a trade partner (reduces cap hit)
        $this->cashRepo->insertCashConsideration([
            'teamid' => $team['teamid'],

            'type' => 'cash',
            'label' => 'Test Incoming Cash',
            'cy' => 1,
            'cyt' => 1,
            'cy1' => -200,
            'cy2' => 0,
            'cy3' => 0,
            'cy4' => 0,
            'cy5' => 0,
            'cy6' => 0,
        ]);

        $salaryAfter = $this->commonRepo->getTeamTotalSalary($teamName);

        self::assertSame($salaryBefore - 200, $salaryAfter);
    }

    public function testBuyoutIncludedInTeamSalary(): void
    {
        $team = $this->getFirstTeam();
        $teamName = $team['team_name'];

        $salaryBefore = $this->commonRepo->getTeamTotalSalary($teamName);

        $this->cashRepo->insertCashConsideration([
            'teamid' => $team['teamid'],

            'type' => 'buyout',
            'label' => 'Test Buyout',
            'cy' => 1,
            'cyt' => 1,
            'cy1' => 350,
            'cy2' => 0,
            'cy3' => 0,
            'cy4' => 0,
            'cy5' => 0,
            'cy6' => 0,
        ]);

        $salaryAfter = $this->commonRepo->getTeamTotalSalary($teamName);

        self::assertSame($salaryBefore + 350, $salaryAfter);
    }

    /**
     * Verifies that for every team with cash considerations, the view's
     * SUM equals ibl_plr SUM + ibl_cash_considerations SUM. This catches
     * any UNION logic errors (wrong cy mapping, missing columns, etc.).
     */
    public function testViewSalaryEqualsPlrPlusCashForAllTeams(): void
    {
        /** @var list<array{teamid: int, plr_salary: int, cash_salary: int, view_salary: int}> $rows */
        $rows = $this->fetchAll("
            WITH plr_totals AS (
                SELECT teamid,
                       SUM(CASE cy WHEN 1 THEN cy1 WHEN 2 THEN cy2 WHEN 3 THEN cy3
                                    WHEN 4 THEN cy4 WHEN 5 THEN cy5 WHEN 6 THEN cy6 ELSE 0 END) AS plr_salary
                FROM ibl_plr WHERE retired = 0 AND teamid BETWEEN 1 AND 28
                GROUP BY teamid
            ),
            cash_totals AS (
                SELECT teamid,
                       SUM(CASE cy WHEN 1 THEN cy1 WHEN 2 THEN cy2 WHEN 3 THEN cy3
                                    WHEN 4 THEN cy4 WHEN 5 THEN cy5 WHEN 6 THEN cy6 ELSE 0 END) AS cash_salary
                FROM ibl_cash_considerations WHERE teamid BETWEEN 1 AND 28
                GROUP BY teamid
            ),
            view_totals AS (
                SELECT teamid, SUM(current_salary) AS view_salary
                FROM vw_current_salary WHERE teamid BETWEEN 1 AND 28
                GROUP BY teamid
            )
            SELECT t.teamid AS teamid,
                   COALESCE(p.plr_salary, 0) AS plr_salary,
                   COALESCE(c.cash_salary, 0) AS cash_salary,
                   COALESCE(v.view_salary, 0) AS view_salary
            FROM ibl_team_info t
            LEFT JOIN plr_totals p ON t.teamid = p.teamid
            LEFT JOIN cash_totals c ON t.teamid = c.teamid
            LEFT JOIN view_totals v ON t.teamid = v.teamid
            WHERE t.teamid BETWEEN 1 AND 28
        ");

        self::assertNotEmpty($rows, 'Expected team salary data');

        foreach ($rows as $row) {
            $teamid = (int) $row['teamid'];
            $plr = (int) $row['plr_salary'];
            $cash = (int) $row['cash_salary'];
            $view = (int) $row['view_salary'];
            $expected = $plr + $cash;
            self::assertSame(
                $expected,
                $view,
                "Team {$teamid}: plr({$plr}) + cash({$cash}) != view({$view})"
            );
        }
    }

    /**
     * Executes createCashTransaction() against real DB and verifies:
     * 1. Paired rows land in ibl_cash_considerations (positive + negative)
     * 2. No rows are created in ibl_plr
     * 3. Labels, amounts, and counterparty references are correct
     */
    public function testCashTransactionInsertsIntoCashTableNotPlr(): void
    {
        $team1 = $this->getFirstTeam();
        $team2Row = $this->fetchAll(
            "SELECT teamid, team_name FROM ibl_team_info WHERE teamid = 2"
        )[0] ?? null;
        self::assertNotNull($team2Row);
        $team2 = ['teamid' => (int) $team2Row['teamid'], 'team_name' => (string) $team2Row['team_name']];

        // Snapshot ibl_plr row count before
        $plrCountBefore = (int) ($this->fetchAll(
            "SELECT COUNT(*) AS cnt FROM ibl_plr"
        )[0]['cnt'] ?? 0);

        $cashCountBefore = (int) ($this->fetchAll(
            "SELECT COUNT(*) AS cnt FROM ibl_cash_considerations"
        )[0]['cnt'] ?? 0);

        // Execute a cash transaction through the real handler
        $handler = new \Trading\CashTransactionHandler($this->db);
        $result = $handler->createCashTransaction(
            $team1['team_name'],
            $team2['team_name'],
            [1 => 500, 2 => 300, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
            2026,
            99999  // fake trade offer ID
        );

        self::assertTrue($result['success'], 'Cash transaction should succeed');

        // Verify two new rows in ibl_cash_considerations
        $cashCountAfter = (int) ($this->fetchAll(
            "SELECT COUNT(*) AS cnt FROM ibl_cash_considerations"
        )[0]['cnt'] ?? 0);
        self::assertSame($cashCountBefore + 2, $cashCountAfter, 'Should insert exactly 2 cash rows');

        // Verify NO new rows in ibl_plr
        $plrCountAfter = (int) ($this->fetchAll(
            "SELECT COUNT(*) AS cnt FROM ibl_plr"
        )[0]['cnt'] ?? 0);
        self::assertSame($plrCountBefore, $plrCountAfter, 'No rows should be added to ibl_plr');

        // Verify the positive entry (sending team)
        $positiveRows = $this->fetchAll(
            "SELECT * FROM ibl_cash_considerations
             WHERE teamid = {$team1['teamid']} AND trade_offer_id = 99999"
        );
        self::assertCount(1, $positiveRows);
        $pos = $positiveRows[0];
        self::assertSame('cash', $pos['type']);
        self::assertSame("Cash to {$team2['team_name']}", $pos['label']);
        self::assertSame($team2['teamid'], (int) $pos['counterparty_teamid']);
        self::assertSame(500, (int) $pos['cy1']);
        self::assertSame(300, (int) $pos['cy2']);
        self::assertSame(2, (int) $pos['cyt']);

        // Verify the negative entry (receiving team)
        $negativeRows = $this->fetchAll(
            "SELECT * FROM ibl_cash_considerations
             WHERE teamid = {$team2['teamid']} AND trade_offer_id = 99999"
        );
        self::assertCount(1, $negativeRows);
        $neg = $negativeRows[0];
        self::assertSame('cash', $neg['type']);
        self::assertSame("Cash from {$team1['team_name']}", $neg['label']);
        self::assertSame($team1['teamid'], (int) $neg['counterparty_teamid']);
        self::assertSame(-500, (int) $neg['cy1']);
        self::assertSame(-300, (int) $neg['cy2']);
        self::assertSame(2, (int) $neg['cyt']);
    }

    /**
     * Helper to run raw SQL and return all rows.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchAll(string $sql): array
    {
        $result = $this->db->query($sql);
        if ($result === false) {
            self::fail('Query failed: ' . $this->db->error);
        }
        /** @var list<array<string, mixed>> */
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
