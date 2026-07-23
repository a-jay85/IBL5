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
    public function sinceBodyChangedSameDayAsVerifiedExitsZero(): void
    {
        // A doc already verified today (e.g. by an earlier same-day merge) whose
        // body is edited again the same day. last_verified cannot be bumped higher
        // without becoming a future date, so the commit-date escape must pass it —
        // no waiting for UTC rollover. The kept date equals the edit's commit date.
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $this->commitFile('ibl5/docs/sample.md', $this->doc($today, 'Original body.'), 'base verified today');
        $base = $this->currentSha();
        $this->commitFile('ibl5/docs/sample.md', $this->doc($today, 'Edited body, same day.'), 'edit body same day');

        [$code, $output] = $this->runScript('--since=' . $base);
        $this->assertSame(0, $code, $output);
    }

    #[Test]
    public function sinceRebasedOntoBaseAlreadyVerifiedTodayExitsZero(): void
    {
        // A doc PR verified today, then rebased onto a base whose copy of the doc
        // was ALSO bumped to today by a sibling PR. Base and head last_verified are
        // now equal (today), but the rebase PRESERVES the head commit's author date
        // — which predates today. The freshness escape (lv >= edit commit date)
        // must pass it; the old equality escape (lv === edit commit date) false-
        // failed because today !== the stale author date. Body still differs, so
        // this exercises the escape, not the values-differ path.
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $this->commitFile('ibl5/docs/sample.md', $this->doc($today, 'Original body.'), 'base verified today by sibling PR');
        $base = $this->currentSha();

        // Head commit with an author date in the past (what a rebase leaves behind).
        $staleAuthorDate = (new \DateTimeImmutable('today -4 days'))->format('Y-m-d\T12:00:00');
        file_put_contents($this->tmpDir . '/ibl5/docs/sample.md', $this->doc($today, 'Rebased body.'));
        $this->runGit('add -A');
        $this->runGit(sprintf('commit -q --date=%s -m %s', escapeshellarg($staleAuthorDate), escapeshellarg('rebased edit, author date preserved')));

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

    #[Test]
    public function sinceEmptyRefExitsTwo(): void
    {
        $this->commitFile('ibl5/docs/sample.md', $this->doc($this->freshDate()), 'base');

        [$code, $output] = $this->runScript('--since=');
        $this->assertSame(2, $code, $output);
        $this->assertStringContainsString('requires a base ref', $output);
    }

    // --- Phase 3: --no-staleness (PR/push gate de-fang) ---

    #[Test]
    public function noStalenessStaleButValidDocExitsZero(): void
    {
        // Same fixture as fullScanWithStaleDocExitsOne, but the flag suppresses
        // the staleness failure so an untouched stale doc no longer blocks.
        $this->commitFile('ibl5/docs/sample.md', $this->doc('2026-01-01'), 'add stale doc');

        [$code, $output] = $this->runScript('--no-staleness');
        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('docs verified', $output);
        $this->assertStringNotContainsString('stale', $output);
    }

    #[Test]
    public function noStalenessStillFailsOnDeadReference(): void
    {
        // A doc that is BOTH stale AND has a dead repo-path reference: the flag
        // suppresses staleness, but the dead-ref failure must still fail the run.
        $body = "References `ibl5/classes/DoesNotExist.php` which is not real.";
        $this->commitFile('ibl5/docs/sample.md', $this->doc('2026-01-01', $body), 'stale + dead ref');

        [$code, $output] = $this->runScript('--no-staleness');
        $this->assertSame(1, $code, $output);
        $this->assertStringContainsString('dead reference', $output);
        $this->assertStringNotContainsString('stale', $output);
    }

    #[Test]
    public function noStalenessStillFailsOnMissingFrontmatter(): void
    {
        // A second non-staleness failure class (missing frontmatter) survives the flag.
        $this->commitFile('ibl5/docs/sample.md', "no frontmatter here\n", 'no frontmatter');

        [$code, $output] = $this->runScript('--no-staleness');
        $this->assertSame(1, $code, $output);
        $this->assertStringContainsString('missing frontmatter block', $output);
    }

    // --- Phase 4: --staleness-report (nightly audit data source) ---

    #[Test]
    public function stalenessReportStaleDocExitsZeroWithJson(): void
    {
        $this->commitFile('ibl5/docs/sample.md', $this->doc('2026-01-01'), 'add stale doc');

        [$code, $output] = $this->runScript('--staleness-report');
        $this->assertSame(0, $code, $output);

        $report = json_decode($output, true);
        $this->assertIsArray($report, $output);
        $this->assertCount(1, $report);
        $entry = $report[0];
        $this->assertSame('ibl5/docs/sample.md', $entry['path']);
        $this->assertSame('2026-01-01', $entry['last_verified']);
        $this->assertArrayHasKey('age_days', $entry);
        $this->assertArrayHasKey('days_over', $entry);
        $this->assertGreaterThan(60, $entry['age_days']);
        $this->assertSame($entry['age_days'] - 60, $entry['days_over']);
    }

    #[Test]
    public function stalenessReportAllFreshExitsZeroEmptyList(): void
    {
        // Zero-stale must be a valid empty JSON array, not an empty string —
        // the nightly workflow's `jq 'length'` depends on it.
        $this->commitFile('ibl5/docs/sample.md', $this->doc($this->freshDate()), 'add fresh doc');

        [$code, $output] = $this->runScript('--staleness-report');
        $this->assertSame(0, $code, $output);

        $report = json_decode($output, true);
        $this->assertIsArray($report, $output);
        $this->assertCount(0, $report);
    }

    #[Test]
    public function stalenessReportExcludesMissingDateFromReport(): void
    {
        // Missing last_verified is the gate's job, never the staleness report's.
        $noDate = "---\ndescription: No date here.\n---\n\n# Sample\n\nBody.\n";
        $this->commitFile('ibl5/docs/sample.md', $noDate, 'add doc without date');

        [$code, $output] = $this->runScript('--staleness-report');
        $this->assertSame(0, $code, $output);

        $report = json_decode($output, true);
        $this->assertIsArray($report, $output);
        $this->assertCount(0, $report);
    }

    // --- Phase 5: --since backlog transitions gate ---

    #[Test]
    public function sinceBodyStatusDoneWithoutPointerExitsOne(): void
    {
        $this->commitFile(
            'ibl5/docs/backlog/ci-backlog.md',
            $this->doc($this->freshDate(), "### 1 Item\n\n**Status (2026-07-01):** ⬜ Open — an item.\n"),
            'base'
        );
        $base = $this->currentSha();
        $this->commitFile(
            'ibl5/docs/backlog/ci-backlog.md',
            $this->doc($this->freshDate(0), "### 1 Item\n\n**Status (2026-07-10):** ✅ Implemented — an item.\n"),
            'mark done inline'
        );

        [$code, $output] = $this->runScript('--since=' . $base);
        $this->assertSame(1, $code, $output);
        $this->assertStringContainsString('marked done inline', $output);
    }

    #[Test]
    public function sinceArchivePointerTargetMissingExitsOne(): void
    {
        $this->commitFile(
            'ibl5/docs/backlog/e2e-backlog.md',
            $this->doc($this->freshDate(), "### 1 Item\n\n**Status (2026-07-01):** ⬜ Open — an item.\n"),
            'base'
        );
        $base = $this->currentSha();
        $this->commitFile(
            'ibl5/docs/backlog/e2e-backlog.md',
            $this->doc($this->freshDate(0), "See [archive](archive/e2e-backlog-archive.md).\n"),
            'add dead pointer'
        );

        [$code, $output] = $this->runScript('--since=' . $base);
        $this->assertSame(1, $code, $output);
        $this->assertStringContainsString('does not resolve', $output);
    }

    #[Test]
    public function sinceNewPointerCanonicalSiblingMissingExitsOne(): void
    {
        $this->commitFile('ibl5/docs/backlog/archive/maintenance-backlog-archive.md', "archive\n", 'existing archive');
        $this->commitFile(
            'ibl5/docs/backlog/token-spend-backlog.md',
            $this->doc($this->freshDate(), "### 1 Item\n\n**Status (2026-07-01):** ⬜ Open — an item.\n"),
            'base'
        );
        $base = $this->currentSha();
        $this->commitFile(
            'ibl5/docs/backlog/token-spend-backlog.md',
            $this->doc($this->freshDate(0), "See [archive](archive/maintenance-backlog-archive.md).\n"),
            'add pointer to wrong sibling'
        );

        [$code, $output] = $this->runScript('--since=' . $base);
        $this->assertSame(1, $code, $output);
        $this->assertStringContainsString('canonical archive sibling', $output);
    }

    #[Test]
    public function sinceArchivedWithSiblingAndPointerExitsZero(): void
    {
        $this->commitFile(
            'ibl5/docs/backlog/a11y-backlog.md',
            $this->doc($this->freshDate(), "### 1 Item\n\n**Status (2026-07-01):** ⬜ Open — an item.\n"),
            'base'
        );
        $base = $this->currentSha();

        @mkdir($this->tmpDir . '/ibl5/docs/backlog/archive', 0o777, true);
        file_put_contents($this->tmpDir . '/ibl5/docs/backlog/archive/a11y-backlog-archive.md', "archive\n");
        $this->commitFile(
            'ibl5/docs/backlog/a11y-backlog.md',
            $this->doc($this->freshDate(0), "➜ 1 Item — ✅ Implemented (2026-07-10): see [archive](archive/a11y-backlog-archive.md).\n"),
            'archive item'
        );

        [$code, $output] = $this->runScript('--since=' . $base);
        $this->assertSame(0, $code, $output);
    }

    #[Test]
    public function sinceMaintenanceTableStatusDoneExemptExitsZero(): void
    {
        $this->commitFile(
            'ibl5/docs/backlog/maintenance-backlog.md',
            $this->doc($this->freshDate(), "| ID | Item | Status |\n|----|------|--------|\n| C1 | Foo | ⬜ |\n"),
            'base'
        );
        $base = $this->currentSha();
        $this->commitFile(
            'ibl5/docs/backlog/maintenance-backlog.md',
            $this->doc($this->freshDate(0), "| ID | Item | Status |\n|----|------|--------|\n| C1 | Foo | ✅ |\n"),
            'flip row done'
        );

        [$code, $output] = $this->runScript('--since=' . $base);
        $this->assertSame(0, $code, $output);
    }

    #[Test]
    public function sinceUnchangedBadBacklogNotInDiffExitsZero(): void
    {
        $this->commitFile(
            'ibl5/docs/backlog/sample-backlog.md',
            $this->doc($this->freshDate(), "### 1 Item\n\n**Status (2026-07-01):** ✅ Implemented — no pointer, deliberately bad.\n"),
            'bad backlog'
        );
        $this->commitFile('ibl5/docs/sample.md', $this->doc($this->freshDate(), 'Original body.'), 'unrelated doc');
        $base = $this->currentSha();
        $this->commitFile('ibl5/docs/sample.md', $this->doc($this->freshDate(0), 'Edited body.'), 'edit unrelated');

        [$code, $output] = $this->runScript('--since=' . $base);
        $this->assertSame(0, $code, $output);
    }

    #[Test]
    public function engineBacklogInScopeStaleFlagged(): void
    {
        // Phase 4 scope extension: engine/docs/backlog/*.md is now freshness-gated.
        $this->commitFile(
            'engine/docs/backlog/jsb-native-backlog.md',
            $this->doc($this->freshDate(61), "### J1 Item\n\nbody\n"),
            'stale engine backlog'
        );
        [$code, $output] = $this->runScript();
        $this->assertNotSame(0, $code, $output);
        $this->assertStringContainsString('engine/docs/backlog/jsb-native-backlog.md', $output);
    }
}
