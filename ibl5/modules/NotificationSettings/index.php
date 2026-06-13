<?php

declare(strict_types=1);

/**
 * NotificationSettings Module - GM notification preference management
 *
 * Lets logged-in GMs configure which event notifications and digests they receive.
 *
 * @see NotificationSettings\NotificationSettingsService For preference logic
 * @see NotificationSettings\NotificationSettingsView For HTML rendering
 */

if (stripos($_SERVER['PHP_SELF'], 'modules.php') === false) {
    die("You can't access this file directly...");
}

use NotificationSettings\NotificationSettingsRepository;
use NotificationSettings\NotificationSettingsService;
use NotificationSettings\NotificationSettingsView;

global $mysqli_db, $user, $authService;

// Must be logged in
if (!is_user($user)) {
    loginbox();
}

$opRaw = $_REQUEST['op'] ?? 'main';
$op = is_string($opRaw) ? $opRaw : 'main';

$repository = new NotificationSettingsRepository($mysqli_db);
$service = new NotificationSettingsService($repository);

// Handle POST-redirect operations before PageLayout::header() sends output,
// so header('Location: ...') can actually redirect the browser.
$userId = $authService->getUserId();

if ($op === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST' && $userId !== null) {
    if (\Security\CsrfGuard::validateSubmittedToken('notification_prefs_save')) {
        $service->savePrefsForUser($userId, array_keys($_POST));
        header('Location: modules.php?name=NotificationSettings&saved=1');
        return;
    }
}

// Non-POST requests to save redirect to main
if ($op === 'save' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: modules.php?name=NotificationSettings');
    return;
}

PageLayout\PageLayout::header();

$view = new NotificationSettingsView();

if ($userId === null) {
    echo '<div class="ibl-alert ibl-alert--error">Unable to determine user identity.</div>';
    PageLayout\PageLayout::footer();
    // footer() calls die(), but guard against future changes
    return;
}

switch ($op) {
    case 'save':
        // CSRF validation failed (POST handled above), show error
        echo '<div class="ibl-alert ibl-alert--error">Invalid or expired form submission. Please try again.</div>';
        break;

    default:
        handleMain($service, $view, $userId);
        break;
}

PageLayout\PageLayout::footer();

/**
 * Show notification preferences form (default view)
 */
function handleMain(NotificationSettingsService $service, NotificationSettingsView $view, int $userId): void
{
    $prefs = $service->getPrefsForUser($userId);
    $justSaved = ($_GET['saved'] ?? '') === '1';

    echo $view->renderForm($prefs, $justSaved);
}
