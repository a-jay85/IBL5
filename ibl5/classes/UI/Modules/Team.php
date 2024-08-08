<?php

namespace UI\Modules;

class Team
{
    public static function championshipBanners($db, $team)
    {
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

        return $ultimate_output[1];
    }

    public static function currentSeason($db, $team)
    {
        $query = "SELECT * FROM ibl_power WHERE Team = '$team->name'";
        $result = $db->sql_query($query);
        $num = $db->sql_numrows($result);
        $win = $db->sql_result($result, 0, "win");
        $loss = $db->sql_result($result, 0, "loss");
        $gb = $db->sql_result($result, 0, "gb");
        $division = $db->sql_result($result, 0, "Division");
        $conference = $db->sql_result($result, 0, "Conference");
        $home_win = $db->sql_result($result, 0, "home_win");
        $home_loss = $db->sql_result($result, 0, "home_loss");
        $road_win = $db->sql_result($result, 0, "road_win");
        $road_loss = $db->sql_result($result, 0, "road_loss");
        $last_win = $db->sql_result($result, 0, "last_win");
        $last_loss = $db->sql_result($result, 0, "last_loss");

        $query2 = "SELECT * FROM ibl_power WHERE Division = '$division' ORDER BY gb DESC";
        $result2 = $db->sql_query($query2);
        $num = $db->sql_numrows($result2);
        $i = 0;
        $gbbase = $db->sql_result($result2, $i, "gb");
        $gb = $gbbase - $gb;
        while ($i < $num) {
            $Team2 = $db->sql_result($result2, $i, "Team");
            if ($Team2 == $team->name) {
                $Div_Pos = $i + 1;
            }
            $i++;
        }

        $query3 = "SELECT * FROM ibl_power WHERE Conference = '$conference' ORDER BY gb DESC";
        $result3 = $db->sql_query($query3);
        $num = $db->sql_numrows($result3);
        $i = 0;
        while ($i < $num) {
            $Team3 = $db->sql_result($result3, $i, "Team");
            if ($Team3 == $team->name) {
                $Conf_Pos = $i + 1;
            }
            $i++;
        }

        $output = "<tr bgcolor=\"#$team->color1\">
            <td align=\"center\">
                <font color=\"#$team->color2\"><b>Current Season</b></font>
            </td>
        </tr>
        <tr>
            <td>
                <table>
                    <tr>
                        <td align='right'><b>Team:</td>
                        <td>$team->name</td>
                    </tr>
                    <tr>
                        <td align='right'><b>f.k.a.:</td>
                        <td>$team->formerlyKnownAs</td>
                    </tr>
                    <tr>
                        <td align='right'><b>Record:</td>
                        <td>$win-$loss</td>
                    </tr>
                    <tr>
                        <td align='right'><b>Arena:</td>
                        <td>$team->arena</td>
                    </tr>
                    <tr>
                        <td align='right'><b>Conference:</td>
                        <td>$conference</td>
                    </tr>
                    <tr>
                        <td align='right'><b>Conf Position:</td>
                        <td>$Conf_Pos</td>
                    </tr>
                    <tr>
                        <td align='right'><b>Division:</td>
                        <td>$division</td>
                    </tr>
                    <tr>
                        <td align='right'><b>Div Position:</td>
                        <td>$Div_Pos</td>
                    </tr>
                    <tr>
                        <td align='right'><b>GB:</td>
                        <td>$gb</td>
                    </tr>
                    <tr>
                        <td align='right'><b>Home Record:</td>
                        <td>$home_win-$home_loss</td>
                    </tr>
                    <tr>
                        <td align='right'><b>Road Record:</td>
                        <td>$road_win-$road_loss</td>
                    </tr>
                    <tr>
                        <td align='right'><b>Last 10:</td>
                        <td>$last_win-$last_loss</td>
                    </tr>
                </table>
            </td>
        </tr>";

        return $output;
    }

    public static function gmHistory($db, $team)
    {
        $owner_award_code = $team->ownerName . " (" . $team->name . ")";
        $querydec = "SELECT * FROM ibl_gm_history WHERE name LIKE '$owner_award_code' ORDER BY year ASC";
        $resultdec = $db->sql_query($querydec);
        $numdec = $db->sql_numrows($resultdec);
        if ($numdec > 0) {
            $dec = 0;
        }

        $output = "<tr bgcolor=\"#$team->color1\">
            <td align=\"center\">
                <font color=\"#$team->color2\"><b>GM History</b></font>
            </td>
        </tr>
        <tr>
            <td>";

        while ($dec < $numdec) {
            $dec_year = $db->sql_result($resultdec, $dec, "year");
            $dec_Award = $db->sql_result($resultdec, $dec, "Award");
            $output .= "<table border=0 cellpadding=0 cellspacing=0>
                <tr>
                    <td>$dec_year $dec_Award</td>
                </tr>
            </table>";
            $dec++;
        }

        $output .= "</td>
        </tr>";

        return $output;
    }

