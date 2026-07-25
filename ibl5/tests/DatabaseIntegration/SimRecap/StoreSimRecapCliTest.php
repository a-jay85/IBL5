<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\SimRecap;

use PHPUnit\Framework\Attributes\Group;
use SimRecap\SimSummaryRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * Database integration test for scripts/storeSimRecap.php.
 *
 * Launches the script as a real child process (isolated connection) so the
 * full path through argv-parse → stdin-parse → markDone → Discord-short-circuit
 * → stdout-JSON is exercised against a live database.
 *
 * ISOLATION: Each test runs in autocommit mode. DatabaseTestCase::setUp() starts
 * a transaction which is immediately committed in this class's setUp() so the
 * connection switches to autocommit. This is required because the child is a
 * separate connection and cannot see the parent's uncommitted state.
 * tearDown() unconditionally removes sim 9001 rows.
 *
 * SECURITY: Every child process invocation prepends
 *   define('PHPUNIT_RUNNING', true);
 * before requiring storeSimRecap.php.  Discord::postToChannel() short-circuits on
 * defined('PHPUNIT_RUNNING'), so no live Discord POST can fire on any machine.
 * 'iblhoops.net' is never used as a host — 'example.test' is the only test host.
 */
#[Group('database')]
final class StoreSimRecapCliTest extends DatabaseTestCase
{
    private const SIM = 9001;

    /**
     * A minimal valid payload that passes SimRecapPayload::fromJson().
     * themes=["comeback"] so themes_used stores '["comeback"]' after markDone.
     */
    private const VALID_PAYLOAD = '{"intro_text":"Intro text.","outro_text":"Outro text.",'
        . '"recap_text":"This is the recap text.","themes":["comeback"],'
        . '"games":[{"season_year":2025,"game_date":"2025-01-01","visitor_teamid":1,'
        . '"home_teamid":2,"game_of_that_day":1,"box_id":null,"sort_order":0,'
        . '"recap_text":"Game recap text."}]}';

    private SimSummaryRepository $repo;
    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Immediately commit the empty transaction that DatabaseTestCase::setUp()
        // opened — this switches the connection to autocommit mode so every
        // subsequent write is visible to the child process on its own connection.
        $this->db->commit();

        $this->repo = new SimSummaryRepository($this->db);

