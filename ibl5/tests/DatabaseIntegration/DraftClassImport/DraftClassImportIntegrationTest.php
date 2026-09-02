<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\DraftClassImport;

use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * Proves that a mid-loop INSERT failure during the draft-class wipe-and-re-insert
 * sequence leaves ibl_draft_class at its original row count.
 *
 * Design note on transaction nesting:
 * DatabaseTestCase wraps every test in begin_transaction() / rollback(). MySQL /
 * MariaDB has no nested transactions — a second begin_transaction() inside the test
 * would implicitly commit the harness's outer transaction, defeating isolation and
 * making the row-count assertion meaningless. We use SAVEPOINT / ROLLBACK TO SAVEPOINT
 * instead, which nests correctly inside the existing harness transaction.
 */
#[Group('database')]
final class DraftClassImportIntegrationTest extends DatabaseTestCase
{
    public function testFailedInsertLeavesTableAtOriginalRowCount(): void
    {
        // ── 1. Seed a known baseline using the harness helper ──────────────────────
        $this->insertDraftClassRow('Prospect Alpha', 'PG');
        $this->insertDraftClassRow('Prospect Beta', 'SG');

        // ── 2. Read the baseline count inside the harness transaction ──────────────
        $countResult = $this->db->query('SELECT COUNT(*) FROM ibl_draft_class');
        self::assertNotFalse($countResult, 'Baseline COUNT query failed: ' . $this->db->error);
        $countRow = $countResult->fetch_row();
        self::assertNotNull($countRow, 'COUNT(*) query returned no rows');
        $baselineCount = (int) $countRow[0];
        $countResult->free();

        // ── 3. Open a nested savepoint (safe inside the harness transaction) ───────
        $this->db->query('SAVEPOINT before_import');

        // ── 4. Mirror the page's wipe step: delete all existing rows ───────────────
        $this->db->query('DELETE FROM ibl_draft_class');

        // ── 5. Prepare the INSERT once, outside the loop, mirroring the page ───────
        //    Type string: s=name, s=pos, i=age, s=team, then i×23 rating columns
        $stmt = $this->db->prepare(
            'INSERT INTO ibl_draft_class'
            . ' (name, pos, age, team, fga, fgp, fta, ftp, r_3ga, r_3gp,'
            . ' orb, drb, ast, stl, tvr, blk, oo, r_drive_off, po, r_trans_off,'
            . ' od, dd, pd, td, talent, skill, intangibles)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        self::assertNotFalse($stmt, 'Failed to prepare INSERT: ' . $this->db->error);

        // bind_param binds by reference — mutating these variables in the loop
        // updates the bound values before each execute() call
        $name       = '';
        $pos        = '';
        $age        = 0;
        $team       = '';
        $fga        = 0;
        $fgp        = 0;
        $fta        = 0;
        $ftp        = 0;
        $r3ga       = 0;
        $r3gp       = 0;
        $orb        = 0;
        $drb        = 0;
        $ast        = 0;
        $stl        = 0;
        $tvr        = 0;
        $blk        = 0;
        $oo         = 0;
        $rDriveOff  = 0;
        $po         = 0;
        $rTransOff  = 0;
        $od         = 0;
        $dd         = 0;
        $pd         = 0;
        $td         = 0;
        $talent     = 0;
        $skill      = 0;
        $intangibles = 0;

        $stmt->bind_param(
            'ssis' . str_repeat('i', 23),
            $name,       $pos,      $age,      $team,
            $fga,        $fgp,      $fta,      $ftp,      $r3ga,     $r3gp,
            $orb,        $drb,      $ast,      $stl,      $tvr,      $blk,      $oo,
            $rDriveOff,  $po,       $rTransOff,
            $od,         $dd,       $pd,       $td,
            $talent,     $skill,    $intangibles
        );

        // Rows: two good ones, then a 40-char name (over varchar(32)).
        // STRICT_TRANS_TABLES is active on this DB; the over-length name triggers
        // an error mid-loop — the malformed row is NOT first, so at least one good
        // INSERT lands before the failure, making the rollback assertion meaningful.
        $rows = [
            ['Prospect Gamma', 'SF', 23, ''],
            ['Prospect Delta', 'PF', 24, ''],
            [str_repeat('X', 40), 'C', 25, ''],  // 40 chars > varchar(32): forced failure
        ];

        $successCount = 0;
        $catchTaken   = false;

        try {
            foreach ($rows as $rowData) {
                $name  = $rowData[0];
                $pos   = $rowData[1];
                $age   = $rowData[2];
                $team  = $rowData[3];
                $fga   = $fgp   = $fta   = $ftp  = 50;
                $r3ga  = $r3gp  = $orb   = $drb  = 50;
                $ast   = $stl   = $tvr   = $blk  = 50;
                $oo    = $rDriveOff = $po = $rTransOff = 50;
                $od    = $dd    = $pd    = $td   = 50;
                $talent = $skill = $intangibles   = 50;

                $ok = $stmt->execute();
                if ($ok === false) {
                    // Defensive branch for MYSQLI_REPORT_OFF environments (PHP < 8.1
                    // default); in PHP 8.1+ mysqli throws mysqli_sql_exception instead.
                    throw new \RuntimeException('INSERT execute failed: ' . $stmt->error);
                }

                $successCount++;
            }
        } catch (\Throwable $e) {
            // Confirm that STRICT_TRANS_TABLES fired on this PHP connection (not merely
            // in the CLI session from the pre-write SELECT @@sql_mode check) and that
            // the failure was the over-length name column specifically.
            self::assertStringContainsString(
                'Data too long',
                $e->getMessage(),
                'Expected a "Data too long" strict-mode error; got: ' . $e->getMessage()
            );

            // Verify mid-loop state: the DELETE already ran and two good rows landed
            // before the failure. This is the condition that makes rollback non-trivial.
            $midResult = $this->db->query('SELECT COUNT(*) FROM ibl_draft_class');
            self::assertNotFalse($midResult, 'Mid-loop COUNT query failed');
            $midRow = $midResult->fetch_row();
            self::assertNotNull($midRow);
            $midCount = (int) $midRow[0];
            $midResult->free();
            self::assertSame(
                2,
                $midCount,
                "Mid-loop: expected 2 rows (DELETE + 2 good inserts), got $midCount"
            );

            // Undo the DELETE and the two partial inserts
            $this->db->query('ROLLBACK TO SAVEPOINT before_import');

            $catchTaken = true;
        }

        $stmt->close();

        // Guard: the catch block must have run — a test whose catch path never
        // executes is worthless (it cannot distinguish rollback from no-op)
        self::assertTrue(
            $catchTaken,
            'Catch block was never reached — the forced failure did not trigger'
        );

        // The malformed row was third; exactly two good inserts preceded it
        self::assertSame(
            2,
            $successCount,
            'Expected exactly 2 successful inserts before the forced failure'
        );

        // Core assertion (Verification Matrix row 20): after rollback, the table
        // must be back to the pre-import count — proving the DELETE is undone
        $afterResult = $this->db->query('SELECT COUNT(*) FROM ibl_draft_class');
        self::assertNotFalse($afterResult, 'Post-rollback COUNT query failed');
        $afterRow = $afterResult->fetch_row();
        self::assertNotNull($afterRow);
        $afterCount = (int) $afterRow[0];
        $afterResult->free();

        self::assertSame(
            $baselineCount,
            $afterCount,
            "Post-rollback row count ($afterCount) must equal baseline ($baselineCount)"
            . ' — the DELETE was not fully rolled back'
        );
    }
}
