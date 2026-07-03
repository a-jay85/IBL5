<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('cli')]
final class AdrCheckCliTest extends TestCase
{
    private string $binPath;

    protected function setUp(): void
    {
        $bin = realpath(__DIR__ . '/../../../bin/adr-check');
        self::assertIsString($bin, 'bin/adr-check must exist');
        $this->binPath = $bin;
    }

    public function testHelpFlagPrintsUsageAndExitsZero(): void
    {
        $output = [];
        $exit = 0;
        exec(escapeshellcmd($this->binPath) . ' --help 2>&1', $output, $exit);
        self::assertSame(0, $exit);
        self::assertStringContainsString('Decision-trigger gate', implode("\n", $output));
    }

    public function testUnknownFlagExitsTwo(): void
    {
        exec(escapeshellcmd($this->binPath) . ' --bogus 2>&1', $unused, $exit);
        self::assertSame(2, $exit);
    }

    /**
     * A dependency version bump modifies an existing require line — same package
     * key on both sides of the diff — so it must NOT trip the new-dependency
     * trigger. This is the dependabot-bump false-positive that motivated the
     * key-diff detection (PR #1293).
     */
    public function testVersionBumpDoesNotTriggerNewDependency(): void
    {
        $before = $this->composerJson(['infection/infection' => '^0.33.2']);
        $after = $this->composerJson(['infection/infection' => '^0.34.0']);

        [$exit, $output] = $this->runStagedAgainst($before, $after);

        self::assertSame(0, $exit, "version bump should pass, got:\n{$output}");
        self::assertStringContainsString('ADR not required', $output);
    }

    /**
     * Adding a genuinely new require entry (a key with no removed counterpart)
     * must still trip the trigger and fail without an accompanying ADR.
     */
    public function testNewDependencyTriggersWithoutAdr(): void
    {
        $before = $this->composerJson(['infection/infection' => '^0.34.0']);
        $after = $this->composerJson([
            'infection/infection' => '^0.34.0',
            'vendor/newpackage' => '^1.0',
        ]);

        [$exit, $output] = $this->runStagedAgainst($before, $after);

        self::assertSame(1, $exit, "new dependency should fail, got:\n{$output}");
        self::assertStringContainsString('new-dependency', $output);
    }

    /**
     * A bump that also happens to reorder or reformat unrelated require lines
     * must still pass: no key is added-without-removed.
     */
    public function testBumpAlongsideRemovedDependencyStillPasses(): void
    {
        $before = $this->composerJson([
            'infection/infection' => '^0.33.2',
            'vendor/dropme' => '^1.0',
        ]);
        $after = $this->composerJson(['infection/infection' => '^0.34.0']);

        [$exit, $output] = $this->runStagedAgainst($before, $after);

        self::assertSame(0, $exit, "bump + removal should pass, got:\n{$output}");
    }

    /**
     * @param array<string, string> $requireDev
     */
    private function composerJson(array $requireDev): string
    {
        return (string) json_encode(
            ['name' => 'ibl5/test', 'require-dev' => $requireDev],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        ) . "\n";
    }

    /**
     * Build a throwaway git repo, commit `ibl5/composer.json` with $before, stage
     * $after, and run `bin/adr-check --staged` from the repo root.
     *
     * @return array{0: int, 1: string}
     */
    private function runStagedAgainst(string $before, string $after): array
    {
        $repo = sys_get_temp_dir() . '/adr-check-test-' . bin2hex(random_bytes(6));
        mkdir($repo . '/ibl5', 0o777, true);
        $composer = $repo . '/ibl5/composer.json';

        $git = 'git -C ' . escapeshellarg($repo)
            . ' -c user.email=test@example.com -c user.name=test'
            . ' -c commit.gpgsign=false';

        file_put_contents($composer, $before);
        exec("{$git} init -q 2>&1");
        exec("{$git} add ibl5/composer.json 2>&1");
        exec("{$git} commit -q -m initial 2>&1");

        file_put_contents($composer, $after);
        exec("{$git} add ibl5/composer.json 2>&1");

        $output = [];
        $exit = 0;
        exec(
            'cd ' . escapeshellarg($repo) . ' && '
            . escapeshellcmd($this->binPath) . ' --staged 2>&1',
            $output,
            $exit,
        );

        $this->rrmdir($repo);

        return [$exit, implode("\n", $output)];
    }

    private function rrmdir(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
