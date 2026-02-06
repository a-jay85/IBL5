<?php

declare(strict_types=1);

namespace Tests\SeasonArchive;

use PHPUnit\Framework\TestCase;
use SeasonArchive\SeasonArchiveView;
use SeasonArchive\Contracts\SeasonArchiveViewInterface;

/**
 * SeasonArchiveViewTest - Tests for SeasonArchiveView HTML rendering
 *
 * @covers \SeasonArchive\SeasonArchiveView
 */
class SeasonArchiveViewTest extends TestCase
{
    private SeasonArchiveView $view;

    protected function setUp(): void
    {
        $this->view = new SeasonArchiveView();
    }

    public function testImplementsSeasonArchiveViewInterface(): void
    {
        $this->assertInstanceOf(SeasonArchiveViewInterface::class, $this->view);
    }

    public function testRenderIndexContainsTableStructure(): void
    {
        $result = $this->view->renderIndex([]);

        $this->assertStringContainsString('<table', $result);
        $this->assertStringContainsString('</table>', $result);
        $this->assertStringContainsString('ibl-data-table', $result);
    }

    public function testRenderIndexContainsTitle(): void
    {
        $result = $this->view->renderIndex([]);

        $this->assertStringContainsString('IBL Season Archive', $result);
        $this->assertStringContainsString('ibl-title', $result);
    }

    public function testRenderIndexContainsTableHeaders(): void
    {
        $result = $this->view->renderIndex([]);

        $this->assertStringContainsString('Season', $result);
        $this->assertStringContainsString('IBL Champion', $result);
        $this->assertStringContainsString('HEAT Champion', $result);
        $this->assertStringContainsString('MVP', $result);
    }

    public function testRenderIndexContainsSeasonLinks(): void
    {
        $seasons = [
            ['year' => 1989, 'label' => 'Season I (1988-89)', 'iblChampion' => 'Clippers', 'heatChampion' => 'Rockets', 'mvp' => 'Arvydas Sabonis'],
        ];

        $result = $this->view->renderIndex($seasons);

        $this->assertStringContainsString('modules.php?name=SeasonArchive&amp;year=1989', $result);
        $this->assertStringContainsString('Season I (1988-89)', $result);
        $this->assertStringContainsString('Clippers', $result);
        $this->assertStringContainsString('Rockets', $result);
        $this->assertStringContainsString('Arvydas Sabonis', $result);
    }

    public function testRenderIndexEscapesHtmlEntities(): void
    {
        $seasons = [
            ['year' => 1989, 'label' => 'Season <script>alert(1)</script>', 'iblChampion' => 'Team&Name', 'heatChampion' => 'Test', 'mvp' => 'Test'],
        ];

        $result = $this->view->renderIndex($seasons);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&amp;Name', $result);
    }

    public function testRenderSeasonDetailContainsAllSections(): void
    {
        $seasonData = $this->createMinimalSeasonData();

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringContainsString('Tournaments', $result);
        $this->assertStringContainsString('All-Star Weekend', $result);
        $this->assertStringContainsString('Major Awards', $result);
        $this->assertStringContainsString('Statistical Leaders', $result);
        $this->assertStringContainsString('All-League Teams', $result);
        $this->assertStringContainsString('All-Defensive Teams', $result);
        $this->assertStringContainsString('All-Rookie Teams', $result);
    }

    public function testRenderSeasonDetailContainsBackLink(): void
    {
        $seasonData = $this->createMinimalSeasonData();

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringContainsString('modules.php?name=SeasonArchive', $result);
        $this->assertStringContainsString('Back to Season Archive', $result);
    }

    public function testRenderSeasonDetailContainsSeasonLeadersLink(): void
    {
        $seasonData = $this->createMinimalSeasonData();

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringContainsString('modules.php?name=SeasonLeaderboards', $result);
        $this->assertStringContainsString('Season Leaders', $result);
    }

    public function testRenderSeasonDetailDoesNotContainJsbExportLink(): void
    {
        $seasonData = $this->createMinimalSeasonData();

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringNotContainsString('JSB Export', $result);
        $this->assertStringNotContainsString('jsb_export', $result);
    }

    public function testRenderSeasonDetailContainsSeasonLabel(): void
    {
        $seasonData = $this->createMinimalSeasonData();

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringContainsString('Season I (1988-89)', $result);
    }

