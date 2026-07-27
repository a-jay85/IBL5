<?php

declare(strict_types=1);

namespace SeasonArchive;

use SeasonArchive\Contracts\SeasonArchiveRepositoryInterface;

/**
 * Extracts award winners, GM/coach honors, and parsed team awards from raw archive rows.
 *
 * Stateless value transformer: every method takes its data as parameters, holds no state.
 *
 * @phpstan-import-type AwardRow from SeasonArchiveRepositoryInterface
 * @phpstan-import-type GmAwardWithTeamRow from SeasonArchiveRepositoryInterface
 * @phpstan-import-type GmTenureWithTeamRow from SeasonArchiveRepositoryInterface
 * @phpstan-import-type TeamAwardRow from SeasonArchiveRepositoryInterface
 */
final class SeasonAwardExtractor
{
    /**
     * Extract a single award winner name by exact award name match
     *
     * Uses trim() to handle trailing whitespace in award names (known data issue).
     *
     * @param list<AwardRow> $awards All awards for a year
     * @param string $awardName Exact award name to match
     * @param array<string, true> $collected Player-name accumulator (by reference)
     * @return string Winner name, or empty string if not found
     */
    public function extractAward(array $awards, string $awardName, array &$collected = []): string
    {
        foreach ($awards as $award) {
            if (trim($award['award']) === $awardName) {
                $name = trim($award['name']);
                if ($name !== '') {
                    $collected[$name] = true;
                }

                return $name;
            }
        }

        return '';
    }

    /**
     * Extract all player names for a given award name
     *
     * Uses trim() to handle trailing whitespace in award names (known data issue).
     *
     * @param list<AwardRow> $awards All awards for a year
     * @param string $awardName Award name to match (exact match after trim)
     * @param array<string, true> $collected Player-name accumulator (by reference)
     * @return list<string> List of player names
     */
    public function extractAwardList(array $awards, string $awardName, array &$collected = []): array
    {
        $names = [];
        foreach ($awards as $award) {
            if (trim($award['award']) === $awardName) {
                $name = trim($award['name']);
                $names[] = $name;
                if ($name !== '') {
                    $collected[$name] = true;
                }
            }
        }

        return $names;
    }

    /**
     * Find GM of the Year from normalized GM awards data
     *
     * @param list<GmAwardWithTeamRow> $gmAwards All GM award records with team names
     * @param int $year Season ending year to find
     * @return array{name: string, team: string} GM name and team, or empty strings if not found
     */
    public function getGmOfTheYear(array $gmAwards, int $year): array
    {
        foreach ($gmAwards as $award) {
            if ($award['award'] === 'GM of the Year' && $award['year'] === $year) {
                return ['name' => $award['gm_display_name'], 'team' => $award['team_name']];
            }
        }

        return ['name' => '', 'team' => ''];
    }

    /**
     * Get All-Star Game head coaches for a given year, split by conference
     *
     * Matches 'ASG Head Coach' and 'ASG Co-Head Coach' awards for the given year.
     * Uses the team_name from the JOIN to determine conference.
     *
     * @param list<GmAwardWithTeamRow> $gmAwards All GM award records with team names
     * @param int $year Season ending year
     * @param array<string, string> $teamConferences Map of team_name => 'Eastern'|'Western'
     * @return array{east: list<string>, west: list<string>}
     */
    public function getAllStarCoaches(array $gmAwards, int $year, array $teamConferences): array
    {
        /** @var list<string> $east */
        $east = [];
        /** @var list<string> $west */
        $west = [];

        foreach ($gmAwards as $award) {
            if ($award['year'] !== $year) {
                continue;
            }

            if ($award['award'] !== 'ASG Head Coach' && $award['award'] !== 'ASG Co-Head Coach') {
                continue;
            }

            $conference = $teamConferences[$award['team_name']] ?? '';

            if ($conference === 'Eastern') {
                $east[] = $award['gm_display_name'];
            } elseif ($conference === 'Western') {
                $west[] = $award['gm_display_name'];
            }
        }

        return ['east' => $east, 'west' => $west];
    }

    /**
     * Get the head coach (GM) of the IBL champion team for a given year
     *
     * Finds the GM whose team_name matches the champion and whose tenure covers the year.
     *
     * @param list<GmTenureWithTeamRow> $gmTenures All GM tenure records with team names
     * @param string $championTeam IBL Finals winner team name
     * @param int $year Season ending year
     * @return string GM username, or empty string if not found
     */
    public function getIblChampionCoach(array $gmTenures, string $championTeam, int $year): string
    {
        if ($championTeam === '') {
            return '';
        }

        foreach ($gmTenures as $tenure) {
            if ($tenure['team_name'] !== $championTeam) {
                continue;
            }

            if ($year < $tenure['start_season_year']) {
                continue;
            }

            if ($tenure['end_season_year'] !== null && $year > $tenure['end_season_year']) {
                continue;
            }

            return $tenure['gm_display_name'];
        }

        return '';
    }

    /**
     * Parse team awards from raw HTML data
     *
     * The ibl_team_awards table has HTML-contaminated data:
     * - award field: "<B>Atlantic Division Champions</b>"
     * - Multiple awards may be concatenated with <BR>
     *
     * @param list<TeamAwardRow> $teamAwardRows Raw team award rows
     * @return array<string, string> Map of award label => team name
     */
    public function parseTeamAwards(array $teamAwardRows): array
    {
        $awards = [];

        foreach ($teamAwardRows as $row) {
            $rawAward = $row['award'];
            $teamName = $row['name'];

            // Strip HTML tags and split by common delimiters
            $cleanAward = strip_tags($rawAward);
            $parts = preg_split('/\s*(?:\r?\n)+\s*/', trim($cleanAward));

            if (!is_array($parts)) {
                $parts = [trim($cleanAward)];
            }

            foreach ($parts as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $awards[$part] = $teamName;
                }
            }
        }

        return $awards;
    }

    /**
     * Get HEAT champion from team awards
     *
     * @param list<TeamAwardRow> $teamAwards Team awards for the year
     * @return string HEAT champion team name, or empty string
     */
    public function getHeatChampionFromTeamAwards(array $teamAwards): string
    {
        foreach ($teamAwards as $row) {
            $cleanAward = strip_tags($row['award']);
            if (stripos($cleanAward, 'HEAT Champion') !== false) {
                return $row['name'];
            }
        }

        return '';
    }
}
