<?php

declare(strict_types=1);

namespace Team;

use League\League;
use Player\Contract\PlayerContractCalculator;
use Player\PlayerDataConverter;
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
 * phase-aware salary aggregation over player rows. The repository keeps only
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
    private ?Season $season = null;
    private ?PlayerContractCalculator $contractCalculator = null;

    /**
     * @param \mysqli $db Database connection
     * @param TeamQueryRepositoryInterface|null $teamQueryRepo Team query repository (created internally if not provided)
     * @param BuyoutLedgerRepositoryInterface|null $cashConsiderationRepo Cash consideration repository (created internally if not provided)
     * @param Season|null $season Current season (used for phase-aware salary aggregation; when null, a lazy Season is built on first use)
     */
    public function __construct(
        \mysqli $db,
        ?TeamQueryRepositoryInterface $teamQueryRepo = null,
        ?BuyoutLedgerRepositoryInterface $cashConsiderationRepo = null,
        ?Season $season = null
    ) {
        $this->db = $db;
        $this->teamQueryRepo = $teamQueryRepo ?? new TeamQueryRepository($db);
        $this->cashConsiderationRepo = $cashConsiderationRepo ?? new BuyoutLedgerRepository($db);
        $this->season = $season;
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
        $total = 0;
        foreach ($result as $row) {
            $total += $this->contractCalculator()->getCurrentSeasonSalary(PlayerDataConverter::arrayToPlayerData($row));
        }
        return $total;
    }

    /**
     * @see TeamCapCalculatorInterface::getTotalNextSeasonSalaries()
     *
     * @param list<array<string, mixed>> $result
     */
    public function getTotalNextSeasonSalaries(array $result): int
    {
        $total = 0;
        /** @var list<PlayerRow> $typedResult */
        $typedResult = $result;
        foreach ($typedResult as $row) {
            $total += $this->contractCalculator()->getNextSeasonSalary(PlayerDataConverter::arrayToPlayerData($row));
        }
        return $total;
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
     * Return the memoized phase-aware contract calculator, building it on first
     * call. The Season is resolved lazily so that callers that never touch a
     * salary aggregate pay no settings-query cost.
     */
    private function contractCalculator(): PlayerContractCalculator
    {
        if ($this->contractCalculator === null) {
            $this->contractCalculator = new PlayerContractCalculator($this->season ?? new Season($this->db));
        }
        return $this->contractCalculator;
    }
}
