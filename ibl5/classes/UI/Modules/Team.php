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

            if ($bannertype === 1) {
                if ($championships % 5 === 0) {
                    $ibl_banner .= "<tr><td align=\"center\"><table><tr>";
                }
                $ibl_banner .= "<td><table><tr bgcolor=$team->color1><td valign=top height=80 width=120 background=\"./images/banners/banner1.gif\"><font color=#$team->color2>
                    <center><b>$banneryear<br>
                    $bannername<br>IBL Champions</b></center></td></tr></table></td>";

                $championships++;

                if ($championships % 5 === 0) {
                    $ibl_banner .= "</tr></td></table></tr>";
                }

                if ($champ_text === "") {
                    $champ_text = "$banneryear";
                } else {
                    $champ_text .= ", $banneryear";
                }
                if ($bannername !== $team->name) {
                    $champ_text .= " (as $bannername)";
                }
            } else if ($bannertype === 2 or $bannertype === 3) {
                if ($conference_titles % 5 === 0) {
                    $conf_banner .= "<tr><td align=\"center\"><table><tr>";
                }

                $conf_banner .= "<td><table><tr bgcolor=$team->color1><td valign=top height=80 width=120 background=\"./images/banners/banner2.gif\"><font color=#$team->color2>
                    <center><b>$banneryear<br>
                    $bannername<br>";
                if ($bannertype === 2) {
                    $conf_banner .= "Eastern Conf. Champions</b></center></td></tr></table></td>";
                } else {
                    $conf_banner .= "Western Conf. Champions</b></center></td></tr></table></td>";
                }

                $conference_titles++;

                if ($conference_titles % 5 === 0) {
                    $conf_banner .= "</tr></table></td></tr>";
                }

                if ($conf_text === "") {
                    $conf_text = "$banneryear";
                } else {
                    $conf_text .= ", $banneryear";
                }
                if ($bannername !== $team->name) {
                    $conf_text .= " (as $bannername)";
                }
            } else if ($bannertype === 4 or $bannertype === 5 or $bannertype === 6 or $bannertype === 7) {
                if ($division_titles % 5 === 0) {
                    $div_banner .= "<tr><td align=\"center\"><table><tr>";
                }
                $div_banner .= "<td><table><tr bgcolor=$team->color1><td valign=top height=80 width=120><font color=#$team->color2>
                    <center><b>$banneryear<br>
                    $bannername<br>";
                if ($bannertype === 4) {
                    $div_banner .= "Atlantic Div. Champions</b></center></td></tr></table></td>";
                } else if ($bannertype === 5) {
                    $div_banner .= "Central Div. Champions</b></center></td></tr></table></td>";
                } else if ($bannertype === 6) {
                    $div_banner .= "Midwest Div. Champions</b></center></td></tr></table></td>";
                } else if ($bannertype === 7) {
                    $div_banner .= "Pacific Div. Champions</b></center></td></tr></table></td>";
                }

                $division_titles++;

                if ($division_titles % 5 === 0) {
                    $div_banner .= "</tr></table></td></tr>";
                }

                if ($div_text === "") {
                    $div_text = "$banneryear";
                } else {
                    $div_text .= ", $banneryear";
                }
                if ($bannername !== $team->team_name) {
                    $div_text .= " (as $bannername)";
                }
            }
            $j++;
        }

        if (substr($ibl_banner, -23) !== "</tr></table></td></tr>" and $ibl_banner !== "") {
            $ibl_banner .= "</tr></table></td></tr>";
        }
        if (substr($conf_banner, -23) !== "</tr></table></td></tr>" and $conf_banner !== "") {
            $conf_banner .= "</tr></table></td></tr>";
        }
        if (substr($div_banner, -23) !== "</tr></table></td></tr>" and $div_banner !== "") {
            $div_banner .= "</tr></table></td></tr>";
        }

        $banner_output = "";
        if ($ibl_banner !== "") {
            $banner_output .= $ibl_banner;
        }
        if ($conf_banner !== "") {
            $banner_output .= $conf_banner;
        }
        if ($div_banner !== "") {
            $banner_output .= $div_banner;
        }
        if ($banner_output !== "") {
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
        $division = \Utilities\HtmlSanitizer::safeHtmlOutput($powerData['Division'] ?? '');
        $conference = \Utilities\HtmlSanitizer::safeHtmlOutput($powerData['Conference'] ?? '');
        $home_win = $powerData['home_win'] ?? 0;
        $home_loss = $powerData['home_loss'] ?? 0;
        $road_win = $powerData['road_win'] ?? 0;
        $road_loss = $powerData['road_loss'] ?? 0;
        $last_win = $powerData['last_win'] ?? 0;
        $last_loss = $powerData['last_loss'] ?? 0;

        $divisionStandings = $this->repository->getDivisionStandings($powerData['Division'] ?? '');
        $gbbase = $divisionStandings[0]['gb'] ?? 0;
        $gb = $gbbase - $gb;

        $Div_Pos = 1;
        foreach ($divisionStandings as $index => $standing) {
            if ($standing['Team'] == $team->name) {
                $Div_Pos = $index + 1;
                break;
            }
        }

        $conferenceStandings = $this->repository->getConferenceStandings($powerData['Conference'] ?? '');
        $Conf_Pos = 1;
        foreach ($conferenceStandings as $index => $standing) {
            if ($standing['Team'] == $team->name) {
                $Conf_Pos = $index + 1;
                break;
            }
        }

        $teamName = \Utilities\HtmlSanitizer::safeHtmlOutput($team->name);
        $fka = \Utilities\HtmlSanitizer::safeHtmlOutput($team->formerlyKnownAs);
        $arena = \Utilities\HtmlSanitizer::safeHtmlOutput($team->arena);

        $output = '<div class="team-info-list">'
            . '<span class="team-info-list__label">Team</span>'
            . "<span class=\"team-info-list__value\">$teamName</span>"
            . '<span class="team-info-list__label">f.k.a.</span>'
            . "<span class=\"team-info-list__value\">$fka</span>"
            . '<span class="team-info-list__label">Record</span>'
            . "<span class=\"team-info-list__value\">$win-$loss</span>"
            . '<span class="team-info-list__label">Arena</span>'
            . "<span class=\"team-info-list__value\">$arena</span>";

        if ($team->capacity !== 0) {
            $capacity = (int) $team->capacity;
            $output .= '<span class="team-info-list__label">Capacity</span>'
                . "<span class=\"team-info-list__value\">$capacity</span>";
        }

        $output .= '<span class="team-info-list__label">Conference</span>'
            . "<span class=\"team-info-list__value\">$conference ($Conf_Pos" . $this->ordinalSuffix($Conf_Pos) . ")</span>"
            . '<span class="team-info-list__label">Division</span>'
            . "<span class=\"team-info-list__value\">$division ($Div_Pos" . $this->ordinalSuffix($Div_Pos) . ")</span>"
            . '<span class="team-info-list__label">Games Back</span>'
            . "<span class=\"team-info-list__value\">$gb</span>"
            . '<span class="team-info-list__label">Home</span>'
            . "<span class=\"team-info-list__value\">$home_win-$home_loss</span>"
            . '<span class="team-info-list__label">Road</span>'
            . "<span class=\"team-info-list__value\">$road_win-$road_loss</span>"
            . '<span class="team-info-list__label">Last 10</span>'
            . "<span class=\"team-info-list__value\">$last_win-$last_loss</span>"
            . '</div>';

        return $output;
    }

    /**
     * Return ordinal suffix for a position number (1st, 2nd, 3rd, etc.)
     */
    private function ordinalSuffix(int $n): string
    {
        if ($n % 100 >= 11 && $n % 100 <= 13) {
            return 'th';
        }
        return match ($n % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
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

        $tableDraftPicks = '<ul class="draft-picks-list">';

        foreach ($resultPicks as $draftPickRow) {
            $draftPick = new \DraftPick($draftPickRow);

            $draftPickOriginalTeamID = (int) $teamsArray[$draftPick->originalTeam]->teamID;
            $draftPickOriginalTeamCity = \Utilities\HtmlSanitizer::safeHtmlOutput($teamsArray[$draftPick->originalTeam]->city);
            $draftPickYear = \Utilities\HtmlSanitizer::safeHtmlOutput((string) $draftPick->year);
            $draftPickOriginalTeamName = \Utilities\HtmlSanitizer::safeHtmlOutput($draftPick->originalTeam);
            $draftPickRound = (int) $draftPick->round;

            $tableDraftPicks .= '<li class="draft-picks-list__item">'
                . "<a href=\"modules.php?name=Team&amp;op=team&amp;teamID=$draftPickOriginalTeamID\">"
                . "<img class=\"draft-picks-list__logo\" src=\"images/logo/$draftPickOriginalTeamName.png\" height=\"33\" width=\"33\" alt=\"$draftPickOriginalTeamName\"></a>"
                . '<div class="draft-picks-list__info">'
                . "<a href=\"modules.php?name=Team&amp;op=team&amp;teamID=$draftPickOriginalTeamID\">$draftPickYear R$draftPickRound $draftPickOriginalTeamCity $draftPickOriginalTeamName</a>";

            if ($draftPick->notes !== null && $draftPick->notes !== '') {
                $tableDraftPicks .= '<div class="draft-picks-list__notes">'
                    . \Utilities\HtmlSanitizer::safeHtmlOutput($draftPick->notes) . '</div>';
            }

            $tableDraftPicks .= '</div></li>';
        }

        $tableDraftPicks .= '</ul>';

        return $tableDraftPicks;
    }

    public function gmHistory($team): string
    {
        $gmHistory = $this->repository->getGMHistory($team->ownerName, $team->name);

        if (empty($gmHistory)) {
            return '';
        }

        $output = '<ul class="team-awards-list">';

        foreach ($gmHistory as $record) {
            $year = \Utilities\HtmlSanitizer::safeHtmlOutput((string) ($record['year'] ?? ''));
            $award = \Utilities\HtmlSanitizer::safeHtmlOutput((string) ($record['Award'] ?? ''));
            $output .= "<li><span class=\"award-year\">$year</span> $award</li>";
        }

        $output .= '</ul>';

        return $output;
    }

    public function resultsHEAT($team): string
    {
        $heatHistory = $this->repository->getHEATHistory($team->name);
        $wintot = 0;
        $lostot = 0;

        $output = '<ul class="team-history-list">';

        foreach ($heatHistory as $record) {
            $yearwl = \Utilities\HtmlSanitizer::safeHtmlOutput((string) ($record['year'] ?? ''));
            $namewl = \Utilities\HtmlSanitizer::safeHtmlOutput($record['namethatyear'] ?? '');
            $wins = $record['wins'] ?? 0;
            $losses = $record['losses'] ?? 0;
            $wintot += $wins;
            $lostot += $losses;
            $winpct = ($wins + $losses) ? number_format($wins / ($wins + $losses), 3) : "0.000";
            $teamID = (int) $team->teamID;
            $output .= "<li><a href=\"./modules.php?name=Team&amp;op=team&amp;teamID=$teamID&amp;yr=$yearwl\">$yearwl $namewl</a> <span class=\"record\">$wins-$losses ($winpct)</span></li>";
        }

        $output .= '</ul>';

        $wlpct = ($wintot + $lostot) ? number_format($wintot / ($wintot + $lostot), 3) : "0.000";
        $output .= "<div class=\"team-card__footer\">Totals: $wintot-$lostot ($wlpct)</div>";

        return $output;
    }

    public function resultsPlayoffs($team): string
    {
        $playoffResults = $this->repository->getPlayoffResults();
        $totalplayoffwins = $totalplayofflosses = 0;

        $rounds = [
            1 => ['name' => 'First Round', 'wins' => 0, 'losses' => 0, 'series_w' => 0, 'series_l' => 0, 'results' => []],
            2 => ['name' => 'Conference Semis', 'wins' => 0, 'losses' => 0, 'series_w' => 0, 'series_l' => 0, 'results' => []],
            3 => ['name' => 'Conference Finals', 'wins' => 0, 'losses' => 0, 'series_w' => 0, 'series_l' => 0, 'results' => []],
            4 => ['name' => 'IBL Finals', 'wins' => 0, 'losses' => 0, 'series_w' => 0, 'series_l' => 0, 'results' => []],
        ];

        $teamName = $team->name;

        foreach ($playoffResults as $playoff) {
            $round = $playoff['round'] ?? 0;
            $year = \Utilities\HtmlSanitizer::safeHtmlOutput((string) ($playoff['year'] ?? ''));
            $winner = $playoff['winner'] ?? '';
            $loser = $playoff['loser'] ?? '';
            $loserGames = $playoff['loser_games'] ?? 0;

            if (!isset($rounds[$round])) {
                continue;
            }

            $isWin = ($winner === $teamName);
            $isLoss = ($loser === $teamName);

            if (!$isWin && !$isLoss) {
                continue;
            }

            $winnerSafe = \Utilities\HtmlSanitizer::safeHtmlOutput($winner);
            $loserSafe = \Utilities\HtmlSanitizer::safeHtmlOutput($loser);

            if ($isWin) {
                $totalplayoffwins += 4;
                $totalplayofflosses += $loserGames;
                $rounds[$round]['wins'] += 4;
                $rounds[$round]['losses'] += $loserGames;
                $rounds[$round]['series_w']++;
                $rounds[$round]['results'][] = "<li class=\"playoff-result playoff-result--win\">$year &mdash; $winnerSafe 4, $loserSafe $loserGames</li>";
            } else {
                $totalplayofflosses += 4;
                $totalplayoffwins += $loserGames;
                $rounds[$round]['losses'] += 4;
                $rounds[$round]['wins'] += $loserGames;
                $rounds[$round]['series_l']++;
                $rounds[$round]['results'][] = "<li class=\"playoff-result\">$year &mdash; $winnerSafe 4, $loserSafe $loserGames</li>";
            }
        }

        $output = '';
        $totalSeriesW = 0;
        $totalSeriesL = 0;

        foreach ($rounds as $r) {
            if (empty($r['results'])) {
                continue;
            }
            $totalSeriesW += $r['series_w'];
            $totalSeriesL += $r['series_l'];
            $gamePct = ($r['wins'] + $r['losses']) ? number_format($r['wins'] / ($r['wins'] + $r['losses']), 3) : "0.000";
            $seriesPct = ($r['series_w'] + $r['series_l']) ? number_format($r['series_w'] / ($r['series_w'] + $r['series_l']), 3) : "0.000";
            $roundName = $r['name'];

            $output .= "<div class=\"team-card__body\" style=\"padding-bottom: 0;\">"
                . "<strong style=\"font-weight: 700; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500);\">$roundName</strong>"
                . '</div>'
                . '<ul class="team-history-list" style="padding: 0 var(--space-4);">'
                . implode('', $r['results'])
                . '</ul>'
                . "<div class=\"team-card__footer\">Games: {$r['wins']}-{$r['losses']} ($gamePct) &middot; Series: {$r['series_w']}-{$r['series_l']} ($seriesPct)</div>";
        }

        $pwlpct = ($totalplayoffwins + $totalplayofflosses !== 0)
            ? number_format($totalplayoffwins / ($totalplayoffwins + $totalplayofflosses), 3) : "0.000";
        $swlpct = ($totalSeriesW + $totalSeriesL)
            ? number_format($totalSeriesW / ($totalSeriesW + $totalSeriesL), 3) : "0.000";

        $output .= "<div class=\"team-card__footer\" style=\"font-weight: 700;\">Post-Season: $totalplayoffwins-$totalplayofflosses ($pwlpct) &middot; Series: $totalSeriesW-$totalSeriesL ($swlpct)</div>";

        return $output;
    }

    public function resultsRegularSeason($team): string
    {
        $regularSeasonHistory = $this->repository->getRegularSeasonHistory($team->name);
        $wintot = 0;
        $lostot = 0;

        $output = '<ul class="team-history-list">';

        foreach ($regularSeasonHistory as $record) {
            $yearwl = $record['year'] ?? 0;
            $namewl = \Utilities\HtmlSanitizer::safeHtmlOutput($record['namethatyear'] ?? '');
            $wins = $record['wins'] ?? 0;
            $losses = $record['losses'] ?? 0;
            $wintot += $wins;
            $lostot += $losses;
            $winpct = ($wins + $losses) ? number_format($wins / ($wins + $losses), 3) : "0.000";
            $teamID = (int) $team->teamID;
            $prevYear = $yearwl - 1;
            $output .= "<li><a href=\"./modules.php?name=Team&amp;op=team&amp;teamID=$teamID&amp;yr=$yearwl\">$prevYear-$yearwl $namewl</a> <span class=\"record\">$wins-$losses ($winpct)</span></li>";
        }

        $output .= '</ul>';

        $wlpct = ($wintot + $lostot) ? number_format($wintot / ($wintot + $lostot), 3) : "0.000";
        $output .= "<div class=\"team-card__footer\">Totals: $wintot-$lostot ($wlpct)</div>";

        return $output;
    }

    public function teamAccomplishments($team): string
    {
        $teamAccomplishments = $this->repository->getTeamAccomplishments($team->name);

        if (empty($teamAccomplishments)) {
            return '';
        }

        $output = '<ul class="team-awards-list">';

        foreach ($teamAccomplishments as $record) {
            $year = \Utilities\HtmlSanitizer::safeHtmlOutput((string) ($record['year'] ?? ''));
            $award = \Utilities\HtmlSanitizer::safeHtmlOutput((string) ($record['Award'] ?? ''));
            $output .= "<li><span class=\"award-year\">$year</span> $award</li>";
        }

        $output .= '</ul>';

        return $output;
    }
}