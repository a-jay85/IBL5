<?php

declare(strict_types=1);

/**
 * IBL Collective Bargaining Agreement (CBA) Contract Rules
 * 
 * Contains league-wide constants for contract negotiations, extensions, and free agency.
 * These values represent the official CBA rules governing player contracts.
 * 
 * Single source of truth for:
 * - Maximum raise percentages
 * - Bird Rights thresholds
 * - Veteran minimum salaries
 * - Maximum contract salaries
 * - Salary cap exceptions (MLE, LLE)
 * 
 * Used by: Extension, FreeAgency, Negotiation, and Waivers modules
 */
class ContractRules
{
    /**
     * Maximum annual raise percentage for players WITHOUT Bird Rights
     * 
     * Players can receive up to 10% raise each year when they don't have
     * Bird Rights with the signing team.
     */
    public const STANDARD_RAISE_PERCENTAGE = 0.10;

    /**
     * Maximum annual raise percentage for players WITH Bird Rights
     * 
     * Players with Bird Rights can receive up to 12.5% raise each year,
     * allowing teams to offer more competitive contracts to retain their own players.
     */
    public const BIRD_RIGHTS_RAISE_PERCENTAGE = 0.125;

    /**
     * Years required to qualify for Bird Rights
     * 
     * A player must play for the same team for 3+ consecutive years
     * to qualify for Bird Rights status with that team.
     */
    public const BIRD_RIGHTS_THRESHOLD = 3;

    /**
     * Veteran minimum salary by years of experience
     * 
     * Used for both Free Agency contract offers and Waiver signings.
     * Year 1 value (35) represents the first-year rookie contract minimum.
     * Year 2+ values (51+) represent veteran and returning player minimums.
     * 
     * @var array<int, int>
     */
    public const VETERAN_MINIMUM_SALARIES = [
        10 => 103,  // 10+ years
        9  => 100,  // 9 years
        8  => 89,   // 8 years
        7  => 82,   // 7 years
        6  => 76,   // 6 years
        5  => 70,   // 5 years
        4  => 64,   // 4 years
        3  => 61,   // 3 years
        2  => 51,   // 2 years (and above for waivers)
        1  => 35,   // 1 year (first-year rookie contract minimum)
    ];

    /**
     * Maximum contract salary by years of experience (first year only)
     * 
     * These represent the maximum first-year contract salary for players
     * based on their years of service. Additional years can have raises
     * up to 12.5% (with bird rights) or 10% (without bird rights).
     * 
     * @var array<int, int>
     */
    public const MAX_CONTRACT_SALARIES = [
        10 => 1451,  // 10+ years
        7  => 1275,  // 7-9 years
        0  => 1063,  // 0-6 years
    ];

    /**
     * Mid-Level Exception offer amounts for a 6-year contract
     * 
     * Each year has a 10% raise from the previous year.
     * For contracts shorter than 6 years, use array_slice to get the appropriate years.
     * 
     * @var array<int>
     */
    public const MLE_OFFERS = [450, 495, 540, 585, 630, 675];

    /**
     * Lower-Level Exception offer amount
     * 
     * Maximum salary for a LLE contract offer.
     * Available to teams with cap space and limited MLE eligibility.
     * 
     * @var int
     */
    public const LLE_OFFER = 145;

    public const PLAY_FOR_WINNER_FACTOR = 0.000153;
    public const TRADITION_FACTOR = 0.000153;
    public const LOYALTY_BONUS_PERCENTAGE = 0.025;
    public const PLAYING_TIME_BASE_FACTOR = 0.025;
    public const PLAYING_TIME_MONEY_FACTOR = 0.0025;
    public const PLAYING_TIME_DIVISOR = 100;
    public const MAX_POSITION_SALARY_CAP = 2000;

    /**
     * Calculate the maximum annual raise amount for a contract
     *
     * Uses floor() to guarantee the returned value never exceeds the allowed raise.
     * All raise calculations (validators, preset buttons, display hints) MUST use
     * this method to avoid off-by-one rounding inconsistencies.
     *
     * @param int $yearOneOffer First year salary amount
     * @param int $birdYears Number of consecutive years with current team
     * @return int Maximum raise amount per year
     */
    public static function calculateMaxRaise(int $yearOneOffer, int $birdYears): int
    {
        return (int) floor($yearOneOffer * self::getMaxRaisePercentage($birdYears));
    }

    /**
     * Get the maximum raise percentage for a player
     *
     * @param int $birdYears Number of consecutive years with current team
     * @return float Maximum raise percentage (0.10 or 0.125)
     */
    public static function getMaxRaisePercentage(int $birdYears): float
    {
        return $birdYears >= self::BIRD_RIGHTS_THRESHOLD
            ? self::BIRD_RIGHTS_RAISE_PERCENTAGE
            : self::STANDARD_RAISE_PERCENTAGE;
    }

    /**
     * Check if player has Bird Rights
     * 
     * @param int $birdYears Number of consecutive years with current team
     * @return bool True if player has Bird Rights
     */
    public static function hasBirdRights(int $birdYears): bool
    {
        return $birdYears >= self::BIRD_RIGHTS_THRESHOLD;
    }

    /**
     * Get veteran minimum salary for a specific experience level
     * 
     * @param int $experience Years of experience
     * @return int Veteran minimum salary
     */
    public static function getVeteranMinimumSalary(int $experience): int
    {
        foreach (self::VETERAN_MINIMUM_SALARIES as $years => $salary) {
            if ($experience >= $years) {
                return $salary;
            }
        }
        return self::VETERAN_MINIMUM_SALARIES[1];
    }

    /**
     * Get maximum contract salary for a specific experience level
     * 
     * @param int $experience Years of experience
     * @return int Maximum first-year contract salary
     */
    public static function getMaxContractSalary(int $experience): int
    {
        foreach (self::MAX_CONTRACT_SALARIES as $years => $salary) {
            if ($experience >= $years) {
                return $salary;
            }
        }
        return self::MAX_CONTRACT_SALARIES[0];
    }

    /**
     * Get MLE offer amounts for a specific number of years
     * 
     * @param int $years Number of contract years (1-6)
     * @return array<int> MLE offer amounts for each year
     */
    public static function getMLEOffers(int $years): array
    {
        return array_slice(self::MLE_OFFERS, 0, $years);
    }

    public static function calculateWinnerModifier(int $wins, int $losses, int $preference): float
    {
        return self::PLAY_FOR_WINNER_FACTOR * ($wins - $losses) * ($preference - 1);
    }

    public static function calculateTraditionModifier(int $tradWins, int $tradLosses, int $preference): float
    {
        return self::TRADITION_FACTOR * ($tradWins - $tradLosses) * ($preference - 1);
    }

    public static function calculateLoyaltyModifier(int $preference, bool $isOwnTeam = true): float
    {
        $sign = $isOwnTeam ? 1 : -1;
        return $sign * self::LOYALTY_BONUS_PERCENTAGE * ($preference - 1);
    }

    public static function calculatePlayingTimeModifier(int $moneyCommitted, int $preference): float
    {
        $capped = min($moneyCommitted, self::MAX_POSITION_SALARY_CAP);
        return (self::PLAYING_TIME_BASE_FACTOR
                - self::PLAYING_TIME_MONEY_FACTOR * $capped / self::PLAYING_TIME_DIVISOR)
               * ($preference - 1);
    }
}
