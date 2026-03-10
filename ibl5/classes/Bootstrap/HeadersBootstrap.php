<?php

declare(strict_types=1);

namespace Bootstrap;

use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;

/**
 * HTTP security headers: CSP, HSTS, X-Frame-Options, X-Content-Type-Options.
 *
 * Extracted from mainfile.php lines 128-148.
 */
class HeadersBootstrap implements BootstrapStepInterface
{
    /**
     * @see BootstrapStepInterface::boot()
     */
    public function boot(ContainerInterface $container): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        header("Content-Security-Policy: default-src 'self'; "
            . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.google.com https://www.gstatic.com; "
            . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            . "font-src 'self' https://fonts.gstatic.com; "
            . "img-src 'self' data: https:; "
            . "frame-src https://www.google.com; "
            . "connect-src 'self'");

        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        $isProduction = $serverName !== 'localhost';
        if ($isProduction && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
