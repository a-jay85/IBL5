<?php

declare(strict_types=1);

namespace Tests\Extension;

use PHPUnit\Framework\TestCase;

/**
 * extension.php is a procedural module script that ends in HtmxHelper::redirect() (exits), so it is not
 * unit-instantiable. These tests pin the team guard's source structure instead.
 */
class ExtensionScriptTeamGuardLockTest extends TestCase
{
    public function testExtensionScriptRedirectsTeamlessSessionBeforeProcessor(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/modules/Player/extension.php');
        self::assertIsString($src);

        $guard = 'if ($sessionTeam === null || $sessionTeam === \'\' || $sessionTeam === \League\League::FREE_AGENTS_TEAM_NAME) {';
        $guardPos = strpos($src, $guard);
        self::assertIsInt($guardPos, 'extension.php team guard removed or weakened');

        self::assertMatchesRegularExpression(
            '/\{\s*\\\\Utilities\\\\HtmxHelper::redirect\(/',
            substr($src, $guardPos, 200),
            'extension.php team guard body no longer redirects'
        );

        $processorPos = strpos($src, 'new \Extension\ExtensionProcessor(');
        self::assertIsInt($processorPos, 'extension.php ExtensionProcessor construction not found');
        self::assertGreaterThan($guardPos, $processorPos, 'extension.php team guard must precede ExtensionProcessor construction');
    }
}
