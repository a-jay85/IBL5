<?php

declare(strict_types=1);

namespace TradeSuggestions;

use TradeSuggestions\Contracts\SuggestionAssetRepositoryInterface;

/**
 * @phpstan-import-type SuggestionPickRow from SuggestionAssetRepositoryInterface
 * @phpstan-import-type MarketMaximums from PlayerRating
 *
 * @see SuggestionAssetRepositoryInterface
 */
class SuggestionAssetRepository extends \Database\BaseMysqliRepository implements SuggestionAssetRepositoryInterface
{
    /**
     * @see SuggestionAssetRepositoryInterface::getRostersForTeams()
     *
     * @param list<int> $tids
     * @param MarketMaximums $maximums
     * @return array<int, list<SuggestionPlayer>>
     */
    public function getRostersForTeams(array $tids, array $maximums, bool $advancesContractYears, int $lastSeasonYear): array
    {
        $rows = $this->fetchAllInList(
            "SELECT pid, name, pos, age, teamid, injured, cy, cyt, ordinal, exp,
                    dc_pg_depth, dc_sg_depth, dc_sf_depth, dc_pf_depth, dc_c_depth, dc_minutes,
                    stats_gm, stats_min, stats_fgm, stats_fga, stats_ftm, stats_fta, stats_3gm, stats_3ga,
                    stats_orb, stats_drb, stats_ast, stats_stl, stats_blk, stats_tvr,
                    salary_yr1, salary_yr2, salary_yr3, salary_yr4, salary_yr5, salary_yr6,
                    " . implode(', ', PlayerRating::COLUMNS) . "
             FROM `ibl_plr`
             WHERE retired = 0 AND ordinal <= 960 AND teamid IN ({IN})
             ORDER BY teamid, ordinal, pid",
            'i',
            $tids
        );

        $histByPid = $this->getLastSeasonLines(
            array_map(static fn (array $row): int => self::scalarToInt($row['pid'] ?? null), $rows),
            $lastSeasonYear
        );

        $rosters = [];
        foreach ($rows as $row) {
            $hist = $histByPid[self::scalarToInt($row['pid'] ?? null)] ?? null;
            $rosters[self::scalarToInt($row['teamid'] ?? null)][] = self::toPlayer($row, $maximums, $advancesContractYears, $hist);
        }

        return $rosters;
    }

