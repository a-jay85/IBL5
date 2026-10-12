<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Auth\Contracts\AuthServiceInterface;
use Bootstrap\Container;
use Bootstrap\RequestEventLoggingBootstrap;
use Logging\LoggerFactory;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

final class RequestEventLoggingBootstrapTest extends TestCase
{
    private RequestEventLoggingBootstrap $step;
    private Container $container;
    private TestHandler $logHandler;

    protected function setUp(): void
    {
        $this->step = new RequestEventLoggingBootstrap();
        $this->container = new Container();
        // Capture every channel (including 'perf') so no test writes a real log.
        $this->logHandler = new TestHandler();
        LoggerFactory::forTesting($this->logHandler);
    }

    protected function tearDown(): void
    {
        // Restore superglobals and the logger singleton modified by tests.
        unset($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'], $_GET['name']);
        unset($GLOBALS['authService'], $GLOBALS['mysqli_db']);
        LoggerFactory::reset();
    }

    public function testNoOpUnderCliSapi(): void
    {
        // Default constructor reads the real SAPI ('cli' under PHPUnit).
        // REQUEST_URI IS set, so only the isCli arm of the guard can stop boot().
        $_SERVER['REQUEST_URI'] = '/ibl5/index.php';
        $auth = self::createMock(AuthServiceInterface::class);
        $auth->expects(self::never())->method('isAuthenticated');
        $GLOBALS['authService'] = $auth;
        $mockDb = new MockDatabase();
        $GLOBALS['mysqli_db'] = $mockDb;

        $this->step->boot($this->container);

        self::assertSame([], $mockDb->getPreparedQueries(), 'CLI guard must prevent any DB access');
        self::assertSame([], $this->logHandler->getRecords(), 'CLI guard must return before the try block');
    }

    public function testNoOpWhenRequestUriUnset(): void
    {
        // Under CLI, REQUEST_URI is not set — guard fires before any DB access.
        unset($_SERVER['REQUEST_URI']);
        $mockDb = new MockDatabase();
        $GLOBALS['mysqli_db'] = $mockDb;

        $this->step->boot($this->container);

        // REQUEST_URI guard must fire before any DB access
        self::assertSame([], $mockDb->getPreparedQueries(), 'Missing REQUEST_URI must prevent any DB access');
    }

    public function testNoOpInWebModeWhenRequestUriUnset(): void
    {
        // isCli=false isolates the REQUEST_URI arm of the guard.
        unset($_SERVER['REQUEST_URI']);
        $auth = self::createMock(AuthServiceInterface::class);
        $auth->expects(self::never())->method('isAuthenticated');
        $GLOBALS['authService'] = $auth;
        $mockDb = new MockDatabase();
        $GLOBALS['mysqli_db'] = $mockDb;

        (new RequestEventLoggingBootstrap(isCli: false))->boot($this->container);

        self::assertSame([], $mockDb->getPreparedQueries(), 'Missing REQUEST_URI must prevent any DB access in web mode');
        self::assertSame([], $this->logHandler->getRecords(), 'Guard must return before the try block');
    }

    #[DataProvider('dependencyThrowableProvider')]
    public function testSwallowsThrowableFromDependencyAndLogsWarning(\Throwable $thrown): void
    {
        $_SERVER['REQUEST_URI'] = '/ibl5/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $auth = self::createMock(AuthServiceInterface::class);
        $auth->expects(self::once())->method('isAuthenticated')->willThrowException($thrown);
        $GLOBALS['authService'] = $auth;
        $mockDb = new MockDatabase();
        $GLOBALS['mysqli_db'] = $mockDb;
        $obLevel = ob_get_level();

        // Must not throw: the catch (\Throwable) swallows it.
        (new RequestEventLoggingBootstrap(isCli: false))->boot($this->container);

        $records = $this->logHandler->getRecords();
        self::assertCount(1, $records);
        self::assertSame('Request event logging failed', $records[0]->message);
        self::assertSame(Level::Warning, $records[0]->level);
        self::assertSame($thrown->getMessage(), $records[0]->context['exception'] ?? null);
        self::assertSame([], $mockDb->getPreparedQueries(), 'Auth failure must abort before the INSERT');
        self::assertSame($obLevel, ob_get_level(), 'boot() must not alter output buffer state');
    }

    /**
     * @return array<string, array{\Throwable}>
     */
    public static function dependencyThrowableProvider(): array
    {
        return [
            'exception' => [new \RuntimeException('auth backend down')],
            'engine error' => [new \TypeError('auth returned wrong type')],
        ];
    }
}
