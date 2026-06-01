<?php

declare(strict_types=1);

namespace EngineRunner;

use EngineRunner\Contracts\EngineRunnerInterface;

/**
 * Runs the compiled native sim binary (ibl5/bin/jsbsim) as a pure stdin/stdout
 * transform: bundle JSON in, NDJSON out (header line {"seed":N} then one compact
 * GameResult per line).
 *
 * Constant memory: stdout is spooled to a temp file rather than read into a PHP
 * string, so a full-season run (a multi-hundred-MB payload) never inflates the
 * PHP process. The temp file is read back one line at a time and each game is
 * handed to the caller's callback; peak RAM is one game, not the whole result.
 *
 * Security: the binary is invoked via proc_open with an EXPLICIT ARGV ARRAY (no
 * shell string), so neither the binary path nor the seed is ever subject to
 * shell interpretation. The binary path is validated before exec, the exit code
 * is checked, and a missing/malformed header or game line is treated as a failure.
 */
final class EngineRunner implements EngineRunnerInterface
{
    /** Default location the Docker entrypoint installs the binary to. */
    public const DEFAULT_BINARY_PATH = __DIR__ . '/../../bin/jsbsim';

    private readonly string $binaryPath;

    public function __construct(?string $binaryPath = null)
    {
        $this->binaryPath = $binaryPath ?? self::DEFAULT_BINARY_PATH;
    }

    /**
     * @param callable(array<string, mixed>, int): void $onGame
     */
    public function runStreaming(string $bundleJson, callable $onGame, ?int $seed = null): int
    {
        if (!is_file($this->binaryPath) || !is_executable($this->binaryPath)) {
            throw new EngineRunnerException(
                "Engine binary not found or not executable at {$this->binaryPath}"
            );
        }

        // Explicit argv array — proc_open bypasses the shell entirely.
        $argv = [$this->binaryPath];
        if ($seed !== null) {
            $argv[] = '--seed';
            $argv[] = (string) $seed;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'jsbsim_');
        if ($tmpPath === false) {
            throw new EngineRunnerException('Failed to create temp file for engine output.');
        }

        $descriptors = [
            0 => ['pipe', 'r'],            // stdin  — bundle JSON
            1 => ['file', $tmpPath, 'w'],  // stdout — NDJSON spooled to disk
            2 => ['pipe', 'w'],            // stderr — diagnostics
        ];

        $pipes = [];
        $process = proc_open($argv, $descriptors, $pipes);
        if (!is_resource($process)) {
            @unlink($tmpPath);
            throw new EngineRunnerException("Failed to start engine process: {$this->binaryPath}");
        }

        fwrite($pipes[0], $bundleJson);
        fclose($pipes[0]);

        // Drain stderr before proc_close to avoid a stderr-pipe-full deadlock on a
        // chatty run (stdout goes to the file descriptor, so $pipes[1] is unused).
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            @unlink($tmpPath);
            $detail = trim((string) $stderr);
            throw new EngineRunnerException(
                "Engine exited with code {$exitCode}" . ($detail !== '' ? ": {$detail}" : '')
            );
        }

        try {
            return $this->streamGames($tmpPath, $onGame);
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * Read the spooled NDJSON file: parse the header line for the seed, then decode
     * each subsequent non-empty line as one game and invoke $onGame.
     *
     * @param callable(array<string, mixed>, int): void $onGame
     *
     * @return int games processed
     */
    private function streamGames(string $tmpPath, callable $onGame): int
    {
        $handle = fopen($tmpPath, 'r');
        if ($handle === false) {
            throw new EngineRunnerException('Failed to open engine output for reading.');
        }

        try {
            $headerLine = fgets($handle);
            if ($headerLine === false || trim($headerLine) === '') {
                throw new EngineRunnerException('Engine produced no output (missing header line).');
            }

            try {
                /** @var array<string, mixed> $header */
                $header = json_decode($headerLine, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new EngineRunnerException('Engine emitted a malformed header line: ' . $e->getMessage(), 0, $e);
            }
            if (!is_array($header) || !isset($header['seed']) || !is_int($header['seed'])) {
                throw new EngineRunnerException('Engine header line is missing an integer "seed".');
            }
            $seed = $header['seed'];

            $count = 0;
            while (($line = fgets($handle)) !== false) {
                if (trim($line) === '') {
                    continue;
                }
                try {
                    /** @var array<string, mixed> $game */
                    $game = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new EngineRunnerException('Engine emitted a malformed game line: ' . $e->getMessage(), 0, $e);
                }
                $onGame($game, $seed);
                $count++;
            }

            return $count;
        } finally {
            fclose($handle);
        }
    }
}
