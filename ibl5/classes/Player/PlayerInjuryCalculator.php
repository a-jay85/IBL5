<?php

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
            $injuryDateString = $playerData->daysRemainingForInjury + 1 . ' days';
            $injuryReturnDate = date_add($properLastSimEndDate, date_interval_create_from_date_string($injuryDateString));
            return $injuryReturnDate->format('Y-m-d');
        }
    }
}
