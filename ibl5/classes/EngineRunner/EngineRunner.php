<?php

declare(strict_types=1);

namespace EngineRunner;

use EngineRunner\Contracts\EngineRunnerInterface;

/**
 * Runs the compiled native sim binary (ibl5/bin/jsbsim) as a pure stdin/stdout
 * transform: bundle JSON in, Result JSON out.
 *
 * Security: the binary is invoked via proc_open with an EXPLICIT ARGV ARRAY (no
 * shell string), so neither the binary path nor the seed is ever subject to
 * shell interpretation. The binary path is validated before exec, the exit code
 * is checked, and empty/malformed output is treated as a failure.
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

    public function run(string $bundleJson, ?int $seed = null): string
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

        $descriptors = [
            0 => ['pipe', 'r'], // stdin  — bundle JSON
            1 => ['pipe', 'w'], // stdout — Result JSON
            2 => ['pipe', 'w'], // stderr — diagnostics
        ];

        $pipes = [];
        $process = proc_open($argv, $descriptors, $pipes);
        if (!is_resource($process)) {
            throw new EngineRunnerException("Failed to start engine process: {$this->binaryPath}");
        }

        fwrite($pipes[0], $bundleJson);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $detail = trim((string) $stderr);
            throw new EngineRunnerException(
                "Engine exited with code {$exitCode}" . ($detail !== '' ? ": {$detail}" : '')
            );
        }

        if (!is_string($stdout) || trim($stdout) === '') {
            throw new EngineRunnerException('Engine produced no output on stdout.');
        }

        try {
            json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new EngineRunnerException('Engine produced malformed JSON on stdout: ' . $e->getMessage(), 0, $e);
        }

        return $stdout;
    }
}
