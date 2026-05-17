<?php

declare(strict_types=1);

namespace Tests\LastSimRecap;

use LastSimRecap\Contracts\LastSimRecapRepositoryInterface;
use LastSimRecap\LastSimRecapService;
use PHPUnit\Framework\TestCase;
use Repositories\Contracts\PlayerLookupRepositoryInterface;

class LastSimRecapServiceTest extends TestCase
{
    private PlayerLookupRepositoryInterface $playerLookup;

    protected function setUp(): void
    {
        $this->playerLookup = $this->createStub(PlayerLookupRepositoryInterface::class);
        $this->playerLookup->method('getPlayerByID')->willReturn(null);
    }

    public function testReturnsNullWhenNoWindow(): void
    {
        $repo = $this->buildRepo(window: null);
        $svc = new LastSimRecapService($repo, $this->playerLookup);

        self::assertNull($svc->buildSlateForTeam(1));
    }

    public function testReturnsNullWhenTeamHasNoGames(): void
    {
        $repo = $this->buildRepo(games: []);
        $svc = new LastSimRecapService($repo, $this->playerLookup);

        self::assertNull($svc->buildSlateForTeam(1));
    }

    public function testSlateAggregatesWinsLossesNetMargin(): void
    {
        $repo = $this->buildRepo(
            games: [
                $this->makeGameRow(schedId: 100, date: '2030-03-03', visitor: 1, vScore: 110, home: 2, hScore: 100), // T1 win, +10
                $this->makeGameRow(schedId: 99,  date: '2030-03-02', visitor: 2, vScore: 95,  home: 1, hScore: 90),  // T1 loss, -5
                $this->makeGameRow(schedId: 98,  date: '2030-03-01', visitor: 1, vScore: 88,  home: 3, hScore: 80),  // T1 win, +8
            ],
            quarterLines: [
                100 => $this->makeLines(),
                99 => $this->makeLines(),
                98 => $this->makeLines(),
            ],
        );
        $svc = new LastSimRecapService($repo, $this->playerLookup);

        $slate = $svc->buildSlateForTeam(1);

        self::assertNotNull($slate);
        self::assertSame(2, $slate->wins);
        self::assertSame(1, $slate->losses);
        self::assertSame(13, $slate->netMargin); // +10 -5 +8
        self::assertCount(3, $slate->games);
    }

    public function testSlateBestAndWorstLabels(): void
    {
        $repo = $this->buildRepo(
            games: [
                $this->makeGameRow(schedId: 200, date: '2030-03-05', visitor: 1, vScore: 120, home: 2, hScore: 99),  // T1 win, +21
                $this->makeGameRow(schedId: 199, date: '2030-03-04', visitor: 3, vScore: 110, home: 1, hScore: 100), // T1 loss, -10
            ],
            quarterLines: [
                200 => $this->makeLines(),
                199 => $this->makeLines(),
            ],
        );
        $svc = new LastSimRecapService($repo, $this->playerLookup);

        $slate = $svc->buildSlateForTeam(1);

        self::assertNotNull($slate);
        self::assertStringContainsString('+21', $slate->bestLabel);
        self::assertStringContainsString('Stars', $slate->bestLabel);
        self::assertStringContainsString('@', $slate->bestLabel);   // visitor → @
        self::assertStringContainsString('−10', $slate->worstLabel);
        self::assertStringContainsString('Cougars', $slate->worstLabel);
        self::assertStringContainsString('vs', $slate->worstLabel); // home → vs
    }

    public function testSlateBestTieBreakUsesMostRecent(): void
    {
        $repo = $this->buildRepo(
            games: [
                $this->makeGameRow(schedId: 300, date: '2030-03-07', visitor: 1, vScore: 107, home: 5, hScore: 100), // +7, OPP=5 (Minutemen → MIN)
                $this->makeGameRow(schedId: 299, date: '2030-03-04', visitor: 1, vScore: 107, home: 6, hScore: 100), // +7, OPP=6 (Rage → RAG)
            ],
            quarterLines: [
                300 => $this->makeLines(),
                299 => $this->makeLines(),
            ],
        );
        $svc = new LastSimRecapService($repo, $this->playerLookup);

        $slate = $svc->buildSlateForTeam(1);

        self::assertNotNull($slate);
        // Service iterates in input order (already desc by date), and uses `>` for best so
        // the FIRST max (i.e. the most recent) wins ties. Best label should show MIN.
        self::assertStringContainsString('Minutemen', $slate->bestLabel);
    }

    public function testOtGameProducesFiveMargins(): void
    {
        $linesWithOt = $this->makeLines(visOt: 5, homeOt: 7);
        $repo = $this->buildRepo(
            games: [
                $this->makeGameRow(schedId: 400, date: '2030-04-01', visitor: 1, vScore: 117, home: 2, hScore: 119),
            ],
            quarterLines: [400 => $linesWithOt],
        );
        $svc = new LastSimRecapService($repo, $this->playerLookup);

        $slate = $svc->buildSlateForTeam(1);

        self::assertNotNull($slate);
        $g = $slate->games[0];
        self::assertTrue($g->ot);
        self::assertCount(5, $g->margins);
        self::assertSame('OT', $g->qLabels[4]);
        self::assertFalse($g->won);
        self::assertLessThan(0, $g->margin);
    }

