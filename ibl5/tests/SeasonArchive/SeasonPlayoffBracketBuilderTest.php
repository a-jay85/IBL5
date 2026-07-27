<?php

declare(strict_types=1);

namespace Tests\SeasonArchive;

use PHPUnit\Framework\TestCase;
use SeasonArchive\SeasonPlayoffBracketBuilder;

/**
 * @covers \SeasonArchive\SeasonPlayoffBracketBuilder
 */
class SeasonPlayoffBracketBuilderTest extends TestCase
{
    private SeasonPlayoffBracketBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new SeasonPlayoffBracketBuilder();
    }

    /**
     * @return array{year: int, round: int, winner: string, loser: string, winner_games: int, loser_games: int}
     */
    private function row(int $round, string $winner, string $loser, int $winnerGames = 4, int $loserGames = 2): array
    {
        return [
            'year' => 2000,
            'round' => $round,
            'winner' => $winner,
            'loser' => $loser,
            'winner_games' => $winnerGames,
            'loser_games' => $loserGames,
        ];
    }

    // ─── buildPlayoffBracket ─────────────────────────────────────────────────

    public function testBuildPlayoffBracketGroupsRowsByRound(): void
    {
        $rows = [
            $this->row(3, 'Chicago', 'Utah'),
            $this->row(4, 'LA', 'Chicago'),
            $this->row(5, 'ignore', 'me'),
        ];

        $bracket = $this->builder->buildPlayoffBracket($rows);

        $this->assertSame([3, 4, 5], array_keys($bracket));
        $this->assertCount(1, $bracket[4]);
        $this->assertSame('LA', $bracket[4][0]['winner']);
    }

    public function testBuildPlayoffBracketAccumulatesMultipleSeriesInSameRound(): void
    {
        $rows = [
            $this->row(1, 'Chicago', 'Utah', 4, 1),
            $this->row(1, 'LA', 'Boston', 4, 2),
            $this->row(4, 'Chicago', 'LA', 4, 3),
        ];

        $bracket = $this->builder->buildPlayoffBracket($rows);

        $this->assertCount(2, $bracket[1]);
        $this->assertSame('Chicago', $bracket[1][0]['winner']);
        $this->assertSame('LA', $bracket[1][1]['winner']);
    }

    public function testBuildPlayoffBracketPreservesInsertionOrderWithinRound(): void
    {
        $rows = [
            $this->row(1, 'TeamA', 'TeamB'),
            $this->row(1, 'TeamC', 'TeamD'),
            $this->row(1, 'TeamE', 'TeamF'),
            $this->row(4, 'TeamA', 'TeamC'),
        ];

        $bracket = $this->builder->buildPlayoffBracket($rows);

        $this->assertSame(
            [
                ['winner' => 'TeamA', 'loser' => 'TeamB', 'loserGames' => 2],
                ['winner' => 'TeamC', 'loser' => 'TeamD', 'loserGames' => 2],
                ['winner' => 'TeamE', 'loser' => 'TeamF', 'loserGames' => 2],
            ],
            $bracket[1]
        );
    }

    public function testBuildPlayoffBracketSortsRoundKeysAscending(): void
    {
        $rows = [
            $this->row(4, 'Chicago', 'LA'),
            $this->row(1, 'Chicago', 'Utah'),
        ];

        $bracket = $this->builder->buildPlayoffBracket($rows);

        $this->assertSame([1, 4], array_keys($bracket));
    }

    public function testBuildPlayoffBracketMapsOnlySeriesFields(): void
    {
        $rows = [
            $this->row(4, 'Chicago', 'Utah', 4, 2),
        ];

        $bracket = $this->builder->buildPlayoffBracket($rows);

        $this->assertSame(
            [['winner' => 'Chicago', 'loser' => 'Utah', 'loserGames' => 2]],
            $bracket[4]
        );
    }

    public function testBuildPlayoffBracketReturnsEmptyArrayForNoRows(): void
    {
        $this->assertSame([], $this->builder->buildPlayoffBracket([]));
    }

    /**
     * Pre-expansion seasons legitimately produce non-contiguous rounds (e.g. rounds 1 and 4
     * with no 2 or 3) and first rounds with fewer than 8 teams. This is historically correct
     * data and must be preserved exactly as stored. Any future change that fills gaps,
     * renumbers rounds, or pads to a power of two is a behavior regression — fix the change,
     * not this test.
     */
    public function testBuildPlayoffBracketPreservesIrregularHistoricalShapes(): void
    {
        $rows = [
            $this->row(1, 'TeamA', 'TeamB'),
            $this->row(4, 'TeamA', 'TeamC'),
        ];

        $bracket = $this->builder->buildPlayoffBracket($rows);

        $this->assertSame([1, 4], array_keys($bracket));
        $this->assertArrayNotHasKey(2, $bracket);
        $this->assertArrayNotHasKey(3, $bracket);
    }

    // ─── getIblFinals ────────────────────────────────────────────────────────

    public function testGetIblFinalsReturnsRoundFourSeries(): void
    {
        $rows = [
            $this->row(3, 'Chicago', 'Utah', 4, 1),
            $this->row(4, 'Chicago', 'LA', 4, 2),
            $this->row(5, 'other', 'team', 4, 0),
        ];

        $this->assertSame(
            ['winner' => 'Chicago', 'loser' => 'LA', 'loserGames' => 2],
            $this->builder->getIblFinals($rows)
        );
    }

    public function testGetIblFinalsReturnsFirstMatchWhenMultipleRoundFourRows(): void
    {
        $rows = [
            $this->row(4, 'First', 'A', 4, 1),
            $this->row(4, 'Second', 'B', 4, 2),
        ];

        $result = $this->builder->getIblFinals($rows);

        $this->assertSame('First', $result['winner']);
    }

    public function testGetIblFinalsReturnsEmptyStructWhenNoRoundFour(): void
    {
        $rows = [
            $this->row(3, 'Chicago', 'Utah'),
            $this->row(5, 'LA', 'Boston'),
        ];

        $this->assertSame(
            ['winner' => '', 'loser' => '', 'loserGames' => 0],
            $this->builder->getIblFinals($rows)
        );
    }

    public function testGetIblFinalsReturnsEmptyStructForEmptyInput(): void
    {
        $this->assertSame(
            ['winner' => '', 'loser' => '', 'loserGames' => 0],
            $this->builder->getIblFinals([])
        );
    }

    public function testGetIblFinalsLoserGamesComesFromLoserGamesField(): void
    {
        $rows = [
            $this->row(3, 'A', 'B', 4, 0),
            $this->row(4, 'Chicago', 'Utah', 4, 1),
            $this->row(5, 'C', 'D', 4, 0),
        ];

        $result = $this->builder->getIblFinals($rows);

        $this->assertSame(1, $result['loserGames']);
    }

    // ─── getIblChampionFromPlayoffs ──────────────────────────────────────────

    public function testGetIblChampionReturnsRoundFourWinner(): void
    {
        $rows = [
            $this->row(3, 'Utah', 'Denver'),
            $this->row(4, 'Chicago', 'Utah'),
            $this->row(5, 'Other', 'Team'),
        ];

        $this->assertSame('Chicago', $this->builder->getIblChampionFromPlayoffs($rows));
    }

    public function testGetIblChampionReturnsWinnerNotLoser(): void
    {
        $rows = [
            $this->row(4, 'Chicago', 'Utah'),
        ];

        $this->assertNotSame('Utah', $this->builder->getIblChampionFromPlayoffs($rows));
        $this->assertSame('Chicago', $this->builder->getIblChampionFromPlayoffs($rows));
    }

    public function testGetIblChampionReturnsEmptyStringWhenNoRoundFour(): void
    {
        $rows = [
            $this->row(3, 'Chicago', 'Utah'),
            $this->row(5, 'LA', 'Boston'),
        ];

        $this->assertSame('', $this->builder->getIblChampionFromPlayoffs($rows));
    }

    public function testGetIblChampionReturnsFirstRoundFourWinner(): void
    {
        $rows = [
            $this->row(4, 'First', 'A'),
            $this->row(4, 'Second', 'B'),
        ];

        $this->assertSame('First', $this->builder->getIblChampionFromPlayoffs($rows));
    }
}
