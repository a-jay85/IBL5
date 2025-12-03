<?php

declare(strict_types=1);

namespace PlayerSearch;

use Player\PlayerRepository;
use Player\PlayerData;
use PlayerSearch\Contracts\PlayerSearchServiceInterface;
use PlayerSearch\Contracts\PlayerSearchValidatorInterface;
use PlayerSearch\Contracts\PlayerSearchRepositoryInterface;

/**
 * PlayerSearchService - Business logic for player search
 * 
 * Orchestrates search workflow and data transformation.
 * Returns PlayerData objects for type-safe result handling.
 */
class PlayerSearchService implements PlayerSearchServiceInterface
{
    private PlayerSearchValidatorInterface $validator;
    private PlayerSearchRepositoryInterface $repository;
    private PlayerRepository $playerRepository;

    /**
     * Constructor
     * 
     * @param PlayerSearchValidatorInterface $validator Validator instance
     * @param PlayerSearchRepositoryInterface $repository Search repository instance
     * @param PlayerRepository $playerRepository Player repository for data object population
     */
    public function __construct(
        PlayerSearchValidatorInterface $validator,
        PlayerSearchRepositoryInterface $repository,
        PlayerRepository $playerRepository
    ) {
        $this->validator = $validator;
        $this->repository = $repository;
        $this->playerRepository = $playerRepository;
    }

    /**
     * Execute a player search based on form parameters
     * 
     * @param array<string, mixed> $rawParams Raw POST parameters
     * @return array{players: array<PlayerData>, count: int, params: array<string, mixed>}
     */
    public function search(array $rawParams): array
    {
        // Validate parameters
        $params = $this->validator->validateSearchParams($rawParams);

        // Check if form was submitted (POST data exists)
        if (empty($rawParams)) {
            return [
                'players' => [],
                'count' => 0,
                'params' => $params
            ];
        }

        // Execute search
        $searchResult = $this->repository->searchPlayers($params);

        // Convert raw player arrays to PlayerData objects
        $playerDataObjects = array_map(
            fn(array $playerRow) => $this->playerRepository->fillFromCurrentRow($playerRow),
            $searchResult['results']
        );

        return [
            'players' => $playerDataObjects,
            'count' => $searchResult['count'],
            'params' => $params
        ];
    }

}
