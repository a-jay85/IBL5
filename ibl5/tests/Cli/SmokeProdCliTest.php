<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
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

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * @param array<string, int> $pathStatusOverrides path-substring => HTTP status
     * @param array<string, string> $pathBodyOverrides path-substring => HTTP 200 body; healthy servers only, checked after status overrides
     */
    private function startServer(
        string $name,
        bool $healthy,
        array $pathStatusOverrides = [],
        array $pathBodyOverrides = [],
    ): void {
        $port = $this->findFreePort();
        $this->ports[$name] = $port;

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
            $cases,
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
     * @return array{output: string, exit: int}
     */
    private function runSmoke(
        string $scope,
        bool $rehearsalMode = false,
    ): array {
        $baseUrl = isset($this->ports['ibl5'])
            ? 'http://127.0.0.1:' . $this->ports['ibl5']
            : 'http://127.0.0.1:1';

        $env = 'SMOKE_INTERCHECK_DELAY=0 SMOKE_RETRY_DELAY=0';

        if ($rehearsalMode) {
            $env .= ' SMOKE_REHEARSAL_MODE=1';
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
