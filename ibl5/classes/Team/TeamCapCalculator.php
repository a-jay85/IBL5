<?php

declare(strict_types=1);

namespace Team;

use League\League;
use Player\Player;
use Season\Season;
use Team\Contracts\TeamCapCalculatorInterface;
use Team\Contracts\TeamQueryRepositoryInterface;
use Trading\BuyoutLedgerRepository;
use Trading\Contracts\BuyoutLedgerRepositoryInterface;

/**
 * TeamCapCalculator - Salary-cap compliance decisions for a team
 *
 * Holds the cap *business logic* extracted from {@see TeamQueryRepository}
 * (a data-access class per ADR-0001): hard-cap / buyout-limit verdicts and
 * salary aggregation over {@see Player} objects. The repository keeps only
 * raw row/aggregate fetches; this collaborator turns those fetches into cap
 * decisions, mirroring {@see \FreeAgency\FreeAgencyCapCalculator}
 * (ADR-0028: a domain calculator, not a generic Service/Shared bucket).
 *
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 *
 * @see TeamCapCalculatorInterface
 */
class TeamCapCalculator implements TeamCapCalculatorInterface
{
    private \mysqli $db;
    private TeamQueryRepositoryInterface $teamQueryRepo;
    private BuyoutLedgerRepositoryInterface $cashConsiderationRepo;

    /**
     * @param \mysqli $db Database connection (used to hydrate Player objects)
     * @param TeamQueryRepositoryInterface|null $teamQueryRepo Team query repository (created internally if not provided)
     * @param BuyoutLedgerRepositoryInterface|null $cashConsiderationRepo Cash consideration repository (created internally if not provided)
     */
    public function __construct(
        \mysqli $db,
        ?TeamQueryRepositoryInterface $teamQueryRepo = null,
        ?BuyoutLedgerRepositoryInterface $cashConsiderationRepo = null
    ) {
        $this->db = $db;
        $this->teamQueryRepo = $teamQueryRepo ?? new TeamQueryRepository($db);
        $this->cashConsiderationRepo = $cashConsiderationRepo ?? new BuyoutLedgerRepository($db);
    }

    /**
     * @see TeamCapCalculatorInterface::getSalaryCapArray()
     *
     * @return array<string, int>
     */
    public function getSalaryCapArray(string $teamName, int $teamId, Season $season): array
    {
        /** @var array<string, int> $salaryCapSpent */
        $salaryCapSpent = [];
        $resultContracts = $this->teamQueryRepo->getRosterUnderContractOrderedByName($teamId);

        foreach ($resultContracts as $contract) {
            $yearUnderContract = $contract['cy'] ?? 0;
            if ($season->isOffseasonPhase()) {
                $yearUnderContract++;
            }

            $cyt = $contract['cyt'] ?? 0;
            $i = 1;
            while ($yearUnderContract <= $cyt) {
                $fieldString = "salary_yr" . $yearUnderContract;
                $key = "year" . $i;
                if (!isset($salaryCapSpent[$key])) {
                    $salaryCapSpent[$key] = 0;
                }
                $rawSalary = $contract[$fieldString] ?? 0;
                $salaryCapSpent[$key] += is_numeric($rawSalary) ? (int) $rawSalary : 0;
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
                $salaryCapSpent[$key] += BuyoutLedgerRepository::salaryForContractYear($cashRow, $yearUnderContract);
                $yearUnderContract++;
                $i++;
            }
        }

        return $salaryCapSpent;
    }

    /**
     * @see TeamCapCalculatorInterface::getTotalCurrentSeasonSalaries()
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
     * @see TeamCapCalculatorInterface::getTotalNextSeasonSalaries()
     *
     * @param list<array<string, mixed>> $result
     */
    public function getTotalNextSeasonSalaries(array $result): int
    {
        $totalNextSeasonSalaries = 0;

        /** @var list<PlayerRow> $typedResult */
        $typedResult = $result;
        $playerArray = $this->convertPlrResultIntoPlayerArray($typedResult);
        foreach ($playerArray as $player) {
            $totalNextSeasonSalaries += $player->getNextSeasonSalary();
        }
        return $totalNextSeasonSalaries;
    }

    /**
     * @see TeamCapCalculatorInterface::canAddContractWithoutGoingOverHardCap()
     */
    public function canAddContractWithoutGoingOverHardCap(int $teamId, int $contractValue): bool
    {
        $teamResult = $this->teamQueryRepo->getRosterUnderContractOrderedByName($teamId);
        $totalCurrentSeasonSalaries = $this->getTotalCurrentSeasonSalaries($teamResult);
        $projectedTotalCurrentSeasonSalaries = $totalCurrentSeasonSalaries + $contractValue;

        return $projectedTotalCurrentSeasonSalaries <= League::HARD_CAP_MAX;
    }

    /**
     * @see TeamCapCalculatorInterface::canAddBuyoutWithoutExceedingBuyoutLimit()
     */
    public function canAddBuyoutWithoutExceedingBuyoutLimit(int $teamId, int $buyoutValue, Season $season): bool
    {
        $buyoutsResult = $this->teamQueryRepo->getBuyouts($teamId);
        $totalCurrentSeasonBuyouts = BuyoutLedgerRepository::sumCurrentSeasonSalaryFromRows(
            $buyoutsResult,
            $season->isOffseasonPhase()
        );
        $projectedTotalCurrentSeasonBuyouts = $totalCurrentSeasonBuyouts + $buyoutValue;
        $buyoutLimit = League::HARD_CAP_MAX * Team::BUYOUT_PERCENTAGE_MAX;

        return $projectedTotalCurrentSeasonBuyouts <= $buyoutLimit;
    }

    /**
     * Convert player result array into Player objects.
     *
     * @param list<PlayerRow> $result
     * @return array<int, Player> Array of Player objects indexed by player ID
     */
    private function convertPlrResultIntoPlayerArray(array $result): array
    {
        $array = [];
        foreach ($result as $plrRow) {
            $playerID = (int) $plrRow['pid'];
            $array[$playerID] = Player::withPlrRow($this->db, $plrRow);
        }
        return $array;
    }
}
