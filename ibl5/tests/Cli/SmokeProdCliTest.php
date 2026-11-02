<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('cli')]
final class SmokeProdCliTest extends TestCase
{
    /** Path substring smoke-prod's Team check requests. Case-sensitive: `teamID=1` does not match. */
    private const TEAM_PAGE_PATH = 'name=Team&op=team&teamid=1';
    /** Minimal real-team body: carries the banner marker only TeamView::renderTeamBanner() emits. */
    private const TEAM_PAGE_BODY = 'IBL team <div class="team-banner-logo"><h1><img src="./images/logo/1.jpg" alt="Team"></h1></div>';
    /** Free Agents shape: contains "team" (the old expect string) but no banner marker. */
    private const FREE_AGENTS_BODY = 'IBL team <h1 class="ibl-title">Free Agents</h1><div class="team-logo-fallback"></div>';
    /** Body the fake curl writes to the -o file: carries every expect string the IBL5 checks grep for. */
    private const SHIM_BODY = 'IBL Standings team player Sign In team-banner-logo';

    private string $scriptPath;
    private string $fixtureDir;

    /** @var array<string, resource|null> */
    private array $servers = [];

    /** @var array<string, int> */
    private array $ports = [];

    protected function setUp(): void
    {
        $resolved = realpath(__DIR__ . '/../../../bin/smoke-prod');
        self::assertNotFalse($resolved, 'bin/smoke-prod must exist');
        $this->scriptPath = $resolved;

        $this->fixtureDir = sys_get_temp_dir() . '/smoke-prod-test-' . bin2hex(random_bytes(4));
        mkdir($this->fixtureDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->servers as $proc) {
            if (is_resource($proc)) {
                $status = proc_get_status($proc);
                if ($status['running']) {
                    posix_kill($status['pid'], SIGTERM);
                    usleep(100_000);
                    // kill the child php process tree
                    exec('kill ' . $status['pid'] . ' 2>/dev/null');
                }
                proc_close($proc);
            }
        }

        if (is_dir($this->fixtureDir)) {
            $this->recursiveRm($this->fixtureDir);
        }
    }

    // =========================================================================
    // Characterization tests (pre-impl behavior, now exercised via --scope=all)
    // =========================================================================

    public function testAllEndpointsHealthyExitsZero(): void
    {
        $this->startServer('ibl5', healthy: true);

        $result = $this->runSmoke(scope: 'all');

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('OK: Homepage', $result['output']);
        self::assertStringNotContainsString('IBL6 checks', $result['output']);
    }

    public function testIbl5EndpointReturns500ExitsOne(): void
    {
        $this->startServer('ibl5', healthy: false);

        $result = $this->runSmoke(scope: 'all');

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAIL: Homepage', $result['output']);
    }

    public function testTeamPageServingFreeAgentsBodyExitsOne(): void
    {
        $this->startServer('ibl5', healthy: true, pathBodyOverrides: [
            self::TEAM_PAGE_PATH => self::FREE_AGENTS_BODY,
        ]);

        $result = $this->runSmoke(scope: 'all');

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('FAIL: Team page', $result['output']);
        self::assertStringContainsString('missing expected content: team-banner-logo', $result['output']);
        self::assertStringContainsString('OK: Homepage', $result['output']);
    }

    // =========================================================================
    // Post-impl: --scope=ibl5
    // =========================================================================

    public function testScopeIbl5IgnoresIbl6Failures(): void
    {
        $this->startServer('ibl5', healthy: true);

        $result = $this->runSmoke(scope: 'ibl5');

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('IBL5 checks', $result['output']);
        self::assertStringNotContainsString('IBL6 checks', $result['output']);
    }

    public function testScopeIbl5FailsWhenIbl5Broken(): void
    {
        $this->startServer('ibl5', healthy: false);

        $result = $this->runSmoke(scope: 'ibl5');

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAIL: Homepage', $result['output']);
    }

