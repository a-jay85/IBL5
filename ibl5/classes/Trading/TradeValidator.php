<?php

declare(strict_types=1);

namespace Trading;

use League\League;
use Trading\Contracts\TradeValidatorInterface;
use Trading\Contracts\TradeAssetRepositoryInterface;
use Trading\Contracts\TradeFormRepositoryInterface;
use Team\Team;
use Season\Season;
use Validation\ValidationError;
use Validation\ValidationResult;
use Validation\ValidationResultWithContext;

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
    protected Season $season;

    public function __construct(\mysqli $db, ?Season $season = null)
    {
        $this->db = $db;
        $this->assetRepository = new TradeAssetRepository($db);
        $this->formRepository = new TradeFormRepository($db);
        $this->season = $season ?? new Season($db);
    }

    /**
     * @see TradeValidatorInterface::validateMinimumCashAmounts()
     */
    public function validateMinimumCashAmounts(array $userSendsCash, array $partnerSendsCash): ValidationResult
    {
        $filteredUserSendsCash = array_filter($userSendsCash, static fn (int $amount): bool => $amount !== 0);
        $filteredPartnerSendsCash = array_filter($partnerSendsCash, static fn (int $amount): bool => $amount !== 0);

        if ($filteredUserSendsCash !== [] && min($filteredUserSendsCash) < 100) {
            return ValidationResult::failure('This trade is illegal: the minimum amount of cash that your team can send in any one season is 100.');
        }

        if ($filteredPartnerSendsCash !== [] && min($filteredPartnerSendsCash) < 100) {
            return ValidationResult::failure('This trade is illegal: the minimum amount of cash that the other team can send in any one season is 100.');
        }

        return ValidationResult::success();
    }

    /**
     * @see TradeValidatorInterface::validateSalaryCaps()
     *
     * @return ValidationResultWithContext<array{userPostTradeCapTotal: int, partnerPostTradeCapTotal: int}>
     */
    public function validateSalaryCaps(array $tradeData): ValidationResultWithContext
    {
        $userCurrentSeasonCapTotal = $tradeData['userCurrentSeasonCapTotal'] ?? 0;
        $partnerCurrentSeasonCapTotal = $tradeData['partnerCurrentSeasonCapTotal'] ?? 0;
        $userCapSentToPartner = $tradeData['userCapSentToPartner'] ?? 0;
        $partnerCapSentToUser = $tradeData['partnerCapSentToUser'] ?? 0;

        // Delegate the cap math to the N-party method with a 2-element list so the
        // bilateral and N-party paths share a single implementation (green-green).
        // The user team sends $userCapSentToPartner and receives $partnerCapSentToUser;
        // the partner team is the mirror image.
        $result = $this->validateSalaryCapsForParties([
            [
                'teamName' => 'user',
                'currentSeasonCapTotal' => $userCurrentSeasonCapTotal,
                'capSent' => $userCapSentToPartner,
                'capReceived' => $partnerCapSentToUser,
            ],
            [
                'teamName' => 'partner',
                'currentSeasonCapTotal' => $partnerCurrentSeasonCapTotal,
                'capSent' => $partnerCapSentToUser,
                'capReceived' => $userCapSentToPartner,
            ],
        ]);

        // Reshape to the legacy two-party contract with the exact legacy error
        // strings (the N-party method emits team-name-generalized messages).
        $parties = $result->getContext();
        /** @var list<ValidationError> $errors */
        $errors = [];
        if ($parties[0]['overCap']) {
            $errors[] = new ValidationError('salary_cap', 'This trade is illegal since it puts you over the hard cap.');
        }
        if ($parties[1]['overCap']) {
            $errors[] = new ValidationError('salary_cap', 'This trade is illegal since it puts other team over the hard cap.');
        }

        return ValidationResultWithContext::fromErrors($errors, [
            'userPostTradeCapTotal' => $parties[0]['postTradeCapTotal'],
            'partnerPostTradeCapTotal' => $parties[1]['postTradeCapTotal'],
        ]);
    }

    /**
     * @see TradeValidatorInterface::validateSalaryCapsForParties()
     *
     * @param list<array{teamName: string, currentSeasonCapTotal: int, capSent: int, capReceived: int}> $partyCapDeltas
     * @return ValidationResultWithContext<list<array{teamName: string, postTradeCapTotal: int, overCap: bool}>>
     */
    public function validateSalaryCapsForParties(array $partyCapDeltas): ValidationResultWithContext
    {
        /** @var list<ValidationError> $errors */
        $errors = [];
        $parties = [];

        foreach ($partyCapDeltas as $party) {
            $postTradeCapTotal = $party['currentSeasonCapTotal'] - $party['capSent'] + $party['capReceived'];
            $overCap = $postTradeCapTotal > League::HARD_CAP_MAX;

            if ($overCap) {
                $errors[] = new ValidationError('salary_cap', 'This trade is illegal since it puts the ' . $party['teamName'] . ' over the hard cap.');
            }

            $parties[] = [
                'teamName' => $party['teamName'],
                'postTradeCapTotal' => $postTradeCapTotal,
                'overCap' => $overCap,
            ];
        }

        return ValidationResultWithContext::fromErrors($errors, $parties);
    }

    /**
     * @see TradeValidatorInterface::validateRosterLimits()
     */
    public function validateRosterLimits(
        int $userTeamId,
        int $partnerTeamId,
        int $userPlayersSent,
        int $partnerPlayersSent
    ): ValidationResult {
        // Delegate to the N-party method with a 2-element list so the bilateral and
        // N-party roster paths share a single implementation (green-green). The
        // user team sends $userPlayersSent and receives $partnerPlayersSent.
        $result = $this->validateRosterLimitsForParties([
            [
                'teamId' => $userTeamId,
                'teamName' => 'user',
                'playersSent' => $userPlayersSent,
                'playersReceived' => $partnerPlayersSent,
            ],
            [
                'teamId' => $partnerTeamId,
                'teamName' => 'partner',
                'playersSent' => $partnerPlayersSent,
                'playersReceived' => $userPlayersSent,
            ],
        ]);

        // Reshape to the legacy two-party contract with the exact legacy error
        // strings (the N-party method emits team-name-generalized messages).
        $parties = $result->getContext();
        $errors = [];
        if ($parties[0]['overLimit']) {
            $errors[] = 'This trade is illegal since it puts your team over the ' . Team::ROSTER_SPOTS_MAX . '-player roster limit.';
        }
        if ($parties[1]['overLimit']) {
            $errors[] = 'This trade is illegal since it puts the other team over the ' . Team::ROSTER_SPOTS_MAX . '-player roster limit.';
        }

        return ValidationResult::failures($errors);
    }

    /**
     * @see TradeValidatorInterface::validateRosterLimitsForParties()
     *
     * @param list<array{teamId: int, teamName: string, playersSent: int, playersReceived: int}> $partyRosterDeltas
     * @return ValidationResultWithContext<list<array{teamName: string, postTradeRoster: int, overLimit: bool}>>
     */
    public function validateRosterLimitsForParties(array $partyRosterDeltas): ValidationResultWithContext
    {
        $isOffseason = $this->season->advancesContractYears();

        /** @var list<ValidationError> $errors */
        $errors = [];
        $parties = [];

        foreach ($partyRosterDeltas as $party) {
            $currentRoster = $this->formRepository->getTeamPlayerCount($party['teamId'], $isOffseason);
            $postTradeRoster = $currentRoster - $party['playersSent'] + $party['playersReceived'];
            $overLimit = $postTradeRoster > Team::ROSTER_SPOTS_MAX;

            if ($overLimit) {
                $errors[] = new ValidationError('roster_limit', 'This trade is illegal since it puts the ' . $party['teamName'] . ' over the ' . Team::ROSTER_SPOTS_MAX . '-player roster limit.');
            }

            $parties[] = [
                'teamName' => $party['teamName'],
                'postTradeRoster' => $postTradeRoster,
                'overLimit' => $overLimit,
            ];
        }

        return ValidationResultWithContext::fromErrors($errors, $parties);
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

        // Player cannot be traded if they are waived (ordinal > JsbConstants::WAIVERS_ORDINAL) or have 0 salary
        return $cy !== 0 && $ordinal <= \League\JsbConstants::WAIVERS_ORDINAL;
    }

    /**
     * @see TradeValidatorInterface::getCurrentSeasonCashConsiderations()
     */
    public function getCurrentSeasonCashConsiderations(array $userSendsCash, array $partnerSendsCash): array
    {
        // If the current season phase shifts cap situations to next season, evaluate next season's cap limits.
        if ($this->season->advancesContractYears()) {
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