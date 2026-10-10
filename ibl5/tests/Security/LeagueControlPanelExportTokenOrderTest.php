<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Locks the ordering invariant that makes issuing a CSRF token from the
 * export handler's 403 branch safe: the admin guard must run before any
 * generateRawToken() call in modules/LeagueControlPanel/index.php (backlog #789).
 */
class LeagueControlPanelExportTokenOrderTest extends TestCase
{
    private const SCRIPT = __DIR__ . '/../../modules/LeagueControlPanel/index.php';
    private const ADMIN_GUARD = '/if\s*\(\s*!\s*\$authService->isAdmin\(\)\s*\)/';
    private const TOKEN_CALL = '/generateRawToken\(\s*\'lcp_export_active_players\'\s*\)/';

    /** @return array{guard: int, tokens: list<int>} byte offsets */
    private function locate(string $source): array
    {
        $guardFound = preg_match(self::ADMIN_GUARD, $source, $m, PREG_OFFSET_CAPTURE);
        $guard = $guardFound === 1 ? $m[0][1] : -1;
        preg_match_all(self::TOKEN_CALL, $source, $all, PREG_OFFSET_CAPTURE);
        $tokens = array_map(static fn (array $hit): int => $hit[1], $all[0]);
        return ['guard' => $guard, 'tokens' => $tokens];
    }

    private function assertGuardPrecedesEveryTokenIssue(string $source): void
    {
        $found = $this->locate($source);
        self::assertGreaterThanOrEqual(0, $found['guard'], 'isAdmin() guard missing');
        self::assertNotSame([], $found['tokens'], 'no lcp_export_active_players token issue found');
        foreach ($found['tokens'] as $offset) {
            self::assertGreaterThan($found['guard'], $offset, 'token issued before the admin guard');
        }
    }

    public function testAdminGuardPrecedesEveryExportTokenIssue(): void
    {
        $source = file_get_contents(self::SCRIPT);
        self::assertIsString($source);
        $this->assertGuardPrecedesEveryTokenIssue($source);
    }

    public function testExportHandlerIssuesTokenOnAllThreeJsonReplies(): void
    {
        $source = file_get_contents(self::SCRIPT);
        self::assertIsString($source);
        // 403 (CSRF failure), 500 (export failed), 200 (success). The 409 phase
        // branch is intentionally token-free; a fourth hit means it grew one.
        self::assertCount(3, $this->locate($source)['tokens']);
    }

    public function testCheckerFailsWhenTokenIsIssuedBeforeGuard(): void
    {
        $planted = "<?php\n\$t = \\Security\\CsrfGuard::generateRawToken('lcp_export_active_players');\nif (!\$authService->isAdmin()) { exit; }\n";
        $this->expectException('PHPUnit\Framework\AssertionFailedError');
        $this->assertGuardPrecedesEveryTokenIssue($planted);
    }
}
