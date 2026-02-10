<?php

declare(strict_types=1);

namespace Team;

use BasketballStats\StatsFormatter;
use Team\Contracts\TeamComponentsViewInterface;
use Team\Contracts\TeamRepositoryInterface;

/**
 * @phpstan-import-type PowerRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type BannerRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type GMTenureRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type GMAwardRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type TeamAwardRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type WinLossRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type HEATWinLossRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type PlayoffResultRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type FranchiseSeasonRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type TeamInfoRow from \Services\CommonMysqliRepository
 *
 * @see TeamComponentsViewInterface
 */
class TeamComponentsView implements TeamComponentsViewInterface
{
    private TeamRepositoryInterface $repository;

    public function __construct(TeamRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see TeamComponentsViewInterface::championshipBanners()
     */
    public function championshipBanners(object $team): string
    {
        /** @var \Team $team */
        $teamName = $team->name;
        $teamColor1 = $team->color1;
        $teamColor2 = $team->color2;
        $banners = $this->repository->getChampionshipBanners($teamName);

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
            $banneryear = $banner['year'];
            $bannername = $banner['bannername'];
            $bannertype = $banner['bannertype'];

            if ($bannertype === 1) {
                if ($championships % 5 === 0) {
                    $ibl_banner .= "<tr><td align=\"center\"><table><tr>";
                }
                $ibl_banner .= "<td><table><tr bgcolor=$teamColor1><td valign=top height=80 width=120 background=\"./images/banners/banner1.gif\"><font color=#$teamColor2>
                    <center><b>$banneryear<br>
                    $bannername<br>IBL Champions</b></center></td></tr></table></td>";

                $championships++;

                if ($championships % 5 === 0) {
                    $ibl_banner .= "</tr></td></table></tr>";
                }

                $champ_text = $this->appendBannerYear($champ_text, $banneryear, $bannername, $teamName);
            } elseif ($bannertype === 2 || $bannertype === 3) {
                if ($conference_titles % 5 === 0) {
                    $conf_banner .= "<tr><td align=\"center\"><table><tr>";
                }

                $conf_banner .= "<td><table><tr bgcolor=$teamColor1><td valign=top height=80 width=120 background=\"./images/banners/banner2.gif\"><font color=#$teamColor2>
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

                $conf_text = $this->appendBannerYear($conf_text, $banneryear, $bannername, $teamName);
            } elseif ($bannertype >= 4 && $bannertype <= 7) {
                if ($division_titles % 5 === 0) {
                    $div_banner .= "<tr><td align=\"center\"><table><tr>";
                }
                $div_banner .= "<td><table><tr bgcolor=$teamColor1><td valign=top height=80 width=120><font color=#$teamColor2>
                    <center><b>$banneryear<br>
                    $bannername<br>";
                if ($bannertype === 4) {
                    $div_banner .= "Atlantic Div. Champions</b></center></td></tr></table></td>";
                } elseif ($bannertype === 5) {
                    $div_banner .= "Central Div. Champions</b></center></td></tr></table></td>";
                } elseif ($bannertype === 6) {
                    $div_banner .= "Midwest Div. Champions</b></center></td></tr></table></td>";
                } else {
                    $div_banner .= "Pacific Div. Champions</b></center></td></tr></table></td>";
                }

                $division_titles++;

                if ($division_titles % 5 === 0) {
                    $div_banner .= "</tr></table></td></tr>";
                }

                $div_text = $this->appendBannerYear($div_text, $banneryear, $bannername, $teamName);
            }
        }

        if (substr($ibl_banner, -23) !== "</tr></table></td></tr>" && $ibl_banner !== "") {
            $ibl_banner .= "</tr></table></td></tr>";
        }
        if (substr($conf_banner, -23) !== "</tr></table></td></tr>" && $conf_banner !== "") {
            $conf_banner .= "</tr></table></td></tr>";
        }
        if (substr($div_banner, -23) !== "</tr></table></td></tr>" && $div_banner !== "") {
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
            $banner_output = "<center><table><tr><td bgcolor=\"#$teamColor1\" align=\"center\"><font color=\"#$teamColor2\"><h2>$teamName Banners</h2></font></td></tr>" . $banner_output . "</table></center>";
        }

        return $banner_output;
    }

