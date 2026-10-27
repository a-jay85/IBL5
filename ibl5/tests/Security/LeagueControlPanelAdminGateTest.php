<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Pins the admin gate of modules/LeagueControlPanel/index.php by source structure. The page
 * needs the module router boot (DB + session), so PHPUnit cannot run it; the
 * runtime behavior is covered by the non-admin e2e spec. The guard must set
 * 403, echo the denial text, end in exit, and precede the service wiring and
 * every POST handler.
 */
final class LeagueControlPanelAdminGateTest extends TestCase
{
    private const SCRIPT = __DIR__ . '/../../modules/LeagueControlPanel/index.php';
    private const GUARD_BLOCK = '/if\s*\(\s*!\s*\$authService->isAdmin\(\)\s*\)\s*\{(?<body>[^{}]*)\}/';
    private const DENIAL = 'Access denied. Administrator privileges required.';
    private const GATED = [
        '/\$leagueContext->getCurrentLeague\(\)/',
        '/ModuleServices::current\(\)/',
        '/\$_SERVER\[\'REQUEST_METHOD\'\]/',
        '/\$_POST\b/',
    ];

    private const VALID_GUARD = "if (!\$authService->isAdmin()) {\n    http_response_code(403);\n    echo 'Access denied. Administrator privileges required.';\n    exit;\n}\n";
    private const LEAGUE_LINE = "\$l = \$leagueContext->getCurrentLeague();\n";
    private const WIRING_LINE = "\$r = \\Module\\ModuleServices::current()->factory(\\Module\\Factories\\LeagueControlPanelFactory::class);\n";
    private const POST_LINE = "if (\$_SERVER['REQUEST_METHOD'] === 'POST' && \$_POST['x']) {}\n";

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

    public function testAdminGuardPrecedesServiceWiringAndPostHandlers(): void
    {
        $this->assertGuardDeniesAndPrecedesWiring($this->readScript());
    }

    public function testCheckerFailsWhenGuardDoesNotExit(): void
    {
        $guardWithoutExit = "if (!\$authService->isAdmin()) {\n    http_response_code(403);\n    echo 'Access denied. Administrator privileges required.';\n}\n";
        $planted = "<?php\n" . $guardWithoutExit . self::LEAGUE_LINE . self::WIRING_LINE . self::POST_LINE;

        $this->expectException('PHPUnit\Framework\AssertionFailedError');
        $this->assertGuardDeniesAndPrecedesWiring($planted);
    }

    public function testCheckerFailsWhenGuardOmitsForbiddenStatus(): void
    {
        $guardWithoutStatus = "if (!\$authService->isAdmin()) {\n    echo 'Access denied. Administrator privileges required.';\n    exit;\n}\n";
        $planted = "<?php\n" . $guardWithoutStatus . self::LEAGUE_LINE . self::WIRING_LINE . self::POST_LINE;

        $this->expectException('PHPUnit\Framework\AssertionFailedError');
        $this->assertGuardDeniesAndPrecedesWiring($planted);
    }

    public function testCheckerFailsWhenServiceIsWiredBeforeGuard(): void
    {
        $planted = "<?php\n" . self::WIRING_LINE . self::VALID_GUARD . self::LEAGUE_LINE . self::POST_LINE;

        $this->expectException('PHPUnit\Framework\AssertionFailedError');
        $this->assertGuardDeniesAndPrecedesWiring($planted);
    }

    public function testCheckerFailsWhenPostHandlerPrecedesGuard(): void
    {
        $planted = "<?php\n" . self::POST_LINE . self::VALID_GUARD . self::LEAGUE_LINE . self::WIRING_LINE;

        $this->expectException('PHPUnit\Framework\AssertionFailedError');
        $this->assertGuardDeniesAndPrecedesWiring($planted);
    }

    public function testCheckerAcceptsWellFormedPlantedSource(): void
    {
        $planted = "<?php\n" . self::VALID_GUARD . self::LEAGUE_LINE . self::WIRING_LINE . self::POST_LINE;

        $this->assertGuardDeniesAndPrecedesWiring($planted);
    }
}
