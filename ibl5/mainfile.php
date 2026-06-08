<?php

declare(strict_types=1);

/************************************************************************/
/* PHP-NUKE: Advanced Content Management System                         */
/* ============================================                         */
/*                                                                      */
/* Copyright (c) 2007 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/vendor/autoload.php';

if (is_link(__DIR__ . '/vendor')) {
    $worktreeClasses = realpath(__DIR__ . '/classes');
    if ($worktreeClasses !== false) {
        spl_autoload_register(static function (string $class) use ($worktreeClasses): void {
            $file = $worktreeClasses . '/' . str_replace('\\', '/', $class) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        }, true, true);
    }
}

require_once __DIR__ . '/classes/Bootstrap/LegacyFunctions.php';

$bootApp = \Bootstrap\WebApplicationFactory::build(__DIR__);
$bootApp->boot();

// Default the language when nuke_config is unset/empty (e.g. fresh installs or
// minimal seeds). Under strict_types, passing a null $language to setcookie()
// below is a fatal TypeError rather than a silent null→"" coercion.
$language = isset($language) && is_string($language) && $language !== '' ? $language : 'english';

if (!defined('FORUM_ADMIN')) {
    if ((isset($newlang)) and (stristr($newlang, "."))) {
        if (file_exists("language/lang-" . $newlang . ".php")) {
            setcookie("lang", $newlang, time() + 31536000);
            include_secure("language/lang-" . $newlang . ".php");
            $currentlang = $newlang;
        } else {
            setcookie("lang", $language, time() + 31536000);
            include_secure("language/lang-" . $language . ".php");
            $currentlang = $language;
        }
    } elseif (isset($lang)) {
        include_secure("language/lang-" . $lang . ".php");
        $currentlang = $lang;
    } else {
        setcookie("lang", $language, time() + 31536000);
        include_secure("language/lang-" . $language . ".php");
        $currentlang = $language;
    }
}

if (!defined('FORUM_ADMIN')) {
    $ThemeSel = 'IBL';
    include_once "themes/$ThemeSel/theme.php";
}

require_once __DIR__ . '/includes/buildRedirectUrl.php';
