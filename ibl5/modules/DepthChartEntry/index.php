<?php

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = " - Depth Chart Entry";

function userinfo($username)
{
    global $mysqli_db;

    $controller = new DepthChartEntry\DepthChartEntryController($mysqli_db);
    $controller->displayForm($username);
}

function main($user)
{
    if (!is_user($user)) {
        loginbox();
    } else {
        global $cookie;
        cookiedecode($user);
        userinfo($cookie[1]);
    }
}

function submit()
{
    global $mysqli_db;

    PageLayout\PageLayout::header();

    $handler = new DepthChartEntry\DepthChartEntrySubmissionHandler($mysqli_db);
    $handler->handleSubmission($_POST);

    PageLayout\PageLayout::footer();
}

function tabApi()
{
    global $mysqli_db;

    $handler = new DepthChartEntry\DepthChartEntryApiHandler($mysqli_db);
    $handler->handle();
}

function api($user)
{
    global $mysqli_db, $cookie;

    if (!is_user($user)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }

    cookiedecode($user);
    $username = $cookie[1];

    $commonRepo = new Services\CommonMysqliRepository($mysqli_db);
    $teamName = $commonRepo->getTeamnameFromUsername($username);
    if ($teamName === null || $teamName === '' || $teamName === 'Free Agents') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['error' => 'No team assigned']);
        return;
    }

    $tid = $commonRepo->getTidFromTeamname($teamName) ?? 0;
    if ($tid === 0) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['error' => 'Team not found']);
        return;
    }

    $action = $_GET['action'] ?? '';

    // For rename, use POST params; for list/load, use GET params
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawBody = file_get_contents('php://input');
        $params = is_string($rawBody) && $rawBody !== '' ? (json_decode($rawBody, true) ?? []) : [];
        if (!is_array($params)) {
            $params = [];
        }
    } else {
        $params = $_GET;
    }

    $handler = new SavedDepthChart\SavedDepthChartApiHandler($mysqli_db);
    $handler->handle($action, $tid, $username, $params);
}

switch ($op) {
    case "submit":
        submit();
        break;
    case "tab-api":
        tabApi();
        break;
    case "api":
        api($user);
        break;
    default:
        main($user);
        break;
}
