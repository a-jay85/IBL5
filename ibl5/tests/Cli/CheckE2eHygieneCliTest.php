<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('cli')]
final class CheckE2eHygieneCliTest extends TestCase
{
    private string $scriptPath;
    private string $tmpDir;

    protected function setUp(): void
    {
        $resolved = realpath(__DIR__ . '/../../../bin/check-e2e-hygiene');
        self::assertNotFalse($resolved, 'bin/check-e2e-hygiene must exist');
        $this->scriptPath = $resolved;

        $this->tmpDir = sys_get_temp_dir() . '/e2e-hyg-test-' . bin2hex(random_bytes(8));
        mkdir($this->tmpDir, 0755, true);

        $this->runInDir('git init -b main');
        $this->runInDir('git config user.email "test@test.com"');
        $this->runInDir('git config user.name "Test"');

        mkdir($this->tmpDir . '/ibl5/tests/e2e', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->recursiveRm($this->tmpDir);
    }

    public function testTestOnlyExitsOne(): void
    {
        $spec = "import { test, expect } from '@playwright/test';\n";
        $spec .= "test.only('focused', async ({ page }) => {\n";
        $spec .= "  await expect(page.locator('h1')).toBeVisible();\n";
        $spec .= "});\n";
        $this->writeSpec('focused.spec.ts', $spec);

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('test-only', $result['output']);
        self::assertStringContainsString('focused.spec.ts', $result['output']);
    }

    public function testDescribeOnlyExitsOne(): void
    {
        $spec = "import { test, expect } from '@playwright/test';\n";
        $spec .= "test.describe.only('group', () => {\n";
        $spec .= "  test('a', async ({ page }) => {\n";
        $spec .= "    await expect(page.locator('h1')).toBeVisible();\n";
        $spec .= "  });\n";
        $spec .= "});\n";
        $this->writeSpec('group.spec.ts', $spec);

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('test-only', $result['output']);
    }

    public function testCleanSpecExitsZero(): void
    {
        $spec = "import { test, expect } from '@playwright/test';\n";
        $spec .= "test('renders heading', async ({ page }) => {\n";
        $spec .= "  await expect(page.locator('h1')).toHaveText('Home');\n";
        $spec .= "});\n";
        $this->writeSpec('clean.spec.ts', $spec);

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('PASS', $result['output']);
    }

    public function testHelpFlagExitsZero(): void
    {
        $output = [];
        $exit = 0;
        exec(escapeshellcmd($this->scriptPath) . ' --help 2>&1', $output, $exit);

        self::assertSame(0, $exit);
        self::assertStringContainsString('Usage:', implode("\n", $output));
    }

    /**
     * @return array{output: string, exit: int}
     */
    private function runScript(): array
    {
        $output = [];
        $exit = 0;
        $cmd = 'cd ' . escapeshellarg($this->tmpDir) . ' && '
            . 'bash ' . escapeshellarg($this->scriptPath) . ' 2>&1';
        exec($cmd, $output, $exit);

        return ['output' => implode("\n", $output), 'exit' => $exit];
    }

    private function writeSpec(string $filename, string $content): void
    {
        file_put_contents($this->tmpDir . '/ibl5/tests/e2e/' . $filename, $content);
    }

    private function runInDir(string $cmd): void
    {
        exec('cd ' . escapeshellarg($this->tmpDir) . ' && ' . $cmd . ' 2>&1');
    }

    private function recursiveRm(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->recursiveRm($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
