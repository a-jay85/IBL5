<?php

declare(strict_types=1);

namespace Tests\LastSimRecap;

use LastSimRecap\Contracts\LastSimRecapRepositoryInterface;
use LastSimRecap\Dto\RecapGame;
use LastSimRecap\Dto\RecapStarter;
use LastSimRecap\LastSimRecapService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Repositories\Contracts\PlayerLookupRepositoryInterface;

/**
 * @phpstan-import-type TeamBoxscoreLines from LastSimRecapRepositoryInterface
 * @phpstan-import-type InjuryRow from LastSimRecapRepositoryInterface
 * @phpstan-import-type PlayerLine from LastSimRecapRepositoryInterface
 * @phpstan-import-type StarterMap from LastSimRecapRepositoryInterface
 */
class LastSimRecapServiceTest extends TestCase
{
    private PlayerLookupRepositoryInterface $playerLookup;

    protected function setUp(): void
    {
        $this->playerLookup = self::createStub(PlayerLookupRepositoryInterface::class);
        $this->playerLookup->method('getPlayerByID')->willReturn(null);
    }

    public function testReturnsNullWhenNoWindow(): void
    {
        $repo = $this->buildRepo(window: null);
        $svc = new LastSimRecapService($repo, $this->playerLookup);

        self::assertNull($svc->buildSlateForTeam(1));
    }

