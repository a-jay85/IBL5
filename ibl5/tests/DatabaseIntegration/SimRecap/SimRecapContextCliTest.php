<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\SimRecap;

use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * Database integration test for scripts/simRecapContext.php.
 *
 * Launches the script as a real child process so the full path through
 * argv-parse → buildContext → JSON stdout is exercised against a live database.
 *
 * Tests the unknown-sim (999999) path: no fixtures are required, the script
 * must exit 0 and emit a well-formed six-key JSON object.  DatabaseTestCase's
 * begin_transaction/rollback isolation is sufficient — no autocommit switch
 * is needed because no fixtures are committed for child visibility.
 */
#[Group('database')]
final class SimRecapContextCliTest extends DatabaseTestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();

        $path = realpath(__DIR__ . '/../../../scripts/simRecapContext.php');
        self::assertNotFalse($path, 'simRecapContext.php must exist at the expected path');
        $this->scriptPath = $path;
    }

    // ── Tests ──────────────────────────────────────────────────────────────────

    /**
     * An unknown sim exits 0 and emits a well-formed JSON object containing
     * all six expected keys.  This exercises the fault-tolerance path of
     * buildContext() (unknown sim → empty context, no throw).
     */
    public function testUnknownSimExitsZeroWithWellFormedSixKeyJson(): void
    {
        $result = $this->runScript(['--sim=999999']);

        self::assertSame(0, $result['exit_code'],
            'exit 0 expected for unknown sim (fault-tolerance — never throws)');

        $json = json_decode($result['stdout'], true);
        self::assertIsArray($json,
            'stdout must be valid JSON; got: ' . $result['stdout']);

        foreach (['sim', 'start_date', 'end_date', 'roster', 'active_injuries', 'sim_trades'] as $key) {
            self::assertArrayHasKey($key, $json,
                "JSON must contain key: {$key}");
        }

        self::assertSame(999999, $json['sim'],
            'sim key must echo the requested sim number');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    /**
     * Launch simRecapContext.php as a child process via a bootstrap tmpfile.
     *
     * The bootstrap prepends define('PHPUNIT_RUNNING', true) before requiring
     * the script, mirroring the pattern established in StoreSimRecapCliTest.
     * Using a tmpfile keeps $argv indexing predictable (no json_encode escaping
     * that php -r introduces).
     *
     * @param list<string> $argv Arguments forwarded to the script (e.g. ['--sim=999999'])
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    private function runScript(array $argv): array
    {
        $bootstrapFile = tempnam(sys_get_temp_dir(), 'sim-recap-ctx-bootstrap-');
        if ($bootstrapFile === false) {
            self::fail('tempnam must succeed');
        }

        $bootstrapContent = '<?php' . "\n"
            . "define('PHPUNIT_RUNNING', true);\n"
            . 'require ' . var_export($this->scriptPath, true) . ";\n";

        try {
            if (file_put_contents($bootstrapFile, $bootstrapContent) === false) {
                self::fail('Failed to write bootstrap file');
            }

            $envVars = getenv();
            $env = is_array($envVars) ? $envVars : [];

            $command = array_merge([PHP_BINARY, $bootstrapFile], $argv);

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptors, $pipes, null, $env);
            if ($process === false) {
                self::fail('proc_open failed to launch the child process');
            }

            fclose($pipes[0]);

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            return [
                'exit_code' => $exitCode,
                'stdout'    => is_string($stdout) ? $stdout : '',
                'stderr'    => is_string($stderr) ? $stderr : '',
            ];
        } finally {
            @unlink($bootstrapFile);
        }
    }
}
