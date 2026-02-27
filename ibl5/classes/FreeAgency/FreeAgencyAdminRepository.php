<?php

declare(strict_types=1);

namespace FreeAgency;

use BaseMysqliRepository;
use FreeAgency\Contracts\FreeAgencyAdminRepositoryInterface;

/**
 * Repository for admin free agency database operations
 *
 * Handles all database queries for the admin free agency processor,
 * including offer retrieval, player contract updates, MLE/LLE usage,
 * news story insertion, and offer clearing.
 *
 * @see FreeAgencyAdminRepositoryInterface
 *
 * @phpstan-import-type OfferRow from FreeAgencyAdminRepositoryInterface
 * @phpstan-import-type DemandRow from FreeAgencyAdminRepositoryInterface
 */
class FreeAgencyAdminRepository extends BaseMysqliRepository implements FreeAgencyAdminRepositoryInterface
{
    /**
     * @see FreeAgencyAdminRepositoryInterface::getAllOffersWithBirdYears()
     *
     * @return list<OfferRow>
     */
    public function getAllOffersWithBirdYears(): array
    {
        /** @var list<OfferRow> */
        return $this->fetchAll(
            "SELECT ibl_fa_offers.*, ibl_plr.bird
             FROM ibl_fa_offers
             JOIN ibl_plr ON ibl_fa_offers.pid = ibl_plr.pid
             ORDER BY ibl_fa_offers.name ASC, ibl_fa_offers.perceivedvalue DESC",
            ""
        );
    }

    /**
     * @see FreeAgencyAdminRepositoryInterface::getPlayerDemands()
     *
     * @return DemandRow|null
     */
    public function getPlayerDemands(int $playerID): ?array
    {
        /** @var DemandRow|null */
        return $this->fetchOne(
            "SELECT dem1, dem2, dem3, dem4, dem5, dem6 FROM ibl_demands WHERE pid = ?",
            "i",
            $playerID
        );
    }

    /**
     * @see FreeAgencyAdminRepositoryInterface::updatePlayerContract()
     */
    public function updatePlayerContract(
        int $pid,
        string $teamName,
        int $tid,
        int $offerYears,
        int $offer1,
        int $offer2,
        int $offer3,
        int $offer4,
        int $offer5,
        int $offer6
    ): int {
        return $this->execute(
            "UPDATE ibl_plr
             SET cy = 0,
                 cy1 = ?,
                 cy2 = ?,
                 cy3 = ?,
                 cy4 = ?,
                 cy5 = ?,
                 cy6 = ?,
                 teamname = ?,
                 cyt = ?,
                 tid = ?,
                 fa_signing_flag = 1
             WHERE pid = ?
             LIMIT 1",
            "iiiiiisiii",
            $offer1,
            $offer2,
            $offer3,
            $offer4,
            $offer5,
            $offer6,
            $teamName,
            $offerYears,
            $tid,
            $pid
        );
    }

    /**
     * @see FreeAgencyAdminRepositoryInterface::markMleUsed()
     */
    public function markMleUsed(string $teamName): void
    {
        $this->execute(
            "UPDATE ibl_team_info SET HasMLE = 0 WHERE team_name = ? LIMIT 1",
            "s",
            $teamName
        );
    }

    /**
     * @see FreeAgencyAdminRepositoryInterface::markLleUsed()
     */
    public function markLleUsed(string $teamName): void
    {
        $this->execute(
            "UPDATE ibl_team_info SET HasLLE = 0 WHERE team_name = ? LIMIT 1",
            "s",
            $teamName
        );
    }

    /**
     * @see FreeAgencyAdminRepositoryInterface::insertNewsStory()
     */
    public function insertNewsStory(string $title, string $homeText, string $bodyText): int
    {
        $currentTime = date('Y-m-d H:i:s');

        return $this->execute(
            "INSERT INTO nuke_stories
             (catid, aid, title, time, hometext, bodytext, comments, counter, topic, informant, notes, ihome, alanguage, acomm, haspoll, pollID, associated)
             VALUES (8, 'chibul', ?, ?, ?, ?, 0, 0, 29, 'chibul', '', 0, 'english', 0, 0, 0, '29-')",
            "ssss",
            $title,
            $currentTime,
            $homeText,
            $bodyText
        );
    }

    /**
     * @see FreeAgencyAdminRepositoryInterface::clearAllOffers()
     */
    public function clearAllOffers(): void
    {
        $this->execute("DELETE FROM ibl_fa_offers", "");
    }
}
