<?php

declare(strict_types=1);

namespace PlayerSearch;

/**
 * PlayerSearchValidator - Validates and sanitizes player search parameters
 * 
 * Provides input validation and sanitization for all search form fields.
 * Prevents SQL injection by validating input types and whitelisting values.
 */
class PlayerSearchValidator
{
    /**
     * Validate and sanitize all search parameters from form submission
     * 
     * @param array<string, mixed> $params Raw POST parameters
     * @return array<string, mixed> Validated and sanitized parameters
     */
    public function validateSearchParams(array $params): array
    {
        return [
            // Basic filters
            'pos' => $this->validatePosition($params['pos'] ?? null),
            'age' => $this->validateIntegerParam($params['age'] ?? null),
            'search_name' => $this->validateStringParam($params['search_name'] ?? null),
            'college' => $this->validateStringParam($params['college'] ?? null),
            
            // Experience filters
            'exp' => $this->validateIntegerParam($params['exp'] ?? null),
            'exp_max' => $this->validateIntegerParam($params['exp_max'] ?? null),
            'bird' => $this->validateIntegerParam($params['bird'] ?? null),
            'bird_max' => $this->validateIntegerParam($params['bird_max'] ?? null),
            
            // Rating filters (r_ prefixed fields)
            'r_fga' => $this->validateIntegerParam($params['r_fga'] ?? null),
            'r_fgp' => $this->validateIntegerParam($params['r_fgp'] ?? null),
            'r_fta' => $this->validateIntegerParam($params['r_fta'] ?? null),
            'r_ftp' => $this->validateIntegerParam($params['r_ftp'] ?? null),
            'r_tga' => $this->validateIntegerParam($params['r_tga'] ?? null),
            'r_tgp' => $this->validateIntegerParam($params['r_tgp'] ?? null),
            'r_orb' => $this->validateIntegerParam($params['r_orb'] ?? null),
            'r_drb' => $this->validateIntegerParam($params['r_drb'] ?? null),
            'r_ast' => $this->validateIntegerParam($params['r_ast'] ?? null),
            'r_stl' => $this->validateIntegerParam($params['r_stl'] ?? null),
            'r_blk' => $this->validateIntegerParam($params['r_blk'] ?? null),
            'r_to' => $this->validateIntegerParam($params['r_to'] ?? null),
            'r_foul' => $this->validateIntegerParam($params['r_foul'] ?? null),
            
            // Attribute ratings
            'Clutch' => $this->validateIntegerParam($params['Clutch'] ?? null),
            'Consistency' => $this->validateIntegerParam($params['Consistency'] ?? null),
            'talent' => $this->validateIntegerParam($params['talent'] ?? null),
            'skill' => $this->validateIntegerParam($params['skill'] ?? null),
            'intangibles' => $this->validateIntegerParam($params['intangibles'] ?? null),
            
            // Offensive/defensive skill ratings
            'oo' => $this->validateIntegerParam($params['oo'] ?? null),
            'do' => $this->validateIntegerParam($params['do'] ?? null),
            'po' => $this->validateIntegerParam($params['po'] ?? null),
            'to' => $this->validateIntegerParam($params['to'] ?? null),
            'od' => $this->validateIntegerParam($params['od'] ?? null),
            'dd' => $this->validateIntegerParam($params['dd'] ?? null),
            'pd' => $this->validateIntegerParam($params['pd'] ?? null),
            'td' => $this->validateIntegerParam($params['td'] ?? null),
            
            // Meta filters
            'active' => $this->validateBooleanParam($params['active'] ?? null),
        ];
    }

    /**
     * Validate position parameter against whitelist
     * 
     * @param mixed $value Raw position value
     * @return string|null Validated position or null
     */
    public function validatePosition(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        $position = strtoupper(trim((string)$value));
        
        if (in_array($position, \JSB::PLAYER_POSITIONS, true)) {
            return $position;
        }
        
        return null;
    }

    /**
     * Validate integer parameter
     * 
     * @param mixed $value Raw value
     * @return int|null Validated integer or null if empty/invalid
     */
    public function validateIntegerParam(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        // Check if it's a valid integer
        if (!is_numeric($value)) {
            return null;
        }
        
        $intValue = (int)$value;
        
        // Ensure non-negative for rating values (0-99 typical range)
        if ($intValue < 0) {
            return null;
        }
        
        return $intValue;
    }

    /**
     * Validate string parameter for name/college searches
     * 
     * @param mixed $value Raw string value
     * @return string|null Sanitized string or null if empty
     */
    public function validateStringParam(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        // Trim and limit length to prevent abuse
        $sanitized = trim((string)$value);
        
        if ($sanitized === '') {
            return null;
        }
        
        // Limit to 64 characters to prevent excessively long searches
        return mb_substr($sanitized, 0, 64);
    }

    /**
     * Validate boolean/flag parameter
     * 
     * @param mixed $value Raw value
     * @return int|null 0, 1, or null
     */
    public function validateBooleanParam(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        return in_array($value, [0, 1, '0', '1'], true) ? (int)$value : null;
    }


}
