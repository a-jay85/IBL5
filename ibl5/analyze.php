<?php

// PHP-Nuke/phpBB2 Portal Checker: analyze.php
// ===========================================
// 
// Copyright (c) 2003-2005 CastleCops
// http://castlecops.com 
// 
// This program is free software. You can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License.
//
// DISCLAIMER
// Prior to use, please read the acceptable use policy at CastleCops.
// Use of this code signifies your acceptance of the AUP.
// This script is meant to help you debug your *Nuke installation.
// Use at your own risk!
// It is recommended to remove this script when no longer needed.
//
// HISTORY
// Borne from http://nukecops.com and partial contributions from 
// members cited below.	

function head() {
	global $appname, $PHP_SELF, $start_time;

	$mtime = microtime();
	$mtime = explode(" ",$mtime);
	$mtime = $mtime[1] + $mtime[0];
	$start_time = $mtime;

	$appname = $_SERVER['PHP_SELF'];

	if (!$appname) {
		$appname = $PHP_SELF;
	}

	?>
	<HTML>
	<HEAD>
	<TITLE>PHP-Nuke phpBB2 Analyzer by CastleCops.com</TITLE>
	</HEAD>
	<BODY bgcolor="#FFFFCC" text="#000000" link="#363636" vlink="#363636" alink="#d5ae83" LEFTMARGIN=10 TOPMARGIN=8 MARGINWIDTH=0 MARGINHEIGHT=0>
	<SPAN style="FONT-SIZE: 10pt; FONT-FAMILY: Arial">
	<?php
}

function DirectoryListing() {

	$SERVER_NAME = getenv("SERVER_NAME");

	if ($SERVER_NAME != "castlecops.com") {

		# DirectoryListing adopted from http://www.php.net/manual/en/function.readdir.php.
		$DirectoriesToScan = array(realpath('.'));
		$DirectoriesScanned = array();
		while (count($DirectoriesToScan) > 0) {
		 foreach ($DirectoriesToScan as $DirectoryKey => $startingdir) {
		   if ($dir = @opendir($startingdir)) {
		     while (($file = readdir($dir)) !== false) {
		       if (($file != '.') && ($file != '..')) {
		        $RealPathName = realpath($startingdir.'/'.$file);
			$DirInDir[] = dirname($RealPathName);
	        	 if (is_dir($RealPathName)) {
		           if (!in_array($RealPathName, $DirectoriesScanned) && !in_array($RealPathName, $DirectoriesToScan)) {
		             $DirectoriesToScan[] = $RealPathName;
	        	   }
		         } elseif (is_file($RealPathName)) {
		           $FilesInDir[] = $RealPathName;
                         }
		       }
		     }
		    closedir($dir);
		   }
		   $DirectoriesScanned[] = $startingdir;
		 unset($DirectoriesToScan[$DirectoryKey]);
		 }
		}
	
		$FilesInDir = array_unique($FilesInDir);
		sort($FilesInDir);
		$DirInDir = array_unique($DirInDir);
		sort($DirInDir);

                echo "<B>Permissions Key</B>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
                . "<TR bgcolor=#FFCC66><TD>Value</TD><TD>Definition</TD></TR>"
                . "<TR bgcolor=#FFFF99>"
                . "<TD>0</TD><TD>No Access</TD>"
                . "</TR>"
                . "<TR bgcolor=#FFFF99>"
                . "<TD>1</TD><TD>Execute Access</TD>"
                . "</TR>"
                . "<TR bgcolor=#FFFF99>"
                . "<TD>2</TD><TD>Write Access</TD>"
                . "</TR>"
                . "<TR bgcolor=#FFFF99>"
                . "<TD>3</TD><TD>Execute & Write Access</TD>"
                . "</TR>"
                . "<TR bgcolor=#FFFF99>"
                . "<TD>4</TD><TD>Read Access</TD>"
                . "</TR>"
                . "<TR bgcolor=#FFFF99>"
                . "<TD>5</TD><TD>Execute & Read Access</TD>"
                . "</TR>"
                . "<TR bgcolor=#FFFF99>"
                . "<TD>6</TD><TD>Write & Read Access</TD>"
                . "</TR>"
                . "<TR bgcolor=#FFFF99>"
                . "<TD>7</TD><TD>Execute, Write, & Read Access</TD>"
                . "</TR>"
                . "</TABLE>"
                . "<BR>";

		echo "<B>Owner, Group, All</B>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
                . "<TR bgcolor=#FFCC66><TD>Explanation</TD></TR>"
                . "<TR bgcolor=#FFFF99>"
                . "<TD>A bold three digit number will be displayed below after each directory and file listing (resource).  <BR>The leftmost digit signifies the Owner of the resource. <BR> The middle digit signifies the Group the resource belongs to. <BR> Lastly, the rightmost digit signifies the rest of the world, or All.</TD>"
                . "</TR>"
                . "<TR bgcolor=#FFFF99>"
                . "<TD>Example: <B>777</B> = Owner, Group, and All have full Execute, Write, & Read Access.<BR> Example: <B>541</B> = Owner has Execute & Read Access, Group has Read Access, and All others have only Execute Access.<BR>Example: <B>644</B> = Owner has Write & Read Access, both Group and All others have only Read Access.</TD>"
                . "</TR>"
                . "</TABLE>"
                . "<BR>";


		echo "<H2>Non-Empty Directories Listing Section...</H2>";

		foreach ($DirInDir as $dirname) {
		 $perms = decoct(fileperms($dirname)) % 1000;
                 echo $dirname." | <B>".$perms."</B><BR>";
                }
		
		echo "<BR><H2>Files Listing Section...</H2>";
	
		foreach ($FilesInDir as $filename) {
		 $perms = decoct(fileperms($filename)) % 1000;
		 echo $filename." | <B>".$perms."</B><BR>";
		}
	} else {
		echo "<B>Feature disabled for $SERVER_NAME</B><BR>";
	}
}

function analyzerImage() {

        $img_width = $str_width + 381;
        $img_height = $str_height + 201;

        $im = ImageCreate($img_width,$img_height);

        $black = ImageColorAllocate($im, 0, 0, 0);
        $white = ImageColorAllocate($im, 255, 255, 255);
        $grey = ImageColorAllocate($im, 204, 204, 204);
	$red = ImageColorAllocate($im, 225, 0, 0);
	$blue = ImageColorAllocate($im, 0, 0, 155);

        ImageFill($im, 0,0,$white);

	$y = 0;
	for ($j = 2; $j < 32; $j++) {
		if (($j%2) == 0) {
			ImageFilledRectangle($im, 0, $y, 380, $y + 15.384615384615384615384615384615, $red);
		}
		$y = $y + 15.384615384615384615384615384615;
	}

	ImageFilledRectangle($im, 0, 0, 152, 107.69230769230769230769230769231, $blue);

  	$corners = 10;
  	$dphi = 6.265/$corners;
  	$r[0] = 6.265;
  	$r[1] = 2;
	for ($up = 15; $up < 152; $up++) {
		$j = 0;
		$ir = 0;
		if (($up%25) == 15) {
		  	for ($i = 0, $phi = 0; $i < $corners; $i++, $phi += $dphi) {
    				$points[$j] = $up+$r[$ir]*sin($phi);
    				$j++;
				$points[$j] = 15-$r[$ir]*cos($phi);
	    			$j++;
    				$ir = 1-$ir;
			}
			ImageFilledPolygon($im, $points, $corners, $white);
		  	ImagePolygon($im, $points, $corners, $white);
		}
	}

        for ($up = 15; $up < 152; $up++) {
                $j = 0;
                $ir = 0;
                if (($up%25) == 15) {
                        for ($i = 0, $phi = 0; $i < $corners; $i++, $phi += $dphi) {
                                $points[$j] = $up+$r[$ir]*sin($phi);
                                $j++;
                                $points[$j] = 34.423076923076923076923076923075-$r[$ir]*cos($phi);
                                $j++;
                                $ir = 1-$ir;
                        }
                        ImageFilledPolygon($im, $points, $corners, $white);
                        ImagePolygon($im, $points, $corners, $white);
                }
        }

        for ($up = 15; $up < 152; $up++) {
                $j = 0;
                $ir = 0;
                if (($up%25) == 15) {
                        for ($i = 0, $phi = 0; $i < $corners; $i++, $phi += $dphi) {
                                $points[$j] = $up+$r[$ir]*sin($phi);
                                $j++;
                                $points[$j] = 53.84615384615384615384615384615-$r[$ir]*cos($phi);
                                $j++;
                                $ir = 1-$ir;
                        }
                        ImageFilledPolygon($im, $points, $corners, $white);
                        ImagePolygon($im, $points, $corners, $white);
                }
        }

        for ($up = 15; $up < 152; $up++) {
                $j = 0;
                $ir = 0;
                if (($up%25) == 15) {
                        for ($i = 0, $phi = 0; $i < $corners; $i++, $phi += $dphi) {
                                $points[$j] = $up+$r[$ir]*sin($phi);
                                $j++;
                                $points[$j] = 73.269230769230769230769230769225-$r[$ir]*cos($phi);
                                $j++;
                                $ir = 1-$ir;
                        }
                        ImageFilledPolygon($im, $points, $corners, $white);
                        ImagePolygon($im, $points, $corners, $white);
                }
        }

        for ($up = 15; $up < 152; $up++) {
                $j = 0;
                $ir = 0;
                if (($up%25) == 15) {
                        for ($i = 0, $phi = 0; $i < $corners; $i++, $phi += $dphi) {
                                $points[$j] = $up+$r[$ir]*sin($phi);
                                $j++;
                                $points[$j] = 92.6923076923076923076923076923-$r[$ir]*cos($phi);
                                $j++;
                                $ir = 1-$ir;
                        }
                        ImageFilledPolygon($im, $points, $corners, $white);
                        ImagePolygon($im, $points, $corners, $white);
                }
        }

        for ($up = 27; $up < 152; $up++) {
                $j = 0;
                $ir = 0;
                if (($up%25) == 2) {
                        for ($i = 0, $phi = 0; $i < $corners; $i++, $phi += $dphi) {
                                $points[$j] = $up+$r[$ir]*sin($phi);
                                $j++;
                                $points[$j] = 24.711538461538461538461538461535-$r[$ir]*cos($phi);
                                $j++;
                                $ir = 1-$ir;
                        }
                        ImageFilledPolygon($im, $points, $corners, $white);
                        ImagePolygon($im, $points, $corners, $white);
                }
        }

        for ($up = 27; $up < 152; $up++) {
                $j = 0;
                $ir = 0;
                if (($up%25) == 2) {
                        for ($i = 0, $phi = 0; $i < $corners; $i++, $phi += $dphi) {
                                $points[$j] = $up+$r[$ir]*sin($phi);
                                $j++;
                                $points[$j] = 44.13461538461538461538461538461-$r[$ir]*cos($phi);
                                $j++;
                                $ir = 1-$ir;
                        }
                        ImageFilledPolygon($im, $points, $corners, $white);
                        ImagePolygon($im, $points, $corners, $white);
                }
        }

        for ($up = 27; $up < 152; $up++) {
                $j = 0;
                $ir = 0;
                if (($up%25) == 2) {
                        for ($i = 0, $phi = 0; $i < $corners; $i++, $phi += $dphi) {
                                $points[$j] = $up+$r[$ir]*sin($phi);
                                $j++;
                                $points[$j] = 63.557692307692307692307692307685-$r[$ir]*cos($phi);
                                $j++;
                                $ir = 1-$ir;
                        }
                        ImageFilledPolygon($im, $points, $corners, $white);
                        ImagePolygon($im, $points, $corners, $white);
                }
        }

        for ($up = 27; $up < 152; $up++) {
                $j = 0;
                $ir = 0;
                if (($up%25) == 2) {
                        for ($i = 0, $phi = 0; $i < $corners; $i++, $phi += $dphi) {
                                $points[$j] = $up+$r[$ir]*sin($phi);
                                $j++;
                                $points[$j] = 82.98076923076923076923076923076-$r[$ir]*cos($phi);
                                $j++;
                                $ir = 1-$ir;
                        }
                        ImageFilledPolygon($im, $points, $corners, $white);
                        ImagePolygon($im, $points, $corners, $white);
                }
        }


        Header("Content-type: image/jpeg");
        $text_color = imagecolorallocate ($im, 70, 70, 70);
        imagejpeg($im, '', 100);
        imagedestroy($im);

	# Proportions obtained from http://www.math.grin.edu/~stone/courses/scheme/exercises/American-flag.xhtml
	# - The ratio between the long dimension of the flag (its ``fly'') and its short dimension (its ``hoist'') is 19/10. 
	# - The ratio between the hoist of any of the red or white stripes and the hoist of the whole flag is 1/13. 
	# - The ratio between the hoist of the Union (the blue rectangle in the upper left-hand corner) and the hoist of the whole flag is 7/13. 
	# - The ratio between the fly of the Union and the hoist of the whole flag is 76/100. 
	# - The ratio between the diameter of a circle circumscribing any of the white stars and the hoist of the whole flag is 616/10000. 


}

