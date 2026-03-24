<?php

declare(strict_types=1);

/**
 * ApiKeys Module - Self-service API key management
 *
 * Lets logged-in users generate, view, and revoke their own API keys
 * for use with the Player Export CSV endpoint and Google Sheets IMPORTDATA.
 *
 * @see ApiKeys\ApiKeysService For key generation logic
 * @see ApiKeys\ApiKeysView For HTML rendering
 */

if (stripos($_SERVER['PHP_SELF'], 'modules.php') === false) {
    die("You can't access this file directly...");
}

use ApiKeys\ApiKeysRepository;
use ApiKeys\ApiKeysService;
use ApiKeys\ApiKeysView;

global $mysqli_db, $user, $authService;

// Must be logged in
if (!is_user($user)) {
    loginbox();
}

PageLayout\PageLayout::header();

$opRaw = $_REQUEST['op'] ?? 'main';
$op = is_string($opRaw) ? $opRaw : 'main';

$repository = new ApiKeysRepository($mysqli_db);
$service = new ApiKeysService($repository);
$view = new ApiKeysView();

$userId = $authService->getUserId();
if ($userId === null) {
    echo '<div class="ibl-alert ibl-alert--error">Unable to determine user identity.</div>';
    PageLayout\PageLayout::footer();
    // footer() calls die(), but guard against future changes
    return;
}

switch ($op) {
    case 'generate':
        handleGenerate($service, $view, $userId, $authService);
        break;

    case 'revoke':
        handleRevoke($service, $userId);
        break;

    default:
        handleMain($service, $view, $userId);
        break;
}

PageLayout\PageLayout::footer();

/**
 * Show key status (default view)
 */
function handleMain(ApiKeysService $service, ApiKeysView $view, int $userId): void
{
    $keyStatus = $service->getUserKeyStatus($userId);

    if ($keyStatus === null) {
        echo $view->renderNoKeyState();
    } else {
        echo $view->renderActiveKeyState($keyStatus);
    }
}

/**
 * Generate a new API key (POST only)
 */
function handleGenerate(ApiKeysService $service, ApiKeysView $view, int $userId, \Auth\AuthService $authService): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: modules.php?name=ApiKeys');
        return;
    }

    if (!\Utilities\CsrfGuard::validateSubmittedToken('api_keys_generate')) {
        echo '<div class="ibl-alert ibl-alert--error">Invalid or expired form submission. Please try again.</div>';
        return;
    }

    $username = $authService->getUsername();
    if ($username === null) {
        echo '<div class="ibl-alert ibl-alert--error">Unable to determine username.</div>';
        return;
    }

    try {
        $result = $service->generateKeyForUser($userId, $username);
        echo $view->renderNewKeyState($result['raw_key']);
    } catch (\RuntimeException $e) {
        echo '<div class="ibl-alert ibl-alert--error">' . \Utilities\HtmlSanitizer::e($e->getMessage()) . '</div>';
    }
}

/**
 * Revoke the current API key (POST only)
 */
function handleRevoke(ApiKeysService $service, int $userId): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: modules.php?name=ApiKeys');
        return;
    }

    if (!\Utilities\CsrfGuard::validateSubmittedToken('api_keys_revoke')) {
        echo '<div class="ibl-alert ibl-alert--error">Invalid or expired form submission. Please try again.</div>';
        return;
    }

    $service->revokeKeyForUser($userId);
    header('Location: modules.php?name=ApiKeys');
}
