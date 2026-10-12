<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Migration\SchemaAssertion;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real bin/validate-schema as a child process against a scratch database built
 * from config/schema-assertions.php. Extends TestCase directly, not DatabaseTestCase:
 * CREATE DATABASE and CREATE TABLE commit implicitly, so the transaction wrapper is moot.
 * The shared migrated database is never altered; the scratch database is dropped in tearDown.
 *
 * The script reads credentials from config.php, which in CI requires a config.local.php with
 * hardcoded values and ignores DB_* env vars. So each run copies the script into a sandbox
 * tree whose own config.php points at the scratch database; vendor/, classes/ and config/
 * are symlinks back to the real tree.
 */
#[Group('database')]
final class ValidateSchemaScriptTest extends TestCase
{
    private string $ibl5Dir;
    private \mysqli $db;
    private string $scratchDb;
    private string $sandboxDir = '';

    protected function setUp(): void
    {
        $this->ibl5Dir = dirname(__DIR__, 2);

        $host = $this->requireEnv('DB_HOST');
        $user = $this->requireEnv('DB_USER');
        $pass = $this->requireEnv('DB_PASS');
        $name = $this->requireEnv('DB_NAME');

        $this->db = new \mysqli();
        $this->db->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
        $this->db->real_connect($host, $user, $pass, $name);
        if ($this->db->connect_errno !== 0) {
            self::fail('Database connection failed: ' . $this->db->connect_error);
        }

        $this->scratchDb = 'ibl_vs_' . bin2hex(random_bytes(6));
        $this->db->query('CREATE DATABASE `' . $this->scratchDb . '`');
        $this->db->select_db($this->scratchDb);
    }

    private function removeSandbox(): void
    {
        if ($this->sandboxDir === '' || !is_dir($this->sandboxDir)) {
            return;
        }
        $ibl5 = $this->sandboxDir . '/ibl5';
        foreach (['vendor', 'classes', 'config', 'config.php', 'bin/validate-schema'] as $entry) {
            if (is_link($ibl5 . '/' . $entry) || is_file($ibl5 . '/' . $entry)) {
                unlink($ibl5 . '/' . $entry);
            }
        }
        foreach ([$ibl5 . '/bin', $ibl5, $this->sandboxDir] as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    protected function tearDown(): void
    {
        $this->removeSandbox();
        if (isset($this->db)) {
            try {
                if (isset($this->scratchDb)) {
                    $this->db->query('DROP DATABASE IF EXISTS `' . $this->scratchDb . '`');
                }
            } finally {
                $this->db->close();
            }
        }
    }

    private function requireEnv(string $key): string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            self::fail("{$key} must be set");
        }

        return $value;
    }

    private function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * @return list<SchemaAssertion>
     */
    private function loadAssertions(): array
    {
        /** @var list<SchemaAssertion> $assertions */
        $assertions = require $this->ibl5Dir . '/config/schema-assertions.php';

        return $assertions;
    }

    /**
     * Builds every asserted table and column in the scratch database, minus $omit, plus one
     * unasserted column and one unasserted table the validator must ignore.
     */
    private function buildSchema(?SchemaAssertion $omit): void
    {
        /** @var array<string, list<string>> $columnsByTable */
        $columnsByTable = [];
        foreach ($this->loadAssertions() as $assertion) {
            if ($omit !== null && $assertion->table === $omit->table && $assertion->column === $omit->column) {
                continue;
            }
            $columnsByTable[$assertion->table][] = $assertion->column;
        }

        $isFirstTable = true;
        foreach ($columnsByTable as $table => $columns) {
            $defs = array_map(fn (string $column): string => $this->quote($column) . ' INT NULL', $columns);
            if ($isFirstTable) {
                $defs[] = '`zz_unasserted_extra` INT NULL';
                $isFirstTable = false;
            }
            $this->db->query('CREATE TABLE ' . $this->quote($table) . ' (' . implode(', ', $defs) . ')');
        }
        $this->db->query('CREATE TABLE `zz_unasserted_table` (`id` INT NULL)');
    }

