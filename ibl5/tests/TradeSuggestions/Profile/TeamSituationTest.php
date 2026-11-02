<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Profile;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Profile\SeasonLineup;
use TradeSuggestions\Profile\TeamSituation;
use TradeSuggestions\Projection\InjuryStatus;

final class TeamSituationTest extends TestCase
{
    public function testConferenceRankOrdersByPctThenWinsThenTid(): void
    {
        $standings = [
            1 => self::row(30, 20, 'East', 0.600),
            2 => self::row(33, 22, 'East', 0.600),
            3 => self::row(35, 15, 'East', 0.700),
            4 => self::row(33, 22, 'East', 0.600),
            5 => self::row(45, 5, 'West', 0.900),
        ];

        self::assertSame(1, self::situation(3, $standings)->conferenceRank);
        self::assertSame(2, self::situation(2, $standings)->conferenceRank);
        self::assertSame(3, self::situation(4, $standings)->conferenceRank);
        self::assertSame(4, self::situation(1, $standings)->conferenceRank);
        self::assertSame(1, self::situation(5, $standings)->conferenceRank);
    }

    public function testPlayoffBubbleWithinThreeGamesOfEighthSeed(): void
    {
        self::assertTrue(self::situation(9, self::conference(37, 33))->playoffBubble);
        self::assertFalse(self::situation(9, self::conference(37, 34))->playoffBubble);
    }

    public function testTopSeedFarAheadIsNotOnBubble(): void
    {
        $situation = self::situation(1, self::conference(37, 33));

        self::assertSame(1, $situation->conferenceRank);
        self::assertFalse($situation->playoffBubble);
    }

    public function testSeventhSeedOneGameUpOnNinthIsOnBubble(): void
    {
        $standings = self::conference(40, 30);
        $standings[7] = self::row(41, 29, 'East');

        $situation = self::situation(7, $standings);

        self::assertSame(7, $situation->conferenceRank);
        self::assertTrue($situation->playoffBubble);
    }

    public function testOpenRosterSpotsNeverNegative(): void
    {
        $standings = [1 => self::row(10, 10, 'East')];
        $full = [];
        for ($pid = 1; $pid <= 16; $pid++) {
            $full[] = ProjectionFactory::player(pid: $pid);
        }

        self::assertSame(0, self::situation(1, $standings, ProjectionFactory::lineupFor(1, 0, $full))->openRosterSpots);
        self::assertSame(5, self::situation(1, $standings, ProjectionFactory::lineupFor(1, 0, array_slice($full, 0, 10)))->openRosterSpots);
    }

    public function testCoreAgeIsMinutesWeightedOverTopEight(): void
    {
        $players = [];
        for ($pid = 1; $pid <= 8; $pid++) {
            $young = $pid <= 4;
            $players[] = ProjectionFactory::player(
                pid: $pid,
                age: $young ? 24 : 28,
                production: ProjectionFactory::productionLine(['stats_min' => $young ? 1200 : 480]),
            );
        }
        // The ninth player is 40 and has the fewest minutes.
        $players[] = ProjectionFactory::player(pid: 9, age: 40, production: ProjectionFactory::productionLine(['stats_min' => 400]));

        $situation = self::situation(1, [1 => self::row(10, 10, 'East')], ProjectionFactory::lineupFor(1, 0, $players));

        // (4 * 24 * 30 + 4 * 28 * 12) / (4 * 30 + 4 * 12) = 25.142857...
        self::assertEqualsWithDelta((4 * 24 * 30 + 4 * 28 * 12) / 168, $situation->coreAge, 1e-9);
    }

    public function testCoreAgeZeroForEmptyRoster(): void
    {
        self::assertSame(0.0, self::situation(1, [1 => self::row(10, 10, 'East')])->coreAge);
    }

    public function testUnknownTidThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::situation(99, [1 => self::row(10, 10, 'East')]);
    }

    public function testInjuryStatusForUsesGamesLeftAndSeasonFlag(): void
    {
        $standings = [1 => self::row(10, 10, 'East', null, 20)];
        $injured = ProjectionFactory::player(injuredDays: 20);
        $lineup = ProjectionFactory::lineupFor(1, 0, []);

        $inSeason = TeamSituation::build(1, $standings, [], $lineup, true);
        $offSeason = TeamSituation::build(1, $standings, [], $lineup, false);

        self::assertSame(InjuryStatus::OutForYear, $inSeason->injuryStatusFor($injured));
        self::assertSame(InjuryStatus::Healthy, $offSeason->injuryStatusFor($injured));
        self::assertSame(20, $inSeason->gamesLeft);
    }

    /**
     * @param array<int, array{wins: int, losses: int, pct: float, games_unplayed: int, conference: string}> $standings
     */
    private static function situation(int $tid, array $standings, ?SeasonLineup $lineup = null): TeamSituation
    {
        return TeamSituation::build(
            $tid,
            $standings,
            [$tid => ['last_win' => 6, 'last_loss' => 4]],
            $lineup ?? ProjectionFactory::lineupFor($tid, 0, []),
            true
        );
    }

    /**
     * Nine East teams: tids 1-7 well clear, tid 8 at 40-30, tid 9 at the given record.
     *
     * @return array<int, array{wins: int, losses: int, pct: float, games_unplayed: int, conference: string}>
     */
    private static function conference(int $ninthWins, int $ninthLosses): array
    {
        $standings = [];
        for ($tid = 1; $tid <= 7; $tid++) {
            $standings[$tid] = self::row(60 - $tid, 10 + $tid, 'East');
        }
        $standings[8] = self::row(40, 30, 'East');
        $standings[9] = self::row($ninthWins, $ninthLosses, 'East');

        return $standings;
    }

    /**
     * @return array{wins: int, losses: int, pct: float, games_unplayed: int, conference: string}
     */
    private static function row(int $wins, int $losses, string $conference, ?float $pct = null, int $gamesUnplayed = 12): array
    {
        return [
            'wins' => $wins,
            'losses' => $losses,
            'pct' => $pct ?? $wins / ($wins + $losses),
            'games_unplayed' => $gamesUnplayed,
            'conference' => $conference,
        ];
    }
}
