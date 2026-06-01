<?php

declare(strict_types=1);

namespace EngineShadow;

use EngineShadow\Contracts\ShadowProcessLauncherInterface;

/**
 * Launches runEngineShadow.php as a DETACHED background process so a long/heavy/
 * crashing full-season shadow sim never blocks or breaks the synchronous admin
 * "Update All The Things" web request.
 *
 * Detachment mechanism (php:apache, Linux):
 *   - EXPLICIT ARGV ARRAY via proc_open — no shell string, no user input, so
 *     command injection is structurally impossible (same precedent as EngineRunner).
 *   - `setsid --fork`: setsid forks and the intermediate parent exits immediately,
 *     so proc_open's DIRECT child returns at once and proc_close() does NOT block
 *     the request. The grandchild runs php in a NEW session/process group, so when
 *     Apache reaps the request worker the SIGHUP/SIGTERM to the worker's group does
 *     not reach the shadow run — it outlives the request. (Without --fork, setsid
 *     would exec php in place and proc_close would block for the whole season.)
 *   - FILE descriptors, not pipes: a pipe with no reader deadlocks the child once
 *     the ~64 KB OS buffer fills on a full-season run, so stdout/stderr go to a
 *     log file and stdin to /dev/null.
 *
 * Not final: the protected spawn() seam is overridden in tests to record argv
 * instead of launching a real process.
 */
class ShadowProcessLauncher implements ShadowProcessLauncherInterface
{
    public function __construct(
        private readonly string $scriptPath,
        private readonly string $logPath,
        private readonly string $phpBinary = '/usr/local/bin/php',
        private readonly string $setsidBinary = '/usr/bin/setsid',
    ) {
    }

    public function launch(): void
    {
        if (!is_file($this->scriptPath)) {
            throw new \RuntimeException("Shadow CLI script not found at {$this->scriptPath}");
        }

        // Explicit argv array — no shell, no user input. `--fork` makes setsid fork
        // so the direct child exits immediately (proc_close does not block).
        $argv = [$this->setsidBinary, '--fork', $this->phpBinary, $this->scriptPath];

        // File descriptors, never pipes (an unread pipe deadlocks a full-season run).
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $this->logPath, 'a'],
            2 => ['file', $this->logPath, 'a'],
        ];

        $this->spawn($argv, $descriptors);
    }

    /**
     * Spawn seam: starts the process and returns without waiting. Overridden in
     * unit tests to record argv instead of spawning a real process.
     *
     * @param list<string>                                  $argv
     * @param array<int, array{0: string, 1: string, 2?: string}> $descriptors
     */
    protected function spawn(array $argv, array $descriptors): void
    {
        $pipes = [];
        $process = proc_open($argv, $descriptors, $pipes);
        if (is_resource($process)) {
            // Do NOT wait: setsid --fork already detached the run into its own
            // session. proc_close reaps the short-lived intermediate child only.
            proc_close($process);
        }
    }
}
