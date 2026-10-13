<?php

declare(strict_types=1);

namespace Tests\SeasonArchive;

use PHPUnit\Framework\TestCase;
use SeasonArchive\Contracts\SeasonArchiveRepositoryInterface;
use SeasonArchive\SeasonArchiveService;

/**
 * Characterization pin for SeasonArchiveService::getSeasonDetail().
 *
 * Pins the full returned array (value types and key order), the order in which
 * player names reach getPlayerIdsByNames(), and the repository call order.
 *
 * @covers \SeasonArchive\SeasonArchiveService
 */
class SeasonArchiveServiceDetailCharacterizationTest extends TestCase
{
    private const EXPECTED_NAME_ORDER = [
        'Alpha OneOnOne',
        'Bravo RookieOneOnOne',
        'Charlie AsgMvp',
        'Delta AsgCoMvp',
        'Echo DunkWinner',
        'Foxtrot ThreeWinner',
        'Golf RookieSophMvp',
        'Hotel Dunker',
        'India Shooter',
        'Juliet RookieSoph',
        'Kilo Mvp',
        'Lima Dpoy',
        'Mike Roy',
        'November SixthMan',
        'Oscar FinalsMvp',
        'Papa AllLeague1',
        'Quebec AllLeague2',
        'Romeo AllLeague3',
        'Sierra AllDef1',
        'Tango AllDef2',
        'Uniform AllDef3',
        'Victor AllRookie1',
        'Whiskey AllRookie2',
        'Xray AllRookie3',
        'Yankee Scorer',
        'Zulu Rebounder',
        'Ada Assister',
        'Bea Stealer',
        'Cy Blocker',
        'Dee IblChamp',
        'Eve HeatChamp',
        'Fay East',
        'Gus West',
    ];

    /** @var list<string> */
    private array $calls = [];

    /** @var list<string> */
    private array $lookedUpNames = [];

