<?php

declare(strict_types=1);

namespace DraftHistory\Contracts;

/**
 * Repository interface for Draft History module.
 *
 * Provides methods to retrieve draft information from the database.
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
     * @return array<int, array{
     *     pid: int,
     *     name: string,
     *     draftround: int,
     *     draftpickno: int,
     *     draftedby: string,
     *     college: string
     * }> Array of draft pick data
     */
    public function getDraftPicksByYear(int $year): array;
}
