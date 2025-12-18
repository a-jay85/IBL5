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
 */
class FreeAgencyRepository extends BaseMysqliRepository implements FreeAgencyRepositoryInterface
{
    /**
     * Constructor - inherits from BaseMysqliRepository
     * 
     * @param object $db Active mysqli connection (or duck-typed mock during migration)
     * @throws \RuntimeException If connection is invalid (error code 1002)
     */
    public function __construct(object $db)
    {
        parent::__construct($db);
    }

    /**
     * @see FreeAgencyRepositoryInterface::getExistingOffer()
     */
    public function getExistingOffer(string $teamName, string $playerName): ?array
    {
        $result = $this->fetchOne(
            "SELECT offer1, offer2, offer3, offer4, offer5, offer6 
             FROM ibl_fa_offers 
             WHERE team = ? AND name = ?",
            "ss",
            $teamName,
            $playerName
        );
        
        return $result;
    }

    /**
     * @see FreeAgencyRepositoryInterface::deleteOffer()
     */
    public function deleteOffer(string $teamName, string $playerName): int
    {
        return $this->execute(
            "DELETE FROM ibl_fa_offers WHERE name = ? AND team = ? LIMIT 1",
            "ss",
            $playerName,
            $teamName
        );
    }

    /**
     * @see FreeAgencyRepositoryInterface::saveOffer()
     */
    public function saveOffer(array $offerData): bool
    {
        // First delete any existing offer
        $this->deleteOffer($offerData['teamName'], $offerData['playerName']);
        
        // Insert the new offer
        $affected = $this->execute(
            "INSERT INTO ibl_fa_offers 
             (name, team, offer1, offer2, offer3, offer4, offer5, offer6, 
              modifier, random, perceivedvalue, mle, lle, offer_type) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            "ssiiiiiiiidiii",
            $offerData['playerName'],
            $offerData['teamName'],
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
     */
    public function getAllPlayersExcludingTeam(string $teamName): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_plr WHERE teamname != ? AND retired = '0' ORDER BY ordinal ASC",
            "s",
            $teamName
        );
    }
}
