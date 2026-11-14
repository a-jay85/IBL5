<?php

declare(strict_types=1);

namespace TradeSuggestions\Projection;

use League\LeagueContext;
use TradeSuggestions\ProductionLine;
use TradeSuggestions\Projection\Contracts\ProjectionPlayerRepositoryInterface;

/**
 * Loads the v3 projection inputs. It is a sibling of v2's
 * SuggestionAssetRepository and copies its roster filter, so both pipelines
 * read the same player set. `peak` and `stamina` are never selected.
 *
 * @see ProjectionPlayerRepositoryInterface
 */
final class ProjectionPlayerRepository extends \Database\BaseMysqliRepository implements ProjectionPlayerRepositoryInterface
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @see ProjectionPlayerRepositoryInterface::getLeaguePlayers()
     *
     * @return array<int, list<ProjectionPlayer>>
     */
    public function getLeaguePlayers(bool $advancesContractYears, int $lastSeasonYear): array
    {
        $rows = $this->fetchAll(
            "SELECT pid, name, pos, age, teamid, injured, cy, cyt, ordinal, exp, bird, talent,
                    loyalty, winner, tradition, security, playing_time,
                    draftyear, draftround, draftpickno,
                    pg_depth, sg_depth, sf_depth, pf_depth, c_depth,
                    dc_pg_depth, dc_sg_depth, dc_sf_depth, dc_pf_depth, dc_c_depth, dc_minutes,
                    oo, od, dd, td, po, pd, r_drive_off, r_trans_off,
                    r_fga, r_fgp, r_fta, r_ftp, r_3ga, r_3gp, r_orb, r_drb, r_ast, r_stl, r_blk, r_tvr, r_foul,
                    stats_gm, stats_min, stats_fgm, stats_fga, stats_ftm, stats_fta, stats_3gm, stats_3ga,
                    stats_orb, stats_drb, stats_ast, stats_stl, stats_blk, stats_tvr, stats_pf,
                    salary_yr1, salary_yr2, salary_yr3, salary_yr4, salary_yr5, salary_yr6
             FROM `ibl_plr`
             WHERE retired = 0 AND ordinal <= 960 AND teamid > 0
             ORDER BY teamid, ordinal, pid"
        );

        $histByPid = $this->getLastSeasonLines($lastSeasonYear);

        $players = [];
        foreach ($rows as $row) {
            $hist = $histByPid[self::scalarToInt($row['pid'] ?? null)] ?? null;
            $player = self::toProjectionPlayer($row, $advancesContractYears, $hist);
            $players[$player->tid][] = $player;
        }

        return $players;
    }

    /**
     * @see ProjectionPlayerRepositoryInterface::getRatingScale()
     */
    public function getRatingScale(): RatingScale
    {
        // One MAX per Rating::ALL key; ProjectionPlayerRepositoryTest checks the list stays complete.
        $rows = $this->fetchAll(
            "SELECT MAX(`r_drive_off`) AS `r_drive_off`, MAX(`r_trans_off`) AS `r_trans_off`,
                    MAX(`dd`) AS `dd`, MAX(`td`) AS `td`, MAX(`r_stl`) AS `r_stl`, MAX(`r_blk`) AS `r_blk`,
                    MAX(`r_orb`) AS `r_orb`, MAX(`r_drb`) AS `r_drb`,
                    MAX(`oo`) AS `oo`, MAX(`od`) AS `od`, MAX(`po`) AS `po`, MAX(`pd`) AS `pd`,
                    MAX(`r_fga`) AS `r_fga`, MAX(`r_fgp`) AS `r_fgp`, MAX(`r_fta`) AS `r_fta`, MAX(`r_ftp`) AS `r_ftp`,
                    MAX(`r_3ga`) AS `r_3ga`, MAX(`r_3gp`) AS `r_3gp`, MAX(`r_ast`) AS `r_ast`,
                    MAX(`r_tvr`) AS `r_tvr`, MAX(`r_foul`) AS `r_foul`
             FROM `ibl_plr` WHERE retired = 0"
        );

        return RatingScale::fromRows(array_values($rows));
    }

    /**
     * One ibl_hist row per pid at $year ((pid, year) is the primary key).
     *
     * @return array<int, array<string, mixed>> Keyed by pid
     */
    private function getLastSeasonLines(int $year): array
    {
        $rows = $this->fetchAll(
            "SELECT pid, games, minutes, fgm, fga, ftm, fta, tgm, tga, reb, ast, stl, blk, tvr, pf, pts
             FROM `ibl_hist` WHERE year = ?",
            'i',
            $year
        );

        $byPid = [];
        foreach ($rows as $row) {
            $byPid[self::scalarToInt($row['pid'] ?? null)] = $row;
        }

        return $byPid;
    }

    /**
     * Mirrors v2 `SuggestionAssetRepository::toPlayer()` for the effective
     * contract year, the production sample rule, and `tradeable`.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed>|null $histRow
     */
    private static function toProjectionPlayer(array $row, bool $advancesContractYears, ?array $histRow): ProjectionPlayer
    {
        $cy = self::scalarToInt($row['cy'] ?? null);
        $cyt = self::scalarToInt($row['cyt'] ?? null);
        $effectiveCy = $advancesContractYears ? $cy + 1 : $cy;

        $salaryByYear = [];
        for ($year = 1; $year <= 6; $year++) {
            $salaryByYear[$year] = self::scalarToInt($row['salary_yr' . $year] ?? null);
        }

        $currentSalary = $cy !== 0 ? ($salaryByYear[$effectiveCy] ?? 0) : 0;
        $yearsRemaining = $currentSalary > 0 ? max(1, $cyt - $effectiveCy + 1) : 0;
        $ordinal = self::scalarToInt($row['ordinal'] ?? null);

        $current = ProductionLine::fromCurrentRow($row);
        $last = $histRow === null ? null : ProductionLine::fromHistRow($histRow);
        $production = ProductionLine::choose($current, $last);

        $fouls = 0;
        if ($production === $current) {
            $fouls = self::scalarToInt($row['stats_pf'] ?? null);
        } elseif ($production !== null && $histRow !== null) {
            $fouls = self::scalarToInt($histRow['pf'] ?? null);
        }

        return new ProjectionPlayer(
            pid: self::scalarToInt($row['pid'] ?? null),
            name: self::scalarToString($row['name'] ?? null),
            pos: self::scalarToString($row['pos'] ?? null),
            tid: self::scalarToInt($row['teamid'] ?? null),
            age: self::scalarToInt($row['age'] ?? null),
            injuredDays: self::scalarToInt($row['injured'] ?? null),
            exp: self::scalarToInt($row['exp'] ?? null),
            talent: self::scalarToInt($row['talent'] ?? null),
            bird: self::scalarToInt($row['bird'] ?? null),
            draftRound: self::scalarToInt($row['draftround'] ?? null),
            draftYear: self::scalarToInt($row['draftyear'] ?? null),
            draftPickNo: self::scalarToInt($row['draftpickno'] ?? null),
            prefs: [
                'loyalty' => self::scalarToInt($row['loyalty'] ?? null),
                'winner' => self::scalarToInt($row['winner'] ?? null),
                'tradition' => self::scalarToInt($row['tradition'] ?? null),
                'security' => self::scalarToInt($row['security'] ?? null),
                'playing_time' => self::scalarToInt($row['playing_time'] ?? null),
            ],
            ratings: RatingSet::fromRow($row),
            depth: [
                'PG' => self::scalarToInt($row['pg_depth'] ?? null),
                'SG' => self::scalarToInt($row['sg_depth'] ?? null),
                'SF' => self::scalarToInt($row['sf_depth'] ?? null),
                'PF' => self::scalarToInt($row['pf_depth'] ?? null),
                'C' => self::scalarToInt($row['c_depth'] ?? null),
            ],
            dcDepth: [
                'PG' => self::scalarToInt($row['dc_pg_depth'] ?? null),
                'SG' => self::scalarToInt($row['dc_sg_depth'] ?? null),
                'SF' => self::scalarToInt($row['dc_sf_depth'] ?? null),
                'PF' => self::scalarToInt($row['dc_pf_depth'] ?? null),
                'C' => self::scalarToInt($row['dc_c_depth'] ?? null),
            ],
            dcMinutes: self::scalarToInt($row['dc_minutes'] ?? null),
            effectiveCy: $effectiveCy,
            salaryByYear: $salaryByYear,
            production: $production,
            // Mirrors TradeValidator::canPlayerBeTraded(), as v2 toPlayer() does.
            tradeable: $cy !== 0 && $ordinal <= \League\JsbConstants::WAIVERS_ORDINAL && $yearsRemaining > 0,
            productionFouls: $fouls,
        );
    }
}
