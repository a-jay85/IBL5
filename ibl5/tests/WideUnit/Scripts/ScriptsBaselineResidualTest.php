<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Ratchet over the `scripts/` entries in phpstan-baseline.neon: scripts whose errors were
 * fixed in source must not get a suppression back, and the two admin scripts may only
 * keep their auth-gate-line entries. The plr scripts are deliberately not pinned.
 */
final class ScriptsBaselineResidualTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function clearedScriptProvider(): array
    {
        $paths = [
            'scripts/simRecapContext.php',
            'scripts/simRecapQueue.php',
            'scripts/storeSimRecap.php',
            'scripts/generate_api_key.php',
            'scripts/jsbExport.php',
            'scripts/reconstructPlr.php',
            'scripts/bulkJsbImport.php',
            'scripts/build-engine-bundle.php',
            'scripts/runEngineShadow.php',
            'scripts/archive/importBxsMissing.php',
            'scripts/archive/plrScratchpad.php',
        ];

        $rows = [];
        foreach ($paths as $path) {
            $rows[$path] = [$path];
        }

        return $rows;
    }

    #[DataProvider('clearedScriptProvider')]
    public function testClearedScriptsHaveNoBaselineEntries(string $path): void
    {
        self::assertArrayNotHasKey($path, self::scriptCounts());
    }

    public function testAdminScriptsKeepOnlyGateLineEntries(): void
    {
        $counts = self::scriptCounts();

        self::assertLessThanOrEqual(3, $counts['scripts/allStarRename.php'] ?? 0);
        self::assertLessThanOrEqual(4, $counts['scripts/updateAllTheThings.php'] ?? 0);
    }

    public function testBaselineParserFindsScriptEntries(): void
    {
        $counts = self::scriptCounts();

        self::assertNotSame([], $counts);
        self::assertArrayHasKey('scripts/allStarRename.php', $counts);
    }

    /** @return array<string, int> */
    private static function scriptCounts(): array
    {
        // phpunit-hygiene-allow: the baseline neon is the data under test, not production code
        $neon = file_get_contents(dirname(__DIR__, 3) . '/phpstan-baseline.neon');
        self::assertNotFalse($neon, 'Unable to read phpstan-baseline.neon');

        preg_match_all('/count:\s*(\d+)\s*\n\s*path:\s*(scripts\/\S+)/', $neon, $matches, PREG_SET_ORDER);

        $counts = [];
        foreach ($matches as $match) {
            $counts[$match[2]] = ($counts[$match[2]] ?? 0) + (int) $match[1];
        }

        return $counts;
    }
}
