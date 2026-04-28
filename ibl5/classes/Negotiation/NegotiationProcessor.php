<?php

declare(strict_types=1);

namespace Negotiation;

use Negotiation\Contracts\NegotiationDemandCalculatorInterface;
use Negotiation\Contracts\NegotiationProcessorInterface;
use Negotiation\Contracts\NegotiationRepositoryInterface;
use Player\Player;

/**
 * @see NegotiationProcessorInterface
 *
 * @phpstan-import-type TeamFactors from NegotiationDemandCalculatorInterface
 */
class NegotiationProcessor implements NegotiationProcessorInterface
{
    private \mysqli $db;
    private NegotiationRepositoryInterface $repository;
    private NegotiationValidator $validator;
    private NegotiationDemandCalculator $demandCalculator;

    public function __construct(\mysqli $mysqli_db, ?\Season\Season $season = null)
    {
        $this->db = $mysqli_db;
        $this->repository = new NegotiationRepository($mysqli_db);
        $this->validator = new NegotiationValidator($mysqli_db, $season);
        $this->demandCalculator = new NegotiationDemandCalculator($mysqli_db);
    }
    
    /**
     * @see NegotiationProcessorInterface::processNegotiation()
     */
    public function processNegotiation(int $playerID, string $userTeamName, string $prefix, bool $bypassOwnership = false): string
    {
        try {
            $player = Player::withPlayerID($this->db, $playerID);
        } catch (\Exception $e) {
            return NegotiationViewHelper::renderError('Player not found.');
        } catch (\TypeError $e) {
            return NegotiationViewHelper::renderError('Player not found.');
        }

        $output = NegotiationViewHelper::renderHeader($player);

        $freeAgencyValidation = $this->validator->validateFreeAgencyNotActive();
        if (!$freeAgencyValidation->isValid()) {
            return $output . NegotiationViewHelper::renderError($freeAgencyValidation->getError() ?? '');
        }

        if ($bypassOwnership) {
            $eligibilityValidation = $this->validator->validateRenegotiationEligibility($player);
        } else {
            $eligibilityValidation = $this->validator->validateNegotiationEligibility($player, $userTeamName);
        }
        if (!$eligibilityValidation->isValid()) {
            return $output . NegotiationViewHelper::renderError($eligibilityValidation->getError() ?? '');
        }

        $factorsTeamName = $bypassOwnership ? ($player->teamName ?? '') : $userTeamName;
        $teamFactors = $this->getTeamFactors($factorsTeamName, $player->position ?? '', $player->name ?? '');

        if ($bypassOwnership) {
            $breakdown = $this->demandCalculator->calculateDemandsWithBreakdown($player, $teamFactors);
            $output .= NegotiationDemandsBreakdownView::render($breakdown);
            return $output;
        }

        $demands = $this->demandCalculator->calculateDemands($player, $teamFactors);
        $capSpace = $this->repository->getTeamCapSpaceNextSeason($userTeamName);
        $maxYearOneSalary = \ContractRules::getMaxContractSalary($player->yearsOfExperience ?? 0);

        $output .= NegotiationViewHelper::renderNegotiationForm(
            $player,
            $demands,
            $capSpace,
            $maxYearOneSalary
        );

        return $output;
    }
    
    /**
     * Get team factors for demand calculation
     *
     * @param string $teamName Team name
     * @param string $playerPosition Player position
     * @param string $playerName Player name (to exclude from position calculation)
     * @return TeamFactors Team factors
     */
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
