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
            "SELECT p.pid, p.name, p.pos, p.draftround, p.draftpickno, p.draftedby, p.college,
                    t.teamid, t.team_city, t.color1, t.color2
            FROM ibl_plr p
            LEFT JOIN ibl_team_info t ON p.draftedby = t.team_name
            WHERE p.draftyear = ? AND p.draftround > 0
            ORDER BY p.draftround ASC, p.draftpickno ASC",
            "i",
            $year
        );
    }

    /**
     * @see DraftHistoryRepositoryInterface::getDraftPicksByTeam()
     */
    public function getDraftPicksByTeam(string $teamName): array
    {
        return $this->fetchAll(
            "SELECT p.pid, p.name, p.pos, p.draftround, p.draftpickno, p.draftyear, p.college, p.retired
            FROM ibl_plr p
            WHERE p.draftedby = ? AND p.draftround > 0
            ORDER BY p.draftyear DESC, p.draftround ASC, p.draftpickno ASC",
            "s",
            $teamName
        );
    }
}
