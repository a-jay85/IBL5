<?php

declare(strict_types=1);

namespace Bootstrap;

use Psr\Log\LoggerInterface;

/**
 * Routes uncaught throwables and fatal errors to a PSR-3 logger so that
 * production failures reach the `error` channel — which already fans out to the
 * Discord webhook handler (see Logging\LoggerFactory). Without this, uncaught
 * web/API failures were invisible (the only handlers lived in the updater CLI).
 *
 * The logger is injected (never a static LoggerFactory call) so the class is
 * unit-testable with a mock logger. An optional renderer emits a client-facing
 * 500 response; it is kept separate from logging so handler behavior can be
 * asserted without producing output.
 */
final class ErrorHandlerRegistrar
{
    /** Fatal error types that PHP cannot recover from and that warrant an alert. */
    private const FATAL_ERROR_TYPES = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;

    private LoggerInterface $logger;

    /** @var callable(int): void */
    private $renderer;

    /** @var callable(): (array{type: int, message: string, file: string, line: int}|null) */
    private $lastErrorProvider;

    /**
     * @param callable(int $statusCode): void|null $renderer Emits a client-facing
     *        response for the given HTTP status. Defaults to a no-op (logging only).
     * @param callable(): (array{type: int, message: string, file: string, line: int}|null)|null $lastErrorProvider
     *        Source of the last PHP error for shutdown handling. Defaults to
     *        error_get_last(); overridden in tests to exercise handleShutdown()
     *        without a real fatal.
     */
    public function __construct(
        LoggerInterface $logger,
        ?callable $renderer = null,
        ?callable $lastErrorProvider = null
    ) {
        $this->logger = $logger;
        $this->renderer = $renderer ?? static function (int $statusCode): void {
        };
        $this->lastErrorProvider = $lastErrorProvider ?? static fn (): ?array => error_get_last();
    }

    /**
     * Wire PHP's global handlers. Call once, early in boot, after logging is configured.
     */
    public function register(): void
    {
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Log an uncaught throwable at `error` level, then emit the 500 response.
     */
    public function handleException(\Throwable $e): void
    {
        $this->logger->error('Uncaught exception', [
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        ($this->renderer)(500);
    }

    /**
     * On shutdown, if the last error was a fatal, log it at `error` level. Silent
     * for clean shutdowns and non-fatal errors (warnings/notices are out of scope).
     */
    public function handleShutdown(): void
    {
        $error = ($this->lastErrorProvider)();
        if ($error === null || ($error['type'] & self::FATAL_ERROR_TYPES) === 0) {
            return;
        }

        $this->logger->error('Fatal error', [
            'type' => $error['type'],
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line'],
        ]);

        ($this->renderer)(500);
    }
}
