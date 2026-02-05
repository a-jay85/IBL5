<?php

declare(strict_types=1);

namespace SeasonArchive;

use Player\PlayerImageHelper;
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
     * @param array<string, array{color1: string, color2: string, teamid: int}> $teamColors
     * @param array<string, int> $playerIds
     * @param array<string, int> $teamIds
     */
    public function renderIndex(
        array $seasons,
        array $teamColors = [],
        array $playerIds = [],
        array $teamIds = []
    ): string {
        $html = '';
        if ($teamColors !== [] || $playerIds !== []) {
            $html .= $this->renderStyles();
        }
        $html .= '<h2 class="ibl-title">IBL Season Archive</h2>';
        $html .= '<table class="sortable ibl-data-table">';
        $html .= '<thead><tr><th>Season</th><th>IBL Champion</th><th>HEAT Champion</th><th>MVP</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($seasons as $season) {
            /** @var array{year: int, label: string, iblChampion: string, heatChampion: string, mvp: string} $season */
            $year = $season['year'];
            $label = self::esc($season['label']);

            $html .= '<tr>';
            $html .= '<td><a href="modules.php?name=SeasonArchive&amp;year=' . $year . '">' . $label . '</a></td>';
            $html .= self::renderTeamCell($season['iblChampion'], $teamColors, $year);
            $html .= self::renderTeamCell($season['heatChampion'], $teamColors, $year);
            $html .= '<td>' . self::renderPlayerName($season['mvp'], $playerIds) . '</td>';
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
        $year = $seasonData['year'];
        /** @var string $label */
        $label = $seasonData['label'];
        /** @var array{heatChampion: string, heatUrl: string, oneOnOneChampion: string, rookieOneOnOneChampion: string, oneOnOneUrl: string, iblFinalsWinner: string, iblFinalsLoser: string, iblFinalsLoserGames: int, playoffsUrl: string} $tournaments */
        $tournaments = $seasonData['tournaments'];
        /** @var array{gameMvp: string, slamDunkWinner: string, threePointWinner: string, rookieSophomoreMvp: string, slamDunkParticipants: list<string>, threePointParticipants: list<string>, rookieSophomoreParticipants: list<string>} $allStarWeekend */
        $allStarWeekend = $seasonData['allStarWeekend'];
        /** @var array{mvp: string, dpoy: string, roy: string, sixthMan: string, gmOfYear: array{name: string, team: string}, finalsMvp: string} $majorAwards */
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
        /** @var array<string, array{color1: string, color2: string, teamid: int}> $teamColors */
        $teamColors = $seasonData['teamColors'];
        /** @var array<string, int> $playerIds */
        $playerIds = $seasonData['playerIds'];
        /** @var array<string, int> $teamIds */
        $teamIds = $seasonData['teamIds'];

        $html = $this->renderStyles();
        $html .= '<div class="season-archive-nav">';
        $html .= '<a href="modules.php?name=SeasonArchive">&larr; Back to Season Archive</a>';
        $html .= '<a href="modules.php?name=SeasonLeaderboards">Season Leaders &rarr;</a>';
        $html .= '</div>';
        $html .= '<h2 class="ibl-title">' . self::esc($label) . '</h2>';
        $html .= $this->renderTournaments($tournaments, $playerIds, $teamColors, $year);
        $html .= $this->renderAllStarWeekend($allStarWeekend, $playerIds);
        $html .= $this->renderMajorAwards($majorAwards, $playerIds, $teamColors, $teamIds, $year);
        $html .= $this->renderStatisticalLeaders($statisticalLeaders, $playerIds);
        $html .= $this->renderTeamSelection($allLeagueTeams, 'All-League Teams', $playerIds);
        $html .= $this->renderTeamSelection($allDefensiveTeams, 'All-Defensive Teams', $playerIds);
        $html .= $this->renderTeamSelection($allRookieTeams, 'All-Rookie Teams', $playerIds);
        $html .= $this->renderPlayoffBracket($playoffBracket, $teamColors, $year);
        $html .= $this->renderHeatStandings($heatStandings, $teamColors, $year);
        $html .= $this->renderTeamAwardsSection($teamAwards, $teamColors, $year);
        $html .= $this->renderChampionRosters($championRosters, $playerIds);
        $html .= $this->renderAllStarRosterTables($allStarRosters, $playerIds);

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

    /**
     * Render a player name with optional photo thumbnail and link
     *
     * @param array<string, int> $playerIds Map of player name => pid
     */
    private static function renderPlayerName(string $name, array $playerIds): string
    {
        if ($name === '') {
            return '';
        }

        $pid = $playerIds[$name] ?? null;
        if ($pid !== null) {
            $thumbnail = PlayerImageHelper::renderThumbnail($pid);
            return '<span class="ibl-player-cell" style="white-space: nowrap;">'
                . '<a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=' . $pid . '">'
                . $thumbnail . self::esc($name) . '</a></span>';
        }

        return self::esc($name);
    }

    /**
     * Render a team cell with colored background, logo, and link
     *
     * @param array<string, array{color1: string, color2: string, teamid: int}> $teamColors
     */
    private static function renderTeamCell(string $teamName, array $teamColors, int $year): string
    {
        $colors = $teamColors[$teamName] ?? null;
        if ($colors !== null) {
            $color1 = self::esc($colors['color1']);
            $color2 = self::esc($colors['color2']);
            $teamid = $colors['teamid'];
            return '<td class="ibl-team-cell--colored" style="background-color: #' . $color1 . ';">'
                . '<a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $teamid . '&amp;yr=' . $year
                . '" class="ibl-team-cell__name" style="color: #' . $color2 . ';">'
                . '<img src="images/logo/new' . $teamid . '.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">'
                . '<span class="ibl-team-cell__text">' . self::esc($teamName) . '</span>'
                . '</a></td>';
        }

        return '<td>' . self::esc($teamName) . '</td>';
    }

    private function renderStyles(): string
    {
        return '<style>'
            . '.season-archive-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4, 1rem); flex-wrap: wrap; gap: var(--space-2, 0.5rem); }'
            . '.season-archive-nav a { color: var(--accent-500, #f97316); text-decoration: none; font-weight: 600; font-family: var(--font-display, \'Barlow Condensed\', sans-serif); }'
            . '.season-archive-nav a:hover { text-decoration: underline; }'
            . '.season-archive-section { margin-bottom: var(--space-8, 2rem); }'
            . '.season-archive-section h3 { font-family: var(--font-display, \'Barlow Condensed\', sans-serif); font-size: 1.25rem; font-weight: 600; color: var(--navy-900, #0f172a); margin-bottom: var(--space-3, 0.75rem); }'
            . '.season-archive-roster-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4, 1rem); }'
            . '.season-archive-roster-grid h4 { font-family: var(--font-display, \'Barlow Condensed\', sans-serif); font-size: 1.1rem; font-weight: 600; color: var(--navy-800, #1e293b); margin-bottom: var(--space-2, 0.5rem); }'
            . '.bracket-link { color: var(--accent-500, #f97316); text-decoration: none; }'
            . '.bracket-link:hover { text-decoration: underline; }'
            . '.season-archive-bracket-hint { font-size: 0.85rem; color: var(--gray-500, #6b7280); margin-bottom: var(--space-2, 0.5rem); }'
            . '.bracket-round-start td { border-top: 2px solid var(--gray-300, #d1d5db); }'
            . '</style>';
    }

    /**
     * @param array{heatChampion: string, heatUrl: string, oneOnOneChampion: string, rookieOneOnOneChampion: string, oneOnOneUrl: string, iblFinalsWinner: string, iblFinalsLoser: string, iblFinalsLoserGames: int, playoffsUrl: string} $t
     * @param array<string, int> $playerIds
     * @param array<string, array{color1: string, color2: string, teamid: int}> $teamColors
     */
    private function renderTournaments(array $t, array $playerIds, array $teamColors, int $year): string
    {
        $finalsResult = '';
        if ($t['iblFinalsWinner'] !== '') {
            $finalsResult = self::esc($t['iblFinalsWinner']) . ' def. '
                . self::esc($t['iblFinalsLoser']) . ' 4-' . $t['iblFinalsLoserGames'];
        }

        $html = '<div class="season-archive-section"><h3>Tournaments</h3>';
        $html .= '<p class="season-archive-bracket-hint">Click an event name to view its Challonge bracket.</p>';
        $html .= '<table class="ibl-data-table"><thead><tr><th>Event</th><th>Champion</th></tr></thead><tbody>';

        // H.E.A.T. Championship — team champion
        $html .= '<tr><td><a href="' . self::esc($t['heatUrl']) . '" class="bracket-link" target="_blank" rel="noopener noreferrer">H.E.A.T. Championship</a></td>';
        $html .= self::renderTeamCell($t['heatChampion'], $teamColors, $year) . '</tr>';

        // One-on-One Tournament — player champion
        $html .= '<tr><td><a href="' . self::esc($t['oneOnOneUrl']) . '" class="bracket-link" target="_blank" rel="noopener noreferrer">One-on-One Tournament</a></td>';
        $html .= '<td>' . self::renderPlayerName($t['oneOnOneChampion'], $playerIds) . '</td></tr>';

        if ($t['rookieOneOnOneChampion'] !== '') {
            $html .= '<tr><td><a href="' . self::esc($t['oneOnOneUrl']) . '" class="bracket-link" target="_blank" rel="noopener noreferrer">Rookie One-on-One Tournament</a></td>';
            $html .= '<td>' . self::renderPlayerName($t['rookieOneOnOneChampion'], $playerIds) . '</td></tr>';
        }

        // IBL Finals — teams
        $html .= '<tr><td><a href="' . self::esc($t['playoffsUrl']) . '" class="bracket-link" target="_blank" rel="noopener noreferrer">IBL Finals</a></td>';
        $html .= '<td>' . $finalsResult . '</td></tr>';
        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * @param array{gameMvp: string, slamDunkWinner: string, threePointWinner: string, rookieSophomoreMvp: string, slamDunkParticipants: list<string>, threePointParticipants: list<string>, rookieSophomoreParticipants: list<string>} $asw
     * @param array<string, int> $playerIds
     */
    private function renderAllStarWeekend(array $asw, array $playerIds): string
    {
        $html = '<div class="season-archive-section"><h3>All-Star Weekend</h3>';
        $html .= '<table class="ibl-data-table"><thead><tr><th>Event</th><th>Winner</th></tr></thead><tbody>';
        $html .= '<tr><td>Three-Point Contest</td><td>' . self::renderPlayerName($asw['threePointWinner'], $playerIds) . '</td></tr>';
        $html .= '<tr><td>Slam Dunk Competition</td><td>' . self::renderPlayerName($asw['slamDunkWinner'], $playerIds) . '</td></tr>';
        $html .= '<tr><td>Rookie-Sophomore Challenge MVP</td><td>' . self::renderPlayerName($asw['rookieSophomoreMvp'], $playerIds) . '</td></tr>';
        $html .= '<tr><td>All-Star Game MVP</td><td>' . self::renderPlayerName($asw['gameMvp'], $playerIds) . '</td></tr>';
        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * @param array{mvp: string, dpoy: string, roy: string, sixthMan: string, gmOfYear: array{name: string, team: string}, finalsMvp: string} $awards
     * @param array<string, int> $playerIds
     * @param array<string, array{color1: string, color2: string, teamid: int}> $teamColors
     * @param array<string, int> $teamIds
     */
    private function renderMajorAwards(array $awards, array $playerIds, array $teamColors, array $teamIds, int $year): string
    {
        // Build GM of Year display with optional team link
        $gmDisplay = '';
        $gmOfYear = $awards['gmOfYear'];
        if ($gmOfYear['name'] !== '') {
            $gmDisplay = self::esc($gmOfYear['name']);
            if ($gmOfYear['team'] !== '') {
                $teamId = $teamIds[$gmOfYear['team']] ?? null;
                if ($teamId !== null) {
                    $gmDisplay .= ' (<a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $teamId . '&amp;yr=' . $year . '">' . self::esc($gmOfYear['team']) . '</a>)';
                } else {
                    $gmDisplay .= ' (' . self::esc($gmOfYear['team']) . ')';
                }
            }
        }

        $html = '<div class="season-archive-section"><h3>Major Awards</h3>';
        $html .= '<table class="ibl-data-table"><thead><tr><th>Award</th><th>Winner</th></tr></thead><tbody>';
        $html .= '<tr><td>Most Valuable Player</td><td>' . self::renderPlayerName($awards['mvp'], $playerIds) . '</td></tr>';
        $html .= '<tr><td>Defensive Player of the Year</td><td>' . self::renderPlayerName($awards['dpoy'], $playerIds) . '</td></tr>';
        $html .= '<tr><td>Rookie of the Year</td><td>' . self::renderPlayerName($awards['roy'], $playerIds) . '</td></tr>';
        $html .= '<tr><td>6th Man Award</td><td>' . self::renderPlayerName($awards['sixthMan'], $playerIds) . '</td></tr>';
        $html .= '<tr><td>GM of the Year</td><td>' . $gmDisplay . '</td></tr>';
        $html .= '<tr><td>Finals MVP</td><td>' . self::renderPlayerName($awards['finalsMvp'], $playerIds) . '</td></tr>';
        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * @param array{scoring: string, rebounds: string, assists: string, steals: string, blocks: string} $leaders
     * @param array<string, int> $playerIds
     */
    private function renderStatisticalLeaders(array $leaders, array $playerIds): string
    {
        $html = '<div class="season-archive-section"><h3>Statistical Leaders</h3>';
        $html .= '<table class="ibl-data-table"><thead><tr><th>Category</th><th>Leader</th></tr></thead><tbody>';
        $html .= '<tr><td>Scoring</td><td>' . self::renderPlayerName($leaders['scoring'], $playerIds) . '</td></tr>';
        $html .= '<tr><td>Rebounds</td><td>' . self::renderPlayerName($leaders['rebounds'], $playerIds) . '</td></tr>';
        $html .= '<tr><td>Assists</td><td>' . self::renderPlayerName($leaders['assists'], $playerIds) . '</td></tr>';
        $html .= '<tr><td>Steals</td><td>' . self::renderPlayerName($leaders['steals'], $playerIds) . '</td></tr>';
        $html .= '<tr><td>Blocks</td><td>' . self::renderPlayerName($leaders['blocks'], $playerIds) . '</td></tr>';
        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * @param array{first: list<string>, second: list<string>, third: list<string>} $teams
     * @param array<string, int> $playerIds
     */
    private function renderTeamSelection(array $teams, string $title, array $playerIds): string
    {
        $html = '<div class="season-archive-section"><h3>' . self::esc($title) . '</h3>';
        $html .= '<table class="ibl-data-table"><thead><tr><th>First Team</th><th>Second Team</th><th>Third Team</th></tr></thead><tbody>';

        $maxRows = max(count($teams['first']), count($teams['second']), count($teams['third']));
        for ($i = 0; $i < $maxRows; $i++) {
            $first = isset($teams['first'][$i]) ? self::renderPlayerName($teams['first'][$i], $playerIds) : '';
            $second = isset($teams['second'][$i]) ? self::renderPlayerName($teams['second'][$i], $playerIds) : '';
            $third = isset($teams['third'][$i]) ? self::renderPlayerName($teams['third'][$i], $playerIds) : '';
            $html .= '<tr><td>' . $first . '</td><td>' . $second . '</td><td>' . $third . '</td></tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * @param array<int, list<array{winner: string, loser: string, loserGames: int}>> $bracket
     * @param array<string, array{color1: string, color2: string, teamid: int}> $teamColors
     */
    private function renderPlayoffBracket(array $bracket, array $teamColors, int $year): string
    {
        if ($bracket === []) {
            return '';
        }

        $html = '<div class="season-archive-section"><h3>Playoff Bracket</h3>';
        $html .= '<table class="ibl-data-table"><thead><tr><th>Round</th><th>Winner</th><th>Loser</th><th>Result</th></tr></thead><tbody>';

        $isFirstRound = true;
        foreach ($bracket as $round => $seriesList) {
            foreach ($seriesList as $index => $series) {
                $rowClass = ($index === 0 && !$isFirstRound) ? ' class="bracket-round-start"' : '';
                $html .= '<tr' . $rowClass . '>';
                if ($index === 0) {
                    $roundName = self::ROUND_NAMES[$round] ?? 'Round ' . $round;
                    $html .= '<td rowspan="' . count($seriesList) . '">' . self::esc($roundName) . '</td>';
                }
                $html .= self::renderTeamCell($series['winner'], $teamColors, $year);
                $html .= self::renderTeamCell($series['loser'], $teamColors, $year);
                $html .= '<td>4-' . $series['loserGames'] . '</td>';
                $html .= '</tr>';
            }
            $isFirstRound = false;
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * @param list<array{team: string, wins: int, losses: int}> $standings
     * @param array<string, array{color1: string, color2: string, teamid: int}> $teamColors
     */
    private function renderHeatStandings(array $standings, array $teamColors, int $year): string
    {
        if ($standings === []) {
            return '';
        }

        $html = '<div class="season-archive-section"><h3>H.E.A.T. Standings</h3>';
        $html .= '<table class="ibl-data-table"><thead><tr><th>Team</th><th>W-L</th></tr></thead><tbody>';

        foreach ($standings as $row) {
            $teamName = $row['team'];
            $html .= '<tr>';
            $html .= self::renderTeamCell($teamName, $teamColors, $year);
            $html .= '<td>' . $row['wins'] . '-' . $row['losses'] . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * @param array<string, string> $teamAwards
     * @param array<string, array{color1: string, color2: string, teamid: int}> $teamColors
     */
    private function renderTeamAwardsSection(array $teamAwards, array $teamColors, int $year): string
    {
        if ($teamAwards === []) {
            return '';
        }

        $html = '<div class="season-archive-section"><h3>Champions &amp; Awards</h3>';
        $html .= '<table class="ibl-data-table"><thead><tr><th>Award</th><th>Team</th></tr></thead><tbody>';

        foreach ($teamAwards as $awardName => $teamName) {
            $html .= '<tr><td>' . self::esc($awardName) . '</td>';
            $html .= self::renderTeamCell($teamName, $teamColors, $year) . '</tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * @param array{ibl: list<string>, heat: list<string>} $rosters
     * @param array<string, int> $playerIds
     */
    private function renderChampionRosters(array $rosters, array $playerIds): string
    {
        if ($rosters['ibl'] === [] && $rosters['heat'] === []) {
            return '';
        }

        $html = '<div class="season-archive-section"><h3>Championship Rosters</h3>';
        $html .= '<div class="season-archive-roster-grid">';

        if ($rosters['ibl'] !== []) {
            $html .= '<div><h4>IBL Champions</h4>';
            $html .= '<table class="ibl-data-table"><thead><tr><th>Player</th></tr></thead><tbody>';
            foreach ($rosters['ibl'] as $player) {
                $html .= '<tr><td>' . self::renderPlayerName($player, $playerIds) . '</td></tr>';
            }
            $html .= '</tbody></table></div>';
        }

        if ($rosters['heat'] !== []) {
            $html .= '<div><h4>H.E.A.T. Champions</h4>';
            $html .= '<table class="ibl-data-table"><thead><tr><th>Player</th></tr></thead><tbody>';
            foreach ($rosters['heat'] as $player) {
                $html .= '<tr><td>' . self::renderPlayerName($player, $playerIds) . '</td></tr>';
            }
            $html .= '</tbody></table></div>';
        }

        $html .= '</div></div>';

        return $html;
    }

    /**
     * @param array{east: list<string>, west: list<string>} $rosters
     * @param array<string, int> $playerIds
     */
    private function renderAllStarRosterTables(array $rosters, array $playerIds): string
    {
        if ($rosters['east'] === [] && $rosters['west'] === []) {
            return '';
        }

        $html = '<div class="season-archive-section"><h3>All-Star Rosters</h3>';
        $html .= '<div class="season-archive-roster-grid">';

        if ($rosters['east'] !== []) {
            $html .= '<div><h4>Eastern Conference</h4>';
            $html .= '<table class="ibl-data-table"><thead><tr><th>Player</th></tr></thead><tbody>';
            foreach ($rosters['east'] as $player) {
                $html .= '<tr><td>' . self::renderPlayerName($player, $playerIds) . '</td></tr>';
            }
            $html .= '</tbody></table></div>';
        }

        if ($rosters['west'] !== []) {
            $html .= '<div><h4>Western Conference</h4>';
            $html .= '<table class="ibl-data-table"><thead><tr><th>Player</th></tr></thead><tbody>';
            foreach ($rosters['west'] as $player) {
                $html .= '<tr><td>' . self::renderPlayerName($player, $playerIds) . '</td></tr>';
            }
            $html .= '</tbody></table></div>';
        }

        $html .= '</div></div>';

        return $html;
    }
}