        $path = realpath(__DIR__ . '/../../../scripts/storeSimRecap.php');
        self::assertNotFalse($path, 'storeSimRecap.php must exist at the expected path');
        $this->scriptPath = $path;
    }

    protected function tearDown(): void
    {
        // Unconditional cleanup — removes any committed rows even when the test
        // failed mid-way. parent::tearDown() calls rollback() (a no-op in
        // autocommit mode) then close().
        try {
            if (isset($this->db)) {
                $this->db->query('DELETE FROM `ibl_sim_game_recaps` WHERE `sim` = ' . self::SIM);
                $this->db->query('DELETE FROM `ibl_sim_summaries` WHERE `sim` = ' . self::SIM);
                $this->db->query('DELETE FROM `ibl_sim_dates` WHERE `sim` = ' . self::SIM);
            }
        } catch (\Throwable) {
            // Best-effort — connection may be in an unrecoverable state.
        }
        parent::tearDown();
    }

    // ── Happy path ─────────────────────────────────────────────────────────────

    /**
     * Happy path: stdout JSON shape, exit 0, row persisted as done.
     *
     * Verification matrix rows 38–39.
     */
    public function testHappyPathStdoutAndDbState(): void
    {
        $this->repo->queuePendingIfAbsent(self::SIM);
        // Autocommit — each query commits immediately; no explicit commit needed.

        $result = $this->runScript(['--sim=' . self::SIM], self::VALID_PAYLOAD);

        self::assertSame(0, $result['exit_code'], 'exit 0 expected for happy path');

        // Stdout must be a well-formed JSON object.
        $json = json_decode($result['stdout'], true);
        self::assertIsArray($json, 'stdout must be a valid JSON object');
        self::assertTrue($json['ok'] ?? false, 'ok must be true');
        self::assertSame(self::SIM, $json['sim'] ?? null, 'sim must echo the requested sim');
        self::assertSame(1, $json['games'] ?? null, 'games count must match the payload (1 game)');

        // URL must contain the sim number and the viewer path (decision 23 pin).
        $url = is_string($json['url'] ?? null) ? $json['url'] : '';
        self::assertStringContainsString((string) self::SIM, $url, 'url must contain the sim number');
        self::assertStringContainsString('simSummaries.php', $url, 'url must point at simSummaries.php');

        // Read-back: row must be done with the expected prose and themes.
        $row = $this->repo->find(self::SIM);
        self::assertNotNull($row, 'find() must return a row after the script runs');
        self::assertSame('done', $row['status'], 'status must be done after store');
        self::assertSame('This is the recap text.', $row['recap_text'], 'recap_text must round-trip');
        self::assertSame('["comeback"]', $row['themes_used'], 'themes_used must store the JSON array');
    }

    // ── Negative cases ─────────────────────────────────────────────────────────

    /**
     * Missing --sim: non-zero exit, exact STDERR, nothing written.
     *
     * Verification matrix row 40 (missing-sim branch).
     */
    public function testMissingSimArgExitsWithExpectedStderr(): void
    {
        $result = $this->runScript([], self::VALID_PAYLOAD);

        self::assertNotSame(0, $result['exit_code'], 'exit must be non-zero for missing --sim');
        self::assertStringContainsString(
            'storeSimRecap: --sim=N is required',
            $result['stderr'],
            'exact STDERR message expected for missing --sim'
        );
        self::assertNull($this->repo->find(self::SIM), 'no row must be written when --sim is missing');
    }

    /**
     * Non-numeric --sim: non-zero exit, distinct STDERR, nothing written.
     *
     * Verification matrix row 40 (non-numeric-sim branch).
     */
    public function testNonNumericSimArgExitsWithExpectedStderr(): void
    {
        $result = $this->runScript(['--sim=abc'], self::VALID_PAYLOAD);

        self::assertNotSame(0, $result['exit_code'], 'exit must be non-zero for non-numeric --sim');
        self::assertStringContainsString(
            'storeSimRecap: --sim must be a positive integer, got: abc',
            $result['stderr'],
            'exact STDERR message expected for non-numeric --sim'
        );
        self::assertNull($this->repo->find(self::SIM), 'no row must be written for non-numeric --sim');
    }

    /**
     * Malformed JSON on stdin: exit 1, nothing written.
     *
     * Verification matrix row 41 (malformed-JSON branch).
     */
    public function testMalformedJsonExitsOneWithNothingWritten(): void
    {
        $result = $this->runScript(['--sim=' . self::SIM], 'not valid json at all');

        self::assertSame(1, $result['exit_code'], 'exit 1 expected for malformed JSON');
        self::assertStringContainsString('storeSimRecap:', $result['stderr']);
        self::assertNull($this->repo->find(self::SIM), 'no row must be written for malformed JSON');
    }

    /**
     * Missing required recap_text field: exit 1, nothing written.
     *
     * Verification matrix row 41 (missing-field branch).
     */
    public function testMissingRecapTextExitsOne(): void
    {
        $payloadWithoutRecapText = '{"intro_text":"Intro.","outro_text":"Outro.",'
            . '"games":[{"season_year":2025,"game_date":"2025-01-01","visitor_teamid":1,'
            . '"home_teamid":2,"game_of_that_day":1,"box_id":null,"sort_order":0,'
            . '"recap_text":"Game."}]}';

        $result = $this->runScript(['--sim=' . self::SIM], $payloadWithoutRecapText);

        self::assertSame(1, $result['exit_code'], 'exit 1 expected for missing recap_text');
        self::assertNull($this->repo->find(self::SIM), 'no row must be written for missing recap_text');
    }

    /**
     * Malformed themes field: SimRecapPayload degrades to null, script still
     * exits 0, recap is stored with themes_used = null.
     *
     * Verification matrix row 42.
     */
    public function testMalformedThemesDegradesToNullThemesUsed(): void
    {
        // themes is a bare string, not a list — parseThemes degrades to null.
        $payloadBadThemes = '{"intro_text":"Intro.","outro_text":"Outro.",'
            . '"recap_text":"Recap text.","themes":"not-a-list",'
            . '"games":[{"season_year":2025,"game_date":"2025-01-01","visitor_teamid":1,'
            . '"home_teamid":2,"game_of_that_day":1,"box_id":null,"sort_order":0,'
            . '"recap_text":"Game."}]}';

        $result = $this->runScript(['--sim=' . self::SIM], $payloadBadThemes);

        self::assertSame(0, $result['exit_code'], 'exit 0 expected even when themes degrade');

        $row = $this->repo->find(self::SIM);
        self::assertNotNull($row, 'row must be written even when themes degrade');
        self::assertSame('done', $row['status']);
        self::assertNull($row['themes_used'], 'themes_used must be null when themes degrade');
    }

    /**
     * Second run against already-done sim: markDone is an UPSERT, so the script
     * exits 0 and the row remains valid (not corrupted).
     *
     * Verification matrix row 43.
     */
    public function testSecondRunAgainstDoneSimFollowsMarkDoneSemantics(): void
    {
        $this->repo->queuePendingIfAbsent(self::SIM);

        $result1 = $this->runScript(['--sim=' . self::SIM], self::VALID_PAYLOAD);
        self::assertSame(0, $result1['exit_code'], 'first run must exit 0');

        $rowAfterFirst = $this->repo->find(self::SIM);
        self::assertNotNull($rowAfterFirst);
        self::assertSame('done', $rowAfterFirst['status']);

        // Second run: markDone UPSERT overwrites in place — must not error or corrupt.
        $result2 = $this->runScript(['--sim=' . self::SIM], self::VALID_PAYLOAD);
        self::assertSame(0, $result2['exit_code'], 'second run must exit 0 (ODKU is idempotent)');

        $rowAfterSecond = $this->repo->find(self::SIM);
        self::assertNotNull($rowAfterSecond, 'row must still exist after second run');
        self::assertSame('done', $rowAfterSecond['status'], 'row must still be done after second run');
    }

    // ── Host-branch cases ───────────────────────────────────────────────────────

    /**
     * No IBL5_CANONICAL_HOST in child env: url must be a relative path,
     * not an absolute URL.
     *
     * Verification matrix row 44.
     */
    public function testEmptyHostProducesRelativeUrl(): void
    {
        $this->repo->queuePendingIfAbsent(self::SIM);

        // runScript() removes IBL5_CANONICAL_HOST from the child env by default.
        $result = $this->runScript(['--sim=' . self::SIM], self::VALID_PAYLOAD);

        self::assertSame(0, $result['exit_code']);

        $json = json_decode($result['stdout'], true);
        self::assertIsArray($json);

        $url = is_string($json['url'] ?? null) ? $json['url'] : '';
        self::assertStringNotContainsString('https://', $url, 'url must be relative when host is empty');
        self::assertStringContainsString('simSummaries.php', $url);
    }

    /**
     * IBL5_CANONICAL_HOST=example.test in child env: url must be the full
     * absolute URL https://example.test/ibl5/simSummaries.php?sim=9001.
     *
     * Verification matrix row 45. 'example.test' is the test host;
     * 'iblhoops.net' must never appear here.
     */
    public function testPopulatedHostProducesAbsoluteUrl(): void
    {
        $this->repo->queuePendingIfAbsent(self::SIM);

        $result = $this->runScript(
            ['--sim=' . self::SIM],
            self::VALID_PAYLOAD,
            ['IBL5_CANONICAL_HOST' => 'example.test']
        );

        self::assertSame(0, $result['exit_code']);

        $json = json_decode($result['stdout'], true);
        self::assertIsArray($json);

        $url = is_string($json['url'] ?? null) ? $json['url'] : '';
        self::assertSame(
            'https://example.test/ibl5/simSummaries.php?sim=' . self::SIM,
            $url,
            'url must be the full absolute URL when IBL5_CANONICAL_HOST is set'
        );
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    /**
     * Launch storeSimRecap.php as a child process.
     *
     * A bootstrap tmpfile defines PHPUNIT_RUNNING before requiring the script
     * so Discord::postToChannel() short-circuits — no live webhook POST can fire
     * on any machine.  Using a tmpfile (rather than php -r) gives predictable
     * $argv indexing: $argv[0] = bootstrap path, $argv[1..] = caller's args,
     * so storeSimRecap.php's array_slice($argv, 1) sees exactly what we pass.
     * (php -r encodes the path via json_encode which escapes forward slashes
     * as \/ — PHP's double-quoted strings keep the backslash, breaking the path.)
     *
     * proc_open() is used with an explicit env array built from the full current
     * environment (so PATH and DB_* vars reach the child), with IBL5_CANONICAL_HOST
     * removed by default and selectively set by callers who test the host branch.
     *
     * @param list<string> $argv   Arguments forwarded to the script (e.g. ['--sim=9001'])
     * @param string       $stdin  JSON payload to pipe on stdin
     * @param array<string, string> $envOverrides  Extra env vars for the child
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    private function runScript(array $argv, string $stdin, array $envOverrides = []): array
    {
        // Write a one-shot bootstrap file that defines PHPUNIT_RUNNING then
        // requires the script.  var_export() produces a single-quoted PHP string
        // literal — no forward-slash escaping, so the path is always correct.
        $bootstrapFile = tempnam(sys_get_temp_dir(), 'sim-recap-bootstrap-');
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

            // Build env: inherit all current env vars, remove IBL5_CANONICAL_HOST
            // by default (safe non-production default). Callers pass it via
            // $envOverrides for the host-branch tests.
            $envVars = getenv();
            $env = is_array($envVars) ? $envVars : [];
            unset($env['IBL5_CANONICAL_HOST']);
            foreach ($envOverrides as $key => $value) {
                $env[$key] = $value;
            }

            // $argv[0] = bootstrap file (set by PHP), $argv[1..] = caller's args.
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

            fwrite($pipes[0], $stdin);
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
