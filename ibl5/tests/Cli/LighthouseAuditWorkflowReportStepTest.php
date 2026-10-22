<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real `run:` body of the "Generate report" step in
 * .github/workflows/lighthouse-audit.yml under bash, with GitHub's default
 * shell flags, and asserts on the report it writes.
 */
#[Group('cli')]
final class LighthouseAuditWorkflowReportStepTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/../fixtures/lighthouse';

    /**
     * @return array<string, array{0: array<string, string>}>
     */
    public static function freshPathUseBaselineProvider(): array
    {
        return [
            'use_baseline false' => [['USE_BASELINE' => 'false']],
            'use_baseline unset' => [[]],
            'use_baseline empty' => [['USE_BASELINE' => '']],
        ];
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function missingValueProvider(): array
    {
        return [
            'unset' => [null],
            'empty' => [''],
        ];
    }

    /**
     * Mutations caught: deleting `args+=(--sha="$RUN_SHA")`, and changing the
     * baseline test to `[ -n "$USE_BASELINE" ]` (the 'false' case then takes
     * the baseline branch).
     *
     * @param array<string, string> $useBaseline
     */
    #[DataProvider('freshPathUseBaselineProvider')]
    public function testReportStepUsesRunShaOnFreshAuditPath(array $useBaseline): void
    {
        $result = self::runReportStep($useBaseline + ['RUN_SHA' => str_repeat('c', 40)]);

        self::assertSame(0, $result['exit'], $result['output']);
        self::assertStringContainsString('Commit: `' . str_repeat('c', 40) . '`', $result['body']);
    }

    /**
     * Mutation caught: reverting the step to an unconditional
     * `--sha="$RUN_SHA"` (exit 2 on mutual exclusion, or the wrong sha).
     */
    public function testReportStepUsesAuditedCommitOnBaselinePath(): void
    {
        $result = self::runReportStep([
            'USE_BASELINE' => 'true',
            'BASELINE_ARTIFACTS' => self::baselineArtifactsJson(),
            'RUN_SHA' => str_repeat('c', 40),
        ]);

        self::assertSame(0, $result['exit'], $result['output']);
        self::assertStringContainsString('39989da42eb8033c852937d16c6049b36007e167', $result['body']);
        self::assertStringNotContainsString('cccccccc', $result['body']);
    }

    /**
     * Mutation caught: an `else args+=(--sha="$RUN_SHA")` fallback on the
     * baseline path.
     */
    #[DataProvider('missingValueProvider')]
    public function testReportStepOmitsCommitWhenBaselineArtifactsMissing(?string $artifacts): void
    {
        $result = self::runReportStep(self::withOptional(
            ['USE_BASELINE' => 'true', 'RUN_SHA' => str_repeat('c', 40)],
            'BASELINE_ARTIFACTS',
            $artifacts,
        ));

        self::assertSame(0, $result['exit'], $result['output']);
        self::assertStringNotContainsString('Commit:', $result['body']);
    }

    /**
     * Mutation caught: deleting the `echo "::warning::..."` line.
     */
    #[DataProvider('missingValueProvider')]
    public function testReportStepWarnsWhenBaselineArtifactsMissing(?string $artifacts): void
    {
        $result = self::runReportStep(self::withOptional(
            ['USE_BASELINE' => 'true', 'RUN_SHA' => str_repeat('c', 40)],
            'BASELINE_ARTIFACTS',
            $artifacts,
        ));

        self::assertStringContainsString(
            '::warning::Baseline download reported no artifact metadata',
            $result['output'],
        );
    }

    /**
     * Mutation caught: dropping the `elif [ -n "${RUN_SHA:-}" ]` guard
     * (renders an empty `Commit: ` code span).
     */
    #[DataProvider('missingValueProvider')]
    public function testReportStepOmitsCommitWhenRunShaMissing(?string $runSha): void
    {
        $result = self::runReportStep(self::withOptional(['USE_BASELINE' => 'false'], 'RUN_SHA', $runSha));

        self::assertSame(0, $result['exit'], $result['output']);
        self::assertStringNotContainsString('Commit:', $result['body']);
    }

    /**
     * Mutation caught: deleting the `args+=(--workflow-url=...)` line.
     */
    public function testReportStepRendersWorkflowUrlWhenSet(): void
    {
        $result = self::runReportStep([
            'USE_BASELINE' => 'false',
            'RUN_SHA' => str_repeat('c', 40),
            'WORKFLOW_URL' => 'https://github.com/a-jay85/IBL5/actions/runs/123',
        ]);

        self::assertSame(0, $result['exit'], $result['output']);
        self::assertStringContainsString('actions/runs/123', $result['body']);
    }

    /**
     * Mutation caught: removing the `-n` guard on WORKFLOW_URL, if the
     * formatter renders an empty URL differently from a null one. If it renders
     * them the same, that mutation is equivalent and harmless.
     */
    #[DataProvider('missingValueProvider')]
    public function testReportStepOmitsWorkflowUrlWhenMissing(?string $workflowUrl): void
    {
        $sha = str_repeat('c', 40);
        $result = self::runReportStep(self::withOptional(
            ['USE_BASELINE' => 'false', 'RUN_SHA' => $sha],
            'WORKFLOW_URL',
            $workflowUrl,
        ));

        self::assertSame(0, $result['exit'], $result['output']);
        self::assertSame(self::renderReferenceBody($sha), $result['body']);
    }

    /**
     * Mutation caught: putting `${{ github.sha }}` (or any expression) back
     * into the `run:` body.
     */
    public function testReportStepRunBodyHasNoExpressionInterpolation(): void
    {
        self::assertStringNotContainsString('${{', self::extractRunBody());
    }

    /**
     * @param array<string, string> $env
     * @return array<string, string>
     */
    private static function withOptional(array $env, string $key, ?string $value): array
    {
        if ($value !== null) {
            $env[$key] = $value;
        }

        return $env;
    }

    /**
     * Body that the report script writes with no --workflow-url at all.
     */
    private static function renderReferenceBody(string $sha): string
    {
        $out = sys_get_temp_dir() . '/' . uniqid('lh-ref-', true) . '.md';

        try {
            $script = realpath(__DIR__ . '/../../../bin/lighthouse-audit-report');
            self::assertIsString($script);

            $process = proc_open(
                [
                    $script,
                    '--manifest=' . self::FIXTURE_DIR . '/audit-manifest-all-pass.json',
                    '--sha=' . $sha,
                    '--out-body=' . $out,
                ],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                null,
                ['PATH' => (string) getenv('PATH')],
            );
            self::assertIsResource($process);
            $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $output);

            $body = file_get_contents($out);
            self::assertIsString($body);

            return $body;
        } finally {
            if (is_file($out)) {
                unlink($out);
            }
        }
    }

    private static function baselineArtifactsJson(): string
    {
        $json = file_get_contents(self::FIXTURE_DIR . '/baseline-artifacts.json');
        self::assertIsString($json);

        return $json;
    }

    /**
     * @return string the step's `run:` body, de-indented, with `/tmp/` untouched
     */
    private static function extractRunBody(): string
    {
        $path = realpath(__DIR__ . '/../../../.github/workflows/lighthouse-audit.yml');
        self::assertIsString($path);

        // phpunit-hygiene-allow: the extracted run: body is the workflow file's shape and is executed, not string-matched
        $yaml = file_get_contents($path);
        self::assertIsString($yaml);

        $lines = explode("\n", $yaml);
        $inStep = false;
        $inRun = false;
        $body = [];
        foreach ($lines as $line) {
            if (!$inStep) {
                if ($line === '      - name: Generate report') {
                    $inStep = true;
                }
                continue;
            }
            if (!$inRun) {
                if ($line === '        run: |') {
                    $inRun = true;
                }
                continue;
            }
            if (str_starts_with($line, '      - name:')) {
                break;
            }
            $body[] = str_starts_with($line, '          ') ? substr($line, 10) : $line;
        }

        $text = rtrim(implode("\n", $body)) . "\n";
        self::assertNotSame("\n", $text, 'Generate report run: body not found');

        return $text;
    }

    /**
     * @param array<string, string> $env only the keys set here reach the step
     * @return array{exit: int, output: string, body: string}
     */
    private static function runReportStep(array $env): array
    {
        $body = self::extractRunBody();

        $work = sys_get_temp_dir() . '/' . uniqid('lh-step-', true);
        self::assertTrue(mkdir($work . '/bin', 0o777, true));

        try {
            $body = str_replace('/tmp/', $work . '/', $body);

            $script = realpath(__DIR__ . '/../../../bin/lighthouse-audit-report');
            self::assertIsString($script);
            self::assertTrue(symlink($script, $work . '/bin/lighthouse-audit-report'));

            self::assertTrue(mkdir($work . '/ibl5/.lighthouseci', 0o777, true));
            self::assertTrue(copy(
                self::FIXTURE_DIR . '/audit-manifest-all-pass.json',
                $work . '/ibl5/.lighthouseci/manifest-filtered.json',
            ));

            self::assertNotFalse(file_put_contents($work . '/step.sh', $body));

            $process = proc_open(
                ['bash', '--noprofile', '--norc', '-eo', 'pipefail', 'step.sh'],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                $work,
                ['PATH' => (string) getenv('PATH')] + $env,
            );
            self::assertIsResource($process);

            // Step output is a few lines, far below the pipe buffer, so reading sequentially cannot deadlock.
            $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);

            $issueBody = is_file($work . '/issue-body.md') ? file_get_contents($work . '/issue-body.md') : '';

            return [
                'exit' => $exit,
                'output' => $output,
                'body' => $issueBody === false ? '' : $issueBody,
            ];
        } finally {
            self::removeTree($work);
        }
    }

    private static function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }
        if (!is_dir($path)) {
            return;
        }
        $entries = scandir($path);
        foreach ($entries === false ? [] : $entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            self::removeTree($path . '/' . $entry);
        }
        rmdir($path);
    }
}
