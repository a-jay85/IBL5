<?php

declare(strict_types=1);

namespace Trading;

use League\League;
use Trading\Contracts\TradeValidatorInterface;
use Trading\Contracts\TradeAssetRepositoryInterface;
use Trading\Contracts\TradeFormRepositoryInterface;
use Team\Team;
use Season\Season;

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
    protected \mysqli $db;
    protected TradeAssetRepositoryInterface $assetRepository;
    protected TradeFormRepositoryInterface $formRepository;
    protected \Shared\Contracts\SharedRepositoryInterface $sharedRepository;
    protected Season $season;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->assetRepository = new TradeAssetRepository($db);
        $this->formRepository = new TradeFormRepository($db);
        $this->sharedRepository = new \Shared\SharedRepository($db);
        $this->season = new Season($db);
    }

    /**
     * @see TradeValidatorInterface::validateMinimumCashAmounts()
     */
    public function validateMinimumCashAmounts(array $userSendsCash, array $partnerSendsCash): array
    {
        $filteredUserSendsCash = array_filter($userSendsCash, static fn (int $amount): bool => $amount !== 0);
        $filteredPartnerSendsCash = array_filter($partnerSendsCash, static fn (int $amount): bool => $amount !== 0);

        if ($filteredUserSendsCash !== [] && min($filteredUserSendsCash) < 100) {
            return [
                'valid' => false,
                'error' => 'This trade is illegal: the minimum amount of cash that your team can send in any one season is 100.'
            ];
        }

        if ($filteredPartnerSendsCash !== [] && min($filteredPartnerSendsCash) < 100) {
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

        if ($userPostTradeCapTotal > League::HARD_CAP_MAX) {
            $errors[] = 'This trade is illegal since it puts you over the hard cap.';
        }

        if ($partnerPostTradeCapTotal > League::HARD_CAP_MAX) {
            $errors[] = 'This trade is illegal since it puts other team over the hard cap.';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'userPostTradeCapTotal' => $userPostTradeCapTotal,
            'partnerPostTradeCapTotal' => $partnerPostTradeCapTotal
        ];
    }

    /**
     * @see TradeValidatorInterface::validateRosterLimits()
     */
    public function validateRosterLimits(
        int $userTeamId,
        int $partnerTeamId,
        int $userPlayersSent,
        int $partnerPlayersSent
    ): array {
        $isOffseason = $this->season->phase === "Playoffs"
            || $this->season->phase === "Draft"
            || $this->season->phase === "Free Agency";

        $userCurrentRoster = $this->formRepository->getTeamPlayerCount($userTeamId, $isOffseason);
        $partnerCurrentRoster = $this->formRepository->getTeamPlayerCount($partnerTeamId, $isOffseason);

        $userPostTradeRoster = $userCurrentRoster - $userPlayersSent + $partnerPlayersSent;
        $partnerPostTradeRoster = $partnerCurrentRoster - $partnerPlayersSent + $userPlayersSent;

        $errors = [];

        if ($userPostTradeRoster > Team::ROSTER_SPOTS_MAX) {
            $errors[] = 'This trade is illegal since it puts your team over the ' . Team::ROSTER_SPOTS_MAX . '-player roster limit.';
        }

        if ($partnerPostTradeRoster > Team::ROSTER_SPOTS_MAX) {
            $errors[] = 'This trade is illegal since it puts the other team over the ' . Team::ROSTER_SPOTS_MAX . '-player roster limit.';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * @see TradeValidatorInterface::canPlayerBeTraded()
     */
    public function canPlayerBeTraded(int $playerId): bool
    {
        $player = $this->assetRepository->getPlayerForTradeValidation($playerId);

        if ($player === null) {
            return false;
        }

        // Extract ordinal and cy from the associative array
        $ordinal = $player['ordinal'] ?? 99999;
        $cy = $player['cy'] ?? 0;

        // Player cannot be traded if they are waived (ordinal > JSB::WAIVERS_ORDINAL) or have 0 salary
        return $cy !== 0 && $ordinal <= \JSB::WAIVERS_ORDINAL;
    }

    /**
     * @see TradeValidatorInterface::getCurrentSeasonCashConsiderations()
     */
    public function getCurrentSeasonCashConsiderations(array $userSendsCash, array $partnerSendsCash): array
    {
        // If the current season phase shifts cap situations to next season, evaluate next season's cap limits.
        if (
            $this->season->phase === "Playoffs"
            || $this->season->phase === "Draft"
            || $this->season->phase === "Free Agency"
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