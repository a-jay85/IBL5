<?php

declare(strict_types=1);

namespace Tests\Module;

use PHPUnit\Framework\TestCase;

/**
 * Ratchet: a module entry point may not compose its own object graph.
 *
 * Module `index.php` files resolve a factory through `ModuleServices` instead of
 * calling `new` or reading the `$mysqli_db` global. ALLOWLIST holds the entry
 * points that still do; it can only shrink. A converted module that stays listed
 * fails testAllowlistHasNoStaleEntries, and a new module that composes its own
 * graph fails testNoUnlistedModuleComposesItsOwnGraph.
 *
 * The detector is token based: comments and string literals never count.
 */
class ModuleCompositionRootRatchetTest extends TestCase
{
    /**
     * Module directories whose index.php still holds a T_NEW token or a
     * `$mysqli_db` variable. Empty: every module resolves through ModuleServices.
     *
     * @var list<string>
     */
    public const ALLOWLIST = [];

    /**
     * @return list<string>
     */
    public static function allowlist(): array
    {
        return self::ALLOWLIST;
    }

    private static function ibl5Root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Violations the detector finds in a PHP source string.
     *
     * @return list<string> One entry per `new` token or `$mysqli_db` variable, with its line
     */
    private static function detectOwnComposition(string $source): array
    {
        $violations = [];
        foreach (token_get_all($source) as $token) {
            if (!is_array($token)) {
                continue;
            }
            if ($token[0] === T_NEW) {
                $violations[] = 'new (line ' . $token[2] . ')';
            } elseif ($token[0] === T_VARIABLE && $token[1] === '$mysqli_db') {
                $violations[] = '$mysqli_db (line ' . $token[2] . ')';
            }
        }

        return $violations;
    }

    /**
     * Module directory name => detector violations, for every modules/<dir>/index.php.
     *
     * @return array<string, list<string>>
     */
    private static function scanModules(): array
    {
        $files = glob(self::ibl5Root() . '/modules/*/index.php');
        self::assertNotFalse($files);
        self::assertNotEmpty($files, 'No module entry points found; the scan path is wrong');

        $result = [];
        foreach ($files as $file) {
            $source = file_get_contents($file);
            self::assertNotFalse($source);
            $result[basename(dirname($file))] = self::detectOwnComposition($source);
        }

        return $result;
    }

    public function testNoUnlistedModuleComposesItsOwnGraph(): void
    {
        $unlisted = [];
        foreach (self::scanModules() as $module => $violations) {
            if ($violations !== [] && !in_array($module, self::allowlist(), true)) {
                $unlisted[] = $module . ' [' . implode(', ', $violations) . ']';
            }
        }

        $this->assertSame(
            [],
            $unlisted,
            'These modules compose their own object graph in index.php. Resolve a factory through '
            . 'ModuleServices instead; see ibl5/classes/Module/README.md § Module composition.'
        );
    }

    public function testAllowlistHasNoStaleEntries(): void
    {
        $scan = self::scanModules();

        $stale = [];
        foreach (self::allowlist() as $module) {
            if (!array_key_exists($module, $scan)) {
                $stale[] = $module . ' (no modules/' . $module . '/index.php)';
            } elseif ($scan[$module] === []) {
                $stale[] = $module . ' (no longer composes its own graph)';
            }
        }

        $this->assertSame(
            [],
            $stale,
            'Remove these entries from ModuleCompositionRootRatchetTest::ALLOWLIST; the list can only shrink.'
        );
    }

    public function testAllowlistIsEmpty(): void
    {
        $this->assertSame(
            [],
            self::allowlist(),
            'Every module entry point must resolve its graph through ModuleServices; ALLOWLIST must be empty.'
        );
    }

    public function testFrontControllerPublishesServicesAfterPageCacheExit(): void
    {
        $source = file_get_contents(self::ibl5Root() . '/modules.php');
        $this->assertNotFalse($source);

        $cacheHit = strpos($source, 'X-IBL-Cache: HIT');
        $publish = strpos($source, 'ModuleServices::publish(');
        $access = strpos($source, 'isModuleAccessible(');
        $include = strpos($source, 'include $modpath');

        $this->assertNotFalse($cacheHit, 'modules.php lost its page-cache HIT exit');
        $this->assertNotFalse($publish, 'modules.php does not publish ModuleServices');
        $this->assertNotFalse($access, 'modules.php lost its ModuleAccessControl check');
        $this->assertNotFalse($include, 'modules.php no longer includes the module file');

        $this->assertLessThan($publish, $cacheHit, 'A page-cache hit must exit before ModuleServices is published');
        $this->assertLessThan($access, $publish, 'ModuleServices must be published before the access check');
        $this->assertLessThan($include, $access, 'The access check must run before the module file is included');
    }

    public function testDetectorFlagsInjectedConstruction(): void
    {
        $violations = self::detectOwnComposition('<?php $c = new Foo\Bar($x);');

        $this->assertNotSame([], $violations);
    }

    public function testDetectorFlagsMysqliGlobal(): void
    {
        $violations = self::detectOwnComposition('<?php global $mysqli_db;');

        $this->assertNotSame([], $violations);
    }

    public function testDetectorIgnoresNewInCommentsAndStrings(): void
    {
        $violations = self::detectOwnComposition("<?php // new Season\necho 'new Player';\n/* new Team */");

        $this->assertSame([], $violations);
    }
}
