<?php

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = " - Depth Chart Entry";

function userinfo($username)
{
    global $mysqli_db;

    $controller = new DepthChart\DepthChartController($mysqli_db);
    $controller->displayForm($username);
}

function main($user)
{
    global $stop;
    if (!is_user($user)) {
        Nuke\Header::header();
        OpenTable();
        echo "<center><font class=\"title\"><b>" . ($stop ? _LOGININCOR : _USERREGLOGIN) . "</b></font></center>";
        CloseTable();
        echo "<br>";
        if (!is_user($user)) {
            OpenTable();
            loginbox();
            CloseTable();
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
    OpenTable();

    $handler = new DepthChart\DepthChartSubmissionHandler($mysqli_db);
    $handler->handleSubmission($_POST);

    CloseTable();
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
