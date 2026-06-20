<?php

declare(strict_types=1);

namespace Trading;

use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Trading\Contracts\BuyoutLedgerRepositoryInterface;
use Trading\Contracts\TradeCapCalculatorInterface;
use Season\Season;

/**
 * TradeCapCalculator - Salary-cap arithmetic for trade offers
 *
 * Holds the cap math extracted from {@see TradeOffer::calculateSalaryCapData()}
 * and {@see TradeOffer::sumCashRecordSalaries()}, following the established
 * calculator-collaborator pattern of {@see \Team\TeamCapCalculator} and
 * {@see \FreeAgency\FreeAgencyCapCalculator} (ADR-0028).
 *
 * @phpstan-import-type TradeFormData from TradeCapCalculatorInterface
 *
 * @see TradeCapCalculatorInterface
 */
class TradeCapCalculator implements TradeCapCalculatorInterface
{
    private TeamIdentityRepositoryInterface $commonRepository;
    private BuyoutLedgerRepositoryInterface $cashConsiderationRepository;
    private Season $season;
    private TradeValidator $validator;

    /**
     * @param TeamIdentityRepositoryInterface $commonRepository Team identity repository (required)
     * @param BuyoutLedgerRepositoryInterface|null $cashConsiderationRepository Cash consideration repository (created from $db if not provided)
     * @param Season|null $season Season entity (created from $db if not provided)
     * @param TradeValidator|null $validator Trade validator (created from $db if not provided)
     * @param \mysqli|null $db Database connection — only needed when any of the above is null
     */
    public function __construct(
        TeamIdentityRepositoryInterface $commonRepository,
        ?BuyoutLedgerRepositoryInterface $cashConsiderationRepository = null,
        ?Season $season = null,
        ?TradeValidator $validator = null,
        ?\mysqli $db = null
    ) {
        $this->commonRepository = $commonRepository;
        // TradeOffer always injects all four collaborators; $db is only needed for standalone fallback-construct.
        $this->cashConsiderationRepository = $cashConsiderationRepository
            ?? ($db !== null ? new BuyoutLedgerRepository($db) : throw new \LogicException('$db required when $cashConsiderationRepository is not provided'));
        $this->season = $season
            ?? ($db !== null ? new Season($db) : throw new \LogicException('$db required when $season is not provided'));
        $this->validator = $validator
            ?? ($db !== null ? new TradeValidator($db) : throw new \LogicException('$db required when $validator is not provided'));
    }

    /**
     * @see TradeCapCalculatorInterface::calculateSalaryCapData()
     *
     * @param TradeFormData $tradeData
     * @return array{userCurrentSeasonCapTotal: int, partnerCurrentSeasonCapTotal: int, userCapSentToPartner: int, partnerCapSentToUser: int}
     */
    public function calculateSalaryCapData(array $tradeData): array
    {
        $userCurrentSeasonCapTotal = 0;
        $partnerCurrentSeasonCapTotal = 0;
        $userCapSentToPartner = 0;
        $partnerCapSentToUser = 0;

        // Calculate user team salary data from form
        $switchCounter = $tradeData['switchCounter'];
        for ($j = 0; $j < $switchCounter; $j++) {
            $isChecked = $tradeData['check'][$j] ?? null;
            $salaryAmount = (int) ($tradeData['contract'][$j] ?? '0');
            $userCurrentSeasonCapTotal += $salaryAmount;

            if ($isChecked === "on") {
                $userCapSentToPartner += $salaryAmount;
            }
        }

        // Calculate partner team salary data from form
        $fieldsCounter = $tradeData['fieldsCounter'];
        for ($j = $switchCounter; $j < $fieldsCounter; $j++) {
            $isChecked = $tradeData['check'][$j] ?? null;
            $salaryAmount = (int) ($tradeData['contract'][$j] ?? '0');
            $partnerCurrentSeasonCapTotal += $salaryAmount;

            if ($isChecked === "on") {
                $partnerCapSentToUser += $salaryAmount;
            }
        }

        // Include existing cash transaction records (e.g. "Cash from Heat")
        // which are stored as player records with names starting with '|'
        // but excluded from the form's hidden contract fields
        $userTeamId = $this->commonRepository->getTidFromTeamname($tradeData['offeringTeam']) ?? 0;
        $partnerTeamId = $this->commonRepository->getTidFromTeamname($tradeData['listeningTeam']) ?? 0;

        $userCurrentSeasonCapTotal += $this->sumCashRecordSalaries($userTeamId);
        $partnerCurrentSeasonCapTotal += $this->sumCashRecordSalaries($partnerTeamId);

        // Add new cash considerations from this trade offer
        $cashConsiderations = $this->validator->getCurrentSeasonCashConsiderations(
            $tradeData['userSendsCash'],
            $tradeData['partnerSendsCash']
        );

        $userCurrentSeasonCapTotal += $cashConsiderations['cashSentToThem'];
        $userCurrentSeasonCapTotal -= $cashConsiderations['cashSentToMe'];
        $partnerCurrentSeasonCapTotal += $cashConsiderations['cashSentToMe'];
        $partnerCurrentSeasonCapTotal -= $cashConsiderations['cashSentToThem'];

        // Self-trade: both sides are the same team, so no players actually move.
        // Zero out sent/received to avoid double-counting.
        if ($tradeData['offeringTeam'] === $tradeData['listeningTeam']) {
            $userCapSentToPartner = 0;
            $partnerCapSentToUser = 0;
        }

        return [
            'userCurrentSeasonCapTotal' => $userCurrentSeasonCapTotal,
            'partnerCurrentSeasonCapTotal' => $partnerCurrentSeasonCapTotal,
            'userCapSentToPartner' => $userCapSentToPartner,
            'partnerCapSentToUser' => $partnerCapSentToUser
        ];
    }

    /**
     * Sum current-season salary from cash consideration records for a team.
     *
     * Cash entries (trades, buyouts) are excluded from form hidden fields
     * but still affect the team's salary cap. This computes their current-year
     * impact using the same contract-year logic as the form.
     *
     * Playoffs counts as offseason for trade cap math (contract years have
     * effectively rolled over), unlike the FA cap calculation.
     *
     * @param int $teamId Team ID
     * @return int Sum of cash record salaries for the current season (may be negative)
     */
    private function sumCashRecordSalaries(int $teamId): int
    {
        $cashRecords = $this->cashConsiderationRepository->getTeamCashForSalary($teamId);

        $isOffseason = $this->season->advancesContractYears();

        return BuyoutLedgerRepository::sumCurrentSeasonSalaryFromRows($cashRecords, $isOffseason);
    }
}