    public function testGetSeasonDetailReturnsFullyPinnedArrayInKeyOrder(): void
    {
        $service = $this->buildService(self::awardsFixture());

        $playerIds = [];
        foreach (self::EXPECTED_NAME_ORDER as $i => $name) {
            $playerIds[$name] = 101 + $i;
        }

        $expected = [
            'year' => 2000,
            'label' => 'Season XII (1999-00)',
            'tournaments' => [
                'heatChampion' => 'Bulls',
                'heatUrl' => 'https://challonge.com/IBLheat99',
                'oneOnOneChampion' => 'Alpha OneOnOne',
                'rookieOneOnOneChampion' => 'Bravo RookieOneOnOne',
                'oneOnOneUrl' => 'https://challonge.com/users/coldbeatle89/tournaments',
                'iblFinalsWinner' => 'Hawks',
                'iblFinalsLoser' => 'Nets',
                'iblFinalsLoserGames' => 2,
                'playoffsUrl' => 'https://challonge.com/iblplayoffs2000',
            ],
            'allStarWeekend' => [
                'gameMvps' => ['Charlie AsgMvp', 'Delta AsgCoMvp'],
                'slamDunkWinner' => 'Echo DunkWinner',
                'threePointWinner' => 'Foxtrot ThreeWinner',
                'rookieSophomoreMvp' => 'Golf RookieSophMvp',
                'slamDunkParticipants' => ['Echo DunkWinner', 'Hotel Dunker'],
                'threePointParticipants' => ['India Shooter'],
                'rookieSophomoreParticipants' => ['Juliet RookieSoph'],
            ],
            'majorAwards' => [
                'mvp' => 'Kilo Mvp',
                'dpoy' => 'Lima Dpoy',
                'roy' => 'Mike Roy',
                'sixthMan' => 'November SixthMan',
                'gmOfYear' => ['name' => 'Gina Gm', 'team' => 'Hawks'],
                'finalsMvp' => 'Oscar FinalsMvp',
            ],
            'allLeagueTeams' => [
                'first' => ['Papa AllLeague1'],
                'second' => ['Quebec AllLeague2'],
                'third' => ['Romeo AllLeague3'],
            ],
            'allDefensiveTeams' => [
                'first' => ['Sierra AllDef1'],
                'second' => ['Tango AllDef2'],
                'third' => ['Uniform AllDef3'],
            ],
            'allRookieTeams' => [
                'first' => ['Victor AllRookie1'],
                'second' => ['Whiskey AllRookie2'],
                'third' => ['Xray AllRookie3'],
            ],
            'statisticalLeaders' => [
                'scoring' => 'Yankee Scorer',
                'rebounds' => 'Zulu Rebounder',
                'assists' => 'Ada Assister',
                'steals' => 'Bea Stealer',
                'blocks' => 'Cy Blocker',
            ],
            'playoffBracket' => [
                1 => [
                    ['winner' => 'Hawks', 'loser' => 'Bulls', 'loserGames' => 1],
                    ['winner' => 'Nets', 'loser' => 'Lakers', 'loserGames' => 3],
                ],
                4 => [
                    ['winner' => 'Hawks', 'loser' => 'Nets', 'loserGames' => 2],
                ],
            ],
            'heatStandings' => [
                ['team' => 'Bulls', 'wins' => 5, 'losses' => 1],
                ['team' => 'Hawks', 'wins' => 3, 'losses' => 3],
            ],
            'teamAwards' => [
                'Central Division Champions' => 'Hawks',
                'Eastern Conference Champions' => 'Hawks',
                'IBL HEAT Champions' => 'Bulls',
            ],
            'championRosters' => [
                'ibl' => ['Dee IblChamp', 'Kilo Mvp'],
                'heat' => ['Eve HeatChamp'],
            ],
            'allStarRosters' => [
                'east' => ['Fay East', 'Alpha OneOnOne'],
                'west' => ['Gus West', ''],
            ],
            'allStarCoaches' => [
                'east' => ['Hank Coach'],
                'west' => ['Ivy Coach'],
            ],
            'iblChampionCoach' => 'Jack Owner',
            'teamColors' => self::colorsFixture(),
            'playerIds' => $playerIds,
            'teamIds' => ['Hawks' => 1, 'Bulls' => 4],
        ];

        self::assertSame($expected, $service->getSeasonDetail(2000));
    }

    public function testPlayerIdLookupReceivesNamesInSectionInsertionOrder(): void
    {
        $service = $this->buildService(self::awardsFixture());

        $service->getSeasonDetail(2000);

        self::assertSame(self::EXPECTED_NAME_ORDER, $this->lookedUpNames);
    }

    public function testRepositoryIsQueriedInOriginalOrderWithHeatYear(): void
    {
        $service = $this->buildService(self::awardsFixture());

        $service->getSeasonDetail(2000);

        self::assertSame([
            'getAwardsByYear(2000)',
            'getPlayoffResultsByYear(2000)',
            'getTeamAwardsByYear(2000,1999)',
            'getAllGmAwardsWithTeams()',
            'getAllGmTenuresWithTeams()',
            'getHeatWinLossByYear(1999)',
            'getTeamColors()',
            'getTeamConferences()',
            'getPlayerIdsByNames(33)',
        ], $this->calls);
    }

    public function testEmptyAwardsStopsAfterAwardsLookup(): void
    {
        $service = $this->buildService([]);

        self::assertNull($service->getSeasonDetail(2000));
        self::assertSame(['getAwardsByYear(2000)'], $this->calls);
    }

    public function testPreFoundingYearReturnsNullWithoutRepositoryCalls(): void
    {
        $service = $this->buildService(self::awardsFixture());

        self::assertNull($service->getSeasonDetail(1988));
        self::assertSame([], $this->calls);
    }

