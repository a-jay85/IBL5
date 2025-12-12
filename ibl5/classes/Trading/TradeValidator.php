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
    protected object $mysqli_db;
    protected TradingRepository $repository;
    protected \Shared $sharedFunctions;
    protected \Season $season;

    public function __construct($db, object $mysqli_db = null)
    {
        $this->db = $db;
        
        // Extract mysqli from provided parameter, or from legacy $db object, or fallback to global
        if ($mysqli_db !== null) {
            $this->mysqli_db = $mysqli_db;
        } else {
            // Try to extract from legacy $db object
            $this->mysqli_db = $db->db_connect_id ?? $db;
        }
        
        $this->repository = new TradingRepository($this->mysqli_db);
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
        $player = $this->repository->getPlayerForTradeValidation($playerId);

        if (!$player) {
            return false;
        }

        // Extract ordinal and cy from the associative array
        $ordinal = isset($player['ordinal']) ? (int) $player['ordinal'] : 99999;
        $cy = isset($player['cy']) ? (int) $player['cy'] : 0;

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