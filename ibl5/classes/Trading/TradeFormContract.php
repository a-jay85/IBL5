<?php

declare(strict_types=1);

namespace Trading;

/**
 * Single source for the contract amount the trade offer form posts for a player row.
 *
 * Shared by TradeOfferFormView (renders the hidden `contract<k>` field) and the
 * trade suggestion validation filter (builds the same payload the form would POST).
 *
 * @phpstan-import-type TradingPlayerRow from Contracts\TradeFormRepositoryInterface
 */
final class TradeFormContract
{
    /**
     * @param TradingPlayerRow $row
     * @param bool $advancesContractYears True when the form shows next season's salary (offseason)
     */
    public static function amountFor(array $row, bool $advancesContractYears): int
    {
        $contractYear = $row['cy'] ?? 0;

        if ($advancesContractYears) {
            $contractYear++;
        }
        if ($contractYear === 0) {
            $contractYear = 1;
        }

        // Rows carry salary_yr1..salary_yr6 only, so a year past six misses and reads as 0.
        /** @var int $contractAmount */
        $contractAmount = $row["salary_yr{$contractYear}"] ?? 0;

        return $contractAmount;
    }
}
