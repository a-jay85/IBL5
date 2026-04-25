<?php

declare(strict_types=1);

namespace FreeAgency;

use BaseMysqliRepository;
use FreeAgency\Contracts\FreeAgencyRepositoryInterface;

/**
 * FreeAgencyRepository - Database operations for free agency module
 *
 * Extends BaseMysqliRepository for standardized prepared statement handling.
 * Centralizes all database queries for the FreeAgency module.
 *
 * @see FreeAgencyRepositoryInterface For method contracts
 * @see BaseMysqliRepository For base class documentation and error codes
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type OfferRow from \FreeAgency\Contracts\FreeAgencyRepositoryInterface
 * @phpstan-import-type OfferData from \FreeAgency\Contracts\FreeAgencyRepositoryInterface
 */
class FreeAgencyRepository extends BaseMysqliRepository implements FreeAgencyRepositoryInterface
{
    /**
     * Constructor - inherits from BaseMysqliRepository
     *
     * @param \mysqli $db Active mysqli connection
     * @throws \RuntimeException If connection is invalid (error code 1002)
     */
    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    /**
     * @see FreeAgencyRepositoryInterface::getExistingOffer()
     *
     * @return OfferRow|null
     */
    public function getExistingOffer(int $teamid, int $pid): ?array
    {
        /** @var OfferRow|null $result */
        $result = $this->fetchOne(
            "SELECT offer1, offer2, offer3, offer4, offer5, offer6
             FROM ibl_fa_offers
             WHERE teamid = ? AND pid = ?",
            "ii",
            $teamid,
            $pid
        );

        return $result;
    }

    /**
     * @see FreeAgencyRepositoryInterface::deleteOffer()
     */
    public function deleteOffer(int $teamid, int $pid): int
    {
        return $this->execute(
            "DELETE FROM ibl_fa_offers WHERE pid = ? AND teamid = ? LIMIT 1",
            "ii",
            $pid,
            $teamid
        );
    }

    /**
     * @see FreeAgencyRepositoryInterface::saveOffer()
     *
     * @param OfferData $offerData
     */
    public function saveOffer(array $offerData): bool
    {
        return $this->transactional(function () use ($offerData): bool {
            $this->deleteOffer($offerData['teamid'], $offerData['pid']);

            $affected = $this->execute(
                "INSERT INTO ibl_fa_offers
                 (name, pid, team, teamid, offer1, offer2, offer3, offer4, offer5, offer6,
                  modifier, random, perceivedvalue, mle, lle, offer_type)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                "sisiiiiiiidddiii",
                $offerData['playerName'],
                $offerData['pid'],
                $offerData['teamName'],
                $offerData['teamid'],
                $offerData['offer1'],
                $offerData['offer2'],
                $offerData['offer3'],
                $offerData['offer4'],
                $offerData['offer5'],
                $offerData['offer6'],
                $offerData['modifier'],
                $offerData['random'],
                $offerData['perceivedValue'],
                $offerData['mle'],
                $offerData['lle'],
                $offerData['offerType']
            );

            return $affected > 0;
        });
    }

    /**
     * @see FreeAgencyRepositoryInterface::getAllPlayersExcludingTeam()
     *
     * @return list<PlayerRow>
     */
    public function getAllPlayersExcludingTeam(int $teamId): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT p.*, t.team_name AS teamname, t.color1, t.color2
            FROM ibl_plr p
            LEFT JOIN ibl_team_info t ON p.teamid = t.teamid
            WHERE p.teamid <> ? AND p.retired = 0
            ORDER BY p.ordinal ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see FreeAgencyRepositoryInterface::isPlayerAlreadySigned()
     */
    public function isPlayerAlreadySigned(int $playerId): bool
    {
        /** @var array{cy: int|null, salary_yr1: int|null}|null $row */
        $row = $this->fetchOne(
            "SELECT cy, salary_yr1 FROM ibl_plr WHERE pid = ?",
            "i",
            $playerId
        );

        if ($row === null) {
            return false;
        }

        $currentContractYear = $row['cy'] ?? 0;
        $year1Contract = $row['salary_yr1'] ?? 0;

        return ($currentContractYear === 0 && $year1Contract !== 0);
    }

    /**
     * @see FreeAgencyRepositoryInterface::hasPendingMleOffer()
     */
    public function hasPendingMleOffer(int $teamid, int $excludePid): bool
    {
        /** @var array{pid: int}|null $row */
        $row = $this->fetchOne(
            "SELECT pid FROM ibl_fa_offers
             WHERE teamid = ? AND MLE = 1 AND pid <> ?
             LIMIT 1",
            "ii",
            $teamid,
            $excludePid
        );

        return $row !== null;
    }

    /**
     * @see FreeAgencyRepositoryInterface::hasPendingLleOffer()
     */
    public function hasPendingLleOffer(int $teamid, int $excludePid): bool
    {
        /** @var array{pid: int}|null $row */
        $row = $this->fetchOne(
            "SELECT pid FROM ibl_fa_offers
             WHERE teamid = ? AND LLE = 1 AND pid <> ?
             LIMIT 1",
            "ii",
            $teamid,
            $excludePid
        );

        return $row !== null;
    }
}
