<?php

declare(strict_types=1);

namespace Player\Stats\Views\Contracts;

use Player\Stats\Views\PlayerSeasonTableConfig;

interface PlayerSeasonTableRendererInterface
{
    /**
     * @param list<array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int}> $seasonRows
     * @param array{pid: int, name: string, games: int, minutes: float, fgm: float, fga: float, fgpct: float, ftm: float, fta: float, ftpct: float, tgm: float, tga: float, tpct: float, orb: float, reb: float, ast: float, stl: float, tvr: float, blk: float, pf: float, pts: float, retired: int, ...<string, mixed>}|null $careerAverages
     */
    public function render(PlayerSeasonTableConfig $config, array $seasonRows, ?array $careerAverages = null): string;
}
