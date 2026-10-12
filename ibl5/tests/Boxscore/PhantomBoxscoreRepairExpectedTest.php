<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\PhantomBoxscoreRepair;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins the production snapshot literals in PhantomBoxscoreRepair::EXPECTED.
 *
 * Changing any value in EXPECTED requires updating this file and
 * Tests\DatabaseIntegration\PhantomBoxscoreRepairTest::testDefaultConstructedRepairGatesOnProductionExpected.
 * The PR must attach the migration-168 dry-run output that measured the new value.
 *
 * Also enforces the contract that the constructor's `$expectedOverride` is a test-only
 * seam: no production call site under classes/, migrations/, bin/ or scripts/ may pass it.
 */
#[CoversClass(PhantomBoxscoreRepair::class)]
final class PhantomBoxscoreRepairExpectedTest extends TestCase
{
    private const MIGRATION_168 = 'migrations/168_delete_phantom_season2008_boxscores.php';

    /**
     * @return array<string, int>
     */
    private static function expected(): array
    {
        /** @var array<string, int> $value */
        $value = (new \ReflectionClassConstant(PhantomBoxscoreRepair::class, 'EXPECTED'))->getValue();

        return $value;
    }

    /**
     * @return array<string, int>
     */
    private static function fixtureExpected(): array
    {
        /** @var array<string, int> $value */
        $value = (new \ReflectionClassConstant(
            \Tests\DatabaseIntegration\PhantomBoxscoreRepairTest::class,
            'FIXTURE_EXPECTED'
        ))->getValue();

        return $value;
    }

    public function testExpectedPinsProductionSnapshotLiterals(): void
    {
        self::assertSame(
            [
                'orphan_games' => 618,
                'orphan_team_rows' => 1236,
                'duplicate_triple_games' => 3,
                'duplicate_team_rows' => 6,
                'player_rows' => 14502,
                'recap_rows' => 20,
            ],
            self::expected(),
            'PhantomBoxscoreRepair::EXPECTED changed. Update this pin only alongside a migration-168 dry-run report that measured the new value.'
        );
    }

    public function testTeamRowCountsAreTwiceTheirGameCounts(): void
    {
        foreach (['EXPECTED' => self::expected(), 'FIXTURE_EXPECTED' => self::fixtureExpected()] as $name => $counts) {
            self::assertSame(
                2 * $counts['orphan_games'],
                $counts['orphan_team_rows'],
                $name . ': each orphan game is one visitor row plus one home row'
            );
            self::assertSame(
                2 * $counts['duplicate_triple_games'],
                $counts['duplicate_team_rows'],
                $name . ': each duplicate-triple game is one visitor row plus one home row'
            );
        }
    }

    public function testExpectedKeySetMatchesFixtureExpected(): void
    {
        $productionKeys = array_keys(self::expected());
        $fixtureKeys = array_keys(self::fixtureExpected());
        sort($productionKeys);
        sort($fixtureKeys);

        self::assertSame($fixtureKeys, $productionKeys);
        self::assertCount(6, $productionKeys);

        foreach ([self::expected(), self::fixtureExpected()] as $counts) {
            foreach ($counts as $key => $value) {
                self::assertIsInt($value, $key . ' must be an int');
                self::assertGreaterThanOrEqual(0, $value, $key . ' must be non-negative');
            }
        }
    }

