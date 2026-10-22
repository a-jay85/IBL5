<?php

declare(strict_types=1);

namespace Tests\Cli;

use Cli\LighthouseAuditReportFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Clock\FixedClock;

#[Group('cli')]
final class LighthouseAuditReportFormatterTest extends TestCase
{
    private LighthouseAuditReportFormatter $formatter;
    private string $fixtureDir;
    private string $savedTimezone;

    private const CAVEATS_THROUGH_RULE = "## Caveats\n\n"
        . "- Phase-gated modules (Draft, FreeAgency, Waivers) may render a \"not active\" gate page \u{2014} still audited for accessibility/best-practices.\n"
        . "- All pages audited in anonymous context (no authenticated user).\n\n"
        . "---\n\n";

    private const TABLE_HEADER = "| URL | Performance | Accessibility | Best Practices | Composite |\n"
        . "|-----|------------|---------------|----------------|----------|\n";

    private const ALL_PASS_BODY = "## Summary\n\n"
        . "- **URLs audited:** 2\n"
        . "- \u{2705} **Pass:** 2\n"
        . "- \u{1F7E1} **Warn:** 0\n"
        . "- \u{1F534} **Error:** 0\n\n"
        . "## Full Results\n\n"
        . self::TABLE_HEADER
        . "| `/modules.php?name=Standings` | 0.85 | 0.93 | 0.88 | 0.89 |\n"
        . "| `/index.php` | 0.92 | 0.96 | 0.91 | 0.93 |\n\n"
        . self::CAVEATS_THROUGH_RULE;

    private const MIXED_BODY = "## Summary\n\n"
        . "- **URLs audited:** 3\n"
        . "- \u{2705} **Pass:** 1\n"
        . "- \u{1F7E1} **Warn:** 1\n"
        . "- \u{1F534} **Error:** 1\n\n"
        . "## Flagged Pages\n\n"
        . self::TABLE_HEADER
        . "| `/modules.php?name=Player&pa=showpage&pid=1` | 0.70 | \u{1F534} 0.75 | 0.82 | 0.76 |\n"
        . "| `/modules.php?name=Standings` | \u{1F7E1} 0.55 | 0.90 | 0.85 | 0.77 |\n\n"
        . "## Full Results\n\n"
        . self::TABLE_HEADER
        . "| `/modules.php?name=Player&pa=showpage&pid=1` | 0.70 | \u{1F534} 0.75 | 0.82 | 0.76 |\n"
        . "| `/modules.php?name=Standings` | \u{1F7E1} 0.55 | 0.90 | 0.85 | 0.77 |\n"
        . "| `/index.php` | 0.92 | 0.96 | 0.91 | 0.93 |\n\n"
        . self::CAVEATS_THROUGH_RULE;