    /**
     * @param list<array{year: int, award: string, name: string, table_id: int}> $awards
     */
    private function buildService(array $awards): SeasonArchiveService
    {
        $this->calls = [];
        $this->lookedUpNames = [];

        $repository = self::createStub(SeasonArchiveRepositoryInterface::class);

        $repository->method('getAwardsByYear')->willReturnCallback(
            function (int $year) use ($awards): array {
                $this->calls[] = 'getAwardsByYear(' . $year . ')';

                return $awards;
            }
        );
        $repository->method('getPlayoffResultsByYear')->willReturnCallback(
            function (int $year): array {
                $this->calls[] = 'getPlayoffResultsByYear(' . $year . ')';

                return [
                    ['year' => 2000, 'round' => 4, 'winner' => 'Hawks', 'loser' => 'Nets', 'winner_games' => 4, 'loser_games' => 2],
                    ['year' => 2000, 'round' => 1, 'winner' => 'Hawks', 'loser' => 'Bulls', 'winner_games' => 4, 'loser_games' => 1],
                    ['year' => 2000, 'round' => 1, 'winner' => 'Nets', 'loser' => 'Lakers', 'winner_games' => 4, 'loser_games' => 3],
                ];
            }
        );
        $repository->method('getTeamAwardsByYear')->willReturnCallback(
            function (int $year, int $heatYear): array {
                $this->calls[] = 'getTeamAwardsByYear(' . $year . ',' . $heatYear . ')';

                return [
                    ['year' => 2000, 'name' => 'Hawks', 'award' => "<B>Central Division Champions</b>\n<B>Eastern Conference Champions</b>", 'id' => 1],
                    ['year' => 2000, 'name' => 'Bulls', 'award' => '<B>IBL HEAT Champions</b>', 'id' => 2],
                ];
            }
        );
        $repository->method('getAllGmAwardsWithTeams')->willReturnCallback(
            function (): array {
                $this->calls[] = 'getAllGmAwardsWithTeams()';

                return [
                    ['year' => 1999, 'award' => 'GM of the Year', 'gm_display_name' => 'Old Gm', 'team_name' => 'Nets', 'table_id' => 1],
                    ['year' => 2000, 'award' => 'GM of the Year', 'gm_display_name' => 'Gina Gm', 'team_name' => 'Hawks', 'table_id' => 2],
                    ['year' => 2000, 'award' => 'ASG Head Coach', 'gm_display_name' => 'Hank Coach', 'team_name' => 'Hawks', 'table_id' => 3],
                    ['year' => 2000, 'award' => 'ASG Co-Head Coach', 'gm_display_name' => 'Ivy Coach', 'team_name' => 'Lakers', 'table_id' => 4],
                ];
            }
        );
        $repository->method('getAllGmTenuresWithTeams')->willReturnCallback(
            function (): array {
                $this->calls[] = 'getAllGmTenuresWithTeams()';

                return [
                    ['gm_display_name' => 'Prior Owner', 'start_season_year' => 1990, 'end_season_year' => 1994, 'team_name' => 'Hawks'],
                    ['gm_display_name' => 'Jack Owner', 'start_season_year' => 1995, 'end_season_year' => null, 'team_name' => 'Hawks'],
                ];
            }
        );
        $repository->method('getHeatWinLossByYear')->willReturnCallback(
            function (int $heatYear): array {
                $this->calls[] = 'getHeatWinLossByYear(' . $heatYear . ')';

                return [
                    ['year' => 1999, 'currentname' => 'Bulls', 'namethatyear' => 'Bulls', 'wins' => 5, 'losses' => 1],
                    ['year' => 1999, 'currentname' => 'Hawks', 'namethatyear' => 'Atlanta', 'wins' => 3, 'losses' => 3],
                ];
            }
        );
        $repository->method('getTeamColors')->willReturnCallback(
            function (): array {
                $this->calls[] = 'getTeamColors()';

                return self::colorsFixture();
            }
        );
        $repository->method('getTeamConferences')->willReturnCallback(
            function (): array {
                $this->calls[] = 'getTeamConferences()';

                return ['Hawks' => 'Eastern', 'Nets' => 'Eastern', 'Lakers' => 'Western', 'Bulls' => 'Western'];
            }
        );
        $repository->method('getPlayerIdsByNames')->willReturnCallback(
            function (array $names): array {
                $this->calls[] = 'getPlayerIdsByNames(' . count($names) . ')';
                $this->lookedUpNames = array_values($names);

                $ids = [];
                foreach (array_values($names) as $i => $name) {
                    $ids[$name] = 101 + $i;
                }

                return $ids;
            }
        );

        return new SeasonArchiveService($repository);
    }

