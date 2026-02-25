<?php

declare(strict_types=1);

namespace Team;

use Player\Player;
use Team\Contracts\TeamQueryRepositoryInterface;

/**
 * TeamQueryRepository - Query methods for team-related data
 *
 * Extracted from the Team entity class to separate query concerns from entity state.
 * Extends BaseMysqliRepository for standardized database access via fetchAll/fetchOne.
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type DraftPickRow from TeamQueryRepositoryInterface
 * @phpstan-import-type FreeAgencyOfferRow from TeamQueryRepositoryInterface
 *
 * @see TeamQueryRepositoryInterface
 * @see \BaseMysqliRepository For base class documentation and error codes
 */
class TeamQueryRepository extends \BaseMysqliRepository implements TeamQueryRepositoryInterface
{
    /**
     * @see TeamQueryRepositoryInterface::getBuyouts()
     *
     * @return list<PlayerRow>
     */
    public function getBuyouts(int $teamId): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE tid = ?
              AND name LIKE '%Buyout%'
            ORDER BY name ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getDraftHistory()
     *
     * @return list<PlayerRow>
     */
    public function getDraftHistory(string $teamName): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE draftedby LIKE ?
            ORDER BY draftyear DESC,
                     draftround,
                     draftpickno ASC",
            "s",
            $teamName
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getDraftPicks()
     *
     * @return list<DraftPickRow>
     */
    public function getDraftPicks(string $teamName): array
    {
        /** @var list<DraftPickRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_draft_picks
            WHERE ownerofpick = ?
            ORDER BY year, round, teampick ASC",
            "s",
            $teamName
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getFreeAgencyOffers()
     *
     * @return list<FreeAgencyOfferRow>
     */
    public function getFreeAgencyOffers(int $teamId): array
    {
        /** @var list<FreeAgencyOfferRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_fa_offers
            WHERE tid = ?
            ORDER BY name ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getFreeAgencyRosterOrderedByName()
     *
     * @return list<PlayerRow>
     */
    public function getFreeAgencyRosterOrderedByName(int $teamId): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE tid = ?
              AND retired = 0
              AND cyt != cy
            ORDER BY name ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getHealthyAndInjuredPlayersOrderedByName()
     *
     * @return list<PlayerRow>
     */
    public function getHealthyAndInjuredPlayersOrderedByName(string $teamName, int $teamId, ?\Season $season = null): array
    {
        $freeAgencyCondition = '';
        if ($season !== null && $season->phase === 'Free Agency') {
            // During Free Agency, only count players who have a salary for next year
            $freeAgencyCondition = " AND (
                (cy = 0 AND cy1 > 0) OR
                (cy = 0 AND cy2 > 0) OR
                (cy = 1 AND cy2 > 0) OR
                (cy = 2 AND cy3 > 0) OR
                (cy = 3 AND cy4 > 0) OR
                (cy = 4 AND cy5 > 0) OR
                (cy = 5 AND cy6 > 0)
            )";
        }

        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE teamname = ?
              AND tid = ?
              AND retired = 0
              AND ordinal <= '" . \JSB::WAIVERS_ORDINAL . "'" . $freeAgencyCondition . "
            ORDER BY name ASC",
            "si",
            $teamName,
            $teamId
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getHealthyPlayersOrderedByName()
     *
     * @return list<PlayerRow>
     */
    public function getHealthyPlayersOrderedByName(string $teamName, int $teamId, ?\Season $season = null): array
    {
        $freeAgencyCondition = '';
        if ($season !== null && $season->phase === 'Free Agency') {
            // During Free Agency, only count players who have a salary for next year
            $freeAgencyCondition = " AND (
                (cy = 0 AND cy1 > 0) OR
                (cy = 0 AND cy2 > 0) OR
                (cy = 1 AND cy2 > 0) OR
                (cy = 2 AND cy3 > 0) OR
                (cy = 3 AND cy4 > 0) OR
                (cy = 4 AND cy5 > 0) OR
                (cy = 5 AND cy6 > 0)
            )";
        }

        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE teamname = ?
              AND tid = ?
              AND retired = 0
              AND ordinal <= '" . \JSB::WAIVERS_ORDINAL . "'" . $freeAgencyCondition . "
              AND injured = '0'
            ORDER BY name ASC",
            "si",
            $teamName,
            $teamId
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getLastSimStarterPlayerIDForPosition()
     */
    public function getLastSimStarterPlayerIDForPosition(int $teamId, string $position): int
    {
        /** @var array{pid: int}|null $result */
        $result = $this->fetchOne(
            "SELECT pid
            FROM ibl_plr
            WHERE tid = ?
              AND retired = 0
              AND " . $position . "Depth = 1",
            "i",
            $teamId
        );
        return $result !== null ? $result['pid'] : 0;
    }

    /**
     * @see TeamQueryRepositoryInterface::getCurrentlySetStarterPlayerIDForPosition()
     */
    public function getCurrentlySetStarterPlayerIDForPosition(int $teamId, string $position): int
    {
        /** @var array{pid: int}|null $result */
        $result = $this->fetchOne(
            "SELECT pid
            FROM ibl_plr
            WHERE tid = ?
              AND retired = 0
              AND dc_" . $position . "Depth = 1",
            "i",
            $teamId
        );
        return $result !== null ? $result['pid'] : 0;
    }

    /**
     * @see TeamQueryRepositoryInterface::getAllPlayersUnderContract()
     *
     * @return list<PlayerRow>
     */
    public function getAllPlayersUnderContract(string $teamName): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE teamname = ?
              AND cy1 != 0
              AND retired = 0",
            "s",
            $teamName
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getPlayersUnderContractByPosition()
     *
     * @return list<PlayerRow>
     */
    public function getPlayersUnderContractByPosition(string $teamName, string $position): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE teamname = ?
              AND pos = ?
              AND cy1 != 0
              AND retired = 0",
            "ss",
            $teamName,
            $position
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getRosterUnderContractOrderedByName()
     *
     * @return list<PlayerRow>
     */
    public function getRosterUnderContractOrderedByName(int $teamId): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE tid = ?
              AND retired = 0
            ORDER BY name ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getRosterUnderContractOrderedByOrdinal()
     *
     * @return list<PlayerRow>
     */
    public function getRosterUnderContractOrderedByOrdinal(int $teamId): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE tid = ?
              AND retired = 0
            ORDER BY ordinal ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getSalaryCapArray()
     *
     * @return array<string, int>
     */
    public function getSalaryCapArray(string $teamName, int $teamId, \Season $season): array
    {
        /** @var array<string, int> $salaryCapSpent */
        $salaryCapSpent = [];
        $resultContracts = $this->getRosterUnderContractOrderedByName($teamId);

        foreach ($resultContracts as $contract) {
            $yearUnderContract = $contract['cy'] ?? 0;
            if ($season->phase === "Free Agency") {
                $yearUnderContract++;
            }

            $cyt = $contract['cyt'] ?? 0;
            $i = 1;
            while ($yearUnderContract <= $cyt) {
                $fieldString = "cy" . $yearUnderContract;
                $key = "year" . $i;
                if (!isset($salaryCapSpent[$key])) {
                    $salaryCapSpent[$key] = 0;
                }
                $salaryCapSpent[$key] += (int) ($contract[$fieldString] ?? 0);
                $yearUnderContract++;
                $i++;
            }
        }

        return $salaryCapSpent;
    }

    /**
     * @see TeamQueryRepositoryInterface::getTotalCurrentSeasonSalaries()
     *
     * @param list<PlayerRow> $result
     */
    public function getTotalCurrentSeasonSalaries(array $result): int
    {
        $totalCurrentSeasonSalaries = 0;

        $playerArray = $this->convertPlrResultIntoPlayerArray($result);
        foreach ($playerArray as $player) {
            $totalCurrentSeasonSalaries += $player->getCurrentSeasonSalary();
        }
        return $totalCurrentSeasonSalaries;
    }

    /**
     * @see TeamQueryRepositoryInterface::getTotalNextSeasonSalaries()
     *
     * @param list<PlayerRow> $result
     */
    public function getTotalNextSeasonSalaries(array $result): int
    {
        $totalNextSeasonSalaries = 0;

        $playerArray = $this->convertPlrResultIntoPlayerArray($result);
        foreach ($playerArray as $player) {
            $totalNextSeasonSalaries += $player->getNextSeasonSalary();
        }
        return $totalNextSeasonSalaries;
    }

    /**
     * @see TeamQueryRepositoryInterface::canAddContractWithoutGoingOverHardCap()
     */
    public function canAddContractWithoutGoingOverHardCap(int $teamId, int $contractValue): bool
    {
        $teamResult = $this->getRosterUnderContractOrderedByName($teamId);
        $totalCurrentSeasonSalaries = $this->getTotalCurrentSeasonSalaries($teamResult);
        $projectedTotalCurrentSeasonSalaries = $totalCurrentSeasonSalaries + $contractValue;

        return $projectedTotalCurrentSeasonSalaries <= \League::HARD_CAP_MAX;
    }

    /**
     * @see TeamQueryRepositoryInterface::canAddBuyoutWithoutExceedingBuyoutLimit()
     */
    public function canAddBuyoutWithoutExceedingBuyoutLimit(int $teamId, int $buyoutValue): bool
    {
        $buyoutsResult = $this->getBuyouts($teamId);
        $totalCurrentSeasonBuyouts = $this->getTotalCurrentSeasonSalaries($buyoutsResult);
        $projectedTotalCurrentSeasonBuyouts = $totalCurrentSeasonBuyouts + $buyoutValue;
        $buyoutLimit = \League::HARD_CAP_MAX * \Team::BUYOUT_PERCENTAGE_MAX;

        return $projectedTotalCurrentSeasonBuyouts <= $buyoutLimit;
    }

    /**
     * @see TeamQueryRepositoryInterface::convertPlrResultIntoPlayerArray()
     *
     * @param list<PlayerRow> $result
     * @return array<int, Player>
     */
    public function convertPlrResultIntoPlayerArray(array $result): array
    {
        $array = [];
        foreach ($result as $plrRow) {
            $playerID = (int) $plrRow['pid'];
            $array[$playerID] = Player::withPlrRow($this->db, $plrRow);
        }
        return $array;
    }
}
