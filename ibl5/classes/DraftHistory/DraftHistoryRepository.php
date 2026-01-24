<?php

declare(strict_types=1);

namespace DraftHistory;

use DraftHistory\Contracts\DraftHistoryRepositoryInterface;

/**
 * DraftHistoryRepository - Data access layer for draft history
 *
 * Retrieves draft pick information from the ibl_plr table.
 *
 * @see DraftHistoryRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class DraftHistoryRepository extends \BaseMysqliRepository implements DraftHistoryRepositoryInterface
{
    /**
     * @see DraftHistoryRepositoryInterface::getFirstDraftYear()
     */
    public function getFirstDraftYear(): int
    {
        // IBL v5's first non-dispersal draft was in 1988
        return 1988;
    }

    /**
     * @see DraftHistoryRepositoryInterface::getLastDraftYear()
     */
    public function getLastDraftYear(): int
    {
        $result = $this->fetchOne(
            "SELECT draftyear FROM ibl_plr ORDER BY draftyear DESC LIMIT 1"
        );

        return (int) ($result['draftyear'] ?? 1988);
    }

    /**
     * @see DraftHistoryRepositoryInterface::getDraftPicksByYear()
     */
    public function getDraftPicksByYear(int $year): array
    {
        return $this->fetchAll(
            "SELECT pid, name, draftround, draftpickno, draftedby, college
            FROM ibl_plr
            WHERE draftyear = ? AND draftround > 0
            ORDER BY draftround ASC, draftpickno ASC",
            "i",
            $year
        );
    }
}
