<?php

declare(strict_types=1);

namespace Bootstrap;

use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;

/**
 * Authentication bootstrap: AuthService init, remember-me, legacy $user global.
 *
 * Extracted from mainfile.php lines 210-233.
 */
class AuthBootstrap implements BootstrapStepInterface
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * @see BootstrapStepInterface::boot()
     */
    public function boot(ContainerInterface $container): void
    {
        /** @var \mysqli $mysqliDb */
        $mysqliDb = $GLOBALS['mysqli_db'];

        $authService = new \Auth\AuthService(
            new \Auth\AuthRepository($mysqliDb),
            null,
            static fn (): \PDO => \Database\PdoConnection::getInstance(),
        );
        $authService->tryRememberMe();

        // Dev-only auto-login: bypasses login forms on localhost when DEV_AUTO_LOGIN is set.
        // E2E tests set _no_auto_login cookie to opt out.
        $noAutoLogin = isset($_COOKIE['_no_auto_login']) && $_COOKIE['_no_auto_login'] === '1';
        if (!$authService->isAuthenticated() && !$noAutoLogin) {
            \Auth\DevAutoLogin::tryAutoLogin($mysqliDb);
        }

        // Populate legacy $user global for backward compat
        $user = '';
        if ($authService->isAuthenticated()) {
            $cookieArray = $authService->getCookieArray();
            if ($cookieArray !== null) {
                $user = base64_encode(implode(':', $cookieArray));
            }
        }

        $GLOBALS['authService'] = $authService;
        $GLOBALS['user'] = $user;
        $container->set('authService', $authService);
        $container->set('auth.username', static fn (): string => $authService->getUsername() ?? '');

        // Custom mainfile extensions
        if (file_exists($this->basePath . '/includes/custom_files/custom_mainfile.php')) {
            /** @phpstan-ignore ibl.requireOnce (optional user customization hook; not a class) */
            @include_once $this->basePath . '/includes/custom_files/custom_mainfile.php';
        }
    }
}
