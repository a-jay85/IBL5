<?php

declare(strict_types=1);

namespace SimRecap;

/**
 * Team display names and Discord mentions, read once from ibl_team_info.
 *
 * Snowflakes are carried as STRINGS end to end. `discord_id` is bigint(20) unsigned
 * and jq <= 1.6 corrupts such values through IEEE-754 doubles, so nothing here ever
 * lets one become an int or a float.
 */
final class MentionMap
{
    /** @param list<array<string, mixed>> $teamRows from getAllRealTeams('teamid ASC') */
    private function __construct(private readonly array $teamRows) {}

    public static function fromDatabase(\mysqli $db): self
    {
        $teams = new \Repositories\TeamIdentityRepository($db); // @phpstan-ignore ibl.directRepoInstantiation (fromDatabase is the designated factory for this class — injection is not applicable here)
        return new self($teams->getAllRealTeams('teamid ASC'));
    }

    /**
     * @return array<string, string> nickname => Discord snowflake as string — the existing mention-map verb shape
     */
    public function byTeamName(): array
    {
        $map = [];
        foreach ($this->teamRows as $row) {
            if (!is_scalar($row['discord_id'])) {
                continue;
            }
            $id = (string) $row['discord_id'];
            if (!is_string($row['team_name'])) {
                continue;
            }
            $map[$row['team_name']] = $id;
        }
        return $map;
    }

    /**
     * @return array<int, string> teamid => Discord snowflake as string, teams without a discord_id omitted
     */
    public function byTeamId(): array
    {
        $map = [];
        foreach ($this->teamRows as $row) {
            if (!is_scalar($row['discord_id'])) {
                continue;
            }
            $id = (string) $row['discord_id'];
            if (!is_numeric($row['teamid'])) {
                continue;
            }
            $map[(int) $row['teamid']] = $id;
        }
        return $map;
    }

    /**
     * @return array<int, string> teamid => "City Nickname" (e.g. "New York Metros")
     */
    public function displayNamesByTeamId(): array
    {
        $map = [];
        foreach ($this->teamRows as $row) {
            if (!is_numeric($row['teamid'])) {
                continue;
            }
            $city = is_string($row['team_city']) ? $row['team_city'] : '';
            $name = is_string($row['team_name']) ? $row['team_name'] : '';
            $map[(int) $row['teamid']] = trim($city . ' ' . $name);
        }
        return $map;
    }
}
