<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Read-only coverage of the empty-backup abort in ibl5/bin/rollback-phantom-repair-run.
 *
 * That abort fires after three SELECT-only preflight checks and before
 * begin_transaction(), so the script writes nothing when all three backup tables
 * exist and are empty. The script is only spawned after a probe on the same
 * config.php connection proves that precondition.
 *
 * Deliberately extends TestCase, not DatabaseTestCase: the script opens its own
 * connection through config.php, and the gate has to observe that exact database.
 */
#[Group('database')]
final class RollbackPhantomRepairRunAbortTest extends TestCase
{
    private const BACKUP_TABLES = [
        'ibl_box_scores_teams_phantom_backup',
        'ibl_box_scores_phantom_backup',
        'ibl_sim_game_recaps_phantom_backup',
    ];

    private const LIVE_TABLES = [
        'ibl_box_scores_teams',
        'ibl_box_scores',
        'ibl_sim_game_recaps',
    ];

    private string $ibl5Dir;

    protected function setUp(): void
    {
        $resolved = realpath(__DIR__ . '/../..');
        self::assertNotFalse($resolved);
        $this->ibl5Dir = $resolved;
    }

    public function testBackupGuardRefusesUnlessAllThreeBackupsAreEmpty(): void
    {
        $allZero = array_fill_keys([...self::BACKUP_TABLES, ...self::LIVE_TABLES], 0);

        self::assertTrue(self::backupsProvablyEmpty($allZero));

        self::assertFalse(self::backupsProvablyEmpty(null));
        self::assertFalse(self::backupsProvablyEmpty([]));

        foreach (self::BACKUP_TABLES as $table) {
            self::assertFalse(
                self::backupsProvablyEmpty([...$allZero, $table => 1]),
                "A single populated backup ({$table}) must refuse"
            );
            self::assertFalse(
                self::backupsProvablyEmpty([...$allZero, $table => -1]),
                "A missing backup table ({$table}) must refuse"
            );

            $missingKey = $allZero;
            unset($missingKey[$table]);
            self::assertFalse(
                self::backupsProvablyEmpty($missingKey),
                "A probe lacking {$table} must refuse"
            );
        }
    }

    public function testEmptyBackupsAbortWithExitOneAndWriteNothing(): void
    {
        $before = $this->probe();
        if (!self::backupsProvablyEmpty($before)) {
            $detail = $before === null ? 'probe failed' : json_encode($before);
            if (getenv('CI') === 'true') {
                self::fail('Backup tables must exist and be empty on the CI database, got: ' . $detail);
            }
            // phpunit-hygiene-allow: refuses to run the destructive rollback against a DB holding backup rows
            self::markTestSkipped('backup tables not provably empty on this DB; refusing to run the rollback');
        }

        $run = $this->runCommand([PHP_BINARY, $this->ibl5Dir . '/bin/rollback-phantom-repair-run']);

        self::assertSame(1, $run['exit'], 'stdout: ' . $run['stdout'] . ' stderr: ' . $run['stderr']);
        self::assertStringContainsString('no backup rows - nothing to roll back', $run['stderr']);
        self::assertStringContainsString('backup team rows     0', $run['stdout']);

        self::assertSame($before, $this->probe(), 'The aborted run must not change any live or backup table count');
    }

    /**
     * @param array<string, int>|null $counts
     */
    private static function backupsProvablyEmpty(?array $counts): bool
    {
        if ($counts === null) {
            return false;
        }
        foreach (self::BACKUP_TABLES as $table) {
            if (!array_key_exists($table, $counts) || $counts[$table] !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Counts rows (or -1 for a missing table) over the same config.php connection the
     * script uses. SELECT-only.
     *
     * @return array<string, int>|null
     */
    private function probe(): ?array
    {
        $tables = var_export([...self::BACKUP_TABLES, ...self::LIVE_TABLES], true);
        $code = <<<PHP
<?php
declare(strict_types=1);
include {$this->exportPath('/config.php')};
include {$this->exportPath('/db/db.php')};
\$tables = {$tables};
\$out = [];
foreach (\$tables as \$table) {
    \$stmt = \$mysqli_db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    \$stmt->bind_param('s', \$table);
    \$stmt->execute();
    \$stmt->bind_result(\$exists);
    \$stmt->fetch();
    \$stmt->close();
    if ((int) \$exists === 0) {
        \$out[\$table] = -1;
        continue;
    }
    \$result = \$mysqli_db->query('SELECT COUNT(*) FROM `' . \$table . '`');
    \$row = \$result instanceof mysqli_result ? \$result->fetch_row() : null;
    \$out[\$table] = is_array(\$row) ? (int) \$row[0] : -1;
}
echo json_encode(\$out);
PHP;

        $file = sys_get_temp_dir() . '/rollback-probe-' . bin2hex(random_bytes(8)) . '.php';
        file_put_contents($file, $code);
        try {
            $run = $this->runCommand([PHP_BINARY, $file]);
        } finally {
            unlink($file);
        }

        if ($run['exit'] !== 0) {
            return null;
        }
        $decoded = json_decode(trim($run['stdout']), true);
        if (!is_array($decoded) || count($decoded) !== 6) {
            return null;
        }

        $counts = [];
        foreach ($decoded as $table => $count) {
            if (!is_string($table) || !is_int($count)) {
                return null;
            }
            $counts[$table] = $count;
        }

        return $counts;
    }

    private function exportPath(string $relative): string
    {
        return var_export($this->ibl5Dir . $relative, true);
    }

    /**
     * @param list<string> $command
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function runCommand(array $command): array
    {
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->ibl5Dir
        );
        self::assertIsResource($process);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return ['exit' => $exit, 'stdout' => $stdout, 'stderr' => $stderr];
    }
}
