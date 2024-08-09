<?php

namespace Nuke;

class Header
{
    public static function head()
    {
        global $sitename, $pagetitle;
        $ThemeSel = get_theme();
        include_secure("themes/$ThemeSel/theme.php");
        echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
        echo '<html xmlns="http://www.w3.org/1999/xhtml">';
        echo "<head>\n";
        echo "<title>$sitename $pagetitle</title>\n";
        echo '<meta name="google-site-verification" content="3y3xJYDHSYUitn7cbfFfI6C2BiK_q66dtRfykpzHW5w" />';
        echo "<script src=\"jslib/sorttable.js\"></script>";

        ?>
        <!-- banner org_green -->

                <!-- Attach our CSS -->
            <link rel="stylesheet" href="themes/<?php echo $ThemeSel ?>/orbit-1.2.3.css">


            <!-- Attach necessary JS -->
            <script type="text/javascript" src="themes/<?php echo $ThemeSel ?>/jquery-1.5.1.min.js"></script>
            <script type="text/javascript" src="themes/<?php echo $ThemeSel ?>/jquery.orbit-1.2.3.min.js"></script>

                <!--[if IE]>
                    <style type="text/css">
                        .timer { display: none !important; }
                        div.caption { background:transparent; filter:progid:DXImageTransform.Microsoft.gradient(startColorstr=#99000000,endColorstr=#99000000);zoom: 1; }
                    </style>
                <![endif]-->

            <!-- Run the plugin -->
            <script type="text/javascript">
                $(window).load(function() {
                    $('#featured').orbit();
                });
            </script>


        <!-- end banner org_green -->
        <?php
        include "includes/meta.php";
        include "includes/javascript.php";

        if (file_exists("themes/$ThemeSel/images/favicon.ico")) {
            echo "<link REL=\"shortcut icon\" HREF=\"themes/$ThemeSel/images/favicon.ico\" TYPE=\"image/x-icon\">\n";
        }
        echo "<link rel=\"alternate\" type=\"application/rss+xml\" title=\"RSS\" href=\"backend.php\">\n";
        echo "<LINK REL=\"StyleSheet\" HREF=\"themes/$ThemeSel/style/style.css\" TYPE=\"text/css\">\n\n\n";
        if (file_exists("includes/custom_files/custom_head.php")) {
            include_secure("includes/custom_files/custom_head.php");
        }
        echo "\n\n\n</head>\n\n";
        if (file_exists("includes/custom_files/custom_header.php")) {
            include_secure("includes/custom_files/custom_header.php");
        }
        themeheader();
    }

    public static function header()
    {
        define('NUKE_HEADER', true);
        require_once "mainfile.php";

        online();
        Header::head();
        include "includes/counter.php";
        if (defined('HOME_FILE')) {
            message_box();
            blocks("Center");
        }
    }
}