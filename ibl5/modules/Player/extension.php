<?php

declare(strict_types=1);

require __DIR__ . '/../../mainfile.php';

global $user, $authService, $bootApp;
/** @var \Auth\AuthService $authService */
/** @var \Bootstrap\Application $bootApp */
$services = \Module\ModuleServices::currentOrPublish(
    static fn (): \Bootstrap\Contracts\ContainerInterface => $bootApp->getContainer()
);
$nukeCompat = $services->nukeCompat();

$httpRequest = \Http\HttpRequest::fromGlobals();
$serverNameRaw = $httpRequest->server('SERVER_NAME');
$serverName = is_string($serverNameRaw) ? $serverNameRaw : '';

$controller = $services->factory(\Module\Factories\PlayerFactory::class)->extensionController($serverName);

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
