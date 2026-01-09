<?php

declare(strict_types=1);

namespace PlayerAwards;

use PlayerAwards\Contracts\PlayerAwardsServiceInterface;
use PlayerAwards\Contracts\PlayerAwardsValidatorInterface;
use PlayerAwards\Contracts\PlayerAwardsRepositoryInterface;

/**
 * PlayerAwardsService - Business logic for player awards search
 * 
 * Implements the service contract defined in PlayerAwardsServiceInterface.
 * See the interface for detailed behavior documentation.
 * 
 * @see PlayerAwardsServiceInterface
 */
class PlayerAwardsService implements PlayerAwardsServiceInterface
{
    private PlayerAwardsValidatorInterface $validator;
    private PlayerAwardsRepositoryInterface $repository;

    /**
     * Constructor with dependency injection
     * 
     * @param PlayerAwardsValidatorInterface $validator Validator for search params
     * @param PlayerAwardsRepositoryInterface $repository Repository for database operations
     */
    public function __construct(
        PlayerAwardsValidatorInterface $validator,
        PlayerAwardsRepositoryInterface $repository
    ) {
        $this->validator = $validator;
        $this->repository = $repository;
    }

    /**
     * @see PlayerAwardsServiceInterface::search()
     */
    public function search(array $rawParams): array
    {
        // Validate all parameters
        $params = $this->validator->validateSearchParams($rawParams);

        // If no form submission (empty POST), return empty results with default params
        if (empty($rawParams)) {
            return [
                'awards' => [],
                'count' => 0,
                'params' => $params,
            ];
        }

        // Execute database search
        $searchResult = $this->repository->searchAwards($params);

        return [
            'awards' => $searchResult['results'],
            'count' => $searchResult['count'],
            'params' => $params,
        ];
    }

    /**
     * @see PlayerAwardsServiceInterface::getSortOptions()
     */
    public function getSortOptions(): array
    {
        return [
            1 => 'Name',
            2 => 'Award Name',
            3 => 'Year',
        ];
    }
}
