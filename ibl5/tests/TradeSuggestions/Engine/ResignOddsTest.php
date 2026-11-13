<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Engine\Contracts\SlotRankReaderInterface;
use TradeSuggestions\Engine\ResignOdds;
use TradeSuggestions\Profile\SeasonLineup;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\TeamDepth;
use TradeSuggestions\Valuation\ResignLikelihood;
use TradeSuggestions\Valuation\TeamFactors;

/**
 * Hand-worked fixture. Offset-0 position money, excluding target pid 10:
 *
 *   slot  team1  team2  team3  team4
 *   SG     250    400     90      0   (team1: pid 11 SG 200 + pid 13 GF 50; pid 12 G counts at PG)
 *   PG     100    120     80      0
 *
 * League-average factors over teams 1-3: wins 40, losses 42, tradWins 38, tradLosses 42.
 */
final class ResignOddsTest extends TestCase
{
    private const array LOW_PREFS = ['loyalty' => 1, 'winner' => 5, 'tradition' => 5, 'security' => 1, 'playing_time' => 5];
    private const array HIGH_PREFS = ['loyalty' => 3, 'winner' => 5, 'tradition' => 5, 'security' => 1, 'playing_time' => 5];

    /**
     * @param array<string, int> $prefs
     * @param array<int, int>|null $salaryByYear
     */
    private static function player(int $pid, string $pos, int $year1, array $prefs = self::LOW_PREFS, ?array $salaryByYear = null): ProjectionPlayer
    {
        $base = ProjectionFactory::player(
            pid: $pid,
            pos: $pos,
            salaryByYear: $salaryByYear ?? [1 => $year1, 2 => 7, 3 => 7, 4 => 0, 5 => 0, 6 => 0],
        );

        return new ProjectionPlayer(...[...get_object_vars($base), 'prefs' => $prefs, 'bird' => 1]);
    }

    /**
     * @param list<ProjectionPlayer> $players
     */
    private static function lineup(int $tid, array $players): SeasonLineup
    {
        $byPid = [];
        foreach ($players as $p) {
            $byPid[$p->pid] = $p;
        }

        return new SeasonLineup($tid, 0, new TeamDepth([], true), [], $byPid, true, []);
    }

    /**
     * @return array<int, SeasonLineup>
     */
    private static function lineups(ProjectionPlayer $target): array
    {
        return [
            1 => self::lineup(1, [
                $target,
                self::player(11, 'SG', 200),
                self::player(12, 'G', 100),
                self::player(13, 'GF', 50),
                self::player(14, 'C', 400),
            ]),
            2 => self::lineup(2, [
                self::player(21, 'SG', 150),
                self::player(22, 'SG', 250),
                self::player(23, 'PF', 500),
                self::player(24, 'PG', 120),
            ]),
            3 => self::lineup(3, [
                self::player(31, 'SG', 90),
                self::player(32, 'F', 60),
                self::player(33, 'PG', 80),
            ]),
            4 => self::lineup(4, []),
        ];
    }

    /**
     * @return array<int, TeamFactors>
     */
    private static function factors(): array
    {
        return [
            1 => new TeamFactors(1, 10, 72, 20, 60),
            2 => new TeamFactors(2, 60, 22, 55, 25),
            3 => new TeamFactors(3, 50, 32, 40, 40),
        ];
    }

    /**
     * @param array<string, array<int, int>> $ranks slot => pid => pool rank
     */
    private static function ranks(array $ranks): SlotRankReaderInterface
    {
        return new class ($ranks) implements SlotRankReaderInterface {
            /** @param array<string, array<int, int>> $ranks */
            public function __construct(private readonly array $ranks)
            {
            }

            public function poolRank(string $slot, int $pid): ?int
            {
                return $this->ranks[$slot][$pid] ?? null;
            }

            public function starterRank(string $slot, int $tid): ?int
            {
                return null;
            }

            public function bestBenchRank(string $slot, int $tid): ?int
            {
                return null;
            }

            public function worstStarterPoolRank(string $slot): ?int
            {
                return null;
            }
        };
    }