    /**
     * @param list<SchemaAssertion> $assertions
     */
    private function pickDriftTarget(array $assertions): SchemaAssertion
    {
        $counts = [];
        foreach ($assertions as $assertion) {
            $counts[$assertion->table] = ($counts[$assertion->table] ?? 0) + 1;
        }
        foreach ($assertions as $assertion) {
            if ($counts[$assertion->table] >= 2) {
                return $assertion;
            }
        }
        self::fail('No table with at least 2 asserted columns in config/schema-assertions.php');
    }

    /**
     * Builds the sandbox tree and returns the path of the copied script.
     */
    private function buildSandbox(string $dbUser): string
    {
        $this->sandboxDir = sys_get_temp_dir() . '/ibl_vs_' . bin2hex(random_bytes(6));
        $ibl5 = $this->sandboxDir . '/ibl5';
        self::assertTrue(mkdir($ibl5 . '/bin', 0777, true));
        foreach (['vendor', 'classes', 'config'] as $dir) {
            self::assertTrue(symlink($this->ibl5Dir . '/' . $dir, $ibl5 . '/' . $dir));
        }
        $config = sprintf(
            "<?php\n\$dbhost = %s;\n\$dbuname = %s;\n\$dbpass = %s;\n\$dbname = %s;\n",
            var_export($this->requireEnv('DB_HOST'), true),
            var_export($dbUser, true),
            var_export($this->requireEnv('DB_PASS'), true),
            var_export($this->scratchDb, true)
        );
        self::assertNotFalse(file_put_contents($ibl5 . '/config.php', $config));
        self::assertTrue(copy($this->ibl5Dir . '/bin/validate-schema', $ibl5 . '/bin/validate-schema'));

        return $ibl5 . '/bin/validate-schema';
    }

    /**
     * @return array{code: int, stdout: string, stderr: string}
     */
    private function runScript(?string $dbUser = null): array
    {
        $script = $this->buildSandbox($dbUser ?? $this->requireEnv('DB_USER'));

        $process = proc_open(
            [PHP_BINARY, $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname($script, 2)
        );
        self::assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['code' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
    }

    #[Test]
    public function testExitsZeroWhenEveryAssertedColumnExists(): void
    {
        $assertions = $this->loadAssertions();
        self::assertGreaterThan(0, count($assertions));
        $this->buildSchema(null);

        $result = $this->runScript();

        self::assertSame(0, $result['code'], $result['stdout'] . $result['stderr']);
        self::assertSame('Schema validation passed (' . count($assertions) . " assertions).\n", $result['stdout']);
    }

    #[Test]
    public function testExitsOneAndNamesTheSingleMissingColumn(): void
    {
        $target = $this->pickDriftTarget($this->loadAssertions());
        $this->buildSchema($target);

        $result = $this->runScript();

        self::assertSame(1, $result['code'], $result['stdout'] . $result['stderr']);
        self::assertStringContainsString('SCHEMA VALIDATION FAILED:', $result['stdout']);
        self::assertStringContainsString('  MISSING: ' . $target->table . '.' . $target->column, $result['stdout']);
        self::assertStringContainsString('1 missing column(s) detected.', $result['stdout']);
        self::assertSame(1, substr_count($result['stdout'], 'MISSING:'));
    }

    /**
     * A failed connect must exit 2 with one clean stderr line. The script wraps only the
     * real_connect() call in a try/catch for mysqli_sql_exception, which PHP 8.1+ throws under
     * the default report mode. Exit 2 stays distinct from 1 (drift) and 0 (pass), and no PHP
     * fatal or stack trace reaches the caller.
     */
    #[Test]
    public function testConnectFailureExitsTwoWithCleanMessage(): void
    {
        $result = $this->runScript('ibl_no_such_user_' . bin2hex(random_bytes(4)));
        $combined = $result['stdout'] . $result['stderr'];

        self::assertSame(2, $result['code'], $combined);
        self::assertStringContainsString('Failed to connect to MariaDB (', $result['stderr']);
        self::assertStringNotContainsString('Schema validation passed', $result['stdout']);
        self::assertStringNotContainsString('SCHEMA VALIDATION FAILED', $result['stdout']);
        self::assertStringNotContainsString('mysqli_sql_exception', $combined);
        self::assertStringNotContainsString('Stack trace', $combined);
        self::assertStringNotContainsString('Fatal error', $combined);
    }
}
