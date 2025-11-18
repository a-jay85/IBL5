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

if (stristr(htmlentities($_SERVER['PHP_SELF']), "javascript.php")) {
    Header("Location: ../index.php");
    die();
}

##################################################
# Include for some common javascripts functions  #
##################################################

global $module, $name, $admin, $advanced_editor, $lang, $nuke_editor;

if (file_exists("themes/" . $ThemeSel . "/style/editor.css")) {
    $edtcss = "editor_css : \"themes/" . $ThemeSel . "/style/editor.css\",";
}

if (defined('MODULE_FILE') and file_exists("modules/" . $name . "/copyright.php")) {
    echo "<script type=\"text/javascript\">\n";
    echo "<!--\n";
    echo "function openwindow(){\n";
    echo "	window.open (\"modules/" . $name . "/copyright.php\",\"Copyright\",\"toolbar=no,location=no,directories=no,status=no,scrollbars=yes,resizable=no,copyhistory=no,width=400,height=200\");\n";
    echo "}\n";
    echo "//-->\n";
    echo "</SCRIPT>\n\n";
}
