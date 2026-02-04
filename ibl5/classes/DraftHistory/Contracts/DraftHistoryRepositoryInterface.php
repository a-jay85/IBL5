<?php

declare(strict_types=1);

namespace DraftHistory\Contracts;

/**
 * Repository interface for Draft History module.
 *
 * Provides methods to retrieve draft information from the database.
 *
 * @phpstan-type DraftPickByYearRow array{pid: int, name: string, pos: string, draftround: int, draftpickno: int, draftedby: string, college: string, teamid: int|null, team_city: string|null, color1: string|null, color2: string|null}
 * @phpstan-type DraftPickByTeamRow array{pid: int, name: string, pos: string, draftround: int, draftpickno: int, draftyear: int, college: string, retired: string}
 */
interface DraftHistoryRepositoryInterface
{
    /**
     * Get the earliest draft year on record.
     *
     * @return int First draft year
     */
    public function getFirstDraftYear(): int;

    /**
     * Get the most recent draft year on record.
     *
     * @return int Last draft year
     */
    public function getLastDraftYear(): int;

    /**
     * Get all draft picks for a specific year.
     *
     * @param int $year Draft year
     * @return list<DraftPickByYearRow> Array of draft pick data
     */
    public function getDraftPicksByYear(int $year): array;

    /**
     * Get all draft picks for a specific team.
     *
     * @param string $teamName Team name (matches ibl_plr.draftedby)
     * @return list<DraftPickByTeamRow> Array of draft pick data ordered by year desc, round asc, pick asc
     */
    public function getDraftPicksByTeam(string $teamName): array;
}