    // =========================================================================
    // Post-impl: --scope=all (default) preserves prior behavior
    // =========================================================================

    public function testScopeAllFailsWhenIbl5Broken(): void
    {
        $this->startServer('ibl5', healthy: false);

        $result = $this->runSmoke(scope: 'all');

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAIL: Homepage', $result['output']);
        self::assertStringNotContainsString('IBL6 checks', $result['output']);
    }

    // =========================================================================
    // Post-impl: --help
    // =========================================================================

    public function testHelpExitsZeroWithScopeInfo(): void
    {
        $result = $this->runRaw('--help');

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('--scope', $result['output']);
    }

    // =========================================================================
    // Post-impl: unknown --scope
    // =========================================================================

    public function testUnknownScopeExitsTwo(): void
    {
        $result = $this->runRaw('--scope=garbage');

        self::assertSame(2, $result['exit']);
        self::assertStringContainsString('unknown scope', $result['output']);
    }

    public function testScopeIbl6NowRejected(): void
    {
        $result = $this->runSmoke(scope: 'ibl6');

        self::assertSame(2, $result['exit']);
        self::assertStringContainsString('unknown scope: ibl6', $result['output']);
        self::assertStringContainsString('expected ibl5 or all', $result['output']);
    }

    // =========================================================================
    // Post-impl: SMOKE_REHEARSAL_MODE
    // =========================================================================

    public function testRehearsalModeBroadensApiAcceptList(): void
    {
        $this->startServer('ibl5', healthy: true, pathStatusOverrides: [
            'api/v1/season' => 404,
        ]);

        $result = $this->runSmoke(scope: 'ibl5', rehearsalMode: true);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('OK: API routing', $result['output']);
    }

    public function testRehearsalModeSkipsCssAsset(): void
    {
        $this->startServer('ibl5', healthy: true, pathStatusOverrides: [
            'style.css' => 404,
        ]);

        $result = $this->runSmoke(scope: 'ibl5', rehearsalMode: true);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('SKIP: CSS asset (rehearsal mode)', $result['output']);
        self::assertStringNotContainsString('FAIL: CSS asset', $result['output']);
    }

    public function testRehearsalModeDoesNotAffectOtherChecks(): void
    {
        $this->startServer('ibl5', healthy: false);

        $result = $this->runSmoke(scope: 'ibl5', rehearsalMode: true);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAIL: Homepage', $result['output']);
    }

    public function testProductionModeApi404StillFails(): void
    {
        $this->startServer('ibl5', healthy: true, pathStatusOverrides: [
            'api/v1/season' => 404,
        ]);

        $result = $this->runSmoke(scope: 'ibl5', rehearsalMode: false);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAIL: API routing', $result['output']);
    }

    public function testProductionModeCss404StillFails(): void
    {
        $this->startServer('ibl5', healthy: true, pathStatusOverrides: [
            'style.css' => 404,
        ]);

        $result = $this->runSmoke(scope: 'ibl5', rehearsalMode: false);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAIL: CSS asset', $result['output']);
    }

    public function testHelpDocumentsRehearsalMode(): void
    {
        $result = $this->runRaw('--help');

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('SMOKE_REHEARSAL_MODE', $result['output']);
    }

    public function testHelpDocumentsMaxSeconds(): void
    {
        $result = $this->runRaw('--help');

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString(
            'SMOKE_MAX_SECONDS       Max seconds per check before it fails (default: 8)',
            $result['output'],
        );
    }

    // =========================================================================
    // Characterization: exit 3 and HTTP 000 fallback
    // =========================================================================

