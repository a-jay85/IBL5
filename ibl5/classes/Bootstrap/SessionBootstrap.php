<?php

declare(strict_types=1);

namespace Bootstrap;

use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;

/**
 * Session bootstrap: cookie params and session_start().
 *
 * Extracted from mainfile.php lines 105-126.
 */
class SessionBootstrap implements BootstrapStepInterface
{
    private const SESSION_LIFETIME = 15552000; // 6 months (180 days)

    /**
     * @see BootstrapStepInterface::boot()
     */
    public function boot(ContainerInterface $container): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $isHttps = self::detectHttps();

        session_set_cookie_params([
            'lifetime' => self::SESSION_LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        ini_set('session.gc_maxlifetime', (string) self::SESSION_LIFETIME);

        session_start();
    }

    /**
     * Detect whether the current request is served over HTTPS.
     */
    public static function detectHttps(): bool
    {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === 443);
    }
}
