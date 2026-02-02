<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyServiceInterface;
use FreeAgency\Contracts\FreeAgencyRepositoryInterface;
use FreeAgency\Contracts\FreeAgencyDemandRepositoryInterface;
use Player\Player;

/**
 * @see FreeAgencyServiceInterface
 */
class FreeAgencyService implements FreeAgencyServiceInterface
{
    private FreeAgencyRepositoryInterface $repository;
    private FreeAgencyDemandRepositoryInterface $demandRepository;
    private object $mysqli_db;

    public function __construct(
        FreeAgencyRepositoryInterface $repository,
        FreeAgencyDemandRepositoryInterface $demandRepository,
        object $mysqli_db
    ) {
        $this->repository = $repository;
        $this->demandRepository = $demandRepository;
        $this->mysqli_db = $mysqli_db;
    }

    /**
     * @see FreeAgencyServiceInterface::getMainPageData()
     */
    public function getMainPageData(\Team $team, \Season $season): array
    {
        $capCalculator = new FreeAgencyCapCalculator($this->mysqli_db, $team, $season);
        $capMetrics = $capCalculator->calculateTeamCapMetrics();

        $allOtherPlayers = $this->repository->getAllPlayersExcludingTeam($team->name);

        return [
            'capMetrics' => $capMetrics,
            'team' => $team,
            'season' => $season,
            'allOtherPlayers' => $allOtherPlayers,
        ];
    }

    /**
     * @see FreeAgencyServiceInterface::getNegotiationData()
     */
    public function getNegotiationData(int $playerID, \Team $team, \Season $season): array
    {
        $player = Player::withPlayerID($this->mysqli_db, $playerID);

        $capCalculator = new FreeAgencyCapCalculator($this->mysqli_db, $team, $season);
        $capMetrics = $capCalculator->calculateTeamCapMetrics($player->name);

        $calculator = new FreeAgencyDemandCalculator($this->demandRepository);
        $demands = $calculator->getPlayerDemands($player->name);

        $existingOffer = $this->getExistingOffer($team->name, $player->name);

        $amendedCapSpace = $capMetrics['softCapSpace'][0] + $existingOffer['offer1'];
        $hasExistingOffer = $existingOffer['offer1'] > 0;

        $veteranMinimum = \ContractRules::getVeteranMinimumSalary($player->yearsOfExperience);
        $maxContract = \ContractRules::getMaxContractSalary($player->yearsOfExperience);

        return [
            'player' => $player,
            'capMetrics' => $capMetrics,
            'demands' => $demands,
            'existingOffer' => $existingOffer,
            'amendedCapSpace' => $amendedCapSpace,
            'hasExistingOffer' => $hasExistingOffer,
            'veteranMinimum' => $veteranMinimum,
            'maxContract' => $maxContract,
        ];
    }

    /**
     * @see FreeAgencyServiceInterface::getExistingOffer()
     */
    public function getExistingOffer(string $teamName, string $playerName): array
    {
        $offer = $this->repository->getExistingOffer($teamName, $playerName);

        if ($offer === null) {
            return [
                'offer1' => 0,
                'offer2' => 0,
                'offer3' => 0,
                'offer4' => 0,
                'offer5' => 0,
                'offer6' => 0,
            ];
        }

        return [
            'offer1' => (int) ($offer['offer1'] ?? 0),
            'offer2' => (int) ($offer['offer2'] ?? 0),
            'offer3' => (int) ($offer['offer3'] ?? 0),
            'offer4' => (int) ($offer['offer4'] ?? 0),
            'offer5' => (int) ($offer['offer5'] ?? 0),
            'offer6' => (int) ($offer['offer6'] ?? 0),
        ];
    }
}
