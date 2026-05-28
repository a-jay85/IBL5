<?php

declare(strict_types=1);

namespace Tests\Cli;

use Cli\LighthouseAuditReportFormatter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('cli')]
final class LighthouseAuditReportFormatterTest extends TestCase
{
    private LighthouseAuditReportFormatter $formatter;
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->formatter = new LighthouseAuditReportFormatter();
        $resolved = realpath(__DIR__ . '/../fixtures/lighthouse');
        self::assertNotFalse($resolved, 'Lighthouse fixture directory must exist');
        $this->fixtureDir = $resolved;
    }

    public function testRanksWorstFirst(): void
    {
        $manifest = $this->loadJson('audit-manifest-mixed.json');

        $result = $this->formatter->format($manifest);

        $lines = explode("\n", $result['body']);
        $dataRows = array_values(array_filter($lines, static fn(string $l): bool => str_starts_with($l, '| `')));

        self::assertNotEmpty($dataRows);
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

        $result = $this->formatter->format($manifest);

        self::assertStringContainsString(date('Y-m-d'), $result['title']);
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
