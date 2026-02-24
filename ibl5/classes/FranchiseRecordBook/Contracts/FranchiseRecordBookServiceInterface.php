<?php

declare(strict_types=1);

namespace FranchiseRecordBook\Contracts;

/**
 * Interface for franchise record book business logic.
 *
 * @phpstan-import-type AlltimeRecord from FranchiseRecordBookRepositoryInterface
 * @phpstan-import-type TeamInfo from FranchiseRecordBookRepositoryInterface
 *
 * @phpstan-type RecordsByCategory array<string, list<AlltimeRecord>>
 * @phpstan-type RecordBookData array{singleSeason: RecordsByCategory, career: RecordsByCategory, team: TeamInfo|null, teams: list<TeamInfo>, scope: string}
 */
interface FranchiseRecordBookServiceInterface
{
    /**
     * Get record book data for a specific team.
     *
     * @param int $teamId JSB team ID (1-28)
     * @return RecordBookData
     */
    public function getTeamRecordBook(int $teamId): array;

    /**
     * Get league-wide record book data.
     *
     * @return RecordBookData
     */
    public function getLeagueRecordBook(): array;
}