function main() {
	global $prefix, $user_prefix, $dbtype, $dbname, $dbuname, $dbpass, $dbhost, $Default_Theme, $language, $locale, $Version_Num, $sitekey, $NCsitename, $NCVersion_Num, $SERVER_NAME, $NClanguage, $NClocale, $NCsitekey, $dbi, $NCDefault_Theme, $appname, $sitename;
	$sitekey = 0;
	include("config.php");

	$NClocale = $locale;
	$NClanguage = $language;
	$NCDefault_Theme = $Default_Theme;
	$NCVersion_Num = $Version_Num;
	$SERVER_NAME = getenv("SERVER_NAME");
	$NCsitekey = $sitekey;
	$NCsitename = $sitename;

	echo "Click <a href=\"$appname?zx=ls\">here</a> for a recursive listing of your current working directory: ". getcwd() ."<BR><BR>";

	$dbi = @mysql_connect("$dbhost", "$dbuname", "$dbpass") or die("
                <B>Your config.php values</B>
                <TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">
                <TR bgcolor=#FFCC66><TD>Type</TD><TD>Value</TD></TR>
                <TR bgcolor=#FFFF99>
                <TD>dbhost</TD><TD>$dbhost</TD>
                </TR>
                <TR bgcolor=#FFFF99>
                <TD>dbname</TD><TD>$dbname</TD>
                </TR>
                <TR bgcolor=#FFFF99>
                <TD>dbuname</TD><TD>$dbuname</TD>
                </TR>
                <TR bgcolor=#FFFF99>
                <TD>prefix</TD><TD>$prefix</TD>
                </TR>
                <TR bgcolor=#FFFF99>
                <TD>user_prefix</TD><TD>$user_prefix</TD>
                </TR>
                <TR bgcolor=#FFFF99>
                <TD>dbtype</TD><TD>$dbtype</TD>
                </TR>
                </TABLE>
                <BR><BR><B>Could not connect to your MySQL Database, visit <a href=\"http://castlecops.com\">CastleCops</a> for support..</B><BR><BR><STRONG>MySQL Error:</STRONG><i> ".mysql_error()."</i>");
	
	mysql_select_db("$dbname") or die("
                <B>Your config.php values</B>
                <TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">
                <TR bgcolor=#FFCC66><TD>Type</TD><TD>Value</TD></TR>
                <TR bgcolor=#FFFF99>
                <TD>dbhost</TD><TD>$dbhost</TD>
                </TR>
                <TR bgcolor=#FFFF99>
                <TD>dbname</TD><TD>$dbname</TD>
                </TR>
                <TR bgcolor=#FFFF99>
                <TD>dbuname</TD><TD>$dbuname</TD>
                </TR>
                <TR bgcolor=#FFFF99>
                <TD>prefix</TD><TD>$prefix</TD>
                </TR>
                <TR bgcolor=#FFFF99>
                <TD>user_prefix</TD><TD>$user_prefix</TD>
                </TR>
                <TR bgcolor=#FFFF99>
                <TD>dbtype</TD><TD>$dbtype</TD>
                </TR>
                </TABLE><BR><BR><B>Could not select your database. Visit <a href=\"http://castlecops.com\">CastleCops</a> for support..</B><BR><BR><STRONG>MySQL Error:</STRONG><i> ".mysql_error()."</i>");
}

