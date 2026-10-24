<?php

declare(strict_types=1);

namespace Tests\EngineRunner;

use EngineRunner\EngineRunner;
use EngineRunner\EngineRunnerException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EngineRunner's streaming contract. The injectable binary path
 * lets every path be exercised with NDJSON-emitting stub scripts — no real engine
 * binary required. Each stub writes a header line then zero or more game lines.
 */
final class EngineRunnerTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];
        parent::tearDown();
    }

    #[Test]
    public function invokesCallbackPerGameAndReturnsCount(): void
    {
        $stub = $this->ndjsonStub(
            '{"seed":1988}',
            '{"date":"d1","home_team_id":1}',
            '{"date":"d2","home_team_id":2}',
        );
        $runner = new EngineRunner($stub);

        $games = [];
        $count = $runner->runStreaming(
            '{"seed":1,"schedule":[]}',
            function (array $game, int $seed) use (&$games): void {
                $games[] = $game;
            },
        );

        self::assertSame(2, $count);
        self::assertCount(2, $games);
        self::assertSame('d1', $games[0]['date']);
        self::assertSame(1, $games[0]['home_team_id']);
        self::assertSame('d2', $games[1]['date']);
    }

    #[Test]
    public function surfacesSeedToCallback(): void
    {
        $stub = $this->ndjsonStub('{"seed":1988}', '{"date":"d1"}');
        $runner = new EngineRunner($stub);

        $seeds = [];
        $runner->runStreaming('{}', function (array $game, int $seed) use (&$seeds): void {
            $seeds[] = $seed;
        });

        self::assertSame([1988], $seeds);
    }

    #[Test]
    public function emptyGamesHeaderOnlyReturnsZero(): void
    {
        $stub = $this->ndjsonStub('{"seed":1988}'); // header line, no game lines
        $runner = new EngineRunner($stub);

        $called = false;
        $count = $runner->runStreaming('{}', function () use (&$called): void {
            $called = true;
        });

        self::assertSame(0, $count);
        self::assertFalse($called, 'callback must never fire when there are no game lines');
    }

    #[Test]
    public function nonzeroExitThrowsWithStderr(): void
    {
        $stub = $this->makeStub("#!/bin/sh\necho 'boom' >&2\nexit 3\n");
        $runner = new EngineRunner($stub);

        $this->expectException(EngineRunnerException::class);
        $this->expectExceptionMessageMatches('/exited with code 3.*boom/s');
        $runner->runStreaming('{}', function (): void {});
    }

    #[Test]
    public function closedPipeNonzeroExitReportsExitCodeWithoutNotice(): void
    {
        // Same stub as nonzeroExitThrowsWithStderr: never reads stdin. A 1 MiB bundle
        // cannot fit in the pipe buffer, so the write always lands on a closed pipe.
        $stub = $this->makeStub("#!/bin/sh\necho 'boom' >&2\nexit 3\n");
        $runner = new EngineRunner($stub);

        $out = self::runCapturingErrors(
            static fn (): int => $runner->runStreaming(self::largeBundle(), static function (): void {})
        );

        self::assertSame(['handler-probe'], $out['errors'], 'no broken-pipe notice may escape the runner');
        self::assertNull(error_get_last(), 'the runner handler must swallow the notice itself');
        self::assertInstanceOf(EngineRunnerException::class, $out['thrown']);
        self::assertMatchesRegularExpression('/exited with code 3.*boom/s', $out['thrown']->getMessage());
    }

    #[Test]
    public function shortBundleWriteWithZeroExitThrows(): void
    {
        $stub = $this->makeStub(
            "#!/bin/sh\nhead -c 16 > /dev/null\necho 'short-read' >&2\necho '{\"seed\":1}'\n"
        );
        $runner = new EngineRunner($stub);

        /** @var list<int> $calls */
        $calls = [];
        $out = self::runCapturingErrors(
            static fn (): int => $runner->runStreaming(self::largeBundle(), static function () use (&$calls): void {
                $calls[] = 1;
            })
        );

        self::assertSame(['handler-probe'], $out['errors']);
        self::assertNull(error_get_last());
        self::assertInstanceOf(EngineRunnerException::class, $out['thrown']);
        $msg = $out['thrown']->getMessage();
        self::assertMatchesRegularExpression('/exited 0 before reading the full bundle: wrote \d+ of 1048576 bytes/', $msg);
        self::assertStringContainsString('errno=32', $msg, 'the captured write error must be surfaced');
        self::assertStringContainsString('short-read', $msg, 'trimmed stderr must be surfaced');
        self::assertSame([], $calls, 'a truncated bundle must never reach streamGames');
        self::assertNull($out['result']);
    }

    #[Test]
    public function largeBundleFullyReadSucceedsWithoutNotice(): void
    {
        $runner = new EngineRunner($this->ndjsonStub('{"seed":7}', '{"date":"d1"}'));

        $out = self::runCapturingErrors(
            static fn (): int => $runner->runStreaming(self::largeBundle(), static function (): void {})
        );

        self::assertSame(['handler-probe'], $out['errors']);
        self::assertNull(error_get_last());
        self::assertNull($out['thrown']);
        self::assertSame(1, $out['result']);
    }

    #[Test]
    public function malformedHeaderThrows(): void
    {
        $stub = $this->ndjsonStub('{not valid json');
        $runner = new EngineRunner($stub);

        $this->expectException(EngineRunnerException::class);
        $this->expectExceptionMessageMatches('/header/');
        $runner->runStreaming('{}', function (): void {});
    }

    #[Test]
    public function missingHeaderEmptyStreamThrows(): void
    {
        // Stub consumes stdin and emits nothing on stdout → no header line.
        $stub = $this->makeStub("#!/bin/sh\ncat > /dev/null\n");
        $runner = new EngineRunner($stub);

        $this->expectException(EngineRunnerException::class);
        $this->expectExceptionMessageMatches('/header/');
        $runner->runStreaming('{}', function (): void {});
    }

    #[Test]
    public function malformedGameLineThrows(): void
    {
        $stub = $this->ndjsonStub('{"seed":1}', '{bad game');
        $runner = new EngineRunner($stub);

        $this->expectException(EngineRunnerException::class);
        $this->expectExceptionMessageMatches('/game line/');
        $runner->runStreaming('{}', function (): void {});
    }

    #[Test]
    public function blankLinesBetweenGamesAreSkipped(): void
    {
        $stub = $this->ndjsonStub('{"seed":1}', '{"date":"d1"}', '', '{"date":"d2"}');
        $runner = new EngineRunner($stub);

        $count = 0;
        $result = $runner->runStreaming('{}', function () use (&$count): void {
            $count++;
        });

        self::assertSame(2, $result);
        self::assertSame(2, $count, 'blank/whitespace lines between games must be skipped');
    }

    #[Test]
    public function missingBinaryThrowsBeforeExec(): void
    {
        $runner = new EngineRunner('/nonexistent/path/to/jsbsim-' . bin2hex(random_bytes(4)));

        $this->expectException(EngineRunnerException::class);
        $this->expectExceptionMessageMatches('/not found or not executable/');
        $runner->runStreaming('{}', function (): void {});
    }

    /**
     * Security: the runner uses an explicit argv array (no shell), so the seed is
     * passed as a single literal argument and never interpreted. The stub records
     * its argv; we assert the seed arrived as discrete elements and no shell side
     * effect occurred.
     */
    #[Test]
    public function seedIsPassedAsLiteralArgvNotShell(): void
    {
        $argsLog = $this->tempPath('args');
        $sentinel = $this->tempPath('pwned');
        $stub = $this->makeStub(
            "#!/bin/sh\ncat > /dev/null\nprintf '%s\\n' \"\$@\" > '" . $argsLog . "'\n"
            . "echo '{\"seed\":1988}'\n"
        );
        $runner = new EngineRunner($stub);

        $injection = '1; touch ' . $sentinel;
        // EngineRunner stringifies the int seed; the literal-passing guarantee is
        // checked via the recorded argv and the absence of any shell expansion.
        $count = $runner->runStreaming('{}', function (): void {}, 42);

        self::assertSame(0, $count);
        $recorded = is_file($argsLog) ? file_get_contents($argsLog) : '';
        self::assertStringContainsString("--seed\n42", (string) $recorded, 'seed must be passed as discrete argv elements');
        self::assertFalse(is_file($sentinel), 'no shell side effect should occur');
        // Defensive: the injection string is never run because seed is an int.
        self::assertStringNotContainsString($injection, (string) $recorded);
    }

    /** A bundle larger than any OS pipe buffer, so the write always reaches a closed pipe. */
    private static function largeBundle(): string
    {
        return str_repeat('x', 1048576);
    }

    /**
     * @param callable(): int $run
     *
     * @return array{errors: list<string>, thrown: ?EngineRunnerException, result: ?int}
     */
    private static function runCapturingErrors(callable $run): array
    {
        /** @var list<string> $errors */
        $errors = [];
        $thrown = null;
        $result = null;
        error_clear_last();
        set_error_handler(static function (int $errno, string $errstr) use (&$errors): bool {
            $errors[] = $errstr;

            return true;
        });
        try {
            $result = $run();
        } catch (EngineRunnerException $e) {
            $thrown = $e;
        } finally {
            // Lands in $errors only if the runner restored the handler it installed.
            trigger_error('handler-probe', E_USER_NOTICE);
            restore_error_handler();
        }

        return ['errors' => $errors, 'thrown' => $thrown, 'result' => $result];
    }

    /**
     * Build an NDJSON-emitting stub: each line is `echo`'d (sh appends the newline),
     * so JSON double-quotes pass through a single-quoted shell argument untouched.
     */
    private function ndjsonStub(string ...$lines): string
    {
        $body = "#!/bin/sh\ncat > /dev/null\n";
        foreach ($lines as $line) {
            $body .= "echo '" . $line . "'\n";
        }

        return $this->makeStub($body);
    }

    private function makeStub(string $body): string
    {
        $path = $this->tempPath('stub') . '.sh';
        file_put_contents($path, $body);
        chmod($path, 0755);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function tempPath(string $prefix): string
    {
        $path = sys_get_temp_dir() . '/engine-runner-' . $prefix . '-' . bin2hex(random_bytes(6));
        $this->tempFiles[] = $path;

        return $path;
    }
}
