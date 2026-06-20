<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * TradeCapCalculatorInterface - Salary-cap math for a pending trade offer
 *
 * Owns the cap arithmetic extracted from {@see \Trading\TradeOffer}: it computes
 * each team's current-season cap total and the amounts each side sends/receives,
 * following the same calculator-collaborator shape as
 * {@see \Team\Contracts\TeamCapCalculatorInterface} and
 * {@see \FreeAgency\FreeAgencyCapCalculator} (ADR-0028).
 *
 * @phpstan-type TradeFormData array{offeringTeam: string, listeningTeam: string, switchCounter: int, fieldsCounter: int, check: array<int, string|null>, index: array<int, string>, type: array<int, string>, contract: array<int, string>, userSendsCash: array<int, int>, partnerSendsCash: array<int, int>}
 */
interface TradeCapCalculatorInterface
{
    /**
     * Calculate salary cap data for both teams in a trade offer.
     *
     * Returns each team's current-season cap total (contracts + cash records +
     * new cash considerations) and the amounts being sent between teams.
     * For self-trades (offeringTeam === listeningTeam), sent amounts are zeroed.
     *
     * @param TradeFormData $tradeData
     * @return array{userCurrentSeasonCapTotal: int, partnerCurrentSeasonCapTotal: int, userCapSentToPartner: int, partnerCapSentToUser: int}
     */
    public function calculateSalaryCapData(array $tradeData): array;
}
