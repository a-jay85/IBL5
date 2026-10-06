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
 * Implements the service contract defined in PlayerSearchServiceInterface.
 * See the interface for detailed behavior documentation.
 *
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 */
class PlayerSearchService implements PlayerSearchServiceInterface
{
    private PlayerSearchValidatorInterface $validator;
    private PlayerSearchRepositoryInterface $repository;
    private PlayerRepository $playerRepository;

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
     * @see PlayerSearchServiceInterface::search()
     */
    public function search(array $rawParams): array
    {
        $params = $this->validator->validateSearchParams($rawParams);

        if ($rawParams === []) {
            return [
                'players' => [],
                'count' => 0,
                'params' => $params
            ];
        }

        $searchResult = $this->repository->searchPlayers($params);

        $playerDataObjects = array_map(
            function (array $playerRow): PlayerData {
                /** @var PlayerRow $typedRow */
                $typedRow = $playerRow;
                return $this->playerRepository->fillFromCurrentRow($typedRow);
            },
            $searchResult['results']
        );

        return [
            'players' => $playerDataObjects,
            'count' => $searchResult['count'],
            'params' => $params
        ];
    }

}