    public static function resultsHEAT($db, $team)
    {
        $querywl = "SELECT * FROM ibl_heat_win_loss WHERE currentname = '$team->name' ORDER BY year DESC";
        $resultwl = $db->sql_query($querywl);
        $numwl = $db->sql_numrows($resultwl);
        $h = 0;
        $wintot = 0;
        $lostot = 0;
        
        $output = "<tr bgcolor=\"#$team->color1\">
            <td align=center>
                <font color=\"#$team->color2\"><b>H.E.A.T. History</b></font>
            </td>
        </tr>
        <tr>
            <td>
                <div id=\"History-R\" style=\"overflow:auto\">";
        
        while ($h < $numwl) {
            $yearwl = $db->sql_result($resultwl, $h, "year");
            $namewl = $db->sql_result($resultwl, $h, "namethatyear");
            $wins = $db->sql_result($resultwl, $h, "wins");
            $losses = $db->sql_result($resultwl, $h, "losses");
            $wintot += $wins;
            $lostot += $losses;
            $winpct = ($wins + $losses) ? number_format($wins / ($wins + $losses), 3) : "0.000";
            $output .= "<a href=\"./modules.php?name=Team&op=team&tid=$team->teamID&yr=$yearwl\">$yearwl $namewl</a>: $wins-$losses ($winpct)<br>";
        
            $h++;
        }
        
        $wlpct = ($wintot + $lostot) ? number_format($wintot / ($wintot + $lostot), 3) : "0.000";
        
        $output .= "</div>
            </td>
        </tr>
        <tr>
            <td>
                <b>Totals:</b> $wintot-$lostot ($wlpct)
            </td>
        </tr>";

        return $output;
    }

