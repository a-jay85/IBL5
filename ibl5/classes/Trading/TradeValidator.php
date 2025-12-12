<?php

declare(strict_types=1);

namespace Trading;

use Trading\Contracts\TradeValidatorInterface;

/**
 * TradeValidator - Validates trade legality
 *
 * Validates trade legality including minimum cash amounts, salary cap
 * compliance, and player tradability status.
 * 
 * @see TradeValidatorInterface
 */
class TradeValidator implements TradeValidatorInterface
{
    protected $db;
    protected \Shared $sharedFunctions;
    protected \Season $season;

    public function __construct($db)
    {
        $this->db = $db;
        $this->sharedFunctions = new \Shared($db);
        $this->season = new \Season($db);
    }

    /**
     * @see TradeValidatorInterface::validateMinimumCashAmounts()
     */
    public function validateMinimumCashAmounts(array $userSendsCash, array $partnerSendsCash): array
    {
        $filteredUserSendsCash = array_filter($userSendsCash);
        $filteredPartnerSendsCash = array_filter($partnerSendsCash);

        if (!empty($filteredUserSendsCash) && min($filteredUserSendsCash) < 100) {
            return [
                'valid' => false,
                'error' => 'This trade is illegal: the minimum amount of cash that your team can send in any one season is 100.'
            ];
        }

        if (!empty($filteredPartnerSendsCash) && min($filteredPartnerSendsCash) < 100) {
            return [
                'valid' => false,
                'error' => 'This trade is illegal: the minimum amount of cash that the other team can send in any one season is 100.'
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * @see TradeValidatorInterface::validateSalaryCaps()
     */
    public function validateSalaryCaps(array $tradeData): array
    {
        $userCurrentSeasonCapTotal = $tradeData['userCurrentSeasonCapTotal'] ?? 0;
        $partnerCurrentSeasonCapTotal = $tradeData['partnerCurrentSeasonCapTotal'] ?? 0;
        $userCapSentToPartner = $tradeData['userCapSentToPartner'] ?? 0;
        $partnerCapSentToUser = $tradeData['partnerCapSentToUser'] ?? 0;

        $userPostTradeCapTotal = $userCurrentSeasonCapTotal - $userCapSentToPartner + $partnerCapSentToUser;
        $partnerPostTradeCapTotal = $partnerCurrentSeasonCapTotal - $partnerCapSentToUser + $userCapSentToPartner;

        $errors = [];

        if ($userPostTradeCapTotal > \League::HARD_CAP_MAX) {
            $errors[] = 'This trade is illegal since it puts you over the hard cap.';
        }

        if ($partnerPostTradeCapTotal > \League::HARD_CAP_MAX) {
            $errors[] = 'This trade is illegal since it puts other team over the hard cap.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'userPostTradeCapTotal' => $userPostTradeCapTotal,
            'partnerPostTradeCapTotal' => $partnerPostTradeCapTotal
        ];
    }

    /**
     * @see TradeValidatorInterface::canPlayerBeTraded()
     */
    public function canPlayerBeTraded(int $playerId): bool
    {
        $result = $this->db->sql_query("SELECT ordinal, cy FROM ibl_plr WHERE pid = $playerId");
        $player = $this->db->sql_fetchrow($result);

        if (!$player || !is_array($player) || count($player) < 2) {
            return false;
        }

        // Extract ordinal and cy from the indexed array
        $ordinal = isset($player[0]) ? (int) $player[0] : 99999; // Default to high ordinal if missing
        $cy = isset($player[1]) ? (int) $player[1] : 0; // Default to 0 salary if missing

        // Player cannot be traded if they are waived (ordinal > JSB::WAIVERS_ORDINAL) or have 0 salary
        return $cy != 0 && $ordinal <= \JSB::WAIVERS_ORDINAL;
    }

    /**
     * @see TradeValidatorInterface::getCurrentSeasonCashConsiderations()
     */
    public function getCurrentSeasonCashConsiderations(array $userSendsCash, array $partnerSendsCash): array
    {
        // If the current season phase shifts cap situations to next season, evaluate next season's cap limits.
        if (
            $this->season->phase == "Playoffs"
            || $this->season->phase == "Draft"
            || $this->season->phase == "Free Agency"
        ) {
            $cashSentToThemThisSeason = $userSendsCash[2] ?? 0;
            $cashSentToMeThisSeason = $partnerSendsCash[2] ?? 0;
        } else {
            $cashSentToThemThisSeason = $userSendsCash[1] ?? 0;
            $cashSentToMeThisSeason = $partnerSendsCash[1] ?? 0;
        }

        return [
            'cashSentToThem' => $cashSentToThemThisSeason,
            'cashSentToMe' => $cashSentToMeThisSeason
        ];
    }
}