    /**
     * @see TeamComponentsViewInterface::currentSeason()
     */
    public function currentSeason(object $team): string
    {
        /** @var \Team $team */
        $powerData = $this->repository->getTeamPowerData($team->name);
        if ($powerData === null) {
            return '';
        }

        $win = $powerData['win'];
        $loss = $powerData['loss'];
        $gb = $powerData['gb'];
        /** @var string $division */
        $division = \Utilities\HtmlSanitizer::safeHtmlOutput($powerData['Division']);
        /** @var string $conference */
        $conference = \Utilities\HtmlSanitizer::safeHtmlOutput($powerData['Conference']);
        $home_win = $powerData['home_win'];
        $home_loss = $powerData['home_loss'];
        $road_win = $powerData['road_win'];
        $road_loss = $powerData['road_loss'];
        $last_win = $powerData['last_win'];
        $last_loss = $powerData['last_loss'];

        $divisionStandings = $this->repository->getDivisionStandings($powerData['Division']);
        $gbbase = $divisionStandings[0]['gb'] ?? 0.0;
        $gb = $gbbase - $gb;

        $Div_Pos = 1;
        foreach ($divisionStandings as $index => $standing) {
            if ($standing['Team'] === $team->name) {
                $Div_Pos = $index + 1;
                break;
            }
        }

        $conferenceStandings = $this->repository->getConferenceStandings($powerData['Conference']);
        $Conf_Pos = 1;
        foreach ($conferenceStandings as $index => $standing) {
            if ($standing['Team'] === $team->name) {
                $Conf_Pos = $index + 1;
                break;
            }
        }

        /** @var string $teamName */
        $teamName = \Utilities\HtmlSanitizer::safeHtmlOutput($team->name);
        /** @var string $arena */
        $arena = \Utilities\HtmlSanitizer::safeHtmlOutput($team->arena);

        $franchiseSeasons = $this->repository->getFranchiseSeasons($team->teamID);
        $fkaRaw = $this->buildFormerlyKnownAs($franchiseSeasons, $team->city, $team->name);
        /** @var string $fka */
        $fka = $fkaRaw !== null ? \Utilities\HtmlSanitizer::safeHtmlOutput($fkaRaw) : '';

        $output = '<div class="team-info-list">'
            . '<span class="team-info-list__label">Team</span>'
            . "<span class=\"team-info-list__value\">$teamName</span>";

        if ($fka !== '') {
            $output .= '<span class="team-info-list__label">f.k.a.</span>'
                . "<span class=\"team-info-list__value\">$fka</span>";
        }

        $output .= '<span class="team-info-list__label">Record</span>'
            . "<span class=\"team-info-list__value\">$win-$loss</span>"
            . '<span class="team-info-list__label">Arena</span>'
            . "<span class=\"team-info-list__value\">$arena</span>";

        if ($team->capacity !== 0) {
            $capacity = $team->capacity;
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

    /**
     * Build a "formerly known as" string from franchise season history.
     *
     * Groups consecutive seasons with the same city+name into eras,
     * excludes the current identity, and formats past eras as
     * "City Name (startYear-endYear)" joined by ", ".
     *
     * @param list<FranchiseSeasonRow> $franchiseSeasons
     * @return string|null Formatted string or null if no past eras
     */
    private function buildFormerlyKnownAs(array $franchiseSeasons, string $currentCity, string $currentName): ?string
    {
        if ($franchiseSeasons === []) {
            return null;
        }

        /** @var list<array{city: string, name: string, startYear: int, endYear: int}> $eras */
        $eras = [];

        foreach ($franchiseSeasons as $season) {
            $city = $season['team_city'];
            $name = $season['team_name'];
            $lastIndex = count($eras) - 1;

            if ($lastIndex >= 0 && $eras[$lastIndex]['city'] === $city && $eras[$lastIndex]['name'] === $name) {
                $eras[$lastIndex] = [
                    'city' => $eras[$lastIndex]['city'],
                    'name' => $eras[$lastIndex]['name'],
                    'startYear' => $eras[$lastIndex]['startYear'],
                    'endYear' => $season['season_ending_year'],
                ];
            } else {
                $eras[] = [
                    'city' => $city,
                    'name' => $name,
                    'startYear' => $season['season_year'],
                    'endYear' => $season['season_ending_year'],
                ];
            }
        }

        $parts = [];
        foreach ($eras as $era) {
            if ($era['city'] === $currentCity && $era['name'] === $currentName) {
                continue;
            }
            $parts[] = $era['city'] . ' ' . $era['name'] . ' (' . $era['startYear'] . '-' . $era['endYear'] . ')';
        }

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    /**
     * @see TeamComponentsViewInterface::draftPicks()
     */
    public function draftPicks(\Team $team): string
    {
        global $mysqli_db;
        /** @var \mysqli $mysqli_db */

        $resultPicks = $team->getDraftPicksResult();

        $league = new \League($mysqli_db);
        $allTeamsResult = $league->getAllTeamsResult();

        /** @var array<string, \Team> $teamsArray */
        $teamsArray = [];
        foreach ($allTeamsResult as $teamRow) {
            /** @var TeamInfoRow $teamRow */
            $teamRowName = $teamRow['team_name'];
            $teamsArray[$teamRowName] = \Team::initialize($mysqli_db, $teamRow);
        }

        $tableDraftPicks = '<ul class="draft-picks-list">';

        foreach ($resultPicks as $draftPickRow) {
            $draftPick = new \DraftPick($draftPickRow);

            $draftPickOriginalTeamID = $teamsArray[$draftPick->originalTeam]->teamID;
            /** @var string $draftPickOriginalTeamCity */
            $draftPickOriginalTeamCity = \Utilities\HtmlSanitizer::safeHtmlOutput($teamsArray[$draftPick->originalTeam]->city);
            /** @var string $draftPickYear */
            $draftPickYear = \Utilities\HtmlSanitizer::safeHtmlOutput((string) $draftPick->year);
            /** @var string $draftPickOriginalTeamName */
            $draftPickOriginalTeamName = \Utilities\HtmlSanitizer::safeHtmlOutput($draftPick->originalTeam);
            $draftPickRound = $draftPick->round;

            $tableDraftPicks .= '<li class="draft-picks-list__item">'
                . "<a href=\"modules.php?name=Team&amp;op=team&amp;teamID=$draftPickOriginalTeamID\">"
                . "<img class=\"draft-picks-list__logo\" src=\"images/logo/$draftPickOriginalTeamName.png\" height=\"33\" width=\"33\" alt=\"$draftPickOriginalTeamName\"></a>"
                . '<div class="draft-picks-list__info">'
                . "<a href=\"modules.php?name=Team&amp;op=team&amp;teamID=$draftPickOriginalTeamID\">$draftPickYear R$draftPickRound $draftPickOriginalTeamCity $draftPickOriginalTeamName</a>";

            if ($draftPick->notes !== null && $draftPick->notes !== '') {
                /** @var string $notesSafe */
                $notesSafe = \Utilities\HtmlSanitizer::safeHtmlOutput($draftPick->notes);
                $tableDraftPicks .= '<div class="draft-picks-list__notes">'
                    . $notesSafe . '</div>';
            }

            $tableDraftPicks .= '</div></li>';
        }

        $tableDraftPicks .= '</ul>';

        return $tableDraftPicks;
    }

    /**
     * @see TeamComponentsViewInterface::gmHistory()
     */
    public function gmHistory(object $team): string
    {
        /** @var \Team $team */
        $tenures = $this->repository->getGMTenures($team->teamID);
        $awards = $this->repository->getGMAwards($team->ownerName);

        $tenureHtml = $this->renderGMTenureList($tenures);
        $awardsHtml = $this->renderGMAwardsList($awards);

        if ($tenureHtml === '' && $awardsHtml === '') {
            return '';
        }

        $output = $tenureHtml;
        if ($awardsHtml !== '') {
            $output .= $awardsHtml;
        }

        return $output;
    }

    /**
     * @see TeamComponentsViewInterface::resultsHEAT()
     */
    public function resultsHEAT(object $team): string
    {
        /** @var \Team $team */
        $heatHistory = $this->repository->getHEATHistory($team->name);

        return $this->renderWinLossHistory(
            $heatHistory,
            $team->teamID,
            static function (array $record): string {
                /** @var string $name */
                $name = \Utilities\HtmlSanitizer::safeHtmlOutput($record['namethatyear']);
                return $record['year'] . ' ' . $name;
            },
        );
    }

    /**
     * @see TeamComponentsViewInterface::resultsPlayoffs()
     */
    public function resultsPlayoffs(object $team): string
    {
        /** @var \Team $team */
        $playoffResults = $this->repository->getPlayoffResults();
        $totalplayoffwins = 0;
        $totalplayofflosses = 0;

        /** @var array<int, array{name: string, wins: int, losses: int, series_w: int, series_l: int, results: list<string>}> $rounds */
        $rounds = [
            1 => ['name' => 'First Round', 'wins' => 0, 'losses' => 0, 'series_w' => 0, 'series_l' => 0, 'results' => []],
            2 => ['name' => 'Conference Semis', 'wins' => 0, 'losses' => 0, 'series_w' => 0, 'series_l' => 0, 'results' => []],
            3 => ['name' => 'Conference Finals', 'wins' => 0, 'losses' => 0, 'series_w' => 0, 'series_l' => 0, 'results' => []],
            4 => ['name' => 'IBL Finals', 'wins' => 0, 'losses' => 0, 'series_w' => 0, 'series_l' => 0, 'results' => []],
        ];

        $teamName = $team->name;

        foreach ($playoffResults as $playoff) {
            $round = $playoff['round'];
            /** @var string $year */
            $year = \Utilities\HtmlSanitizer::safeHtmlOutput((string) $playoff['year']);
            $winner = $playoff['winner'];
            $loser = $playoff['loser'];
            $loserGames = $playoff['loser_games'];

            if (!isset($rounds[$round])) {
                continue;
            }

            $isWin = ($winner === $teamName);
            $isLoss = ($loser === $teamName);

            if (!$isWin && !$isLoss) {
                continue;
            }

            /** @var string $winnerSafe */
            $winnerSafe = \Utilities\HtmlSanitizer::safeHtmlOutput($winner);
            /** @var string $loserSafe */
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
            if ($r['results'] === []) {
                continue;
            }
            $totalSeriesW += $r['series_w'];
            $totalSeriesL += $r['series_l'];
            $gamePct = StatsFormatter::formatPercentage($r['wins'], $r['wins'] + $r['losses']);
            $seriesPct = StatsFormatter::formatPercentage($r['series_w'], $r['series_w'] + $r['series_l']);
            $roundName = $r['name'];

            $output .= "<div class=\"team-card__body\" style=\"padding-bottom: 0;\">"
                . "<strong style=\"font-weight: 700; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500);\">$roundName</strong>"
                . '</div>'
                . '<ul class="team-history-list" style="padding: 0 var(--space-4);">'
                . implode('', $r['results'])
                . '</ul>'
                . "<div class=\"team-card__footer\">Games: {$r['wins']}-{$r['losses']} ($gamePct) &middot; Series: {$r['series_w']}-{$r['series_l']} ($seriesPct)</div>";
        }

        $pwlpct = StatsFormatter::formatPercentage($totalplayoffwins, $totalplayoffwins + $totalplayofflosses);
        $swlpct = StatsFormatter::formatPercentage($totalSeriesW, $totalSeriesW + $totalSeriesL);

        $output .= "<div class=\"team-card__footer\" style=\"font-weight: 700;\">Post-Season: $totalplayoffwins-$totalplayofflosses ($pwlpct) &middot; Series: $totalSeriesW-$totalSeriesL ($swlpct)</div>";

        return $output;
    }

    /**
     * @see TeamComponentsViewInterface::resultsRegularSeason()
     */
    public function resultsRegularSeason(object $team): string
    {
        /** @var \Team $team */
        $regularSeasonHistory = $this->repository->getRegularSeasonHistory($team->name);

        return $this->renderWinLossHistory(
            $regularSeasonHistory,
            $team->teamID,
            static function (array $record): string {
                $yearwl = (string) $record['year'];
                $prevYear = (int) $yearwl - 1;
                /** @var string $name */
                $name = \Utilities\HtmlSanitizer::safeHtmlOutput($record['namethatyear']);
                return $prevYear . '-' . $yearwl . ' ' . $name;
            },
        );
    }

    /**
     * @see TeamComponentsViewInterface::teamAccomplishments()
     */
    public function teamAccomplishments(object $team): string
    {
        /** @var \Team $team */
        return $this->renderAwardsList($this->repository->getTeamAccomplishments($team->name));
    }

    /**
     * Append a banner year to the accumulating text, noting the franchise name if different.
     */
    private function appendBannerYear(string $text, int|string $year, string $bannerName, string $currentName): string
    {
        if ($text === '') {
            $text = (string) $year;
        } else {
            $text .= ", $year";
        }
        if ($bannerName !== $currentName) {
            $text .= " (as $bannerName)";
        }
        return $text;
    }

    /**
     * Render GM tenure list as HTML
     *
     * @param list<GMTenureRow> $tenures
     */
    private function renderGMTenureList(array $tenures): string
    {
        if ($tenures === []) {
            return '';
        }

        $output = '<ul class="team-awards-list">';

        foreach ($tenures as $tenure) {
            $start = $tenure['start_season_year'];
            $end = $tenure['end_season_year'];
            $endLabel = $end === null ? 'Present' : (string) $end;
            /** @var string $username */
            $username = \Utilities\HtmlSanitizer::safeHtmlOutput($tenure['gm_username']);
            $output .= "<li><span class=\"award-year\">$start-$endLabel</span> $username</li>";
        }

        $output .= '</ul>';

        return $output;
    }

    /**
     * Render GM awards list as HTML
     *
     * @param list<GMAwardRow> $awards
     */
    private function renderGMAwardsList(array $awards): string
    {
        if ($awards === []) {
            return '';
        }

        $output = '<ul class="team-awards-list">';

        foreach ($awards as $award) {
            $year = $award['year'];
            /** @var string $awardName */
            $awardName = \Utilities\HtmlSanitizer::safeHtmlOutput($award['Award']);
            $output .= "<li><span class=\"award-year\">$year</span> $awardName</li>";
        }

        $output .= '</ul>';

        return $output;
    }

    /**
     * Render a list of awards/accomplishments from year+Award rows.
     *
     * @param list<array{year: int, Award: string}> $awards
     */
    private function renderAwardsList(array $awards): string
    {
        if ($awards === []) {
            return '';
        }

        $output = '<ul class="team-awards-list">';

        foreach ($awards as $record) {
            $year = $record['year'];
            /** @var string $sanitizedAward */
            $sanitizedAward = \Utilities\HtmlSanitizer::safeHtmlOutput($record['Award']);
            $output .= "<li><span class=\"award-year\">$year</span> $sanitizedAward</li>";
        }

        $output .= '</ul>';

        return $output;
    }

    /**
     * Render a win/loss history list with best-record bolding and totals footer.
     *
     * @param list<array{year: int, namethatyear: string, wins: int, losses: int}> $history
     * @param \Closure(array{year: int, namethatyear: string, wins: int, losses: int}): string $formatLabel
     */
    private function renderWinLossHistory(array $history, int $teamID, \Closure $formatLabel): string
    {
        $wintot = 0;
        $lostot = 0;

        // Find the best record by win percentage (most wins as tiebreaker)
        $bestPct = -1.0;
        $bestWins = -1;
        $bestIndex = -1;
        foreach ($history as $index => $record) {
            $w = (int) $record['wins'];
            $l = (int) $record['losses'];
            $total = $w + $l;
            if ($total > 0) {
                $pct = $w / $total;
                if ($pct > $bestPct) {
                    $bestPct = $pct;
                    $bestWins = $w;
                    $bestIndex = $index;
                } elseif ($pct === $bestPct && $w > $bestWins) {
                    $bestWins = $w;
                    $bestIndex = $index;
                }
            }
        }

        $output = '<ul class="team-history-list">';

        foreach ($history as $index => $record) {
            $yearwl = $record['year'];
            $wins = (int) $record['wins'];
            $losses = (int) $record['losses'];
            $wintot += $wins;
            $lostot += $losses;
            $winpct = StatsFormatter::formatPercentage($wins, $wins + $losses);
            $label = $formatLabel($record);
            $isBest = ($index === $bestIndex);
            $boldOpen = $isBest ? '<strong>' : '';
            $boldClose = $isBest ? '</strong>' : '';
            $output .= "<li>{$boldOpen}<a href=\"./modules.php?name=Team&amp;op=team&amp;teamID=$teamID&amp;yr=$yearwl\">$label</a> <span class=\"record\">$wins-$losses ($winpct)</span>{$boldClose}</li>";
        }

        $output .= '</ul>';

        $wlpct = StatsFormatter::formatPercentage($wintot, $wintot + $lostot);
        $output .= "<div class=\"team-card__footer\">Totals: $wintot-$lostot ($wlpct)</div>";

        return $output;
    }
}
