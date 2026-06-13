<?php

declare(strict_types=1);

/**
 * Notifications Module — in-app GM notification inbox.
 *
 * Routes:
 *  - default:  List the logged-in GM's notifications (newest first).
 *  - mark      (POST): Mark one notification read (CSRF + session-scoped authz).
 *  - mark_all  (POST): Mark all of the GM's notifications read.
 *
 * Authorization invariant: the team id is ALWAYS resolved from the session
 * username via NavigationRepository::resolveTeamId() — never read from the
 * request body — so a forged notification id cannot touch another team's rows.
 *
 * @see Notifications\NotificationRepository For database operations
 * @see Notifications\NotificationsView For HTML rendering
 */

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$op = is_string($_REQUEST['op'] ?? null) ? $_REQUEST['op'] : '';

use Notifications\NotificationRepository;
use Notifications\NotificationsView;

/**
 * Resolve the logged-in user's team id, or null when not logged in / no team.
 */
function notifications_resolve_team_id(mixed $user): ?int
{
    global $mysqli_db, $cookie;

    if (!is_user($user)) {
        return null;
    }

    cookiedecode($user);
    $username = (string) ($cookie[1] ?? '');
    if ($username === '') {
        return null;
    }

    return (new \Navigation\NavigationRepository($mysqli_db))->resolveTeamId($username);
}

/**
 * Default route — render the inbox for an authenticated team owner.
 *
 * @param mixed $user User authentication data
 */
function main(mixed $user): void
{
    global $mysqli_db;

    if (!is_user($user)) {
        loginbox();
        return;
    }

    $teamId = notifications_resolve_team_id($user);

    \PageLayout\PageLayout::header();

    if ($teamId === null) {
        echo '<h2 class="ibl-title">Notifications</h2>'
            . '<div class="ibl-empty-state"><p class="ibl-empty-state__text">'
            . 'You don\'t own a team, so you have no notifications.</p></div>';
        \PageLayout\PageLayout::footer();
        return;
    }

    $repository = new NotificationRepository($mysqli_db);
    $notifications = $repository->getForTeam($teamId);

    $markToken = \Security\CsrfGuard::generateRawToken('notif_mark');
    $markAllToken = \Security\CsrfGuard::generateRawToken('notif_mark_all');

    $view = new NotificationsView();
    echo $view->render($notifications, $markToken, $markAllToken);

    \PageLayout\PageLayout::footer();
}

/**
 * POST handler — mark a single notification read.
 *
 * @param mixed $user User authentication data
 */
function mark_read(mixed $user): void
{
    global $mysqli_db;

    if (!\Security\CsrfGuard::validateSubmittedToken('notif_mark')) {
        \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Notifications&error=' . rawurlencode('Invalid or expired form submission. Please try again.'));
        return;
    }

    $teamId = notifications_resolve_team_id($user);
    if ($teamId === null) {
        \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Notifications');
        return;
    }

    $id = is_numeric($_POST['id'] ?? null) ? (int) $_POST['id'] : 0;
    if ($id > 0) {
        (new NotificationRepository($mysqli_db))->markRead($id, $teamId);
    }

    \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Notifications');
}

/**
 * POST handler — mark all of the GM's notifications read.
 *
 * @param mixed $user User authentication data
 */
function mark_all_read(mixed $user): void
{
    global $mysqli_db;

    if (!\Security\CsrfGuard::validateSubmittedToken('notif_mark_all')) {
        \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Notifications&error=' . rawurlencode('Invalid or expired form submission. Please try again.'));
        return;
    }

    $teamId = notifications_resolve_team_id($user);
    if ($teamId !== null) {
        (new NotificationRepository($mysqli_db))->markAllRead($teamId);
    }

    \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Notifications');
}

switch ($op) {
    case 'mark':
        mark_read($user);
        break;
    case 'mark_all':
        mark_all_read($user);
        break;
    default:
        main($user);
        break;
}
