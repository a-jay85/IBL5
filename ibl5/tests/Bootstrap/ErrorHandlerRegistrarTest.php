<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Bootstrap\ErrorHandlerRegistrar;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ErrorHandlerRegistrarTest extends TestCase
{
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
                        && is_string($context['trace']);
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
}
