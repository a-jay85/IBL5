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
} else {
    $edtcss = "editor_css : \"includes/tiny_mce/themes/default/editor_ui.css\",";
}

if ($nuke_editor == 1) {
    if (is_admin($admin) and $name != "Private_Messages" and $name != "Forums" and !defined('NO_EDITOR')) {
        echo "<!-- tinyMCE -->
			<script language=\"javascript\" type=\"text/javascript\" src=\"includes/tiny_mce/tiny_mce.js\"></script>
			<script language=\"javascript\" type=\"text/javascript\">
		   	tinyMCE.init({
	      		mode : \"textareas\",
				theme : \"basic\",
				language : \"$lang\",
				$edtcss
				force_p_newlines: \"false\",
				force_br_newlines: \"true\"
		   	});
			</script>
			<!-- /tinyMCE -->";
    } elseif ($name != "Private_Messages" and $name != "Forums" and !defined('NO_EDITOR')) {
        echo "<!-- tinyMCE -->
			<script language=\"javascript\" type=\"text/javascript\" src=\"includes/tiny_mce/tiny_mce.js\"></script>
			<script language=\"javascript\" type=\"text/javascript\">
		   	tinyMCE.init({
	      		mode : \"textareas\",
				theme : \"default\",
				language : \"$lang\",
				$edtcss
				force_p_newlines: \"false\",
				force_br_newlines: \"true\"
		   	});
			</script>
			<!-- /tinyMCE -->";
    }
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