    public function testUniformWafStatusOnEveryCheckExitsThree(): void
    {
        $this->startServer('ibl5', healthy: true, pathStatusOverrides: ['/ibl5/' => 403]);

        $result = $this->runSmoke(scope: 'ibl5');

        self::assertSame(3, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('INCONCLUSIVE: uniform HTTP 403', $result['output']);
        self::assertStringNotContainsString('OK:', $result['output']);
    }

    public function testCurlFailureWithNoOutputReportsHttp000(): void
    {
        $result = $this->runSmoke(scope: 'ibl5', extraEnv: $this->curlShimEnv('', 7));

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('FAIL: Homepage — HTTP 000 (expected 200)', $result['output']);
    }

    public function testMalformedStatusFieldFallsBackToHttp000(): void
    {
        // Mutation guard: loosening the status regex ^[0-9]{3}$ to ^[0-9a-z]{3}$ lets `20x` through and turns this red.
        $result = $this->runSmoke(scope: 'ibl5', extraEnv: $this->curlShimEnv('20x 0.10', 0));

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('FAIL: Homepage — HTTP 000', $result['output']);
    }

    // =========================================================================
    // Post-impl: latency limit and curl timeout
    // =========================================================================

    public function testFastChecksPrintElapsedOnOkLines(): void
    {
        $this->startServer('ibl5', healthy: true);

        $result = $this->runSmoke(scope: 'ibl5');

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertMatchesRegularExpression('/OK: Homepage \(\d+\.\d{2}s\)/', $result['output']);
    }

    public function testSlowOnceThenFastRetryPasses(): void
    {
        $this->startServer('ibl5', healthy: true, pathDelays: ['/ibl5/index.php' => [1.0, 0.0]]);

        $result = $this->runSmoke(scope: 'ibl5', extraEnv: ['SMOKE_MAX_SECONDS' => '0.5']);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('RETRY: Homepage — slow: ', $result['output']);
        self::assertStringContainsString('(limit 0.5s), waiting 0s...', $result['output']);
        self::assertStringContainsString('OK: Homepage (', $result['output']);
        self::assertStringNotContainsString('FAIL:', $result['output']);
    }

    public function testSlowTwiceFailsNamingPage(): void
    {
        $this->startServer('ibl5', healthy: true, pathDelays: ['/ibl5/index.php' => [1.0]]);

        $result = $this->runSmoke(scope: 'ibl5', extraEnv: ['SMOKE_MAX_SECONDS' => '0.5']);

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('FAIL: Homepage — slow: ', $result['output']);
        self::assertStringContainsString('(limit 0.5s)', $result['output']);
        self::assertStringContainsString('OK: Standings (', $result['output']);
    }

    public function testSlowCheckAmongWafStatusesExitsOneNotThree(): void
    {
        $this->startServer(
            'ibl5',
            healthy: true,
            pathStatusOverrides: ['modules.php' => 403, 'api/v1' => 403, 'style.css' => 403],
            pathDelays: ['/ibl5/index.php' => [1.0]],
        );

        $result = $this->runSmoke(scope: 'ibl5', extraEnv: ['SMOKE_MAX_SECONDS' => '0.5']);

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('FAIL: Homepage — slow: ', $result['output']);
        self::assertStringNotContainsString('INCONCLUSIVE', $result['output']);
    }

    public function testCurlTimeoutWithCapturedStatusFails(): void
    {
        $result = $this->runSmoke(scope: 'ibl5', extraEnv: $this->curlShimEnv('200 0.10', 28));

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('RETRY: Homepage — curl timeout after 30s', $result['output']);
        self::assertStringContainsString('FAIL: Homepage — curl timeout after 30s', $result['output']);
        self::assertStringNotContainsString('OK: Homepage', $result['output']);
        self::assertStringNotContainsString('INCONCLUSIVE', $result['output']);
    }

    public function testDefaultLimitIsEightSecondsWhenUnset(): void
    {
        $result = $this->runSmoke(scope: 'ibl5', extraEnv: $this->curlShimEnv('200 8.50', 0));

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('FAIL: Homepage — slow: 8.50s (limit 8s)', $result['output']);
    }

    public function testTimeExactlyAtLimitPasses(): void
    {
        $result = $this->runSmoke(scope: 'ibl5', extraEnv: $this->curlShimEnv('200 8.000000', 0));

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('OK: Homepage (8.00s)', $result['output']);
    }

    public function testEmptyMaxSecondsFallsBackToDefault(): void
    {
        // Mutation guard: ${SMOKE_MAX_SECONDS:-8} becoming ${SMOKE_MAX_SECONDS-8} makes the empty value exit 2 and turns this red.
        $result = $this->runSmoke(
            scope: 'ibl5',
            extraEnv: ['SMOKE_MAX_SECONDS' => ''] + $this->curlShimEnv('200 7.90', 0),
        );

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('OK: Homepage (7.90s)', $result['output']);
    }

    #[DataProvider('invalidMaxSecondsProvider')]
    public function testInvalidMaxSecondsExitsTwo(string $value): void
    {
        $result = $this->runSmoke(
            scope: 'ibl5',
            extraEnv: ['SMOKE_MAX_SECONDS' => $value] + $this->curlShimEnv('200 0.10', 0),
        );

        self::assertSame(2, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('SMOKE_MAX_SECONDS must be a positive number', $result['output']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidMaxSecondsProvider(): array
    {
        return [
            'non-numeric' => ['abc'],
            'unit suffix' => ['8s'],
            'negative' => ['-1'],
            'zero' => ['0'],
        ];
    }

    public function testCommaDecimalTimeIsNormalized(): void
    {
        $result = $this->runSmoke(scope: 'ibl5', extraEnv: $this->curlShimEnv('200 9,50', 0));

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('slow: 9.50s (limit 8s)', $result['output']);
    }

    public function testRehearsalModeAppliesLatencyLimit(): void
    {
        $result = $this->runSmoke(
            scope: 'ibl5',
            rehearsalMode: true,
            extraEnv: $this->curlShimEnv('200 9.00', 0),
        );

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('FAIL: API routing — slow: 9.00s', $result['output']);
        self::assertStringContainsString('SKIP: CSS asset (rehearsal mode)', $result['output']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * @param array<string, int> $pathStatusOverrides path-substring => HTTP status
     * @param array<string, string> $pathBodyOverrides path-substring => HTTP 200 body; healthy servers only, checked after status overrides
     * @param array<string, list<float>> $pathDelays path-substring => per-hit delay schedule in seconds; hit N sleeps schedule[min(N, last)]
     */
    private function startServer(
        string $name,
        bool $healthy,
        array $pathStatusOverrides = [],
        array $pathBodyOverrides = [],
        array $pathDelays = [],
    ): void {
        $port = $this->findFreePort();
        $this->ports[$name] = $port;

        $delays = '';
        foreach ($pathDelays as $pathMatch => $schedule) {
            $counterFile = $this->fixtureDir . '/hits-' . md5($pathMatch) . '.txt';
            $delays .= sprintf(
                'if (strpos($_SERVER["REQUEST_URI"], %1$s) !== false) { $n = (int) @file_get_contents(%2$s); file_put_contents(%2$s, (string) ($n + 1)); $s = %3$s; usleep((int) ($s[min($n, count($s) - 1)] * 1000000)); } ',
                var_export($pathMatch, true),
                var_export($counterFile, true),
                var_export($schedule, true),
            );
        }

        $cases = '';
        foreach ($pathStatusOverrides as $pathMatch => $status) {
            $cases .= sprintf(
                'if (strpos($_SERVER["REQUEST_URI"], %s) !== false) { http_response_code(%d); echo ""; exit; } ',
                var_export($pathMatch, true),
                $status,
            );
        }
        if ($healthy) {
            // A healthy server serves a real team page on the Team path unless a test overrides it.
            $bodies = $pathBodyOverrides + [self::TEAM_PAGE_PATH => self::TEAM_PAGE_BODY];
            foreach ($bodies as $pathMatch => $body) {
                $cases .= sprintf(
                    'if (strpos($_SERVER["REQUEST_URI"], %s) !== false) { http_response_code(200); echo %s; exit; } ',
                    var_export($pathMatch, true),
                    var_export($body, true),
                );
            }
        }
        $defaultStatus = $healthy ? 200 : 500;
        $defaultBody = $healthy ? 'IBL Standings team player Sign In' : 'Server Error';
        $routerContent = sprintf(
            '<?php %s http_response_code(%d); echo %s;',
            $delays . $cases,
            $defaultStatus,
            var_export($defaultBody, true),
        );

        $routerFile = $this->fixtureDir . "/router-{$name}.php";
        file_put_contents($routerFile, $routerContent);

        $cmd = sprintf(
            'exec php -S 127.0.0.1:%d -t %s %s',
            $port,
            escapeshellarg($this->fixtureDir),
            escapeshellarg($routerFile),
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $descriptors, $pipes);
        self::assertIsResource($proc, "Failed to start {$name} fixture server");
        $this->servers[$name] = $proc;

        // Wait for server to be accepting connections
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($sock !== false) {
                fclose($sock);
                return;
            }
            usleep(50_000);
        }

        self::fail("Fixture server {$name} did not start on port {$port} within 5s");
    }

    /**
     * Write a fake `curl` that writes SHIM_BODY to its -o file, prints $wOutput as the -w output, and exits $exitCode.
     * @return array<string, string> env overrides that put the shim first on PATH
     */
    private function curlShimEnv(string $wOutput, int $exitCode): array
    {
        $dir = $this->fixtureDir . '/shim';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $script = "#!/usr/bin/env bash\n"
            . "out=''\n"
            . "while [ \$# -gt 0 ]; do case \"\$1\" in -o) out=\"\$2\"; shift 2 ;; *) shift ;; esac; done\n"
            . "if [ -n \"\$out\" ]; then printf '%s' " . escapeshellarg(self::SHIM_BODY) . " > \"\$out\"; fi\n"
            . "printf '%s' " . escapeshellarg($wOutput) . "\n"
            . 'exit ' . $exitCode . "\n";
        file_put_contents($dir . '/curl', $script);
        chmod($dir . '/curl', 0755);

        return ['PATH' => $dir . ':' . (string) getenv('PATH')];
    }

    private function findFreePort(): int
    {
        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        self::assertNotFalse($sock, 'Could not create socket');
        socket_bind($sock, '127.0.0.1', 0);
        socket_getsockname($sock, $addr, $port);
        socket_close($sock);

        return $port;
    }

    /**
     * Run bin/smoke-prod with --scope and env overrides.
     * @param array<string, string> $extraEnv extra env assignments, values shell-escaped (SMOKE_MAX_SECONDS, PATH)
     * @return array{output: string, exit: int}
     */
    private function runSmoke(
        string $scope,
        bool $rehearsalMode = false,
        array $extraEnv = [],
    ): array {
        $baseUrl = isset($this->ports['ibl5'])
            ? 'http://127.0.0.1:' . $this->ports['ibl5']
            : 'http://127.0.0.1:1';

        $env = 'SMOKE_INTERCHECK_DELAY=0 SMOKE_RETRY_DELAY=0';

        if ($rehearsalMode) {
            $env .= ' SMOKE_REHEARSAL_MODE=1';
        }

        foreach ($extraEnv as $key => $value) {
            $env .= ' ' . $key . '=' . escapeshellarg($value);
        }

        $cmd = sprintf(
            '%s %s --scope=%s %s 2>&1',
            $env,
            escapeshellcmd($this->scriptPath),
            escapeshellarg($scope),
            escapeshellarg($baseUrl),
        );

        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);

        return ['output' => implode("\n", $output), 'exit' => $exit];
    }

    /**
     * Run bin/smoke-prod with raw arguments (for --help, unknown flags).
     * @return array{output: string, exit: int}
     */
    private function runRaw(string $args): array
    {
        $cmd = escapeshellcmd($this->scriptPath) . ' ' . $args . ' 2>&1';
        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);

        return ['output' => implode("\n", $output), 'exit' => $exit];
    }

    private function recursiveRm(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->recursiveRm($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
