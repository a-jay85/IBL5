<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use Tests\Module\Fixtures\FixtureModuleFactory;
use Tests\Module\ModuleCompositionRootRatchetTest;
use Tests\Security\Fixtures\IdentityLeakingFactory;

/**
 * Mechanizes the security invariants of the module composition root.
 *
 * (a) ModuleServices and the module factories are identity-free: they read no
 *     request or auth state and take no actor-identity parameter.
 * (b) In an entry point that resolves services, the MODULE_FILE guard and the
 *     terminating auth gates sit above the first `ModuleServices::current(` call.
 * (c) Converting an entry point never drops an auth, admin, loginbox or CSRF
 *     gate: each count stays at or above the baseline recorded from the
 *     untouched tree.
 *
 * Detectors are private methods that take a source string or a ReflectionClass.
 * The testDetector* methods feed them an injected violation so a detector that
 * silently returns [] fails the build.
 */
final class ModuleFactorySecuritySurfaceTest extends TestCase
{
    private const WIRING_TOKEN = 'ModuleServices::current(';
    private const IDENTITY_PARAMETER_PATTERN = '/(loggedin|currentuser|username|userteam|ownteam|highlightteam|session|cookie|actor)/i';
    private const GATE_TOKEN_PATTERN = '/is_admin\(|->isAdmin\(|->isAuthenticated\(|\bloginbox\(|CsrfGuard::/';

    /** Request and auth state a factory must never read. */
    private const FORBIDDEN_VARIABLES = [
        '$_GET', '$_POST', '$_REQUEST', '$_SESSION', '$_COOKIE', '$_SERVER', '$_FILES', '$GLOBALS',
    ];

    /**
     * Entry points whose terminating gate precedes all composition today. Each
     * gate token must appear above the first wiring token once the module converts.
     * Phase 7 adds ProjectedDraftOrder when it converts that module.
     *
     * @var array<string, list<string>>
     */
    private const GATED_BEFORE_WIRING = [
        'LeagueControlPanel' => ['->isAuthenticated(', '->isAdmin('],
        'NextSim' => ['loginbox('],
        'ProjectedDraftOrder' => ['->isAdmin('],
        'TrainingCampRatingsDiff' => ['loginbox('],
    ];

    /**
     * Gate-token line count per modules/<dir>/index.php, recorded from the
     * untouched tree with:
     * grep -cE 'is_admin\(|->isAdmin\(|->isAuthenticated\(|\bloginbox\(|CsrfGuard::'
     *
     * @var array<string, int>
     */
    private const GATE_TOKEN_BASELINE = [
        'DepthChartEntry' => 2,
        'LeagueControlPanel' => 9,
        'NextSim' => 1,
        'OneOnOneGame' => 1,
        'Player' => 2,
        'ProjectedDraftOrder' => 2,
        'TrainingCampRatingsDiff' => 1,
        'YourAccount' => 4,
    ];

    private static function ibl5Root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * ModuleServices plus every factory under classes/Module/Factories. A missing
     * Factories directory yields just ModuleServices.
     *
     * @return list<string> Absolute file paths
     */
    private static function scannedClassFiles(): array
    {
        $files = [self::ibl5Root() . '/classes/Module/ModuleServices.php'];
        $factories = glob(self::ibl5Root() . '/classes/Module/Factories/*.php');
        if ($factories !== false) {
            foreach ($factories as $factory) {
                $files[] = $factory;
            }
        }

        return $files;
    }

    /**
     * Public method names of the auth interface, minus any the container also declares.
     *
     * @return list<string>
     */
    private static function authMethodNames(): array
    {
        $names = [];
        foreach ((new \ReflectionClass(\Auth\Contracts\AuthServiceInterface::class))->getMethods() as $method) {
            $names[] = $method->getName();
        }

        $containerNames = [];
        foreach ((new \ReflectionClass(\Bootstrap\Contracts\ContainerInterface::class))->getMethods() as $method) {
            $containerNames[] = $method->getName();
        }

        return array_values(array_diff($names, $containerNames));
    }

    /**
     * @param list<string> $authMethodNames
     * @return list<string> One entry per forbidden token, with its line
     */
    private static function detectRequestOrAuthState(string $source, array $authMethodNames): array
    {
        $violations = [];
        foreach (token_get_all($source) as $token) {
            if (!is_array($token)) {
                continue;
            }
            [$id, $text, $line] = $token;
            if ($id === T_VARIABLE && in_array($text, self::FORBIDDEN_VARIABLES, true)) {
                $violations[] = $text . ' (line ' . $line . ')';
            } elseif ($id === T_GLOBAL) {
                $violations[] = 'global (line ' . $line . ')';
            } elseif ($id === T_STRING && ($text === 'is_admin' || in_array($text, $authMethodNames, true))) {
                $violations[] = $text . ' (line ' . $line . ')';
            }
        }

        return $violations;
    }

