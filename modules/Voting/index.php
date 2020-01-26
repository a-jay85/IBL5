<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2002 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/*                                                                      */
/* ibl College Scout Module added by Spencer Cooley                    */
/* 2/2/2005                                                             */
/*                                                                      */
/************************************************************************/

if (!eregi("modules.php", $_SERVER['PHP_SELF'])) {
	die ("You can't access this file directly...");
}

require_once("mainfile.php");
$module_name = basename(dirname(__FILE__));
get_lang($module_name);
$userpage = 1;

//include("modules/$module_name/navbar.php");

function userinfo($username, $bypass=0, $hid=0, $url=0) {
    global $user, $cookie, $sitename, $prefix, $user_prefix, $db, $admin, $broadcast_msg, $my_headlines, $module_name, $useset, $subscription_url;
    $sql = "SELECT * FROM ".$prefix."_bbconfig";
    $result = $db->sql_query($sql);
    while ( $row = $db->sql_fetchrow($result) )
    {
    $board_config[$row['config_name']] = $row['config_value'];
    }
    $sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
    $result2 = $db->sql_query($sql2);
    $num = $db->sql_numrows($result2);
    $userinfo = $db->sql_fetchrow($result2);
    if(!$bypass) cookiedecode($user);
    include("header.php");

// === CODE TO INSERT ibl DEPTH CHART ===

    OpenTable();
    $teamlogo = $userinfo[user_ibl_team];

    $sql7 = "SELECT * FROM nuke_ibl_offense_sets WHERE TeamName = '$teamlogo' ORDER BY SetNumber ASC ";
    $result7 = $db->sql_query($sql7);
    $num7 = $db->sql_numrows($result7);

    $sql8 = "SELECT * FROM nuke_iblplyr WHERE teamname='$userinfo[user_ibl_team]' AND retired = '0' ORDER BY ordinal ASC ";
    $result8 = $db->sql_query($sql8);

    if ($useset == NULL)
    {
    $useset=1;
    }

    $sql9 = "SELECT * FROM nuke_ibl_offense_sets WHERE TeamName='$userinfo[user_ibl_team]' AND SetNumber='$useset'";
    $result9 = $db->sql_query($sql9);
    $row9 = $db->sql_fetchrow($result9);

    $offense_name = $row9[offense_name];
    $Slot1 = $row9[PG_Depth_Name];
    $Slot2 = $row9[SG_Depth_Name];
    $Slot3 = $row9[SF_Depth_Name];
    $Slot4 = $row9[PF_Depth_Name];
    $Slot5 = $row9[C_Depth_Name];

    $Low1 = $row9[PG_Low_Range];
    $Low2 = $row9[SG_Low_Range];
    $Low3 = $row9[SF_Low_Range];
    $Low4 = $row9[PF_Low_Range];
    $Low5 = $row9[C_Low_Range];

    $High1 = $row9[PG_High_Range];
    $High2 = $row9[SG_High_Range];
    $High3 = $row9[SF_High_Range];
    $High4 = $row9[PF_High_Range];
    $High5 = $row9[C_High_Range];

   $i=0;

   echo "SELECT OFFENSIVE SET TO USE: ";

    while ($i < 3)
   {

    $name_of_set=mysql_result($result7,$i,"offense_name");

   $i++;

    echo "<a href=\"modules.php?name=Depth_Chart_Entry&useset=$i\">$name_of_set</a> | ";

   }


    echo "<hr>
    <form name=\"Depth_Chart\" method=\"post\" action=\"modules.php?name=Depth_Chart_Entry&op=submit\">
    <input type=\"hidden\" name=\"Team_Name\" value=\"$teamlogo\"><input type=\"hidden\" name=\"Set_Name\" value=\"$offense_name\">
    <center><img src=\"online/teamgrfx/$teamlogo.jpg\"><br><table><tr><th colspan=14><center>DEPTH CHART ENTRY - Offensive Set: $offense_name</center></th></tr>
    <tr><th>Pos</th><th>Player</th><th>$Slot1</th><th>$Slot2</th><th>$Slot3</th><th>$Slot4</th><th>$Slot5</th><th>active</th><th>min</th><th>OF</th><th>DF</th><th>OI</th><th>DI</th><th>BH</th></tr>";
    $depthcount=1;

    while($row8 = $db->sql_fetchrow($result8)) {
	$player_pos = $row8[altpos];
	$player_name = $row8[name];
	$player_staminacap = $row8[sta]+40;
	if ($player_staminacap > 40 )
         {
	 $player_staminacap = 40;
         }
	$player_PG = $row8[dc_PGDepth];
	$player_SG = $row8[dc_SGDepth];
	$player_SF = $row8[dc_SFDepth];
	$player_PF = $row8[dc_PFDepth];
	$player_C = $row8[dc_CDepth];
	$player_active = $row8[dc_active];
	$player_min = $row8[dc_minutes];
	$player_of = $row8[dc_of];
	$player_df = $row8[dc_df];
	$player_oi = $row8[dc_oi];
	$player_di = $row8[dc_di];
	$player_bh = $row8[dc_bh];
	$player_inj = $row8[injured];

        if ($player_pos == 'PG')
        {
        $pos_value=1;
        }
        if ($player_pos == 'G')
        {
        $pos_value=2;
        }
        if ($player_pos == 'SG')
        {
        $pos_value=3;
        }
        if ($player_pos == 'GF')
        {
        $pos_value=4;
        }
        if ($player_pos == 'SF')
        {
        $pos_value=5;
        }
        if ($player_pos == 'F')
        {
        $pos_value=6;
        }
        if ($player_pos == 'PF')
        {
        $pos_value=7;
        }
        if ($player_pos == 'FC')
        {
        $pos_value=8;
        }
        if ($player_pos == 'C')
        {
        $pos_value=9;
        }

        echo "<tr><td>$player_pos</td><td nowrap><input type=\"hidden\" name=\"Inury$depthcount\" value=\"$player_inj\"><input type=\"hidden\" name=\"Name$depthcount\" value=\"$player_name\">$player_name</td>";

// SLOT 1 HANDLER

        if ($pos_value >= $Low1 && $player_inj < 15)
        {
          if ($pos_value <= $High1)
          {
            echo "<td>
            <select name=\"pg$depthcount\">";

            if ($player_PG == 0) {
            echo "	<option value=\"0\" SELECTED>No</option>";
            } else {
            echo "	<option value=\"0\">No</option>";
            }
            if ($player_PG == 1) {
            echo "	<option value=\"1\" SELECTED>1st</option>";
            } else {
            echo "	<option value=\"1\">1st</option>";
            }
            if ($player_PG == 2) {
            echo "	<option value=\"2\" SELECTED>2nd</option>";
            } else {
            echo "	<option value=\"2\">2nd</option>";
            }
            if ($player_PG == 3) {
            echo "	<option value=\"3\" SELECTED>3rd</option>";
            } else {
            echo "	<option value=\"3\">3rd</option>";
            }
            if ($player_PG == 4) {
            echo "	<option value=\"4\" SELECTED>4th</option>";
            } else {
            echo "	<option value=\"4\">4th</option>";
            }
            if ($player_PG == 5) {
            echo "	<option value=\"5\" SELECTED>ok</option>";
            } else {
            echo "	<option value=\"5\">ok</option>";
            }

            echo "	</select></td>";
          } else {
          echo "<td><input type=\"hidden\" name=\"pg$depthcount\" value=\"0\">no</td>";
        }
      } else {
        echo "<td><input type=\"hidden\" name=\"pg$depthcount\" value=\"0\">no</td>";
      }

// SLOT 2 HANDLER

        if ($pos_value >= $Low2 && $player_inj < 15)
        {
          if ($pos_value <= $High2)
          {
            echo "<td>
            <select name=\"sg$depthcount\">";

            if ($player_SG == 0) {
            echo "	<option value=\"0\" SELECTED>No</option>";
            } else {
            echo "	<option value=\"0\">No</option>";
            }
            if ($player_SG == 1) {
            echo "	<option value=\"1\" SELECTED>1st</option>";
            } else {
            echo "	<option value=\"1\">1st</option>";
            }
            if ($player_SG == 2) {
            echo "	<option value=\"2\" SELECTED>2nd</option>";
            } else {
            echo "	<option value=\"2\">2nd</option>";
            }
            if ($player_SG == 3) {
            echo "	<option value=\"3\" SELECTED>3rd</option>";
            } else {
            echo "	<option value=\"3\">3rd</option>";
            }
            if ($player_SG == 4) {
            echo "	<option value=\"4\" SELECTED>4th</option>";
            } else {
            echo "	<option value=\"4\">4th</option>";
            }
            if ($player_SG == 5) {
            echo "	<option value=\"5\" SELECTED>ok</option>";
            } else {
            echo "	<option value=\"5\">ok</option>";
            }

            echo "	</select></td>";
          } else {
          echo "<td><input type=\"hidden\" name=\"sg$depthcount\" value=\"0\">no</td>";
        }
      } else {
        echo "<td><input type=\"hidden\" name=\"sg$depthcount\" value=\"0\">no</td>";
      }

// SLOT 3 HANDLER

        if ($pos_value >= $Low3 && $player_inj < 15)
        {
          if ($pos_value <= $High3)
          {
            echo "<td>
            <select name=\"sf$depthcount\">";

            if ($player_SF == 0) {
            echo "	<option value=\"0\" SELECTED>No</option>";
            } else {
            echo "	<option value=\"0\">No</option>";
            }
            if ($player_SF == 1) {
            echo "	<option value=\"1\" SELECTED>1st</option>";
            } else {
            echo "	<option value=\"1\">1st</option>";
            }
            if ($player_SF == 2) {
            echo "	<option value=\"2\" SELECTED>2nd</option>";
            } else {
            echo "	<option value=\"2\">2nd</option>";
            }
            if ($player_SF == 3) {
            echo "	<option value=\"3\" SELECTED>3rd</option>";
            } else {
            echo "	<option value=\"3\">3rd</option>";
            }
            if ($player_SF == 4) {
            echo "	<option value=\"4\" SELECTED>4th</option>";
            } else {
            echo "	<option value=\"4\">4th</option>";
            }
            if ($player_SF == 5) {
            echo "	<option value=\"5\" SELECTED>ok</option>";
            } else {
            echo "	<option value=\"5\">ok</option>";
            }

            echo "	</select></td>";
          } else {
          echo "<td><input type=\"hidden\" name=\"sf$depthcount\" value=\"0\">no</td>";
        }
      } else {
        echo "<td><input type=\"hidden\" name=\"sf$depthcount\" value=\"0\">no</td>";
      }


// SLOT 4 HANDLER

        if ($pos_value >= $Low4 && $player_inj < 15)
        {
          if ($pos_value <= $High4)
          {
            echo "<td>
            <select name=\"pf$depthcount\">";

            if ($player_PF == 0) {
            echo "	<option value=\"0\" SELECTED>No</option>";
            } else {
            echo "	<option value=\"0\">No</option>";
            }
            if ($player_PF == 1) {
            echo "	<option value=\"1\" SELECTED>1st</option>";
            } else {
            echo "	<option value=\"1\">1st</option>";
            }
            if ($player_PF == 2) {
            echo "	<option value=\"2\" SELECTED>2nd</option>";
            } else {
            echo "	<option value=\"2\">2nd</option>";
            }
            if ($player_PF == 3) {
            echo "	<option value=\"3\" SELECTED>3rd</option>";
            } else {
            echo "	<option value=\"3\">3rd</option>";
            }
            if ($player_PF == 4) {
            echo "	<option value=\"4\" SELECTED>4th</option>";
            } else {
            echo "	<option value=\"4\">4th</option>";
            }
            if ($player_PF == 5) {
            echo "	<option value=\"5\" SELECTED>ok</option>";
            } else {
            echo "	<option value=\"5\">ok</option>";
            }

            echo "	</select></td>";
          } else {
          echo "<td><input type=\"hidden\" name=\"pf$depthcount\" value=\"0\">no</td>";
        }
      } else {
        echo "<td><input type=\"hidden\" name=\"pf$depthcount\" value=\"0\">no</td>";
      }

// SLOT 5 HANDLER

        if ($pos_value >= $Low5 && $player_inj < 15)
        {
          if ($pos_value <= $High5)
          {
            echo "<td>
            <select name=\"c$depthcount\">";

            if ($player_C == 0) {
            echo "	<option value=\"0\" SELECTED>No</option>";
            } else {
            echo "	<option value=\"0\">No</option>";
            }
            if ($player_C == 1) {
            echo "	<option value=\"1\" SELECTED>1st</option>";
            } else {
            echo "	<option value=\"1\">1st</option>";
            }
            if ($player_C == 2) {
            echo "	<option value=\"2\" SELECTED>2nd</option>";
            } else {
            echo "	<option value=\"2\">2nd</option>";
            }
            if ($player_C == 3) {
            echo "	<option value=\"3\" SELECTED>3rd</option>";
            } else {
            echo "	<option value=\"3\">3rd</option>";
            }
            if ($player_C == 4) {
            echo "	<option value=\"4\" SELECTED>4th</option>";
            } else {
            echo "	<option value=\"4\">4th</option>";
            }
            if ($player_C == 5) {
            echo "	<option value=\"5\" SELECTED>ok</option>";
            } else {
            echo "	<option value=\"5\">ok</option>";
            }

            echo "	</select></td>";
          } else {
          echo "<td><input type=\"hidden\" name=\"c$depthcount\" value=\"0\">no</td>";
        }
      } else {
        echo "<td><input type=\"hidden\" name=\"c$depthcount\" value=\"0\">no</td>";
      }


 echo "	<td>
        <select name=\"active$depthcount\">";
	if ($player_active == 1) {
	  echo "	<option value=\"1\" SELECTED>Yes</option>
		<option value=\"0\">No</option>";
	  } else {
	  echo "	<option value=\"1\">Yes</option>
		<option value=\"0\" SELECTED>No</option>";
	}
	echo "	</select></td>";



echo "        <td><select name=\"min$depthcount\">";

	if ($player_min == 0) {
	  echo "	<option value=\"0\" SELECTED>Auto</option>";
	  } else {
	  echo "	<option value=\"0\">Auto</option>";
	}

        $abc=1;

	while ($abc <= $player_staminacap)
        {

	if ($player_min == $abc) {
	  echo " <option value=\"$abc\" SELECTED>$abc</option>";
	  } else {
	  echo " <option value=\"$abc\">$abc</option>";
	  }
        $abc++;
        }

echo "	</select></td>
	<td>
	<select name=\"OF$depthcount\">
";
	if ($player_of == 0) {
	  echo " <option value=\"0\" SELECTED>Auto</option>";
	  } else {
	  echo " <option value=\"0\">Auto</option>";
	}
	if ($player_of == 1) {
	  echo " <option value=\"1\" SELECTED>Outside</option>";
	  } else {
	  echo " <option value=\"1\">Outside</option>";
	}
	if ($player_of == 2) {
	  echo " <option value=\"2\" SELECTED>Drive</option>";
	  } else {
	  echo " <option value=\"2\">Drive</option>";
	}
	if ($player_of == 3) {
	  echo " <option value=\"3\" SELECTED>Post</option>";
	  } else {
	  echo " <option value=\"3\">Post</option>";
	}

echo "	</select>
	</td>
	<td>
	<select name=\"DF$depthcount\">
";
	if ($player_df == 0) {
	  echo " <option value=\"0\" SELECTED>Auto</option>";
	  } else {
	  echo " <option value=\"0\">Auto</option>";
	}
	if ($player_df == 1) {
	  echo " <option value=\"1\" SELECTED>Outside</option>";
	  } else {
	  echo " <option value=\"1\">Outside</option>";
	}
	if ($player_df == 2) {
	  echo " <option value=\"2\" SELECTED>Drive</option>";
	  } else {
	  echo " <option value=\"2\">Drive</option>";
	}
	if ($player_df == 3) {
	  echo " <option value=\"3\" SELECTED>Post</option>";
	  } else {
	  echo " <option value=\"3\">Post</option>";
	}

echo "	</select>
	</td>
	<td>
	<select name=\"OI$depthcount\">
";
	if ($player_oi == 2) {
	  echo " <option value=\"2\" SELECTED>2</option>";
	  } else {
	  echo " <option value=\"2\">2</option>";
	}
	if ($player_oi == 1) {
	  echo " <option value=\"1\" SELECTED>1</option>";
	  } else {
	  echo " <option value=\"1\">1</option>";
	}
	if ($player_oi == 0) {
	  echo " <option value=\"0\" SELECTED>-</option>";
	  } else {
	  echo " <option value=\"0\">-</option>";
	}
	if ($player_oi == -1) {
	  echo " <option value=\"-1\" SELECTED>-1</option>";
	  } else {
	  echo " <option value=\"-1\">-1</option>";
	}
	if ($player_oi == -2) {
	  echo " <option value=\"-2\" SELECTED>-2</option>";
	  } else {
	  echo " <option value=\"-2\">-2</option>";
	}


echo "	</select>
	</td>
	<td>
	<select name=\"DI$depthcount\">
";

	if ($player_di == 2) {
	  echo " <option value=\"2\" SELECTED>2</option>";
	  } else {
	  echo " <option value=\"2\">2</option>";
	}
	if ($player_di == 1) {
	  echo " <option value=\"1\" SELECTED>1</option>";
	  } else {
	  echo " <option value=\"1\">1</option>";
	}
	if ($player_di == 0) {
	  echo " <option value=\"0\" SELECTED>-</option>";
	  } else {
	  echo " <option value=\"0\">-</option>";
	}
	if ($player_di == -1) {
	  echo " <option value=\"-1\" SELECTED>-1</option>";
	  } else {
	  echo " <option value=\"-1\">-1</option>";
	}
	if ($player_di == -2) {
	  echo " <option value=\"-2\" SELECTED>-2</option>";
	  } else {
	  echo " <option value=\"-2\">-2</option>";
	}

echo "	</select>

	</td>
	<td>
	<select name=\"BH$depthcount\">
";

	if ($player_bh == 2) {
	  echo " <option value=\"2\" SELECTED>2</option>";
	  } else {
	  echo " <option value=\"2\">2</option>";
	}
	if ($player_bh == 1) {
	  echo " <option value=\"1\" SELECTED>1</option>";
	  } else {
	  echo " <option value=\"1\">1</option>";
	}
	if ($player_bh == 0) {
	  echo " <option value=\"0\" SELECTED>-</option>";
	  } else {
	  echo " <option value=\"0\">-</option>";
	}
	if ($player_bh == -1) {
	  echo " <option value=\"-1\" SELECTED>-1</option>";
	  } else {
	  echo " <option value=\"-1\">-1</option>";
	}
	if ($player_bh == -2) {
	  echo " <option value=\"-2\" SELECTED>-2</option>";
	  } else {
	  echo " <option value=\"-2\">-2</option>";
	}

echo "	</select>
	</td>
        </tr>";
        $depthcount++;
    }

    echo "        <tr><th colspan=14><input type=\"radio\" name=\"emailtarget\" value=\"Normal\" checked> Submit Depth Chart? <input type=\"submit\" value=\"Submit\"></th></tr></form></table></center>";
    CloseTable();

// === END INSERT OF ibl DEPTH CHART ===

    include("footer.php");
}

