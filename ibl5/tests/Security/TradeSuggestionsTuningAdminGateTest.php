<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Pins the admin gate of modules/TradeSuggestionsTuning/index.php by source
 * structure, copied from LeagueControlPanelAdminGateTest. The page needs the
 * module router boot (DB + session), so PHPUnit cannot run it; the runtime
 * behavior is covered by the e2e specs. The guard must set 403, echo the
 * denial text, end in exit, and precede the factory fetch and the controller
 * call. The route never reads POST.
 */
final class TradeSuggestionsTuningAdminGateTest extends TestCase
{
    private const SCRIPT = __DIR__ . '/../../modules/TradeSuggestionsTuning/index.php';
    private const GUARD_BLOCK = '/if\s*\(\s*!\s*\$authService->isAdmin\(\)\s*\)\s*\{(?<body>[^{}]*)\}/';
    private const DENIAL = 'Access denied. Administrator privileges required.';
    private const GATED = [
        '/ModuleServices::current\(\)/',
        '/->handle\(\$_GET\)/',
    ];

    private const VALID_GUARD = "if (!\$authService->isAdmin()) {\n    http_response_code(403);\n    echo 'Access denied. Administrator privileges required.';\n    exit;\n}\n";
    private const WIRING_LINE = "\$factory = \\Module\\ModuleServices::current()->factory(\\Module\\Factories\\TradeSuggestionsTuningFactory::class);\n";
    private const HANDLE_LINE = "echo \$factory->controller()->handle(\$_GET);\n";

    /**
     * @return array{body: string, end: int}
     */
    private function locateGuard(string $source): array
    {
        $count = preg_match_all(self::GUARD_BLOCK, $source, $matches, PREG_OFFSET_CAPTURE);
        self::assertSame(1, $count, 'isAdmin() guard missing or duplicated');

        return [
            'body' => $matches['body'][0][0],
            'end' => $matches[0][0][1] + strlen($matches[0][0][0]),
        ];
    }

    private function assertGuardDeniesAndExits(string $source): void
    {
        $guard = $this->locateGuard($source);

        self::assertMatchesRegularExpression('/http_response_code\(\s*403\s*\)/', $guard['body'], 'guard does not set 403');
        self::assertTrue(
            str_contains($guard['body'], "echo '" . self::DENIAL . "'"),
            'guard does not echo the denial text',
        );
        self::assertMatchesRegularExpression('/\bexit\s*;\s*$/', $guard['body'], 'guard does not end in exit;');
    }

    private function assertGuardDeniesAndPrecedesWiring(string $source): void
    {
        $this->assertGuardDeniesAndExits($source);
        $guard = $this->locateGuard($source);

        foreach (self::GATED as $pattern) {
            self::assertSame(1, preg_match_all($pattern, $source), 'gated statement missing or duplicated: ' . $pattern);
            $found = preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE);
            self::assertSame(1, $found, 'gated statement not found: ' . $pattern);
            self::assertGreaterThan($guard['end'], $match[0][1], 'statement runs before the admin guard: ' . $pattern);
        }
    }

    private function readScript(): string
    {
        $source = file_get_contents(self::SCRIPT);
        self::assertIsString($source);

        return $source;
    }

    public function testAdminGuardSetsForbiddenEchoesDenialAndExits(): void
    {
        $this->assertGuardDeniesAndExits($this->readScript());
    }

    public function testAdminGuardPrecedesFactoryFetchAndControllerCall(): void
    {
        $this->assertGuardDeniesAndPrecedesWiring($this->readScript());
    }

    public function testNoPostReadAnywhereInRoute(): void
    {
        self::assertDoesNotMatchRegularExpression('/\$_POST\b/', $this->readScript());
    }

    public function testControllerCallIsBelowTheGuard(): void
    {
        $source = $this->readScript();
        $handle = strpos($source, '->handle($_GET)');
        $denial = strpos($source, self::DENIAL);

        self::assertIsInt($handle, 'controller call not found');
        self::assertIsInt($denial, 'denial text not found');
        self::assertGreaterThan($denial, $handle, 'controller call runs before the admin guard');
    }

    public function testCheckerFailsWhenGuardDoesNotExit(): void
    {
        $guardWithoutExit = "if (!\$authService->isAdmin()) {\n    http_response_code(403);\n    echo 'Access denied. Administrator privileges required.';\n}\n";
        $planted = "<?php\n" . $guardWithoutExit . self::WIRING_LINE . self::HANDLE_LINE;

        $this->expectException('PHPUnit\Framework\AssertionFailedError');
        $this->assertGuardDeniesAndPrecedesWiring($planted);
    }

    public function testCheckerFailsWhenGuardOmitsForbiddenStatus(): void
    {
        $guardWithoutStatus = "if (!\$authService->isAdmin()) {\n    echo 'Access denied. Administrator privileges required.';\n    exit;\n}\n";
        $planted = "<?php\n" . $guardWithoutStatus . self::WIRING_LINE . self::HANDLE_LINE;

        $this->expectException('PHPUnit\Framework\AssertionFailedError');
        $this->assertGuardDeniesAndPrecedesWiring($planted);
    }

    public function testCheckerFailsWhenGuardIsMissing(): void
    {
        $planted = "<?php\n" . self::WIRING_LINE . self::HANDLE_LINE;

        $this->expectException('PHPUnit\Framework\AssertionFailedError');
        $this->assertGuardDeniesAndPrecedesWiring($planted);
    }

    public function testCheckerFailsWhenFactoryIsFetchedBeforeGuard(): void
    {
        $planted = "<?php\n" . self::WIRING_LINE . self::VALID_GUARD . self::HANDLE_LINE;

        $this->expectException('PHPUnit\Framework\AssertionFailedError');
        $this->assertGuardDeniesAndPrecedesWiring($planted);
    }

    public function testCheckerFailsWhenControllerCallPrecedesGuard(): void
    {
        $planted = "<?php\n" . self::HANDLE_LINE . self::VALID_GUARD . self::WIRING_LINE;

        $this->expectException('PHPUnit\Framework\AssertionFailedError');
        $this->assertGuardDeniesAndPrecedesWiring($planted);
    }

    public function testCheckerAcceptsWellFormedPlantedSource(): void
    {
        $planted = "<?php\n" . self::VALID_GUARD . self::WIRING_LINE . self::HANDLE_LINE;

        $this->assertGuardDeniesAndPrecedesWiring($planted);
    }
}
