<?php

declare(strict_types=1);

namespace Tests\Cli;

use Cli\LighthouseCommentFormatter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('cli')]
final class LighthouseCommentFormatterTest extends TestCase
{
    private LighthouseCommentFormatter $formatter;
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->formatter = new LighthouseCommentFormatter();
        $resolved = realpath(__DIR__ . '/../fixtures/lighthouse');
        self::assertNotFalse($resolved, 'Lighthouse fixture directory must exist');
        $this->fixtureDir = $resolved;
    }

    /** Case 1: All scores pass, no baseline — no delta column */
    public function testAllPassNoBaseline(): void
    {
        $manifest = $this->loadJson('manifest-pass.json');
        $links = $this->loadJson('links.json');

        $result = $this->formatter->format($manifest, $links);

        self::assertFalse($result['hasErrorViolation']);
        self::assertStringContainsString('<!-- lighthouse-comment -->', $result['markdown']);
        self::assertStringContainsString('Lighthouse Audit', $result['markdown']);
        self::assertStringContainsString('view ↗', $result['markdown']);
        self::assertStringContainsString('No master baseline yet', $result['markdown']);
        $tableRows = $this->extractTableRows($result['markdown']);
        self::assertStringNotContainsString("\u{1F534}", $tableRows);
        self::assertStringNotContainsString("\u{1F7E1}", $tableRows);
    }

    /** Case 2: All scores pass, with baseline (no regression) — delta column shows ±0.00 */
    public function testAllPassWithBaselineNoRegression(): void
    {
        $manifest = $this->loadJson('manifest-pass.json');
        $baseline = $this->loadJson('baseline-manifest.json');
        $links = $this->loadJson('links.json');

        $result = $this->formatter->format($manifest, $links, $baseline);

        self::assertFalse($result['hasErrorViolation']);
        self::assertStringContainsString("\u{00B1}0.00", $result['markdown']);
        self::assertStringNotContainsString('No master baseline yet', $result['markdown']);
    }

    /** Case 3: One URL regresses by 0.05 vs baseline — shows yellow marker */
    public function testRegressionShowsYellowMarker(): void
    {
        $manifest = $this->loadJson('manifest-regression.json');
        $baseline = $this->loadJson('baseline-manifest.json');

        $result = $this->formatter->format($manifest, [], $baseline);

        self::assertFalse($result['hasErrorViolation']);
        self::assertStringContainsString("\u{1F7E1}", $result['markdown']);
        self::assertStringContainsString('0.55', $result['markdown']);
        self::assertStringContainsString('-0.05', $result['markdown']);
    }

    /** Case 4: One URL fails error threshold — shows red marker, hasErrorViolation true */
    public function testErrorThresholdShowsRedMarker(): void
    {
        $manifest = $this->loadJson('manifest-error.json');

        $result = $this->formatter->format($manifest);

        self::assertTrue($result['hasErrorViolation']);
        self::assertStringContainsString("\u{1F534}", $result['markdown']);
        self::assertStringContainsString('0.75', $result['markdown']);
    }

    /** Case 5: Baseline file absent (null) — comment includes "No master baseline yet" */
    public function testNoBaselineShowsFootnote(): void
    {
        $manifest = $this->loadJson('manifest-pass.json');

        $result = $this->formatter->format($manifest, [], null);

        self::assertFalse($result['hasErrorViolation']);
        self::assertStringContainsString('No master baseline yet', $result['markdown']);
    }

    /** Case 6: Empty manifest — shows "Lighthouse skipped" */
    public function testEmptyManifestShowsSkipped(): void
    {
        $result = $this->formatter->format([]);

        self::assertFalse($result['hasErrorViolation']);
        self::assertStringContainsString('<!-- lighthouse-comment -->', $result['markdown']);
        self::assertStringContainsString('Lighthouse skipped', $result['markdown']);
    }

    /** Case 7: bin/lighthouse-comment exits 2 on missing --manifest */
    public function testCliExitsTwoOnMissingManifest(): void
    {
        $result = $this->runScript([]);

        self::assertSame(2, $result['exit']);
        self::assertStringContainsString('--manifest', $result['output']);
    }

    /** Case 8: bin/lighthouse-comment exits 0 on all-pass manifest */
    public function testCliExitsZeroOnAllPass(): void
    {
        $result = $this->runScript([
            '--manifest=' . $this->fixtureDir . '/manifest-pass.json',
            '--links=' . $this->fixtureDir . '/links.json',
        ]);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('Lighthouse Audit', $result['output']);
    }

    /** Case 9: bin/lighthouse-comment exits 1 on error-threshold violation */
    public function testCliExitsOneOnErrorViolation(): void
    {
        $result = $this->runScript([
            '--manifest=' . $this->fixtureDir . '/manifest-error.json',
        ]);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString("\u{1F534}", $result['output']);
    }

    /**
     * @param list<string> $args
     * @return array{output: string, exit: int}
     */
    private function runScript(array $args): array
    {
        $scriptPath = realpath(__DIR__ . '/../../../bin/lighthouse-comment');
        self::assertNotFalse($scriptPath, 'bin/lighthouse-comment must exist');

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

    private function extractTableRows(string $markdown): string
    {
        $lines = explode("\n", $markdown);
        $rows = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, '| `')) {
                $rows[] = $line;
            }
        }
        return implode("\n", $rows);
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