function main($user) {
    global $stop, $module_name, $redirect, $mode, $t, $f, $gfx_chk;
    if(!is_user($user)) {
	include("header.php");
	if ($stop) {
	    OpenTable();
	    echo "<center><font class=\"title\"><b>"._LOGININCOR."</b></font></center>\n";
	    CloseTable();
	    echo "<br>\n";
	} else {
	    OpenTable();
	    echo "<center><font class=\"title\"><b>"._USERREGLOGIN."</b></font></center>\n";
	    CloseTable();
	    echo "<br>\n";
	}
	if (!is_user($user)) {
	    OpenTable();
	    mt_srand ((double)microtime()*1000000);
	    $maxran = 1000000;
	    $random_num = mt_rand(0, $maxran);
	    echo "<form action=\"modules.php?name=$module_name\" method=\"post\">\n"
		."<b>"._USERLOGIN."</b><br><br>\n"
		."<table border=\"0\"><tr><td>\n"
		.""._NICKNAME.":</td><td><input type=\"text\" name=\"username\" size=\"15\" maxlength=\"25\"></td></tr>\n"
		."<tr><td>"._PASSWORD.":</td><td><input type=\"password\" name=\"user_password\" size=\"15\" maxlength=\"20\"></td></tr>\n";
	    if (extension_loaded("gd") AND ($gfx_chk == 2 OR $gfx_chk == 4 OR $gfx_chk == 5 OR $gfx_chk == 7)) {
		echo "<tr><td colspan='2'>"._SECURITYCODE.": <img src='modules.php?name=$module_name&op=gfx&random_num=$random_num' border='1' alt='"._SECURITYCODE."' title='"._SECURITYCODE."'></td></tr>\n"
		    ."<tr><td colspan='2'>"._TYPESECCODE.": <input type=\"text\" NAME=\"gfx_check\" SIZE=\"7\" MAXLENGTH=\"6\"></td></tr>\n"
		    ."<input type=\"hidden\" name=\"random_num\" value=\"$random_num\">\n";
	    }
	    echo "</table><input type=\"hidden\" name=\"redirect\" value=$redirect>\n"
		."<input type=\"hidden\" name=\"mode\" value=$mode>\n"
		."<input type=\"hidden\" name=\"f\" value=$f>\n"
		."<input type=\"hidden\" name=\"t\" value=$t>\n"
		."<input type=\"hidden\" name=\"op\" value=\"login\">\n"
		."<input type=\"submit\" value=\""._LOGIN."\"></form><br>\n\n"
		."<center><font class=\"content\">[ <a href=\"modules.php?name=$module_name&amp;op=pass_lost\">"._PASSWORDLOST."</a> | <a href=\"modules.php?name=$module_name&amp;op=new_user\">"._REGNEWUSER."</a> ]</font></center>\n";
	    CloseTable();
	}
	include("footer.php");
    } elseif (is_user($user)) {
        global $cookie;
        cookiedecode($user);
        userinfo($cookie[1]);
    }

}

