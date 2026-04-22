<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * Repository interface for ibl_cash_considerations table.
 *
 * Handles cash consideration and buyout entries that were previously
 * stored as fake player rows in ibl_plr.
 *
 * @phpstan-type CashConsiderationRow = array{
 *     id: int,
 *     teamid: int,
 *     type: string,
 *     label: string,
 *     counterparty_teamid: int|null,
 *     trade_offer_id: int|null,
 *     cy: int,
 *     cyt: int,
 *     cy1: int,
 *     cy2: int,
 *     cy3: int,
 *     cy4: int,
 *     cy5: int,
 *     cy6: int,
 *     created_at: string,
 *     updated_at: string
 * }
 *
 * @phpstan-type CashConsiderationInsert = array{
 *     teamid: int,
 *     type: string,
 *     label: string,
 *     counterparty_teamid?: int|null,
 *     trade_offer_id?: int|null,
 *     cy: int,
 *     cyt: int,
 *     cy1: int,
 *     cy2: int,
 *     cy3: int,
 *     cy4: int,
 *     cy5: int,
 *     cy6: int
 * }
 */
interface CashConsiderationRepositoryInterface
{
    /**
     * Insert a new cash consideration or buyout entry.
     *
     * @param CashConsiderationInsert $data
     * @return int Number of affected rows (1 on success)
     */
    public function insertCashConsideration(array $data): int;

    /**
     * Get all cash considerations for a team (cash + buyout).
     *
     * @return list<CashConsiderationRow>
     */
    public function getTeamCashConsiderations(int $teamId): array;

    /**
     * Get only buyout entries for a team.
     *
     * @return list<CashConsiderationRow>
     */
    public function getTeamBuyouts(int $teamId): array;

    /**
     * Get cash/buyout records for salary cap calculations.
     * Returns the fields needed for the cy-based salary offset logic.
     *
     * @return list<array{cy: int, cy1: int, cy2: int, cy3: int, cy4: int, cy5: int, cy6: int}>
     */
    public function getTeamCashForSalary(int $teamId): array;

    /**
     * Delete expired cash/buyout entries where all contract years are exhausted.
     *
     * @return int Number of deleted rows
     */
    public function deleteExpiredCashConsiderations(): int;
}
