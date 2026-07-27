<?php

declare(strict_types=1);

namespace Tests\Player\Stats\Views;

class RegularSeasonViewFixtures
{
    /**
     * Three seasons of getHistoricalStats() rows (superset shape: season stats +
     * ratings + salary), used by the Totals, Averages and Ratings view tests.
     *
     * Value discipline — do not "tidy" these numbers:
     *  - Row 1 and row 2 carry a stored `pts` that DIFFERS from (2*fgm)+ftm+tgm, so the
     *    per-row `if ($pts === 0)` recalculation branch stays untaken for them.
     *  - Row 3 carries `pts => 0`, forcing that branch to fire and render 509.
     *  - Displayed row points sum to 795+1177+509 = 2481, while the career recalculation
     *    yields 2*(300+412+187) + (150+221+94) + (60+97+41) = 2461. The two MUST differ,
     *    otherwise "career points are recalculated, not summed" is unverifiable.
     *  - Every r_* value is distinct within its row, and r_Off/r_Def never collide
     *    (244/233, 260/256, 261/238), so a `+` -> `-` mutant always changes the output.
     *  - Salaries sum to 12550 -> "125.5 million": non-integer, so both the `/ 100` ->
     *    `* 100` mutant and the `+=` -> `-=` mutant render differently.
     *
     * @return list<array<string, int|string>>
     */
    public static function seasonRows(): array
    {
        return [
            [
                'year' => 2029, 'team' => 'ATL', 'teamid' => 3,
                'pid' => 42, 'name' => 'Test Player',
                'games' => 41, 'minutes' => 1230,
                'fgm' => 300, 'fga' => 640, 'ftm' => 150, 'fta' => 190,
                'tgm' => 60, 'tga' => 175,
                'orb' => 55, 'reb' => 210, 'ast' => 130, 'stl' => 45,
                'tvr' => 70, 'blk' => 25, 'pf' => 95, 'pts' => 795,
                'r_2ga' => 12, 'r_2gp' => 47, 'r_fta' => 5, 'r_ftp' => 79,
                'r_3ga' => 6, 'r_3gp' => 34, 'r_orb' => 41, 'r_drb' => 52,
                'r_ast' => 63, 'r_stl' => 38, 'r_blk' => 26, 'r_tvr' => 44,
                'r_oo' => 71, 'r_drive_off' => 58, 'r_po' => 49, 'r_trans_off' => 66,
                'r_od' => 73, 'r_dd' => 54, 'r_pd' => 61, 'r_td' => 45,
                'salary' => 3250,
            ],
            [
                'year' => 2030, 'team' => 'BOS', 'teamid' => 7,
                'pid' => 42, 'name' => 'Test Player',
                'games' => 58, 'minutes' => 1885,
                'fgm' => 412, 'fga' => 903, 'ftm' => 221, 'fta' => 268,
                'tgm' => 97, 'tga' => 259,
                'orb' => 83, 'reb' => 347, 'ast' => 196, 'stl' => 71,
                'tvr' => 104, 'blk' => 39, 'pf' => 142, 'pts' => 1177,
                'r_2ga' => 15, 'r_2gp' => 51, 'r_fta' => 7, 'r_ftp' => 83,
                'r_3ga' => 9, 'r_3gp' => 37, 'r_orb' => 46, 'r_drb' => 57,
                'r_ast' => 68, 'r_stl' => 42, 'r_blk' => 29, 'r_tvr' => 48,
                'r_oo' => 76, 'r_drive_off' => 62, 'r_po' => 53, 'r_trans_off' => 69,
                'r_od' => 78, 'r_dd' => 59, 'r_pd' => 64, 'r_td' => 55,
                'salary' => 4300,
            ],
            [
                // pts => 0 forces the (2*fgm)+ftm+tgm recalculation: renders 509.
                'year' => 2031, 'team' => 'DEN', 'teamid' => 9,
                'pid' => 42, 'name' => 'Test Player',
                'games' => 33, 'minutes' => 921,
                'fgm' => 187, 'fga' => 403, 'ftm' => 94, 'fta' => 118,
                'tgm' => 41, 'tga' => 112,
                'orb' => 36, 'reb' => 148, 'ast' => 87, 'stl' => 29,
                'tvr' => 51, 'blk' => 17, 'pf' => 66, 'pts' => 0,
                'r_2ga' => 18, 'r_2gp' => 44, 'r_fta' => 6, 'r_ftp' => 88,
                'r_3ga' => 11, 'r_3gp' => 31, 'r_orb' => 39, 'r_drb' => 60,
                'r_ast' => 72, 'r_stl' => 35, 'r_blk' => 33, 'r_tvr' => 41,
                'r_oo' => 68, 'r_drive_off' => 74, 'r_po' => 56, 'r_trans_off' => 63,
                'r_od' => 81, 'r_dd' => 47, 'r_pd' => 58, 'r_td' => 52,
                'salary' => 5000,
            ],
        ];
    }