function submit()

{

    include("header.php");

    OpenTable();

$Set_Name = $_POST['Set_Name'];
$Team_Name = $_POST['Team_Name'];
$emailtarget = $_POST['emailtarget'];
$text = "$Team_Name Depth Chart Submission<br><table>";
$text = $text."<tr><td><b>Name</td><td><b>PG</td><td><b>SG</td><td><b>SF</td><td><b>PF</td><td><b>C</td><td><b>Active</td><td><b>Min</td><td><b>OF</td><td><b>DF</td><td><b>OI</td><td><b>DI</td><td><b>BH</td></tr>";
$filetext = "Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI
";

$activeplayers=0;
$pos_1=0;
$pos_2=0;
$pos_3=0;
$pos_4=0;
$pos_5=0;

$i=1;
$injury_Sum = 0;
While ($i < 16)
{

	$a = "<tr><td>".$_POST['Name'.$i]."</td>";
	$b = "<td>".$_POST['pg'.$i]."</td>";
	$c = "<td>".$_POST['sg'.$i]."</td>";
	$d = "<td>".$_POST['sf'.$i]."</td>";
	$e = "<td>".$_POST['pf'.$i]."</td>";
	$f = "<td>".$_POST['c'.$i]."</td>";
	$g = "<td>".$_POST['active'.$i]."</td>";
	$h = "<td>".$_POST['min'.$i]."</td>";
	$z = "<td>".$_POST['OF'.$i]."</td>";
	$j = "<td>".$_POST['DF'.$i]."</td>";
	$k = "<td>".$_POST['OI'.$i]."</td>";
	$l = "<td>".$_POST['DI'.$i]."</td>";
	$m = "<td>".$_POST['BH'.$i]."</td></tr>
";
	$text = $text.$a.$b.$c.$d.$e.$f.$g.$h.$z.$j.$k.$l.$m;

	$injury = $_POST['Inury'.$i];
	$aa = $_POST['Name'.$i].",";
	$bb = $_POST['pg'.$i].",";
	$cc = $_POST['sg'.$i].",";
	$dd = $_POST['sf'.$i].",";
	$ee = $_POST['pf'.$i].",";
	$ff = $_POST['c'.$i].",";
	$gg = $_POST['active'.$i].",";
	$hh = $_POST['min'.$i].",";
	$zz = $_POST['OF'.$i].",";
	$jj = $_POST['DF'.$i].",";
	$kk = $_POST['OI'.$i].",";
	$ll = $_POST['DI'.$i].",";
	$mm = $_POST['BH'.$i]."
";
	$filetext = $filetext.$aa.$bb.$cc.$dd.$ee.$ff.$gg.$hh.$zz.$jj.$kk.$ll.$mm;

$dc_insert1=$_POST['pg'.$i];
$dc_insert2=$_POST['sg'.$i];
$dc_insert3=$_POST['sf'.$i];
$dc_insert4=$_POST['pf'.$i];
$dc_insert5=$_POST['c'.$i];
$dc_insert6=$_POST['active'.$i];
$dc_insert7=$_POST['min'.$i];
$dc_insert8=$_POST['OF'.$i];
$dc_insert9=$_POST['DF'.$i];
$dc_insertA=$_POST['OI'.$i];
$dc_insertB=$_POST['DI'.$i];
$dc_insertC=$_POST['BH'.$i];
$dc_insertkey=$_POST['Name'.$i];

$updatequery1="UPDATE nuke_iblplyr SET dc_PGDepth = '$dc_insert1' WHERE name = '$dc_insertkey'";
$updatequery2="UPDATE nuke_iblplyr SET dc_SGDepth = '$dc_insert2' WHERE name = '$dc_insertkey'";
$updatequery3="UPDATE nuke_iblplyr SET dc_SFDepth = '$dc_insert3' WHERE name = '$dc_insertkey'";
$updatequery4="UPDATE nuke_iblplyr SET dc_PFDepth = '$dc_insert4' WHERE name = '$dc_insertkey'";
$updatequery5="UPDATE nuke_iblplyr SET dc_CDepth = '$dc_insert5' WHERE name = '$dc_insertkey'";
$updatequery6="UPDATE nuke_iblplyr SET dc_active = '$dc_insert6' WHERE name = '$dc_insertkey'";
$updatequery7="UPDATE nuke_iblplyr SET dc_minutes = '$dc_insert7' WHERE name = '$dc_insertkey'";
$updatequery8="UPDATE nuke_iblplyr SET dc_of = '$dc_insert8' WHERE name = '$dc_insertkey'";
$updatequery9="UPDATE nuke_iblplyr SET dc_df = '$dc_insert9' WHERE name = '$dc_insertkey'";
$updatequeryA="UPDATE nuke_iblplyr SET dc_oi = '$dc_insertA' WHERE name = '$dc_insertkey'";
$updatequeryB="UPDATE nuke_iblplyr SET dc_di = '$dc_insertB' WHERE name = '$dc_insertkey'";
$updatequeryC="UPDATE nuke_iblplyr SET dc_bh = '$dc_insertC' WHERE name = '$dc_insertkey'";
$updatequeryD="UPDATE ibl_team_history SET depth = NOW() + INTERVAL 2 HOUR WHERE team_name = '$Team_Name'";
$updatequeryF="UPDATE ibl_team_history SET sim_depth = NOW() + INTERVAL 2 HOUR WHERE team_name = '$Team_Name'";
$executeupdate1=mysql_query($updatequery1);
$executeupdate2=mysql_query($updatequery2);
$executeupdate3=mysql_query($updatequery3);
$executeupdate4=mysql_query($updatequery4);
$executeupdate5=mysql_query($updatequery5);
$executeupdate6=mysql_query($updatequery6);
$executeupdate7=mysql_query($updatequery7);
$executeupdate8=mysql_query($updatequery8);
$executeupdate9=mysql_query($updatequery9);
$executeupdateA=mysql_query($updatequeryA);
$executeupdateB=mysql_query($updatequeryB);
$executeupdateC=mysql_query($updatequeryC);
$executeupdateD=mysql_query($updatequeryD);
$executeupdateF=mysql_query($updatequeryF);

$i++;

if ($dc_insert6 == 1)
 {
  $activeplayers=$activeplayers+1;
 }

if ($dc_insert1 > 0 && $injury == 0)
 {
  $pos_1=$pos_1+1;
 }

if ($dc_insert2 > 0 && $injury == 0)
 {
  $pos_2=$pos_2+1;
 }

if ($dc_insert3 > 0 && $injury == 0)
 {
  $pos_3=$pos_3+1;
 }

if ($dc_insert4 > 0 && $injury == 0)
 {
  $pos_4=$pos_4+1;
 }

if ($dc_insert5 > 0 && $injury == 0)
 {
  $pos_5=$pos_5+1;
 }


}

$text = $text."</table>";
if ($activeplayers < 11)
{
 echo "<font color=red>Your lineup has not been submitted to the commissioner's office because it is an illegal lineup.  You must have at least 12 active players in your lineup; you have $activeplayers.  Please press the \"Back\" button on your browser and re-enter your lineup.</font>";
 $error=1;
}

if ($pos_1 <3 && $error == 0)
{
	 echo "<font color=red>Your lineup has not been submitted to the commissioner's office because it is an illegal lineup.  You must have at least 3 players entered in PG slot; you have $pos_1.  Please press the \"Back\" button on your browser and re-enter your lineup.</font>";
	$error=1;
}

if ($pos_2 <3 && $error == 0)
{
	 echo "<font color=red>Your lineup has not been submitted to the commissioner's office because it is an illegal lineup.  You must have at least 3 players entered in SG slot; you have $pos_2.  Please press the \"Back\" button on your browser and re-enter your lineup.</font>";
	$error=1;
}

if ($pos_3 <3 && $error == 0)
{
	 echo "<font color=red>Your lineup has not been submitted to the commissioner's office because it is an illegal lineup.  You must have at least 3 players entered in SF slot; you have $pos_3.  Please press the \"Back\" button on your browser and re-enter your lineup.</font>";
	$error=1;
}

if ($pos_4 <3 && $error == 0)
{
	 echo "<font color=red>Your lineup has not been submitted to the commissioner's office because it is an illegal lineup.  You must have at least 3 players entered in PF slot; you have $pos_4.  Please press the \"Back\" button on your browser and re-enter your lineup.</font>";
	$error=1;
}

if ($pos_5 <3 && $error == 0)
{
	 echo "<font color=red>Your lineup has not been submitted to the commissioner's office because it is an illegal lineup.  You must have at least 3 players entered in C slot; you have $pos_5.  Please press the \"Back\" button on your browser and re-enter your lineup.</font>";
	$error=1;
}


if ($error == 0)
{
 $emailsubject = $Team_Name." Depth Chart - $Set_Name Offensive Set";
 if ($emailtarget == Preseason)
 {
 $recipient = 'ibldepthcharts@gmail.com';
 } else {
 $recipient = 'ibldepthcharts@gmail.com';
 }

 if (mail($recipient, $emailsubject, $filetext, "From: ibldepthcharts@gmail.com"))
 {
 echo "<center> <font color=red>Your depth chart has been submitted and e-mailed successfully.  Thank you. </font></center>";
 } else {
         echo " <font color=red>Message failed to e-mail properly; please contact the commissioner.</font></center>";
 }
}


echo "<br>$text";

// DISPLAYS DEPTH CHART

    CloseTable();

    include("footer.php");
}


