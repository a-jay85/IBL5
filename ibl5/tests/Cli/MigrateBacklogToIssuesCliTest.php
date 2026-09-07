<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CLI-level tests for bin/migrate-backlog-to-issues.
 *
 * Builds a throwaway git repo per test, writes small hand-written fixture
 * backlogs into it, copies the real script to bin/ so repoRoot() resolves
 * to the throwaway root, and invokes it via proc_open. Every test runs in
 * dry-run or --existing-json mode — none touches the network or calls gh
 * for real (a fake gh shim handles any apply-path calls).
 */
#[Group('cli')]
final class MigrateBacklogToIssuesCliTest extends TestCase
{
    private string $tmpDir;
    private string $scriptPath;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/mbti-cli-' . uniqid('', true);
        mkdir($this->tmpDir, 0o777, true);

        $repoScript = realpath(__DIR__ . '/../../../bin/migrate-backlog-to-issues');
        $this->assertIsString($repoScript, 'bin/migrate-backlog-to-issues not found');

        mkdir($this->tmpDir . '/bin', 0o777, true);
        copy($repoScript, $this->tmpDir . '/bin/migrate-backlog-to-issues');
        $this->scriptPath = $this->tmpDir . '/bin/migrate-backlog-to-issues';

        // Fake gh shim: on "gh issue create" writes --body-file content to
        // $FAKE_GH_BODY_OUT if set, then returns a synthetic issue URL.
        // On any other subcommand (issue close, issue list, etc.) exits 0.
        $fakeGh = <<<'SH'
#!/bin/sh
if [ "$1" = "issue" ] && [ "$2" = "create" ]; then
    BODY_FILE=""
    PREV=""
    for arg in "$@"; do
        if [ "$PREV" = "--body-file" ]; then
            BODY_FILE="$arg"
        fi
        PREV="$arg"
    done
    if [ -n "$BODY_FILE" ] && [ -f "$BODY_FILE" ] && [ -n "$FAKE_GH_BODY_OUT" ]; then
        cat "$BODY_FILE" > "$FAKE_GH_BODY_OUT"
    fi
    echo "https://github.com/test-owner/test-repo/issues/1"
    exit 0
fi
if [ "$1" = "issue" ] && [ "$2" = "close" ]; then
    # Mirror real gh, which prints a confirmation line the tool checks for.
    # Args are: issue close --repo <slug> <number>
    echo "✓ Closed issue #$5"
    exit 0
fi
exit 0
SH;
        file_put_contents($this->tmpDir . '/bin/gh', $fakeGh);
        chmod($this->tmpDir . '/bin/gh', 0o755);

        $this->runGit('init -q');
        $this->runGit('config user.email test@example.com');
        $this->runGit('config user.name Test');
        $this->runGit('config commit.gpgsign false');

