<?php

declare(strict_types=1);

namespace Tests\Player\Stats\Views;

final class TournamentViewFixtures
{
    /** @return list<array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int}> */
    public static function twoSeasonRows(): array
    {
        return [
            [
                'year' => 28, 'pos' => 'SG', 'pid' => 100, 'name' => 'Test Player',
                'team' => 'Miami', 'games' => 5, 'minutes' => 160, 'fgm' => 40,
                'fga' => 80, 'ftm' => 20, 'fta' => 25, 'tgm' => 10, 'tga' => 30,
                'orb' => 8, 'reb' => 25, 'ast' => 18, 'stl' => 6, 'tvr' => 12,
                'blk' => 3, 'pf' => 14, 'pts' => 110,
            ],
            [
                'year' => 29, 'pos' => 'SG', 'pid' => 100, 'name' => 'Test Player',
                'team' => 'Orlando', 'games' => 3, 'minutes' => 90, 'fgm' => 22,
                'fga' => 50, 'ftm' => 12, 'fta' => 15, 'tgm' => 6, 'tga' => 18,
                'orb' => 4, 'reb' => 14, 'ast' => 10, 'stl' => 4, 'tvr' => 8,
                'blk' => 2, 'pf' => 9, 'pts' => 62,
            ],
        ];
    }

    /** @return array{pid: int, name: string, games: int, minutes: float, fgm: float, fga: float, fgpct: float, ftm: float, fta: float, ftpct: float, tgm: float, tga: float, tpct: float, orb: float, reb: float, ast: float, stl: float, tvr: float, blk: float, pf: float, pts: float, retired: int} */
    public static function careerAveragesRow(): array
    {
        return [
            'pid' => 100, 'name' => 'Test Player', 'games' => 8,
            'minutes' => 31.3, 'fgm' => 7.0, 'fga' => 16.0, 'fgpct' => 0.477,
            'ftm' => 4.0, 'fta' => 5.0, 'ftpct' => 0.800,
            'tgm' => 2.0, 'tga' => 6.0, 'tpct' => 0.333,
            'orb' => 1.5, 'reb' => 4.9, 'ast' => 3.5,
            'stl' => 1.3, 'tvr' => 2.5, 'blk' => 0.6,
            'pf' => 2.9, 'pts' => 21.5, 'retired' => 0,
        ];
    }
}
