<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Proves the apply-time guard and the resulting types for narrowing the
 * ibl_olympics_career_* int(11) stat columns to the ibl_plr.car_* unsigned types
 * (backlog#218).
 *
 * The guard tests run the migration's OWN guard text against int(11) probe tables,
 * so editing the guard in the migration is what fails them.
 *
 * Layout contract on the migration: backticked table names inside each guard,
 * each guard is one `SELECT IF(` statement with no `;` inside, both guards precede
 * either ALTER.
 */
#[Group('database')]
final class OlympicsCareerIntWidthGuardTest extends DatabaseTestCase
{
    private const MIGRATIONS_DIR = __DIR__ . '/../../migrations';

    private const SMALLINT_UNSIGNED_MAX = 65535;

    private const MEDIUMINT_UNSIGNED_MAX = 16777215;

    private const TOTALS_TABLE = 'ibl_olympics_career_totals';

    private const AVGS_TABLE = 'ibl_olympics_career_avgs';

    /** @var list<string> */
    private const TOTALS_MEDIUMINT_COLUMNS = [
        'minutes', 'fgm', 'fga', 'ftm', 'fta', 'tgm', 'tga',
        'orb', 'reb', 'ast', 'stl', 'tvr', 'blk', 'pf', 'pts',
    ];

    /** @var array<string, string> */
    private const CAR_COLUMN_MAP = [
        'games' => 'car_gm',
        'minutes' => 'car_min',
        'fgm' => 'car_fgm',
        'fga' => 'car_fga',
        'ftm' => 'car_ftm',
        'fta' => 'car_fta',
        'tgm' => 'car_3gm',
        'tga' => 'car_3ga',
        'orb' => 'car_orb',
        'reb' => 'car_reb',
        'ast' => 'car_ast',
        'stl' => 'car_stl',
        'tvr' => 'car_tvr',
        'blk' => 'car_blk',
        'pf' => 'car_pf',
        'pts' => 'car_pts',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->query("SET SESSION sql_mode = ''");
    }

    #[DataProvider('totalsViolationProvider')]
    public function testTotalsGuardAbortsOnViolatingValue(string $column, int $value): void
    {
        $this->createTotalsProbe('_oc_totals_probe');

        $stmt = $this->db->prepare("INSERT INTO `_oc_totals_probe` (`$column`) VALUES (?)");
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $value);
        $stmt->execute();
        $stmt->close();

        try {
            $this->runGuard($this->guardStatementFor(self::TOTALS_TABLE, '_oc_totals_probe'));
            self::fail("Totals guard should have raised error 1242 for $column = $value");
        } catch (\mysqli_sql_exception $e) {
            self::assertSame(1242, $e->getCode());
        }
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function totalsViolationProvider(): array
    {
        $cases = [];
        $columns = array_merge(['games'], self::TOTALS_MEDIUMINT_COLUMNS);
        foreach ($columns as $column) {
            $over = $column === 'games'
                ? self::SMALLINT_UNSIGNED_MAX + 1
                : self::MEDIUMINT_UNSIGNED_MAX + 1;
            $cases["$column over bound"] = [$column, $over];
            $cases["$column negative"] = [$column, -1];
        }

        return $cases;
    }

    public function testTotalsGuardPassesAtExactBound(): void
    {
        $this->createTotalsProbe('_oc_totals_probe_bound');

        $columns = self::TOTALS_MEDIUMINT_COLUMNS;
        $assignments = ['`games` = ' . self::SMALLINT_UNSIGNED_MAX];
        foreach ($columns as $column) {
            $assignments[] = "`$column` = " . self::MEDIUMINT_UNSIGNED_MAX;
        }
        $this->db->query('INSERT INTO `_oc_totals_probe_bound` SET ' . implode(', ', $assignments));

        $guard = $this->guardStatementFor(self::TOTALS_TABLE, '_oc_totals_probe_bound');
        self::assertSame(0, $this->runGuard($guard), 'Values at the exact unsigned max must pass the guard');
    }

    public function testTotalsGuardPassesOnEmptyTable(): void
    {
        $this->createTotalsProbe('_oc_totals_probe_empty');

        $guard = $this->guardStatementFor(self::TOTALS_TABLE, '_oc_totals_probe_empty');
        self::assertSame(0, $this->runGuard($guard), 'An empty table must take the false branch (no false-abort)');
    }

    #[DataProvider('avgsViolationProvider')]
    public function testAvgsGuardAbortsOnViolatingGames(int $value): void
    {
        $this->createAvgsProbe('_oc_avgs_probe');

        $stmt = $this->db->prepare('INSERT INTO `_oc_avgs_probe` (`games`) VALUES (?)');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $value);
        $stmt->execute();
        $stmt->close();

        try {
            $this->runGuard($this->guardStatementFor(self::AVGS_TABLE, '_oc_avgs_probe'));
            self::fail("Avgs guard should have raised error 1242 for games = $value");
        } catch (\mysqli_sql_exception $e) {
            self::assertSame(1242, $e->getCode());
        }
    }

    /**
     * @return array<string, array{int}>
     */
    public static function avgsViolationProvider(): array
    {
        return [
            'over bound' => [self::SMALLINT_UNSIGNED_MAX + 1],
            'negative' => [-1],
        ];
    }

    public function testAvgsGuardPassesAtExactBound(): void
    {
        $this->createAvgsProbe('_oc_avgs_probe_bound');
        $this->db->query('INSERT INTO `_oc_avgs_probe_bound` (`games`) VALUES (' . self::SMALLINT_UNSIGNED_MAX . ')');

        $guard = $this->guardStatementFor(self::AVGS_TABLE, '_oc_avgs_probe_bound');
        self::assertSame(0, $this->runGuard($guard), 'games at the exact smallint unsigned max must pass the guard');
    }

    public function testMigrationSourceContractGuardBeforeAlter(): void
    {
        $filename = $this->migrationFile();
        $source = $this->migrationSource($filename);
        $ddl = $this->migrationDdl($filename);

        self::assertStringContainsString(
            '(SELECT 1 UNION SELECT 2)',
            $source,
            'Migration must use the mode-independent UNION-subquery idiom'
        );

        self::assertStringNotContainsStringIgnoringCase(
            'STRICT_ALL_TABLES',
            $ddl,
            'Migration must NOT use STRICT_ALL_TABLES (does not abort on prod\'s empty sql_mode)'
        );
        self::assertStringNotContainsStringIgnoringCase(
            'CAST(',
            $ddl,
            'Migration must NOT use CAST-as-guard (warn-only under non-strict mode)'
        );

        $unionPos = strpos($ddl, 'UNION SELECT');
        $alterPos = stripos($ddl, 'MODIFY COLUMN');
        self::assertNotFalse($unionPos, 'UNION SELECT not found in migration DDL');
        self::assertNotFalse($alterPos, 'MODIFY COLUMN not found in migration DDL');
        self::assertLessThan(
            $alterPos,
            $unionPos,
            'Guard (UNION SELECT) must appear BEFORE the first ALTER MODIFY COLUMN'
        );

        self::assertStringContainsStringIgnoringCase(
            'information_schema',
            $ddl,
            'Migration must use information_schema gate for idempotency'
        );
        self::assertStringContainsStringIgnoringCase(
            'PREPARE',
            $ddl,
            'Migration must use PREPARE/EXECUTE/DEALLOCATE for idempotent DDL'
        );
        self::assertStringContainsStringIgnoringCase('smallint(5) unsigned', $ddl);
        self::assertStringContainsStringIgnoringCase('mediumint(8) unsigned', $ddl);
    }

    #[DataProvider('narrowedColumnProvider')]
    public function testOlympicsCareerColumnIsNarrowed(string $table, string $column, string $expectedType): void
    {
        self::assertSame(
            $expectedType,
            $this->columnType($table, $column),
            "$table.$column must be $expectedType after the narrowing migration"
        );

        $stmt = $this->db->prepare(
            "SELECT IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        /** @var array{IS_NULLABLE: string, COLUMN_DEFAULT: string|null, COLUMN_COMMENT: string}|null $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertIsArray($row, "Column $table.$column not found");
        self::assertSame('NO', $row['IS_NULLABLE'], "$table.$column must stay NOT NULL");
        self::assertSame('0', $row['COLUMN_DEFAULT'], "$table.$column must keep DEFAULT 0");
        self::assertNotSame('', $row['COLUMN_COMMENT'], "$table.$column must keep its COMMENT");
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function narrowedColumnProvider(): array
    {
        $cases = [
            'avgs games' => [self::AVGS_TABLE, 'games', 'smallint(5) unsigned'],
            'totals games' => [self::TOTALS_TABLE, 'games', 'smallint(5) unsigned'],
        ];
        foreach (self::TOTALS_MEDIUMINT_COLUMNS as $column) {
            $cases["totals $column"] = [self::TOTALS_TABLE, $column, 'mediumint(8) unsigned'];
        }

        return $cases;
    }

    #[DataProvider('carParityProvider')]
    public function testTotalsTypesMatchPlayerCareerColumns(string $column, string $carColumn): void
    {
        self::assertSame(
            $this->columnType('ibl_plr', $carColumn),
            $this->columnType(self::TOTALS_TABLE, $column),
            "ibl_olympics_career_totals.$column must match ibl_plr.$carColumn"
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function carParityProvider(): array
    {
        $cases = [];
        foreach (self::CAR_COLUMN_MAP as $column => $carColumn) {
            $cases["$column vs $carColumn"] = [$column, $carColumn];
        }

        return $cases;
    }

    public function testMigrationReapplyIsNoOp(): void
    {
        $source = $this->migrationSource($this->migrationFile());

        $this->db->multi_query($source);
        do {
            $result = $this->db->store_result();
            if ($result !== false) {
                $result->free();
            }
        } while ($this->db->more_results() && $this->db->next_result());

        $result = $this->db->query('SELECT @oc_totals_needs_alter AS t, @oc_avgs_needs_alter AS a');
        self::assertNotFalse($result);
        /** @var array{t: string|int|null, a: string|int|null}|null $row */
        $row = $result->fetch_assoc();
        self::assertIsArray($row);
        self::assertSame(0, (int) $row['t'], 'Totals gate must find nothing to alter on a re-apply');
        self::assertSame(0, (int) $row['a'], 'Avgs gate must find nothing to alter on a re-apply');
        self::assertSame('mediumint(8) unsigned', $this->columnType(self::TOTALS_TABLE, 'pts'));

        // Control: a gate comparing against 'smallint(5)' (no `unsigned`) would count this column
        // as needing an ALTER on every re-apply, so the real type must differ from that string.
        $control = $this->db->query(
            "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ibl_olympics_career_totals'
               AND COLUMN_NAME = 'games' AND COLUMN_TYPE <> 'smallint(5)'"
        );
        self::assertNotFalse($control);
        /** @var array{c: string|int}|null $controlRow */
        $controlRow = $control->fetch_assoc();
        self::assertIsArray($controlRow);
        self::assertSame(1, (int) $controlRow['c'], 'games must carry the unsigned suffix the gate compares against');
    }

    private function createTotalsProbe(string $name): void
    {
        $columns = array_merge(['games'], self::TOTALS_MEDIUMINT_COLUMNS);
        $defs = ['pid int(11) NOT NULL DEFAULT 0'];
        foreach ($columns as $column) {
            $defs[] = "`$column` int(11) NOT NULL DEFAULT 0";
        }

        // Plain int(11) probe, never LIKE the real table: after the migration LIKE copies the
        // unsigned narrow types and a violating insert would clamp instead of reaching the guard.
        $this->db->query("CREATE TEMPORARY TABLE `$name` (" . implode(', ', $defs) . ')');
    }

    private function createAvgsProbe(string $name): void
    {
        $this->db->query(
            "CREATE TEMPORARY TABLE `$name` (pid int(11) NOT NULL DEFAULT 0, games int(11) NOT NULL DEFAULT 0)"
        );
    }

    private function runGuard(string $sql): int
    {
        $result = $this->db->query($sql);
        self::assertNotFalse($result);
        $row = $result->fetch_row();
        self::assertIsArray($row);

        return (int) $row[0];
    }

    /**
     * Extracts the migration's own guard for $table and points it at the probe table.
     */
    private function guardStatementFor(string $table, string $probe): string
    {
        $chunks = explode(';', $this->migrationDdl($this->migrationFile()));
        $matches = [];
        foreach ($chunks as $chunk) {
            $trimmed = ltrim($chunk);
            if (stripos($trimmed, 'SELECT IF(') === 0 && str_contains($trimmed, "`$table`")) {
                $matches[] = $trimmed;
            }
        }
        self::assertCount(1, $matches, "Expected exactly one SELECT IF( guard for `$table`");

        $count = 0;
        $rewritten = str_replace("`$table`", "`$probe`", $matches[0], $count);
        self::assertGreaterThanOrEqual(1, $count, 'Guard must reference the table in backticks');

        return $rewritten;
    }

    private function migrationFile(): string
    {
        $found = glob(self::MIGRATIONS_DIR . '/*_narrow_olympics_career_int_types.sql');
        self::assertIsArray($found);
        self::assertCount(1, $found, 'Expected exactly one *_narrow_olympics_career_int_types.sql migration');

        return basename($found[0]);
    }

    private function columnType(string $table, string $column): string
    {
        $stmt = $this->db->prepare(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        /** @var array{COLUMN_TYPE: string}|null $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertIsArray($row, "Column $table.$column not found");

        return $row['COLUMN_TYPE'];
    }

    private function migrationSource(string $file): string
    {
        $path = self::MIGRATIONS_DIR . '/' . $file;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function migrationDdl(string $file): string
    {
        $split = preg_split('/\R/', $this->migrationSource($file));
        $lines = $split === false ? [] : $split;
        $ddl = array_filter(
            $lines,
            static fn (string $line): bool => !str_starts_with(ltrim($line), '--')
        );

        return implode("\n", $ddl);
    }
}
