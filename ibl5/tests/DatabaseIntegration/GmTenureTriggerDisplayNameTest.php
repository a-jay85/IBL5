<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

/**
 * Covers trg_gm_tenure_track after migration 207 (backlog#1708): the trigger stores GM display
 * names (ibl_team_info.owner_name) instead of site usernames, closes the franchise's open tenure
 * by franchise rather than by name, and follows owner_name renames.
 *
 * Three write orders exist for the two columns, since an admin edits them in a DB tool:
 *   1. Both columns in one UPDATE statement: exact.
 *   2. gm_username first, then owner_name: exact. The second statement takes the rename branch
 *      and fixes the open tenure the first statement opened under the stale name.
 *   3. owner_name first, then gm_username: the rename branch renames the outgoing GM's open
 *      tenure to the incoming GM's name before the second statement closes it, so one closed row
 *      carries the wrong name. This is the named residual and is not tested.
 */
#[Group('database')]
class GmTenureTriggerDisplayNameTest extends DatabaseTestCase
{
    public function testGmChangeOpensTenureUnderOwnerName(): void
    {
        [$a] = $this->probeTeams();

        $this->setTeamGm($a, 'zzolduser', 'ZZ Old Gm');
        $this->setTeamGm($a, 'zznewuser', 'ZZ New Gm');

        $open = array_values(array_filter(
            $this->openTenures($a),
            static fn (array $row): bool => $row['end_season_year'] === null
        ));
        self::assertCount(1, $open);
        self::assertSame('ZZ New Gm', $open[0]['gm_display_name']);
        self::assertSame(2025, $open[0]['start_season_year']);

        $userRows = $this->db->query(
            "SELECT COUNT(*) AS cnt FROM ibl_gm_tenures WHERE gm_display_name = 'zznewuser'"
        )->fetch_assoc();
        self::assertIsArray($userRows);
        self::assertSame(0, $userRows['cnt']);
    }