    public function testRenderSeasonDetailEscapesPlayerNames(): void
    {
        $seasonData = $this->createMinimalSeasonData();
        $seasonData['majorAwards']['mvp'] = 'Player <script>alert(1)</script>';

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testAllStarGameMvpShowsSingleMvp(): void
    {
        $seasonData = $this->createMinimalSeasonData();
        $seasonData['allStarWeekend']['gameMvps'] = ['Armon Gilliam'];

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringContainsString('All-Star Game MVP', $result);
        $this->assertStringNotContainsString('Co-MVPs', $result);
        $this->assertStringContainsString('Armon Gilliam', $result);
    }

    public function testAllStarGameCoMvpsShowsBothPlayers(): void
    {
        $seasonData = $this->createMinimalSeasonData();
        $seasonData['allStarWeekend']['gameMvps'] = ['Kobe Bryant', 'Michael Jordan'];

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringContainsString('All-Star Game Co-MVPs', $result);
        $this->assertStringContainsString('Kobe Bryant', $result);
        $this->assertStringContainsString('Michael Jordan', $result);
    }

    public function testRenderSeasonDetailShowsPlayoffBracket(): void
    {
        $seasonData = $this->createMinimalSeasonData();
        $seasonData['playoffBracket'] = [
            1 => [
                ['winner' => 'Raptors', 'loser' => 'Pelicans', 'loserGames' => 0],
            ],
            4 => [
                ['winner' => 'Clippers', 'loser' => 'Raptors', 'loserGames' => 3],
            ],
        ];

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringContainsString('Playoff Bracket', $result);
        $this->assertStringContainsString('Raptors', $result);
        $this->assertStringContainsString('4-0', $result);
        $this->assertStringContainsString('4-3', $result);
    }

    public function testRenderSeasonDetailShowsHeatStandingsWithTeamColors(): void
    {
        $seasonData = $this->createMinimalSeasonData();
        $seasonData['heatStandings'] = [
            ['team' => 'Clippers', 'wins' => 10, 'losses' => 2],
        ];
        $seasonData['teamColors'] = [
            'Clippers' => ['color1' => 'C8102E', 'color2' => 'FFFFFF', 'teamid' => 5],
        ];

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringContainsString('H.E.A.T. Standings', $result);
        $this->assertStringContainsString('C8102E', $result);
        $this->assertStringContainsString('FFFFFF', $result);
        // W-L combined column
        $this->assertStringContainsString('10-2', $result);
    }

    public function testHeatStandingsUsesCombinedWinLossColumn(): void
    {
        $seasonData = $this->createMinimalSeasonData();
        $seasonData['heatStandings'] = [
            ['team' => 'Clippers', 'wins' => 10, 'losses' => 2],
        ];

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringContainsString('<th>W-L</th>', $result);
        $this->assertStringContainsString('10-2', $result);
    }

    public function testRenderSeasonDetailShowsMergedRostersSection(): void
    {
        $seasonData = $this->createMinimalSeasonData();
        $seasonData['championRosters'] = [
            'ibl' => ['Player A', 'Player B'],
            'heat' => ['Player C'],
        ];
        $seasonData['allStarRosters'] = [
            'east' => ['East Player 1', 'East Player 2'],
            'west' => ['West Player 1'],
        ];

        $result = $this->view->renderSeasonDetail($seasonData);

        // Single merged "Rosters" section replaces old separate sections
        $this->assertStringContainsString('>Rosters</h3>', $result);
        $this->assertStringNotContainsString('Championship Rosters', $result);
        $this->assertStringNotContainsString('All-Star Rosters', $result);
        // All three roster columns present
        $this->assertStringContainsString('IBL Champions', $result);
        $this->assertStringContainsString('Eastern Conf. All-Stars', $result);
        $this->assertStringContainsString('Western Conf. All-Stars', $result);
        $this->assertStringContainsString('H.E.A.T. Champions', $result);
        // Player names present
        $this->assertStringContainsString('Player A', $result);
        $this->assertStringContainsString('Player B', $result);
        $this->assertStringContainsString('Player C', $result);
        $this->assertStringContainsString('East Player 1', $result);
        $this->assertStringContainsString('West Player 1', $result);
        // Uses flexbox layout
        $this->assertStringContainsString('season-archive-roster-flex', $result);
        $this->assertStringContainsString('season-archive-roster-col', $result);
    }

    public function testRostersSectionShowsAllStarCoachCaptions(): void
    {
        $seasonData = $this->createMinimalSeasonData();
        $seasonData['allStarRosters'] = [
            'east' => ['East Player 1'],
            'west' => ['West Player 1'],
        ];
        $seasonData['allStarCoaches'] = [
            'east' => ['Ross Gates'],
            'west' => ['Brandon Tomyoy'],
        ];

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringContainsString('Head Coach: Ross Gates', $result);
        $this->assertStringContainsString('Head Coach: Brandon Tomyoy', $result);
        $this->assertStringContainsString('season-archive-coach-caption', $result);
    }

    public function testRostersSectionShowsMultipleCoachesLabel(): void
    {
        $seasonData = $this->createMinimalSeasonData();
        $seasonData['allStarRosters'] = [
            'east' => ['East Player 1'],
            'west' => ['West Player 1'],
        ];
        $seasonData['allStarCoaches'] = [
            'east' => ['Coach A', 'Coach B'],
            'west' => [],
        ];

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringContainsString('Head Coaches: Coach A, Coach B', $result);
    }

    public function testRostersSectionShowsIblChampionLogo(): void
    {
        $seasonData = $this->createMinimalSeasonData();
        $seasonData['championRosters'] = [
            'ibl' => ['Player A'],
            'heat' => [],
        ];
        $seasonData['teamColors'] = [
            'Clippers' => ['color1' => 'C8102E', 'color2' => 'FFFFFF', 'teamid' => 5],
        ];

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringContainsString('season-archive-champion-logo', $result);
        $this->assertStringContainsString('new5.png', $result);
        $this->assertStringContainsString('alt="Clippers"', $result);
        // Logo appears after the "IBL Champions" heading (below the roster table)
        $headingPos = strpos($result, 'IBL Champions</h4>');
        $logoPos = strpos($result, 'class="season-archive-champion-logo"');
        $this->assertNotFalse($headingPos);
        $this->assertNotFalse($logoPos);
        $this->assertGreaterThan($headingPos, $logoPos);
    }

    public function testRostersSectionShowsIblChampionCoachCaption(): void
    {
        $seasonData = $this->createMinimalSeasonData();
        $seasonData['championRosters'] = [
            'ibl' => ['Player A'],
            'heat' => [],
        ];
        $seasonData['iblChampionCoach'] = 'Brandon Tomyoy';

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringContainsString('Head Coach: Brandon Tomyoy', $result);
        // Coach caption appears before "IBL Champions" heading
        $captionPos = strpos($result, 'Head Coach: Brandon Tomyoy');
        $headingPos = strpos($result, 'IBL Champions</h4>');
        $this->assertNotFalse($captionPos);
        $this->assertNotFalse($headingPos);
        $this->assertLessThan($headingPos, $captionPos);
    }

    public function testRenderSeasonDetailShowsTeamAwards(): void
    {
        $seasonData = $this->createMinimalSeasonData();
        $seasonData['teamAwards'] = [
            'Pacific Division Champions' => 'Clippers',
            'IBL Champions' => 'Clippers',
        ];

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringContainsString('Champions &amp; Awards', $result);
        $this->assertStringContainsString('Pacific Division Champions', $result);
    }

    public function testPlayerCellRenderedWithLinkAndPhoto(): void
    {
        $seasonData = $this->createMinimalSeasonData();
        $seasonData['majorAwards']['mvp'] = 'Test MVP';
        $seasonData['playerIds'] = ['Test MVP' => 42];

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringContainsString('ibl-player-cell', $result);
        $this->assertStringContainsString('pid=42', $result);
        $this->assertStringContainsString('ibl-player-photo', $result);
    }

    public function testTeamCellRenderedWithColorsAndLogo(): void
    {
        $seasonData = $this->createMinimalSeasonData();
        $seasonData['heatStandings'] = [
            ['team' => 'Clippers', 'wins' => 10, 'losses' => 2],
        ];
        $seasonData['teamColors'] = [
            'Clippers' => ['color1' => 'C8102E', 'color2' => 'FFFFFF', 'teamid' => 5],
        ];

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringContainsString('ibl-team-cell--colored', $result);
        $this->assertStringContainsString('ibl-team-cell__logo', $result);
        $this->assertStringContainsString('ibl-team-cell__name', $result);
        $this->assertStringContainsString('teamID=5', $result);
        $this->assertStringContainsString('new5.png', $result);
    }

    public function testTournamentsBracketColumnRemoved(): void
    {
        $seasonData = $this->createMinimalSeasonData();

        $result = $this->view->renderSeasonDetail($seasonData);

        // Tournaments section should have Event and Champion headers only, no Bracket header
        $this->assertStringContainsString('season-archive-bracket-hint', $result);
        $this->assertStringContainsString('Click an event name', $result);
        // Event names should be links
        $this->assertStringContainsString('bracket-link', $result);
    }

    public function testTournamentsEventNamesAreLinks(): void
    {
        $seasonData = $this->createMinimalSeasonData();

        $result = $this->view->renderSeasonDetail($seasonData);

        // H.E.A.T. Championship event name should be a link to the heatUrl
        $this->assertStringContainsString('href="https://challonge.com/IBLheat88"', $result);
        $this->assertStringContainsString('>H.E.A.T. Championship</a>', $result);
    }

    public function testPlayoffBracketRoundBorders(): void
    {
        $seasonData = $this->createMinimalSeasonData();
        $seasonData['playoffBracket'] = [
            1 => [
                ['winner' => 'Raptors', 'loser' => 'Pelicans', 'loserGames' => 0],
            ],
            2 => [
                ['winner' => 'Raptors', 'loser' => 'Nets', 'loserGames' => 3],
            ],
        ];

        $result = $this->view->renderSeasonDetail($seasonData);

        // bracket-round-start class should be in the CSS
        $this->assertStringContainsString('bracket-round-start', $result);
    }

    public function testGmOfYearDisplaysTeamLink(): void
    {
        $seasonData = $this->createMinimalSeasonData();
        $seasonData['majorAwards']['gmOfYear'] = ['name' => 'Ross Gates', 'team' => 'Bulls'];
        $seasonData['teamIds'] = ['Bulls' => 3];

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringContainsString('Ross Gates', $result);
        $this->assertStringContainsString('teamID=3', $result);
        $this->assertStringContainsString('Bulls', $result);
    }

    public function testRenderIndexUsesTeamCellsForChampions(): void
    {
        $seasons = [
            ['year' => 1989, 'label' => 'Season I (1988-89)', 'iblChampion' => 'Clippers', 'heatChampion' => 'Rockets', 'mvp' => 'Arvydas Sabonis'],
        ];
        $teamColors = [
            'Clippers' => ['color1' => 'C8102E', 'color2' => 'FFFFFF', 'teamid' => 5],
            'Rockets' => ['color1' => 'CE1141', 'color2' => '000000', 'teamid' => 12],
        ];

        $result = $this->view->renderIndex($seasons, $teamColors);

        $this->assertStringContainsString('ibl-team-cell--colored', $result);
        $this->assertStringContainsString('C8102E', $result);
        $this->assertStringContainsString('CE1141', $result);
        $this->assertStringContainsString('teamID=5', $result);
        $this->assertStringContainsString('teamID=12', $result);
        $this->assertStringContainsString('new5.png', $result);
        $this->assertStringContainsString('new12.png', $result);
    }

    public function testRenderIndexUsesPlayerCellForMvp(): void
    {
        $seasons = [
            ['year' => 1989, 'label' => 'Season I (1988-89)', 'iblChampion' => 'Clippers', 'heatChampion' => 'Rockets', 'mvp' => 'Arvydas Sabonis'],
        ];
        $playerIds = ['Arvydas Sabonis' => 100];

        $result = $this->view->renderIndex($seasons, [], $playerIds);

        $this->assertStringContainsString('ibl-player-cell', $result);
        $this->assertStringContainsString('pid=100', $result);
        $this->assertStringContainsString('Arvydas Sabonis', $result);
    }

    public function testRenderIndexOmitsInlineStylesAfterCssCentralization(): void
    {
        $seasons = [
            ['year' => 1989, 'label' => 'Season I (1988-89)', 'iblChampion' => 'Clippers', 'heatChampion' => 'Rockets', 'mvp' => 'Arvydas Sabonis'],
        ];
        $teamColors = [
            'Clippers' => ['color1' => 'C8102E', 'color2' => 'FFFFFF', 'teamid' => 5],
        ];

        $result = $this->view->renderIndex($seasons, $teamColors);

        // CSS is now centralized in design/components/season-archive.css
        $this->assertStringNotContainsString('<style>', $result);
    }

    public function testRenderIndexWithoutEnrichmentDataOmitsStyles(): void
    {
        $result = $this->view->renderIndex([]);

        $this->assertStringNotContainsString('<style>', $result);
    }

    public function testTeamAwardsUseTeamCells(): void
    {
        $seasonData = $this->createMinimalSeasonData();
        $seasonData['teamAwards'] = [
            'IBL Champions' => 'Clippers',
        ];
        $seasonData['teamColors'] = [
            'Clippers' => ['color1' => 'C8102E', 'color2' => 'FFFFFF', 'teamid' => 5],
        ];

        $result = $this->view->renderSeasonDetail($seasonData);

        $this->assertStringContainsString('ibl-team-cell--colored', $result);
        $this->assertStringContainsString('teamID=5', $result);
    }

    /**
     * Create minimal season data for testing
     *
     * @return array{
     *     year: int,
     *     label: string,
     *     tournaments: array{heatChampion: string, heatUrl: string, oneOnOneChampion: string, rookieOneOnOneChampion: string, oneOnOneUrl: string, iblFinalsWinner: string, iblFinalsLoser: string, iblFinalsLoserGames: int, playoffsUrl: string},
     *     allStarWeekend: array{gameMvps: list<string>, slamDunkWinner: string, threePointWinner: string, rookieSophomoreMvp: string, slamDunkParticipants: list<string>, threePointParticipants: list<string>, rookieSophomoreParticipants: list<string>},
     *     majorAwards: array{mvp: string, dpoy: string, roy: string, sixthMan: string, gmOfYear: array{name: string, team: string}, finalsMvp: string},
     *     allLeagueTeams: array{first: list<string>, second: list<string>, third: list<string>},
     *     allDefensiveTeams: array{first: list<string>, second: list<string>, third: list<string>},
     *     allRookieTeams: array{first: list<string>, second: list<string>, third: list<string>},
     *     statisticalLeaders: array{scoring: string, rebounds: string, assists: string, steals: string, blocks: string},
     *     playoffBracket: array<int, list<array{winner: string, loser: string, loserGames: int}>>,
     *     heatStandings: list<array{team: string, wins: int, losses: int}>,
     *     teamAwards: array<string, string>,
     *     championRosters: array{ibl: list<string>, heat: list<string>},
     *     allStarRosters: array{east: list<string>, west: list<string>},
     *     allStarCoaches: array{east: list<string>, west: list<string>},
     *     iblChampionCoach: string,
     *     teamColors: array<string, array{color1: string, color2: string, teamid: int}>,
     *     playerIds: array<string, int>,
     *     teamIds: array<string, int>
     * }
     */
    private function createMinimalSeasonData(): array
    {
        return [
            'year' => 1989,
            'label' => 'Season I (1988-89)',
            'tournaments' => [
                'heatChampion' => 'Rockets',
                'heatUrl' => 'https://challonge.com/IBLheat88',
                'oneOnOneChampion' => 'Test Player',
                'rookieOneOnOneChampion' => '',
                'oneOnOneUrl' => 'https://challonge.com/users/coldbeatle89/tournaments',
                'iblFinalsWinner' => 'Clippers',
                'iblFinalsLoser' => 'Raptors',
                'iblFinalsLoserGames' => 3,
                'playoffsUrl' => 'https://challonge.com/iblplayoffs1989',
            ],
            'allStarWeekend' => [
                'gameMvps' => ['Test ASG MVP'],
                'slamDunkWinner' => 'Test Dunker',
                'threePointWinner' => 'Test Shooter',
                'rookieSophomoreMvp' => 'Test RS MVP',
                'slamDunkParticipants' => [],
                'threePointParticipants' => [],
                'rookieSophomoreParticipants' => [],
            ],
            'majorAwards' => [
                'mvp' => 'Arvydas Sabonis',
                'dpoy' => 'Hakeem Olajuwon',
                'roy' => 'Test Rookie',
                'sixthMan' => 'Test 6th Man',
                'gmOfYear' => ['name' => 'Test GM', 'team' => 'Test Team'],
                'finalsMvp' => 'Test Finals MVP',
            ],
            'allLeagueTeams' => ['first' => ['Player 1'], 'second' => ['Player 2'], 'third' => ['Player 3']],
            'allDefensiveTeams' => ['first' => ['Player 4'], 'second' => ['Player 5'], 'third' => ['Player 6']],
            'allRookieTeams' => ['first' => ['Player 7'], 'second' => ['Player 8'], 'third' => ['Player 9']],
            'statisticalLeaders' => [
                'scoring' => 'Test Scorer',
                'rebounds' => 'Test Rebounder',
                'assists' => 'Test Assister',
                'steals' => 'Test Stealer',
                'blocks' => 'Test Blocker',
            ],
            'playoffBracket' => [],
            'heatStandings' => [],
            'teamAwards' => [],
            'championRosters' => ['ibl' => [], 'heat' => []],
            'allStarRosters' => ['east' => [], 'west' => []],
            'allStarCoaches' => ['east' => [], 'west' => []],
            'iblChampionCoach' => '',
            'teamColors' => [],
            'playerIds' => [],
            'teamIds' => [],
        ];
    }
}
