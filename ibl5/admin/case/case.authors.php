<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2007 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

if (!defined('ADMIN_FILE')) {
    die("Access Denied");
}

switch ($op) {

    case "mod_authors":
    case "modifyadmin":
    case "UpdateAuthor":
    case "AddAuthor":
    case "deladmin2":
    case "deladmin":
    case "assignstories":
    case "deladminconf":
        include "admin/modules/authors.php";
        break;

}
