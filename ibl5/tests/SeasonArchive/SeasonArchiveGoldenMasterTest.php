<?php

declare(strict_types=1);

namespace Tests\SeasonArchive;

use PHPUnit\Framework\TestCase;
use SeasonArchive\SeasonArchiveIndexView;
use SeasonArchive\SeasonDetailView;

/**
 * Golden-master snapshot tests pinned against the split view classes.
 *
 * Snapshots were emitted from the unmodified SeasonArchiveView and frozen.
 * Both new view classes must reproduce byte-identical output against these pins.
 *
 * DO NOT delete or regenerate the .html files.
 * A snapshot mismatch = the split changed rendered output = fix the new view class.
 *
 * @covers \SeasonArchive\SeasonArchiveIndexView
 * @covers \SeasonArchive\SeasonDetailView
 */
class SeasonArchiveGoldenMasterTest extends TestCase
{
    use SnapshotTestTrait;

    private SeasonArchiveIndexView $indexView;
    private SeasonDetailView $detailView;

    protected function setUp(): void
    {
        $this->indexView = new SeasonArchiveIndexView();
        $this->detailView = new SeasonDetailView();
    }

    public function testDetailRichSnapshotMatchesUnmodifiedClass(): void
    {
        $html = $this->detailView->renderSeasonDetail($this->createRichSeasonData());

        // Branch-marker guards — these run on emit AND every future run to defend the rich dataset.
        // Fail here = the rich dataset is missing a branch, not a stale snapshot.
        $this->assertStringContainsString('bracket-round-start', $html);         // ≥2-round bracket fired
        $this->assertStringContainsString('All-Star Game Co-MVPs', $html);       // ≥2 game MVPs
        $this->assertStringContainsString('Head Coaches', $html);                // multi-coach plural label
        $this->assertStringContainsString('H.E.A.T. Standings', $html);         // heatStandings non-empty
        $this->assertStringContainsString('Champions &amp; Awards', $html);      // teamAwards non-empty
        $this->assertStringContainsString('IBL Champions', $html);               // ibl roster column
        $this->assertStringContainsString('Eastern Conf. All-Stars', $html);     // east roster column
        $this->assertStringContainsString('Western Conf. All-Stars', $html);     // west roster column
        $this->assertStringContainsString('H.E.A.T. Champions', $html);         // heat champions roster
        $this->assertStringContainsString('name=Team&amp;op=team&amp;teamid=', $html); // gm-of-year team link
        $this->assertStringContainsString('Rookie One-on-One Tournament', $html); // rookieOneOnOneChampion non-empty

        $this->assertSnapshotMatches($html, 'detail-rich.html');
    }

    public function testDetailMinimalSnapshotMatchesUnmodifiedClass(): void
    {
        $html = $this->detailView->renderSeasonDetail($this->createMinimalSeasonData());

        // Empty-optional sections must be absent (boundary characterization).
        $this->assertStringNotContainsString('bracket-round-start', $html);
        $this->assertStringNotContainsString('H.E.A.T. Standings', $html);
        $this->assertStringNotContainsString('Champions &amp; Awards', $html);

        $this->assertSnapshotMatches($html, 'detail-minimal.html');
    }

    public function testIndexRichSnapshotMatchesUnmodifiedClass(): void
    {
        [$seasons, $teamColors, $playerIds, $teamIds] = $this->createRichIndexData();
        $html = $this->indexView->renderIndex($seasons, $teamColors, $playerIds, $teamIds);

        // Verify team cells and player cells rendered (not bare fallback paths).
        $this->assertStringContainsString('ibl-team-cell--colored', $html);
        $this->assertStringContainsString('ibl-player-cell', $html);

        $this->assertSnapshotMatches($html, 'index-rich.html');
    }

    public function testIndexBareSnapshotMatchesUnmodifiedClass(): void
    {
        [$seasons] = $this->createRichIndexData();
        $html = $this->indexView->renderIndex($seasons);

        // Bare fallback path: no team cells, no player cells, no styles block.
        $this->assertStringNotContainsString('ibl-team-cell--colored', $html);
        $this->assertStringNotContainsString('ibl-player-cell', $html);
        $this->assertStringNotContainsString('<style>', $html);

        $this->assertSnapshotMatches($html, 'index-bare.html');
    }

