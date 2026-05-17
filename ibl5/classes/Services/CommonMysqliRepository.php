<?php

declare(strict_types=1);

namespace Services;

use Services\Contracts\CommonMysqliRepositoryInterface;

/**
 * Composite delegate — backwards-compatibility shim during the split.
 * Callers will be retargeted to the narrow interfaces; once all are moved, this class is deleted.
 *
 * @phpstan-import-type UserRow from CommonMysqliRepositoryInterface
 * @phpstan-import-type TeamInfoRow from CommonMysqliRepositoryInterface
 * @phpstan-import-type PlayerRow from CommonMysqliRepositoryInterface
 */
class CommonMysqliRepository implements CommonMysqliRepositoryInterface
{
    private readonly TeamIdentityRepository $identity;
    private readonly PlayerLookupRepository $players;
    private readonly SalaryCapRepository $salary;

    public function __construct(\mysqli $db, ?\League\LeagueContext $leagueContext = null)
    {
        $this->identity = new TeamIdentityRepository($db, $leagueContext);
        $this->players = new PlayerLookupRepository($db, $leagueContext);
        $this->salary = new SalaryCapRepository($db, $leagueContext);
    }

    /** @return UserRow|null */
    public function getUserByUsername(string $username): ?array
    {
        return $this->identity->getUserByUsername($username);
    }

    public function getTeamnameFromUsername(?string $username): ?string
    {
        return $this->identity->getTeamnameFromUsername($username);
    }

    public function getUsernameFromTeamname(string $teamName): ?string
    {
        return $this->identity->getUsernameFromTeamname($teamName);
    }

    /** @return TeamInfoRow|null */
    public function getTeamByName(string $teamName): ?array
    {
        return $this->identity->getTeamByName($teamName);
    }

    public function getTidFromTeamname(string $teamName): ?int
    {
        return $this->identity->getTidFromTeamname($teamName);
    }

    public function getTeamnameFromTeamID(int $teamid): ?string
    {
        return $this->identity->getTeamnameFromTeamID($teamid);
    }

    public function getTeamDiscordID(string $teamName): ?int
    {
        return $this->identity->getTeamDiscordID($teamName);
    }

    /** @return list<TeamInfoRow> */
    public function getAllRealTeams(string $orderBy = 'team_name ASC'): array
    {
        return $this->identity->getAllRealTeams($orderBy);
    }

    /** @return PlayerRow|null */
    public function getPlayerByID(int $playerID): ?array
    {
        return $this->players->getPlayerByID($playerID);
    }

    public function getPlayerIDFromPlayerName(string $playerName): ?int
    {
        return $this->players->getPlayerIDFromPlayerName($playerName);
    }

    /** @return PlayerRow|null */
    public function getPlayerByName(string $playerName): ?array
    {
        return $this->players->getPlayerByName($playerName);
    }

    public function getTeamTotalSalary(string $teamName): int
    {
        return $this->salary->getTeamTotalSalary($teamName);
    }

    public function getTeamNextYearSalary(string $teamName): int
    {
        return $this->salary->getTeamNextYearSalary($teamName);
    }

    public function getPositionSalaryCommitmentNextYear(string $teamName, string $position, int $excludePlayerID): int
    {
        return $this->salary->getPositionSalaryCommitmentNextYear($teamName, $position, $excludePlayerID);
    }

    /** @return array{current: int, nextYear: int} */
    public function getTeamSalarySummary(string $teamName): array
    {
        return $this->salary->getTeamSalarySummary($teamName);
    }

    public function getTeamCapSpaceNextSeason(string $teamName): int
    {
        return $this->salary->getTeamCapSpaceNextSeason($teamName);
    }
}
