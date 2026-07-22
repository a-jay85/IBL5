<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\TestCase;

/**
 * Source-assertion guard test for the admin recap viewer entry point.
 *
 * simSummaries.php cannot be included in a unit test (it boots mainfile.php),
 * so its security properties are asserted against the raw source: both auth
 * guards present, both ahead of any repository use, and no raw SQL.
 */
final class SimSummariesGuardTest extends TestCase
{
    private string $src;

    protected function setUp(): void
    {
        parent::setUp();
        $source = file_get_contents(__DIR__ . '/../../../simSummaries.php');
        self::assertIsString($source, 'simSummaries.php must exist and be readable');
        $this->src = $source;
    }

    public function testBothGuardsArePresent(): void
    {
        self::assertStringContainsString('if (!is_user($user))', $this->src, 'Login guard must be present');
        self::assertStringContainsString('if (!is_admin())', $this->src, 'Admin guard must be present');
        self::assertStringContainsString('http_response_code(403)', $this->src, 'Non-admins must get a 403');
    }

    public function testTheGuardsPrecedeAnyRepositoryUse(): void
    {
        $adminGuard = strpos($this->src, 'is_admin()');
        self::assertIsInt($adminGuard);

        foreach (['SimSummaryRepository', 'listAll', 'find('] as $needle) {
            $offset = strpos($this->src, $needle);
            self::assertIsInt($offset, sprintf('Expected %s in simSummaries.php', $needle));
            self::assertGreaterThan(
                $adminGuard,
                $offset,
                sprintf('%s must appear after the admin guard, never before it', $needle)
            );
        }
    }

    public function testNoRawSqlAndNoDirectDbCall(): void
    {
        self::assertStringNotContainsString('->query(', $this->src, 'All database access goes through the repository');
        self::assertStringNotContainsString('SELECT ', $this->src, 'No raw SQL belongs in the entry point');
    }

    /**
     * `.htaccess` is inert in the Docker/CI stack (AllowOverride None), so an
     * Apache-level rule would falsely imply protection. The pre-existing
     * `ibl5/.htaccess` is a bot IP blocklist and must stay free of any rule for
     * this page; no sibling `.htaccess` belongs in the SimRecap class directory.
     */
    public function testNoHtaccessProtectsThisPage(): void
    {
        $htaccess = file_get_contents(__DIR__ . '/../../../.htaccess');
        self::assertIsString($htaccess);
        self::assertStringNotContainsString(
            'simSummaries',
            $htaccess,
            'Access control for simSummaries.php lives in the PHP guards, not in an inert .htaccess'
        );

        self::assertFileDoesNotExist(__DIR__ . '/../../../classes/SimRecap/.htaccess');
    }
}
