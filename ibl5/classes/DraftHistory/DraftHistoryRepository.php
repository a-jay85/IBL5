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
 * @see \Database\BaseMysqliRepository For base class documentation
 *
 * @phpstan-import-type DraftPickByYearRow from DraftHistoryRepositoryInterface
 * @phpstan-import-type DraftPickByTeamRow from DraftHistoryRepositoryInterface
 */
class DraftHistoryRepository extends \Database\BaseMysqliRepository implements DraftHistoryRepositoryInterface
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
            "SELECT draftyear FROM `ibl_plr` ORDER BY draftyear DESC LIMIT 1"
        );

        if ($result === null) {
            return 1988;
        }

        /** @var array{draftyear: int} $result */
        return $result['draftyear'];
    }

    /**
     * @see DraftHistoryRepositoryInterface::getDraftPicksByYear()
     *
     * @return list<DraftPickByYearRow>
     */
    public function getDraftPicksByYear(int $year): array
    {
        // The drafting identity is the franchise's name in the draft year (fs), so a retired
        // name or a reused one ("Nets", "Thunder") gets its era franchise and colors; names
        // with no fs row that year (pre-1989) fall back to the current team_name match.
        /** @var list<DraftPickByYearRow> */
        return $this->fetchAll(
            "SELECT p.pid, p.name, p.pos, p.draftround, p.draftpickno, p.draftedby, p.college,
                    t.teamid,
                    COALESCE(fs.team_city, t.team_city) AS team_city,
                    COALESCE(NULLIF(eb.color1, ''), t.color1) AS color1,
                    COALESCE(NULLIF(eb.color2, ''), t.color2) AS color2
            FROM `ibl_plr` p
            LEFT JOIN `ibl_franchise_seasons` fs
                   ON fs.team_name = p.draftedby AND fs.season_ending_year = p.draftyear
            LEFT JOIN `ibl_team_info` cur ON p.draftedby = cur.team_name
            LEFT JOIN `ibl_team_info` t ON t.teamid = COALESCE(fs.franchise_id, cur.teamid)
            LEFT JOIN `ibl_franchise_era_branding` eb
                   ON eb.franchise_id = fs.franchise_id
                  AND eb.team_city COLLATE utf8mb4_unicode_ci = fs.team_city
                  AND eb.team_name COLLATE utf8mb4_unicode_ci = fs.team_name
            WHERE p.draftyear = ? AND p.draftround > 0
            ORDER BY p.draftround ASC, p.draftpickno ASC, p.pid ASC",
            "i",
            $year
        );
    }

    /**
     * @see DraftHistoryRepositoryInterface::getDraftPicksByTeam()
     *
     * @return list<DraftPickByTeamRow>
     */
    public function getDraftPicksByTeam(string $teamName): array
    {
        /** @var list<DraftPickByTeamRow> */
        return $this->fetchAll(
            "SELECT p.pid, p.name, p.pos, p.draftround, p.draftpickno, p.draftyear, p.college, p.retired
            FROM `ibl_plr` p
            WHERE p.draftedby = ? AND p.draftround > 0
            ORDER BY p.draftyear DESC, p.draftround ASC, p.draftpickno ASC, p.pid ASC",
            "s",
            $teamName
        );
    }
}
