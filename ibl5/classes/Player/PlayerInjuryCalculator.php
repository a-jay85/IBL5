<?php

declare(strict_types=1);

namespace Player;

use Player\Contracts\PlayerInjuryCalculatorInterface;

/**
 * @see PlayerInjuryCalculatorInterface
 */
class PlayerInjuryCalculator implements PlayerInjuryCalculatorInterface
{
    /**
     * Calculate when an injured player will return
     */
    public function getInjuryReturnDate(PlayerData $playerData, string $rawLastSimEndDate): string
    {
        if ($playerData->daysRemainingForInjury <= 0) {
            return "";
        } else {
            $properLastSimEndDate = date_create($rawLastSimEndDate);
            if ($properLastSimEndDate === false) {
                return "";
            }
            $injuryDateString = ($playerData->daysRemainingForInjury + 1) . ' days';
            $interval = date_interval_create_from_date_string($injuryDateString);
            if ($interval === false) {
                return "";
            }
            $injuryReturnDate = date_add($properLastSimEndDate, $interval);
            return $injuryReturnDate->format('Y-m-d');
        }
    }
}