    public function testStarterHurtTrueWhenNewInjuryMatches(): void
    {
        $repo = $this->buildRepo(
            games: [
                $this->makeGameRow(schedId: 500, date: '2030-05-01', visitor: 1, vScore: 100, home: 2, hScore: 90),
            ],
            quarterLines: [500 => $this->makeLines()],
            rosterByTid: [1 => [101], 2 => [201]],
            injuriesByPidAndDate: [
                '101|2030-05-01' => [[
                    'pid' => 101, 'name' => 'Star Player', 'pos' => 'PG',
                    'date' => '2030-05-01', 'injuryDescription' => 'Sprain',
                    'injuryGamesMissed' => 5, 'daysRemaining' => 5, 'isNew' => true,
                ]],
            ],
            starterSnapshots: [
                '1|2030-05-01' => ['PG' => 101, 'SG' => 102, 'SF' => 103, 'PF' => 104, 'C' => 105],
            ],
            playerLines: [
                '101|500' => ['pid' => 101, 'name' => 'Star Player', 'pos' => 'PG', 'pts' => 0, 'minutes' => 0],
            ],
        );
        $svc = new LastSimRecapService($repo, $this->playerLookup);

        $slate = $svc->buildSlateForTeam(1);

        self::assertNotNull($slate);
        $pgStarter = $slate->games[0]->starters[0];
        self::assertSame(101, $pgStarter->youPid);
        self::assertTrue($pgStarter->youHurt);
    }

    private function buildRepo(
        ?array $window = ['sim' => 1, 'startDate' => '2030-03-01', 'endDate' => '2030-03-31'],
        array $games = [],
        array $quarterLines = [],
        array $rosterByTid = [],
        array $injuriesByPidAndDate = [],
        array $starterSnapshots = [],
        array $playerLines = [],
    ): LastSimRecapRepositoryInterface {
        $teamInfo = [
            1 => ['tid' => 1, 'city' => 'New York', 'name' => 'Metros'],
            2 => ['tid' => 2, 'city' => 'Los Angeles', 'name' => 'Stars'],
            3 => ['tid' => 3, 'city' => 'Chicago', 'name' => 'Cougars'],
            5 => ['tid' => 5, 'city' => 'Boston', 'name' => 'Minutemen'],
            6 => ['tid' => 6, 'city' => 'Philadelphia', 'name' => 'Rage'],
        ];

        return new class(
            $window, $games, $quarterLines, $rosterByTid,
            $injuriesByPidAndDate, $starterSnapshots, $playerLines, $teamInfo,
        ) implements LastSimRecapRepositoryInterface {
            public function __construct(
                private ?array $window,
                private array $games,
                private array $quarterLines,
                private array $rosterByTid,
                private array $injuriesByPidAndDate,
                private array $starterSnapshots,
                private array $playerLines,
                private array $teamInfo,
            ) {}

            public function getLastSimWindow(): ?array { return $this->window; }
            public function getGamesForTeamInWindow(int $tid, string $startDate, string $endDate): array { return $this->games; }
            public function getTeamBoxscoreLines(int $visitor, int $home, string $date): ?array {
                // Look up by date — tests key fixtures by schedId, but the
                // production query no longer needs schedId.
                foreach ($this->quarterLines as $lines) {
                    return $lines;
                }
                return null;
            }
            public function getActiveInjuriesForPlayers(array $playerIds, string $date): array {
                $out = [];
                foreach ($playerIds as $pid) {
                    foreach ($this->injuriesByPidAndDate[$pid . '|' . $date] ?? [] as $row) {
                        $out[] = $row;
                    }
                }
                return $out;
            }
            public function getTeamRosterPids(int $tid): array { return $this->rosterByTid[$tid] ?? []; }
            public function getStarterPidsFromSnapshot(int $tid, string $date): ?array { return $this->starterSnapshots[$tid . '|' . $date] ?? null; }
            public function getStarterPidsFromBoxScores(int $schedId, int $tid): array {
                return ['PG' => 0, 'SG' => 0, 'SF' => 0, 'PF' => 0, 'C' => 0];
            }
            public function getPlayerLineForGame(int $pid, int $schedId): ?array { return $this->playerLines[$pid . '|' . $schedId] ?? null; }
            public function getTeamRecordAsOf(int $tid, string $date): array {
                return ['wins' => 0, 'losses' => 0];
            }
            public function getTeamInfo(int $tid): ?array { return $this->teamInfo[$tid] ?? null; }
        };
    }

    /** @return array{schedId:int,boxId:int,date:string,visitor:int,vScore:int,home:int,hScore:int,year:int} */
    private function makeGameRow(int $schedId, string $date, int $visitor, int $vScore, int $home, int $hScore): array
    {
        return [
            'schedId' => $schedId, 'boxId' => 0, 'date' => $date,
            'visitor' => $visitor, 'vScore' => $vScore,
            'home' => $home, 'hScore' => $hScore, 'year' => 2030,
        ];
    }

    /** @return array{visQ:array{0:int,1:int,2:int,3:int},homeQ:array{0:int,1:int,2:int,3:int},visOT:int,homeOT:int,visitorPreWins:int,visitorPreLosses:int,homePreWins:int,homePreLosses:int} */
    private function makeLines(int $visOt = 0, int $homeOt = 0): array
    {
        return [
            'visQ' => [25, 25, 25, 25],
            'homeQ' => [20, 20, 20, 20],
            'visOT' => $visOt,
            'homeOT' => $homeOt,
            'visitorPreWins' => 10, 'visitorPreLosses' => 5,
            'homePreWins' => 8, 'homePreLosses' => 7,
        ];
    }
}
