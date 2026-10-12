<?php

declare(strict_types=1);

require __DIR__ . '/../../mainfile.php';

global $mysqli_db, $user, $authService;
/** @var \mysqli $mysqli_db */
/** @var \Auth\AuthService $authService */

$controller = new \Extension\ExtensionController(
    new \Repositories\TeamIdentityRepository($mysqli_db),
    new \Extension\ExtensionProcessor($mysqli_db, $_SERVER['SERVER_NAME'] ?? ''),
);

// is_user()/cookiedecode() ignore their argument and read the $authService global
// that mainfile.php's boot() wired up. Gate order lives in the controller.
\Utilities\HtmxHelper::redirect($controller->handleSubmission(
    static fn (): bool => \Security\CsrfGuard::validateSubmittedToken('extension'),
    static fn (): bool => is_user($user ?? '') === 1,
    static function () use ($user, $authService): string {
        cookiedecode($user ?? '');
        return $authService->getUsername() ?? '';
    },
    $_POST,
));