    public function testBestRankSlotWinsWhenItComesAfterAWorseRank(): void
    {
        $target = self::player(10, 'G', 300, self::HIGH_PREFS);

        $r = ResignOdds::forTeam($target, 1, self::lineups($target), self::ranks(['PG' => [10 => 12], 'SG' => [10 => 4]]), self::factors());

        // SG slot: own 250, rival intdiv(400 + 90 + 0, 3) = 163.
        $expected = ResignLikelihood::likelihood(ResignLikelihood::edge(
            self::HIGH_PREFS,
            1,
            new TeamFactors(1, 10, 72, 20, 60),
            new TeamFactors(0, 40, 42, 38, 42),
            250,
            163,
        ));
        self::assertEqualsWithDelta($expected, $r['likelihood'], 1e-9);
        self::assertFalse($r['walks']);
    }

    public function testBestRankSlotWinsWhenItComesBeforeAWorseRank(): void
    {
        $target = self::player(10, 'G', 300, self::HIGH_PREFS);

        $r = ResignOdds::forTeam($target, 1, self::lineups($target), self::ranks(['PG' => [10 => 4], 'SG' => [10 => 12]]), self::factors());

        // PG slot: own 100, rival intdiv(120 + 80 + 0, 3) = 66.
        $expected = ResignLikelihood::likelihood(ResignLikelihood::edge(
            self::HIGH_PREFS,
            1,
            new TeamFactors(1, 10, 72, 20, 60),
            new TeamFactors(0, 40, 42, 38, 42),
            100,
            66,
        ));
        self::assertEqualsWithDelta($expected, $r['likelihood'], 1e-9);
    }

    public function testTiedPoolRanksKeepTheFirstSlotInSlotOrder(): void
    {
        $target = self::player(10, 'F', 300, self::HIGH_PREFS);

        $r = ResignOdds::forTeam(
            $target,
            1,
            self::lineups($target),
            self::ranks(['PG' => [10 => 6], 'SG' => [10 => 6], 'PF' => [10 => 6]]),
            self::factors(),
        );

        // PG slot (first of the tie), not SG and not the player's own pos slot SF.
        $expected = ResignLikelihood::likelihood(ResignLikelihood::edge(
            self::HIGH_PREFS,
            1,
            new TeamFactors(1, 10, 72, 20, 60),
            new TeamFactors(0, 40, 42, 38, 42),
            100,
            66,
        ));
        self::assertEqualsWithDelta($expected, $r['likelihood'], 1e-9);
    }

    public function testPlayerWithNoPoolRankFallsBackToHisFirstPositionSlot(): void
    {
        $target = self::player(10, 'GF', 300, self::HIGH_PREFS);

        $r = ResignOdds::forTeam($target, 1, self::lineups($target), self::ranks([]), self::factors());

        // slots() = [SG, SF]; slot SG: own 250, rival 163.
        $expected = ResignLikelihood::likelihood(ResignLikelihood::edge(
            self::HIGH_PREFS,
            1,
            new TeamFactors(1, 10, 72, 20, 60),
            new TeamFactors(0, 40, 42, 38, 42),
            250,
            163,
        ));
        self::assertEqualsWithDelta($expected, $r['likelihood'], 1e-9);
        self::assertFalse($r['walks']);
    }

    public function testUnrankedPlayerWithNoPositionSlotsFallsBackToTheFirstLeagueSlot(): void
    {
        $target = self::player(10, 'X', 300, self::HIGH_PREFS);

        $r = ResignOdds::forTeam($target, 1, self::lineups($target), self::ranks([]), self::factors());

        // SlotMapper::SLOTS[0] = PG: own 100, rival 66.
        $expected = ResignLikelihood::likelihood(ResignLikelihood::edge(
            self::HIGH_PREFS,
            1,
            new TeamFactors(1, 10, 72, 20, 60),
            new TeamFactors(0, 40, 42, 38, 42),
            100,
            66,
        ));
        self::assertEqualsWithDelta($expected, $r['likelihood'], 1e-9);
    }

