<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('cli')]
final class SmokeProdCliTest extends TestCase
{
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
        foreach ($this->servers as $name => $proc) {
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
        $this->startServer('ibl6', healthy: true);

        $result = $this->runSmoke(scope: 'all');

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('OK: Homepage', $result['output']);
        self::assertStringContainsString('OK: IBL6 app', $result['output']);
    }

    public function testIbl5EndpointReturns500ExitsOne(): void
    {
        $this->startServer('ibl5', healthy: false);
        $this->startServer('ibl6', healthy: true);

        $result = $this->runSmoke(scope: 'all');

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAIL: Homepage', $result['output']);
    }

    public function testIbl6UnreachableExitsOne(): void
    {
        $this->startServer('ibl5', healthy: true);
        // no IBL6 server → connection refused

        $result = $this->runSmoke(scope: 'all', ibl6Url: 'http://127.0.0.1:1/');

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAIL: IBL6 app', $result['output']);
    }

    // =========================================================================
    // Post-impl: --scope=ibl5
    // =========================================================================

    public function testScopeIbl5IgnoresIbl6Failures(): void
    {
        $this->startServer('ibl5', healthy: true);
        // IBL6 deliberately broken (no server)

        $result = $this->runSmoke(scope: 'ibl5');

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('IBL5 checks', $result['output']);
        self::assertStringNotContainsString('IBL6 checks', $result['output']);
    }

    public function testScopeIbl5FailsWhenIbl5Broken(): void
    {
        $this->startServer('ibl5', healthy: false);
        $this->startServer('ibl6', healthy: true);

        $result = $this->runSmoke(scope: 'ibl5');

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAIL: Homepage', $result['output']);
    }

    // =========================================================================
    // Post-impl: --scope=ibl6
    // =========================================================================

    public function testScopeIbl6IgnoresIbl5Failures(): void
    {
        // IBL5 deliberately broken
        $this->startServer('ibl6', healthy: true);

        $result = $this->runSmoke(scope: 'ibl6');

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('IBL6 checks', $result['output']);
        self::assertStringNotContainsString('IBL5 checks', $result['output']);
    }

    public function testScopeIbl6FailsWhenIbl6Broken(): void
    {
        $this->startServer('ibl5', healthy: true);

        $result = $this->runSmoke(scope: 'ibl6', ibl6Url: 'http://127.0.0.1:1/');

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAIL: IBL6 app', $result['output']);
    }

    // =========================================================================
    // Post-impl: --scope=all (default) preserves prior behavior
    // =========================================================================

    public function testScopeAllFailsWhenEitherBroken(): void
    {
        $this->startServer('ibl5', healthy: true);
        // IBL6 broken

        $result = $this->runSmoke(scope: 'all', ibl6Url: 'http://127.0.0.1:1/');

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('FAIL: IBL6 app', $result['output']);
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

    // =========================================================================
    // Post-impl: SMOKE_IBL6_URL env var
    // =========================================================================

    public function testIbl6UrlEnvVarOverridesDefault(): void
    {
        $this->startServer('ibl6', healthy: true);

        $result = $this->runSmoke(
            scope: 'ibl6',
            ibl6Url: 'http://127.0.0.1:' . $this->ports['ibl6'] . '/',
        );

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('OK: IBL6 app', $result['output']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function startServer(string $name, bool $healthy): void
    {
        $port = $this->findFreePort();
        $this->ports[$name] = $port;

        $routerContent = $healthy
            ? '<?php http_response_code(200); echo "IBL Standings team player Sign In";'
            : '<?php http_response_code(500); echo "Server Error";';

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
    private function runSmoke(string $scope, ?string $ibl6Url = null): array
    {
        $baseUrl = isset($this->ports['ibl5'])
            ? 'http://127.0.0.1:' . $this->ports['ibl5']
            : 'http://127.0.0.1:1';

        $env = 'SMOKE_INTERCHECK_DELAY=0 SMOKE_RETRY_DELAY=0';

        if ($ibl6Url !== null) {
            $env .= ' SMOKE_IBL6_URL=' . escapeshellarg($ibl6Url);
        } elseif (isset($this->ports['ibl6'])) {
            $env .= ' SMOKE_IBL6_URL=' . escapeshellarg(
                'http://127.0.0.1:' . $this->ports['ibl6'] . '/',
            );
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