function secchk() {
	global $prefix, $user_prefix, $dbtype, $dbname, $dbuname, $dbpass, $dbhost, $Default_Theme, $language, $locale, $Version_Num, $sitekey, $NCsitename, $NCVersion_Num, $SERVER_NAME, $NClanguage, $NClocale, $NCsitekey, $dbi, $NCFver;

	if (ini_get('register_globals') == 1) {
                echo "<H2>WARNING! WARNING! WARNING! Vulnerable PHP Setting On Your Server!</H2>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\" bgcolor=#660000>"
                . "<TR bgcolor=#990000><TD><FONT COLOR=#FFCC00>register_globals</FONT></TD><TD><FONT COLOR=#FFCC00>Reason For Vulnerability</FONT></TD></TR>"
                . "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>On</FONT></TD><TD><FONT COLOR=#FFFFFF>This PHP setting of 'register_globals' = On leaves an open gap for attackers to manipulate your portal.  Highly recommended to disable this.  However, disabling it does not remove the possibility of being hacked.  For details read this and decide: http://php.net/register_globals.</FONT></TD></TR>"
                . "</TABLE><BR><BR>";
	}

	if (phpversion() < "4.4.0") {
                echo "<H2>WARNING! WARNING! WARNING! Vulnerable PHP On Your Server!</H2>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\" bgcolor=#660000>"
                . "<TR bgcolor=#990000><TD><FONT COLOR=#FFCC00>PHP Version</FONT></TD><TD><FONT COLOR=#FFCC00>Reason For Vulnerability</FONT></TD></TR>"
                . "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>". phpversion() ."</FONT></TD><TD><FONT COLOR=#FFFFFF>Your Server may be vulnerable to many PHP exploits.  Tell your host to read the SecurityFocus reports by clicking --> <a href=\"http://www.securityfocus.com/archive/1/\"><FONT COLOR=#FFCC00>here</FONT></a>. Until that is resolved, PHP-Nuke should be the least of your worries.</FONT></TD></TR>"
                . "<TR bgcolor=#990000><TD><FONT COLOR=#FFCC00>AFFECTED VERSIONS:</FONT></TD>"
                . "<TD><FONT COLOR=#FFCC00>Constraints</FONT></TD></TR>"
                . "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>4.3.0 and 4.3.1</FONT></TD>"
                . "<TD><FONT COLOR=#FFFFFF>with php.ini containing session.use_trans_sid=1</FONT></TD></TR>"
                . "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>4.2.0 to 4.2.3</FONT></TD>"
                . "<TD><FONT COLOR=#FFFFFF>without php.ini, or with php.ini containing session.use_trans_sid=1"
                . "(php.ini-dist and php.ini-recommended from the PHP source distribution had use_trans_sid=1 "
                . "from 4.2.0 to 4.2.2, and use_trans_sid=0 for 4.2.3 and later versions.)</FONT></TD></TR>"
                . "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>prior to 4.2.0</FONT></TD>"
                . "<TD><FONT COLOR=#FFFFFF>compiled with --enable-trans-sid and with session.use_trans_sid=1"
                . "</FONT></TD></TR>"
		. "<TR bgcolor=#990000><TD><FONT COLOR=#FFCC00>FIXED VERSIONS:</FONT></TD>"
                . "<TD><FONT COLOR=#FFCC00>Suggestion</FONT></TD></TR>"
		. "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>4.3.2 or later</FONT></TD>"
                . "<TD><FONT COLOR=#FFFFFF>Backup your system and upgrade PHP, also read the article at SecurityFocus.  "
		. "Solution 1 from Security Focus: <a href=\"http://www.securityfocus.com/bid/7761/solution/\"><FONT COLOR=#FFCC00>"
		. "Click</FONT></a>, "
		. " Solution 2 from thathost: <a href=\"http://shh.thathost.com/secadv/2003-05-11-php.txt\"><FONT COLOR=#FFCC00>"
		. "Click</FONT></a>."
		. "  Solution 1 suggests the use of mod_security, which is an Apache module discussed at Nuke Cops: "
		. "<a href=\"http://nukecops.com/article39.html\"><FONT COLOR=#FFCC00>Here</FONT></a>"
		. "</FONT></TD></TR>"
                . "</TABLE><BR><BR>";
	} elseif (phpversion() == "3.0" || phpversion() == "4.06" || phpversion() == "4.10" || phpversion() == "4.11") {
                echo "<H2>WARNING! WARNING! WARNING! Vulnerable PHP On Your Server!</H2>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\" bgcolor=#660000>"
                . "<TR bgcolor=#990000><TD><FONT COLOR=#FFCC00>PHP Version</FONT></TD><TD><FONT COLOR=#FFCC00>Reason For Vulnerability</FONT></TD></TR>"
                . "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>". phpversion() ."</FONT></TD><TD><FONT COLOR=#FFFFFF>This PHP version contains a vulnerability in \"php_mime_split\" function allowing arbitrary code execution.  Tell your host to read the CERT/CC Vulnerability Note VU#297363 by clicking --> <a href=\"http://www.kb.cert.org/vuls/id/297363\"><FONT COLOR=#FFCC00>here</FONT></a>. Until that is resolved, PHP-Nuke should be the least of your worries.</FONT></TD></TR>"
                . "</TABLE><BR><BR>";
        } elseif (preg_match ("/3.0.1/", phpversion()) || preg_match ("/4.0./", phpversion()) || phpversion() == "4.1.0"  || phpversion() == "4.1.1") {
                echo "<H2>WARNING! WARNING! WARNING! Vulnerable PHP On Your Server!</H2>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\" bgcolor=#660000>"
                . "<TR bgcolor=#990000><TD><FONT COLOR=#FFCC00>PHP Version</FONT></TD><TD><FONT COLOR=#FFCC00>Reason For Vulnerability</FONT></TD></TR>"
                . "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>". phpversion() ."</FONT></TD><TD><FONT COLOR=#FFFFFF>This PHP version contains a file uploads vulnerability.  Tell your host to read the e-matters Advisory 01/2002 by clicking --> <a href=\"http://security.e-matters.de/advisories/012002.html\"><FONT COLOR=#FFCC00>here</FONT></a>. Until that is resolved, PHP-Nuke should be the least of your worries.</FONT></TD></TR>"
                . "</TABLE><BR><BR>";
        } elseif (phpversion() == "4.2.0" || phpversion() == "4.2.1") {
                echo "<H2>WARNING! WARNING! WARNING! Vulnerable PHP On Your Server!</H2>"
		. "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\" bgcolor=#660000>"
                . "<TR bgcolor=#990000><TD><FONT COLOR=#FFCC00>PHP Version</FONT></TD><TD><FONT COLOR=#FFCC00>Reason For Vulnerability</FONT></TD></TR>"
                . "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>". phpversion() ."</FONT></TD><TD><FONT COLOR=#FFFFFF>This PHP version fails to properly parse the headers of HTTP POST requests.  Tell your host to read the CERT/CC Vulnerability Note VU#929115 by clicking --> <a href=\"http://www.kb.cert.org/vuls/id/929115\"><FONT COLOR=#FFCC00>here</FONT></a>. Until that is resolved, PHP-Nuke should be the least of your worries. Please reference PHP.net directly for a full review of this serious vulnerability: --> <a href=\"http://www.php.net/release_4_2_2.php\"><FONT COLOR=#FFCC00>click here</FONT></a></FONT>.</TD></TR>"
                . "</TABLE></FONT><BR><BR>";
        } elseif (phpversion() == "4.3.0") {
                echo "<H2>WARNING! WARNING! WARNING! Vulnerable PHP On Your Server!</H2>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\" bgcolor=#660000>"
                . "<TR bgcolor=#990000><TD><FONT COLOR=#FFCC00>PHP Version</FONT></TD><TD><FONT COLOR=#FFCC00>Reason For Vulnerability</FONT></TD></TR>"
                . "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>". phpversion() ."</FONT></TD><TD><FONT COLOR=#FFFFFF>PHP Security Advisory: CGI vulnerability in PHP version 4.3.0.  <BR><BR>PHP contains code for preventing direct access to the CGI binary with configure option \"--enable-force-cgi-redirect\" and php.ini option \"cgi.force_redirect\". In PHP 4.3.0 there is a bug which renders these options useless.  For a full report go here --> <a href=\"http://www.php.net/release_4_3_1.php\"><FONT COLOR=#FFCC00>here</FONT></a>. <BR><BR>Solution is to <a href=\"http://www.php.net/downloads.php\">upgrade</a> to PHP 4.3.1 as there are no other workarounds. <BR><BR>The impact: \"Anyone with access to websites hosted on a web server which employs the CGI module may exploit this vulnerability to gain access to any file readable by the user under which the webserver runs. A remote attacker could also trick PHP into executing arbitrary PHP code if attacker is able to inject the code into files accessible by the CGI. This could be for example the web server access-logs.\" <BR><BR>Until that is resolved, PHP-Nuke should be the least of your worries. </FONT>.</TD></TR>"
                . "</TABLE></FONT><BR><BR>";
	} elseif (phpversion() < "5.0.4") {
                echo "<H2>WARNING! WARNING! WARNING! Vulnerable PHP On Your Server!</H2>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\" bgcolor=#660000>"
                . "<TR bgcolor=#990000><TD><FONT COLOR=#FFCC00>PHP Version</FONT></TD><TD><FONT COLOR=#FFCC00>Reason For Vulnerability</FONT></TD></TR>"
                . "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>". phpversion() ."</FONT></TD><TD><FONT COLOR=#FFFFFF>In general it is best to run the most recent production release of PHP in the version 5 or 4 series.  Newest versions tend to fix vulnerabilities in previous releases.  5.0.4 is the newest version.</FONT></TD></TR>"
                . "</TABLE><BR><BR>";
        }

	$mysql_server_info = mysql_get_server_info();

        if ($mysql_server_info < "4.1.12") {
                echo "<H2>WARNING! WARNING! WARNING! Vulnerable MySQL Server On Your Server!</H2>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\" bgcolor=#660000>"
                . "<TR bgcolor=#990000><TD><FONT COLOR=#FFCC00>MySQL Server Version</FONT></TD><TD><FONT COLOR=#FFCC00>Reason For Vulnerability</FONT></TD></TR>"
                . "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>". $mysql_server_info ."</FONT></TD><TD><FONT COLOR=#FFFFFF>It is generally recommended by Mysql.com to patch to the newest MySQL version.</FONT></TD></TR>"
                . "</TABLE><BR>";
        }


        if ($mysql_server_info < "3.23.58") {
                echo "<H2>WARNING! WARNING! WARNING! Vulnerable MySQL Server On Your Server!</H2>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\" bgcolor=#660000>"
                . "<TR bgcolor=#990000><TD><FONT COLOR=#FFCC00>MySQL Server Version</FONT></TD><TD><FONT COLOR=#FFCC00>Reason For Vulnerability</FONT></TD></TR>"
                . "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>". $mysql_server_info ."</FONT></TD><TD><FONT COLOR=#FFFFFF>It is generally recommended by Mysql.com to run the General Available Release 4.1 series.  A vulnerability has been discovered in MySQL that may cause a denial of service. It has been reported that, under certain circumstances, a malicious MySQL client may be able to trigger a condition in which MySQL attempts to free the same memory twice.  MySQL Daemon can be crashed unless upgraded. Details can be found by clicking --> <a href=\"http://www.mysql.com/doc/en/News-3.23.55.html\"><FONT COLOR=#FFCC00>here</FONT></a>. Until that is resolved, PHP-Nuke should be the least of your worries.</FONT></TD></TR>"
                . "</TABLE><BR>";
        }


        if ($mysql_server_info < "3.23.33") {
                echo "<H2>WARNING! WARNING! WARNING! Vulnerable MySQL Server On Your Server!</H2>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\" bgcolor=#660000>"
                . "<TR bgcolor=#990000><TD><FONT COLOR=#FFCC00>MySQL Server Version</FONT></TD><TD><FONT COLOR=#FFCC00>Reason For Vulnerability</FONT></TD></TR>"
                . "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>". $mysql_server_info ."</FONT></TD><TD><FONT COLOR=#FFFFFF>It is generally recommended by Mysql.com to run the General Available Release 4.1 series.  This MySQL Server version monitor drop database command contains buffer overflow.  Tell your host to read the CERT/CC Vulnerability Note VU#367320 by clicking --> <a href=\"http://www.kb.cert.org/vuls/id/367320\"><FONT COLOR=#FFCC00>here</FONT></a>. Until that is resolved, PHP-Nuke should be the least of your worries.</FONT></TD></TR>"
                . "</TABLE><BR>";
        }


	if ( $NCsitekey ) {
		if ($NCsitekey == "SdFk*fa28367-dm56w69.3a2fDS+e9") {
			echo "<H2>WARNING! WARNING! WARNING! Your config.php \$sitekey has the default value!</H2>"
	                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\" bgcolor=#660000>"
        	        . "<TR bgcolor=#990000><TD><FONT COLOR=#FFCC00>\$sitekey value</FONT></TD><TD><FONT COLOR=#FFCC00>Reason For Vulnerability</FONT></TD></TR>"
                	. "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>". $sitekey ."</FONT></TD><TD><FONT COLOR=#FFFFFF>PHP-Nuke has a default \$sitekey value as distributed in config.php.  You should change this immediately to a unique value only you know.</FONT></TD></TR>"
	                . "</TABLE><BR>";
		}
	}

	if (get_cfg_var('magic_quotes_gpc') == "0") {
		echo "<H2>WARNING! WARNING! WARNING! Your PHP-Nuke Site is open to SQL Injection Attack!</H2>"
                        . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\" bgcolor=#660000>"
                        . "<TR bgcolor=#990000><TD><FONT COLOR=#FFCC00>php.ini magic_quotes.gpc value</FONT></TD><TD><FONT COLOR=#FFCC00>Reason For Vulnerability</FONT></TD></TR>"
                        . "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>Not Enabled (Not \"On\")</FONT></TD><TD><FONT COLOR=#FFFFFF>If your <b>magic_quotes_gpc</b> is not <b>On</b>, as it currently isn't, you  should change this immediately to the <b>On</b> value. If left disabled then your site is susceptible to member password retrieval and member admin escalation.  For full details and exploits for testing go <a href=\"http://www.securityfocus.com/archive/1/314104/2003-03-03/2003-03-09/0\">here</a>. For details on exactly what code to insert if you cannot enable magic_quotes_gpc read <a href=\"http://castlecops.com/postlite2543-.html\">here</a>.</FONT></TD></TR>"
                        . "</TABLE><BR>";
	}

	if (file_exists("modules/WebMail/mailattach.php")) {
		echo "<H2>WARNING! WARNING! WARNING! Your PHP-Nuke Site is open to a WebMail Attack!</H2>"
                        . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\" bgcolor=#660000>"
                        . "<TR bgcolor=#990000><TD><FONT COLOR=#FFCC00>path/filename</FONT></TD><TD><FONT COLOR=#FFCC00>Reason For Vulnerability</FONT></TD></TR>"
                        . "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>modules/WebMail/mailattach.php</FONT></TD><TD><FONT COLOR=#FFFFFF>Highly advised by Francisco Burzi (nukelite) and Nuke Cops, this file should be removed completely from your system regardless of WebMail activation.  This file can be used to copy any file on your system and make it available for download.  You don't want your config.php file downloaded do you? Remove this file immediately!</FONT></TD></TR>"
                        . "</TABLE><BR>";
	}

	// nukesql.php check added by recommendation from chatserv
        if (file_exists("nukesql.php")) {
                echo "<H2>WARNING! WARNING! WARNING! Your PHP-Nuke Database Tables are at Risk!</H2>"
                        . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\" bgcolor=#660000>"
                        . "<TR bgcolor=#990000><TD><FONT COLOR=#FFCC00>path/filename</FONT></TD><TD><FONT COLOR=#FFCC00>Reason For Vulnerability</FONT></TD></TR>"
                        . "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>nukesql.php</FONT></TD><TD><FONT COLOR=#FFFFFF>This Web Browser based tables installer should have been deleted after it was used. This file can be used to run a Replace Tables which will empty your site's database tables, thus allowing anyone to restart your site and become a Superuser. You wouldn't want to have to start all over again do you? Remove this file immediately!</FONT></TD></TR>"
                        . "</TABLE><BR>";
        }

        if ($NCVersion_Num < "7.8") {
                echo "<H2>WARNING! WARNING! WARNING! Your PHP-Nuke CMS Is Old!</H2>"
                        . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\" bgcolor=#660000>"
                        . "<TR bgcolor=#990000><TD><FONT COLOR=#FFCC00>Your Version</FONT></TD><TD><FONT COLOR=#FFCC00>Reason For Vulnerability</FONT></TD></TR>"
                        . "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>$NCVersion_Num</FONT></TD><TD><FONT COLOR=#FFFFFF>PHP-Nuke, with each new release (currently at 7.8, fixes vulnerabilities and exploits that older versions are susceptible to.  This is a general alert for you to be aware that running older PHP-Nuke (or its ports) versions may leave it open to such attacks.  It is your choice whether to upgrade or not to the newest version (regardless of status: gold, release candidate, or beta).  But if you do decide to upgrade, for your sake make sure you backup 100% your MySQL database and all of your filesystem files.</FONT></TD></TR>"
                        . "</TABLE><BR>";
        }

        if ($NCFver < ".0.17") {
                echo "<H2>WARNING! WARNING! WARNING! Your phpbb2 forums are at Risk!</H2>"
                        . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\" bgcolor=#660000>"
                        . "<TR bgcolor=#990000><TD><FONT COLOR=#FFCC00>Version</FONT></TD><TD><FONT COLOR=#FFCC00>Reason For Vulnerability</FONT></TD></TR>"
                        . "<TR bgcolor=#CC0000><TD><FONT COLOR=#FFFFFF>2$NCFver</FONT></TD><TD><FONT COLOR=#FFFFFF>The phpBB group at <a href=\"http://phpbb.com\">phpBB.com</a> frequently update their forums software to eliminate known vulnerabilities and exploits.  Analyzer has found that your forums port is not the newest release: <b>2.0.17</b>.  By not staying current in phpBB upgrades you leave your forums open to attack.  The choice to upgrade, backup, or stay at current version is 100% completely yours, all we have done is alerted you to it.</FONT></TD></TR>"
                        . "</TABLE><BR>";
        }


}

