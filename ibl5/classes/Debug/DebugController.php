<?php

declare(strict_types=1);

namespace Debug;

use Auth\AuthService;
use Debug\Contracts\DebugControllerInterface;
use Debug\DebugSession;
use Security\CsrfGuard;
use Utilities\HtmxHelper;

/**
 * @see DebugControllerInterface
 */
class DebugController implements DebugControllerInterface
{
    public function __construct(private AuthService $authService)
    {
    }

    public function handleToggle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            HtmxHelper::redirect('/ibl5/');
        }

        $username = $this->authService->getUsername() ?? '';
        $serverName = $_SERVER['SERVER_NAME'] ?? null;
        $cookieValue = $_COOKIE[DebugSession::COOKIE_NAME] ?? null;
        $debugSession = new DebugSession(
            $username,
            is_string($serverName) ? $serverName : null,
            is_string($cookieValue) ? $cookieValue : null,
            getenv('E2E_TESTING') === '1',
        );

        if (!$debugSession->isDebugAdmin()) {
            HtmxHelper::redirect('/ibl5/');
        }

        if (!CsrfGuard::validateSubmittedToken('debug_toggle')) {
            HtmxHelper::redirect('/ibl5/');
        }

        $debugSession->toggleViewAllExtensions();

        $redirect = $_POST['redirect'] ?? '/ibl5/';
        if (!is_string($redirect)) {
            $redirect = '/ibl5/';
        }

        $redirect = self::sanitizeRedirect($redirect);

        HtmxHelper::redirect($redirect);
    }

    public static function sanitizeRedirect(string $url): string
    {
        if ($url === '' || $url[0] !== '/') {
            return '/ibl5/';
        }

        if (str_starts_with($url, '//')) {
            return '/ibl5/';
        }

        $parsed = parse_url($url);
        if ($parsed === false || isset($parsed['scheme']) || isset($parsed['host'])) {
            return '/ibl5/';
        }

        return $url;
    }
}
