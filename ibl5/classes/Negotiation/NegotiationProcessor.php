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

    public function __construct(\mysqli $mysqli_db)
    {
        $this->db = $mysqli_db;
        $this->repository = new NegotiationRepository($mysqli_db);
        $this->validator = new NegotiationValidator($mysqli_db);
        $this->demandCalculator = new NegotiationDemandCalculator($mysqli_db);
    }
    
    /**
     * @see NegotiationProcessorInterface::processNegotiation()
     */
    public function processNegotiation(int $playerID, string $userTeamName, string $prefix): string
    {
        // Load player using existing Player class
        try {
            $player = Player::withPlayerID($this->db, $playerID);
        } catch (\Exception $e) {
            return NegotiationViewHelper::renderError('Player not found.');
        } catch (\TypeError $e) {
            return NegotiationViewHelper::renderError('Player not found.');
        }
        
        // Render page header
        $output = NegotiationViewHelper::renderHeader($player);
        
        // Validate free agency is not active
        $freeAgencyValidation = $this->validator->validateFreeAgencyNotActive();
        if (!$freeAgencyValidation['valid']) {
            return $output . NegotiationViewHelper::renderError((string) ($freeAgencyValidation['error'] ?? ''));
        }

        // Validate negotiation eligibility
        $eligibilityValidation = $this->validator->validateNegotiationEligibility($player, $userTeamName);
        if (!$eligibilityValidation['valid']) {
            return $output . NegotiationViewHelper::renderError((string) ($eligibilityValidation['error'] ?? ''));
        }

        // Get team factors for demand calculation
        $teamFactors = $this->getTeamFactors($userTeamName, $player->position ?? '', $player->name ?? '');
        
        // Calculate contract demands
        $demands = $this->demandCalculator->calculateDemands($player, $teamFactors);
        
        // Calculate available cap space
        $capSpace = $this->repository->getTeamCapSpaceNextSeason($userTeamName);
        
        // Determine max first year salary based on experience
        $maxYearOneSalary = \ContractRules::getMaxContractSalary($player->yearsOfExperience ?? 0);
        
        // Render negotiation form
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
            'wins' => $teamData['Contract_Wins'],
            'losses' => $teamData['Contract_Losses'],
            'tradition_wins' => $teamData['Contract_AvgW'],
            'tradition_losses' => $teamData['Contract_AvgL'],
            'money_committed_at_position' => $moneyCommitted
        ];
    }
    
}