function configconn() {
	global $prefix, $user_prefix, $dbtype, $dbname, $dbuname, $dbpass, $dbhost, $Default_Theme, $language, $locale, $Version_Num, $sitekey, $NCsitename, $NCVersion_Num, $SERVER_NAME, $NClanguage, $NClocale, $NCsitekey, $dbi;

	if ($SERVER_NAME != "castlecops.com") {
        	echo "<B>Your config.php values</B>"
	        . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
        	. "<TR bgcolor=#FFCC66><TD>Type</TD><TD>Value</TD></TR>"
	        . "<TR bgcolor=#FFFF99>"
        	. "<TD>dbhost</TD><TD>$dbhost</TD>"
	        . "</TR>"
        	. "<TR bgcolor=#FFFF99>"
	        . "<TD>dbname</TD><TD>$dbname</TD>"
        	. "</TR>"
		. "<TR bgcolor=#FFFF99>"
        	. "<TD>dbuname</TD><TD>$dbuname</TD>"
	        . "</TR>"
        	. "<TR bgcolor=#FFFF99>"
	        . "<TD>prefix</TD><TD>$prefix</TD>"
        	. "</TR>"
	        . "<TR bgcolor=#FFFF99>"
        	. "<TD>user_prefix</TD><TD>$user_prefix</TD>"
	        . "</TR>"
        	. "<TR bgcolor=#FFFF99>"
	        . "<TD>dbtype</TD><TD>$dbtype</TD>"
        	. "</TR>"
	        . "</TABLE>"
        	. "<BR>";
	}
}

// Begin Raven's code block contribution from Nuke Cops

function my_gd_info() { 
      $array = Array( 
                      "GD Version" => "", 
                      "FreeType Support"=> 0, 
                      "FreeType Support" => 0, 
                      "FreeType Linkage" => "", 
                      "T1Lib Support" => 0, 
                      "GIF Read Support" => 0, 
                      "GIF Create Support" => 0, 
                      "JPG Support" => 0, 
                      "PNG Support" => 0, 
                      "WBMP Support" => 0, 
                      "XBM Support" => 0 
      ); 
      $gif_support = 0; 

      ob_start(); 
      eval("phpinfo();"); 
      $info = ob_get_contents(); 
      ob_end_clean(); 

      foreach(explode("\n", $info) as $line) { 
          if(strpos($line, "GD Version")!==false) 
              $array["GD Version"] = trim(str_replace("GD Version", "", strip_tags($line))); 
          if(strpos($line, "FreeType Support")!==false) 
              $array["FreeType Support"] = trim(str_replace("FreeType Support", "", strip_tags($line))); 
          if(strpos($line, "FreeType Linkage")!==false) 
              $array["FreeType Linkage"] = trim(str_replace("FreeType Linkage", "", strip_tags($line))); 
          if(strpos($line, "T1Lib Support")!==false) 
              $array["T1Lib Support"] = trim(str_replace("T1Lib Support", "", strip_tags($line))); 
          if(strpos($line, "GIF Read Support")!==false) 
              $array["GIF Read Support"] = trim(str_replace("GIF Read Support", "", strip_tags($line))); 
          if(strpos($line, "GIF Create Support")!==false) 
              $array["GIF Create Support"] = trim(str_replace("GIF Create Support", "", strip_tags($line))); 
          if(strpos($line, "GIF Support")!==false) 
              $gif_support = trim(str_replace("GIF Support", "", strip_tags($line))); 
          if(strpos($line, "JPG Support")!==false) 
              $array["JPG Support"] = trim(str_replace("JPG Support", "", strip_tags($line))); 
          if(strpos($line, "PNG Support")!==false) 
              $array["PNG Support"] = trim(str_replace("PNG Support", "", strip_tags($line))); 
          if(strpos($line, "WBMP Support")!==false) 
              $array["WBMP Support"] = trim(str_replace("WBMP Support", "", strip_tags($line))); 
          if(strpos($line, "XBM Support")!==false) 
              $array["XBM Support"] = trim(str_replace("XBM Support", "", strip_tags($line))); 
      } 

      if($gif_support==="enabled") { 
          $array["GIF Read Support"]   = 1; 
          $array["GIF Create Support"] = 1; 
      } 

      if($array["FreeType Support"]==="enabled"){ 
          $array["FreeType Support"] = 1;    } 

      if($array["T1Lib Support"]==="enabled") 
          $array["T1Lib Support"] = 1; 

      if($array["GIF Read Support"]==="enabled"){ 
          $array["GIF Read Support"] = 1;    } 

      if($array["GIF Create Support"]==="enabled") 
          $array["GIF Create Support"] = 1; 

      if($array["JPG Support"]==="enabled") 
          $array["JPG Support"] = 1; 

      if($array["PNG Support"]==="enabled") 
          $array["PNG Support"] = 1; 

      if($array["WBMP Support"]==="enabled") 
          $array["WBMP Support"] = 1; 

      if($array["XBM Support"]==="enabled") 
          $array["XBM Support"] = 1; 

      return $array; 
} 

// End Raven's Contribution

function gdchk() {
	global $prefix, $user_prefix, $dbtype, $dbname, $dbuname, $dbpass, $dbhost, $Default_Theme, $language, $locale, $Version_Num, $sitekey;
        global $NCsitename, $NCVersion_Num, $SERVER_NAME, $NClanguage, $NClocale, $NCsitekey, $dbi;

        if (extension_loaded("gd")) {
                if (phpversion() >= "4.3.0") {
                        $gi = gd_info();
                        $gi1 = $gi['GD Version'];
                        $gi2 = $gi['FreeType Support'];
                        if ($gi2) { $gi2 = "Yes"; } else { $gi2 = "No"; }
                        $gi3 = $gi['T1Lib Support'];
                        if ($gi3) { $gi3 = "Yes"; } else { $gi3 = "No"; }
                        $gi4 = $gi['GIF Read Support'];
                        if ($gi4) { $gi4 = "Yes"; } else { $gi4 = "No"; }
                        $gi5 = $gi['GIF Create Support'];
                        if ($gi5) { $gi5 = "Yes"; } else { $gi5 = "No"; }
                        $gi6 = $gi['JPG Support'];
                        if ($gi6) { $gi6 = "Yes"; } else { $gi6 = "No"; }
                        $gi7 = $gi['PNG Support'];
                        if ($gi7) { $gi7 = "Yes"; } else { $gi7 = "No"; }
                        $gi8 = $gi['WBMP Support'];
                        if ($gi8) { $gi8 = "Yes"; } else { $gi8 = "No"; }
                        $gi9 = $gi['XBM Support'];
                        if ($gi9) { $gi9 = "Yes"; } else { $gi9 = "No"; }

// Begin Raven's code block contribution

		} else { 
                  $gi = my_gd_info(); 
                        $gi1 = $gi['GD Version']; 
                        $gi2 = $gi['FreeType Support']; 
                        if ($gi2) { $gi2 = "Yes"; } else { $gi2 = "No"; } 
                        $gi3 = $gi['T1Lib Support']; 
                        if ($gi3) { $gi3 = "Yes"; } else { $gi3 = "No"; } 
                        $gi4 = $gi['GIF Read Support']; 
                        if ($gi4) { $gi4 = "Yes"; } else { $gi4 = "No"; } 
                        $gi5 = $gi['GIF Create Support']; 
                        if ($gi5) { $gi5 = "Yes"; } else { $gi5 = "No"; } 
                        $gi6 = $gi['JPG Support']; 
                        if ($gi6) { $gi6 = "Yes"; } else { $gi6 = "No"; } 
                        $gi7 = $gi['PNG Support']; 
                        if ($gi7) { $gi7 = "Yes"; } else { $gi7 = "No"; } 
                        $gi8 = $gi['WBMP Support']; 
                        if ($gi8) { $gi8 = "Yes"; } else { $gi8 = "No"; } 
                        $gi9 = $gi['XBM Support']; 
                        if ($gi9) { $gi9 = "Yes"; } else { $gi9 = "No"; } 
                } 

// End Raven's code block contribution

                        ?>
                        <B>GD Library Information</B>
                        <TABLE cellspacing="1" cellpadding="3" cellspacing="2" border="1">
                        <TR bgcolor=#FFCC66><TD>Type</TD><TD>Value</TD></TR>
                        <TR bgcolor=#FFFF99>
                        <TD>GD Version</TD><TD><?php echo $gi1; ?></TD>
                        </TR>
                        <TR bgcolor=#FFFF99>
                        <TD>FreeType Support</TD><TD><?php echo $gi2; ?></TD>
                        </TR>
                        <TR bgcolor=#FFFF99>
                        <TD>T1Lib Support</TD><TD><?php echo $gi3; ?></TD>
                        </TR>
                        <TR bgcolor=#FFFF99>
                        <TD>GIF Read Support</TD><TD><?php echo $gi4; ?></TD>
                        </TR>
                        <TR bgcolor=#FFFF99>
                        <TD>GIF Create Support</TD><TD><?php echo $gi5; ?></TD>
                        </TR>
                        <TR bgcolor=#FFFF99>
                        <TD>JPG Support</TD><TD><?php echo $gi6; ?></TD>
                        </TR>
                        <TR bgcolor=#FFFF99>
                        <TD>PNG Support</TD><TD><?php echo $gi7; ?></TD>
                        </TR>
                        <TR bgcolor=#FFFF99>
                        <TD>WBMP Support</TD><TD><?php echo $gi8; ?></TD>
                        </TR>
                        <TR bgcolor=#FFFF99>
                        <TD>XBM Support</TD><TD><?php echo $gi9; ?></TD>
                        </TR>
                        </TABLE><BR>
                        <?php
	        } else {
        	        ?>
                	<B>GD Library Information</B>
	                <TABLE cellspacing="1" cellpadding="3" cellspacing="2" border="1">
        	        <TR bgcolor=#FFCC66><TD>Extension Status</TD></TR>
                	<TR bgcolor=#FFFF99>
	                <TD>Not-Loaded</TD>
        	        </TR>
                	</TABLE><BR>
	                <?php
	        }
}

