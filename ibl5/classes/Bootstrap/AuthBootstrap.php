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

        $authService = new \Auth\AuthService($mysqliDb);
        $authService->tryRememberMe();

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

        // Custom mainfile extensions
        if (file_exists($this->basePath . '/includes/custom_files/custom_mainfile.php')) {
            @include_once $this->basePath . '/includes/custom_files/custom_mainfile.php';
        }
    }
}
