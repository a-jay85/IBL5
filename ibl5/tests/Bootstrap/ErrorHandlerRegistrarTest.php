<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Bootstrap\ErrorHandlerRegistrar;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ErrorHandlerRegistrarTest extends TestCase
{
    private ?string $originalIgnoreArgs = null;

    protected function tearDown(): void
    {
        if ($this->originalIgnoreArgs !== null) {
            ini_set('zend.exception_ignore_args', $this->originalIgnoreArgs);
            $this->originalIgnoreArgs = null;
        }
        parent::tearDown();
    }

    public function testHandleExceptionLogsThrowableAtErrorLevel(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                self::isString(),
                self::callback(static function (array $context): bool {
                    return $context['exception'] === \RuntimeException::class
                        && $context['message'] === 'boom'
                        && is_string($context['file'])
                        && is_int($context['line'])
                        && str_contains($context['trace'], '{main}');
                })
            );

        $rendered = [];
        $registrar = new ErrorHandlerRegistrar($logger, static function (int $status) use (&$rendered): void {
            $rendered[] = $status;
        });

        $registrar->handleException(new \RuntimeException('boom'));

        $this->assertSame([500], $rendered);
    }

    public function testHandleShutdownLogsForFatalError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                self::isString(),
                self::callback(static function (array $context): bool {
                    return $context['type'] === E_ERROR
                        && $context['message'] === 'Allowed memory exhausted';
                })
            );

        $registrar = $this->registrarWithLastError($logger, [
            'type' => E_ERROR,
            'message' => 'Allowed memory exhausted',
            'file' => '/app/x.php',
            'line' => 42,
        ]);

        $registrar->handleShutdown();
    }

    public function testHandleShutdownSilentForNonFatalError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $registrar = $this->registrarWithLastError($logger, [
            'type' => E_WARNING,
            'message' => 'undefined index',
            'file' => '/app/x.php',
            'line' => 7,
        ]);

        $registrar->handleShutdown();
    }

    public function testHandleShutdownSilentWhenNoError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $registrar = $this->registrarWithLastError($logger, null);

        $registrar->handleShutdown();
    }

    public function testHandleExceptionDoesNotLeakFrameArguments(): void
    {
        try {
            $this->throwFromAuthLikeFrame('gm_user', self::SYNTHETIC_SECRET, 3600);
            self::fail('helper did not throw');
        } catch (\RuntimeException $e) {
            $context = $this->captureExceptionContext($e);
        }

        self::assertIsString($context['trace']);
        self::assertStringNotContainsString(self::SYNTHETIC_SECRET, $context['trace']);
        self::assertStringNotContainsString(self::SYNTHETIC_SECRET, json_encode($context, JSON_THROW_ON_ERROR));
    }

    public function testHandleExceptionTraceContainsFrameIdentity(): void
    {
        try {
            $this->throwFromAuthLikeFrame('gm_user', self::SYNTHETIC_SECRET, 3600);
            self::fail('helper did not throw');
        } catch (\RuntimeException $e) {
            $context = $this->captureExceptionContext($e);
        }

        self::assertSame(\RuntimeException::class, $context['exception']);
        self::assertSame('boom', $context['message']);
        self::assertSame(__FILE__, $context['file']);
        self::assertIsInt($context['line']);
        $trace = $context['trace'];
        foreach (['#0 ', 'throwFromAuthLikeFrame', self::class, 'ErrorHandlerRegistrarTest.php:', '{main}'] as $needle) {
            self::assertStringContainsString($needle, $trace);
        }
    }

    public function testHandleExceptionArgFreeRegardlessOfIni(): void
    {
        $this->originalIgnoreArgs = (string) ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');

        try {
            $this->throwFromAuthLikeFrame('gm_user', self::SYNTHETIC_SECRET, 3600);
            self::fail('helper did not throw');
        } catch (\RuntimeException $e) {
            // PHP 8.4+ truncates getTraceAsString() args to 15 chars; assert via getTrace() args directly
            self::assertSame(self::SYNTHETIC_SECRET, $e->getTrace()[0]['args'][1] ?? null); // guard
            $context = $this->captureExceptionContext($e);
        }

        self::assertStringNotContainsString(self::SYNTHETIC_SECRET, $context['trace']);
    }

    public function testHandleExceptionTraceHandlesInternalAndClasslessFrames(): void
    {
        try {
            array_map(static fn (int $n): int => throw new \RuntimeException('boom'), [1]);
            self::fail('helper did not throw');
        } catch (\RuntimeException $e) {
            $context = $this->captureExceptionContext($e);
        }

        self::assertStringContainsString('[internal function]', $context['trace']);
        self::assertMatchesRegularExpression('/^#0 \S+\(\) at \[internal function\]$/m', $context['trace']);
        self::assertStringContainsString('array_map() at ', $context['trace']);
        self::assertStringEndsWith('{main}', $context['trace']);
    }

    /**
     * Build a registrar whose last-error provider returns a fixed value, so
     * handleShutdown() can be exercised without a real PHP fatal.
     *
     * @param array{type: int, message: string, file: string, line: int}|null $error
     */
    private function registrarWithLastError(LoggerInterface $logger, ?array $error): ErrorHandlerRegistrar
    {
        return new ErrorHandlerRegistrar($logger, null, static fn (): ?array => $error);
    }

    private const SYNTHETIC_SECRET = 'SYNTH_Pa55word';

    /** Mirrors AuthService::loginWithUsername($username, $password, $ttl) frame shape. */
    private function throwFromAuthLikeFrame(string $username, string $password, int $ttl): void
    {
        throw new \RuntimeException('boom');
    }

    /** @return array<string, mixed> the context array handleException() passed to the logger */
    private function captureExceptionContext(\Throwable $e): array
    {
        $captured = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Uncaught exception', self::callback(
                static function (array $context) use (&$captured): bool {
                    $captured = $context;
                    return true;
                }
            ));

        $registrar = new ErrorHandlerRegistrar($logger, static function (int $status): void {});
        $registrar->handleException($e);

        return $captured;
    }
}