    public static function resultsPlayoffs($db, $team)
    {
        $queryplayoffs = "SELECT * FROM ibl_playoff_results ORDER BY year DESC";
        $resultplayoffs = $db->sql_query($queryplayoffs);
        $numplayoffs = $db->sql_numrows($resultplayoffs);

        $pp = 0;
        $totalplayoffwins = $totalplayofflosses = 0;
        $first_round_victories = $second_round_victories = $third_round_victories = $fourth_round_victories = 0;
        $first_round_losses = $second_round_losses = $third_round_losses = $fourth_round_losses = 0;
        $round_one_output = $round_two_output = $round_three_output = $round_four_output = "";
        $first_wins = $second_wins = $third_wins = $fourth_wins = 0;
        $first_losses = $second_losses = $third_losses = $fourth_losses = 0;

        while ($pp < $numplayoffs) {
            $playoffround = $db->sql_result($resultplayoffs, $pp, "round");
            $playoffyear = $db->sql_result($resultplayoffs, $pp, "year");
            $playoffwinner = $db->sql_result($resultplayoffs, $pp, "winner");
            $playoffloser = $db->sql_result($resultplayoffs, $pp, "loser");
            $playoffloser_games = $db->sql_result($resultplayoffs, $pp, "loser_games");

            if ($playoffround == 1) {
                if ($playoffwinner == $team->name) {
                    $totalplayoffwins += 4;
                    $totalplayofflosses += $playoffloser_games;
                    $first_wins += 4;
                    $first_losses += $playoffloser_games;
                    $first_round_victories++;
                    $round_one_output .= "$playoffyear - $team->name 4, $playoffloser $playoffloser_games<br>";
                } else if ($playoffloser == $team->name) {
                    $totalplayofflosses += 4;
                    $totalplayoffwins += $playoffloser_games;
                    $first_losses += 4;
                    $first_wins += $playoffloser_games;
                    $first_round_losses++;
                    $round_one_output .= "$playoffyear - $playoffwinner 4, $team->name $playoffloser_games<br>";
                }
            } else if ($playoffround == 2) {
                if ($playoffwinner == $team->name) {
                    $totalplayoffwins += 4;
                    $totalplayofflosses += $playoffloser_games;
                    $second_wins += 4;
                    $second_losses += $playoffloser_games;
                    $second_round_victories++;
                    $round_two_output .= "$playoffyear - $team->name 4, $playoffloser $playoffloser_games<br>";
                } else if ($playoffloser == $team->name) {
                    $totalplayofflosses += 4;
                    $totalplayoffwins += $playoffloser_games;
                    $second_losses += 4;
                    $second_wins += $playoffloser_games;
                    $second_round_losses++;
                    $round_two_output .= "$playoffyear - $playoffwinner 4, $team->name $playoffloser_games<br>";
                }
            } else if ($playoffround == 3) {
                if ($playoffwinner == $team->name) {
                    $totalplayoffwins += 4;
                    $totalplayofflosses += $playoffloser_games;
                    $third_wins += 4;
                    $third_losses += $playoffloser_games;
                    $third_round_victories++;
                    $round_three_output .= "$playoffyear - $team->name 4, $playoffloser $playoffloser_games<br>";
                } else if ($playoffloser == $team->name) {
                    $totalplayofflosses += 4;
                    $totalplayoffwins += $playoffloser_games;
                    $third_losses += 4;
                    $third_wins += $playoffloser_games;
                    $third_round_losses++;
                    $round_three_output .= "$playoffyear - $playoffwinner 4, $team->name $playoffloser_games<br>";
                }
            } else if ($playoffround == 4) {
                if ($playoffwinner == $team->name) {
                    $totalplayoffwins += 4;
                    $totalplayofflosses += $playoffloser_games;
                    $fourth_wins += 4;
                    $fourth_losses += $playoffloser_games;
                    $fourth_round_victories++;
                    $round_four_output .= "$playoffyear - $team->name 4, $playoffloser $playoffloser_games<br>";
                } else if ($playoffloser == $team->name) {
                    $totalplayofflosses += 4;
                    $totalplayoffwins += $playoffloser_games;
                    $fourth_losses += 4;
                    $fourth_wins += $playoffloser_games;
                    $fourth_round_losses++;
                    $round_four_output .= "$playoffyear - $playoffwinner 4, $team->name $playoffloser_games<br>";
                }
            }
            $pp++;
        }

        $pwlpct = ($totalplayoffwins + $totalplayofflosses != 0) ? number_format($totalplayoffwins / ($totalplayoffwins + $totalplayofflosses), 3) : "0.000";
        $r1wlpct = ($first_round_victories + $first_round_losses != 0) ? number_format($first_round_victories / ($first_round_victories + $first_round_losses), 3) : "0.000";
        $r2wlpct = ($second_round_victories + $second_round_losses != 0) ? number_format($second_round_victories / ($second_round_victories + $second_round_losses), 3) : "0.000";
        $r3wlpct = ($third_round_victories + $third_round_losses) ? number_format($third_round_victories / ($third_round_victories + $third_round_losses), 3) : "0.000";
        $r4wlpct = ($fourth_round_victories + $fourth_round_losses) ? number_format($fourth_round_victories / ($fourth_round_victories + $fourth_round_losses), 3) : "0.000";
        $round_victories = $first_round_victories + $second_round_victories + $third_round_victories + $fourth_round_victories;
        $round_losses = $first_round_losses + $second_round_losses + $third_round_losses + $fourth_round_losses;
        $swlpct = ($round_victories + $round_losses) ? number_format($round_victories / ($round_victories + $round_losses), 3) : "0.000";
        $firstpct = ($first_wins + $first_losses) ? number_format($first_wins / ($first_wins + $first_losses), 3) : "0.000";
        $secondpct = ($second_wins + $second_losses) ? number_format($second_wins / ($second_wins + $second_losses), 3) : "0.000";
        $thirdpct = ($third_wins + $third_losses) ? number_format($third_wins / ($third_wins + $third_losses), 3) : "0.000";
        $fourthpct = ($fourth_wins + $fourth_losses) ? number_format($fourth_wins / ($fourth_wins + $fourth_losses), 3) : "0.000";

        $output = "";
        if ($round_one_output != "") {
            $output .= "<tr bgcolor=\"#$team->color1\">
                <td align=center>
                    <font color=\"#$team->color2\"><b>First-Round Playoff Results</b></font>
                </td>
            </tr>
            <tr>
                <td>
                    <div id=\"History-P1\" style=\"overflow:auto\">" . $round_one_output . "</div>
                </td>
            </tr>
            <tr>
                <td>
                    <b>Totals:</b> $first_wins-$first_losses ($firstpct)<br>
                    <b>Series:</b> $first_round_victories-$first_round_losses ($r1wlpct)
                </td>
            </tr>";
        }
        if ($round_two_output != "") {
            $output .= "<tr bgcolor=\"#$team->color1\">
                <td align=center>
                    <font color=\"#$team->color2\"><b>Conference Semis Playoff Results</b></font>
                </td>
            </tr>
            <tr>
                <td>
                    <div id=\"History-P2\" style=\"overflow:auto\">" . $round_two_output . "</div>
                </td>
            </tr>
            <tr>
                <td>
                    <b>Totals:</b> $second_wins-$second_losses ($secondpct)<br>
                    <b>Series:</b> $second_round_victories-$second_round_losses ($r2wlpct)
                </td>
            </tr>";
        }
        if ($round_three_output != "") {
            $output .= "<tr bgcolor=\"#$team->color1\">
                <td align=center>
                    <font color=\"#$team->color2\"><b>Conference Finals Playoff Results</b></font>
                </td>
            </tr>
            <tr>
                <td>
                    <div id=\"History-P3\" style=\"overflow:auto\">" . $round_three_output . "</div>
                </td>
            </tr>
            <tr>
                <td>
                    <b>Totals:</b> $third_wins-$third_losses ($thirdpct)<br>
                    <b>Series:</b> $third_round_victories-$third_round_losses ($r3wlpct)
                </td>
            </tr>";
        }
        if ($round_four_output != "") {
            $output .= "<tr bgcolor=\"#$team->color1\">
                <td align=center>
                    <font color=\"#$team->color2\"><b>IBL Finals Playoff Results</b></font>
                </td>
            </tr>
            <tr>
                <td>
                    <div id=\"History-P4\" style=\"overflow:auto\">" . $round_four_output . "</div>
                </td>
            </tr>
            <tr>
                <td>
                    <b>Totals:</b> $fourth_wins-$fourth_losses ($fourthpct)<br>
                    <b>Series:</b> $fourth_round_victories-$fourth_round_losses ($r4wlpct)
                </td>
            </tr>";
        }

        $output .= "<tr bgcolor=\"#$team->color1\">
            <td align=center>
                <font color=\"#$team->color2\"><b>Post-Season Totals</b></font>
            </td>
        </tr>
        <tr>
            <td>
                <b>Games:</b> $totalplayoffwins-$totalplayofflosses ($pwlpct)
            </td>
        </tr>
        <tr>
            <td>
                <b>Series:</b> $round_victories-$round_losses ($swlpct)
            </td>
        </tr>";

        return $output;
    }

