<?php

declare(strict_types=1);

namespace Repositories\Contracts;

/**
 * @phpstan-type UserRow array{user_id: int, username: string, user_email: string}
 * @phpstan-type TeamInfoRow array{teamid: int, team_city: string, team_name: string, color1: string, color2: string, arena: string, owner_name: string, owner_email: string, gm_username: ?string, discord_id: ?int, used_extension_this_chunk: int, used_extension_this_season: ?int, has_mle: int, has_lle: int, ...}
 */
interface TeamIdentityRepositoryInterface
{
    /**
     * @return UserRow|null
     */
    public function getUserByUsername(string $username): ?array;

    public function getTeamnameFromUsername(?string $username): ?string;

    public function getUsernameFromTeamname(string $teamName): ?string;

    /**
     * @return TeamInfoRow|null
     */
    public function getTeamByName(string $teamName): ?array;

    public function getTidFromTeamname(string $teamName): ?int;

    public function getTeamnameFromTeamID(int $teamid): ?string;

    public function getTeamDiscordID(string $teamName): ?int;

    /**
     * @return list<TeamInfoRow>
     */
    public function getAllRealTeams(string $orderBy = 'team_name ASC'): array;
}
