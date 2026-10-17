<?php

declare(strict_types=1);

namespace TradeSuggestions;

use Trading\TradeFormContract;

/**
 * Runs a suggestion through the real trade validation before it is shown.
 *
 * Builds the exact form payload the pre-filled Trading screen would POST, then
 * makes the same validator calls as TradeOffer::createTradeOffer, in the same
 * order. No cap or roster rule is re-implemented here. Cash validation is
 * skipped because v1 suggestions never include cash.
 *
 * @phpstan-import-type TradeFormData from \Trading\Contracts\TradeCapCalculatorInterface
 */
class SuggestionValidationFilter
{
    public function __construct(
        private readonly \Trading\Contracts\TradeValidatorInterface $validator,
        private readonly \Trading\TradeCapCalculator $capCalculator,
        private readonly \Trading\Contracts\TradeFormRepositoryInterface $formRepo,
        private readonly \Season\Season $season,
    ) {
    }

    /**
     * Rows come from the same readers the Trading form renders from, in form
     * order: user players, user picks, partner players, partner picks.
     *
     * @return TradeFormData
     */
    public function buildTradeFormData(
        string $userTeamName,
        string $partnerTeamName,
        int $userTid,
        int $partnerTid,
        TradeCandidate $c,
    ): array {
        $advances = $this->season->advancesContractYears();

        /** @var array<int, string|null> $check */
        $check = [];
        /** @var array<int, string> $index */
        $index = [];
        /** @var array<int, string> $type */
        $type = [];
        /** @var array<int, string> $contract */
        $contract = [];
        $k = 0;

        $sides = [
            [$userTid, $c->userPids, $c->userPickIds],
            [$partnerTid, $c->partnerPids, $c->partnerPickIds],
        ];

        $switchCounter = 0;
        foreach ($sides as $sideIndex => [$tid, $pids, $pickIds]) {
            foreach ($this->formRepo->getTeamPlayersForTrading($tid) as $row) {
                $index[$k] = (string) $row['pid'];
                $type[$k] = '1';
                $contract[$k] = (string) TradeFormContract::amountFor($row, $advances);
                $check[$k] = in_array($row['pid'], $pids, true) ? 'on' : null;
                $k++;
            }
            foreach ($this->formRepo->getTeamDraftPicksForTrading($tid) as $row) {
                $index[$k] = (string) $row['pickid'];
                $type[$k] = '0';
                $contract[$k] = '0';
                $check[$k] = in_array($row['pickid'], $pickIds, true) ? 'on' : null;
                $k++;
            }
            if ($sideIndex === 0) {
                $switchCounter = $k;
            }
        }

        return [
            'offeringTeam' => $userTeamName,
            'listeningTeam' => $partnerTeamName,
            'switchCounter' => $switchCounter,
            'fieldsCounter' => $k,
            'check' => $check,
            'index' => $index,
            'type' => $type,
            'contract' => $contract,
            'userSendsCash' => [],
            'partnerSendsCash' => [],
        ];
    }

    /**
     * @param TradeFormData $tradeData
     */
    public function passes(array $tradeData, int $userTid, int $partnerTid, TradeCandidate $c): bool
    {
        $capData = $this->capCalculator->calculateSalaryCapData($tradeData);
        if (!$this->validator->validateSalaryCaps($capData)->isValid()) {
            return false;
        }

        if (!$this->validator->validateRosterLimits($userTid, $partnerTid, count($c->userPids), count($c->partnerPids))->isValid()) {
            return false;
        }

        foreach ([...$c->userPids, ...$c->partnerPids] as $pid) {
            if (!$this->validator->canPlayerBeTraded($pid)) {
                return false;
            }
        }

        return true;
    }
}
