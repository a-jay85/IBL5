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
    public function getExistingOffer(int $tid, int $pid): ?array
    {
        /** @var OfferRow|null $result */
        $result = $this->fetchOne(
            "SELECT offer1, offer2, offer3, offer4, offer5, offer6
             FROM ibl_fa_offers
             WHERE tid = ? AND pid = ?",
            "ii",
            $tid,
            $pid
        );

        return $result;
    }

    /**
     * @see FreeAgencyRepositoryInterface::deleteOffer()
     */
    public function deleteOffer(int $tid, int $pid): int
    {
        return $this->execute(
            "DELETE FROM ibl_fa_offers WHERE pid = ? AND tid = ? LIMIT 1",
            "ii",
            $pid,
            $tid
        );
    }

    /**
     * @see FreeAgencyRepositoryInterface::saveOffer()
     *
     * @param OfferData $offerData
     */
    public function saveOffer(array $offerData): bool
    {
        // First delete any existing offer
        $this->deleteOffer($offerData['tid'], $offerData['pid']);

        // Insert the new offer
        $affected = $this->execute(
            "INSERT INTO ibl_fa_offers
             (name, pid, team, tid, offer1, offer2, offer3, offer4, offer5, offer6,
              modifier, random, perceivedvalue, mle, lle, offer_type)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            "sisiiiiiiiiidiii",
            $offerData['playerName'],
            $offerData['pid'],
            $offerData['teamName'],
            $offerData['tid'],
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
    }

    /**
     * @see FreeAgencyRepositoryInterface::getAllPlayersExcludingTeam()
     *
     * @return list<PlayerRow>
     */
    public function getAllPlayersExcludingTeam(string $teamName): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_plr WHERE teamname != ? AND retired = 0 ORDER BY ordinal ASC",
            "s",
            $teamName
        );
    }

    /**
     * @see FreeAgencyRepositoryInterface::isPlayerAlreadySigned()
     */
    public function isPlayerAlreadySigned(int $playerId): bool
    {
        /** @var array{cy: int|null, cy1: int|null}|null $row */
        $row = $this->fetchOne(
            "SELECT cy, cy1 FROM ibl_plr WHERE pid = ?",
            "i",
            $playerId
        );

        if ($row === null) {
            return false;
        }

        $currentContractYear = $row['cy'] ?? 0;
        $year1Contract = $row['cy1'] ?? 0;

        return ($currentContractYear === 0 && $year1Contract !== 0);
    }
}
