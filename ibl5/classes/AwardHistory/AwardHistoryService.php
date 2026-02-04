<?php

declare(strict_types=1);

namespace AwardHistory;

use AwardHistory\Contracts\AwardHistoryServiceInterface;
use AwardHistory\Contracts\AwardHistoryValidatorInterface;
use AwardHistory\Contracts\AwardHistoryRepositoryInterface;

/**
 * AwardHistoryService - Business logic for player awards search
 * 
 * Implements the service contract defined in AwardHistoryServiceInterface.
 * See the interface for detailed behavior documentation.
 * 
 * @see AwardHistoryServiceInterface
 */
class AwardHistoryService implements AwardHistoryServiceInterface
{
    private AwardHistoryValidatorInterface $validator;
    private AwardHistoryRepositoryInterface $repository;

    /**
     * Constructor with dependency injection
     * 
     * @param AwardHistoryValidatorInterface $validator Validator for search params
     * @param AwardHistoryRepositoryInterface $repository Repository for database operations
     */
    public function __construct(
        AwardHistoryValidatorInterface $validator,
        AwardHistoryRepositoryInterface $repository
    ) {
        $this->validator = $validator;
        $this->repository = $repository;
    }

    /**
     * @see AwardHistoryServiceInterface::search()
     */
    public function search(array $rawParams): array
    {
        // Validate all parameters
        $params = $this->validator->validateSearchParams($rawParams);

        // If no form submission (empty POST), return empty results with default params
        if ($rawParams === []) {
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
     * @see AwardHistoryServiceInterface::getSortOptions()
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
