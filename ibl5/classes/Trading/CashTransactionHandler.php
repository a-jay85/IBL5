<?php

declare(strict_types=1);

namespace Trading;

use Trading\Contracts\CashTransactionHandlerInterface;
use Trading\Contracts\CashConsiderationRepositoryInterface;
use Trading\Contracts\TradeCashRepositoryInterface;

/**
 * CashTransactionHandler - Handles cash considerations in trades
 *
 * Manages the creation of cash entries in trades, inserting paired
 * positive/negative records into ibl_cash_considerations.
 *
 * @see CashTransactionHandlerInterface
 */
class CashTransactionHandler implements CashTransactionHandlerInterface
{
    protected \mysqli $db;
    protected CashConsiderationRepositoryInterface $cashConsiderationRepository;
    protected TradeCashRepositoryInterface $cashRepository;
    protected \Services\CommonMysqliRepository $commonRepository;

    public function __construct(
        \mysqli $db,
        ?CashConsiderationRepositoryInterface $cashConsiderationRepository = null,
        ?TradeCashRepositoryInterface $cashRepository = null
    ) {
        $this->db = $db;
        $this->cashConsiderationRepository = $cashConsiderationRepository ?? new CashConsiderationRepository($db);
        $this->cashRepository = $cashRepository ?? new TradeCashRepository($db);
        $this->commonRepository = new \Services\CommonMysqliRepository($db);
    }

    /**
     * @see CashTransactionHandlerInterface::calculateContractTotalYears()
     */
    public function calculateContractTotalYears(array $cashYear): int
    {
        if (($cashYear[6] ?? 0) !== 0) {
            return 6;
        } elseif (($cashYear[5] ?? 0) !== 0) {
            return 5;
        } elseif (($cashYear[4] ?? 0) !== 0) {
            return 4;
        } elseif (($cashYear[3] ?? 0) !== 0) {
            return 3;
        } elseif (($cashYear[2] ?? 0) !== 0) {
            return 2;
        } else {
            return 1;
        }
    }

    /**
     * @see CashTransactionHandlerInterface::formatCashTradeText()
     */
    public static function formatCashTradeText(array $cashYear, string $from, string $to, int $seasonEndingYear): string
    {
        $lines = '';
        for ($y = 1; $y <= 6; $y++) {
            $amount = $cashYear[$y] ?? 0;
            if ($amount === 0) {
                continue;
            }
            $startYear = $seasonEndingYear - 2 + $y;
            $endYear = $seasonEndingYear - 1 + $y;
            $lines .= "The {$from} send {$amount} in cash to the {$to} for {$startYear}-{$endYear}.<br>";
        }
        return $lines;
    }

    /**
     * @see CashTransactionHandlerInterface::createCashTransaction()
     */
    public function createCashTransaction(string $offeringTeamName, string $listeningTeamName, array $cashYear, int $seasonEndingYear, ?int $tradeOfferId = null): array
    {
        $offeringTeamId = $this->commonRepository->getTidFromTeamname($offeringTeamName) ?? 0;
        $listeningTeamId = $this->commonRepository->getTidFromTeamname($listeningTeamName) ?? 0;

        $cy1 = (int) ($cashYear[1] ?? 0);
        $cy2 = (int) ($cashYear[2] ?? 0);
        $cy3 = (int) ($cashYear[3] ?? 0);
        $cy4 = (int) ($cashYear[4] ?? 0);
        $cy5 = (int) ($cashYear[5] ?? 0);
        $cy6 = (int) ($cashYear[6] ?? 0);

        $contractCurrentYear = 1;
        $contractTotalYears = $this->calculateContractTotalYears($cashYear);

        // Insert positive cash row (sending team's cap hit)
        $affectedRowsPositive = $this->cashConsiderationRepository->insertCashConsideration([
            'teamid' => $offeringTeamId,
            'type' => 'cash',
            'label' => "Cash to $listeningTeamName",
            'counterparty_teamid' => $listeningTeamId,
            'trade_offer_id' => $tradeOfferId,
            'cy' => $contractCurrentYear,
            'cyt' => $contractTotalYears,
            'cy1' => $cy1,
            'cy2' => $cy2,
            'cy3' => $cy3,
            'cy4' => $cy4,
            'cy5' => $cy5,
            'cy6' => $cy6,
        ]);

        // Insert negative cash row (receiving team's cap relief)
        $affectedRowsNegative = $this->cashConsiderationRepository->insertCashConsideration([
            'teamid' => $listeningTeamId,
            'type' => 'cash',
            'label' => "Cash from $offeringTeamName",
            'counterparty_teamid' => $offeringTeamId,
            'trade_offer_id' => $tradeOfferId,
            'cy' => $contractCurrentYear,
            'cyt' => $contractTotalYears,
            'cy1' => -$cy1,
            'cy2' => -$cy2,
            'cy3' => -$cy3,
            'cy4' => -$cy4,
            'cy5' => -$cy5,
            'cy6' => -$cy6,
        ]);

        $success = ($affectedRowsPositive > 0) && ($affectedRowsNegative > 0);
        $tradeLine = self::formatCashTradeText($cashYear, $offeringTeamName, $listeningTeamName, $seasonEndingYear);

        return [
            'success' => $success,
            'tradeLine' => $tradeLine
        ];
    }

    /**
     * @see CashTransactionHandlerInterface::insertCashTradeData()
     */
    public function insertCashTradeData(int $tradeOfferId, string $offeringTeamName, string $listeningTeamName, array $cashAmounts): bool
    {
        $cy1 = (int) ($cashAmounts[1] ?? 0);
        $cy2 = (int) ($cashAmounts[2] ?? 0);
        $cy3 = (int) ($cashAmounts[3] ?? 0);
        $cy4 = (int) ($cashAmounts[4] ?? 0);
        $cy5 = (int) ($cashAmounts[5] ?? 0);
        $cy6 = (int) ($cashAmounts[6] ?? 0);

        $affectedRows = $this->cashRepository->insertCashTradeOffer(
            $tradeOfferId,
            $offeringTeamName,
            $listeningTeamName,
            $cy1,
            $cy2,
            $cy3,
            $cy4,
            $cy5,
            $cy6
        );

        return $affectedRows > 0;
    }

    /**
     * @see CashTransactionHandlerInterface::hasCashInTrade()
     */
    public function hasCashInTrade(array $cashAmounts): bool
    {
        foreach ($cashAmounts as $amount) {
            if ((int)$amount !== 0) {
                return true;
            }
        }
        return false;
    }
}
