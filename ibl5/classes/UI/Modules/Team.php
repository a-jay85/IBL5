<?php

declare(strict_types=1);

namespace UI\Modules;

use Team\TeamRepository;

class Team
{
    private TeamRepository $repository;

    public function __construct(TeamRepository $repository)
    {
        $this->repository = $repository;
    }

    public function championshipBanners($team): string
    {
        $banners = $this->repository->getChampionshipBanners($team->name);
        $numbanner = count($banners);

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

        foreach ($banners as $banner) {
            $banneryear = $banner['year'] ?? '';
            $bannername = $banner['bannername'] ?? '';
            $bannertype = $banner['bannertype'] ?? 0;

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

    public function currentSeason($team): string
    {
        $powerData = $this->repository->getTeamPowerData($team->name);
        if (!$powerData) {
            return '';
        }

        $win = $powerData['win'] ?? 0;
        $loss = $powerData['loss'] ?? 0;
        $gb = $powerData['gb'] ?? 0;
        $division = $powerData['Division'] ?? '';
        $conference = $powerData['Conference'] ?? '';
        $home_win = $powerData['home_win'] ?? 0;
        $home_loss = $powerData['home_loss'] ?? 0;
        $road_win = $powerData['road_win'] ?? 0;
        $road_loss = $powerData['road_loss'] ?? 0;
        $last_win = $powerData['last_win'] ?? 0;
        $last_loss = $powerData['last_loss'] ?? 0;

        $divisionStandings = $this->repository->getDivisionStandings($division);
        $gbbase = $divisionStandings[0]['gb'] ?? 0;
        $gb = $gbbase - $gb;
        
        $Div_Pos = 1;
        foreach ($divisionStandings as $index => $standing) {
            if ($standing['Team'] == $team->name) {
                $Div_Pos = $index + 1;
                break;
            }
        }

        $conferenceStandings = $this->repository->getConferenceStandings($conference);
        $Conf_Pos = 1;
        foreach ($conferenceStandings as $index => $standing) {
            if ($standing['Team'] == $team->name) {
                $Conf_Pos = $index + 1;
                break;
            }
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
                    </tr>";
        if ($team->capacity != 0) {
            $output .= "
                    <tr>
                        <td align='right'><b>Capacity:</td>
                        <td>$team->capacity</td>
                    </tr>";
        }
                    $output .= "
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

    public function draftPicks(\Team $team): string
    {
        global $mysqli_db;
        
        $resultPicks = $team->getDraftPicksResult();
    
        $league = new \League($mysqli_db);
        $allTeamsResult = $league->getAllTeamsResult();
    
        foreach ($allTeamsResult as $teamRow) {
            $teamsArray[$teamRow['team_name']] = \Team::initialize($mysqli_db, $teamRow);
        }
    
        $tableDraftPicks = "<table align=\"center\">";
    
        foreach ($resultPicks as $draftPickRow) {
            $draftPick = new \DraftPick($draftPickRow);
    
            $draftPickOriginalTeamID = $teamsArray[$draftPick->originalTeam]->teamID;
            $draftPickOriginalTeamCity = $teamsArray[$draftPick->originalTeam]->city;
    
            $tableDraftPicks .= "<tr>
                <td valign=\"center\"><a href=\"modules.php?name=Team&op=team&teamID=$draftPickOriginalTeamID\"><img src=\"images/logo/$draftPick->originalTeam.png\" height=33 width=33></a></td>
                <td valign=\"center\"><a href=\"modules.php?name=Team&op=team&teamID=$draftPickOriginalTeamID\">$draftPick->year $draftPickOriginalTeamCity $draftPick->originalTeam (Round $draftPick->round)</a></td>
            </tr>";
            if ($draftPick->notes != NULL) {
                $tableDraftPicks .= "<tr>
                    <td width=200 colspan=2 valign=\"top\"><i>$draftPick->notes</i><br>&nbsp;</td>
                </tr>";
            }
        }
    
        $tableDraftPicks .= "</table>";
    
        return $tableDraftPicks;
    }

    public function gmHistory($team): string
    {
        $gmHistory = $this->repository->getGMHistory($team->ownerName, $team->name);

        $output = "<tr bgcolor=\"#$team->color1\">
            <td align=\"center\">
                <font color=\"#$team->color2\"><b>GM History</b></font>
            </td>
        </tr>
        <tr>
            <td>";

        foreach ($gmHistory as $record) {
            $dec_year = $record['year'] ?? '';
            $dec_Award = $record['Award'] ?? '';
            $output .= "<table border=0 cellpadding=0 cellspacing=0>
                <tr>
                    <td>$dec_year $dec_Award</td>
                </tr>
            </table>";
        }

        $output .= "</td>
        </tr>";

        return $output;
    }

    public function resultsHEAT($team): string
    {
        $heatHistory = $this->repository->getHEATHistory($team->name);
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
        
        foreach ($heatHistory as $record) {
            $yearwl = $record['year'] ?? '';
            $namewl = $record['namethatyear'] ?? '';
            $wins = $record['wins'] ?? 0;
            $losses = $record['losses'] ?? 0;
            $wintot += $wins;
            $lostot += $losses;
            $winpct = ($wins + $losses) ? number_format($wins / ($wins + $losses), 3) : "0.000";
            $output .= "<a href=\"./modules.php?name=Team&op=team&teamID=$team->teamID&yr=$yearwl\">$yearwl $namewl</a>: $wins-$losses ($winpct)<br>";
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

    public function resultsPlayoffs($team): string
    {
        $playoffResults = $this->repository->getPlayoffResults();
        $totalplayoffwins = $totalplayofflosses = 0;
        $first_round_victories = $second_round_victories = $third_round_victories = $fourth_round_victories = 0;
        $first_round_losses = $second_round_losses = $third_round_losses = $fourth_round_losses = 0;
        $round_one_output = $round_two_output = $round_three_output = $round_four_output = "";
        $first_wins = $second_wins = $third_wins = $fourth_wins = 0;
        $first_losses = $second_losses = $third_losses = $fourth_losses = 0;

        foreach ($playoffResults as $playoff) {
            $playoffround = $playoff['round'] ?? 0;
            $playoffyear = $playoff['year'] ?? '';
            $playoffwinner = $playoff['winner'] ?? '';
            $playoffloser = $playoff['loser'] ?? '';
            $playoffloser_games = $playoff['loser_games'] ?? 0;

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

    public function resultsRegularSeason($team): string
    {
        $regularSeasonHistory = $this->repository->getRegularSeasonHistory($team->name);
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

        foreach ($regularSeasonHistory as $record) {
            $yearwl = $record['year'] ?? 0;
            $namewl = $record['namethatyear'] ?? '';
            $wins = $record['wins'] ?? 0;
            $losses = $record['losses'] ?? 0;
            $wintot += $wins;
            $lostot += $losses;
            $winpct = ($wins + $losses) ? number_format($wins / ($wins + $losses), 3) : "0.000";
            $output .= "<a href=\"./modules.php?name=Team&op=team&teamID=$team->teamID&yr=$yearwl\">" . ($yearwl - 1) . "-$yearwl $namewl</a>: $wins-$losses ($winpct)<br>";
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

    public function teamAccomplishments($team): string
    {
        $teamAccomplishments = $this->repository->getTeamAccomplishments($team->name);

        $output = "<tr bgcolor=\"#$team->color1\">
            <td align=\"center\">
                <font color=\"#$team->color2\"><b>Team Accomplishments</b></font>
            </td>
        </tr>
        <tr>
            <td>";

        foreach ($teamAccomplishments as $record) {
            $dec_year = $record['year'] ?? '';
            $dec_Award = $record['Award'] ?? '';
            $output .= "<table border=0 cellpadding=0 cellspacing=0>
                <tr>
                    <td>$dec_year $dec_Award</td>
                </tr>
            </table>";
        }

        $output .= "</td>
        </tr>";

        return $output;
    }
}