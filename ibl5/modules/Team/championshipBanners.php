<?php

global $db;

$querybanner = "SELECT * FROM ibl_banners WHERE currentname = '$team->name' ORDER BY year ASC";
$resultbanner = $db->sql_query($querybanner);
$numbanner = $db->sql_numrows($resultbanner);

$j = 0;

$championships = 0;
$conference_titles = 0;
$division_titles = 0;

$champ_text = "";
$conf_text = "";
$div_text = "";

$ibl_banner = "";
$conf_banner = "";
$div_banner = "";

while ($j < $numbanner) {
    $banneryear = $db->sql_result($resultbanner, $j, "year");
    $bannername = $db->sql_result($resultbanner, $j, "bannername");
    $bannertype = $db->sql_result($resultbanner, $j, "bannertype");

    if ($bannertype == 1) {
        if ($championships % 5 == 0) {
            $ibl_banner .= "<tr><td align=\"center\"><table><tr>";
        }
        $ibl_banner .= "<td><table><tr bgcolor=$team->color1><td valign=top height=80 width=120 background=\"./images/banners/banner1.gif\"><font color=#$team->color2>
            <center><b>$banneryear<br>
            $bannername<br>IBL Champions</b></center></td></tr></table></td>";

        $championships++;

        if ($championships % 5 == 0) {
            $ibl_banner .= "</tr></td></table></tr>";
        }

        if ($champ_text == "") {
            $champ_text = "$banneryear";
        } else {
            $champ_text .= ", $banneryear";
        }
        if ($bannername != $team->name) {
            $champ_text .= " (as $bannername)";
        }
    } else if ($bannertype == 2 or $bannertype == 3) {
        if ($conference_titles % 5 == 0) {
            $conf_banner .= "<tr><td align=\"center\"><table><tr>";
        }

        $conf_banner .= "<td><table><tr bgcolor=$team->color1><td valign=top height=80 width=120 background=\"./images/banners/banner2.gif\"><font color=#$team->color2>
            <center><b>$banneryear<br>
            $bannername<br>";
        if ($bannertype == 2) {
            $conf_banner .= "Eastern Conf. Champions</b></center></td></tr></table></td>";
        } else {
            $conf_banner .= "Western Conf. Champions</b></center></td></tr></table></td>";
        }

        $conference_titles++;

        if ($conference_titles % 5 == 0) {
            $conf_banner .= "</tr></table></td></tr>";
        }

        if ($conf_text == "") {
            $conf_text = "$banneryear";
        } else {
            $conf_text .= ", $banneryear";
        }
        if ($bannername != $team->name) {
            $conf_text .= " (as $bannername)";
        }
    } else if ($bannertype == 4 or $bannertype == 5 or $bannertype == 6 or $bannertype == 7) {
        if ($division_titles % 5 == 0) {
            $div_banner .= "<tr><td align=\"center\"><table><tr>";
        }
        $div_banner .= "<td><table><tr bgcolor=$team->color1><td valign=top height=80 width=120><font color=#$team->color2>
            <center><b>$banneryear<br>
            $bannername<br>";
        if ($bannertype == 4) {
            $div_banner .= "Atlantic Div. Champions</b></center></td></tr></table></td>";
        } else if ($bannertype == 5) {
            $div_banner .= "Central Div. Champions</b></center></td></tr></table></td>";
        } else if ($bannertype == 6) {
            $div_banner .= "Midwest Div. Champions</b></center></td></tr></table></td>";
        } else if ($bannertype == 7) {
            $div_banner .= "Pacific Div. Champions</b></center></td></tr></table></td>";
        }

        $division_titles++;

        if ($division_titles % 5 == 0) {
            $div_banner .= "</tr></table></td></tr>";
        }

        if ($div_text == "") {
            $div_text = "$banneryear";
        } else {
            $div_text .= ", $banneryear";
        }
        if ($bannername != $team->team_name) {
            $div_text .= " (as $bannername)";
        }
    }
    $j++;
}

if (substr($ibl_banner, -23) != "</tr></table></td></tr>" and $ibl_banner != "") {
    $ibl_banner .= "</tr></table></td></tr>";
}
if (substr($conf_banner, -23) != "</tr></table></td></tr>" and $conf_banner != "") {
    $conf_banner .= "</tr></table></td></tr>";
}
if (substr($div_banner, -23) != "</tr></table></td></tr>" and $div_banner != "") {
    $div_banner .= "</tr></table></td></tr>";
}

$banner_output = "";
if ($ibl_banner != "") {
    $banner_output .= $ibl_banner;
}
if ($conf_banner != "") {
    $banner_output .= $conf_banner;
}
if ($div_banner != "") {
    $banner_output .= $div_banner;
}
if ($banner_output != "") {
    $banner_output = "<center><table><tr><td bgcolor=\"#$team->color1\" align=\"center\"><font color=\"#$team->color2\"><h2>$team->team_name Banners</h2></font></td></tr>" . $banner_output . "</table></center>";
}

$ultimate_output[1] = $banner_output;

/*

$output=$output."<tr bgcolor=\"#$team->color1\"><td align=center><font color=\"#$team->color2\"<b>Team Banners</b></font></td></tr>
<tr><td>$championships IBL Championships: $champ_text</td></tr>
<tr><td>$conference_titles Conference Championships: $conf_text</td></tr>
<tr><td>$division_titles Division Titles: $div_text</td></tr>
";

    */