    /**
     * @param class-string $className
     * @return list<string> One entry per public method parameter named for an actor identity
     */
    private static function detectIdentityParameters(string $className): array
    {
        $class = new \ReflectionClass($className);
        $violations = [];
        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getParameters() as $parameter) {
                if (preg_match(self::IDENTITY_PARAMETER_PATTERN, $parameter->getName()) === 1) {
                    $violations[] = $class->getName() . '::' . $method->getName() . '($' . $parameter->getName() . ')';
                }
            }
        }

        return $violations;
    }

    /**
     * Source with comments removed, so token offsets reflect executable order.
     */
    private static function stripComments(string $source): string
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        return $code;
    }

    /**
     * Guard-order violations for an entry point that holds the wiring token.
     * Returns [] when the file has no wiring token (nothing to order yet).
     *
     * @param list<string> $gateTokens Gates whose first offset must precede the wiring
     * @return list<string>
     */
    private static function detectWiringAboveGuard(string $source, array $gateTokens): array
    {
        $code = self::stripComments($source);
        $wiring = strpos($code, self::WIRING_TOKEN);
        if ($wiring === false) {
            return [];
        }

        $violations = [];

        $guardOffsets = [];
        foreach ([
            '/defined\([\'"]MODULE_FILE[\'"]\)/',
            '/stripos\(\$_SERVER\[\'PHP_SELF\'\], [\'"]modules\.php[\'"]\)/',
            '/preg_match\(\'\/modules\\\\\.php\/i\'/',
        ] as $pattern) {
            if (preg_match($pattern, $code, $match, PREG_OFFSET_CAPTURE) === 1) {
                $guardOffsets[] = $match[0][1];
            }
        }
        if ($guardOffsets === []) {
            $violations[] = 'MODULE_FILE guard missing';
        } elseif (min($guardOffsets) > $wiring) {
            $violations[] = 'MODULE_FILE guard sits below the first ' . self::WIRING_TOKEN . ' token';
        }

        foreach ($gateTokens as $gate) {
            $offset = strpos($code, $gate);
            if ($offset === false) {
                $violations[] = "gate '{$gate}' missing";
            } elseif ($offset > $wiring) {
                $violations[] = "gate '{$gate}' sits below the first " . self::WIRING_TOKEN . ' token';
            }
        }

        return $violations;
    }

    /**
     * @return list<string> Absolute paths of every modules/<dir>/index.php
     */
    private static function entryPointFiles(): array
    {
        $files = glob(self::ibl5Root() . '/modules/*/index.php');
        self::assertNotFalse($files);
        self::assertNotSame([], $files, 'No module entry points found; the scan path is wrong');

        return $files;
    }

    private static function read(string $path): string
    {
        $source = file_get_contents($path);
        self::assertNotFalse($source, "Cannot read {$path}");

        return $source;
    }

    public function testFactoriesReadNoAuthOrRequestState(): void
    {
        $authMethods = self::authMethodNames();
        $this->assertContains('isAdmin', $authMethods, 'Auth method reflection returned no usable names');

        $violations = [];
        foreach (self::scannedClassFiles() as $file) {
            foreach (self::detectRequestOrAuthState(self::read($file), $authMethods) as $violation) {
                $violations[] = basename($file) . ': ' . $violation;
            }
        }

        $this->assertSame(
            [],
            $violations,
            'ModuleServices and module factories must be identity-free: no superglobals, no global, no auth calls.'
        );
    }

    public function testFactoryMethodsTakeNoActorIdentityParameters(): void
    {
        $violations = [];
        $factories = glob(self::ibl5Root() . '/classes/Module/Factories/*.php');
        $this->assertNotFalse($factories);
        foreach ($factories as $file) {
            $fqcn = 'Module\\Factories\\' . basename($file, '.php');
            if (!class_exists($fqcn)) {
                $violations[] = "{$fqcn} does not autoload from {$file}";
                continue;
            }
            foreach (self::detectIdentityParameters($fqcn) as $violation) {
                $violations[] = $violation;
            }
        }

        $this->assertSame(
            [],
            $violations,
            'Factories receive only ModuleServices; pass the acting user to the controller dispatch method instead.'
        );
    }

    public function testGuardPrecedesWiringInConvertedEntryPoints(): void
    {
        $violations = [];
        foreach (self::entryPointFiles() as $file) {
            $module = basename(dirname($file));
            $source = self::read($file);
            $converted = str_contains(self::stripComments($source), self::WIRING_TOKEN);
            $gates = self::GATED_BEFORE_WIRING[$module] ?? [];

            if (!$converted && $gates !== [] && !in_array($module, ModuleCompositionRootRatchetTest::allowlist(), true)) {
                $violations[] = "{$module}: left the composition ALLOWLIST but never uses " . self::WIRING_TOKEN;
                continue;
            }

            foreach (self::detectWiringAboveGuard($source, $gates) as $violation) {
                $violations[] = "{$module}: {$violation}";
            }
        }

        $this->assertSame(
            [],
            $violations,
            'Guards never move: the MODULE_FILE guard and every terminating gate stay above the factory call.'
        );
    }

    public function testGateTokensSurviveConversion(): void
    {
        $counts = [];
        foreach (self::entryPointFiles() as $file) {
            $count = 0;
            foreach (explode("\n", self::read($file)) as $line) {
                if (preg_match(self::GATE_TOKEN_PATTERN, $line) === 1) {
                    $count++;
                }
            }
            $counts[basename(dirname($file))] = $count;
        }

        foreach (['LeagueControlPanel', 'NextSim', 'TrainingCampRatingsDiff', 'DepthChartEntry', 'Player', 'ProjectedDraftOrder', 'YourAccount'] as $required) {
            $this->assertArrayHasKey($required, self::GATE_TOKEN_BASELINE, "{$required} must stay in GATE_TOKEN_BASELINE");
        }

        foreach (self::GATE_TOKEN_BASELINE as $module => $baseline) {
            $this->assertArrayHasKey(
                $module,
                $counts,
                "modules/{$module}/index.php vanished: update GATE_TOKEN_BASELINE deliberately"
            );
            $this->assertGreaterThanOrEqual(
                $baseline,
                $counts[$module],
                "modules/{$module}/index.php lost a gate token (baseline {$baseline}, now {$counts[$module]})"
            );
        }
    }

    public function testDetectorFlagsInjectedSuperglobalRead(): void
    {
        $this->assertNotSame([], self::detectRequestOrAuthState("<?php \$u = \$_SESSION['u'];", []));
        $this->assertNotSame([], self::detectRequestOrAuthState('<?php global $mysqli_db;', []));
        $this->assertSame([], self::detectRequestOrAuthState('<?php $c = $this->services->db();', []));
    }

    public function testDetectorFlagsAuthInterrogation(): void
    {
        $authMethods = self::authMethodNames();

        $this->assertNotSame(
            [],
            self::detectRequestOrAuthState('<?php $this->services->auth()->isAdmin();', $authMethods)
        );
        $this->assertNotSame([], self::detectRequestOrAuthState('<?php if (is_admin()) {}', $authMethods));
        $this->assertSame(
            [],
            self::detectRequestOrAuthState('<?php $this->services->auth();', $authMethods)
        );
    }

    public function testDetectorFlagsWiringAboveGuard(): void
    {
        $guard = "if (!defined('MODULE_FILE')) { die(); }\n";
        $gate = "if (!\$authService->isAdmin()) { exit; }\n";
        $wiring = "\$c = \\Module\\ModuleServices::current()->factory(Foo::class);\n";

        $this->assertNotSame(
            [],
            self::detectWiringAboveGuard("<?php\n" . $wiring . $guard, []),
            'wiring above the MODULE_FILE guard must be flagged'
        );
        $this->assertNotSame(
            [],
            self::detectWiringAboveGuard("<?php\n" . $guard . $wiring . $gate, ['->isAdmin(']),
            'wiring above a terminating gate must be flagged'
        );
        $this->assertSame(
            [],
            self::detectWiringAboveGuard("<?php\n" . $guard . $gate . $wiring, ['->isAdmin(']),
            'guard, gate, then wiring is the accepted order'
        );
    }

    /**
     * The planted violation is ibl5/tests/Security/Fixtures/IdentityLeakingFactory.php.
     */
    public function testDetectorFlagsIdentityParameter(): void
    {
        $this->assertNotSame([], self::detectIdentityParameters(IdentityLeakingFactory::class));
        $this->assertSame([], self::detectIdentityParameters(FixtureModuleFactory::class));
    }
}
