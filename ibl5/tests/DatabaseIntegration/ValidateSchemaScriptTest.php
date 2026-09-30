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
 */
#[Group('database')]
final class ValidateSchemaScriptTest extends TestCase
{
    private string $ibl5Dir;
    private \mysqli $db;
    private string $scratchDb;

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

    protected function tearDown(): void
    {
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
     * @param array<string, string> $overrides
     * @return array{code: int, stdout: string, stderr: string}
     */
    private function runScript(array $overrides): array
    {
        $env = array_merge(getenv(), $overrides);

        $process = proc_open(
            [PHP_BINARY, $this->ibl5Dir . '/bin/validate-schema'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->ibl5Dir,
            $env
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

        $result = $this->runScript(['DB_NAME' => $this->scratchDb]);

        self::assertSame(0, $result['code'], $result['stdout'] . $result['stderr']);
        self::assertSame('Schema validation passed (' . count($assertions) . " assertions).\n", $result['stdout']);
    }

    #[Test]
    public function testExitsOneAndNamesTheSingleMissingColumn(): void
    {
        $target = $this->pickDriftTarget($this->loadAssertions());
        $this->buildSchema($target);

        $result = $this->runScript(['DB_NAME' => $this->scratchDb]);

        self::assertSame(1, $result['code'], $result['stdout'] . $result['stderr']);
        self::assertStringContainsString('SCHEMA VALIDATION FAILED:', $result['stdout']);
        self::assertStringContainsString('  MISSING: ' . $target->table . '.' . $target->column, $result['stdout']);
        self::assertStringContainsString('1 missing column(s) detected.', $result['stdout']);
        self::assertSame(1, substr_count($result['stdout'], 'MISSING:'));
    }

    #[Test]
    public function testConnectFailureExitsNonZeroAndDistinctFromDrift(): void
    {
        // A non-empty user is required: config.php reads getenv('DB_USER') ?: 'root'.
        $result = $this->runScript([
            'DB_NAME' => $this->scratchDb,
            'DB_USER' => 'ibl_no_such_user_' . bin2hex(random_bytes(4)),
        ]);

        self::assertNotSame(0, $result['code']);
        self::assertNotSame(1, $result['code']);
        self::assertStringNotContainsString('Schema validation passed', $result['stdout']);
        self::assertMatchesRegularExpression(
            '/mysqli_sql_exception|Failed to connect to MariaDB/',
            $result['stdout'] . $result['stderr']
        );
    }
}