    /**
     * @return array<string, array{color1: string, color2: string, teamid: int}>
     */
    private static function colorsFixture(): array
    {
        return [
            'Hawks' => ['color1' => 'E03A3E', 'color2' => 'FFFFFF', 'teamid' => 1],
            'Bulls' => ['color1' => 'CE1141', 'color2' => '000000', 'teamid' => 4],
        ];
    }

    /**
     * Rows are deliberately in reverse of section order, so row order can never
     * masquerade as call order.
     *
     * @return list<array{year: int, award: string, name: string, table_id: int}>
     */
    private static function awardsFixture(): array
    {
        $pairs = [
            // allStarRosters
            ['Western Conference All-Star', 'Gus West'],
            ['Western Conference All-Star', '   '],
            ['Eastern Conference All-Star', 'Fay East'],
            ['Eastern Conference All-Star', 'Alpha OneOnOne'],
            // championRosters
            ['IBL HEAT Championship', 'Eve HeatChamp'],
            ['IBL Champion', 'Dee IblChamp'],
            ['IBL Champion', 'Kilo Mvp'],
            // statisticalLeaders (trailing whitespace on the Blocks award name)
            ['Blocks Leader (1st)  ', 'Cy Blocker'],
            ['Steals Leader (1st)', 'Bea Stealer'],
            ['Assists Leader (1st)', 'Ada Assister'],
            ['Rebounding Leader (1st)', 'Zulu Rebounder'],
            ['Scoring Leader (1st)', 'Yankee Scorer'],
            // allRookieTeams, allDefensiveTeams, allLeagueTeams
            ['All-Rookie Team (3rd)', 'Xray AllRookie3'],
            ['All-Rookie Team (2nd)', 'Whiskey AllRookie2'],
            ['All-Rookie Team (1st)', 'Victor AllRookie1'],
            ['All-Defensive Team (3rd)', 'Uniform AllDef3'],
            ['All-Defensive Team (2nd)', 'Tango AllDef2'],
            ['All-Defensive Team (1st)', 'Sierra AllDef1'],
            ['All-League Third Team', 'Romeo AllLeague3'],
            ['All-League Second Team', 'Quebec AllLeague2'],
            ['All-League First Team', 'Papa AllLeague1'],
            // majorAwards
            ['IBL Finals MVP', 'Oscar FinalsMvp'],
            ['6th Man Award (1st)', 'November SixthMan'],
            ['Rookie of the Year (1st)', 'Mike Roy'],
            ['Defensive Player of the Year (1st)', 'Lima Dpoy'],
            ['Most Valuable Player (1st)', 'Kilo Mvp'],
            // allStarWeekend
            ['Rookie-Sophomore Challenge', 'Juliet RookieSoph'],
            ['Three-Point Contest', 'India Shooter'],
            ['Slam Dunk Competition', 'Echo DunkWinner'],
            ['Slam Dunk Competition', 'Hotel Dunker'],
            ['Rookie-Sophomore Challenge - MVP', 'Golf RookieSophMvp'],
            ['Three-Point Contest - Winner', 'Foxtrot ThreeWinner'],
            ['Slam Dunk Competition - Winner', 'Echo DunkWinner'],
            ['All-Star Game Co-MVP', 'Delta AsgCoMvp'],
            ['All-Star Game MVP', 'Charlie AsgMvp'],
            // tournaments, plus one decoy award no section reads
            ['Rookie One-on-One Tournament Champion', 'Bravo RookieOneOnOne'],
            ['One-on-One Tournament Champion', 'Alpha OneOnOne'],
            ['Most Improved Player', 'Zed Decoy'],
        ];

        $rows = [];
        foreach ($pairs as $i => [$award, $name]) {
            $rows[] = ['year' => 2000, 'award' => $award, 'name' => $name, 'table_id' => $i + 1];
        }

        return $rows;
    }
}