    public function testNoProductionCallSitePassesExpectedOverride(): void
    {
        $root = dirname(__DIR__, 2);
        $sites = [];

        foreach (['classes', 'migrations', 'bin', 'scripts'] as $dir) {
            $path = $root . '/' . $dir;
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }
                $filePath = $file->getPathname();
                if (str_contains($filePath, '/vendor/')) {
                    continue;
                }
                $source = file_get_contents($filePath);
                if ($source === false) {
                    continue;
                }
                $argLists = self::extractConstructorArgumentLists($source);
                if ($argLists !== []) {
                    $sites[substr($filePath, strlen($root) + 1)] = $argLists;
                }
            }
        }

        self::assertNotSame([], $sites, 'No PhantomBoxscoreRepair call site found; the scan regex may have rotted.');
        self::assertArrayHasKey(self::MIGRATION_168, $sites, 'Migration 168 must construct PhantomBoxscoreRepair.');
        self::assertSame(
            2,
            self::countTopLevelArguments($sites[self::MIGRATION_168][0]),
            'Migration 168 must pass exactly 2 constructor arguments.'
        );

        foreach ($sites as $relativePath => $argLists) {
            foreach ($argLists as $argList) {
                self::assertFalse(
                    self::passesExpectedOverride($argList),
                    $relativePath . ' passes the test-only $expectedOverride: ' . $argList
                );
            }
        }
    }

    #[DataProvider('argumentListProvider')]
    public function testArgumentCounterAndOverrideDetection(string $argList, int $expectedCount, bool $expectedOverride): void
    {
        self::assertSame($expectedCount, self::countTopLevelArguments($argList));
        self::assertSame($expectedOverride, self::passesExpectedOverride($argList));
    }

    /**
     * @return array<string, array{string, int, bool}>
     */
    public static function argumentListProvider(): array
    {
        return [
            'migration 168 shape' => ['$mysqli_db, new Boxscore\BoxscoreRepository($mysqli_db)', 2, false],
            'three args with transaction flag' => ['$db, $repo, false', 3, false],
            'four positional args' => ['$db, $repo, false, [\'player_rows\' => 1]', 4, true],
            'named expectedOverride' => ['$db, $repo, expectedOverride: $counts', 3, true],
            'nested array commas count once' => ['$db, [1, 2, 3]', 2, false],
            'empty argument list' => ['', 0, false],
        ];
    }

    /**
     * Returns the raw text between the parentheses of every `new ...PhantomBoxscoreRepair(` call.
     *
     * Known limit: string literals are not parsed, so a comma or bracket inside a quoted
     * argument would miscount. Every current production call site passes variables and
     * `new` expressions only.
     *
     * @return list<string>
     */
    private static function extractConstructorArgumentLists(string $source): array
    {
        $lists = [];
        if (preg_match_all(
            '/new\s+\\\\?(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*PhantomBoxscoreRepair\s*\(/',
            $source,
            $matches,
            PREG_OFFSET_CAPTURE
        ) === false) {
            return $lists;
        }

        $length = strlen($source);
        foreach ($matches[0] as $match) {
            $start = $match[1] + strlen($match[0]);
            $depth = 1;
            for ($i = $start; $i < $length; $i++) {
                $char = $source[$i];
                if ($char === '(' || $char === '[' || $char === '{') {
                    $depth++;
                } elseif ($char === ')' || $char === ']' || $char === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $lists[] = substr($source, $start, $i - $start);
                        break;
                    }
                }
            }
        }

        return $lists;
    }

    /**
     * Counts arguments at bracket depth 0. A trailing comma does not add an argument.
     * Same string-literal limit as extractConstructorArgumentLists().
     */
    private static function countTopLevelArguments(string $argList): int
    {
        $argList = trim($argList);
        if ($argList === '') {
            return 0;
        }

        $count = 1;
        $depth = 0;
        $length = strlen($argList);
        for ($i = 0; $i < $length; $i++) {
            $char = $argList[$i];
            if ($char === '(' || $char === '[' || $char === '{') {
                $depth++;
            } elseif ($char === ')' || $char === ']' || $char === '}') {
                $depth--;
            } elseif ($char === ',' && $depth === 0 && trim(substr($argList, $i + 1)) !== '') {
                $count++;
            }
        }

        return $count;
    }

    private static function passesExpectedOverride(string $argList): bool
    {
        return self::countTopLevelArguments($argList) >= 4
            || preg_match('/\bexpectedOverride\s*:/', $argList) === 1;
    }
}
