<?php

declare(strict_types=1);

namespace SeasonLeaderboards;

use BasketballStats\StatsFormatter;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsRepositoryInterface;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsServiceInterface;

/**
 * @see SeasonLeaderboardsServiceInterface
 *
 * @phpstan-import-type HistRow from SeasonLeaderboardsRepositoryInterface
 * @phpstan-import-type ProcessedStats from SeasonLeaderboardsServiceInterface
 */
class SeasonLeaderboardsService implements SeasonLeaderboardsServiceInterface
{
    /**
     * @see SeasonLeaderboardsServiceInterface::processPlayerRow()
     *
     * @param HistRow $row Database row from ibl_hist table
     * @return ProcessedStats Formatted player statistics
     */
    public function processPlayerRow(array $row): array
    {
        $pid = $row['pid'];
        $name = $row['name'];
        $year = $row['year'];
        $teamname = $row['team'];
        $teamid = $row['teamid'];
        $teamCity = $row['team_city'] ?? '';
        $color1 = $row['color1'] ?? 'FFFFFF';
        $color2 = $row['color2'] ?? '000000';

        $games = $row['games'];
        $minutes = $row['minutes'];
        $fgm = $row['fgm'];
        $fga = $row['fga'];
        $ftm = $row['ftm'];
        $fta = $row['fta'];
        $tgm = $row['tgm'];
        $tga = $row['tga'];
        $orb = $row['orb'];
        $reb = $row['reb'];
        $ast = $row['ast'];
        $stl = $row['stl'];
        $tvr = $row['tvr'];
        $blk = $row['blk'];
        $pf = $row['pf'];

        // Calculate totals
        $points = StatsFormatter::calculatePoints($fgm, $ftm, $tgm);

        // Format percentages
        $fgp = StatsFormatter::formatPercentage($fgm, $fga);
        $ftp = StatsFormatter::formatPercentage($ftm, $fta);
        $tgp = StatsFormatter::formatPercentage($tgm, $tga);

        // Format per-game averages
        $mpg = StatsFormatter::formatPerGameAverage($minutes, $games);
        $fgmpg = StatsFormatter::formatPerGameAverage($fgm, $games);
        $fgapg = StatsFormatter::formatPerGameAverage($fga, $games);
        $ftmpg = StatsFormatter::formatPerGameAverage($ftm, $games);
        $ftapg = StatsFormatter::formatPerGameAverage($fta, $games);
        $tgmpg = StatsFormatter::formatPerGameAverage($tgm, $games);
        $tgapg = StatsFormatter::formatPerGameAverage($tga, $games);
        $orbpg = StatsFormatter::formatPerGameAverage($orb, $games);
        $rpg = StatsFormatter::formatPerGameAverage($reb, $games);
        $apg = StatsFormatter::formatPerGameAverage($ast, $games);
        $spg = StatsFormatter::formatPerGameAverage($stl, $games);
        $tpg = StatsFormatter::formatPerGameAverage($tvr, $games);
        $bpg = StatsFormatter::formatPerGameAverage($blk, $games);
        $fpg = StatsFormatter::formatPerGameAverage($pf, $games);
        $ppg = StatsFormatter::formatPerGameAverage($points, $games);

        // Calculate Quality Assessment (QA)
        $qa = $this->calculateQualityAssessment($games, $points, $reb, $ast, $stl, $blk, $fga, $fgm, $fta, $ftm, $tvr, $pf);

        return [
            'pid' => $pid,
            'name' => $name,
            'year' => $year,
            'teamname' => $teamname,
            'teamid' => $teamid,
            'team_city' => $teamCity,
            'color1' => $color1,
            'color2' => $color2,
            'games' => $games,
            'minutes' => $minutes,
            'fgm' => $fgm,
            'fga' => $fga,
            'ftm' => $ftm,
            'fta' => $fta,
            'tgm' => $tgm,
            'tga' => $tga,
            'orb' => $orb,
            'reb' => $reb,
            'ast' => $ast,
            'stl' => $stl,
            'tvr' => $tvr,
            'blk' => $blk,
            'pf' => $pf,
            'points' => $points,
            'fgp' => $fgp,
            'ftp' => $ftp,
            'tgp' => $tgp,
            'mpg' => $mpg,
            'fgmpg' => $fgmpg,
            'fgapg' => $fgapg,
            'ftmpg' => $ftmpg,
            'ftapg' => $ftapg,
            'tgmpg' => $tgmpg,
            'tgapg' => $tgapg,
            'orbpg' => $orbpg,
            'rpg' => $rpg,
            'apg' => $apg,
            'spg' => $spg,
            'tpg' => $tpg,
            'bpg' => $bpg,
            'fpg' => $fpg,
            'ppg' => $ppg,
            'qa' => $qa,
        ];
    }

    /**
     * Calculate Quality Assessment metric
     *
     * @return string Formatted QA value (1 decimal place)
     */
    private function calculateQualityAssessment(
        int $games,
        int $points,
        int $reb,
        int $ast,
        int $stl,
        int $blk,
        int $fga,
        int $fgm,
        int $fta,
        int $ftm,
        int $tvr,
        int $pf
    ): string {
        if ($games === 0) {
            return "0.0";
        }

        $positives = $points + $reb + (2 * $ast) + (2 * $stl) + (2 * $blk);
        $negatives = ($fga - $fgm) + ($fta - $ftm) + $tvr + $pf;
        $qa = ($positives - $negatives) / $games;

        return number_format($qa, 1);
    }

    /**
     * @see SeasonLeaderboardsServiceInterface::getSortOptions()
     *
     * @return list<string>
     */
    public function getSortOptions(): array
    {
        return [
            "PPG", "REB", "OREB", "AST", "STL", "BLK", "TO", "FOUL", 
            "QA", "FGM", "FGA", "FG%", "FTM", "FTA", "FT%", 
            "TGM", "TGA", "TG%", "GAMES", "MIN"
        ];
    }
}