    protected function setUp(): void
    {
        $this->savedTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $this->formatter = new LighthouseAuditReportFormatter();
        $resolved = realpath(__DIR__ . '/../fixtures/lighthouse');
        self::assertNotFalse($resolved, 'Lighthouse fixture directory must exist');
        $this->fixtureDir = $resolved;
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->savedTimezone);
    }

    public function testRanksWorstFirst(): void
    {
        $manifest = $this->loadJson('audit-manifest-mixed.json');

        $result = $this->formatter->format($manifest);

        $lines = explode("\n", $result['body']);
        $dataRows = array_values(array_filter($lines, static fn(string $l): bool => str_starts_with($l, '| `')));

        self::assertNotSame([], $dataRows);
        self::assertStringContainsString('Player', $dataRows[0]);
    }

    public function testFlagsThresholdViolations(): void
    {
        $manifest = $this->loadJson('audit-manifest-mixed.json');

        $result = $this->formatter->format($manifest);

        self::assertStringContainsString("\u{1F534}", $result['body']);
        self::assertStringContainsString('0.75', $result['body']);
    }

    public function testSummaryCountsAccurate(): void
    {
        $manifest = $this->loadJson('audit-manifest-mixed.json');

        $result = $this->formatter->format($manifest);

        self::assertStringContainsString('**URLs audited:** 3', $result['body']);
        self::assertStringContainsString('**Pass:** 1', $result['body']);
        self::assertStringContainsString('**Warn:** 1', $result['body']);
        self::assertStringContainsString('**Error:** 1', $result['body']);
    }

    public function testEmptyManifestProducesEmptyReport(): void
    {
        $result = $this->formatter->format([]);

        self::assertStringContainsString('No pages were audited', $result['body']);
        self::assertStringContainsString('Lighthouse Audit', $result['title']);
    }

    public function testTitleContainsDate(): void
    {
        $manifest = $this->loadJson('audit-manifest-all-pass.json');
        $formatter = new LighthouseAuditReportFormatter(new FixedClock(1791549296));

        $result = $formatter->format($manifest);

        self::assertSame("Lighthouse Audit \u{2014} Week of 2026-10-09", $result['title']);
    }

    public function testTitleDateRollsOverAtUtcMidnight(): void
    {
        $manifest = $this->loadJson('audit-manifest-all-pass.json');
        $clock = new FixedClock(1791763199);
        $formatter = new LighthouseAuditReportFormatter($clock);

        $before = $formatter->format($manifest);
        $clock->advance(1);
        $after = $formatter->format($manifest);

        self::assertSame("Lighthouse Audit \u{2014} Week of 2026-10-11", $before['title']);
        self::assertSame("Lighthouse Audit \u{2014} Week of 2026-10-12", $after['title']);
    }

    public function testEmptyManifestTitleUsesInjectedClock(): void
    {
        $formatter = new LighthouseAuditReportFormatter(new FixedClock(1791549296));

        $result = $formatter->format([]);

        self::assertSame("Lighthouse Audit \u{2014} Week of 2026-10-09", $result['title']);
        self::assertSame("No pages were audited.\n", $result['body']);
    }

    public function testTitleHasWeekOfIsoDateShape(): void
    {
        $manifest = $this->loadJson('audit-manifest-all-pass.json');

        $result = $this->formatter->format($manifest);

        self::assertMatchesRegularExpression('/^Lighthouse Audit \x{2014} Week of \d{4}-\d{2}-\d{2}$/u', $result['title']);
    }

    public function testAllPassManifestHasNoFlaggedSection(): void
    {
        $manifest = $this->loadJson('audit-manifest-all-pass.json');

        $result = $this->formatter->format($manifest);

        self::assertStringNotContainsString('Flagged Pages', $result['body']);
    }

    public function testFooterContainsShaAndWorkflowUrl(): void
    {
        $manifest = $this->loadJson('audit-manifest-all-pass.json');

        $result = $this->formatter->format($manifest, 'abc1234', 'https://github.com/run/1');

        self::assertStringContainsString('`abc1234`', $result['body']);
        self::assertStringContainsString('[Workflow run](https://github.com/run/1)', $result['body']);
    }

    public function testAllPassBodyMatchesExactly(): void
    {
        $formatter = new LighthouseAuditReportFormatter(new FixedClock(1791549296));

        $result = $formatter->format($this->loadJson('audit-manifest-all-pass.json'));

        self::assertSame("Lighthouse Audit \u{2014} Week of 2026-10-09", $result['title']);
        self::assertSame(self::ALL_PASS_BODY, $result['body']);
    }

    public function testMixedBodyWithFlaggedSectionMatchesExactly(): void
    {
        $formatter = new LighthouseAuditReportFormatter(new FixedClock(1791549296));

        $result = $formatter->format($this->loadJson('audit-manifest-mixed.json'));

        self::assertSame(self::MIXED_BODY, $result['body']);
    }

    /**
     * @return array<string, array{?string, ?string, ?float, string}>
     */
    public static function footerVariantProvider(): array
    {
        return [
            'none' => [null, null, null, ''],
            'sha only' => ['abc1234', null, null, "Commit: `abc1234`\n"],
            'url only' => [null, 'https://github.com/run/1', null, "[Workflow run](https://github.com/run/1)\n"],
            'duration only' => [null, null, 125.0, "Duration: 2m\n"],
            'duration just under one minute' => [null, null, 59.9, "Duration: 0m\n"],
            'duration exactly one minute' => [null, null, 60.0, "Duration: 1m\n"],
            'all three' => [
                'abc1234',
                'https://github.com/run/1',
                125.0,
                "Commit: `abc1234` | [Workflow run](https://github.com/run/1) | Duration: 2m\n",
            ],
        ];
    }

    #[DataProvider('footerVariantProvider')]
    public function testFooterVariantBodyMatchesExactly(
        ?string $sha,
        ?string $workflowUrl,
        ?float $durationSeconds,
        string $expectedTail,
    ): void {
        $formatter = new LighthouseAuditReportFormatter(new FixedClock(1791549296));

        $result = $formatter->format(
            $this->loadJson('audit-manifest-all-pass.json'),
            $sha,
            $workflowUrl,
            $durationSeconds,
        );

        self::assertSame(self::ALL_PASS_BODY . $expectedTail, $result['body']);
    }

    public function testCliExitsTwoOnMissingManifest(): void
    {
        $result = $this->runScript([]);

        self::assertSame(2, $result['exit']);
        self::assertStringContainsString('--manifest', $result['output']);
    }

    public function testCliExitsZeroOnValidManifest(): void
    {
        $result = $this->runScript([
            '--manifest=' . $this->fixtureDir . '/audit-manifest-all-pass.json',
        ]);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('Lighthouse Audit', $result['output']);
    }

    /**
     * Catches: not assigning the resolver result to `$sha` (no Commit part),
     * and calling the formatter with the raw newest element (the expired
     * `ffffffff...` entry) instead of the resolver's pick.
     */
    public function testCliRendersAuditedCommitFromBaselineArtifacts(): void
    {
        $result = $this->runScript([
            '--manifest=' . $this->fixtureDir . '/audit-manifest-all-pass.json',
            '--baseline-artifacts=' . $this->fixtureDir . '/baseline-artifacts.json',
        ]);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString(
            'Commit: `39989da42eb8033c852937d16c6049b36007e167`',
            $result['output'],
        );
        self::assertStringNotContainsString('ffffffff', $result['output']);
    }

    /**
     * Catches: substituting any fallback value (for example the run sha) when
     * the resolver returns null.
     */
    public function testCliOmitsCommitWhenBaselineArtifactsYieldNoSha(): void
    {
        $result = $this->runScriptWithArtifactsContent('[]');

        self::assertSame(0, $result['exit']);
        self::assertStringNotContainsString('Commit:', $result['output']);
    }

    /**
     * Catches: deleting the warning `fwrite` when the resolver returns null.
     */
    public function testCliWarnsWhenBaselineArtifactsYieldNoSha(): void
    {
        $result = $this->runScriptWithArtifactsContent('[]');

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('Warning: no audited commit found', $result['output']);
    }

    /**
     * Catches: a resolver or script change that lets `JsonException` escape
     * (exit 255).
     */
    public function testCliOmitsCommitForMalformedBaselineArtifacts(): void
    {
        $result = $this->runScriptWithArtifactsContent('{not json');

        self::assertSame(0, $result['exit']);
        self::assertStringNotContainsString('Commit:', $result['output']);
    }

    /**
     * Catches: deleting the exclusivity check (exit 0 with one flag silently
     * winning).
     */
    public function testCliRejectsShaWithBaselineArtifacts(): void
    {
        $result = $this->runScript([
            '--manifest=' . $this->fixtureDir . '/audit-manifest-all-pass.json',
            '--sha=' . str_repeat('a', 40),
            '--baseline-artifacts=' . $this->fixtureDir . '/baseline-artifacts.json',
        ]);

        self::assertSame(2, $result['exit']);
        self::assertStringContainsString('mutually exclusive', $result['output']);
    }

    /**
     * Catches: deleting the `is_file` check (the read-failure branch prints a
     * different message).
     */
    public function testCliRejectsMissingBaselineArtifactsFile(): void
    {
        $result = $this->runScript([
            '--manifest=' . $this->fixtureDir . '/audit-manifest-all-pass.json',
            '--baseline-artifacts=/nonexistent/lh-artifacts.json',
        ]);

        self::assertSame(2, $result['exit']);
        self::assertStringContainsString('Baseline artifacts file not found', $result['output']);
    }

    /**
     * Catches: removing the `=== ''` arm together with `is_file`.
     */
    public function testCliRejectsEmptyBaselineArtifactsPath(): void
    {
        $result = $this->runScript([
            '--manifest=' . $this->fixtureDir . '/audit-manifest-all-pass.json',
            '--baseline-artifacts=',
        ]);

        self::assertSame(2, $result['exit']);
        self::assertStringContainsString('Baseline artifacts file not found', $result['output']);
    }

    /**
     * Catches: loosening the parse to `str_starts_with($arg, '--baseline-artifacts')`.
     */
    public function testCliRejectsSpaceSeparatedBaselineArtifactsFlag(): void
    {
        $result = $this->runScript([
            '--manifest=' . $this->fixtureDir . '/audit-manifest-all-pass.json',
            '--baseline-artifacts',
            $this->fixtureDir . '/baseline-artifacts.json',
        ]);

        self::assertSame(2, $result['exit']);
        self::assertStringContainsString('Unknown argument: --baseline-artifacts', $result['output']);
    }

    /**
     * Catches: breaking the `--sha=` parse branch while inserting the new one.
     */
    public function testCliStillRendersShaFlag(): void
    {
        $sha = str_repeat('c', 40);
        $result = $this->runScript([
            '--manifest=' . $this->fixtureDir . '/audit-manifest-all-pass.json',
            '--sha=' . $sha,
        ]);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('Commit: `' . $sha . '`', $result['output']);
    }

    /**
     * Catches: skipping the usage-text update.
     */
    public function testCliHelpListsBaselineArtifactsFlag(): void
    {
        $result = $this->runScript(['--help']);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('--baseline-artifacts=PATH', $result['output']);
    }

    /**
     * @return array{output: string, exit: int}
     */
    private function runScriptWithArtifactsContent(string $content): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'lh');
        self::assertNotFalse($tmp);

        try {
            file_put_contents($tmp, $content);

            return $this->runScript([
                '--manifest=' . $this->fixtureDir . '/audit-manifest-all-pass.json',
                '--baseline-artifacts=' . $tmp,
            ]);
        } finally {
            unlink($tmp);
        }
    }

    /**
     * @param list<string> $args
     * @return array{output: string, exit: int}
     */
    private function runScript(array $args): array
    {
        $scriptPath = realpath(__DIR__ . '/../../../bin/lighthouse-audit-report');
        self::assertNotFalse($scriptPath, 'bin/lighthouse-audit-report must exist');

        $cmd = escapeshellcmd($scriptPath);
        foreach ($args as $arg) {
            $cmd .= ' ' . escapeshellarg($arg);
        }
        $cmd .= ' 2>&1';

        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);

        return ['output' => implode("\n", $output), 'exit' => $exit];
    }

    /**
     * @return mixed
     */
    private function loadJson(string $filename): mixed
    {
        $content = file_get_contents($this->fixtureDir . '/' . $filename);
        self::assertNotFalse($content);
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertNotNull($decoded);
        return $decoded;
    }
}
