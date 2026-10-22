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
 * skipped because suggestions never include cash. Form rows are read once per
 * team per request.
 *
 * @phpstan-import-type TradeFormData from \Trading\Contracts\TradeCapCalculatorInterface
 * @phpstan-import-type TradingPlayerRow from \Trading\Contracts\TradeFormRepositoryInterface
 * @phpstan-import-type TradingDraftPickRow from \Trading\Contracts\TradeFormRepositoryInterface
 */
class SuggestionValidationFilter
{
    public const string FAIL_FORM = 'not on the Trading form';
    public const string FAIL_CAP = 'over the hard cap';
    public const string FAIL_ROSTER = 'over the roster limit';
    public const string FAIL_PLAYER = 'player cannot be traded';

    /** @var array<int, list<TradingPlayerRow>> */
    private array $playerRows = [];

    /** @var array<int, list<TradingDraftPickRow>> */
    private array $pickRows = [];

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
            foreach ($this->playerRows[$tid] ??= $this->formRepo->getTeamPlayersForTrading($tid) as $row) {
                $index[$k] = (string) $row['pid'];
                $type[$k] = '1';
                $contract[$k] = (string) TradeFormContract::amountFor($row, $advances);
                $check[$k] = in_array($row['pid'], $pids, true) ? 'on' : null;
                $k++;
            }
            foreach ($this->pickRows[$tid] ??= $this->formRepo->getTeamDraftPicksForTrading($tid) as $row) {
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
     * Room under League::HARD_CAP_MAX this season, from the same cap math the
     * validator runs (form salaries plus cash records), with nothing ticked.
     */
    public function capRoom(string $teamName, int $tid): int
    {
        $empty = new TradeCandidate($tid, $tid, [], [], [], []);
        $capData = $this->capCalculator->calculateSalaryCapData(
            $this->buildTradeFormData($teamName, $teamName, $tid, $tid, $empty),
        );

        return \League\League::HARD_CAP_MAX - $capData['userCurrentSeasonCapTotal'];
    }

    /**
     * @param TradeFormData $tradeData
     */
    public function passes(array $tradeData, int $userTid, int $partnerTid, TradeCandidate $c): bool
    {
        return $this->rejection($tradeData, $userTid, $partnerTid, $c) === null;
    }

    /**
     * The first failed check (a FAIL_* label), or null when the deal is legal.
     * A deal whose assets are not all ticked on the form fails first: the
     * validator would otherwise judge a different deal than the one shown.
     *
     * @param TradeFormData $tradeData
     */
    public function rejection(array $tradeData, int $userTid, int $partnerTid, TradeCandidate $c): ?string
    {
        $ticked = count(array_filter($tradeData['check'], static fn (?string $v): bool => $v === 'on'));
        $assets = count($c->userPids) + count($c->userPickIds) + count($c->partnerPids) + count($c->partnerPickIds);
        if ($ticked !== $assets) {
            return self::FAIL_FORM;
        }

        $capData = $this->capCalculator->calculateSalaryCapData($tradeData);
        if (!$this->validator->validateSalaryCaps($capData)->isValid()) {
            return self::FAIL_CAP;
        }

        if (!$this->validator->validateRosterLimits($userTid, $partnerTid, count($c->userPids), count($c->partnerPids))->isValid()) {
            return self::FAIL_ROSTER;
        }

        foreach ([...$c->userPids, ...$c->partnerPids] as $pid) {
            if (!$this->validator->canPlayerBeTraded($pid)) {
                return self::FAIL_PLAYER;
            }
        }

        return null;
    }
}
