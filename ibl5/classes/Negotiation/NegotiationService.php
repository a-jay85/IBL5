<?php

declare(strict_types=1);

namespace Negotiation;

use Negotiation\Contracts\NegotiationDemandCalculatorInterface;
use Negotiation\Contracts\NegotiationRepositoryInterface;
use Negotiation\Contracts\NegotiationServiceInterface;
use Negotiation\Contracts\NegotiationValidatorInterface;
use Player\Player;

/**
 * @see NegotiationServiceInterface
 *
 * @phpstan-import-type TeamFactors from NegotiationDemandCalculatorInterface
 */
class NegotiationService implements NegotiationServiceInterface
{
    public function __construct(
        private readonly \mysqli $db,
        private readonly NegotiationRepositoryInterface $repository,
        private readonly NegotiationValidatorInterface $validator,
        private readonly NegotiationDemandCalculatorInterface $demandCalculator,
    ) {}
    
    public function processNegotiation(int $playerID, string $userTeamName, string $prefix, bool $bypassOwnership = false): string
    {
        try {
            $player = Player::withPlayerID($this->db, $playerID);
        } catch (\Exception|\TypeError $e) {
            return NegotiationOfferView::renderError('Player not found.');
        }

        $output = NegotiationOfferView::renderHeader($player);

        $freeAgencyValidation = $this->validator->validateFreeAgencyNotActive();
        if (!$freeAgencyValidation->isValid()) {
            return $output . NegotiationOfferView::renderError($freeAgencyValidation->getError() ?? '');
        }

        if ($bypassOwnership) {
            $eligibilityValidation = $this->validator->validateRenegotiationEligibility($player);
        } else {
            $eligibilityValidation = $this->validator->validateNegotiationEligibility($player, $userTeamName);
        }
        if (!$eligibilityValidation->isValid()) {
            return $output . NegotiationOfferView::renderError($eligibilityValidation->getError() ?? '');
        }

        $factorsTeamName = $bypassOwnership ? ($player->getTeamName() ?? '') : $userTeamName;
        $teamFactors = $this->getTeamFactors($factorsTeamName, $player->getPosition() ?? '', $player->getName() ?? '');

        if ($bypassOwnership) {
            $breakdown = $this->demandCalculator->calculateDemandsWithBreakdown($player, $teamFactors);
            $output .= NegotiationDemandsBreakdownView::render($breakdown);
            return $output;
        }

        $demands = $this->demandCalculator->calculateDemands($player, $teamFactors);
        $capSpace = $this->repository->getTeamCapSpaceNextSeason($userTeamName);
        $maxYearOneSalary = \ContractRules::getMaxContractSalary($player->getYearsOfExperience() ?? 0);

        // Trading card (mirrors PlayerPageController::renderPage assembly)
        $playerRepository = new \Player\PlayerRepository($this->db);
        $playerName = $player->getName() ?? '';
        $asg = $playerRepository->getAllStarGameCount($playerName);
        $threepointcontests = $playerRepository->getThreePointContestCount($playerName);
        $dunkcontests = $playerRepository->getDunkContestCount($playerName);
        $rooksoph = $playerRepository->getRookieSophChallengeCount($playerName);
        $playerStats = \Player\Stats\PlayerStats::withPlayerID($this->db, $playerID);
        $contractDisplay = implode('/', $player->getRemainingContractArray());
        $cardHtml = \Player\Views\PlayerTradingCardFlipView::render(
            $player,
            $playerStats,
            $playerID,
            $contractDisplay,
            $asg,
            $threepointcontests,
            $dunkcontests,
            $rooksoph,
            $this->db
        );

        $output .= NegotiationOfferView::renderNegotiationForm(
            $player,
            $demands,
            $capSpace,
            $maxYearOneSalary,
            $cardHtml
        );

        // Flip card script (must come after card HTML so elements exist for init)
        $output .= \Player\Views\PlayerTradingCardFlipView::getFlipStyles();

        return $output;
    }
    
    /** @return TeamFactors */
    private function getTeamFactors(string $teamName, string $playerPosition, string $playerName): array
    {
        $teamData = $this->repository->getTeamPerformance($teamName);
        $moneyCommitted = $this->repository->getPositionSalaryCommitment($teamName, $playerPosition, $playerName);
        
        return [
            'wins' => $teamData['contract_wins'],
            'losses' => $teamData['contract_losses'],
            'tradition_wins' => $teamData['contract_avg_w'],
            'tradition_losses' => $teamData['contract_avg_l'],
            'money_committed_at_position' => $moneyCommitted
        ];
    }
    
}