        $this->createMinimalSourceTree();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    private function runGit(string $args): void
    {
        exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $args));
    }

    /**
     * Write a file into the throwaway repo tree (does not commit).
     */
    private function writeFile(string $relPath, string $content): void
    {
        $full = $this->tmpDir . '/' . $relPath;
        @mkdir(dirname($full), 0o777, true);
        file_put_contents($full, $content);
    }

    /**
     * Write a temp JSON file for --existing-json and return its path.
     *
     * @param list<array<string,mixed>> $issues
     */
    private function existingJsonFile(array $issues = []): string
    {
        $path = $this->tmpDir . '/existing-' . uniqid('', true) . '.json';
        file_put_contents($path, json_encode($issues));
        return $path;
    }

    /**
     * Run the script inside the throwaway repo root via proc_open.
     *
     * Uses `env -i` so the child process starts with a clean environment —
     * this is the only reliable way to propagate empty-string env vars (e.g.
     * BACKLOG_ISSUES_REPO='') on macOS, where proc_open's $env array strips
     * zero-length values before exec. PATH is prefixed with $tmpDir/bin so
     * the fake gh shim is found first.
     *
     * @param array<string,string> $extraEnv
     * @return array{int, string}
     */
    private function runScript(string $args = '', array $extraEnv = []): array
    {
        $baseEnv = [
            'PATH'   => $this->tmpDir . '/bin:' . (getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin'),
            'HOME'   => $this->tmpDir,
            'TMPDIR' => sys_get_temp_dir(),
        ];

        $envParts = [];
        foreach (array_merge($baseEnv, $extraEnv) as $k => $v) {
            $envParts[] = $k . '=' . escapeshellarg($v);
        }
        $envStr = implode(' ', $envParts);

        $cmd = 'env -i ' . $envStr . ' ' . PHP_BINARY . ' ' . escapeshellarg($this->scriptPath);
        if ($args !== '') {
            $cmd .= ' ' . $args;
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $descriptors, $pipes, $this->tmpDir, null);
        $this->assertIsResource($proc, 'proc_open failed');

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        return [$code, $output];
    }

    /**
     * Create placeholder content for all source paths named in sourcesMap().
     * Placeholder files have a single H1 heading and no ### sections, so
     * each yields one whole-file Issue (Tier c) without any ID headings.
     * Individual tests override specific files as needed.
     */
    private function createMinimalSourceTree(): void
    {
        // All non-skip_missing paths from bin/migrate-backlog-to-issues sourcesMap()
        $paths = [
            'ibl5/docs/backlog/a11y-backlog.md',
            'ibl5/docs/backlog/a11y-contrast-backlog.md',
            'ibl5/docs/backlog/ci-backlog.md',
            'ibl5/docs/backlog/dev-efficiency-backlog.md',
            'ibl5/docs/backlog/e2e-backlog.md',
            'ibl5/docs/backlog/loop-engineering-backlog.md',
            'ibl5/docs/backlog/maintenance-backlog.md',
            'ibl5/docs/backlog/token-spend-backlog.md',
            'ibl5/docs/backlog/voting-csrf-single-use-post-redisplay.md',
            'ibl5/docs/backlog/draft-selection-pick-ownership.md',
            'ibl5/docs/backlog/retired-flag-backfill-and-ret-propagation.md',
            'engine/docs/backlog/jsb-native-backlog.md',
            'ibl5/docs/backlog/archive/ci-backlog-archive.md',
            'ibl5/docs/backlog/archive/dev-efficiency-backlog-archive.md',
            'ibl5/docs/backlog/archive/e2e-backlog-archive.md',
            'ibl5/docs/backlog/archive/jsb-native-backlog-archive.md',
            'ibl5/docs/backlog/archive/loop-engineering-backlog-archive.md',
            'ibl5/docs/backlog/archive/maintenance-backlog-archive.md',
            'ibl5/docs/backlog/archive/token-spend-backlog-archive.md',
        ];
        foreach ($paths as $relPath) {
            $this->writeFile($relPath, "# Placeholder\n");
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        /** @var list<string> $items */
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    // ─── matrix row 9 ─────────────────────────────────────────────────────────

    #[Test]
    public function testUnknownFlagExitsTwoWithUsage(): void
    {
        [$code, $output] = $this->runScript('--bogus-flag-xyz');
        $this->assertSame(2, $code, $output);
        $this->assertStringContainsString('unknown option', $output);
        $this->assertStringContainsString('Usage:', $output);
    }

    // ─── matrix row 10 ────────────────────────────────────────────────────────

    #[Test]
    public function testUnsetBacklogIssuesRepoEnvUsesDefaultSlug(): void
    {
        // Run with --apply and --existing-json so the "Loading existing Issues
        // from <repo>..." line is printed, revealing which slug was resolved.
        $json = $this->existingJsonFile([]);
        [$code, $output] = $this->runScript(
            '--apply --existing-json=' . escapeshellarg($json) . ' --limit=1'
        );
        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('a-jay85/IBL5-backlog', $output);
    }

    // ─── matrix row 11 ────────────────────────────────────────────────────────

    #[Test]
    public function testEmptyBacklogIssuesRepoEnvExitsTwo(): void
    {
        [$code, $output] = $this->runScript('', ['BACKLOG_ISSUES_REPO' => '']);
        $this->assertSame(2, $code, $output);
        $this->assertStringContainsString('BACKLOG_ISSUES_REPO is set but empty', $output);
    }

    // ─── matrix row 12 ────────────────────────────────────────────────────────

    #[Test]
    public function testDryRunWritesNothingIntoTheRepoTree(): void
    {
        // Commit the source tree so git sees a clean baseline.
        $this->runGit('add -A');
        $this->runGit('commit -q -m "init source tree"');

        [$code, $output] = $this->runScript('--dry-run');
        $this->assertSame(0, $code, $output);

        // The repo working tree must be untouched.
        $cmd = sprintf('cd %s && git status --porcelain 2>&1', escapeshellarg($this->tmpDir));
        $status = trim((string) shell_exec($cmd));
        $this->assertSame('', $status, 'dry-run dirtied the repo tree: ' . $status);
    }

    // ─── matrix row 13 ────────────────────────────────────────────────────────

    #[Test]
    public function testDryRunEmitsOneIssuePerIdHeading(): void
    {
        // Override a11y-backlog.md with exactly three ### ID headings.
        // a11y has no archive file in sourcesMap(), so the count is entirely
        // controlled by this fixture (no archive item inflates the total).
        $this->writeFile('ibl5/docs/backlog/a11y-backlog.md', implode("\n", [
            '# A11y Backlog',
            '',
            '### 1.1 First item',
            '',
            'Body one.',
            '',
            '### 1.2 Second item',
            '',
            'Body two.',
            '',
            '### 1.3 Third item',
            '',
            'Body three.',
            '',
        ]));

        [$code, $output] = $this->runScript('--dry-run');
        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('item(s) would be created', $output);
        // The a11y label must show count 3 in the per-label summary table.
        $this->assertMatchesRegularExpression('/a11y\s+3\b/', $output);
    }

    // ─── matrix row 14 ────────────────────────────────────────────────────────

    #[Test]
    public function testTableStatusRowBecomesIssueWithResolvedState(): void
    {
        // extractItems() only reaches extractTableRowItems() when at least one
        // ### section exists (zero sections triggers Tier-c whole-file return).
        // C0 is a dummy section that escapes Tier (c); T1 is the table-row item
        // whose ✅ cell sets status='resolved' → [will close] in apply output.
        $this->writeFile('ibl5/docs/backlog/maintenance-backlog.md', implode("\n", [
            '# Maintenance Backlog',
            '',
            '### C0 Dummy section',
            '',
            'Escapes Tier (c) so the table-rows path is reached.',
            '',
            '| ID | Axis | Status |',
            '|----|------|--------|',
            '| T1 | Something | ✅ done |',
            '',
        ]));

        $json = $this->existingJsonFile([]);
        [$code, $output] = $this->runScript(
            '--apply --existing-json=' . escapeshellarg($json)
        );
        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('T1', $output);
        $this->assertStringContainsString('[will close]', $output);
    }

    // ─── matrix row 15 ────────────────────────────────────────────────────────

    #[Test]
    public function testArchiveFileYieldsClosedIssuesLabelledArchived(): void
    {
        // Override ci-backlog-archive.md with a real heading so the item
        // gets a recognized title and the 'archived' label appears in output.
        $this->writeFile('ibl5/docs/backlog/archive/ci-backlog-archive.md', implode("\n", [
            '# CI Archive',
            '',
            '### A1 Some archived item',
            '',
            'Archived body.',
            '',
        ]));

        $json = $this->existingJsonFile([]);
        [$code, $output] = $this->runScript(
            '--apply --existing-json=' . escapeshellarg($json)
        );
        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('archived', $output);
        $this->assertStringContainsString('[will close]', $output);
    }

    // ─── matrix row 16 ────────────────────────────────────────────────────────

    #[Test]
    public function testFileWithNoHeadingsYieldsSingleWholeFileIssue(): void
    {
        // Override a11y-backlog.md with prose and no ### sections.
        // Tier (c): zero headings → one whole-file Issue.
        // a11y has no archive file in sourcesMap(), so the count of 1 is
        // solely from this fixture — no archive item inflates it to 2.
        $this->writeFile('ibl5/docs/backlog/a11y-backlog.md', implode("\n", [
            '# A11y Backlog',
            '',
            'Single prose section with no heading markers.',
            'All of this becomes one whole-file Issue.',
            '',
        ]));

        [$code, $output] = $this->runScript('--dry-run');
        $this->assertSame(0, $code, $output);
        // a11y label must show count 1.
        $this->assertMatchesRegularExpression('/a11y\s+1\b/', $output);
    }

    #[Test]
    public function testWholeFileSourceDoesNotSplitOnNestedSubheadings(): void
    {
        // draft-selection-pick-ownership.md nests ### as subsections of each
        // ## Finding, and the generic sub-headings repeat verbatim under every
        // finding. Splitting on ### collided them onto one idempotency key and
        // silently dropped every body after the first, so the source is marked
        // whole_file. Two identical ### headings must still yield one Issue.
        $this->writeFile('ibl5/docs/backlog/draft-selection-pick-ownership.md', implode("\n", [
            '# Draft Selection: Pick-Slot Ownership',
            '',
            '## Finding 1 — first finding',
            '',
            '### Problem',
            '',
            'First problem body.',
            '',
            '## Finding 2 — second finding',
            '',
            '### Problem',
            '',
            'Second problem body.',
            '',
        ]));
        // The other security source would otherwise add to the label count.
        $this->writeFile(
            'ibl5/docs/backlog/voting-csrf-single-use-post-redisplay.md',
            "# Voting CSRF\n\nProse only.\n"
        );

        [$code, $output] = $this->runScript('--dry-run');
        $this->assertSame(0, $code, $output);
        // One Issue per security file — not one per ### subsection.
        $this->assertMatchesRegularExpression('/security\s+2\b/', $output);
    }

    #[Test]
    public function testCollidingHeadingKeysInOneFileAbortTheRun(): void
    {
        // The idempotency key is only as unique as the heading it reads. Two
        // non-ID headings with the same text in one file map to one key, so the
        // second body would be silently skipped onto the first one's Issue —
        // and the created/skipped counters could not tell that apart from a
        // legitimate live/archive twin collapse. Abort instead.
        $this->writeFile('ibl5/docs/backlog/ci-backlog.md', implode("\n", [
            '# CI Backlog',
            '',
            '### Problem',
            '',
            'First body.',
            '',
            '### Problem',
            '',
            'Second body — would be dropped.',
            '',
        ]));

        [$code, $output] = $this->runScript('--dry-run');
        $this->assertSame(1, $code, $output);
        $this->assertStringContainsString('colliding idempotency keys', $output);
        $this->assertStringContainsString('ci-backlog.md', $output);
        $this->assertStringContainsString('dropped: Problem', $output);
    }

    // ─── matrix row 17 ────────────────────────────────────────────────────────

    #[Test]
    public function testExistingIssueWithSameIdAndLabelIsSkipped(): void
    {
        // ci-backlog.md has one item with ID 1.1.
        $this->writeFile('ibl5/docs/backlog/ci-backlog.md', implode("\n", [
            '# CI Backlog',
            '',
            '### 1.1 Existing item',
            '',
            'Body.',
            '',
        ]));

        // Seed the existing-issues JSON with ci/1.1 already present.
        $json = $this->existingJsonFile([
            ['number' => 99, 'title' => '1.1 Existing item', 'labels' => [['name' => 'ci']]],
        ]);

        [$code, $output] = $this->runScript(
            '--apply --existing-json=' . escapeshellarg($json)
        );
        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('SKIP', $output);
        $this->assertStringContainsString('1.1', $output);
    }

    #[Test]
    public function testResolvedItemLeftOpenByAnEarlierRunIsHealed(): void
    {
        // A close that failed on an earlier run is not self-correcting through
        // the creation path — every later run skips the item. The skip branch
        // must therefore re-close a resolved item whose Issue is still OPEN,
        // and must leave an already-CLOSED one alone.
        $this->writeFile('ibl5/docs/backlog/ci-backlog.md', implode("\n", [
            '# CI Backlog',
            '',
            '### 1.1 Left open by an earlier run',
            '',
            '**Status:** ✅ Implemented.',
            '',
            '### 1.2 Already closed',
            '',
            '**Status:** ✅ Implemented.',
            '',
        ]));

        $json = $this->existingJsonFile([
            [
                'number' => 99,
                'title'  => '1.1 Left open by an earlier run',
                'labels' => [['name' => 'ci']],
                'state'  => 'OPEN',
            ],
            [
                'number' => 98,
                'title'  => '1.2 Already closed',
                'labels' => [['name' => 'ci']],
                'state'  => 'CLOSED',
            ],
        ]);

        [$code, $output] = $this->runScript(
            '--apply --existing-json=' . escapeshellarg($json)
        );
        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('Healed: closed #99', $output);
        $this->assertStringContainsString('Healed: 1 resolved Issue(s)', $output);
        $this->assertStringNotContainsString('Healed: closed #98', $output);
    }

    // ─── matrix row 18 ────────────────────────────────────────────────────────

    #[Test]
    public function testSameIdInTwoAreasCreatesTwoIssues(): void
    {
        // Both ci and maintenance backlogs carry an item with ID 1.1.
        // The idempotency key is (area-label, id): (ci, 1.1) ≠ (maintenance, 1.1),
        // so both must be created independently.
        $this->writeFile('ibl5/docs/backlog/ci-backlog.md', implode("\n", [
            '# CI Backlog',
            '',
            '### 1.1 CiTitle',
            '',
            'CI body.',
            '',
        ]));
        $this->writeFile('ibl5/docs/backlog/maintenance-backlog.md', implode("\n", [
            '# Maintenance Backlog',
            '',
            '### 1.1 MaintTitle',
            '',
            'Maint body.',
            '',
        ]));

        // Empty existing-issues: both items start absent.
        // After ci/1.1 is created (fake gh returns issues/1), the map entry
        // is keyed by (ci, 1.1). The maintenance/1.1 item uses key (maintenance, 1.1)
        // and must NOT match — so it also gets created.
        $json = $this->existingJsonFile([]);
        [$code, $output] = $this->runScript(
            '--apply --existing-json=' . escapeshellarg($json)
        );
        $this->assertSame(0, $code, $output);
        // Both 1.1 items must appear as CREATE lines (not SKIP).
        $this->assertStringContainsString('CREATE 1.1 — CiTitle', $output);
        $this->assertStringContainsString('CREATE 1.1 — MaintTitle', $output);
    }

    // ─── matrix row 19 ────────────────────────────────────────────────────────

    #[Test]
    public function testBodyOverSixtyThousandCharsIsTruncated(): void
    {
        // Build a ### section whose body exceeds BODY_CAP (60 000 chars).
        // The fake gh shim writes the body passed to it into $FAKE_GH_BODY_OUT
        // so we can inspect the capped length after the run.
        $longBody = str_repeat('x', 70_000);
        $this->writeFile('ibl5/docs/backlog/a11y-backlog.md', implode("\n", [
            '# A11y Backlog',
            '',
            '### A1 Very large item',
            '',
            $longBody,
            '',
        ]));

        $bodyCapturePath = $this->tmpDir . '/gh_body_captured.txt';
        $json = $this->existingJsonFile([]);

        // --limit=1 processes only the first item (a11y-backlog is first in
        // sourcesMap, and its single section is the large item).
        [$code, $output] = $this->runScript(
            '--apply --existing-json=' . escapeshellarg($json) . ' --limit=1',
            ['FAKE_GH_BODY_OUT' => $bodyCapturePath]
        );
        $this->assertSame(0, $code, $output);
        $this->assertFileExists($bodyCapturePath, 'fake gh was not called');

        $capturedBody = (string) file_get_contents($bodyCapturePath);
        $truncationSuffix = "\n\n> [truncated — see git history of the retired backlog file]";
        $this->assertStringContainsString('[truncated', $capturedBody);
        // The captured body must be at most BODY_CAP + suffix chars.
        $this->assertLessThanOrEqual(
            60_000 + strlen($truncationSuffix),
            strlen($capturedBody),
            'body was not capped at 60 000 chars'
        );
    }
}
