<?php

declare(strict_types=1);

namespace Team;

use League\League;
use Player\Player;
use Team\Contracts\TeamQueryRepositoryInterface;
use Season\Season;
use Trading\CashConsiderationRepository;
use Trading\Contracts\CashConsiderationRepositoryInterface;

/**
 * TeamQueryRepository - Query methods for team-related data
 *
 * Extracted from the Team entity class to separate query concerns from entity state.
 * Extends BaseMysqliRepository for standardized database access via fetchAll/fetchOne.
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type CashConsiderationRow from \Trading\Contracts\CashConsiderationRepositoryInterface
 * @phpstan-import-type DraftPickRow from TeamQueryRepositoryInterface
 * @phpstan-import-type FreeAgencyOfferRow from TeamQueryRepositoryInterface
 *
 * @see TeamQueryRepositoryInterface
 * @see \BaseMysqliRepository For base class documentation and error codes
 */
class TeamQueryRepository extends \BaseMysqliRepository implements TeamQueryRepositoryInterface
{
    private CashConsiderationRepositoryInterface $cashConsiderationRepo;

    public function __construct(\mysqli $db, ?\League\LeagueContext $leagueContext = null, ?CashConsiderationRepositoryInterface $cashConsiderationRepo = null)
    {
        parent::__construct($db, $leagueContext);
        $this->cashConsiderationRepo = $cashConsiderationRepo ?? new CashConsiderationRepository($db);
    }

    /**
     * @see TeamQueryRepositoryInterface::getBuyouts()
     *
     * @return list<CashConsiderationRow>
     */
    public function getBuyouts(int $teamId): array
    {
        return $this->cashConsiderationRepo->getTeamBuyouts($teamId);
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
            "SELECT p.*, t.team_name AS teamname, t.color1, t.color2
            FROM ibl_plr p
            LEFT JOIN ibl_team_info t ON p.tid = t.teamid
            WHERE p.draftedby LIKE ?
            ORDER BY p.draftyear DESC,
                     p.draftround,
                     p.draftpickno ASC",
            "s",
            $teamName
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getDraftPicks()
     *
     * @return list<DraftPickRow>
     */
    public function getDraftPicks(int $teamId): array
    {
        /** @var list<DraftPickRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_draft_picks
            WHERE owner_tid = ?
            ORDER BY year, round, teampick ASC",
            "i",
            $teamId
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
            "SELECT p.*, t.team_name AS teamname, t.color1, t.color2
            FROM ibl_plr p
            LEFT JOIN ibl_team_info t ON p.tid = t.teamid
            WHERE p.tid = ?
              AND p.retired = 0
              AND p.cyt != p.cy
            ORDER BY p.name ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getHealthyAndInjuredPlayersOrderedByName()
     *
     * @return list<PlayerRow>
     */
    public function getHealthyAndInjuredPlayersOrderedByName(int $teamId, ?Season $season = null): array
    {
        $freeAgencyCondition = '';
        if ($season !== null && $season->isOffseasonPhase()) {
            // During Free Agency, only count players who have a salary for next year
            $freeAgencyCondition = " AND (
                (p.cy = 0 AND p.cy1 > 0) OR
                (p.cy = 0 AND p.cy2 > 0) OR
                (p.cy = 1 AND p.cy2 > 0) OR
                (p.cy = 2 AND p.cy3 > 0) OR
                (p.cy = 3 AND p.cy4 > 0) OR
                (p.cy = 4 AND p.cy5 > 0) OR
                (p.cy = 5 AND p.cy6 > 0)
            )";
        }

        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT p.*, t.team_name AS teamname, t.color1, t.color2
            FROM ibl_plr p
            LEFT JOIN ibl_team_info t ON p.tid = t.teamid
            WHERE p.tid = ?
              AND p.retired = 0
              AND p.ordinal <= '" . \JSB::WAIVERS_ORDINAL . "'" . $freeAgencyCondition . "
            ORDER BY p.name ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getHealthyPlayersOrderedByName()
     *
     * @return list<PlayerRow>
     */
    public function getHealthyPlayersOrderedByName(int $teamId, ?Season $season = null): array
    {
        $freeAgencyCondition = '';
        if ($season !== null && $season->isOffseasonPhase()) {
            // During Free Agency, only count players who have a salary for next year
            $freeAgencyCondition = " AND (
                (p.cy = 0 AND p.cy1 > 0) OR
                (p.cy = 0 AND p.cy2 > 0) OR
                (p.cy = 1 AND p.cy2 > 0) OR
                (p.cy = 2 AND p.cy3 > 0) OR
                (p.cy = 3 AND p.cy4 > 0) OR
                (p.cy = 4 AND p.cy5 > 0) OR
                (p.cy = 5 AND p.cy6 > 0)
            )";
        }

        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT p.*, t.team_name AS teamname, t.color1, t.color2
            FROM ibl_plr p
            LEFT JOIN ibl_team_info t ON p.tid = t.teamid
            WHERE p.tid = ?
              AND p.retired = 0
              AND p.ordinal <= '" . \JSB::WAIVERS_ORDINAL . "'" . $freeAgencyCondition . "
              AND p.injured = '0'
            ORDER BY p.name ASC",
            "i",
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
    public function getAllPlayersUnderContract(int $teamId): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT p.*, t.team_name AS teamname, t.color1, t.color2
            FROM ibl_plr p
            LEFT JOIN ibl_team_info t ON p.tid = t.teamid
            WHERE p.tid = ?
              AND p.cy1 != 0
              AND p.retired = 0",
            "i",
            $teamId
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getPlayersUnderContractByPosition()
     *
     * @return list<PlayerRow>
     */
    public function getPlayersUnderContractByPosition(int $teamId, string $position): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT p.*, t.team_name AS teamname, t.color1, t.color2
            FROM ibl_plr p
            LEFT JOIN ibl_team_info t ON p.tid = t.teamid
            WHERE p.tid = ?
              AND p.pos = ?
              AND p.cy1 != 0
              AND p.retired = 0",
            "is",
            $teamId,
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
            "SELECT p.*, t.team_name AS teamname, t.color1, t.color2
            FROM ibl_plr p
            LEFT JOIN ibl_team_info t ON p.tid = t.teamid
            WHERE p.tid = ?
              AND p.retired = 0
            ORDER BY p.name ASC",
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
            "SELECT p.*, t.team_name AS teamname, t.color1, t.color2
            FROM ibl_plr p
            LEFT JOIN ibl_team_info t ON p.tid = t.teamid
            WHERE p.tid = ?
              AND p.retired = 0
            ORDER BY p.ordinal ASC",
            "i",
            $teamId
        );
    }