    /**
     * Fully-populated season detail data exercising every conditional branch.
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
    private function createRichSeasonData(): array
    {
        $teamColors = [
            'Clippers' => ['color1' => 'C8102E', 'color2' => 'FFFFFF', 'teamid' => 5],
            'Raptors'  => ['color1' => 'CE1141', 'color2' => '000000', 'teamid' => 12],
            'Pelicans' => ['color1' => '0C2340', 'color2' => 'C8102E', 'teamid' => 8],
            'Nets'     => ['color1' => '000000', 'color2' => 'FFFFFF', 'teamid' => 3],
            'Bulls'    => ['color1' => 'CE1141', 'color2' => '000000', 'teamid' => 2],
        ];

        $teamIds = [];
        foreach ($teamColors as $name => $data) {
            $teamIds[$name] = $data['teamid'];
        }

        return [
            'year'  => 1993,
            'label' => 'Season V (1992-93)',
            'tournaments' => [
                'heatChampion'            => 'Clippers',
                'heatUrl'                 => 'https://challonge.com/IBLheat92',
                'oneOnOneChampion'        => 'Arvydas Sabonis',
                'rookieOneOnOneChampion'  => 'Rookie Champion',
                'oneOnOneUrl'             => 'https://challonge.com/users/coldbeatle89/tournaments',
                'iblFinalsWinner'         => 'Clippers',
                'iblFinalsLoser'          => 'Raptors',
                'iblFinalsLoserGames'     => 2,
                'playoffsUrl'             => 'https://challonge.com/iblplayoffs1993',
            ],
            // ≥2 gameMvps → "All-Star Game Co-MVPs"
            'allStarWeekend' => [
                'gameMvps'                    => ['Kobe Bryant', 'Michael Jordan'],
                'slamDunkWinner'              => 'Dominique Wilkins',
                'threePointWinner'            => 'Larry Bird',
                'rookieSophomoreMvp'          => 'Shaquille ONeal',
                'slamDunkParticipants'        => [],
                'threePointParticipants'      => [],
                'rookieSophomoreParticipants' => [],
            ],
            'majorAwards' => [
                'mvp'        => 'Arvydas Sabonis',
                'dpoy'       => 'Hakeem Olajuwon',
                'roy'        => 'Larry Johnson',
                'sixthMan'   => 'John Starks',
                // team 'Bulls' is in teamIds → GM-of-year team-link branch fires
                'gmOfYear'   => ['name' => 'Ross Gates', 'team' => 'Bulls'],
                'finalsMvp'  => 'Clyde Drexler',
            ],
            'allLeagueTeams' => [
                'first'  => ['Arvydas Sabonis'],
                'second' => ['Patrick Ewing'],
                'third'  => ['Charles Barkley'],
            ],
            'allDefensiveTeams' => [
                'first'  => ['Hakeem Olajuwon'],
                'second' => ['Dennis Rodman'],
                'third'  => ['Gary Payton'],
            ],
            'allRookieTeams' => [
                'first'  => ['Larry Johnson'],
                'second' => ['Dikembe Mutombo'],
                'third'  => ['Billy Owens'],
            ],
            'statisticalLeaders' => [
                'scoring'  => 'Arvydas Sabonis',
                'rebounds' => 'Dennis Rodman',
                'assists'  => 'Magic Johnson',
                'steals'   => 'Gary Payton',
                'blocks'   => 'Hakeem Olajuwon',
            ],
            // ≥2 rounds → second round triggers bracket-round-start
            'playoffBracket' => [
                1 => [
                    ['winner' => 'Clippers', 'loser' => 'Pelicans', 'loserGames' => 0],
                    ['winner' => 'Raptors',  'loser' => 'Nets',     'loserGames' => 1],
                ],
                4 => [
                    ['winner' => 'Clippers', 'loser' => 'Raptors', 'loserGames' => 2],
                ],
            ],
            // non-empty → H.E.A.T. Standings section renders
            'heatStandings' => [
                ['team' => 'Clippers', 'wins' => 8, 'losses' => 2],
                ['team' => 'Raptors',  'wins' => 6, 'losses' => 4],
            ],
            // non-empty → Champions & Awards section renders
            'teamAwards' => [
                'Pacific Division Champions' => 'Clippers',
                'IBL Champions'              => 'Clippers',
            ],
            // ibl + heat non-empty → both roster sections render
            'championRosters' => [
                'ibl'  => ['Arvydas Sabonis', 'Clyde Drexler'],
                'heat' => ['Heat Champ Player'],
            ],
            // east + west non-empty → both all-star columns render
            'allStarRosters' => [
                'east' => ['East Player 1', 'East Player 2'],
                'west' => ['West Player 1'],
            ],
            // west has ≥2 coaches → "Head Coaches" plural label
            'allStarCoaches' => [
                'east' => ['Coach Eastern'],
                'west' => ['Coach Western A', 'Coach Western B'],
            ],
            'iblChampionCoach' => 'Brandon Tomyoy',
            'teamColors'       => $teamColors,
            'playerIds'        => [
                'Arvydas Sabonis' => 100,
                'Clyde Drexler'   => 101,
            ],
            'teamIds' => $teamIds,
        ];
    }

    /**
     * Minimal season data — empty optional sections (from existing SeasonArchiveViewTest).
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
            'year'  => 1989,
            'label' => 'Season I (1988-89)',
            'tournaments' => [
                'heatChampion'            => 'Rockets',
                'heatUrl'                 => 'https://challonge.com/IBLheat88',
                'oneOnOneChampion'        => 'Test Player',
                'rookieOneOnOneChampion'  => '',
                'oneOnOneUrl'             => 'https://challonge.com/users/coldbeatle89/tournaments',
                'iblFinalsWinner'         => 'Clippers',
                'iblFinalsLoser'          => 'Raptors',
                'iblFinalsLoserGames'     => 3,
                'playoffsUrl'             => 'https://challonge.com/iblplayoffs1989',
            ],
            'allStarWeekend' => [
                'gameMvps'                    => ['Test ASG MVP'],
                'slamDunkWinner'              => 'Test Dunker',
                'threePointWinner'            => 'Test Shooter',
                'rookieSophomoreMvp'          => 'Test RS MVP',
                'slamDunkParticipants'        => [],
                'threePointParticipants'      => [],
                'rookieSophomoreParticipants' => [],
            ],
            'majorAwards' => [
                'mvp'       => 'Arvydas Sabonis',
                'dpoy'      => 'Hakeem Olajuwon',
                'roy'       => 'Test Rookie',
                'sixthMan'  => 'Test 6th Man',
                'gmOfYear'  => ['name' => 'Test GM', 'team' => 'Test Team'],
                'finalsMvp' => 'Test Finals MVP',
            ],
            'allLeagueTeams'    => ['first' => ['Player 1'], 'second' => ['Player 2'], 'third' => ['Player 3']],
            'allDefensiveTeams' => ['first' => ['Player 4'], 'second' => ['Player 5'], 'third' => ['Player 6']],
            'allRookieTeams'    => ['first' => ['Player 7'], 'second' => ['Player 8'], 'third' => ['Player 9']],
            'statisticalLeaders' => [
                'scoring'  => 'Test Scorer',
                'rebounds' => 'Test Rebounder',
                'assists'  => 'Test Assister',
                'steals'   => 'Test Stealer',
                'blocks'   => 'Test Blocker',
            ],
            'playoffBracket'  => [],
            'heatStandings'   => [],
            'teamAwards'      => [],
            'championRosters' => ['ibl' => [], 'heat' => []],
            'allStarRosters'  => ['east' => [], 'west' => []],
            'allStarCoaches'  => ['east' => [], 'west' => []],
            'iblChampionCoach' => '',
            'teamColors'      => [],
            'playerIds'       => [],
            'teamIds'         => [],
        ];
    }

    /**
     * Returns [seasons, teamColors, playerIds, teamIds] for index snapshot tests.
     *
     * @return array{
     *   list<array{year: int, label: string, iblChampion: string, heatChampion: string, mvp: string}>,
     *   array<string, array{color1: string, color2: string, teamid: int}>,
     *   array<string, int>,
     *   array<string, int>
     * }
     */
    private function createRichIndexData(): array
    {
        $seasons = [
            ['year' => 1993, 'label' => 'Season V (1992-93)',   'iblChampion' => 'Clippers', 'heatChampion' => 'Raptors',  'mvp' => 'Arvydas Sabonis'],
            ['year' => 1992, 'label' => 'Season IV (1991-92)',  'iblChampion' => 'Nets',     'heatChampion' => 'Bulls',    'mvp' => 'Patrick Ewing'],
            ['year' => 1989, 'label' => 'Season I (1988-89)',   'iblChampion' => 'Clippers', 'heatChampion' => 'Rockets',  'mvp' => 'Hakeem Olajuwon'],
        ];
        $teamColors = [
            'Clippers' => ['color1' => 'C8102E', 'color2' => 'FFFFFF', 'teamid' => 5],
            'Raptors'  => ['color1' => 'CE1141', 'color2' => '000000', 'teamid' => 12],
            'Nets'     => ['color1' => '000000', 'color2' => 'FFFFFF', 'teamid' => 3],
            'Bulls'    => ['color1' => 'CE1141', 'color2' => '000000', 'teamid' => 2],
        ];
        $playerIds = [
            'Arvydas Sabonis'  => 100,
            'Patrick Ewing'    => 101,
            'Hakeem Olajuwon'  => 102,
        ];
        $teamIds = [];
        foreach ($teamColors as $name => $data) {
            $teamIds[$name] = $data['teamid'];
        }

        return [$seasons, $teamColors, $playerIds, $teamIds];
    }
}
