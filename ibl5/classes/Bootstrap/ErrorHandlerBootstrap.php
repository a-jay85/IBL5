<?php

declare(strict_types=1);

namespace Bootstrap;

use Api\Response\JsonResponder;
use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;
use Logging\LoggerFactory;

/**
 * Registers global exception/shutdown handlers that route uncaught failures to the
 * `error` logging channel (which already fans out to Discord). Runs after
 * ConfigBootstrap so the `error` channel is fully configured (file + Discord
 * handlers) before handlers are wired, and before request handling so failures in
 * later steps are captured.
 *
 * The client-facing 500 renderer differs per entry point: a generic HTML page for
 * the web, an enveloped JSON error for the API. Neither leaks the exception detail.
 */
final class ErrorHandlerBootstrap implements BootstrapStepInterface
{
    public const MODE_WEB = 'web';
    public const MODE_API = 'api';

    private string $mode;

    public function __construct(string $mode)
    {
        $this->mode = $mode;
    }

    /**
     * @see BootstrapStepInterface::boot()
     */
    public function boot(ContainerInterface $container): void
    {
        $registrar = new ErrorHandlerRegistrar(
            LoggerFactory::getChannel('error'),
            $this->renderer($container)
        );
        $registrar->register();
    }

    /**
     * @return callable(int): void
     */
    private function renderer(ContainerInterface $container): callable
    {
        if ($this->mode === self::MODE_API) {
            return static function (int $statusCode) use ($container): void {
                if (headers_sent()) {
                    return;
                }
                /** @var JsonResponder $responder */
                $responder = $container->get('api.responder');
                $responder->error($statusCode, 'internal', 'An internal error occurred.');
            };
        }

        return static function (int $statusCode): void {
            if (headers_sent()) {
                return;
            }
            http_response_code($statusCode);
            header('Content-Type: text/html; charset=utf-8');
            // Static markup only — no exception detail is echoed to the client.
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
                . '<title>Internal Server Error</title></head><body>'
                . '<h1>Something went wrong</h1>'
                . '<p>An unexpected error occurred. Please try again later.</p>'
                . '</body></html>';
        };
    }
}
