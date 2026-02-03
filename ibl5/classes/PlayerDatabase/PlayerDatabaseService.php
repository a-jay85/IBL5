<?php

declare(strict_types=1);

namespace PlayerDatabase;

use Player\PlayerRepository;
use Player\PlayerData;
use PlayerDatabase\Contracts\PlayerDatabaseServiceInterface;
use PlayerDatabase\Contracts\PlayerDatabaseValidatorInterface;
use PlayerDatabase\Contracts\PlayerDatabaseRepositoryInterface;

/**
 * PlayerDatabaseService - Business logic for player search
 * 
 * Implements the service contract defined in PlayerDatabaseServiceInterface.
 * See the interface for detailed behavior documentation.
 */
class PlayerDatabaseService implements PlayerDatabaseServiceInterface
{
    private PlayerDatabaseValidatorInterface $validator;
    private PlayerDatabaseRepositoryInterface $repository;
    private PlayerRepository $playerRepository;

    public function __construct(
        PlayerDatabaseValidatorInterface $validator,
        PlayerDatabaseRepositoryInterface $repository,
        PlayerRepository $playerRepository
    ) {
        $this->validator = $validator;
        $this->repository = $repository;
        $this->playerRepository = $playerRepository;
    }

    /**
     * @see PlayerDatabaseServiceInterface::search()
     */
    public function search(array $rawParams): array
    {
        $params = $this->validator->validateSearchParams($rawParams);

        if (empty($rawParams)) {
            return [
                'players' => [],
                'count' => 0,
                'params' => $params
            ];
        }

        $searchResult = $this->repository->searchPlayers($params);

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