    public function testTeamMissingFromLineupsAndFactorsGetsZeroMoneyAndZeroFactors(): void
    {
        $target = self::player(10, 'G', 300, self::HIGH_PREFS);

        $r = ResignOdds::forTeam($target, 9, self::lineups($target), self::ranks(['SG' => [10 => 4]]), self::factors());

        // Every lineup is a rival: intdiv(250 + 400 + 90 + 0, 4) = 185. Own money 0, own factors all 0.
        $expected = ResignLikelihood::likelihood(ResignLikelihood::edge(
            self::HIGH_PREFS,
            1,
            new TeamFactors(9, 0, 0, 0, 0),
            new TeamFactors(0, 40, 42, 38, 42),
            0,
            185,
        ));
        self::assertEqualsWithDelta($expected, $r['likelihood'], 1e-9);
    }

    public function testSingleTeamLeagueHasNoRivalMoney(): void
    {
        $target = self::player(10, 'G', 300, self::HIGH_PREFS);
        $lineups = [1 => self::lineups($target)[1]];

        $r = ResignOdds::forTeam(
            $target,
            1,
            $lineups,
            self::ranks(['SG' => [10 => 4]]),
            [1 => new TeamFactors(1, 10, 72, 20, 60)],
        );

        $expected = ResignLikelihood::likelihood(ResignLikelihood::edge(
            self::HIGH_PREFS,
            1,
            new TeamFactors(1, 10, 72, 20, 60),
            new TeamFactors(0, 10, 72, 20, 60),
            250,
            0,
        ));
        self::assertEqualsWithDelta($expected, $r['likelihood'], 1e-9);
    }

    /**
     * @return array<string, array{array<int, int>, bool}>
     */
    public static function seasonsLeftProvider(): array
    {
        return [
            'one season, offset 0 only' => [[1 => 300, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0], true],
            'one season, offset 3 only' => [[1 => 0, 2 => 0, 3 => 0, 4 => 300, 5 => 0, 6 => 0], true],
            'two seasons' => [[1 => 300, 2 => 300, 3 => 0, 4 => 0, 5 => 0, 6 => 0], true],
            'three seasons' => [[1 => 300, 2 => 300, 3 => 300, 4 => 0, 5 => 0, 6 => 0], false],
            'four seasons' => [[1 => 300, 2 => 300, 3 => 300, 4 => 300, 5 => 0, 6 => 0], false],
            'no salary' => [[1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0], false],
        ];
    }

    /**
     * @param array<int, int> $salaryByYear
     */
    #[DataProvider('seasonsLeftProvider')]
    public function testWalkFlagFollowsSeasonsLeftForATopTierLowLikelihoodPlayer(array $salaryByYear, bool $walks): void
    {
        $target = self::player(10, 'G', 0, self::LOW_PREFS, $salaryByYear);

        $r = ResignOdds::forTeam($target, 1, self::lineups($target), self::ranks(['SG' => [10 => 4]]), self::factors());

        $expected = ResignLikelihood::likelihood(ResignLikelihood::edge(
            self::LOW_PREFS,
            1,
            new TeamFactors(1, 10, 72, 20, 60),
            new TeamFactors(0, 40, 42, 38, 42),
            250,
            163,
        ));
        self::assertEqualsWithDelta($expected, $r['likelihood'], 1e-9);
        self::assertLessThan(0.4, $expected);
        self::assertSame($walks, $r['walks']);
    }

    public function testLowerTierPlayerNeverWalks(): void
    {
        $target = self::player(10, 'G', 300, self::LOW_PREFS, [1 => 300, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0]);

        $r = ResignOdds::forTeam($target, 1, self::lineups($target), self::ranks(['SG' => [10 => 6]]), self::factors());

        self::assertFalse($r['walks']);
    }

    public function testUnrankedPlayerNeverWalks(): void
    {
        $target = self::player(10, 'G', 300, self::LOW_PREFS, [1 => 300, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0]);

        $r = ResignOdds::forTeam($target, 1, self::lineups($target), self::ranks([]), self::factors());

        self::assertFalse($r['walks']);
    }
}