function verchk() {
	global $sitename, $prefix, $user_prefix, $dbtype, $dbname, $dbuname, $dbpass, $dbhost, $Default_Theme, $language, $locale, $Version_Num, $sitekey, $NCsitename, $NCVersion_Num, $SERVER_NAME, $NClanguage, $NClocale, $NCsitekey, $dbi, $NCDefault_Theme, $NCFver;

	$sql = "DESCRIBE ".$prefix."_config";
	$NCresult = mysql_num_rows(mysql_query($sql));

	if ($NCresult > 2) {
		$sql = "select sitename, Default_Theme, Version_Num, locale, language from ".$prefix."_config";
		$result = mysql_query($sql);
			if (mysql_num_rows($result)) {
				while (list($sitename, $Default_Theme, $Version_Num, $locale, $language) = mysql_fetch_row($result)) {
				$NCsitename = $sitename;
				$NCDefault_Theme = $Default_Theme;
				$NCVersion_Num = $Version_Num;
				$NClocale = $locale;
				$NClanguage = $language;
				}
			} 
		mysql_free_result($result);
	} elseif ($NCresult = 2) { 
		$sql = "select config_value from ".$prefix."_config where config_name='sitename'";
		$result = mysql_query($sql);
		if (mysql_num_rows($result)) {
		        while (list($config_value) = mysql_fetch_row($result)) {
                	        $NCsitename = $config_value;
			}
                }
		mysql_free_result($result);
		$sql = "select config_value from ".$prefix."_config where config_name='default_lang'";
		$result = mysql_query($sql);
		if (mysql_num_rows($result)) {
			while (list($config_value) = mysql_fetch_row($result)) {
				$NClanguage = $config_value;
			}
		}
		mysql_free_result($result);
	}

	$sqlNew = "select config_value from ".$prefix."_config where config_name='version'";
	$resultNew = mysql_query($sqlNew);
	if ($resultNew) {
		list($config_value) = mysql_fetch_row(mysql_query($sqlNew));
			$NCFver = $config_value;
	} else {
		$sqlToo = "select config_value from ".$prefix."_bbconfig where config_name='version'";
		$resultToo = mysql_query($sqlToo);
		if ($resultToo) {
			list($config_value) = mysql_fetch_row(mysql_query($sqlToo));
			$NCFver = $config_value;
		}
	}
}

function bulk() {
	global $prefix, $user_prefix, $dbtype, $dbname, $dbuname, $dbpass, $dbhost, $Default_Theme, $language, $locale, $Version_Num, $sitekey, $NCsitename, $NCVersion_Num, $SERVER_NAME, $NClanguage, $NClocale, $NCsitekey, $dbi, $NCDefault_Theme, $NCFver; 

	$sql = "select bc.config_value, bt.style_name, bt.template_name from ".$prefix."_bbthemes bt, ".$prefix."_bbconfig bc where bc.config_name='default_style' and bc.config_value=bt.themes_id";
	$result = mysql_query($sql);
	if ($result<1) { 
		$sql = "select bc.config_value, bt.style_name, bt.template_name from ".$prefix."_themes bt, ".$prefix."_config bc where bc.config_name='default_style' and bc.config_value=bt.themes_id";
		$result = mysql_query($sql);
	}

        echo "<B>MySQL Connection Transcript for $NCsitename</B>"
        . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
        . "<TR bgcolor=#FFCC66><TD>Destination</TD><TD>Result</TD></TR>"
	. "<TR bgcolor=#FFFF99>"
        . "<TD>MySQL Health Check</TD><TD>Successful</TD>"
        . "</TR>";

	if ($SERVER_NAME != "castlecops.com") {
		echo "<TR bgcolor=#FFFF99>"
	        . "<TD>MySQL Server: <I>$dbhost</I></TD><TD>Successful</TD>"
        	. "</TR>"
		. "<TR bgcolor=#FFFF99>"
		. "<TD>MySQL Datbase: <I>$dbname</I></TD><TD>Successful</TD>"
		. "</TR>"
        	. "<TR bgcolor=#FFFF99>"
	        . "<TD>MySQL Username: <I>$dbuname</I></TD><TD>Successful</TD>"
        	. "</TR>"
	        . "</TABLE>"
        	. "<BR>";
	} else {
		echo "</TABLE><BR>";
	}

        if ($SERVER_NAME != "castlecops.com") {
	        echo "<B>PHP Specific Values for $NCsitename</B>"
        	. "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
	        . "<TR bgcolor=#FFCC66><TD>Category</TD><TD>Value</TD></TR>"
        	. "<TR bgcolor=#FFFF99><TD>PHP Version</TD><TD>". phpversion() ."</TD></TR>"
		. "</TABLE><BR>";
	}

	if ($SERVER_NAME != "castlecops.com") {
        	if (phpversion() >= "4.0.2") {
                	echo "<B>Operating System Data for $NCsitename</B>"
	                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
        	        . "<TR bgcolor=#FFCC66><TD>Type</TD><TD>Value</TD></TR>"
                	. "<TR bgcolor=#FFFF99>"
	                . "<TD>OS Type</TD><TD>".php_uname()."</TD>"
        	        . "</TR>"
	                . "</TABLE>"
                	. "<BR>";
        	} elseif (phpversion() >= "3.0.10") {
			$pu = posix_uname();
			echo "<B>Operating System Data for $NCsitename (Values may be blank)</B>"
                        . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
                        . "<TR bgcolor=#FFCC66><TD>Type</TD><TD>Value</TD></TR>"
                        . "<TR bgcolor=#FFFF99>"
                        . "<TD>OS</TD><TD>".$pu['sysname']."</TD>"
                        . "</TR>"
                        . "<TR bgcolor=#FFFF99>"
                        . "<TD>Node Name</TD><TD>".$pu['nodename']."</TD>"
                        . "</TR>"
                        . "<TR bgcolor=#FFFF99>"
                        . "<TD>Build Release</TD><TD>".$pu['release']."</TD>"
                        . "</TR>"
                        . "<TR bgcolor=#FFFF99>"
                        . "<TD>Version</TD><TD>".$pu['version']."</TD>"
                        . "</TR>"
                        . "<TR bgcolor=#FFFF99>"
                        . "<TD>Machine Platform</TD><TD>".$pu['machine']."</TD>"
                        . "</TR>"
                        . "<TR bgcolor=#FFFF99>"
                        . "<TD>Domain Name</TD><TD>".$pu['domainname']."</TD>"
                        . "</TR>"
                        . "</TABLE>"
                        . "<BR>";
                }
	}

	if ($SERVER_NAME != "castlecops.com") {
		if (phpversion() >= "4.0.5") {
			$mysql_server_info = mysql_get_server_info();
	        	echo "<B>MySQL Specific Values for $NCsitename</B>"
	        	. "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
			. "<TR bgcolor=#FFCC66><TD>Category</TD><TD>Value</TD></TR>"
	        	. "<tr bgcolor=#FFFF99><td>"
	        	. "Server Version</TD><TD>". $mysql_server_info .""
		        . "</td></tr>"
                	. "<tr bgcolor=#FFFF99><td>"
	                . "Client Version</TD><TD>". mysql_get_client_info() .""
        	        . "</td></tr>"
                	. "<tr bgcolor=#FFFF99><td>"
	                . "Host Information</TD><TD>". mysql_get_host_info() .""
        	        . "</td></tr>"
                	. "<tr bgcolor=#FFFF99><td>"
	                . "Protocol Information</TD><TD>". mysql_get_proto_info() .""
        	        . "</td></tr>"
        		. "</table><BR>";
		}	
	}

        if ($result) {
                echo "<B>phpBB2 Port Values for $NCsitename</B>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
                . "<TR bgcolor=#FFCC66><TD>Theme ID</TD><TD>Style Name</TD><TD>Template Name</TD><TD>Version</TD></TR>";

                while (list($config_value, $style_name, $template_name) = mysql_fetch_row($result)) {
                        $NCconfig_value =  $config_value;
                        $NCstyle_name = $style_name;
                        $NCtemplate_name =$template_name;
                }
                mysql_free_result($result);

                echo "<TR bgcolor=#FFFF99>"
                . "<TD>$NCconfig_value</TD><TD>$NCstyle_name</TD><TD>$NCtemplate_name</TD><TD>2$NCFver</TD>"
                . "</TR>"
                . "</TABLE>"
                . "<BR>";
        }

        echo "<B>PHP-Nuke Values for $NCsitename</B>"
        . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
        . "<TR bgcolor=#FFCC66><TD>Default_Theme</TD><TD>Version</TD><TD>Locale</TD><TD>Language</TD></TR>"
        . "<TR bgcolor=#FFFF99>"
        . "<TD>$NCDefault_Theme</TD><TD>$NCVersion_Num</TD><TD>$NClocale</TD><TD>$NClanguage</TD>"
        . "</TR>"
        . "</TABLE>"
        . "<BR>";

}