    public function testReturnsSlateWithEmptyGamesWhenTeamHasNoGames(): void
    {
        $repo = $this->buildRepo(games: []);
        $svc = new LastSimRecapService($repo, $this->playerLookup);

        $slate = $svc->buildSlateForTeam(1);

        self::assertNotNull($slate);
        self::assertSame([], $slate->games);
        self::assertSame(0, $slate->wins);
        self::assertSame(0, $slate->losses);
        self::assertSame(0, $slate->netMargin);
        self::assertSame('', $slate->bestLabel);
        self::assertSame('', $slate->worstLabel);
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
                    'injuryGamesMissed' => 5, 'daysRemaining' => 5, 'returnDate' => '2030-06-01', 'isNew' => true,
                ]],
            ],
            starterSnapshots: [
                '1|2030-05-01' => ['PG' => 101, 'SG' => 102, 'SF' => 103, 'PF' => 104, 'C' => 105],
            ],
            playerLines: [
                '101|500' => ['pid' => 101, 'name' => 'Star Player', 'pos' => 'PG', 'pts' => 0, 'reb' => 0, 'ast' => 0, 'stl' => 0, 'blk' => 0, 'minutes' => 0],
            ],
        );
        $svc = new LastSimRecapService($repo, $this->playerLookup);

        $slate = $svc->buildSlateForTeam(1);

        self::assertNotNull($slate);
        $pgStarter = $slate->games[0]->starters[0];
        self::assertSame(101, $pgStarter->youPid);
        self::assertTrue($pgStarter->youHurt);
    }

    public function testLastSimDepthColumnsUsedAsStarterPrimary(): void
    {
        $repo = $this->buildRepo(
            games: [
                $this->makeGameRow(schedId: 600, date: '2030-06-01', visitor: 1, vScore: 100, home: 2, hScore: 90),
            ],
            quarterLines: [600 => $this->makeLines()],
            lastSimStarters: [
                1 => ['PG' => 10, 'SG' => 11, 'SF' => 12, 'PF' => 13, 'C' => 14],
            ],
            starterSnapshots: [
                '1|2030-06-01' => ['PG' => 20, 'SG' => 21, 'SF' => 22, 'PF' => 23, 'C' => 24],
            ],
            playerLines: [
                '13|600' => ['pid' => 13, 'name' => 'George Mikan', 'pos' => 'C', 'pts' => 22, 'reb' => 10, 'ast' => 2, 'stl' => 0, 'blk' => 3, 'minutes' => 32],
            ],
        );
        $svc = new LastSimRecapService($repo, $this->playerLookup);

        $slate = $svc->buildSlateForTeam(1);

        self::assertNotNull($slate);
        $pfStarter = $slate->games[0]->starters[3]; // PF is index 3
        self::assertSame(13, $pfStarter->youPid);
    }

    public function testFallsBackToSnapshotWhenLastSimNull(): void
    {
        $repo = $this->buildRepo(
            games: [
                $this->makeGameRow(schedId: 700, date: '2030-07-01', visitor: 1, vScore: 95, home: 2, hScore: 88),
            ],
            quarterLines: [700 => $this->makeLines()],
            lastSimStarters: [],
            starterSnapshots: [
                '1|2030-07-01' => ['PG' => 30, 'SG' => 31, 'SF' => 32, 'PF' => 33, 'C' => 34],
            ],
            playerLines: [
                '33|700' => ['pid' => 33, 'name' => 'PF Player', 'pos' => 'PF', 'pts' => 15, 'reb' => 8, 'ast' => 1, 'stl' => 1, 'blk' => 0, 'minutes' => 28],
            ],
        );
        $svc = new LastSimRecapService($repo, $this->playerLookup);

        $slate = $svc->buildSlateForTeam(1);

        self::assertNotNull($slate);
        $pfStarter = $slate->games[0]->starters[3];
        self::assertSame(33, $pfStarter->youPid);
    }

    public function testHomeTeamOrientationFlipsMarginsAndOppPreRecord(): void
    {
        $calls = new \ArrayObject();
        $game = $this->onlyGame($this->buildRepo(
            games: [$this->makeGameRow(schedId: 810, date: '2030-08-10', visitor: 2, vScore: 90, home: 1, hScore: 100)],
            quarterLines: [810 => $this->makeLines()],
            calls: $calls,
        ));

        self::assertTrue($game->home);
        self::assertTrue($game->won);
        self::assertSame(100, $game->yourScore);
        self::assertSame(90, $game->oppScore);
        self::assertSame(10, $game->margin);
        self::assertSame([-5,-5,-5,-5], $game->margins);
        self::assertSame(['Q1', 'Q2', 'Q3', 'Q4'], $game->qLabels);
        self::assertFalse($game->ot);
        self::assertSame(2, $game->oppTid);
        self::assertSame('Los Angeles', $game->oppCity);
        self::assertSame('Stars', $game->oppName);
        self::assertSame(10, $game->oppPreWins);
        self::assertSame(5, $game->oppPreLosses);
        self::assertSame(1, $game->gameOfThatDay);
        self::assertSame(810, $game->schedId);
        self::assertSame(0, $game->boxId);
        self::assertSame('2030-08-10', $game->date);
        self::assertSame('boxscoreLines:2|1|2030-08-10', $calls[0]);
    }

    public function testVisitorTeamOrientationUsesHomeTeamPreRecord(): void
    {
        $game = $this->onlyGame($this->buildRepo(
            games: [$this->makeGameRow(schedId: 811, date: '2030-08-11', visitor: 1, vScore: 100, home: 2, hScore: 90)],
            quarterLines: [811 => $this->makeLines()],
        ));

        self::assertFalse($game->home);
        self::assertSame([5,5,5,5], $game->margins);
        self::assertSame(8, $game->oppPreWins);
        self::assertSame(7, $game->oppPreLosses);
        self::assertSame(1, $game->gameOfThatDay);
    }

    #[DataProvider('overtimeOrientationProvider')]
    public function testOvertimeMarginOrientedToYourTeam(int $visitor, int $home, int $expectedOtMargin): void
    {
        $game = $this->onlyGame($this->buildRepo(
            games: [$this->makeGameRow(schedId: 813, date: '2030-08-13', visitor: $visitor, vScore: 100, home: $home, hScore: 100)],
            quarterLines: [813 => $this->makeLines(visOt: 5, homeOt: 7)],
        ));

        self::assertTrue($game->ot);
        self::assertCount(5, $game->margins);
        self::assertSame($expectedOtMargin, $game->margins[4]);
        self::assertSame('OT', $game->qLabels[4]);
    }

    /** @return array<string, array{int, int, int}> */
    public static function overtimeOrientationProvider(): array
    {
        return [
            'visitor' => [1, 2, -2],
            'home' => [2, 1, 2],
        ];
    }

    #[DataProvider('oneSidedOvertimeProvider')]
    public function testOneSidedOvertimeStillAddsOtPeriod(int $visOt, int $homeOt, int $expectedOtMargin): void
    {
        $game = $this->onlyGame($this->buildRepo(
            games: [$this->makeGameRow(schedId: 814, date: '2030-08-14', visitor: 1, vScore: 100, home: 2, hScore: 100)],
            quarterLines: [814 => $this->makeLines(visOt: $visOt, homeOt: $homeOt)],
        ));

        self::assertTrue($game->ot);
        self::assertCount(5, $game->margins);
        self::assertSame($expectedOtMargin, $game->margins[4]);
    }

    /** @return array<string, array{int, int, int}> */
    public static function oneSidedOvertimeProvider(): array
    {
        return [
            'visitor-only' => [9, 0, 9],
            'home-only' => [0, 6, -6],
            'visitor-one-point' => [1, 0, 1],
            'home-one-point' => [0, 1, -1],
        ];
    }

    public function testMissingBoxscoreLinesLeaveMarginsEmptyAndPreRecordZero(): void
    {
        $game = $this->onlyGame($this->buildRepo(
            games: [$this->makeGameRow(schedId: 812, date: '2030-08-12', visitor: 1, vScore: 101, home: 2, hScore: 99)],
            quarterLines: [],
        ));

        self::assertSame([], $game->margins);
        self::assertSame([], $game->qLabels);
        self::assertFalse($game->ot);
        self::assertSame(0, $game->oppPreWins);
        self::assertSame(0, $game->oppPreLosses);
        self::assertSame(0, $game->gameOfThatDay);
        self::assertSame(2, $game->margin);
        self::assertTrue($game->won);
    }

    public function testLastSimStartersShortCircuitSnapshotAndBoxScore(): void
    {
        $calls = new \ArrayObject();
        $game = $this->onlyGame($this->buildRepo(
            games: [$this->makeGameRow(schedId: 820, date: '2030-08-20', visitor: 1, vScore: 100, home: 2, hScore: 90)],
            quarterLines: [820 => $this->makeLines()],
            lastSimStarters: [1 => $this->makeStarterMap(10), 2 => $this->makeStarterMap(20)],
            starterSnapshots: ['1|2030-08-20' => $this->makeStarterMap(30), '2|2030-08-20' => $this->makeStarterMap(35)],
            boxScoreStarters: ['820|1' => $this->makeStarterMap(40), '820|2' => $this->makeStarterMap(45)],
            calls: $calls,
        ));

        foreach ($game->starters as $i => $starter) {
            self::assertSame(10 + $i, $starter->youPid);
            self::assertSame(20 + $i, $starter->oppPid);
        }
        self::assertSame(['boxscoreLines:1|2|2030-08-20', 'lastSim:1', 'lastSim:2'], $calls->getArrayCopy());
    }

    public function testSnapshotStartersShortCircuitBoxScore(): void
    {
        $calls = new \ArrayObject();
        $game = $this->onlyGame($this->buildRepo(
            games: [$this->makeGameRow(schedId: 821, date: '2030-08-21', visitor: 1, vScore: 100, home: 2, hScore: 90)],
            quarterLines: [821 => $this->makeLines()],
            lastSimStarters: [],
            starterSnapshots: ['1|2030-08-21' => $this->makeStarterMap(30), '2|2030-08-21' => $this->makeStarterMap(35)],
            boxScoreStarters: ['821|1' => $this->makeStarterMap(40), '821|2' => $this->makeStarterMap(45)],
            calls: $calls,
        ));

        foreach ($game->starters as $i => $starter) {
            self::assertSame(30 + $i, $starter->youPid);
            self::assertSame(35 + $i, $starter->oppPid);
        }
        self::assertSame(
            ['boxscoreLines:1|2|2030-08-21', 'lastSim:1', 'snapshot:1|2030-08-21', 'lastSim:2', 'snapshot:2|2030-08-21'],
            $calls->getArrayCopy(),
        );
    }

    public function testBoxScoreStartersAreTerminalFallbackForBothTeams(): void
    {
        $calls = new \ArrayObject();
        $game = $this->onlyGame($this->buildRepo(
            games: [$this->makeGameRow(schedId: 822, date: '2030-08-22', visitor: 1, vScore: 100, home: 2, hScore: 90)],
            quarterLines: [822 => $this->makeLines()],
            boxScoreStarters: ['822|1' => $this->makeStarterMap(40), '822|2' => $this->makeStarterMap(45)],
            calls: $calls,
        ));

        foreach ($game->starters as $i => $starter) {
            self::assertSame(40 + $i, $starter->youPid);
            self::assertSame(45 + $i, $starter->oppPid);
        }
        self::assertSame(
            [
                'boxscoreLines:1|2|2030-08-22',
                'lastSim:1', 'snapshot:1|2030-08-22', 'boxScores:822|1',
                'lastSim:2', 'snapshot:2|2030-08-22', 'boxScores:822|2',
            ],
            $calls->getArrayCopy(),
        );
        // Swapped (tid, schedId) args would record this tag instead.
        self::assertNotContains('boxScores:1|822', $calls->getArrayCopy());
    }

    public function testPartialLastSimMapIsUsedWholeWithoutPerPositionMerge(): void
    {
        $calls = new \ArrayObject();
        $partial = ['PG' => 10, 'SG' => 11, 'SF' => 12, 'PF' => 13, 'C' => 0];
        $game = $this->onlyGame($this->buildRepo(
            games: [$this->makeGameRow(schedId: 823, date: '2030-08-23', visitor: 1, vScore: 100, home: 2, hScore: 90)],
            quarterLines: [823 => $this->makeLines()],
            lastSimStarters: [1 => $partial],
            starterSnapshots: ['1|2030-08-23' => ['PG' => 50, 'SG' => 51, 'SF' => 52, 'PF' => 53, 'C' => 55]],
            calls: $calls,
        ));

        self::assertSame(0, $game->starters[4]->youPid);
        foreach ($calls as $call) {
            self::assertStringStartsNotWith('snapshot:1|', $call);
        }
    }

    public function testLastSimDnpStarterKeepsPidWithZeroStatLine(): void
    {
        $game = $this->onlyGame($this->buildRepo(
            games: [$this->makeGameRow(schedId: 830, date: '2030-08-30', visitor: 1, vScore: 100, home: 2, hScore: 90)],
            quarterLines: [830 => $this->makeLines()],
            lastSimStarters: [1 => ['PG' => 10, 'SG' => 0, 'SF' => 0, 'PF' => 0, 'C' => 0]],
            playerLines: ['10|830' => $this->makePlayerLine(10, 'Bob Cousy')],
        ));

        $pg = $game->starters[0];
        self::assertSame(10, $pg->youPid);
        self::assertSame('B. Cousy', $pg->youName);
        self::assertSame([0, 0, 0, 0, 0], [$pg->youPts, $pg->youReb, $pg->youAst, $pg->youStl, $pg->youBlk]);
        self::assertFalse($pg->youHurt);
    }

    public function testSnapshotDnpStarterKeepsPidWithZeroStatLine(): void
    {
        $game = $this->onlyGame($this->buildRepo(
            games: [$this->makeGameRow(schedId: 831, date: '2030-08-31', visitor: 1, vScore: 100, home: 2, hScore: 90)],
            quarterLines: [831 => $this->makeLines()],
            lastSimStarters: [],
            starterSnapshots: ['1|2030-08-31' => ['PG' => 10, 'SG' => 0, 'SF' => 0, 'PF' => 0, 'C' => 0]],
            playerLines: ['10|831' => $this->makePlayerLine(10, 'Bob Cousy')],
        ));

        $pg = $game->starters[0];
        self::assertSame(10, $pg->youPid);
        self::assertSame('B. Cousy', $pg->youName);
        self::assertSame([0, 0, 0, 0, 0], [$pg->youPts, $pg->youReb, $pg->youAst, $pg->youStl, $pg->youBlk]);
        self::assertFalse($pg->youHurt);
    }

    public function testBoxScoreDnpPositionYieldsEmptyStarterWithoutLineLookup(): void
    {
        // The seeded 0|sched line ('0|832') must never be read for pid 0.
        $game = $this->onlyGame($this->buildRepo(
            games: [$this->makeGameRow(schedId: 832, date: '2030-09-01', visitor: 1, vScore: 100, home: 2, hScore: 90)],
            quarterLines: [832 => $this->makeLines()],
            boxScoreStarters: ['832|1' => ['PG' => 0, 'SG' => 41, 'SF' => 42, 'PF' => 43, 'C' => 44]],
            playerLines: ['0|832' => $this->makePlayerLine(0, 'Ghost Row', pts: 99)],
        ));

        $pg = $game->starters[0];
        self::assertSame(0, $pg->youPid);
        self::assertSame('', $pg->youName);
        self::assertSame(0, $pg->youPts);
        self::assertSame(0, $pg->youReb);
        self::assertSame(0, $pg->oppPid);
        self::assertSame('', $pg->oppName);
        self::assertSame(0, $pg->oppPts);
    }

    public function testPlayerLookupNameTakesPrecedenceOverLineName(): void
    {
        $lookup = self::createStub(PlayerLookupRepositoryInterface::class);
        $lookup->method('getPlayerByID')->willReturnCallback(
            static fn (int $pid): ?array => match ($pid) {
                10 => ['name' => 'Robert Cousy'],
                20 => ['name' => 'Jerry West'],
                default => null,
            },
        );
        $game = $this->onlyGame(
            $this->buildRepo(
                games: [$this->makeGameRow(schedId: 840, date: '2030-09-10', visitor: 1, vScore: 100, home: 2, hScore: 90)],
                quarterLines: [840 => $this->makeLines()],
                lastSimStarters: [
                    1 => ['PG' => 10, 'SG' => 0, 'SF' => 0, 'PF' => 0, 'C' => 0],
                    2 => ['PG' => 20, 'SG' => 0, 'SF' => 0, 'PF' => 0, 'C' => 0],
                ],
                playerLines: [
                    '10|840' => $this->makePlayerLine(10, 'Bob Cousy'),
                    '20|840' => $this->makePlayerLine(20, 'Zeke Logo'),
                ],
            ),
            $lookup,
        );

        self::assertSame('R. Cousy', $game->starters[0]->youName);
        self::assertSame('J. West', $game->starters[0]->oppName);
    }

    public function testHurtFlagOnlyForNewInjuriesOnYourRoster(): void
    {
        $d = '2030-09-11';
        $game = $this->onlyGame($this->buildRepo(
            games: [$this->makeGameRow(schedId: 841, date: $d, visitor: 1, vScore: 100, home: 2, hScore: 90)],
            quarterLines: [841 => $this->makeLines()],
            rosterByTid: [1 => [10, 11], 2 => [20]],
            injuriesByPidAndDate: [
                '10|' . $d => [$this->makeInjury(10, $d, true)],
                '11|' . $d => [$this->makeInjury(11, $d, false)],
                '20|' . $d => [$this->makeInjury(20, $d, true)],
            ],
            lastSimStarters: [1 => ['PG' => 10, 'SG' => 11, 'SF' => 20, 'PF' => 13, 'C' => 14]],
        ));

        self::assertTrue($game->starters[0]->youHurt);
        self::assertFalse($game->starters[1]->youHurt);
        self::assertFalse($game->starters[2]->youHurt);
        self::assertCount(2, $game->yourInjuries);
        self::assertCount(1, $game->oppInjuries);
        self::assertSame(20, $game->oppInjuries[0]->pid);
    }

    public function testOpponentStarterFieldsComeFromOpponentLine(): void
    {
        $game = $this->onlyGame($this->buildRepo(
            games: [$this->makeGameRow(schedId: 842, date: '2030-09-12', visitor: 1, vScore: 100, home: 2, hScore: 90)],
            quarterLines: [842 => $this->makeLines()],
            lastSimStarters: [1 => $this->makeStarterMap(10), 2 => $this->makeStarterMap(20)],
            playerLines: [
                '10|842' => $this->makePlayerLine(10, 'Your Guard', pts: 21, reb: 8, ast: 6, stl: 3, blk: 1),
                '20|842' => $this->makePlayerLine(20, 'Opp Guard', pts: 18, reb: 4, ast: 9, stl: 2, blk: 5),
            ],
        ));

        $pg = $game->starters[0];
        self::assertSame('Y. Guard', $pg->youName);
        self::assertSame([21, 8, 6, 3, 1], [$pg->youPts, $pg->youReb, $pg->youAst, $pg->youStl, $pg->youBlk]);
        self::assertSame(20, $pg->oppPid);
        self::assertSame('O. Guard', $pg->oppName);
        self::assertSame([18, 4, 9, 2, 5], [$pg->oppPts, $pg->oppReb, $pg->oppAst, $pg->oppStl, $pg->oppBlk]);
        self::assertSame(
            ['PG', 'SG', 'SF', 'PF', 'C'],
            array_map(static fn (RecapStarter $s): string => $s->pos, $game->starters),
        );
    }

    private function onlyGame(
        LastSimRecapRepositoryInterface $repo,
        ?PlayerLookupRepositoryInterface $lookup = null,
    ): RecapGame {
        $svc = new LastSimRecapService($repo, $lookup ?? $this->playerLookup);

        $slate = $svc->buildSlateForTeam(1);

        self::assertNotNull($slate);
        self::assertCount(1, $slate->games);

        return $slate->games[0];
    }

    /** @return array{PG:int,SG:int,SF:int,PF:int,C:int} */
    private function makeStarterMap(int $firstPid): array
    {
        return [
            'PG' => $firstPid, 'SG' => $firstPid + 1, 'SF' => $firstPid + 2,
            'PF' => $firstPid + 3, 'C' => $firstPid + 4,
        ];
    }

    /** @return PlayerLine */
    private function makePlayerLine(
        int $pid,
        string $name,
        int $pts = 0,
        int $reb = 0,
        int $ast = 0,
        int $stl = 0,
        int $blk = 0,
    ): array {
        return [
            'pid' => $pid, 'name' => $name, 'pos' => 'PG',
            'pts' => $pts, 'reb' => $reb, 'ast' => $ast, 'stl' => $stl, 'blk' => $blk, 'minutes' => 0,
        ];
    }

    /** @return InjuryRow */
    private function makeInjury(int $pid, string $date, bool $isNew): array
    {
        return [
            'pid' => $pid, 'name' => 'Hurt Player', 'pos' => 'PG', 'date' => $date,
            'injuryDescription' => 'Sprain', 'injuryGamesMissed' => 5, 'daysRemaining' => 5,
            'returnDate' => '2030-10-01', 'isNew' => $isNew,
        ];
    }

    /**
     * @param array{sim: int, startDate: string, endDate: string}|null $window
     * @param list<array{schedId:int,boxId:int,date:string,visitor:int,vScore:int,home:int,hScore:int,year:int}> $games
     * @param array<int, TeamBoxscoreLines> $quarterLines
     * @param array<int, list<int>> $rosterByTid
     * @param array<string, list<InjuryRow>> $injuriesByPidAndDate
     * @param array<int, array{PG:int,SG:int,SF:int,PF:int,C:int}> $lastSimStarters
     * @param array<string, array{PG:int,SG:int,SF:int,PF:int,C:int}> $starterSnapshots
     * @param array<string, PlayerLine> $playerLines
     * @param array<string, StarterMap> $boxScoreStarters key "<schedId>|<tid>"
     * @param \ArrayObject<int, string>|null $calls
     */
    private function buildRepo(
        ?array $window = ['sim' => 1, 'startDate' => '2030-03-01', 'endDate' => '2030-03-31'],
        array $games = [],
        array $quarterLines = [],
        array $rosterByTid = [],
        array $injuriesByPidAndDate = [],
        array $lastSimStarters = [],
        array $starterSnapshots = [],
        array $playerLines = [],
        array $boxScoreStarters = [],
        ?\ArrayObject $calls = null,
    ): LastSimRecapRepositoryInterface {
        $calls ??= new \ArrayObject();
        $teamInfo = [
            1 => ['tid' => 1, 'city' => 'New York', 'name' => 'Metros'],
            2 => ['tid' => 2, 'city' => 'Los Angeles', 'name' => 'Stars'],
            3 => ['tid' => 3, 'city' => 'Chicago', 'name' => 'Cougars'],
            5 => ['tid' => 5, 'city' => 'Boston', 'name' => 'Minutemen'],
            6 => ['tid' => 6, 'city' => 'Philadelphia', 'name' => 'Rage'],
        ];

        return new class(
            $window, $games, $quarterLines, $rosterByTid,
            $injuriesByPidAndDate, $lastSimStarters, $starterSnapshots, $playerLines, $teamInfo,
            $boxScoreStarters, $calls,
        ) implements LastSimRecapRepositoryInterface {
            /**
             * @param array{sim: int, startDate: string, endDate: string}|null $window
             * @param list<array{schedId:int,boxId:int,date:string,visitor:int,vScore:int,home:int,hScore:int,year:int}> $games
             * @param array<int, array{visQ:array{0:int,1:int,2:int,3:int},homeQ:array{0:int,1:int,2:int,3:int},visOT:int,homeOT:int,visitorPreWins:int,visitorPreLosses:int,homePreWins:int,homePreLosses:int,gameOfThatDay:int}> $quarterLines
             * @param array<int, list<int>> $rosterByTid
             * @param array<string, list<array{pid:int,name:string,pos:string,date:string,injuryDescription:string,injuryGamesMissed:int,daysRemaining:int,returnDate:string,isNew:bool}>> $injuriesByPidAndDate
             * @param array<int, array{PG:int,SG:int,SF:int,PF:int,C:int}> $lastSimStarters
             * @param array<string, array{PG:int,SG:int,SF:int,PF:int,C:int}> $starterSnapshots
             * @param array<string, array{pid:int,name:string,pos:string,pts:int,reb:int,ast:int,stl:int,blk:int,minutes:int}> $playerLines
             * @param array<int, array{tid:int,city:string,name:string}> $teamInfo
             * @param array<string, array{PG:int,SG:int,SF:int,PF:int,C:int}> $boxScoreStarters
             * @param \ArrayObject<int, string> $calls
             */
            public function __construct(
                private ?array $window,
                private array $games,
                private array $quarterLines,
                private array $rosterByTid,
                private array $injuriesByPidAndDate,
                private array $lastSimStarters,
                private array $starterSnapshots,
                private array $playerLines,
                private array $teamInfo,
                private array $boxScoreStarters,
                private \ArrayObject $calls,
            ) {}

            /** @return array{sim: int, startDate: string, endDate: string}|null */
            public function getLastSimWindow(): ?array { return $this->window; }
            public function getGamesForTeamInWindow(int $tid, string $startDate, string $endDate): array { return $this->games; }
            public function getTeamBoxscoreLines(int $visitor, int $home, string $date): ?array {
                $this->calls->append('boxscoreLines:' . $visitor . '|' . $home . '|' . $date);
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
            public function getStarterPidsFromLastSim(int $tid): ?array {
                $this->calls->append('lastSim:' . $tid);
                return $this->lastSimStarters[$tid] ?? null;
            }
            public function getStarterPidsFromSnapshot(int $tid, string $date): ?array {
                $this->calls->append('snapshot:' . $tid . '|' . $date);
                return $this->starterSnapshots[$tid . '|' . $date] ?? null;
            }
            public function getStarterPidsFromBoxScores(int $schedId, int $tid): array {
                $this->calls->append('boxScores:' . $schedId . '|' . $tid);
                return $this->boxScoreStarters[$schedId . '|' . $tid] ?? ['PG' => 0, 'SG' => 0, 'SF' => 0, 'PF' => 0, 'C' => 0];
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

    /** @return array{visQ:array{0:int,1:int,2:int,3:int},homeQ:array{0:int,1:int,2:int,3:int},visOT:int,homeOT:int,visitorPreWins:int,visitorPreLosses:int,homePreWins:int,homePreLosses:int,gameOfThatDay:int} */
    private function makeLines(int $visOt = 0, int $homeOt = 0): array
    {
        return [
            'visQ' => [25, 25, 25, 25],
            'homeQ' => [20, 20, 20, 20],
            'visOT' => $visOt,
            'homeOT' => $homeOt,
            'visitorPreWins' => 10, 'visitorPreLosses' => 5,
            'homePreWins' => 8, 'homePreLosses' => 7,
            'gameOfThatDay' => 1,
        ];
    }
}
