<?php

declare(strict_types=1);

namespace AwardHistory;

use AwardHistory\Contracts\AwardHistoryValidatorInterface;

/**
 * AwardHistoryValidator - Validates and sanitizes player awards search parameters
 * 
 * Implements the validation contract defined in AwardHistoryValidatorInterface.
 * See the interface for detailed behavior documentation.
 * 
 * @see AwardHistoryValidatorInterface
 */
class AwardHistoryValidator implements AwardHistoryValidatorInterface
{
    /**
     * Valid sort options for award search
     */
    private const VALID_SORT_OPTIONS = [1, 2, 3];

    /**
     * @see AwardHistoryValidatorInterface::validateSearchParams()
     */
    public function validateSearchParams(array $params): array
    {
        return [
            'name' => $this->validateStringParam($params['aw_name'] ?? null),
            'award' => $this->validateStringParam($params['aw_Award'] ?? null),
            'year' => $this->validateYearParam($params['aw_year'] ?? null),
            'sortby' => $this->validateSortParam($params['aw_sortby'] ?? null),
        ];
    }

    /**
     * @see AwardHistoryValidatorInterface::validateStringParam()
     */
    public function validateStringParam(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Narrow to string and remove HTML tags
        /** @var string $stringValue */
        $stringValue = $value;
        $cleaned = strip_tags($stringValue);

        // Trim whitespace
        $cleaned = trim($cleaned);

        // Return null if empty after cleaning
        return $cleaned === '' ? null : $cleaned;
    }

    /**
     * @see AwardHistoryValidatorInterface::validateYearParam()
     */
    public function validateYearParam(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $intValue = (int)$value;
        
        // Year must be positive
        return $intValue > 0 ? $intValue : null;
    }

    /**
     * @see AwardHistoryValidatorInterface::validateSortParam()
     */
    public function validateSortParam(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 3; // Default to year
        }

        if (!is_numeric($value)) {
            return 3;
        }

        $intValue = (int)$value;
        
        // Must be a valid sort option
        return in_array($intValue, self::VALID_SORT_OPTIONS, true) ? $intValue : 3;
    }
}
