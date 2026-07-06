<?php

declare(strict_types=1);

namespace Repositories;

use League\League;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

/**
 * @phpstan-import-type UserRow from TeamIdentityRepositoryInterface
 * @phpstan-import-type TeamInfoRow from TeamIdentityRepositoryInterface
 */
class TeamIdentityRepository extends \BaseMysqliRepository implements TeamIdentityRepositoryInterface
{
    protected function rewriteTableNames(string $query): string
    {
        // GM assignments (gm_username) and auth data live in IBL-scoped tables only.
        // Never route identity lookups to Olympics tables.
        return $query;
    }

    /**
     * @return UserRow|null
     */
    public function getUserByUsername(string $username): ?array
    {
        /** @var UserRow|null */
        return $this->fetchOne(
            "SELECT id AS user_id, username, email AS user_email FROM auth_users WHERE username = ?",
            "s",
            $username
        );
    }

    public function getTeamnameFromUsername(?string $username): ?string
    {
        if ($username === null || $username === '') {
            return League::FREE_AGENTS_TEAM_NAME;
        }

        $override = \Utilities\TestCookieOverrides::getTeamOverride();
        if ($override !== null) {
            return $override;
        }

        /** @var array{team_name: string}|null $result */
        $result = $this->fetchOne(
            "SELECT team_name FROM `ibl_team_info` WHERE gm_username = ? LIMIT 1",
            "s",
            $username
        );

        return $result !== null ? $result['team_name'] : null;
    }

    public function getUsernameFromTeamname(string $teamName): ?string
    {
        /** @var array{gm_username: ?string}|null $result */
        $result = $this->fetchOne(
            "SELECT gm_username FROM `ibl_team_info` WHERE team_name = ? LIMIT 1",
            "s",
            $teamName
        );

        return $result !== null ? $result['gm_username'] : null;
    }

    /**
     * @return TeamInfoRow|null
     */
    public function getTeamByName(string $teamName): ?array
    {
        /** @var TeamInfoRow|null */
        return $this->fetchOne(
            "SELECT * FROM `ibl_team_info` WHERE team_name = ?",
            "s",
            $teamName
        );
    }

    public function getTidFromTeamname(string $teamName): ?int
    {
        /** @var array{teamid: int}|null $result */
        $result = $this->fetchOne(
            "SELECT teamid FROM `ibl_team_info` WHERE team_name = ? LIMIT 1",
            "s",
            $teamName
        );

        return $result !== null ? $result['teamid'] : null;
    }

    public function getTeamnameFromTeamID(int $teamid): ?string
    {
        /** @var array{team_name: string}|null $result */
        $result = $this->fetchOne(
            "SELECT team_name FROM `ibl_team_info` WHERE teamid = ? LIMIT 1",
            "i",
            $teamid
        );

        return $result !== null ? $result['team_name'] : null;
    }

    public function getTeamDiscordID(string $teamName): ?int
    {
        /** @var array{discord_id: int|string|null}|null $result */
        $result = $this->fetchOne(
            "SELECT `discord_id` FROM `ibl_team_info` WHERE `team_name` = ? LIMIT 1",
            "s",
            $teamName
        );

        if ($result === null) {
            return null;
        }

        $discordId = $result['discord_id'] ?? null;
        return $discordId !== null ? (int) $discordId : null;
    }

    public function isKnownDiscordID(string $discordId): bool
    {
        // Bind the snowflake as "s"; MySQL coerces into the BIGINT UNSIGNED column.
        // Existence test only — never (int)-cast the snowflake (contrast getTeamDiscordID).
        return $this->fetchOne(
            "SELECT 1 FROM `ibl_team_info` WHERE `discord_id` = ? LIMIT 1",
            "s",
            $discordId
        ) !== null;
    }

    /**
     * @return list<TeamInfoRow>
     */
    public function getAllRealTeams(string $orderBy = 'team_name ASC'): array
    {
        $teamOrderBy = \TeamOrderBy::tryFrom($orderBy);
        if ($teamOrderBy === null) {
            throw new \InvalidArgumentException(
                "Invalid orderBy '{$orderBy}'. Allowed: 'team_name ASC', 'teamid ASC', 'team_city ASC'."
            );
        }
        /** @var list<TeamInfoRow> */
        return $this->fetchAllRealTeams($teamOrderBy);
    }
}
