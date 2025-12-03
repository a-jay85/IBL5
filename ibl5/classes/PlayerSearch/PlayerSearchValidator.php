<?php

declare(strict_types=1);

namespace PlayerSearch;

use PlayerSearch\Contracts\PlayerSearchValidatorInterface;

/**
 * PlayerSearchValidator - Validates and sanitizes player search parameters
 * 
 * Implements the validation contract defined in PlayerSearchValidatorInterface.
 * See the interface for detailed behavior documentation.
 */
class PlayerSearchValidator implements PlayerSearchValidatorInterface
{
    /**
     * @see PlayerSearchValidatorInterface::validateSearchParams()
     */
    public function validateSearchParams(array $params): array
    {
        return [
            'pos' => $this->validatePosition($params['pos'] ?? null),
            'age' => $this->validateIntegerParam($params['age'] ?? null),
            'search_name' => $this->validateStringParam($params['search_name'] ?? null),
            'college' => $this->validateStringParam($params['college'] ?? null),
            
            'exp' => $this->validateIntegerParam($params['exp'] ?? null),
            'exp_max' => $this->validateIntegerParam($params['exp_max'] ?? null),
            'bird' => $this->validateIntegerParam($params['bird'] ?? null),
            'bird_max' => $this->validateIntegerParam($params['bird_max'] ?? null),
            
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
            
            'Clutch' => $this->validateIntegerParam($params['Clutch'] ?? null),
            'Consistency' => $this->validateIntegerParam($params['Consistency'] ?? null),
            'talent' => $this->validateIntegerParam($params['talent'] ?? null),
            'skill' => $this->validateIntegerParam($params['skill'] ?? null),
            'intangibles' => $this->validateIntegerParam($params['intangibles'] ?? null),
            
            'oo' => $this->validateIntegerParam($params['oo'] ?? null),
            'do' => $this->validateIntegerParam($params['do'] ?? null),
            'po' => $this->validateIntegerParam($params['po'] ?? null),
            'to' => $this->validateIntegerParam($params['to'] ?? null),
            'od' => $this->validateIntegerParam($params['od'] ?? null),
            'dd' => $this->validateIntegerParam($params['dd'] ?? null),
            'pd' => $this->validateIntegerParam($params['pd'] ?? null),
            'td' => $this->validateIntegerParam($params['td'] ?? null),
            
            'active' => $this->validateBooleanParam($params['active'] ?? null),
        ];
    }

    /**
     * @see PlayerSearchValidatorInterface::validatePosition()
     */
    public function validatePosition(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        $position = strtoupper(trim((string)$value));
        return in_array($position, \JSB::PLAYER_POSITIONS, true) ? $position : null;
    }

    /**
     * @see PlayerSearchValidatorInterface::validateIntegerParam()
     */
    public function validateIntegerParam(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        if (!is_numeric($value)) {
            return null;
        }
        
        $intValue = (int)$value;
        return $intValue < 0 ? null : $intValue;
    }

    /**
     * @see PlayerSearchValidatorInterface::validateStringParam()
     */
    public function validateStringParam(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        $sanitized = trim((string)$value);
        return $sanitized === '' ? null : mb_substr($sanitized, 0, 64);
    }

    /**
     * @see PlayerSearchValidatorInterface::validateBooleanParam()
     */
    public function validateBooleanParam(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        return in_array($value, [0, 1, '0', '1'], true) ? (int)$value : null;
    }


}
