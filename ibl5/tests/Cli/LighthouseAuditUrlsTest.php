<?php

declare(strict_types=1);

namespace Tests\Cli;

use Module\ModuleRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('cli')]
final class LighthouseAuditUrlsTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        $resolved = realpath(__DIR__ . '/../../../bin/lighthouse-audit-urls');
        self::assertNotFalse($resolved, 'bin/lighthouse-audit-urls must exist');
        $this->scriptPath = $resolved;
    }

    public function testOutputContainsIndexPage(): void
    {
        $output = $this->runScript([]);

        self::assertStringContainsString('/ibl5/index.php', $output);
    }

    public function testOutputContainsAllModules(): void
    {
        $output = $this->runScript([]);
        $lines = array_filter(explode("\n", trim($output)));

        $moduleCount = count(ModuleRegistry::getAllModules());
        $subPageCount = 8;
        $homepageCount = 1;
        self::assertGreaterThanOrEqual(
            $moduleCount + $homepageCount + $subPageCount,
            count($lines)
        );
    }

    public function testParameterizedSubPagesPresent(): void
    {
        $output = $this->runScript([]);

        self::assertStringContainsString('op=team&teamid=1', $output);
        self::assertStringContainsString('pa=showpage&pid=1', $output);
    }

    public function testAutoDiscoveryIncludesNewModules(): void
    {
        $output = $this->runScript([]);

        foreach (ModuleRegistry::getAllModules() as $module) {
            self::assertStringContainsString(
                'name=' . $module,
                $output,
                "Module '$module' from getAllModules() must appear in URL output"
            );
        }
    }

    public function testCustomBaseUrl(): void
    {
        $output = $this->runScript(['--base-url=http://example.com']);

        self::assertStringContainsString('http://example.com/ibl5/index.php', $output);
        self::assertStringNotContainsString('http://localhost:8080', $output);
    }

    public function testJsonOutput(): void
    {
        $output = $this->runScript(['--json']);

        $decoded = json_decode($output, true);
        self::assertIsArray($decoded);
        self::assertNotEmpty($decoded);
        self::assertStringContainsString('/ibl5/index.php', $decoded[0]);
    }

    /**
     * @param list<string> $args
     */
    private function runScript(array $args): string
    {
        $cmd = escapeshellcmd($this->scriptPath);
        foreach ($args as $arg) {
            $cmd .= ' ' . escapeshellarg($arg);
        }
        $cmd .= ' 2>&1';

        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);

        self::assertSame(0, $exit, 'Script should exit 0. Output: ' . implode("\n", $output));

        return implode("\n", $output);
    }
}
