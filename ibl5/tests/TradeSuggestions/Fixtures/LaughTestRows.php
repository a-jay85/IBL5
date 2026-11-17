<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Fixtures;

use TradeSuggestions\ProductionLine;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\Projection\RatingDirection;
use TradeSuggestions\Projection\SlotRelevance;
use TradeSuggestions\SuggestionPlayer;

/**
 * One synthetic deal that a GM would laugh at: a win-now team sends Dupree
 * (25, SF, better at every SF rating, 350 a year for four years) plus two
 * second-round picks for Laettner (34, F, 420 expiring). v2 accepts it; v3
 * must veto it by id.
 *
 * Each player is one spec array. The v2 test feeds it through
 * RosterFactory, the v3 tests through ProjectionFactory, so both brains
 * judge the same player.
 *
 * Laettner scores 600 points in 1000 minutes and Dupree 400. v2 ranks
 * starters by points per 36 alone, so it sees Laettner as the better
 * starter and accepts the deal. The first cut gave Dupree the higher
 * scoring, and v2 rejected it as ImportantWorsened (Laettner did not cover
 * Dupree's slot). v3 reads ratings, age and contract, so it sees the deal
 * for what it is.
 *
 * @phpstan-type PlayerSpec array{pid: int, name: string, pos: string, age: int, cy: int, salaries: list<int>, ratings: array<string, int>, injured: int, statsPts: int, minutes: int}
 * @phpstan-type PickSpec array{pickid: int, owner_teamid: int, teampick_teamid: int, year: int, round: int}
 */
final class LaughTestRows
{
    public const int DUPREE_PID = 900001;
    public const int LAETTNER_PID = 900002;
    public const int USER_TID = 1;
    public const int PARTNER_TID = 2;
    public const int PICK_YEAR = 2027;

    /** Points the SF-relevant ratings sit above (or below, for lower-is-better keys) Laettner's. */
    public const int RATING_EDGE = 10;

    private const int BASE_RATING = 50;

    /**
     * @return PlayerSpec
     */
    public static function dupree(): array
    {
        return [
            'pid' => self::DUPREE_PID,
            'name' => 'Dupree',
            'pos' => 'SF',
            'age' => 25,
            'cy' => 1,
            'salaries' => [350, 350, 350, 350, 0, 0],
            'ratings' => self::sfRatings(self::RATING_EDGE),
            'injured' => 0,
            'statsPts' => 400,
            'minutes' => 1000,
        ];
    }

    /**
     * @return PlayerSpec
     */
    public static function laettner(): array
    {
        return [
            'pid' => self::LAETTNER_PID,
            'name' => 'Laettner',
            'pos' => 'F',
            'age' => 34,
            'cy' => 1,
            'salaries' => [420, 0, 0, 0, 0, 0],
            'ratings' => self::sfRatings(0),
            'injured' => 0,
            'statsPts' => 600,
            'minutes' => 1000,
        ];
    }

    /**
     * @return PlayerSpec
     */
    public static function filler(int $pid, string $pos, int $age): array
    {
        return [
            'pid' => $pid,
            'name' => 'Filler ' . $pid,
            'pos' => $pos,
            'age' => $age,
            'cy' => 1,
            'salaries' => [200, 200, 0, 0, 0, 0],
            'ratings' => [],
            'injured' => 0,
            'statsPts' => 300,
            'minutes' => 1000,
        ];
    }

    /**
     * Fourteen fillers per team: one starter at every slot but the star's,
     * then bench players. Pids are 9000xx for team 1 and 9001xx for team 2.
     *
     * @return list<PlayerSpec>
     */
    public static function fillers(int $tid): array
    {
        $base = $tid === self::USER_TID ? 900010 : 900110;
        $positions = ['PG', 'SG', 'SF', 'PF', 'C', 'PG', 'SG', 'SF', 'PF', 'C', 'G', 'F', 'GF', 'C'];
        $specs = [];
        foreach ($positions as $i => $pos) {
            $specs[] = self::filler($base + $i, $pos, 24 + $i % 8);
        }

        return $specs;
    }