function listdbs() {
        global $prefix, $user_prefix, $dbtype, $dbname, $dbuname, $dbpass, $dbhost, $Default_Theme, $language, $locale, $Version_Num, $sitekey;
        global $NCsitename, $NCVersion_Num, $SERVER_NAME, $NClanguage, $NClocale, $NCsitekey, $dbi;

	if ($SERVER_NAME != "castlecops.com") {
	        $db_list = mysql_list_dbs($dbi);
	        echo "<B>All Available MySQL Databases</B>"
	        . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
        	. "<TR bgcolor=#FFCC66><TD>Database Name</TD></TR>"
	        . "</TR>";
        	while ($row = mysql_fetch_object($db_list)) {
			if ($row->Database == $dbname) {
				echo "<TR bgcolor=#FFFF99>"
        			. "<TD><I>" . $row->Database . "</I></TD>"
				. "</TR>";
			} else {
				echo "<TR bgcolor=#FFFF99>"
                                . "<TD>" . $row->Database . "</TD>"
                                . "</TR>";
			}
        	}
		echo "</TABLE><BR>";
	}
}


function modchk() {
        global $prefix, $user_prefix, $dbtype, $dbname, $dbuname, $dbpass, $dbhost, $Default_Theme, $language, $locale, $Version_Num, $sitekey;
        global $NCsitename, $NCVersion_Num, $SERVER_NAME, $NClanguage, $NClocale, $NCsitekey, $dbi;

	$sql = "select main_module from ".$prefix."_main";
	$result = mysql_query($sql);
	while (list($main_module) = mysql_fetch_row($result)) {
		$NCmain_module = $main_module;
	}
	mysql_free_result($result);

	$sql = "select title, active, view from ".$prefix."_modules group by title asc";
	$result = mysql_query($sql);
        if ($result) {
                echo "<B>Module Data for $NCsitename</B>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
                . "<TR bgcolor=#FFCC66><TD>Module</TD><TD>Active Code</TD><TD>View Code</TD></TR>";

                while (list($title, $active, $view) = mysql_fetch_row($result)) {
                        $NCtitle = $title;
                        $NCactive = $active;
                        $NCview = $view;
			if ($view > '2') {
	            		$NCview = $NCview - 2;
				$sql2 = "select groupName from ".$prefix."_users_groups where groupID = $NCview";
		                $result2 = mysql_query($sql2);
                		if ($result2) {
		                        while (list($groupName) = mysql_fetch_row($result2)) {
                	                	$NCview = "$groupName";
                        		}
		                        mysql_free_result($result2);
                		}
			} elseif ($view) { 
				$NCview = "Registered Users"; 
			} elseif ($view == '2') { 
				$NCview = "Administrators"; 
			} else { 
				$NCview = "All Visitors"; 
			}
			if ($NCactive) { $NCactive = "active"; } else { $NCactive = "<font color=\"maroon\"><strong>disabled</strong></color>"; }
			if ($NCmain_module == $NCtitle) {$NCtitle = "<B>$NCtitle</B>";}
                        echo "<TR bgcolor=#FFFF99><TD>$NCtitle</TD><TD>$NCactive</TD><TD>$NCview</TD></TR>";
                }
		mysql_free_result($result);
                echo "</TABLE>"
                . "<BR>";

        }
}

function blkchk() {
        global $prefix, $user_prefix, $dbtype, $dbname, $dbuname, $dbpass, $dbhost, $Default_Theme, $language, $locale, $Version_Num, $sitekey;
        global $NCsitename, $NCVersion_Num, $SERVER_NAME, $NClanguage, $NClocale, $NCsitekey, $dbi;

	// Following code for Block Data was supplied by chatserv but I adapted it to work with NSN_Groups permissions if exist.
        $sql = "select title, active, view from ".$prefix."_blocks group by title asc";
        $result = mysql_query($sql);
        if ($result) {
                echo "<B>Block Data for $NCsitename</B>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
                . "<TR bgcolor=#FFCC66><TD>Block</TD><TD>Active Code</TD><TD>View Code</TD></TR>";
                 while (list($title, $active, $view) = mysql_fetch_row($result)) {
                        $NCTitle = $title;
                        $NCActive = $active;
                        $NCView = $view;
			if ($view > '3') {
                                $NCView = $NCView - 3;
                                $sql2 = "select groupName from ".$prefix."_users_groups where groupID = $NCView";
                                $result2 = mysql_query($sql2);
                                if ($result2) {
                                        while (list($groupName) = mysql_fetch_row($result2)) {
                                                $NCView = "$groupName";
                                        }
                                        mysql_free_result($result2);
                                }
			} elseif ($view == '1') {
                                $NCView = "Registered Users";
                        } elseif ($view == '2') {
                                $NCView = "Administrators";
			} elseif ($view == '3') {
				$NCView = "Anonymous";
                        } else {
                                $NCView = "All Visitors";
                        }
                        if ($NCActive) { $NCActive = "active"; } else { $NCActive = "<font color=\"maroon\"><strong>disabled</strong></color>"; }
                        echo "<TR bgcolor=#FFFF99><TD>$NCTitle</TD><TD>$NCActive</TD><TD>$NCView</TD></TR>";
                }
                mysql_free_result($result);
                echo "</TABLE>"
                . "<BR>";
        }
}

function ranks() {
        global $prefix, $user_prefix, $dbtype, $dbname, $dbuname, $dbpass, $dbhost, $Default_Theme, $language, $locale, $Version_Num, $sitekey;
        global $NCsitename, $NCVersion_Num, $SERVER_NAME, $NClanguage, $NClocale, $NCsitekey, $dbi;

        $sql = "select u.username, u.user_id, r.rank_title, u.user_level from ".$prefix."_users u, ".$prefix."_bbranks r where r.rank_id=u.user_rank";
        $result = mysql_query($sql);
        if ($result) {
                echo "<B>Member Special Ranks for $NCsitename</B>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
                . "<TR bgcolor=#FFCC66><TD>phpBB2 Rank Title</TD><TD>PHP-Nuke Rank Level</TD><TD>Member Name</TD><TD>Member UID</TD></TR>";

                while (list($username, $user_id, $rank_title, $user_level) = mysql_fetch_row($result)) {
                        $NCuname = $username;
                        $NCuid = $user_id;
                        $NCrank_title = $rank_title;
                        $NCuser_level = $user_level;
                        if ($NCuser_level == '2') {$NCuser_level="2: Admin/Author";} elseif ($NCuser_level == '3') {$NCuser_level="3: Forum Moderator";} else {$NCuser_level="1: Regular Member";}
                        echo "<TR bgcolor=#FFFF99><TD>$NCrank_title</TD><TD>$NCuser_level</TD><TD>$NCuname</TD><TD>$NCuid</TD></TR>";
                }
                mysql_free_result($result);
                echo "</TABLE>"
                . "<BR>";

        }

	$sql = "select u.uname, u.uid, r.rank_title, u.user_level from ".$prefix."_users u, ".$prefix."_bbranks r where r.rank_id=u.user_rank";
	$result = mysql_query($sql);
	if ($result) {
	        echo "<B>Member Special Ranks for $NCsitename</B>"
	        . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
        	. "<TR bgcolor=#FFCC66><TD>phpBB2 Rank Title</TD><TD>PHP-Nuke Rank Level</TD><TD>Member Name</TD><TD>Member UID</TD></TR>";

		while (list($uname, $uid, $rank_title, $user_level) = mysql_fetch_row($result)) {
        		$NCuname = $uname;
	        	$NCuid = $uid;
        		$NCrank_title = $rank_title;
			$NCuser_level = $user_level;
			if ($NCuser_level == '2') {$NCuser_level="2: Admin/Author";} elseif ($NCuser_level == '3') {$NCuser_level="3: Forum Moderator";} else {$NCuser_level="1: Regular Member";}
	        	echo "<TR bgcolor=#FFFF99><TD>$NCrank_title</TD><TD>$NCuser_level</TD><TD>$NCuname</TD><TD>$NCuid</TD></TR>";
		}
		mysql_free_result($result);
	        echo "</TABLE>"
        	. "<BR>";

	}
}

function admins() {
        global $prefix, $user_prefix, $dbtype, $dbname, $dbuname, $dbpass, $dbhost, $Default_Theme, $language, $locale, $Version_Num, $sitekey;
        global $NCsitename, $NCVersion_Num, $SERVER_NAME, $NClanguage, $NClocale, $NCsitekey, $dbi;

	$sql = "select uname, uid, user_level from ".$prefix."_users where user_level = '2'";
	$result = mysql_query($sql);
        if ($result) {
                echo "<B>Catch-All Administrators for $NCsitename</B>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
                . "<TR bgcolor=#FFCC66><TD>PHP-Nuke Rank Level</TD><TD>Member Name</TD><TD>Member UID</TD></TR>";

                while (list($uname, $uid, $user_level) = mysql_fetch_row($result)) {
                        $NCuname = $uname;
                        $NCuid = $uid;
                        $NCuser_level = $user_level;
                        if ($NCuser_level == '2') {$NCuser_level="2: Admin/Author";}
                        echo "<TR bgcolor=#FFFF99><TD>$NCuser_level</TD><TD>$NCuname</TD><TD>$NCuid</TD></TR>";
                }
                mysql_free_result($result);
                echo "</TABLE>"
                . "<BR>";

        }

        $sql = "select username, user_id, user_level from ".$prefix."_users where user_level = '2'";
        $result = mysql_query($sql);
        if ($result) {
                echo "<B>Catch-All Administrators for $NCsitename</B>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
                . "<TR bgcolor=#FFCC66><TD>PHP-Nuke Rank Level</TD><TD>Member Name</TD><TD>Member UID</TD></TR>";

                while (list($username, $user_id, $user_level) = mysql_fetch_row($result)) {
                        $NCuname = $username;
                        $NCuid = $user_id;
                        $NCuser_level = $user_level;
                        if ($NCuser_level == '2') {$NCuser_level="2: Admin/Author";}
                        echo "<TR bgcolor=#FFFF99><TD>$NCuser_level</TD><TD>$NCuname</TD><TD>$NCuid</TD></TR>";
                }
                mysql_free_result($result);
                echo "</TABLE>"
                . "<BR>";

        }
}