    /**
     * @see TeamQueryRepositoryInterface::getSalaryCapArray()
     *
     * @return array<string, int>
     */
    public function getSalaryCapArray(string $teamName, int $teamId, Season $season): array
    {
        /** @var array<string, int> $salaryCapSpent */
        $salaryCapSpent = [];
        $resultContracts = $this->getRosterUnderContractOrderedByName($teamId);

        foreach ($resultContracts as $contract) {
            $yearUnderContract = $contract['cy'] ?? 0;
            if ($season->isOffseasonPhase()) {
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

        // Add cash considerations (trades, buyouts) for the team
        $cashRows = $this->cashConsiderationRepo->getTeamCashForSalary($teamId);

        foreach ($cashRows as $cashRow) {
            $yearUnderContract = $cashRow['cy'];
            if ($season->isOffseasonPhase()) {
                $yearUnderContract++;
            }

            $i = 1;
            while ($yearUnderContract <= 6) {
                $key = "year" . $i;
                if (!isset($salaryCapSpent[$key])) {
                    $salaryCapSpent[$key] = 0;
                }
                $salaryCapSpent[$key] += match ($yearUnderContract) {
                    1 => $cashRow['cy1'],
                    2 => $cashRow['cy2'],
                    3 => $cashRow['cy3'],
                    4 => $cashRow['cy4'],
                    5 => $cashRow['cy5'],
                    6 => $cashRow['cy6'],
                    default => 0,
                };
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

        return $projectedTotalCurrentSeasonSalaries <= League::HARD_CAP_MAX;
    }

    /**
     * @see TeamQueryRepositoryInterface::canAddBuyoutWithoutExceedingBuyoutLimit()
     */
    public function canAddBuyoutWithoutExceedingBuyoutLimit(int $teamId, int $buyoutValue): bool
    {
        $season = new Season($this->db);
        $buyoutsResult = $this->getBuyouts($teamId);
        $totalCurrentSeasonBuyouts = 0;
        foreach ($buyoutsResult as $buyout) {
            $cy = $buyout['cy'];
            if ($season->isOffseasonPhase()) {
                $cy++;
            }
            if ($cy === 0) {
                $cy = 1;
            }
            $totalCurrentSeasonBuyouts += match ($cy) {
                1 => $buyout['cy1'],
                2 => $buyout['cy2'],
                3 => $buyout['cy3'],
                4 => $buyout['cy4'],
                5 => $buyout['cy5'],
                6 => $buyout['cy6'],
                default => 0,
            };
        }
        $projectedTotalCurrentSeasonBuyouts = $totalCurrentSeasonBuyouts + $buyoutValue;
        $buyoutLimit = League::HARD_CAP_MAX * Team::BUYOUT_PERCENTAGE_MAX;

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
