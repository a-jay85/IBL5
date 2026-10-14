<?php

declare(strict_types=1);

require __DIR__ . '/../../mainfile.php';

global $mysqli_db, $user, $authService;
$nukeCompat = new \Utilities\NukeCompat();
/** @var \mysqli $mysqli_db */
/** @var \Auth\AuthService $authService */

$httpRequest = \Http\HttpRequest::fromGlobals();
$serverNameRaw = $httpRequest->server('SERVER_NAME');
$serverName = is_string($serverNameRaw) ? $serverNameRaw : '';

$controller = new \Extension\ExtensionController(
    new \Repositories\TeamIdentityRepository($mysqli_db),
    new \Extension\ExtensionProcessor($mysqli_db, $serverName),
);

// NukeCompat::isUser()/cookieDecode() ignore their argument and read the $authService global
// that mainfile.php's boot() wired up. Gate order lives in the controller.
\Utilities\HtmxHelper::redirect($controller->handleSubmission(
    static fn (): bool => \Security\CsrfGuard::validateSubmittedToken('extension'),
    static fn (): bool => $nukeCompat->isUser($user ?? ''),
    static function () use ($user, $authService, $nukeCompat): string {
        $nukeCompat->cookieDecode($user ?? '');
        return $authService->getUsername() ?? '';
    },
    $_POST,
));