    /**
     * The two round-2 picks team 1 owns, shaped like `ibl_draft_picks` rows.
     *
     * @return list<PickSpec>
     */
    public static function picks(): array
    {
        return [
            ['pickid' => 910001, 'owner_teamid' => self::USER_TID, 'teampick_teamid' => self::USER_TID, 'year' => self::PICK_YEAR, 'round' => 2],
            ['pickid' => 910002, 'owner_teamid' => self::USER_TID, 'teampick_teamid' => self::USER_TID, 'year' => self::PICK_YEAR + 1, 'round' => 2],
        ];
    }

    /**
     * @param PlayerSpec $spec
     * @param array<string, int> $depth
     */
    public static function toRosterPlayer(array $spec, array $depth = [], int $dcMinutes = 0): SuggestionPlayer
    {
        $later = array_values(array_filter(
            array_slice($spec['salaries'], $spec['cy']),
            static fn (int $s): bool => $s > 0,
        ));

        return RosterFactory::player(
            $spec['pid'],
            $spec['pos'],
            age: $spec['age'],
            salary: $spec['salaries'][$spec['cy'] - 1],
            years: 1 + count($later),
            dcMinutes: $dcMinutes,
            depth: $depth,
            points: self::pointsPer720($spec),
            laterSalaries: $later,
            name: $spec['name'],
        );
    }

    /**
     * @param PlayerSpec $spec
     * @param array<string, int> $dcDepth
     */
    public static function toProjectionPlayer(array $spec, int $tid, array $dcDepth = [], int $dcMinutes = 0): ProjectionPlayer
    {
        $salaryByYear = [];
        foreach ($spec['salaries'] as $i => $salary) {
            $salaryByYear[$i + 1] = $salary;
        }
        $veteran = $spec['age'] >= 30;

        return ProjectionFactory::player(
            pid: $spec['pid'],
            tid: $tid,
            pos: $spec['pos'],
            age: $spec['age'],
            effectiveCy: $spec['cy'],
            salaryByYear: $salaryByYear,
            ratings: ProjectionFactory::ratingSet($spec['ratings']),
            production: self::productionLine($spec),
            injuredDays: $spec['injured'],
            dcDepth: $dcDepth,
            dcMinutes: $dcMinutes,
            name: $spec['name'],
            exp: $veteran ? 12 : 4,
            draftRound: $veteran ? 2 : 1,
        );
    }

    /**
     * The spec as `ibl_plr` columns, for staged-row tests. Points become
     * field goals because `ibl_plr` has no points column.
     *
     * @param PlayerSpec $spec
     * @return array<string, int|string>
     */
    public static function asPlrRow(array $spec, int $tid): array
    {
        $row = [
            'pid' => $spec['pid'],
            'name' => $spec['name'],
            'pos' => $spec['pos'],
            'teamid' => $tid,
            'age' => $spec['age'],
            'cy' => $spec['cy'],
            'injured' => $spec['injured'],
            'stats_min' => $spec['minutes'],
            'stats_fgm' => intdiv($spec['statsPts'], 2),
            'stats_ftm' => $spec['statsPts'] % 2,
        ];
        foreach ($spec['salaries'] as $i => $salary) {
            $row['salary_yr' . ($i + 1)] = $salary;
        }

        return $row + $spec['ratings'];
    }

    /**
     * @param PlayerSpec $spec
     */
    private static function productionLine(array $spec): ProductionLine
    {
        return ProjectionFactory::productionLine([
            'stats_min' => $spec['minutes'],
            'stats_fgm' => intdiv($spec['statsPts'], 2),
            'stats_ftm' => $spec['statsPts'] % 2,
            'stats_3gm' => 0,
        ]);
    }

    /**
     * RosterFactory lines cover 720 minutes, so scale the spec's points.
     *
     * @param PlayerSpec $spec
     */
    private static function pointsPer720(array $spec): int
    {
        return (int) round($spec['statsPts'] * 720 / $spec['minutes']);
    }

    /**
     * @return array<string, int>
     */
    private static function sfRatings(int $edge): array
    {
        $ratings = [];
        foreach (SlotRelevance::keysFor('SF') as $key) {
            $ratings[$key] = RatingDirection::higherIsBetter($key) ? self::BASE_RATING + $edge : self::BASE_RATING - $edge;
        }

        return $ratings;
    }
}