    public function testGmChangeClosesOpenTenureByFranchiseNotByName(): void
    {
        [$a] = $this->probeTeams();

        $this->setTeamGm($a, 'zzolduser', 'ZZ Old Gm');
        $this->setTeamGm($a, null, '');
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id' => $a,
            'gm_display_name' => 'ZZ Manual Entry',
            'start_season_year' => 2020,
        ]);

        $this->setTeamGm($a, 'zznewuser', 'ZZ New Gm');

        $rows = $this->openTenures($a);
        $manual = array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['gm_display_name'] === 'ZZ Manual Entry'
        ));
        self::assertCount(1, $manual);
        self::assertSame(2025, $manual[0]['end_season_year']);

        $open = array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['end_season_year'] === null
        ));
        self::assertCount(1, $open);
        self::assertSame('ZZ New Gm', $open[0]['gm_display_name']);
    }

    public function testGmChangeWithEmptyOwnerNameFallsBackToUsername(): void
    {
        [$a] = $this->probeTeams();

        $this->setTeamGm($a, 'zzonlyuser', '');

        $rows = $this->openTenures($a);
        self::assertCount(1, $rows);
        self::assertSame('zzonlyuser', $rows[0]['gm_display_name']);
        self::assertNull($rows[0]['end_season_year']);
    }

    public function testGmChangeThenOwnerNameInSeparateStatementsConverges(): void
    {
        [$a] = $this->probeTeams();

        $this->setTeamGm($a, 'zzolduser', 'ZZ Old Gm');
        // Backdate the old tenure. The username-only statement reopens a tenure under the stale
        // name at the current season start, and uq_tenure (franchise, name, start) would reject
        // that against an old tenure that also started this season.
        $this->db->query("UPDATE ibl_gm_tenures SET start_season_year = 2020 WHERE franchise_id = $a");
        $this->setUsername($a, 'zznewuser');
        $this->setOwnerName($a, 'ZZ New Gm');

        $rows = $this->openTenures($a);
        self::assertCount(2, $rows);
        self::assertSame('ZZ Old Gm', $rows[0]['gm_display_name']);
        self::assertSame(2025, $rows[0]['end_season_year']);
        self::assertSame('ZZ New Gm', $rows[1]['gm_display_name']);
        self::assertNull($rows[1]['end_season_year']);
    }

    public function testOwnerNameRenameUpdatesOnlyTheFranchiseOpenTenure(): void
    {
        [$a, $b] = $this->probeTeams();

        $this->setTeamGm($a, 'zzuser_a', 'ZZ Shared Name');
        $this->setTeamGm($b, 'zzuser_b', 'ZZ Shared Name');
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id' => $a,
            'gm_display_name' => 'ZZ Shared Name',
            'start_season_year' => 2010,
            'end_season_year' => 2012,
        ]);

        $this->setOwnerName($a, 'ZZ Renamed');

        $rowsA = $this->openTenures($a);
        $openA = array_values(array_filter(
            $rowsA,
            static fn (array $row): bool => $row['end_season_year'] === null
        ));
        self::assertCount(1, $openA);
        self::assertSame('ZZ Renamed', $openA[0]['gm_display_name']);

        $closedA = array_values(array_filter(
            $rowsA,
            static fn (array $row): bool => $row['start_season_year'] === 2010
        ));
        self::assertCount(1, $closedA);
        self::assertSame('ZZ Shared Name', $closedA[0]['gm_display_name']);

        $rowsB = $this->openTenures($b);
        self::assertCount(1, $rowsB);
        self::assertSame('ZZ Shared Name', $rowsB[0]['gm_display_name']);
    }

    public function testOwnerNameRenameRewritesGmHistoryCompositeName(): void
    {
        [$a, $b] = $this->probeTeams();
        $teamNameA = $this->teamName($a);
        $teamNameB = $this->teamName($b);

        $this->setTeamGm($a, 'zzuser_a', 'ZZ Old Gm');
        $this->insertRow('ibl_gm_history', [
            'year' => '2025',
            'name' => "ZZ Old Gm ($teamNameA)",
            'award' => 'ZZ Probe Award',
            'prim' => 9900001,
        ]);
        $this->insertRow('ibl_gm_history', [
            'year' => '2025',
            'name' => "ZZ Old Gm ($teamNameB)",
            'award' => 'ZZ Probe Award',
            'prim' => 9900002,
        ]);

        $this->setOwnerName($a, 'ZZ New Gm');

        self::assertSame("ZZ New Gm ($teamNameA)", $this->gmHistoryName(9900001));
        self::assertSame("ZZ Old Gm ($teamNameB)", $this->gmHistoryName(9900002));
    }

    public function testOwnerNameRenameLeavesGmAwardsUntouched(): void
    {
        [$a] = $this->probeTeams();

        $this->setTeamGm($a, 'zzuser_a', 'ZZ Old Gm');
        $this->insertRow('ibl_gm_awards', [
            'year' => 2025,
            'award' => 'ZZ Probe GM Award',
            'name' => 'ZZ Old Gm',
        ]);

        $this->setOwnerName($a, 'ZZ New Gm');

        $row = $this->db->query(
            "SELECT name FROM ibl_gm_awards WHERE award = 'ZZ Probe GM Award'"
        )->fetch_assoc();
        self::assertIsArray($row);
        self::assertSame('ZZ Old Gm', $row['name'], 'ibl_gm_awards.name carries no franchise key, so a rename must not touch it');
    }

    public function testUnrelatedColumnUpdateLeavesTenuresUntouched(): void
    {
        [$a] = $this->probeTeams();

        $this->setTeamGm($a, 'zzuser_a', 'ZZ Old Gm');
        $before = $this->openTenures($a);

        $arena = 'ZZ Probe Arena';
        $stmt = $this->db->prepare('UPDATE ibl_team_info SET arena = ? WHERE teamid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('si', $arena, $a);
        $stmt->execute();
        $stmt->close();

        self::assertSame($before, $this->openTenures($a));
    }

    public function testOwnerNameRenameToEmptyStringIsIgnored(): void
    {
        [$a] = $this->probeTeams();

        $this->setTeamGm($a, 'zzuser_a', 'ZZ Old Gm');
        $this->setOwnerName($a, '');

        $rows = $this->openTenures($a);
        self::assertCount(1, $rows);
        self::assertSame('ZZ Old Gm', $rows[0]['gm_display_name']);
        self::assertNull($rows[0]['end_season_year']);
    }

    public function testMigrationGuardAbortsWhenFranchiseHasTwoOpenTenures(): void
    {
        [$a] = $this->probeTeams();

        $this->setTeamGm($a, null, '');
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id' => $a,
            'gm_display_name' => 'ZZ Open One',
            'start_season_year' => 2010,
        ]);
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id' => $a,
            'gm_display_name' => 'ZZ Open Two',
            'start_season_year' => 2015,
        ]);

        $guard = null;
        foreach ($this->migrationStatements() as $statement) {
            if (str_contains($statement, 'BEGIN NOT ATOMIC')) {
                $guard = $statement;
                break;
            }
        }
        self::assertNotNull($guard, 'migration has no BEGIN NOT ATOMIC guard statement');

        $this->expectException(\mysqli_sql_exception::class);
        $this->expectExceptionMessageMatches('/more than one open ibl_gm_tenures row/');

        $this->db->query($guard);
    }

    public function testMigrationIsIdempotentAndKeepsTriggerOrder(): void
    {
        // No probe rows: the DDL inside the migration commits the surrounding transaction.
        $statements = $this->migrationStatements();
        self::assertNotSame([], $statements);

        // The migration's backfill runs outside any transaction (DDL committed it already), so it
        // would leave seed tenure names rewritten for every later test. Snapshot and restore them.
        $before = [];
        $snapshot = $this->db->query('SELECT id, gm_display_name FROM ibl_gm_tenures');
        while ($row = $snapshot->fetch_assoc()) {
            $before[(int) $row['id']] = (string) $row['gm_display_name'];
        }

        try {
            for ($run = 0; $run < 2; $run++) {
                foreach ($statements as $statement) {
                    $this->db->query($statement);
                }
            }
        } finally {
            $restore = $this->db->prepare('UPDATE ibl_gm_tenures SET gm_display_name = ? WHERE id = ?');
            self::assertNotFalse($restore);
            foreach ($before as $id => $name) {
                $restore->bind_param('si', $name, $id);
                $restore->execute();
            }
            $restore->close();
        }

        $names = [];
        $orders = [];
        $actions = [];
        $res = $this->db->query(
            "SELECT TRIGGER_NAME, ACTION_ORDER, ACTION_STATEMENT FROM INFORMATION_SCHEMA.TRIGGERS
             WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = 'ibl_team_info'
               AND ACTION_TIMING = 'AFTER' AND EVENT_MANIPULATION = 'UPDATE'
             ORDER BY ACTION_ORDER"
        );
        while ($row = $res->fetch_assoc()) {
            $name = (string) $row['TRIGGER_NAME'];
            $names[] = $name;
            $orders[] = (int) $row['ACTION_ORDER'];
            $actions[$name] = (string) $row['ACTION_STATEMENT'];
        }

        self::assertSame(['trg_team_identity_sync', 'trg_gm_tenure_track'], $names);
        self::assertSame([1, 2], $orders);
        self::assertStringContainsString('NEW.owner_name', $actions['trg_gm_tenure_track']);
    }

    /**
     * Returns the two lowest real teamids and clears their tenures so each test starts clean.
     *
     * @return array{int, int}
     */
    private function probeTeams(): array
    {
        $res = $this->db->query('SELECT teamid FROM ibl_team_info WHERE teamid > 0 ORDER BY teamid LIMIT 2');
        $ids = [];
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int) $row['teamid'];
        }
        self::assertCount(2, $ids, 'seed needs at least two real teams');

        $stmt = $this->db->prepare('DELETE FROM ibl_gm_tenures WHERE franchise_id IN (?, ?)');
        self::assertNotFalse($stmt);
        $stmt->bind_param('ii', $ids[0], $ids[1]);
        $stmt->execute();
        $stmt->close();

        return [$ids[0], $ids[1]];
    }

    private function setTeamGm(int $teamid, ?string $username, string $ownerName): void
    {
        if ($username === null) {
            $stmt = $this->db->prepare('UPDATE ibl_team_info SET gm_username = NULL, owner_name = ? WHERE teamid = ?');
            self::assertNotFalse($stmt);
            $stmt->bind_param('si', $ownerName, $teamid);
        } else {
            $stmt = $this->db->prepare('UPDATE ibl_team_info SET gm_username = ?, owner_name = ? WHERE teamid = ?');
            self::assertNotFalse($stmt);
            $stmt->bind_param('ssi', $username, $ownerName, $teamid);
        }
        $stmt->execute();
        $stmt->close();
    }

    private function setOwnerName(int $teamid, string $ownerName): void
    {
        $stmt = $this->db->prepare('UPDATE ibl_team_info SET owner_name = ? WHERE teamid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('si', $ownerName, $teamid);
        $stmt->execute();
        $stmt->close();
    }

    private function setUsername(int $teamid, string $username): void
    {
        $stmt = $this->db->prepare('UPDATE ibl_team_info SET gm_username = ? WHERE teamid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('si', $username, $teamid);
        $stmt->execute();
        $stmt->close();
    }

    private function teamName(int $teamid): string
    {
        $stmt = $this->db->prepare('SELECT team_name FROM ibl_team_info WHERE teamid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $teamid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertIsArray($row);

        return (string) $row['team_name'];
    }

    private function gmHistoryName(int $prim): string
    {
        $stmt = $this->db->prepare('SELECT name FROM ibl_gm_history WHERE prim = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $prim);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertIsArray($row);

        return (string) $row['name'];
    }

    /**
     * @return list<array{gm_display_name: string, start_season_year: int, end_season_year: ?int}>
     */
    private function openTenures(int $teamid): array
    {
        $stmt = $this->db->prepare(
            'SELECT gm_display_name, start_season_year, end_season_year
             FROM ibl_gm_tenures WHERE franchise_id = ? ORDER BY id'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $teamid);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'gm_display_name' => (string) $row['gm_display_name'],
                'start_season_year' => (int) $row['start_season_year'],
                'end_season_year' => $row['end_season_year'] === null ? null : (int) $row['end_season_year'],
            ];
        }
        $stmt->close();

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function migrationStatements(): array
    {
        $files = glob(__DIR__ . '/../../migrations/*_gm_tenure_trigger_display_name.sql');
        self::assertIsArray($files);
        self::assertCount(1, $files);

        $contents = file_get_contents($files[0]);
        self::assertIsString($contents);

        $statements = [];
        $delim = ';';
        $buffer = '';
        $flush = static function () use (&$buffer, &$delim, &$statements): void {
            foreach (explode($delim, $buffer) as $piece) {
                $trimmed = trim($piece);
                if ($trimmed === '') {
                    continue;
                }
                $nonComment = array_filter(
                    explode("\n", str_replace("\r\n", "\n", $trimmed)),
                    static fn (string $line): bool => !str_starts_with(trim($line), '--') && trim($line) !== ''
                );
                if ($nonComment === []) {
                    continue;
                }
                $statements[] = $trimmed;
            }
            $buffer = '';
        };

        foreach (explode("\n", str_replace("\r\n", "\n", $contents)) as $line) {
            if (preg_match('/^DELIMITER\s+(\S+)/', $line, $m) === 1) {
                $flush();
                $delim = $m[1];
                continue;
            }
            $buffer .= $line . "\n";
        }
        $flush();

        return $statements;
    }
}
