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
    global $stop;
    if (!is_user($user)) {
        Nuke\Header::header();
        echo "<center><font class=\"title\"><b>" . ($stop ? _LOGININCOR : _USERREGLOGIN) . "</b></font></center>";
        echo "<br>";
        if (!is_user($user)) {
            loginbox();
        }
        Nuke\Footer::footer();
    } elseif (is_user($user)) {
        global $cookie;
        cookiedecode($user);
        userinfo($cookie[1]);
    }
}

function submit()
{
    global $mysqli_db;

    Nuke\Header::header();

    $handler = new DepthChartEntry\DepthChartEntrySubmissionHandler($mysqli_db);
    $handler->handleSubmission($_POST);

    Nuke\Footer::footer();
}

switch ($op) {
    case "submit":
        submit();
        break;
    default:
        main($user);
        break;
}