function asg()

{
    global $user, $cookie, $sitename, $prefix, $user_prefix, $db, $admin, $broadcast_msg, $my_headlines, $module_name, $useset, $subscription_url;
    $sql = "SELECT * FROM ".$prefix."_bbconfig";
    $result = $db->sql_query($sql);
    while ( $row = $db->sql_fetchrow($result) )
    {
    $board_config[$row['config_name']] = $row['config_value'];
    }
    $sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
    $result2 = $db->sql_query($sql2);
    $num = $db->sql_numrows($result2);
    $userinfo = $db->sql_fetchrow($result2);
    if(!$bypass) cookiedecode($user);
    include("header.php");

    $sql2 = "SELECT * FROM ".$prefix."_users WHERE username='$cookie[1]'";
    $result2 = $db->sql_query($sql2);
    $num2 = $db->sql_numrows($result2);
    $userinfo = $db->sql_fetchrow($result2);

    $teamlogo = stripslashes(check_html($userinfo['user_ibl_team'], "nohtml"));


    OpenTable();
 



echo "
      <form name=\"ASGVote\" method=\"post\" action=\"ASGVote.php\"><center><img src=\"online/teamgrfx/$teamlogo.jpg\"><br><br>";

$query = "SELECT * FROM nuke_iblplyr where pos = 'C' and (tid = '1' or tid = '26' or tid = '4' or tid = '3' or tid = '21' or tid = '2' or tid = '24' or tid = '29' or tid = '6' or tid = '27' or tid = '9' or tid = '7' or tid = '10' or tid = '5' or tid = '8' or tid = '31') and teamname != 'Retired' and stats_gm > '10' order by ((stats_3gm*3)+stats_ftm+(stats_fgm-stats_3gm)*2)/stats_gm desc";
$result = mysql_query($query);
while($row = mysql_fetch_assoc($result))
{
    $ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
    $ppg = round($ppg,1);
    $rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
    $rpg = round($rpg,1); 
    $apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
    $apg = round($apg,1); 
    $spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
    $spg = round($spg,1); 
    $tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
    $tpg = round($tpg,1); 
    $bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
    $bpg = round($bpg,1); 
    $fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
    $fgp = round($fgp,3);
    $ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
    $ftp = round($ftp,3);
    $tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
    $tpp = round($tpp,3);
    $gm = floatval($row['stats_gm']);
    $gs = floatval($row['stats_gs']);
    $dd .= "<option value='".$row['name']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
} 

$query1 = "SELECT * FROM nuke_iblplyr where (pos = 'PF' or pos = 'SF') and (tid = '1' or tid = '26' or tid = '4' or tid = '3' or tid = '21' or tid = '2' or tid = '24' or tid = '29' or tid = '6' or tid = '27' or tid = '9' or tid = '7' or tid = '10' or tid = '5' or tid = '8' or tid = '31') and teamname != 'Retired' order by name";
$result1 = mysql_query($query1);
while($row = mysql_fetch_assoc($result1))
{
    $ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
    $ppg = round($ppg,1);
    $rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
    $rpg = round($rpg,1); 
    $apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
    $apg = round($apg,1); 
    $spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
    $spg = round($spg,1); 
    $tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
    $tpg = round($tpg,1); 
    $bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
    $bpg = round($bpg,1); 
    $fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
    $fgp = round($fgp,3);
    $ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
    $ftp = round($ftp,3);
    $tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
    $tpp = round($tpp,3);
    $gm = floatval($row['stats_gm']);
    $gs = floatval($row['stats_gs']);
    $ff .= "<option value='".$row['name']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
} 

$query2 = "SELECT * FROM nuke_iblplyr where (pos = 'PG' or pos = 'SG') and (tid = '1' or tid = '26' or tid = '4' or tid = '3' or tid = '21' or tid = '2' or tid = '24' or tid = '29' or tid = '6' or tid = '27' or tid = '9' or tid = '7' or tid = '10' or tid = '5' or tid = '8' or tid = '31') and teamname != 'Retired' order by teamname";
$result2 = mysql_query($query2);
while($row = mysql_fetch_assoc($result2))
{
    $ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
    $ppg = round($ppg,1);
    $rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
    $rpg = round($rpg,1); 
    $apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
    $apg = round($apg,1); 
    $spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
    $spg = round($spg,1); 
    $tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
    $tpg = round($tpg,1); 
    $bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
    $bpg = round($bpg,1); 
    $fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
    $fgp = round($fgp,3);
    $ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
    $ftp = round($ftp,3);
    $tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
    $tpp = round($tpp,3);
    $gm = floatval($row['stats_gm']);
    $gs = floatval($row['stats_gs']);
    $hh .= "<option value='".$row['name']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
} 

$query3 = "SELECT * FROM nuke_iblplyr where pos = 'C' and (tid = '11' or tid = '25' or tid = '30' or tid = '13' or tid = '14' or tid = '15' or tid = '12' or tid = '32' or tid = '16' or tid = '17' or tid = '22' or tid = '20' or tid = '19' or tid = '23' or tid = '18' or tid = '28') and teamname != 'Retired' order by teamname";
$result3 = mysql_query($query3);
while($row = mysql_fetch_assoc($result3))
{
    $ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
    $ppg = round($ppg,1);
    $rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
    $rpg = round($rpg,1); 
    $apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
    $apg = round($apg,1); 
    $spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
    $spg = round($spg,1); 
    $tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
    $tpg = round($tpg,1); 
    $bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
    $bpg = round($bpg,1); 
    $fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
    $fgp = round($fgp,3);
    $ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
    $ftp = round($ftp,3);
    $tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
    $tpp = round($tpp,3);
    $gm = floatval($row['stats_gm']);
    $gs = floatval($row['stats_gs']);
    $ii .= "<option value='".$row['name']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
} 

$query4 = "SELECT * FROM nuke_iblplyr where (pos = 'PF' or pos = 'SF') and (tid = '11' or tid = '25' or tid = '30' or tid = '13' or tid = '14' or tid = '15' or tid = '12' or tid = '32' or tid = '16' or tid = '17' or tid = '22' or tid = '20' or tid = '19' or tid = '23' or tid = '18' or tid = '28') and teamname != 'Retired' order by teamname";
$result4 = mysql_query($query4);
while($row = mysql_fetch_assoc($result4))
{
    $ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
    $ppg = round($ppg,1);
    $rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
    $rpg = round($rpg,1); 
    $apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
    $apg = round($apg,1); 
    $spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
    $spg = round($spg,1); 
    $tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
    $tpg = round($tpg,1); 
    $bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
    $bpg = round($bpg,1); 
    $fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
    $fgp = round($fgp,3);
    $ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
    $ftp = round($ftp,3);
    $tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
    $tpp = round($tpp,3);
    $gm = floatval($row['stats_gm']);
    $gs = floatval($row['stats_gs']);
    $jj .= "<option value='".$row['name']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
} 

$query5 = "SELECT * FROM nuke_iblplyr where (pos = 'PG' or pos = 'SG') and (tid = '11' or tid = '25' or tid = '30' or tid = '13' or tid = '14' or tid = '15' or tid = '12' or tid = '32' or tid = '16' or tid = '17' or tid = '22' or tid = '20' or tid = '19' or tid = '23' or tid = '18' or tid = '28') and teamname != 'Retired' order by teamname";
$result5 = mysql_query($query5);
while($row = mysql_fetch_assoc($result5))
{
    $ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
    $ppg = round($ppg,1);
    $rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
    $rpg = round($rpg,1); 
    $apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
    $apg = round($apg,1); 
    $spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
    $spg = round($spg,1); 
    $tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
    $tpg = round($tpg,1); 
    $bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
    $bpg = round($bpg,1); 
    $fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
    $fgp = round($fgp,3);
    $ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
    $ftp = round($ftp,3);
    $tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
    $tpp = round($tpp,3);
    $gm = floatval($row['stats_gm']);
    $gs = floatval($row['stats_gs']);
    $kk .= "<option value='".$row['name']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
} 

echo "<select name=\"ECC\">
  <option value=\"\">Select Your Eastern Conference Center...</option>
  <option value=\"$dd\">$dd</option>
</select><br><br>

<select name=\"ECF1\">
  <option value=\"\">Select Your First Eastern Conference Forward...</option>
  <option value=\"$ff\">$ff</option>
</select><br><br>

<select name=\"ECF2\">
  <option value=\"\">Select Your Second Eastern Conference Forward...</option>
  <option value=\"$ff\">$ff</option>
</select><br><br>

<select name=\"ECG1\">
  <option value=\"\">Select Your First Eastern Conference Guard...</option>
  <option value=\"$hh\">$hh</option>
</select><br><br>

<select name=\"ECG2\">
  <option value=\"\">Select Your Second Eastern Conference Guard...</option>
  <option value=\"$hh\">$hh</option>
</select><br><br>

<select name=\"WCC\">
  <option value=\"\">Select Your Western Conference Center...</option>
  <option value=\"$ii\">$ii</option>
</select><br><br>

<select name=\"WCF1\">
  <option value=\"\">Select Your First Western Conference Forward...</option>
  <option value=\"$jj\">$jj</option>
</select><br><br>

<select name=\"WCF2\">
  <option value=\"\">Select Your Second Western Conference Forward...</option>
  <option value=\"$jj\">$jj</option>
</select><br><br>

<select name=\"WCG1\">
  <option value=\"\">Select Your First Western Conference Guard...</option>
  <option value=\"$kk\">$kk</option>
</select><br><br>

<select name=\"WCG2\">
  <option value=\"\">Select Your Second Western Conference Guard...</option>
  <option value=\"$kk\">$kk</option>
</select>




</td></tr>







<input type=\"hidden\" name=\"teamname\" value=\"$teamlogo\">
<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
<input type=\"hidden\" name=\"playerpos\" value=\"$player_pos\">
</table>

<center><input type=\"submit\" value=\"Submit Votes!\"></center>
</form>

";

    CloseTable();

    include("footer.php");
}