    /**
     * One ibl_hist row per pid at $year ((pid, year) is the primary key).
     *
     * @param list<int> $pids
     * @return array<int, array<string, mixed>> Keyed by pid
     */
    private function getLastSeasonLines(array $pids, int $year): array
    {
        if ($pids === []) {
            return [];
        }

        $rows = $this->fetchAllInList(
            "SELECT pid, games, minutes, fgm, fga, ftm, fta, tgm, tga, reb, ast, stl, blk, tvr, pts
             FROM `ibl_hist` WHERE year = ? AND pid IN ({IN})",
            'i',
            $pids,
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
     * @see SuggestionAssetRepositoryInterface::getPicksForTeams()
     *
     * @param list<int> $tids
     * @return array<int, list<SuggestionPickRow>>
     */
    public function getPicksForTeams(array $tids, int $fromYear): array
    {
        $rows = $this->fetchAllInList(
            // Sorted in PHP below (year, round, pickid) per team.
            "SELECT pickid, owner_teamid, teampick_teamid, year, round
             FROM `ibl_draft_picks`
             WHERE year >= ? AND round <= 2 AND owner_teamid IN ({IN})",
            'i',
            $tids,
            'i',
            $fromYear
        );

        $picks = [];
        foreach ($rows as $row) {
            $owner = self::scalarToInt($row['owner_teamid'] ?? null);
            $picks[$owner][] = [
                'pickid' => self::scalarToInt($row['pickid'] ?? null),
                'owner_teamid' => $owner,
                'teampick_teamid' => self::scalarToInt($row['teampick_teamid'] ?? null),
                'year' => self::scalarToInt($row['year'] ?? null),
                'round' => self::scalarToInt($row['round'] ?? null),
            ];
        }

        ksort($picks);
        foreach ($picks as &$teamPicks) {
            usort($teamPicks, static fn (array $a, array $b): int
                => [$a['year'], $a['round'], $a['pickid']] <=> [$b['year'], $b['round'], $b['pickid']]);
        }
        unset($teamPicks);

        return $picks;
    }

    /**
     * @see SuggestionAssetRepositoryInterface::getRegularSeasonRecords()
     *
     * @return array<int, array{wins: int, losses: int}>
     */
    public function getRegularSeasonRecords(int $year): array
    {
        $rows = $this->fetchAll(
            "SELECT team_id, wins, losses FROM `ibl_team_season_records`
             WHERE year = ? AND game_type = 1",
            'i',
            $year
        );

        $records = [];
        foreach ($rows as $row) {
            $records[self::scalarToInt($row['team_id'] ?? null)] = [
                'wins' => self::scalarToInt($row['wins'] ?? null),
                'losses' => self::scalarToInt($row['losses'] ?? null),
            ];
        }

        return $records;
    }

    /**
     * The effective contract year is cy, or cy + 1 during the offseason phases
     * (the same shift TradeFormContract applies). A player with no salary in
     * that year has no years left and cannot be dealt.
     *
     * @param array<string, mixed> $row
     * @param MarketMaximums $maximums
     * @param array<string, mixed>|null $histRow
     */
    private static function toPlayer(array $row, array $maximums, bool $advancesContractYears, ?array $histRow): SuggestionPlayer
    {
        $cy = self::scalarToInt($row['cy'] ?? null);
        $cyt = self::scalarToInt($row['cyt'] ?? null);
        $year = $advancesContractYears ? $cy + 1 : $cy;

        $currentSalary = 0;
        $yearsRemaining = 0;
        $futureSalary = 0;
        if ($cy !== 0) {
            $currentSalary = self::salaryForYear($row, $year);
            if ($currentSalary > 0) {
                $yearsRemaining = max(1, $cyt - $year + 1);
            }
            for ($later = $year + 1; $later <= $cyt; $later++) {
                $futureSalary += self::salaryForYear($row, $later);
            }
        }

        $ordinal = self::scalarToInt($row['ordinal'] ?? null);

        $injured = $row['injured'] ?? null;

        return new SuggestionPlayer(
            pid: self::scalarToInt($row['pid'] ?? null),
            name: self::scalarToString($row['name'] ?? null),
            pos: self::scalarToString($row['pos'] ?? null),
            age: self::scalarToInt($row['age'] ?? null),
            ovr: PlayerRating::overall($row, $maximums),
            depth: [
                'PG' => self::scalarToInt($row['dc_pg_depth'] ?? null),
                'SG' => self::scalarToInt($row['dc_sg_depth'] ?? null),
                'SF' => self::scalarToInt($row['dc_sf_depth'] ?? null),
                'PF' => self::scalarToInt($row['dc_pf_depth'] ?? null),
                'C' => self::scalarToInt($row['dc_c_depth'] ?? null),
            ],
            dcMinutes: self::scalarToInt($row['dc_minutes'] ?? null),
            exp: self::scalarToInt($row['exp'] ?? null),
            production: ProductionLine::choose(
                ProductionLine::fromCurrentRow($row),
                $histRow === null ? null : ProductionLine::fromHistRow($histRow)
            ),
            injured: $injured !== null && self::scalarToInt($injured) > 0,
            currentSalary: $currentSalary,
            yearsRemaining: $yearsRemaining,
            futureSalary: $futureSalary,
            // Mirrors TradeValidator::canPlayerBeTraded(); the validator still decides.
            tradeable: $cy !== 0 && $ordinal <= \League\JsbConstants::WAIVERS_ORDINAL && $yearsRemaining > 0,
            // Mirrors TradeFormRepository::getTeamPlayerCount(): in the offseason a
            // player counts only if next contract year's salary is nonzero.
            countsOnRoster: !$advancesContractYears || self::salaryForYear($row, $cy + 1) !== 0,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function salaryForYear(array $row, int $year): int
    {
        if ($year < 1 || $year > 6) {
            return 0;
        }

        return self::scalarToInt($row['salary_yr' . $year] ?? null);
    }
}