function mods() {
        global $prefix, $user_prefix, $dbtype, $dbname, $dbuname, $dbpass, $dbhost, $Default_Theme, $language, $locale, $Version_Num, $sitekey;
        global $NCsitename, $NCVersion_Num, $SERVER_NAME, $NClanguage, $NClocale, $NCsitekey, $dbi;

        $sql = "select uname, uid, user_level from ".$prefix."_users where user_level = '3'";
        $result = mysql_query($sql);
        if ($result) {
                echo "<B>Catch-All Moderators for $NCsitename</B>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
                . "<TR bgcolor=#FFCC66><TD>PHP-Nuke Rank Level</TD><TD>Member Name</TD><TD>Member UID</TD></TR>";

                while (list($uname, $uid, $user_level) = mysql_fetch_row($result)) {
                        $NCuname = $uname;
                        $NCuid = $uid;
                        $NCuser_level = $user_level;
                        if ($NCuser_level == '3') {$NCuser_level="3: Forum Moderator";}
                        echo "<TR bgcolor=#FFFF99><TD>$NCuser_level</TD><TD>$NCuname</TD><TD>$NCuid</TD></TR>";
                }
                mysql_free_result($result);
                echo "</TABLE>"
                . "<BR>";

        }

        $sql = "select username, user_id, user_level from ".$prefix."_users where user_level = '3'";
        $result = mysql_query($sql);
        if ($result) {
                echo "<B>Catch-All Moderators for $NCsitename</B>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
                . "<TR bgcolor=#FFCC66><TD>PHP-Nuke Rank Level</TD><TD>Member Name</TD><TD>Member UID</TD></TR>";

                while (list($username, $user_id, $user_level) = mysql_fetch_row($result)) {
                        $NCuname = $username;
                        $NCuid = $user_id;
                        $NCuser_level = $user_level;
                        if ($NCuser_level == '3') {$NCuser_level="3: Forum Moderator";}
                        echo "<TR bgcolor=#FFFF99><TD>$NCuser_level</TD><TD>$NCuname</TD><TD>$NCuid</TD></TR>";
                }
                mysql_free_result($result);
                echo "</TABLE>"
                . "<BR>";

        }
}

function tables() {
        global $prefix, $user_prefix, $dbtype, $dbname, $dbuname, $dbpass, $dbhost, $Default_Theme, $language, $locale, $Version_Num, $sitekey;
        global $NCsitename, $NCVersion_Num, $SERVER_NAME, $NClanguage, $NClocale, $NCsitekey, $dbi;

	$result = mysql_list_tables($dbname);
	if (!$result) {
        	print "DB Error, could not list tables\n";
	        print 'MySQL Error: ' . mysql_error();
	}
	if ($result) {
                echo "<B>PHP-Nuke Show Tables for $NCsitename</B>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
                . "<TR bgcolor=#FFCC66><TD>Number</TD><TD>Table Name</TD><TD>Fields</TD><TD>Records</TD></TR>";
		$i=1;
	        while ($row = mysql_fetch_row($result)) {
			echo "<TR bgcolor=#FFFF99><TD align=center>$i</TD><TD>$row[0]</TD>";
			$sql2 = "DESCRIBE $row[0]";
			$result2 = mysql_num_rows(mysql_query($sql2));
			if ($result2) { 
				echo "<TD align=right>$result2</TD>"; 
			} else { 
				$result2 = "0"; 
			}
			$sql3 = "SELECT COUNT(*) as nccount FROM ".$row[0];
			$result3 = mysql_query($sql3);
			while (list($nccount) = mysql_fetch_row($result3)) {
				if ($nccount) {
					echo "<TD align=right>$nccount</TD>";
				} else {
					$nccount = 0;
					echo "<TD align=right>$nccount</TD>";
				}
			}
                        $i++;
		}
        	echo "</TR></TABLE>"
	        . "<BR>";
	}
}

function phpini() {
        global $prefix, $user_prefix, $dbtype, $dbname, $dbuname, $dbpass, $dbhost, $Default_Theme, $language, $locale, $Version_Num, $sitekey;
        global $NCsitename, $NCVersion_Num, $SERVER_NAME, $NClanguage, $NClocale, $NCsitekey, $dbi;

	if ($SERVER_NAME != "castlecops.com") {
		if (phpversion() < "4.2.0") {
			echo "<B>php.ini Configuration for $NCsitename</B>"
	                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
			. "<tr bgcolor=#FFFF99><td><pre>";
			print_r(parse_ini_file(get_cfg_var('cfg_file_path')));
			echo "</pre></td></tr>"
        	        . "</table><BR>";
		} else {
			$cfgOut = ini_get_all();
			echo "<B>php.ini Configuration for $NCsitename</B>"
		        . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
        		. "<TR bgcolor=#FFCC66><TD COLSPAN=3>Global / Local / Access : php.ini data</TD></TR>"
			. "<TR bgcolor=silver><TD ALIGN=CENTER COLSPAN=3><B>The Key for [access]</B></TD></TR>"
			. "<TR bgcolor=silver><TD>Constant</TD><TD>Value</TD><TD>Meaning</TD></TR>"
			. "<TR bgcolor=silver><TD>PHP_INI_USER</TD><TD>1</TD><TD>Entry can be set in user scripts</TD></TR>"
			. "<TR bgcolor=silver><TD>PHP_INI_PERDIR</TD><TD>2</TD><TD>Entry can be set in php.ini, .htaccess, or httpd.conf</TD></TR>"
			. "<TR bgcolor=silver><TD>PHP_INI_SYSTEM</TD><TD>4</TD><TD>Entry can be set in php.ini, or httpd.conf</TD></TR>"
			. "<TR bgcolor=silver><TD>PHP_INI_ALL</TD><TD>7</TD><TD>Entry can be set anywhere</TD></TR>"
			. "<tr bgcolor=#FFFF99><td COLSPAN=3><pre>";
			print_r($cfgOut);
			echo "</pre></td></tr>"
			. "</table><BR>";
		}
	}

	if (phpversion() >= "4.3.0") {
        	 echo "<B>MySQL Status for $NCsitename</B>"
	         . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
        	 . "<tr bgcolor=#FFFF99><td><pre>";
	         print_r(mysql_stat($dbi));
        	 echo "</pre></td></tr>"
	         . "</table><BR>";
	}

}

function ncimg() {
	echo "<img src=\"http://castlecops.com/images/banaffi.gif\"><BR><BR>Analyzer brought to you by CastleCops at <a href=\"http://castlecops.com\">castlecops.com</a>.  Analyzer can be used to debug any PHP-Nuke installation or derivative including PHP-Nuke Platinum for example.<BR><BR>";
}


// begin http://www.zend.com/manual/features.error-handling.php

function errorchk() {
	error_reporting(0);

	$old_error_handler = set_error_handler("userErrorHandler");

}

function userErrorHandler ($errno, $errmsg, $filename, $linenum, $vars) {
    $dt = date("Y-m-d H:i:s (T)");

    $errortype = array (
                1   =>  "Error",
                2   =>  "Warning",
                4   =>  "Parsing Error",
                8   =>  "Notice",
                16  =>  "Core Error",
                32  =>  "Core Warning",
                64  =>  "Compile Error",
       	        128 =>  "Compile Warning",
               	256 =>  "User Error",
                512 =>  "User Warning",
       	        1024=>  "User Notice"
               	);
    $user_errors = array(E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE);
   
    $err = "<errorentry>\n";
    $err .= "\t<datetime>".$dt."</datetime>\n";
    $err .= "\t<errornum>".$errno."</errornum>\n";
    $err .= "\t<errortype>".$errortype[$errno]."</errortype>\n";
    $err .= "\t<errormsg>".$errmsg."</errormsg>\n";
    $err .= "\t<scriptname>".$filename."</scriptname>\n";
    $err .= "\t<scriptlinenum>".$linenum."</scriptlinenum>\n";

    if (in_array($errno, $user_errors))
        $err .= "\t<vartrace>".wddx_serialize_value($vars,"Variables")."</vartrace>\n";
    $err .= "</errorentry>\n\n";
    
    echo "<B><U>NCNOTICE: ".$err."</U></B><BR><BR>";

    //// error_log($err, 3, "/usr/local/php4/error.log");
    if ($errno == E_USER_ERROR)
	echo "Critical User Error: ".$err;
        //// mail("phpdev@example.com","Critical User Error",$err);
}

// end http://www.zend.com/manual/features.error-handling.php

function gfx($random_num) {
    global $prefix;
    require("config.php");
    $datekey = date("F j");
    $rcode = hexdec(md5($_SERVER[HTTP_USER_AGENT] . $sitekey . $random_num . $datekey));
    $code = substr($rcode, 2, 10);
    $image = ImageCreateFromJPEG("images/admin/code_bg.jpg");
    $text_color = ImageColorAllocate($image, 80, 80, 80);
    Header("Content-type: image/jpeg");
    ImageString ($image, 5, 12, 2, $code, $text_color);
    ImageJPEG($image, '', 75);
    ImageDestroy($image);
    die();
}

function gfx2($random_num) {
    global $prefix;
    require("config.php");
    $datekey = date("F j");
    $rcode = hexdec(md5($_SERVER[HTTP_USER_AGENT] . $sitekey . $random_num . $datekey));
    $code = substr($rcode, 2, 10);
    $image = ImageCreateFromJPEG("modules/Your_Account/images/code_bg.jpg");
    $text_color = ImageColorAllocate($image, 80, 80, 80);
    Header("Content-type: image/jpeg");
    ImageString ($image, 5, 12, 2, $code, $text_color);
    ImageJPEG($image, '', 75);
    ImageDestroy($image);
    die();
}

