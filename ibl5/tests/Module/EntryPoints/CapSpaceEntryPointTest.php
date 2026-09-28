<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * The CapSpace module is a redirect stub. ModuleRedirect::sendWithPassthrough() returns,
 * so the stub must `return` immediately after — these are source-contract tests;
 * E2E covers the HTTP redirect.
 */
class CapSpaceEntryPointTest extends ModuleEntryPointTestCase
{
    private const STUB = __DIR__ . '/../../../modules/CapSpace/index.php';

    public function testStubRedirectsToContractsTeamsTab(): void
    {
        $source = (string) file_get_contents(self::STUB);
        $this->assertSame(
            1,
            substr_count($source, "\\Module\\ModuleRedirect::sendWithPassthrough('modules.php?name=Contracts&tab=teams', [], \$_GET + \$_POST);\nreturn;")
        );
    }

    public function testStubRendersNoPageOrQueries(): void
    {
        $source = (string) file_get_contents(self::STUB);
        foreach (['PageLayout', 'Repository', 'Service', 'View', 'echo'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source, "stub must not contain {$forbidden}");
        }
        $this->assertStringContainsString("defined('MODULE_FILE')", $source);
    }
}