    /**
     * A single season with games === 0. Consumed only by the Averages/Totals tests,
     * which read no r_* keys, so the ratings columns are intentionally absent.
     *
     * Purpose: kills the `$gm > 0` -> `$gm >= 0` boundary mutant on the three PERCENTAGE
     * columns of PlayerRegularSeasonAveragesView. At games === 0 the unmutated ternary
     * renders the literal '0.0', while the mutant calls
     * StatsFormatter::formatPercentageWithDecimals(0, 0) which returns '0.000'.
     * (The same mutant on the fifteen per-game columns is provably equivalent — see
     * Architectural trade-offs.)
     *
     * @return list<array<string, int|string>>
     */
    public static function zeroGamesRows(): array
    {
        return [
            [
                'year' => 2032, 'team' => 'CHI', 'teamid' => 11,
                'games' => 0, 'minutes' => 0,
                'fgm' => 0, 'fga' => 0, 'ftm' => 0, 'fta' => 0,
                'tgm' => 0, 'tga' => 0,
                'orb' => 0, 'reb' => 0, 'ast' => 0, 'stl' => 0,
                'tvr' => 0, 'blk' => 0, 'pf' => 0, 'pts' => 0,
            ],
        ];
    }

    /**
     * A season with games > 0 but ZERO shot attempts.
     *
     * Purpose: PlayerRegularSeasonAveragesView::computeCareerAveragesFromHistory() guards
     * each percentage with `$fga > 0 ? round($fgm / $fga, 3) : 0.0`. With totalGames > 0
     * the method does not early-return null, so the `>` -> `>=` mutant on all three guards
     * evaluates `0 / 0` and throws DivisionByZeroError — the mutant dies by error. Without
     * this fixture those three mutants survive.
     *
     * @return list<array<string, int|string>>
     */
    public static function noAttemptsRows(): array
    {
        return [
            [
                'year' => 2033, 'team' => 'DAL', 'teamid' => 14,
                'pid' => 42, 'name' => 'Test Player',
                'games' => 9, 'minutes' => 63,
                'fgm' => 0, 'fga' => 0, 'ftm' => 0, 'fta' => 0,
                'tgm' => 0, 'tga' => 0,
                'orb' => 4, 'reb' => 11, 'ast' => 6, 'stl' => 2,
                'tvr' => 3, 'blk' => 1, 'pf' => 8, 'pts' => 0,
            ],
        ];
    }

    /**
     * getSimDates(20) rows. Shape asserted by the view's own docblock:
     * array{sim: int, start_date: string, end_date: string}.
     *
     * ORDER IS LOAD-BEARING. Sim 13 has no box scores, so the view's
     * `if ($numberOfGames === 0) { continue; }` fires. Sim 14 follows it and DOES have
     * box scores — that is the only reason the `continue` -> `break` mutant is
     * observable (under `break`, sim 14 disappears from the rendered table).
     * Do not sort, reorder, or drop a sim.
     *
     * @return list<array{sim: int, start_date: string, end_date: string}>
     */
    public static function simDates(): array
    {
        return [
            ['sim' => 12, 'start_date' => '2030-01-05', 'end_date' => '2030-01-11'],
            ['sim' => 13, 'start_date' => '2030-01-12', 'end_date' => '2030-01-18'],
            ['sim' => 14, 'start_date' => '2030-01-19', 'end_date' => '2030-01-25'],
        ];
    }

    /**
     * Two box scores for sim 12. Totals: pts = 2*(7+4) + 3*(3+2) + (5+8) = 50,
     * fgm = 16, fga = 40, tgm = 5, tga = 14, ftm = 13, fta = 16.
     * The 2x and 3x coefficients are distinct and the operands differ, so swapping them
     * yields 56 != 50 and a `+` -> `-` mutant on fgm yields 6 != 16.
     *
     * @return list<array<string, int>>
     */
    public static function boxScoresForSim12(): array
    {
        return [
            [
                'game_min' => 34, 'game_2gm' => 7, 'game_2ga' => 15,
                'game_3gm' => 3, 'game_3ga' => 8, 'game_ftm' => 5, 'game_fta' => 6,
                'game_orb' => 2, 'game_drb' => 9, 'game_ast' => 4, 'game_stl' => 3,
                'game_tov' => 1, 'game_blk' => 2, 'game_pf' => 4,
            ],
            [
                'game_min' => 29, 'game_2gm' => 4, 'game_2ga' => 11,
                'game_3gm' => 2, 'game_3ga' => 6, 'game_ftm' => 8, 'game_fta' => 10,
                'game_orb' => 3, 'game_drb' => 7, 'game_ast' => 6, 'game_stl' => 1,
                'game_tov' => 5, 'game_blk' => 1, 'game_pf' => 3,
            ],
        ];
    }

    /**
     * One box score for sim 14. Totals: pts = 18 + 12 + 6 = 36 over 1 game.
     * Single-game totals differ from sim 12's two-game averages in every column, so a
     * mutant that mixes the two sims changes the snapshot.
     *
     * @return list<array<string, int>>
     */
    public static function boxScoresForSim14(): array
    {
        return [
            [
                'game_min' => 41, 'game_2gm' => 9, 'game_2ga' => 19,
                'game_3gm' => 4, 'game_3ga' => 12, 'game_ftm' => 6, 'game_fta' => 7,
                'game_orb' => 5, 'game_drb' => 13, 'game_ast' => 8, 'game_stl' => 2,
                'game_tov' => 3, 'game_blk' => 4, 'game_pf' => 2,
            ],
        ];
    }
}
