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

    private const PROBE_ATTEMPTS = 3;

    private const PROBE_RETRY_SLEEP_MICROS = 1_000_000;

    private const PROBE_STDOUT_SNIPPET_CHARS = 200;

    private string $ibl5Dir;

    private string $lastProbeDiagnostic = '';

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
            $populated = $allZero;
            $populated[$table] = 1;
            self::assertFalse(
                self::backupsProvablyEmpty($populated),
                "A single populated backup ({$table}) must refuse"
            );
            $missing = $allZero;
            $missing[$table] = -1;
            self::assertFalse(
                self::backupsProvablyEmpty($missing),
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

    public function testProbeRetryReturnsCountsAfterTransientFailure(): void
    {
        $allZero = array_fill_keys([...self::BACKUP_TABLES, ...self::LIVE_TABLES], 0);
        $queue = [
            ['exit' => 1, 'stdout' => '', 'stderr' => 'mysqli_sql_exception: Connection refused'],
            self::okRun($allZero),
        ];
        $calls = 0;
        $run = static function () use (&$queue, &$calls): array {
            $calls++;

            return array_shift($queue);
        };

        $result = self::probeWithRetry($run, 0);

        self::assertSame($allZero, $result['counts']);
        self::assertSame(2, $result['attempts']);
        self::assertSame(2, $calls);
    }

    public function testProbeRetryGivesUpWithLastAttemptExitAndStderr(): void
    {
        $queue = [
            ['exit' => 0, 'stdout' => '[]', 'stderr' => 'err-1'],
            ['exit' => 0, 'stdout' => '{"ibl_box_scores":"x"}', 'stderr' => 'err-2'],
            ['exit' => 13, 'stdout' => '', 'stderr' => 'err-3'],
        ];
        $calls = 0;
        $run = static function () use (&$queue, &$calls): array {
            $calls++;

            return array_shift($queue);
        };

        $result = self::probeWithRetry($run, 0);

        self::assertNull($result['counts']);
        self::assertSame(13, $result['exit']);
        self::assertSame('err-3', $result['stderr']);
        self::assertSame('', $result['stdout']);
        self::assertSame(3, $result['attempts']);
        self::assertSame(3, $calls);
    }

    public function testProbeRetryReturnsFirstSuccessWithoutRetrying(): void
    {
        $allZero = array_fill_keys([...self::BACKUP_TABLES, ...self::LIVE_TABLES], 0);
        $changed = $allZero;
        $changed['ibl_box_scores_phantom_backup'] = 5;
        $queue = [self::okRun($changed), self::okRun($allZero)];
        $calls = 0;
        $run = static function () use (&$queue, &$calls): array {
            $calls++;

            return array_shift($queue);
        };

        $result = self::probeWithRetry($run, 0);

        self::assertSame($changed, $result['counts']);
        self::assertSame(1, $result['attempts']);
        self::assertSame(1, $calls);
    }

    public function testEmptyBackupsAbortWithExitOneAndWriteNothing(): void
    {
        $before = $this->probe();
        if (!self::backupsProvablyEmpty($before)) {
            $detail = $before === null ? 'probe failed' : json_encode($before);
            if (getenv('CI') === 'true') {
                self::fail('Backup tables must exist and be empty on the CI database, got: ' . $detail . $this->lastProbeDiagnostic);
            }
            // phpunit-hygiene-allow: refuses to run the destructive rollback against a DB holding backup rows
            self::markTestSkipped('backup tables not provably empty on this DB; refusing to run the rollback');
        }

        $run = $this->runCommand([PHP_BINARY, $this->ibl5Dir . '/bin/rollback-phantom-repair-run']);

        self::assertSame(1, $run['exit'], 'stdout: ' . $run['stdout'] . ' stderr: ' . $run['stderr']);
        self::assertStringContainsString('no backup rows - nothing to roll back', $run['stderr']);
        self::assertStringContainsString('backup team rows     0', $run['stdout']);

        self::assertSame(
            $before,
            $this->probe(),
            'The aborted run must not change any live or backup table count' . $this->lastProbeDiagnostic
        );
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
            $result = self::probeWithRetry(
                fn (): array => $this->runCommand([PHP_BINARY, $file]),
                self::PROBE_RETRY_SLEEP_MICROS
            );
        } finally {
            unlink($file);
        }

        $stderr = trim($result['stderr']);
        $stdout = mb_substr(trim($result['stdout']), 0, self::PROBE_STDOUT_SNIPPET_CHARS);
        $this->lastProbeDiagnostic = sprintf(
            ' [probe attempts: %d, last exit: %d, last stderr: %s, last stdout: %s]',
            $result['attempts'],
            $result['exit'],
            $stderr === '' ? '(empty)' : $stderr,
            $stdout === '' ? '(empty)' : $stdout
        );

        return $result['counts'];
    }

    /**
     * Retries only a failed or malformed probe. A probe that exits 0 with six
     * well-formed counts is returned on the spot, so a real row change is never
     * retried away. The pause doubles after each failed attempt.
     *
     * @param callable(): array{exit: int, stdout: string, stderr: string} $run
     * @return array{counts: array<string, int>|null, attempts: int, exit: int, stdout: string, stderr: string}
     */
    private static function probeWithRetry(callable $run, int $sleepMicros): array
    {
        $attempt = 0;
        while (true) {
            $attempt++;
            $last = $run();
            $counts = $last['exit'] === 0 ? self::decodeCounts($last['stdout']) : null;
            if ($counts !== null || $attempt >= self::PROBE_ATTEMPTS) {
                return [
                    'counts' => $counts,
                    'attempts' => $attempt,
                    'exit' => $last['exit'],
                    'stdout' => $last['stdout'],
                    'stderr' => $last['stderr'],
                ];
            }
            if ($sleepMicros > 0) {
                usleep($sleepMicros * (2 ** ($attempt - 1)));
            }
        }
    }

    /**
     * @return array<string, int>|null
     */
    private static function decodeCounts(string $stdout): ?array
    {
        $decoded = json_decode(trim($stdout), true);
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

    /**
     * @param array<string, int> $counts
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private static function okRun(array $counts): array
    {
        return ['exit' => 0, 'stdout' => json_encode($counts, JSON_THROW_ON_ERROR), 'stderr' => ''];
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