function eoy()

{
    global $user, $cookie, $sitename, $prefix, $user_prefix, $db, $admin, $broadcast_msg, $my_headlines, $module_name, $useset, $subscription_url;
    $sql = "SELECT * FROM ".$prefix."_bbconfig";
    $result = $db->sql_query($sql);
    while ( $row = $db->sql_fetchrow($result) )
    {
    $board_config[$row['config_name']] = $row['config_value'];
    }
    $sql2 = "SELECT * FROM ".$user_prefix."_users WHERE username='$username'";
    $result2 = $db->sql_query($sql2);
    $num = $db->sql_numrows($result2);
    $userinfo = $db->sql_fetchrow($result2);
    if(!$bypass) cookiedecode($user);
    include("header.php");

    $sql2 = "SELECT * FROM ".$prefix."_users WHERE username='$cookie[1]'";
    $result2 = $db->sql_query($sql2);
    $num2 = $db->sql_numrows($result2);
    $userinfo = $db->sql_fetchrow($result2);

    $teamlogo = stripslashes(check_html($userinfo['user_ibl_team'], "nohtml"));


    OpenTable();
 



echo "
      <form name=\"EOYVote\" method=\"post\" action=\"EOYVote.php\"><center><img src=\"online/teamgrfx/$teamlogo.jpg\"><br><br>";

$query = "SELECT * FROM nuke_iblplyr where teamname != 'Retired' and stats_gm >= '10' order by ((stats_3gm*3)+stats_ftm+(stats_fgm-stats_3gm)*2)/stats_gm desc";
$result = mysql_query($query);
while($row = mysql_fetch_assoc($result))
{
    $ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
    $ppg = round($ppg,1);
    $rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
    $rpg = round($rpg,1); 
    $apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
    $apg = round($apg,1); 
    $spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
    $spg = round($spg,1); 
    $tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
    $tpg = round($tpg,1); 
    $bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
    $bpg = round($bpg,1); 
    $fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
    $fgp = round($fgp,3);
    $ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
    $ftp = round($ftp,3);
    $tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
    $tpp = round($tpp,3);
    $gm = floatval($row['stats_gm']);
    $gs = floatval($row['stats_gs']);
    $dd .= "<option value='".$row['name']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
} 

$query1 = "SELECT * FROM nuke_iblplyr where teamname != 'Retired' and stats_gs <= '25' and stats_gm >= '10'order by ((stats_3gm*3)+stats_ftm+(stats_fgm-stats_3gm)*2)/stats_gm desc";
$result1 = mysql_query($query1);
while($row = mysql_fetch_assoc($result1))
{
    $ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
    $ppg = round($ppg,1);
    $rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
    $rpg = round($rpg,1); 
    $apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
    $apg = round($apg,1); 
    $spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
    $spg = round($spg,1); 
    $tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
    $tpg = round($tpg,1); 
    $bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
    $bpg = round($bpg,1); 
    $fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
    $fgp = round($fgp,3);
    $ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
    $ftp = round($ftp,3);
    $tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
    $tpp = round($tpp,3);
    $gm = floatval($row['stats_gm']);
    $gs = floatval($row['stats_gs']);
    $ff .= "<option value='".$row['name']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
} 

$query2 = "SELECT * FROM nuke_iblplyr where teamname != 'Retired' and exp = '1' and stats_gm >= '10' order by ((stats_3gm*3)+stats_ftm+(stats_fgm-stats_3gm)*2)/stats_gm desc";
$result2 = mysql_query($query2);
while($row = mysql_fetch_assoc($result2))
{
    $ppg = floatval($row['stats_3gm']*3 + ($row['stats_fgm']-$row['stats_3gm'])*2+ $row['stats_ftm']) / intval($row['stats_gm']);
    $ppg = round($ppg,1);
    $rpg = floatval($row['stats_orb'] + $row['stats_drb']) / intval($row['stats_gm']);
    $rpg = round($rpg,1); 
    $apg = floatval($row['stats_ast']) / intval($row['stats_gm']);
    $apg = round($apg,1); 
    $spg = floatval($row['stats_stl']) / intval($row['stats_gm']);
    $spg = round($spg,1); 
    $tpg = floatval($row['stats_to']) / intval($row['stats_gm']);
    $tpg = round($tpg,1); 
    $bpg = floatval($row['stats_blk']) / intval($row['stats_gm']);
    $bpg = round($bpg,1); 
    $fgp = floatval($row['stats_fgm']) / intval($row['stats_fga']);
    $fgp = round($fgp,3);
    $ftp = floatval($row['stats_ftm']) / intval($row['stats_fta']);
    $ftp = round($ftp,3);
    $tpp = floatval($row['stats_3gm']) / intval($row['stats_3ga']);
    $tpp = round($tpp,3);
    $gm = floatval($row['stats_gm']);
    $gs = floatval($row['stats_gs']);
    $hh .= "<option value='".$row['name']."'>".$row['name'].", ".$row['teamname'].", ".$ppg." pts, ".$rpg." reb, ".$apg." ast, ".$spg." stl,  ".$tpg." to, ".$bpg." blk, ".$fgp." fgp, ".$ftp." ftp, ".$tpp." 3gp, ".$gm." gm, ".$gs." gs</option>";
} 

$query3 = "SELECT * from nuke_ibl_team_info where teamid != '35' order by owner_name";
$result3 = mysql_query($query3);
while($row = mysql_fetch_assoc($result3))
{

    $ii .= "<option value='".$row['owner_name']."'>".$row['owner_name'].", ".$row['team_city']." ".$row['team_name']."</option>";

} 

echo "<select name=\"MVP1\">
  <option value=\"\">Select Your First Choice for Most Valuable Player...</option>
  <option value=\"$dd\">$dd</option>
</select><br><br>

<select name=\"MVP2\">
  <option value=\"\">Select Your Second Choice for Most Valuable Player...</option>
  <option value=\"$dd\">$dd</option>
</select><br><br>

<select name=\"MVP3\">
  <option value=\"\">Select Your Third Choice for Most Valuable Player...</option>
  <option value=\"$dd\">$dd</option>
</select><br><br>

<select name=\"Six1\">
  <option value=\"\">Select Your First Choice for Sixth Man of the Year...</option>
  <option value=\"$ff\">$ff</option>
</select><br><br>

<select name=\"Six2\">
  <option value=\"\">Select Your Second Choice for Sixth Man of the Year...</option>
  <option value=\"$ff\">$ff</option>
</select><br><br>

<select name=\"Six3\">
  <option value=\"\">Select Your Third Choice for Sixth Man of the Year...</option>
  <option value=\"$ff\">$ff</option>
</select><br><br>

<select name=\"ROY1\">
  <option value=\"\">Select Your First Choice for Rookie of the Year...</option>
  <option value=\"$hh\">$hh</option>
</select><br><br>

<select name=\"ROY2\">
  <option value=\"\">Select Your Second Choice for Rookie of the Year...</option>
  <option value=\"$hh\">$hh</option>
</select><br><br>

<select name=\"ROY3\">
  <option value=\"\">Select Your Third Choice for Rookie of the Year...</option>
  <option value=\"$hh\">$hh</option>
</select><br><br>

<select name=\"GM1\">
  <option value=\"\">Select Your First Choice for GM of the Year...</option>
  <option value=\"$ii\">$ii</option>

</select><br><br>

<select name=\"GM2\">
  <option value=\"\">Select Your Second Choice for GM of the Year...</option>
  <option value=\"$ii\">$ii</option>
  
</select><br><br>

<select name=\"GM3\">
  <option value=\"\">Select Your First Choice for GM of the Year...</option>
  <option value=\"$ii\">$ii</option>
  
</select>

</td></tr>

<input type=\"hidden\" name=\"teamname\" value=\"$teamlogo\">
<input type=\"hidden\" name=\"playername\" value=\"$player_name\">
<input type=\"hidden\" name=\"playerpos\" value=\"$player_pos\">
</table>

<center><input type=\"submit\" value=\"Submit Votes!\"></center>
</form>

";




    CloseTable();

    include("footer.php");
}





// END OF DEPTH CHART POST, PROCESS, AND EMAIL FUNCTION

switch($op) {


    case "submit":
        submit();
        break;

    case "asg":
        asg();
        break;

    case "eoy":
        eoy();
        break;

    default:
	main($user);
	break;

}

?>