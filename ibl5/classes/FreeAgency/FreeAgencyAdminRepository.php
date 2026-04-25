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
     * @see FreeAgencyAdminRepositoryInterface::getPlayerDemandsBatch()
     *
     * @return array<int, DemandRow>
     */
    public function getPlayerDemandsBatch(array $playerIds): array
    {
        if ($playerIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($playerIds), '?'));
        $types = str_repeat('i', count($playerIds));

        /** @var list<array{pid: int, dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT pid, dem1, dem2, dem3, dem4, dem5, dem6 FROM ibl_demands WHERE pid IN ({$placeholders})",
            $types,
            ...$playerIds
        );

        $result = [];
        foreach ($rows as $row) {
            $result[$row['pid']] = [
                'dem1' => $row['dem1'],
                'dem2' => $row['dem2'],
                'dem3' => $row['dem3'],
                'dem4' => $row['dem4'],
                'dem5' => $row['dem5'],
                'dem6' => $row['dem6'],
            ];
        }

        return $result;
    }

    /**
     * @see FreeAgencyAdminRepositoryInterface::updatePlayerContract()
     */
    public function updatePlayerContract(
        int $pid,
        int $teamid,
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
                 salary_yr1 = ?,
                 salary_yr2 = ?,
                 salary_yr3 = ?,
                 salary_yr4 = ?,
                 salary_yr5 = ?,
                 salary_yr6 = ?,
                 cyt = ?,
                 teamid = ?,
                 fa_signing_flag = 1
             WHERE pid = ?
             LIMIT 1",
            "iiiiiiiii",
            $offer1,
            $offer2,
            $offer3,
            $offer4,
            $offer5,
            $offer6,
            $offerYears,
            $teamid,
            $pid
        );
    }

    /**
     * @see FreeAgencyAdminRepositoryInterface::markMleUsed()
     */
    public function markMleUsed(string $teamName): void
    {
        $this->execute(
            "UPDATE ibl_team_info SET has_mle = 0 WHERE team_name = ? LIMIT 1",
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
            "UPDATE ibl_team_info SET has_lle = 0 WHERE team_name = ? LIMIT 1",
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
             (catid, aid, title, time, hometext, bodytext, comments, counter, topic, informant, notes, ihome, alanguage, acomm, haspoll, poll_id, associated)
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

    /**
     * @see FreeAgencyAdminRepositoryInterface::executeSigningsTransactionally()
     */
    public function executeSigningsTransactionally(
        array $signings,
        string $newsTitle,
        string $newsHomeText,
        string $newsBodyText
    ): array {
        return $this->transactional(function () use ($signings, $newsTitle, $newsHomeText, $newsBodyText): array {
            $successCount = 0;
            $errorCount = 0;

            foreach ($signings as $signing) {
                $affected = $this->updatePlayerContract(
                    $signing['playerId'],
                    $signing['teamId'],
                    $signing['offerYears'],
                    $signing['offers']['offer1'],
                    $signing['offers']['offer2'],
                    $signing['offers']['offer3'],
                    $signing['offers']['offer4'],
                    $signing['offers']['offer5'],
                    $signing['offers']['offer6']
                );

                if ($affected > 0) {
                    $successCount++;
                } else {
                    $errorCount++;
                }

                if ($signing['usedMle']) {
                    $this->markMleUsed($signing['teamName']);
                }

                if ($signing['usedLle']) {
                    $this->markLleUsed($signing['teamName']);
                }
            }

            if ($successCount > 0 && $newsHomeText !== '' && $newsBodyText !== '') {
                $affected = $this->insertNewsStory($newsTitle, $newsHomeText, $newsBodyText);
                if ($affected > 0) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }

            return ['successCount' => $successCount, 'errorCount' => $errorCount];
        });
    }
}
