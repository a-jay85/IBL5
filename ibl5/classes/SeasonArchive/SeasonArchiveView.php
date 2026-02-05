<?php

declare(strict_types=1);

namespace SeasonArchive;

use SeasonArchive\Contracts\SeasonArchiveServiceInterface;
use SeasonArchive\Contracts\SeasonArchiveViewInterface;
use Utilities\HtmlSanitizer;

/**
 * SeasonArchiveView - HTML rendering for season archive pages
 *
 * Generates HTML for the index listing and individual season detail pages.
 * All dynamic output is sanitized via HtmlSanitizer::safeHtmlOutput().
 *
 * @phpstan-import-type SeasonSummary from SeasonArchiveServiceInterface
 * @phpstan-import-type SeasonDetail from SeasonArchiveServiceInterface
 * @phpstan-import-type PlayoffSeries from SeasonArchiveServiceInterface
 *
 * @see SeasonArchiveViewInterface For the interface contract
 */
class SeasonArchiveView implements SeasonArchiveViewInterface
{
    private const ROUND_NAMES = [
        1 => 'First Round',
        2 => 'Conference Semifinals',
        3 => 'Conference Finals',
        4 => 'IBL Finals',
    ];

    /**
     * @see SeasonArchiveViewInterface::renderIndex()
     *
     * @param list<SeasonSummary> $seasons
     */
    public function renderIndex(array $seasons): string
    {
        $html = '<h2 class="ibl-title">IBL Season Archive</h2>';
        $html .= '<table class="sortable ibl-data-table">';
        $html .= '<thead><tr><th>Season</th><th>IBL Champion</th><th>HEAT Champion</th><th>MVP</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($seasons as $season) {
            /** @var array{year: int, label: string, iblChampion: string, heatChampion: string, mvp: string} $season */
            $year = $season['year'];
            $label = self::esc($season['label']);
            $iblChamp = self::esc($season['iblChampion']);
            $heatChamp = self::esc($season['heatChampion']);
            $mvp = self::esc($season['mvp']);

            $html .= '<tr>';
            $html .= '<td><a href="modules.php?name=SeasonArchive&amp;year=' . $year . '">' . $label . '</a></td>';
            $html .= '<td>' . $iblChamp . '</td>';
            $html .= '<td>' . $heatChamp . '</td>';
            $html .= '<td>' . $mvp . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * @see SeasonArchiveViewInterface::renderSeasonDetail()
     *
     * @param SeasonDetail $seasonData
     */
    public function renderSeasonDetail(array $seasonData): string
    {
        /** @var string $label */
        $label = $seasonData['label'];
        /** @var array{heatChampion: string, heatUrl: string, oneOnOneChampion: string, rookieOneOnOneChampion: string, oneOnOneUrl: string, iblFinalsWinner: string, iblFinalsLoser: string, iblFinalsLoserGames: int, playoffsUrl: string} $tournaments */
        $tournaments = $seasonData['tournaments'];
        /** @var array{gameMvp: string, slamDunkWinner: string, threePointWinner: string, rookieSophomoreMvp: string, slamDunkParticipants: list<string>, threePointParticipants: list<string>, rookieSophomoreParticipants: list<string>} $allStarWeekend */
        $allStarWeekend = $seasonData['allStarWeekend'];
        /** @var array{mvp: string, dpoy: string, roy: string, sixthMan: string, gmOfYear: string, finalsMvp: string} $majorAwards */
        $majorAwards = $seasonData['majorAwards'];
        /** @var array{scoring: string, rebounds: string, assists: string, steals: string, blocks: string} $statisticalLeaders */
        $statisticalLeaders = $seasonData['statisticalLeaders'];
        /** @var array{first: list<string>, second: list<string>, third: list<string>} $allLeagueTeams */
        $allLeagueTeams = $seasonData['allLeagueTeams'];
        /** @var array{first: list<string>, second: list<string>, third: list<string>} $allDefensiveTeams */
        $allDefensiveTeams = $seasonData['allDefensiveTeams'];
        /** @var array{first: list<string>, second: list<string>, third: list<string>} $allRookieTeams */
        $allRookieTeams = $seasonData['allRookieTeams'];
        /** @var array<int, list<array{winner: string, loser: string, loserGames: int}>> $playoffBracket */
        $playoffBracket = $seasonData['playoffBracket'];
        /** @var list<array{team: string, wins: int, losses: int}> $heatStandings */
        $heatStandings = $seasonData['heatStandings'];
        /** @var array<string, string> $teamAwards */
        $teamAwards = $seasonData['teamAwards'];
        /** @var array{ibl: list<string>, heat: list<string>} $championRosters */
        $championRosters = $seasonData['championRosters'];
        /** @var array{east: list<string>, west: list<string>} $allStarRosters */
        $allStarRosters = $seasonData['allStarRosters'];
        /** @var array<string, array{color1: string, color2: string}> $teamColors */
        $teamColors = $seasonData['teamColors'];

        $html = $this->renderStyles();
        $html .= '<div class="season-archive-nav">';
        $html .= '<a href="modules.php?name=SeasonArchive">&larr; Back to Season Archive</a>';
        $html .= '<a href="modules.php?name=SeasonLeaderboards">Season Leaders &rarr;</a>';
        $html .= '</div>';
        $html .= '<h2 class="ibl-title">' . self::esc($label) . '</h2>';
        $html .= $this->renderTournaments($tournaments);
        $html .= $this->renderAllStarWeekend($allStarWeekend);
        $html .= $this->renderMajorAwards($majorAwards);
        $html .= $this->renderStatisticalLeaders($statisticalLeaders);
        $html .= $this->renderTeamSelection($allLeagueTeams, 'All-League Teams');
        $html .= $this->renderTeamSelection($allDefensiveTeams, 'All-Defensive Teams');
        $html .= $this->renderTeamSelection($allRookieTeams, 'All-Rookie Teams');
        $html .= $this->renderPlayoffBracket($playoffBracket);
        $html .= $this->renderHeatStandings($heatStandings, $teamColors);
        $html .= $this->renderTeamAwardsSection($teamAwards);
        $html .= $this->renderChampionRosters($championRosters);
        $html .= $this->renderAllStarRosterCards($allStarRosters);

        return $html;
    }

    /**
     * Escape a string value for safe HTML output
     *
     * Wraps HtmlSanitizer::safeHtmlOutput() with a string return type cast
     * to satisfy PHPStan strict type checking in string concatenation contexts.
     */
    private static function esc(string $value): string
    {
        /** @var string */
        return HtmlSanitizer::safeHtmlOutput($value);
    }

    private function renderStyles(): string
    {
        return '<style>'
            . '.season-archive-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4, 1rem); flex-wrap: wrap; gap: var(--space-2, 0.5rem); }'
            . '.season-archive-nav a { color: var(--accent-500, #f97316); text-decoration: none; font-weight: 600; font-family: var(--font-display, \'Barlow Condensed\', sans-serif); }'
            . '.season-archive-nav a:hover { text-decoration: underline; }'
            . '.season-archive-section { margin-bottom: var(--space-8, 2rem); }'
            . '.season-archive-section h3 { font-family: var(--font-display, \'Barlow Condensed\', sans-serif); font-size: 1.25rem; font-weight: 600; color: var(--navy-900, #0f172a); margin-bottom: var(--space-3, 0.75rem); }'
            . '.season-archive-roster-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: var(--space-4, 1rem); }'
            . '.season-archive-roster-card { background: white; border: 1px solid var(--gray-200, #e5e7eb); border-radius: var(--radius-lg, 0.5rem); padding: var(--space-4, 1rem); }'
            . '.season-archive-roster-card h4 { font-family: var(--font-display, \'Barlow Condensed\', sans-serif); font-size: 1.1rem; font-weight: 600; color: var(--navy-800, #1e293b); margin-bottom: var(--space-2, 0.5rem); }'
            . '.season-archive-roster-card ul { list-style: none; padding: 0; margin: 0; }'
            . '.season-archive-roster-card li { padding: var(--space-1, 0.25rem) 0; color: var(--gray-700, #374151); font-size: 0.9rem; }'
            . '.bracket-link { color: var(--accent-500, #f97316); text-decoration: none; font-size: 0.85rem; }'
            . '.bracket-link:hover { text-decoration: underline; }'
            . '</style>';
    }

    /**
     * @param array{heatChampion: string, heatUrl: string, oneOnOneChampion: string, rookieOneOnOneChampion: string, oneOnOneUrl: string, iblFinalsWinner: string, iblFinalsLoser: string, iblFinalsLoserGames: int, playoffsUrl: string} $t
     */
    private function renderTournaments(array $t): string
    {
        $finalsResult = '';
        if ($t['iblFinalsWinner'] !== '') {
            $finalsResult = self::esc($t['iblFinalsWinner']) . ' def. '
                . self::esc($t['iblFinalsLoser']) . ' 4-' . $t['iblFinalsLoserGames'];
        }

        $html = '<div class="season-archive-section"><h3>Tournaments</h3>';
        $html .= '<table class="ibl-data-table"><thead><tr><th>Event</th><th>Champion</th><th>Bracket</th></tr></thead><tbody>';
        $html .= '<tr><td>H.E.A.T. Championship</td><td>' . self::esc($t['heatChampion']) . '</td>';
        $html .= '<td><a href="' . self::esc($t['heatUrl']) . '" class="bracket-link" target="_blank" rel="noopener noreferrer">Bracket</a></td></tr>';
        $html .= '<tr><td>One-on-One Tournament</td><td>' . self::esc($t['oneOnOneChampion']) . '</td>';
        $html .= '<td><a href="' . self::esc($t['oneOnOneUrl']) . '" class="bracket-link" target="_blank" rel="noopener noreferrer">Bracket</a></td></tr>';

        if ($t['rookieOneOnOneChampion'] !== '') {
            $html .= '<tr><td>Rookie One-on-One Tournament</td><td>' . self::esc($t['rookieOneOnOneChampion']) . '</td>';
            $html .= '<td><a href="' . self::esc($t['oneOnOneUrl']) . '" class="bracket-link" target="_blank" rel="noopener noreferrer">Bracket</a></td></tr>';
        }

        $html .= '<tr><td>IBL Finals</td><td>' . $finalsResult . '</td>';
        $html .= '<td><a href="' . self::esc($t['playoffsUrl']) . '" class="bracket-link" target="_blank" rel="noopener noreferrer">Bracket</a></td></tr>';
        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * @param array{gameMvp: string, slamDunkWinner: string, threePointWinner: string, rookieSophomoreMvp: string, slamDunkParticipants: list<string>, threePointParticipants: list<string>, rookieSophomoreParticipants: list<string>} $asw
     */
    private function renderAllStarWeekend(array $asw): string
    {
        $html = '<div class="season-archive-section"><h3>All-Star Weekend</h3>';
        $html .= '<table class="ibl-data-table"><thead><tr><th>Event</th><th>Winner</th></tr></thead><tbody>';
        $html .= '<tr><td>Three-Point Contest</td><td>' . self::esc($asw['threePointWinner']) . '</td></tr>';
        $html .= '<tr><td>Slam Dunk Competition</td><td>' . self::esc($asw['slamDunkWinner']) . '</td></tr>';
        $html .= '<tr><td>Rookie-Sophomore Challenge MVP</td><td>' . self::esc($asw['rookieSophomoreMvp']) . '</td></tr>';
        $html .= '<tr><td>All-Star Game MVP</td><td>' . self::esc($asw['gameMvp']) . '</td></tr>';
        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * @param array{mvp: string, dpoy: string, roy: string, sixthMan: string, gmOfYear: string, finalsMvp: string} $awards
     */
    private function renderMajorAwards(array $awards): string
    {
        $html = '<div class="season-archive-section"><h3>Major Awards</h3>';
        $html .= '<table class="ibl-data-table"><thead><tr><th>Award</th><th>Winner</th></tr></thead><tbody>';
        $html .= '<tr><td>Most Valuable Player</td><td>' . self::esc($awards['mvp']) . '</td></tr>';
        $html .= '<tr><td>Defensive Player of the Year</td><td>' . self::esc($awards['dpoy']) . '</td></tr>';
        $html .= '<tr><td>Rookie of the Year</td><td>' . self::esc($awards['roy']) . '</td></tr>';
        $html .= '<tr><td>6th Man Award</td><td>' . self::esc($awards['sixthMan']) . '</td></tr>';
        $html .= '<tr><td>GM of the Year</td><td>' . self::esc($awards['gmOfYear']) . '</td></tr>';
        $html .= '<tr><td>Finals MVP</td><td>' . self::esc($awards['finalsMvp']) . '</td></tr>';
        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * @param array{scoring: string, rebounds: string, assists: string, steals: string, blocks: string} $leaders
     */
    private function renderStatisticalLeaders(array $leaders): string
    {
        $html = '<div class="season-archive-section"><h3>Statistical Leaders</h3>';
        $html .= '<table class="ibl-data-table"><thead><tr><th>Category</th><th>Leader</th></tr></thead><tbody>';
        $html .= '<tr><td>Scoring</td><td>' . self::esc($leaders['scoring']) . '</td></tr>';
        $html .= '<tr><td>Rebounds</td><td>' . self::esc($leaders['rebounds']) . '</td></tr>';
        $html .= '<tr><td>Assists</td><td>' . self::esc($leaders['assists']) . '</td></tr>';
        $html .= '<tr><td>Steals</td><td>' . self::esc($leaders['steals']) . '</td></tr>';
        $html .= '<tr><td>Blocks</td><td>' . self::esc($leaders['blocks']) . '</td></tr>';
        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * @param array{first: list<string>, second: list<string>, third: list<string>} $teams
     */
    private function renderTeamSelection(array $teams, string $title): string
    {
        $html = '<div class="season-archive-section"><h3>' . self::esc($title) . '</h3>';
        $html .= '<table class="ibl-data-table"><thead><tr><th>First Team</th><th>Second Team</th><th>Third Team</th></tr></thead><tbody>';

        $maxRows = max(count($teams['first']), count($teams['second']), count($teams['third']));
        for ($i = 0; $i < $maxRows; $i++) {
            $first = isset($teams['first'][$i]) ? self::esc($teams['first'][$i]) : '';
            $second = isset($teams['second'][$i]) ? self::esc($teams['second'][$i]) : '';
            $third = isset($teams['third'][$i]) ? self::esc($teams['third'][$i]) : '';
            $html .= '<tr><td>' . $first . '</td><td>' . $second . '</td><td>' . $third . '</td></tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * @param array<int, list<array{winner: string, loser: string, loserGames: int}>> $bracket
     */
    private function renderPlayoffBracket(array $bracket): string
    {
        if ($bracket === []) {
            return '';
        }

        $html = '<div class="season-archive-section"><h3>Playoff Bracket</h3>';
        $html .= '<table class="ibl-data-table"><thead><tr><th>Round</th><th>Result</th></tr></thead><tbody>';

        foreach ($bracket as $round => $seriesList) {
            foreach ($seriesList as $index => $series) {
                $html .= '<tr>';
                if ($index === 0) {
                    $roundName = self::ROUND_NAMES[$round] ?? 'Round ' . $round;
                    $html .= '<td rowspan="' . count($seriesList) . '">' . self::esc($roundName) . '</td>';
                }
                $html .= '<td>' . self::esc($series['winner']) . ' def. '
                    . self::esc($series['loser']) . ' 4-' . $series['loserGames'] . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * @param list<array{team: string, wins: int, losses: int}> $standings
     * @param array<string, array{color1: string, color2: string}> $teamColors
     */
    private function renderHeatStandings(array $standings, array $teamColors): string
    {
        if ($standings === []) {
            return '';
        }

        $html = '<div class="season-archive-section"><h3>H.E.A.T. Standings</h3>';
        $html .= '<table class="ibl-data-table"><thead><tr><th>Team</th><th>W</th><th>L</th></tr></thead><tbody>';

        foreach ($standings as $row) {
            $teamName = $row['team'];
            $colors = $teamColors[$teamName] ?? null;
            $style = '';
            if ($colors !== null) {
                $color1 = self::esc($colors['color1']);
                $color2 = self::esc($colors['color2']);
                $style = 'background-color: #' . $color1 . '; color: #' . $color2 . ';';
            }
            $html .= '<tr>';
            $html .= '<td class="ibl-team-cell--colored" style="' . $style . '">' . self::esc($teamName) . '</td>';
            $html .= '<td>' . $row['wins'] . '</td>';
            $html .= '<td>' . $row['losses'] . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * @param array<string, string> $teamAwards
     */
    private function renderTeamAwardsSection(array $teamAwards): string
    {
        if ($teamAwards === []) {
            return '';
        }

        $html = '<div class="season-archive-section"><h3>Champions &amp; Awards</h3>';
        $html .= '<table class="ibl-data-table"><thead><tr><th>Award</th><th>Team</th></tr></thead><tbody>';

        foreach ($teamAwards as $awardName => $teamName) {
            $html .= '<tr><td>' . self::esc($awardName) . '</td>';
            $html .= '<td>' . self::esc($teamName) . '</td></tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * @param array{ibl: list<string>, heat: list<string>} $rosters
     */
    private function renderChampionRosters(array $rosters): string
    {
        if ($rosters['ibl'] === [] && $rosters['heat'] === []) {
            return '';
        }

        $html = '<div class="season-archive-section"><h3>Championship Rosters</h3>';
        $html .= '<div class="season-archive-roster-grid">';

        if ($rosters['ibl'] !== []) {
            $html .= '<div class="season-archive-roster-card"><h4>IBL Champions</h4><ul>';
            foreach ($rosters['ibl'] as $player) {
                $html .= '<li>' . self::esc($player) . '</li>';
            }
            $html .= '</ul></div>';
        }

        if ($rosters['heat'] !== []) {
            $html .= '<div class="season-archive-roster-card"><h4>H.E.A.T. Champions</h4><ul>';
            foreach ($rosters['heat'] as $player) {
                $html .= '<li>' . self::esc($player) . '</li>';
            }
            $html .= '</ul></div>';
        }

        $html .= '</div></div>';

        return $html;
    }

    /**
     * @param array{east: list<string>, west: list<string>} $rosters
     */
    private function renderAllStarRosterCards(array $rosters): string
    {
        if ($rosters['east'] === [] && $rosters['west'] === []) {
            return '';
        }

        $html = '<div class="season-archive-section"><h3>All-Star Rosters</h3>';
        $html .= '<div class="season-archive-roster-grid">';

        if ($rosters['east'] !== []) {
            $html .= '<div class="season-archive-roster-card"><h4>Eastern Conference</h4><ul>';
            foreach ($rosters['east'] as $player) {
                $html .= '<li>' . self::esc($player) . '</li>';
            }
            $html .= '</ul></div>';
        }

        if ($rosters['west'] !== []) {
            $html .= '<div class="season-archive-roster-card"><h4>Western Conference</h4><ul>';
            foreach ($rosters['west'] as $player) {
                $html .= '<li>' . self::esc($player) . '</li>';
            }
            $html .= '</ul></div>';
        }

        $html .= '</div></div>';

        return $html;
    }
}
