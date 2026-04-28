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
    global $cookie;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        HtmxHelper::redirect('/');
    }

    $username = strval($cookie[1] ?? '');
    $debugSession = new DebugSession(
        $username,
        $_SERVER['SERVER_NAME'] ?? null,
        $_COOKIE[DebugSession::COOKIE_NAME] ?? null,
    );

    if (!$debugSession->isDebugAdmin()) {
        HtmxHelper::redirect('/');
    }

    if (!CsrfGuard::validateSubmittedToken('debug_toggle')) {
        HtmxHelper::redirect('/');
    }

    $debugSession->toggleViewAllExtensions();

    $redirect = $_POST['redirect'] ?? '/';
    if (!is_string($redirect)) {
        $redirect = '/';
    }

    $redirect = sanitizeRedirect($redirect);

    HtmxHelper::redirect($redirect);
}

function sanitizeRedirect(string $url): string
{
    if ($url === '' || $url[0] !== '/') {
        return '/';
    }

    if (str_starts_with($url, '//')) {
        return '/';
    }

    $parsed = parse_url($url);
    if ($parsed === false || isset($parsed['scheme']) || isset($parsed['host'])) {
        return '/';
    }

    return $url;
}

$op = $_REQUEST['op'] ?? '';

switch ($op) {
    case 'toggle_extensions':
        toggleExtensions();
        break;
    default:
        HtmxHelper::redirect('/');
}
