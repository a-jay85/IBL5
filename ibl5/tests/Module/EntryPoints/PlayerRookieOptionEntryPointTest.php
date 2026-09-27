<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * Structural wiring tests for modules/Player/index.php — the pa=processrookieoption path.
 *
 * Every pa=processrookieoption path ends the process: loginbox() calls die(), and the CSRF
 * refusal plus every later branch calls HtmxHelper::redirect(), which is never and calls exit.
 * DraftEntryPointTest and TradingEntryPointTest document the same skip. The module therefore
 * cannot run in-process. The regression this refactor opens is the module handing the controller
 * the wrong value (for example $teamName) in the $sessionTeam slot. Every controller test would
 * stay green while the gate became a no-op. This file pins that wiring by asserting on the
 * module source.
 */
final class PlayerRookieOptionEntryPointTest extends \PHPUnit\Framework\TestCase
{
    private static function normalizedModuleSource(): string
    {
        $src = file_get_contents(dirname(__DIR__, 3) . '/modules/Player/index.php');
        self::assertIsString($src);
        return (string) preg_replace('/\s+/', ' ', $src);
    }

    private static function callSitePassesSessionTeam(string $normalized): bool
    {
        return preg_match(
            '/\$controller->processRookieOption\(\$teamName, \$playerID, \$extensionAmount, \$sessionTeam\);/',
            $normalized,
        ) === 1;
    }

    public function testDispatchRoutesProcessRookieOptionCase(): void
    {
        $normalized = self::normalizedModuleSource();
        self::assertSame(
            1,
            preg_match('/case "processrookieoption": processrookieoption\(\); break;/', $normalized),
        );
    }

    public function testModulePassesSessionTeamAsControllerFourthArgument(): void
    {
        $normalized = self::normalizedModuleSource();
        self::assertTrue(self::callSitePassesSessionTeam($normalized));
        self::assertSame(1, preg_match_all('/->processRookieOption\(/', $normalized));
    }

    public function testModuleDerivesSessionTeamFromUsernameLookup(): void
    {
        $normalized = self::normalizedModuleSource();
        self::assertSame(
            1,
            preg_match('/\$sessionTeam = \$commonRepository->getTeamnameFromUsername\(\$username\);/', $normalized),
        );
        self::assertSame(
            1,
            preg_match('/\$sessionTeam = is_string\(\$sessionTeam\) \? \$sessionTeam : null;/', $normalized),
        );
        self::assertSame(2, preg_match_all('/\$sessionTeam = /', $normalized));
    }

    public function testCallSiteMatcherRejectsTeamNameInSessionTeamSlot(): void
    {
        $normalized = self::normalizedModuleSource();
        $mutated = str_replace('$extensionAmount, $sessionTeam);', '$extensionAmount, $teamName);', $normalized);
        self::assertNotSame($normalized, $mutated);
        self::assertFalse(self::callSitePassesSessionTeam($mutated));
    }
}
