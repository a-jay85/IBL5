<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CLI-level tests for bin/check-docs.
 *
 * Builds a throwaway git repo per test so the changed-files (--since) mode runs
 * against real git plumbing rather than mocks. Because bin/check-docs resolves
 * its repo root from __DIR__ (the script location), the script is copied into
 * the tmp repo's bin/ so the walk-up lands on the throwaway repo, not the real
 * IBL5 checkout.
 */
#[Group('cli')]
final class CheckDocsCliTest extends TestCase
{
    private string $tmpDir;
    private string $scriptPath;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/cdocs-cli-' . uniqid('', true);
        mkdir($this->tmpDir, 0o777, true);

        // Copy the real script into the tmp repo so repoRoot() (which walks up
        // from __DIR__) resolves to the throwaway repo.
        $repoScript = realpath(__DIR__ . '/../../../bin/check-docs');
        $this->assertIsString($repoScript, 'bin/check-docs not found');
        mkdir($this->tmpDir . '/bin', 0o777, true);
        copy($repoScript, $this->tmpDir . '/bin/check-docs');
        $this->scriptPath = $this->tmpDir . '/bin/check-docs';

        $this->runGit('init -q');
        $this->runGit('config user.email test@example.com');
        $this->runGit('config user.name Test');
        $this->runGit('config commit.gpgsign false');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    /**
     * Run the script inside the tmp repo. Returns [exitCode, combinedOutput].
     *
     * @return array{int, string}
     */
    private function runScript(string $args = ''): array
    {
        $cmd = sprintf(
            'cd %s && php %s %s 2>&1',
            escapeshellarg($this->tmpDir),
            escapeshellarg($this->scriptPath),
            $args
        );
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);
        return [$code, implode("\n", $output)];
    }

    private function runGit(string $args): void
    {
        $cmd = sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $args);
        exec($cmd);
    }

    private function commitFile(string $relPath, string $content, string $message): void
    {
        $full = $this->tmpDir . '/' . $relPath;
        @mkdir(dirname($full), 0o777, true);
        file_put_contents($full, $content);
        $this->runGit('add -A');
        $this->runGit(sprintf('commit -q -m %s', escapeshellarg($message)));
    }

    private function currentSha(): string
    {
        $cmd = sprintf('cd %s && git rev-parse HEAD 2>&1', escapeshellarg($this->tmpDir));
        return trim((string) shell_exec($cmd));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($dir);
    }

    /** Build a valid in-scope doc body with the given last_verified date. */
    private function doc(string $lastVerified, string $body = 'Original body text.'): string
    {
        return "---\n"
            . "description: Sample doc for testing.\n"
            . "last_verified: {$lastVerified}\n"
            . "---\n\n"
            . "# Sample\n\n"
            . "{$body}\n";
    }

    /** A recent (fresh) ISO date well within the 60-day staleness window. */
    private function freshDate(int $daysAgo = 5): string
    {
        return (new \DateTimeImmutable("today -{$daysAgo} days"))->format('Y-m-d');
    }

    // --- Phase 1: characterization (lock in existing full-scan contract) ---

    #[Test]
    public function helpExitsZeroAndShowsUsage(): void
    {
        [$code, $output] = $this->runScript('--help');
        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('Usage:', $output);
    }

    #[Test]
    public function bogusFlagExitsTwo(): void
    {
        [$code, $output] = $this->runScript('--bogus');
        $this->assertSame(2, $code, $output);
        $this->assertStringContainsString('unknown flag', $output);
    }

    #[Test]
    public function fullScanWithValidDocExitsZero(): void
    {
        $this->commitFile('ibl5/docs/sample.md', $this->doc($this->freshDate()), 'add doc');
        [$code, $output] = $this->runScript();
        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('docs verified', $output);
    }

    #[Test]
    public function fullScanWithStaleDocExitsOne(): void
    {
        $this->commitFile('ibl5/docs/sample.md', $this->doc('2026-01-01'), 'add stale doc');
        [$code, $output] = $this->runScript();
        $this->assertSame(1, $code, $output);
        $this->assertStringContainsString('stale', $output);
    }

    // --- Phase 2: changed-files (--since) mode ---

    #[Test]
    public function sinceBodyChangedNotBumpedExitsOne(): void
    {
        $date = $this->freshDate();
        $this->commitFile('ibl5/docs/sample.md', $this->doc($date, 'Original body.'), 'base');
        $base = $this->currentSha();
        $this->commitFile('ibl5/docs/sample.md', $this->doc($date, 'Edited body.'), 'edit body only');

        [$code, $output] = $this->runScript('--since=' . $base);
        $this->assertSame(1, $code, $output);
        $this->assertStringContainsString('last_verified not bumped', $output);
    }

    #[Test]
    public function sinceBodyChangedAndBumpedExitsZero(): void
    {
        $this->commitFile('ibl5/docs/sample.md', $this->doc($this->freshDate(20), 'Original body.'), 'base');
        $base = $this->currentSha();
        $this->commitFile('ibl5/docs/sample.md', $this->doc($this->freshDate(2), 'Edited body.'), 'edit + bump');

        [$code, $output] = $this->runScript('--since=' . $base);
        $this->assertSame(0, $code, $output);
    }

    #[Test]
    public function sinceOutOfScopeChangeExitsZero(): void
    {
        $date = $this->freshDate();
        $this->commitFile('ibl5/docs/sample.md', $this->doc($date), 'base in-scope doc');
        $this->commitFile('notes/scratch.md', "no frontmatter\n", 'base out-of-scope');
        $base = $this->currentSha();
        $this->commitFile('notes/scratch.md', "no frontmatter, edited\n", 'edit out-of-scope only');

        [$code, $output] = $this->runScript('--since=' . $base);
        $this->assertSame(0, $code, $output);
    }

    #[Test]
    public function sinceFutureDateExitsOne(): void
    {
        $this->commitFile('ibl5/docs/sample.md', $this->doc($this->freshDate(), 'Original body.'), 'base');
        $base = $this->currentSha();
        $future = (new \DateTimeImmutable('today +30 days'))->format('Y-m-d');
        $this->commitFile('ibl5/docs/sample.md', $this->doc($future, 'Edited body.'), 'edit + future date');

        [$code, $output] = $this->runScript('--since=' . $base);
        $this->assertSame(1, $code, $output);
        $this->assertStringContainsString('in the future', $output);
    }

    #[Test]
    public function sinceAddedDocWithDateExitsZero(): void
    {
        $this->commitFile('ibl5/docs/existing.md', $this->doc($this->freshDate()), 'base');
        $base = $this->currentSha();
        $this->commitFile('ibl5/docs/added.md', $this->doc($this->freshDate()), 'add new doc with date');

        [$code, $output] = $this->runScript('--since=' . $base);
        $this->assertSame(0, $code, $output);
    }

    #[Test]
    public function sinceAddedDocWithoutDateExitsOne(): void
    {
        $this->commitFile('ibl5/docs/existing.md', $this->doc($this->freshDate()), 'base');
        $base = $this->currentSha();
        $noDate = "---\ndescription: Added without a date.\n---\n\n# Added\n\nBody.\n";
        $this->commitFile('ibl5/docs/added.md', $noDate, 'add new doc without date');

        [$code, $output] = $this->runScript('--since=' . $base);
        $this->assertSame(1, $code, $output);
        $this->assertStringContainsString('last_verified not bumped', $output);
    }

    #[Test]
    public function sinceBadRefExitsTwo(): void
    {
        $this->commitFile('ibl5/docs/sample.md', $this->doc($this->freshDate()), 'base');

        [$code, $output] = $this->runScript('--since=deadbeef123nonexistent');
        $this->assertSame(2, $code, $output);
        $this->assertStringContainsString('unable to diff against base ref', $output);
    }
}
