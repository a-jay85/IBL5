<?php

declare(strict_types=1);

namespace TradeSuggestions\Profile;

/**
 * Ranks every team on every profile dimension. These two methods are the
 * only rank producers.
 */
final class LeagueProfiles
{
    /**
     * Each dimension sorts high to low (low to high when lower is better)
     * and assigns ordinals 1..N, ties broken by tid ascending. The offense
     * and defense means of the four ODPT keys each are ranked the same way.
     *
     * @param array<int, array<string, float>> $valuesByTid
     * @return array<int, TeamProfile>
     */
    public static function build(array $valuesByTid, int $offset = 0): array
    {
        $rankKeys = [...ProfileDimension::KEYS, ProfileDimension::OFFENSE_MEAN, ProfileDimension::DEFENSE_MEAN];
        $withMeans = [];
        foreach ($valuesByTid as $tid => $values) {
            $values[ProfileDimension::OFFENSE_MEAN] = self::mean($values, ProfileDimension::OFFENSE);
            $values[ProfileDimension::DEFENSE_MEAN] = self::mean($values, ProfileDimension::DEFENSE);
            $withMeans[$tid] = $values;
        }

        $ranks = [];
        foreach ($rankKeys as $key) {
            $tids = array_keys($withMeans);
            $ascending = ProfileDimension::lowerIsBetter($key);
            usort($tids, static function (int $a, int $b) use ($withMeans, $key, $ascending): int {
                $va = $withMeans[$a][$key] ?? 0.0;
                $vb = $withMeans[$b][$key] ?? 0.0;
                $byValue = $ascending ? $va <=> $vb : $vb <=> $va;

                return $byValue !== 0 ? $byValue : $a <=> $b;
            });
            foreach ($tids as $index => $tid) {
                $ranks[$tid][$key] = $index + 1;
            }
        }

        $profiles = [];
        foreach ($valuesByTid as $tid => $values) {
            $profiles[$tid] = new TeamProfile($tid, $offset, $values, $ranks[$tid] ?? []);
        }

        return $profiles;
    }

    /**
     * Re-ranks the league after two teams' values change (F14 seam). This
     * re-sorts every dimension; a faster version can replace it later.
     *
     * @param array<int, TeamProfile> $profiles
     * @param array<string, float> $valuesA
     * @param array<string, float> $valuesB
     * @return array<int, TeamProfile>
     */
    public static function reRank(array $profiles, int $tidA, array $valuesA, int $tidB, array $valuesB): array
    {
        $valuesByTid = [];
        $offset = 0;
        foreach ($profiles as $tid => $profile) {
            $valuesByTid[$tid] = $profile->values;
            $offset = $profile->offset;
        }
        $valuesByTid[$tidA] = $valuesA;
        $valuesByTid[$tidB] = $valuesB;

        return self::build($valuesByTid, $offset);
    }

    /**
     * @param array<string, float> $values
     * @param list<string> $keys
     */
    private static function mean(array $values, array $keys): float
    {
        $sum = 0.0;
        foreach ($keys as $key) {
            $sum += $values[$key] ?? 0.0;
        }

        return $sum / count($keys);
    }
}
