<?php

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

use Debug\DebugSession;
use Utilities\CsrfGuard;
use Utilities\HtmxHelper;

function toggleExtensions(): void
{
    global $authService;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        HtmxHelper::redirect('/ibl5/');
    }

    $username = $authService->getUsername() ?? '';
    $debugSession = new DebugSession(
        $username,
        $_SERVER['SERVER_NAME'] ?? null,
        $_COOKIE[DebugSession::COOKIE_NAME] ?? null,
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

    $redirect = sanitizeRedirect($redirect);

    HtmxHelper::redirect($redirect);
}

function sanitizeRedirect(string $url): string
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

$op = $_REQUEST['op'] ?? '';

switch ($op) {
    case 'toggle_extensions':
        toggleExtensions();
        break;
    default:
        HtmxHelper::redirect('/ibl5/');
}