function gfximg() {
	global $NCsitename, $appname;
	define("_SECURITYCODE","Security Code");

	mt_srand ((double)microtime()*1000000);
	$maxran = 1000000;
	$random_num = mt_rand(0, $maxran);

	$adminbg = "images/admin/code_bg.jpg";
	$yabg = "modules/Your_Account/images/code_bg.jpg";

	if (file_exists($adminbg)) { 
		echo "&middot; Admin Security Image Found: $adminbg &middot;<BR>";
		$admintoken = 1;
	} else {
		echo "&middot; Admin Security Image <b>Not</b> Found: $adminbg &middot;<BR>";
	}

	echo "<br>";

        if (file_exists($yabg)) {
                echo "&middot; Your_Account Security Image Found: $yabg &middot;<BR>";
		$yatoken = 1;
        } else {
		echo "&middot; Your_Account Security Image <B>Not</b> Found: $yabg &middot;<BR>";
	}

	echo "<BR>";

	if (extension_loaded("gd") && $admintoken) {
		echo "<B>Security Code Graphic for $NCsitename</B>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
		. "<TR bgcolor=#FFCC66><TD>Security Code Image with Random Number</TD></TR>"
		. "<tr bgcolor=#FFFF99><td>"
		. "<center><img src='$appname?zx=gfx&random_num=$random_num' border='1' alt='"._SECURITYCODE."' title='"._SECURITYCODE."'>"
                . "</center></td></tr>"
                . "</table><BR>";
	} elseif (extension_loaded("gd") && $yatoken) {
                echo "<B>Security Code Graphic for $NCsitename</B>"
                . "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
                . "<TR bgcolor=#FFCC66><TD>Security Code Image with Random Number</TD></TR>"
                . "<tr bgcolor=#FFFF99><td>"
                . "<center><img src='$appname?zx=gfx2&random_num=$random_num' border='1' alt='"._SECURITYCODE."' title='"._SECURITYCODE."'>"
                . "</center></td></tr>"
                . "</table><BR>";
	} elseif (extension_loaded("gd")) {
		?>
                <B>GD & Security Image Data</B>
                <TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">
                <TR bgcolor=#FFCC66><TD>Status</TD></TR>
                <TR bgcolor=#FFFF99>
                <TD>GD Loaded but security images are not present.</TD>
                </TR>
                </TABLE><BR>
                <?php
	} else {
                ?>
                <B>GD Library Information</B>
                <TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">
                <TR bgcolor=#FFCC66><TD>Extension Status</TD></TR>
                <TR bgcolor=#FFFF99>
                <TD>Not-Loaded</TD>
                </TR>
                </TABLE><BR>
                <?php
	}
}

function phploc() {
	global $NCsitename, $SERVER_NAME;
	
	if ($SERVER_NAME != "castlecops.com") {
		echo "<B>php.ini location for $NCsitename</B>"
        	. "<TABLE cellspacing=\"1\" cellpadding=\"3\" cellspacing=\"2\" border=\"1\">"
	        . "<TR bgcolor=#FFCC66><TD>php.ini location</TD></TR>"
        	. "<tr bgcolor=#FFFF99><td>"
        	. get_cfg_var("cfg_file_path")
	        . "</td></tr>"
	        . "</table><BR>";
	}
}

## GZIP Disable Function

function disablegzip() {

global $prefix, $dbtype, $dbname, $dbuname, $dbpass, $dbhost, $dbi;

if ($_SERVER['SERVER_NAME'] != "castlecops.com") {
	$sql = "update ".$prefix."_bbconfig set config_value='0' where config_name='gzip_compress'";
        $result = mysql_query($sql);
	if ($result) {
		echo "GZIP Compression Setting has been disabled in Nuke Cops bbtonuke port<BR><BR>";
	} else {
	        $sql2 = "update ".$prefix."_config set config_value='0' where config_name='gzip_compress'";
		$result2 = mysql_query($sql2);
		if ($result2) {
			echo "GZIP Compression Setting has been disabled<BR><BR>";
		} else {
			echo "ERROR: Unable to disable GZIP compression<BR><BR>";
		}
	}
} else { echo "Function Disabled for Computer Cops Servers<BR><BR>"; }

}

## SMTP Test block start by disgruntledtech

function testmailstart() {
	if(!$to) { 
		echo "<b>Test Your SMTP Server</b>"; 
		echo "<form action=\"".$_SERVER['PHP_SELF']."?zx=testmail\" method=\"post\">"; 
		echo "Send To: <input type\"text\" name=\"to\"><input type=\"submit\" value=\"Send!\">"; 
		echo "</form>"; 
		echo "Notice: leaving this script online may bring unwanted intruders using it for SPAM.  Use at your own risk.<BR><BR>";
	} 
}

function family() {

?> <BR>
Visit the family of Team Cops websites:<BR><BR>
<li><a href="http://castlecops.com">castlecops.com</a>: Computer Security Portal
<BR><BR>
<?php
}

function testmail($to){ 
	if ($_SERVER['SERVER_NAME'] != "castlecops.com") {
		$message = "This is a test Message Sent from ".$_SERVER['SERVER_NAME']." initiated by a user at ".$_SERVER['REMOTE_ADDR']
			. ". \n\n"
			. "Notice: leaving this script running on your server may expose it to unwanted intruders sending SPAM"
			. ", use at your own risk!\n\n"
			. "The CastleCops Analyzer tool is available from http://castlecops.com, the premiere and official member"
			. " of the PHP-Nuke development team.  And our most excellent support and system hardening team is "
			. "available in the forums to help you any time.  They are known as the \"Elite Nukers\". "
			. " This tester was developed by our Elite Nuker: disgruntledtech.";
		mail($to, "SMTP Test Script", $message, 
		     "From: SMTP Tester\r\n" 
		    ."X-Mailer: PHP/" . phpversion()); 
		echo "<b>Sent mail to: <u>".$to;
		echo "</u></b><BR><BR>";
	}
} 

## SMTP Test block end by disgruntledtech

switch($_GET['zx']) {
	case "testmailstart":
		shorttop();
		testmailstart();
		shortend();
		break;

	case "gzipfalse":
		shorttop();
		disablegzip();
		shortend();
		break;

	case "testmail":
		shorttop();
		testmail($_POST[to]);
		shortend();
		break;

        case "ls":
		global $appname;
		head();
		ncimg();
		echo "Click <a href=\"$appname\">here</a> for Analyzer home.<BR><BR>";
                DirectoryListing();
		echo "<BR>Click <a href=\"$appname\">here</a> for Analyzer home.<BR><BR>";
		foot();
                break;
	case "logo":
		if (extension_loaded("gd")) { analyzerImage(); } else { foot(); }
		break;

	case "gfx":
        	gfx($random_num);
	        break;

        case "gfx2":
                gfx2($random_num);
                break;

	case "configconn":
                shorttop();
		configconn();
                shortend();
                break;

	case "secchk":
                shorttop();
		secchk();
                shortend();
                break;

	case "gdchk":
                shorttop();
		gdchk();
                shortend();
                break;

	case "bulk":
                shorttop();
		bulk();
                shortend();
                break;

	case "listdbs":
                shorttop();
		listdbs();
                shortend();
                break;

	case "modchk":
                shorttop();
		modchk();
                shortend();
                break;

	case "blkchk":
                shorttop();
		blkchk();
                shortend();
                break;

	case "ranks":
                shorttop();
		ranks();
                shortend();
                break;

	case "admins":
                shorttop();
		admins();
                shortend();
                break;

	case "mods":
                shorttop();
		mods();
                shortend();
                break;

	case "phpini":
		shorttop();
		phpini();
                shortend();
                break;

	case "gfximg":
		shorttop();
		gfximg();
		shortend();
                break;

	case "family":
		shorttop();
		family();
		shortend();
		break;
	
	case "tables":
		shorttop();
		tables();
		shortend();
		break;

	case "errchk":
		errorchk();
		all();
		break;
	
	case "phploc":
		shorttop();
		phploc();
		shortend();
		break;

	default:
		all();
		break;
}

function shorttop() {
                head();
                ncimg();
                main();
                menu();
                verchk();
}

function shortend() {
                foot();
}

function all(){
                head();
                ncimg();
                main();
		menu();
		verchk();
		secchk();
                configconn();
                gdchk();
                bulk();
                listdbs();
                modchk();
                blkchk();
                ranks();
                admins();
                mods();
		tables();
		phploc();
                phpini();
                gfximg();
                foot();
}

function menu() {
	global $appname;
	echo "<FORM METHOD=GET ACTION=\"$appname\">";
        echo "<SELECT NAME=\"zx\" SIZE=\"7\" onChange=\"top.location.href=this.options[this.selectedIndex].value\">";
        echo "<OPTION VALUE=\"$appname\">>> All Sections [Default] <<";
        echo "<OPTION VALUE=\"$appname?zx=configconn\">Config.php";
	echo "<OPTION VALUE=\"$appname?zx=testmailstart\">SMTP Tester";
        echo "<OPTION VALUE=\"$appname?zx=secchk\">System Security Check";
	echo "<OPTION VALUE=\"$appname?zx=gzipfalse\">Disable Forums GZIP";
        echo "<OPTION VALUE=\"$appname?zx=gdchk\">GD Library Check";
        echo "<OPTION VALUE=\"$appname?zx=bulk\">Majority of Analyzer";
        echo "<OPTION VALUE=\"$appname?zx=listdbs\">Show all Databases";
        echo "<OPTION VALUE=\"$appname?zx=modchk\">Show all Modules";
        echo "<OPTION VALUE=\"$appname?zx=blkchk\">Show all Blocks";
	echo "<OPTION VALUE=\"$appname?zx=errchk\">Show any notices [NCNOTICE]";
        echo "<OPTION VALUE=\"$appname?zx=ranks\">Show Ranks";
        echo "<OPTION VALUE=\"$appname?zx=admins\">Show Administrators";
        echo "<OPTION VALUE=\"$appname?zx=mods\">Show Moderators";
        echo "<OPTION VALUE=\"$appname?zx=phpini\">Show php.ini settings";
        echo "<OPTION VALUE=\"$appname?zx=gfximg\">Show security code image";
	echo "<OPTION VALUE=\"$appname?zx=tables\">Show tables";
	echo "<OPTION VALUE=\"$appname?zx=phploc\">Your php.ini location";
        echo "</SELECT></FORM>";
}

function foot() {
	global $appname, $total_time, $start_time, $sitename;
    	$mtime = microtime();
	$mtime = explode(" ",$mtime);
    	$mtime = $mtime[1] + $mtime[0];
    	$end_time = $mtime;
    	$total_time = ($end_time - $start_time);
    	$total_time = "Page Generation: ".substr($total_time,0,5)." seconds.";
	?>
	Notice: leaving this script online may bring unwanted intruders using it for security attacks at your server. Use at your own risk.
	<BR><BR>

	Copyright 2003-2005 <a href="http://castlecops.com"><b>CastleCops(sm)</b></a>, Paul Laudanski (AKA Zhen-Xjell), Microsoft MVP Windows-Security. <BR>
	PHP-Nuke/phpBB2 Portal Checker Version 2.0 (<a href="http://castlecops.com/">CastleCops(sm)</a>). For support visit CastleCops. <BR>
	- <a href="<?php $appname?>?zx=logo">PHP Generated Logo</a>
	<BR><BR> <?php echo "$total_time"; ?> 
	</SPAN>
	</BODY>
	</HTML>
<?php
}
?>