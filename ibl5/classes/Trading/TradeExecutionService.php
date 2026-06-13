<?php

declare(strict_types=1);

namespace Trading;

use Trading\Contracts\TradeExecutionServiceInterface;
use Trading\Contracts\TradeOfferRepositoryInterface;
use Trading\Contracts\TradeProcessorInterface;
use Trading\Contracts\TradeValidatorInterface;
use Trading\Contracts\TradeCashRepositoryInterface;
use Repositories\Contracts\SalaryCapRepositoryInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

/**
 * TradeExecutionService - Policy/authz/validation layer for accepting trades.
 *
 * The orchestrator that sits between the controller and the low-level
 * {@see TradeProcessor}. It owns the accept path's policy: who may execute an
 * offer (authz/IDOR gate) and whether the offer is legal for *every* party's
 * resulting roster (N-party cap + roster validation). The actual writes stay in
 * TradeProcessor::processTrade(), which owns the transaction boundary.
 *
 * The `Trade*` prefix (not `Trading*`) is correct per Trading/README.md: this is
 * a single-trade-scoped domain object, not a module-level entry point.
 *
 * @phpstan-import-type TradeInfoRow from \Trading\Contracts\TradeOfferRepositoryInterface
 */
class TradeExecutionService implements TradeExecutionServiceInterface
{
    public function __construct(
        private readonly TradeOfferRepositoryInterface $offerRepository,
        private readonly TradeProcessorInterface $processor,
        private readonly TradeValidatorInterface $validator,
        private readonly SalaryCapRepositoryInterface $salaryCapRepository,
        private readonly TeamIdentityRepositoryInterface $teamIdentityRepository,
        private readonly TradeCashRepositoryInterface $cashRepository,
    ) {
    }

    /**
     * @see TradeExecutionServiceInterface::deriveParties()
     */
    public function deriveParties(int $offerId): array
    {
        $rows = $this->offerRepository->getTradesByOfferId($offerId);

        $parties = [];
        foreach ($rows as $row) {
            foreach ([$row['trade_from'], $row['trade_to']] as $team) {
                if (!in_array($team, $parties, true)) {
                    $parties[] = $team;
                }
            }
        }

        return $parties;
    }

    /**
     * @see TradeExecutionServiceInterface::assertActingTeamIsParty()
     */
    public function assertActingTeamIsParty(int $offerId, string $actingTeam): bool
    {
        if ($actingTeam === '') {
            return false;
        }

        return in_array($actingTeam, $this->deriveParties($offerId), true);
    }

    /**
     * @see TradeExecutionServiceInterface::validateAndExecute()
     */
    public function validateAndExecute(int $offerId, string $actingTeam): array
    {
        // Authz / IDOR gate FIRST — the acting GM's team must be a party.
        if (!$this->assertActingTeamIsParty($offerId, $actingTeam)) {
            return ['success' => false, 'error' => 'You are not authorized to act on this trade.'];
        }

        $rows = $this->offerRepository->getTradesByOfferId($offerId);
        if ($rows === []) {
            return ['success' => false, 'error' => 'No trade data found for this offer ID'];
        }

        $capValidation = $this->validateParties($offerId, $rows);
        if ($capValidation['valid'] !== true) {
            return ['success' => false, 'errors' => $capValidation['errors']];
        }

        // Validation passed — delegate the atomic write. processTrade() owns the
        // begin_transaction/commit/rollback boundary; this service never opens its
        // own transaction (its reads above are read-only).
        return $this->processor->processTrade($offerId);
    }

    /**
     * Build per-party cap + roster deltas from the offer's trade_info rows and
     * run both N-party validators. Read-only.
     *
     * @param list<TradeInfoRow> $rows
     * @return array{valid: bool, errors: list<string>}
     */
    private function validateParties(int $offerId, array $rows): array
    {
        $parties = $this->deriveParties($offerId);

        // Player cap basis: each player's vw_current_salary.current_salary, the
        // SAME basis getTeamTotalSalary() sums, so post = current - sent + received
        // stays internally consistent.
        $capSent = array_fill_keys($parties, 0);
        $capReceived = array_fill_keys($parties, 0);
        $playersSent = array_fill_keys($parties, 0);
        $playersReceived = array_fill_keys($parties, 0);

        foreach ($rows as $row) {
            $from = $row['trade_from'];
            $to = $row['trade_to'];

            if ($row['itemtype'] === TradeItemType::Player->value) {
                $salary = $this->salaryCapRepository->getPlayerCurrentSalary((int) $row['itemid']);
                $capSent[$from] += $salary;
                $capReceived[$to] += $salary;
                $playersSent[$from]++;
                $playersReceived[$to]++;
            } elseif ($row['itemtype'] === TradeItemType::Cash->value) {
                // League cap convention (mirrors TradeOffer::calculateSalaryCapData):
                // cash you SEND adds to your cap, cash you RECEIVE subtracts. Expressed
                // in the post = current - sent + received model that means the sender's
                // cap rises (capReceived) and the receiver's cap falls (capSent).
                $cash = $this->currentSeasonCashForLeg($offerId, $from);
                $capReceived[$from] += $cash;
                $capSent[$to] += $cash;
            }
            // Draft picks carry no salary-cap or roster-count impact.
        }

        $capDeltas = [];
        $rosterDeltas = [];
        foreach ($parties as $party) {
            $capDeltas[] = [
                'teamName' => $party,
                'currentSeasonCapTotal' => $this->salaryCapRepository->getTeamTotalSalary($party),
                'capSent' => $capSent[$party],
                'capReceived' => $capReceived[$party],
            ];
            $rosterDeltas[] = [
                'teamId' => $this->teamIdentityRepository->getTidFromTeamname($party) ?? 0,
                'teamName' => $party,
                'playersSent' => $playersSent[$party],
                'playersReceived' => $playersReceived[$party],
            ];
        }

        $capResult = $this->validator->validateSalaryCapsForParties($capDeltas);
        $rosterResult = $this->validator->validateRosterLimitsForParties($rosterDeltas);

        $errors = array_merge($capResult['errors'], $rosterResult['errors']);

        return ['valid' => $errors === [], 'errors' => $errors];
    }

    /**
     * Current-season cash amount for one (offer, sending team) cash leg.
     *
     * Uses salary_yr1 to match the vw_current_salary basis used by
     * getTeamTotalSalary()/getPlayerCurrentSalary() (cy=1 -> salary_yr1),
     * keeping the cap math on a single consistent basis.
     */
    private function currentSeasonCashForLeg(int $offerId, string $sendingTeam): int
    {
        $cashRow = $this->cashRepository->getCashTransactionByOffer($offerId, $sendingTeam);
        if ($cashRow === null) {
            return 0;
        }

        return (int) ($cashRow['salary_yr1'] ?? 0);
    }
}
