<?php

declare(strict_types=1);

namespace Tests\Player\Views;

use Player\Contracts\PlayerRepositoryInterface;

/**
 * @phpstan-import-type AwardRow from PlayerRepositoryInterface
 * @phpstan-import-type PlayerNewsRow from PlayerRepositoryInterface
 * @phpstan-import-type OneOnOneWinRow from PlayerRepositoryInterface
 * @phpstan-import-type OneOnOneLossRow from PlayerRepositoryInterface
 */
class PlayerViewFixtures
{
    /**
     * Three award rows for getAwards() (ordered by year ASC).
     *
     * Value discipline — do not "tidy" these values:
     *  - Row 1 (year=2019, prim=1): an MVP-class primary award; prim=1 kills
     *    the `=== 1` -> `=== 0` mutant on any primary-award indicator.
     *  - Row 2 (year=2021, prim=1): a second distinct primary award; having two
     *    prim=1 rows means a `+` -> `-` mutant on any award counter changes output.
     *  - Row 3 (year=2023, prim=0): a non-primary award; without a prim=0 row a
     *    mutant that always treats awards as primary cannot be killed.
     *  - All year, award, and prim values are distinct across rows — no two rows
     *    collide on the primary key so a `<` -> `<=` mutant on year ordering changes
     *    which award appears first.
     *
     * @return list<AwardRow>
     */
    public static function awardRows(): array
    {
        return [
            ['year' => 2019, 'name' => 'Test Player', 'award' => 'MVP', 'prim' => 1],
            ['year' => 2021, 'name' => 'Test Player', 'award' => 'All-IBL', 'prim' => 1],
            ['year' => 2023, 'name' => 'Test Player', 'award' => 'Sixth Man', 'prim' => 0],
        ];
    }

    /**
     * Two news rows for getPlayerNews() (ordered by time DESC).
     *
     * Value discipline — do not "tidy" these values:
     *  - sid values (101, 247) are distinct non-sequential integers; a mutant that
     *    swaps or omits sid produces a different rendered link.
     *  - title strings are semantically distinct; a mutant that outputs the wrong
     *    title is immediately visible in the snapshot.
     *  - time values differ by both date and hour so a `DESC` -> `ASC` sort mutant
     *    reverses the order, changing which row appears first in the snapshot.
     *    Row 1 has the later timestamp and therefore appears first (DESC).
     *
     * @return list<PlayerNewsRow>
     */
    public static function playerNewsRows(): array
    {
        return [
            ['sid' => 247, 'title' => 'Player wins MVP award', 'time' => '2023-05-20 14:45:00'],
            ['sid' => 101, 'title' => 'Player signs contract extension', 'time' => '2023-03-08 10:30:00'],
        ];
    }

    /**
     * Two win rows for getOneOnOneWins() (ordered by gameid ASC).
     *
     * Value discipline — do not "tidy" these values:
     *  - gameid values (5, 13) are distinct; a `<` -> `<=` mutant on gameid ordering
     *    changes which row appears first.
     *  - winscore always exceeds lossscore (112 > 98, 105 > 101); swapping the two
     *    columns by mutant changes the margin in the snapshot.
     *  - Row 1 has loser_pid=37 (non-null); row 2 has loser_pid=null — exercising
     *    both branches of any null-guard on loser_pid.
     *  - loser names ('Opponent A', 'Opponent B') are distinct; a mutant that
     *    outputs the wrong loser name is visible in the snapshot.
     *
     * @return list<OneOnOneWinRow>
     */
    public static function oneOnOneWinRows(): array
    {
        return [
            [
                'gameid' => 5, 'winner' => 'Test Player', 'loser' => 'Opponent A',
                'winscore' => 112, 'lossscore' => 98, 'loser_pid' => 37,
            ],
            [
                'gameid' => 13, 'winner' => 'Test Player', 'loser' => 'Opponent B',
                'winscore' => 105, 'lossscore' => 101, 'loser_pid' => null,
            ],
        ];
    }

    /**
     * Two loss rows for getOneOnOneLosses() (ordered by gameid ASC).
     *
     * Value discipline — do not "tidy" these values:
     *  - gameid values (8, 21) are distinct and do not collide with the win-row
     *    gameids (5, 13); a mutant mixing wins and losses renders a different gameid.
     *  - winscore always exceeds lossscore (119 > 107, 103 > 94); swapping columns
     *    by mutant changes the rendered margin.
     *  - Row 1 has winner_pid=52 (non-null); row 2 has winner_pid=null — exercising
     *    both branches of any null-guard on winner_pid.
     *  - winner names ('Opponent C', 'Opponent D') are distinct from the win-row
     *    losers; a mutant that sources the wrong row changes the snapshot.
     *
     * @return list<OneOnOneLossRow>
     */
    public static function oneOnOneLossRows(): array
    {
        return [
            [
                'gameid' => 8, 'winner' => 'Opponent C', 'loser' => 'Test Player',
                'winscore' => 119, 'lossscore' => 107, 'winner_pid' => 52,
            ],
            [
                'gameid' => 21, 'winner' => 'Opponent D', 'loser' => 'Test Player',
                'winscore' => 103, 'lossscore' => 94, 'winner_pid' => null,
            ],
        ];
    }
}
