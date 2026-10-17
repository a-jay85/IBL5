<?php

declare(strict_types=1);

namespace TradeSuggestions;

use TradeSuggestions\Contracts\SuggestionAssetRepositoryInterface;

/**
 * @phpstan-import-type SuggestionPickRow from SuggestionAssetRepositoryInterface
 *
 * @see SuggestionAssetRepositoryInterface
 */
class SuggestionAssetRepository extends \Database\BaseMysqliRepository implements SuggestionAssetRepositoryInterface
{
    /**
     * @see SuggestionAssetRepositoryInterface::getRostersForTeams()
     *
     * @param list<int> $tids
     * @return array<int, list<SuggestionPlayer>>
     */
    public function getRostersForTeams(array $tids): array
    {
        $rows = $this->fetchAllInList(
            "SELECT pid, name, pos, age, teamid, talent, skill, intangibles, injured, cy, cyt,
                    salary_yr1, salary_yr2, salary_yr3, salary_yr4, salary_yr5, salary_yr6
             FROM `ibl_plr`
             WHERE retired = 0 AND ordinal <= 960 AND teamid IN ({IN})
             ORDER BY teamid, ordinal, pid",
            'i',
            $tids
        );

        $rosters = [];
        foreach ($rows as $row) {
            $rosters[self::scalarToInt($row['teamid'] ?? null)][] = self::toPlayer($row);
        }

        return $rosters;
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
     * @param array<string, mixed> $row
     */
    private static function toPlayer(array $row): SuggestionPlayer
    {
        $quality = self::rating($row['talent'] ?? null) * 2
            + self::rating($row['skill'] ?? null) * 2
            + self::rating($row['intangibles'] ?? null);

        $cy = self::scalarToInt($row['cy'] ?? null);
        $cyt = self::scalarToInt($row['cyt'] ?? null);

        $currentSalary = 0;
        $yearsRemaining = 0;
        if ($cy !== 0) {
            $currentSalary = self::salaryForYear($row, $cy);
            $yearsRemaining = max(0, $cyt - $cy + 1);
        }

        $futureSalary = 0;
        for ($year = $cy + 1; $year <= $cyt; $year++) {
            $futureSalary += self::salaryForYear($row, $year);
        }

        $injured = $row['injured'] ?? null;

        return new SuggestionPlayer(
            pid: self::scalarToInt($row['pid'] ?? null),
            name: self::scalarToString($row['name'] ?? null),
            pos: self::scalarToString($row['pos'] ?? null),
            age: self::scalarToInt($row['age'] ?? null),
            quality: $quality,
            injured: $injured !== null && self::scalarToInt($injured) > 0,
            currentSalary: $currentSalary,
            yearsRemaining: $yearsRemaining,
            futureSalary: $futureSalary,
        );
    }

    /**
     * A NULL rating counts as 1.
     */
    private static function rating(mixed $value): int
    {
        return $value === null ? 1 : self::scalarToInt($value);
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