    public static function resultsRegularSeason($db, $team)
    {
        $querywl = "SELECT * FROM ibl_team_win_loss WHERE currentname = '$team->name' ORDER BY year DESC";
        $resultwl = $db->sql_query($querywl);
        $numwl = $db->sql_numrows($resultwl);

        $h = 0;
        $wintot = 0;
        $lostot = 0;

        $output = "<tr bgcolor=\"#$team->color1\">
            <td align=center>
                <font color=\"#$team->color2\"><b>Regular Season History</b></font>
            </td>
        </tr>
        <tr>
            <td>
                <div id=\"History-R\" style=\"overflow:auto\">";

        while ($h < $numwl) {
            $yearwl = $db->sql_result($resultwl, $h, "year");
            $namewl = $db->sql_result($resultwl, $h, "namethatyear");
            $wins = $db->sql_result($resultwl, $h, "wins");
            $losses = $db->sql_result($resultwl, $h, "losses");
            $wintot += $wins;
            $lostot += $losses;
            $winpct = ($wins + $losses) ? number_format($wins / ($wins + $losses), 3) : "0.000";
            $output .= "<a href=\"./modules.php?name=Team&op=team&tid=$team->teamID&yr=$yearwl\">" . ($yearwl - 1) . "-$yearwl $namewl</a>: $wins-$losses ($winpct)<br>";

            $h++;
        }

        $wlpct = ($wintot + $lostot) ? number_format($wintot / ($wintot + $lostot), 3) : "0.000";

        $output .= "</div>
            </td>
        </tr>
        <tr>
            <td>
                <b>Totals:</b> $wintot-$lostot ($wlpct)
            </td>
        </tr>";

        return $output;
    }

    public static function teamAccomplishments($db, $team)
    {
        $owner_award_code = $team->name . "";
        $querydec = "SELECT * FROM ibl_team_awards WHERE name LIKE '$owner_award_code' ORDER BY year DESC";
        $resultdec = $db->sql_query($querydec);
        $numdec = $db->sql_numrows($resultdec);
        if ($numdec > 0) {
            $dec = 0;
        }

        $output = "<tr bgcolor=\"#$team->color1\">
            <td align=\"center\">
                <font color=\"#$team->color2\"><b>Team Accomplishments</b></font>
            </td>
        </tr>
        <tr>
            <td>";

        while ($dec < $numdec) {
            $dec_year = $db->sql_result($resultdec, $dec, "year");
            $dec_Award = $db->sql_result($resultdec, $dec, "Award");
            $output .= "<table border=0 cellpadding=0 cellspacing=0>
                <tr>
                    <td>$dec_year $dec_Award</td>
                </tr>
            </table>";
            $dec++;
        }

        $output .= "</td>
        </tr>";

        return $output;
    }
}