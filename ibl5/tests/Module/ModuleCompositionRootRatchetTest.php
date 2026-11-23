<?php

declare(strict_types=1);

namespace Tests\Module;

use PHPUnit\Framework\TestCase;

/**
 * Ratchet: a module entry point may not compose its own object graph.
 *
 * Module entry points (index.php and every ?file= sibling) resolve a factory through `ModuleServices` instead of
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
     * "<Module>/<file>.php" entries that still hold a T_NEW token or a
     * `$mysqli_db` variable. Every module entry point now composes through a factory,
     * so this list must stay empty.
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
     * "<Module>/<file>.php" => detector violations, for every PHP file directly under modules/<dir>/.
     *
     * @return array<string, list<string>>
     */
    private static function scanModules(): array
    {
        $files = glob(self::ibl5Root() . '/modules/*/*.php');
        self::assertNotFalse($files);
        self::assertNotSame([], $files, 'No module entry points found; the scan path is wrong');

        $result = [];
        foreach ($files as $file) {
            $source = file_get_contents($file);
            self::assertNotFalse($source);
            $result[basename(dirname($file)) . '/' . basename($file)] = self::detectOwnComposition($source);
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
            'These module entry points compose their own object graph. Resolve a factory through '
            . 'ModuleServices instead; see ibl5/classes/Module/README.md § Module composition.'
        );
    }

    public function testAllowlistHasNoStaleEntries(): void
    {
        $scan = self::scanModules();

        $stale = [];
        foreach (self::allowlist() as $module) {
            if (!array_key_exists($module, $scan)) {
                $stale[] = $module . ' (no modules/' . $module . ')';
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

    public function testScanCoversSiblingEntryPoints(): void
    {
        $scanned = array_keys(self::scanModules());

        foreach (['Trading/index.php', 'Trading/maketradeoffer.php', 'Player/articles.php', 'News/categories.php'] as $expected) {
            $this->assertContains($expected, $scanned, 'The ratchet must scan every PHP file under modules/<dir>/');
        }
    }

    public function testFrontControllerPublishesServicesAfterPageCacheExit(): void
    {
        // phpunit-hygiene-allow: guards statement ORDER inside the modules.php front controller (cache exit before publish); the file cannot be included in-process without exiting
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

    public function testAllowlistIsEmpty(): void
    {
        $this->assertSame([], self::allowlist(), 'A module re-entered the composition ALLOWLIST; route its construction through a factory instead.');
    }

    public function testFrontControllerConstructsNoSeason(): void
    {
        // phpunit-hygiene-allow: guards that the modules.php front controller reads the season through ModuleServices; the file cannot be included in-process without exiting
        $source = file_get_contents(self::ibl5Root() . '/modules.php');
        $this->assertNotFalse($source);

        $this->assertStringNotContainsString('new \Season\Season(', $source, 'modules.php must read the season through ModuleServices');
        $this->assertStringNotContainsString('new Season\Season(', $source, 'modules.php must read the season through ModuleServices');
    }

    public function testFrontControllerPublishesBeforeAccessControlConstruction(): void
    {
        // phpunit-hygiene-allow: guards statement ORDER inside the modules.php front controller (publish before access-control construction); the file cannot be included in-process without exiting
        $source = file_get_contents(self::ibl5Root() . '/modules.php');
        $this->assertNotFalse($source);

        $publish = strpos($source, 'ModuleServices::publish(');
        $construct = strpos($source, 'new Module\ModuleAccessControl(');

        $this->assertNotFalse($publish, 'modules.php does not publish ModuleServices');
        $this->assertNotFalse($construct, 'modules.php no longer constructs ModuleAccessControl');
        $this->assertLessThan($construct, $publish, 'ModuleServices must be published before ModuleAccessControl is constructed');
    }

    public function testNoFactoryConstructsSeasonDirectly(): void
    {
        $files = glob(self::ibl5Root() . '/classes/Module/Factories/*.php');
        $this->assertNotFalse($files);
        $this->assertNotSame([], $files);

        foreach ($files as $file) {
            // phpunit-hygiene-allow: guards that factories reach Season only through SharedSeason; there is no behavioral seam for a forbidden constructor call
            $source = file_get_contents($file);
            $this->assertNotFalse($source);

            $this->assertStringNotContainsString('new \Season\Season(', $source, basename($file) . ' must use \Season\SharedSeason::get(), not new \Season\Season(');
            $this->assertStringNotContainsString('new Season\Season(', $source, basename($file) . ' must use \Season\SharedSeason::get(), not new Season\Season(');
        }
    }

    public function testNextSimAndLeagueStartersReadNoDbHandle(): void
    {
        foreach (['NextSim', 'LeagueStarters'] as $module) {
            // phpunit-hygiene-allow: pins that these entry points take the db handle through their factory; the detector does not flag ->db() reads
            $source = file_get_contents(self::ibl5Root() . '/modules/' . $module . '/index.php');
            $this->assertNotFalse($source);

            $this->assertStringNotContainsString('ModuleServices::current()->db()', $source, $module . ' must get its db handle through its factory');
        }
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
