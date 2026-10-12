<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\SimRecap;

use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * Characterization pin for `scripts/simRecapQueue.php mention-map`.
 *
 * Launches the script as a real child process and asserts on its raw stdout.
 * The snowflake comes from committed seed data (db-seed.sql line 44: teamid 1
 * `Metros`), because the child process cannot see this test's uncommitted rows.
 * DatabaseTestCase's begin/rollback isolation is enough; no fixtures are inserted.
 */
#[Group('database')]
final class SimRecapQueueMentionMapCliTest extends DatabaseTestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();

        $path = realpath(__DIR__ . '/../../../scripts/simRecapQueue.php');
        self::assertNotFalse($path, 'simRecapQueue.php must exist at the expected path');
        $this->scriptPath = $path;
    }

    // ── Tests ──────────────────────────────────────────────────────────────────

    /**
     * The snowflake leaves as a quoted JSON string, never a bare number.
     * bin/sim-recap-tick reads it with jq, which corrupts bare numbers under jq <= 1.6.
     * Grounded in db-seed.sql line 44 (teamid 1 `Metros`, discord_id 100000000000000001).
     */
    public function testMentionMapEmitsSnowflakeAsQuotedJsonString(): void
    {
        $result = $this->runScript(['mention-map']);

        self::assertSame(0, $result['exit_code'], 'stderr: ' . $result['stderr']);
        self::assertStringContainsString(
            '"Metros":"100000000000000001"',
            $result['stdout'],
            'snowflake must be a quoted JSON string (seed: db-seed.sql line 44)'
        );
        self::assertStringNotContainsString(
            '"Metros":100000000000000001',
            $result['stdout'],
            'bare-number form would corrupt under jq <= 1.6'
        );
    }

    /**
     * Envelope keys are exactly ok/cmd/teams, and teams with a NULL discord_id are omitted.
     */
    public function testMentionMapEnvelopeShapeAndNullIdTeamsOmitted(): void
    {
        $result = $this->runScript(['mention-map']);

        $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($json);

        self::assertSame(true, $json['ok']);
        self::assertSame('mention-map', $json['cmd']);
        self::assertIsArray($json['teams']);
        self::assertSame(['ok', 'cmd', 'teams'], array_keys($json));

        self::assertSame('100000000000000001', $json['teams']['Metros'] ?? null);

        // Seed teams 2 and 3 carry no discord_id, so they must be absent.
        self::assertArrayNotHasKey('Stars', $json['teams']);
        self::assertArrayNotHasKey('Cougars', $json['teams']);

        foreach ($json['teams'] as $name => $id) {
            self::assertIsString($id, "snowflake for {$name} must be a string");
            self::assertMatchesRegularExpression('/^\d+$/', $id);
        }
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
     * @param list<string> $argv Arguments forwarded to the script (e.g. ['mention-map'])
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    private function runScript(array $argv): array
    {
        $bootstrapFile = tempnam(sys_get_temp_dir(), 'sim-recap-mention-bootstrap-');
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
