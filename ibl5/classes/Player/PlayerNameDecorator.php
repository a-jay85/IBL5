<?php

namespace Player;

use Player\Contracts\PlayerNameDecoratorInterface;

/**
 * @see PlayerNameDecoratorInterface
 */
class PlayerNameDecorator implements PlayerNameDecoratorInterface
{
    /**
     * Decorate a player name with status indicators
     */
    public function decoratePlayerName(PlayerData $playerData): string
    {
        if ($playerData->teamID == 0) {
            $decoratedName = "$playerData->name";
        } elseif ($playerData->ordinal > \JSB::WAIVERS_ORDINAL) {
            $decoratedName = "($playerData->name)*";
        } elseif ($playerData->contractCurrentYear == $playerData->contractTotalYears) { // eligible for Free Agency at the end of this season
            $decoratedName = "$playerData->name^";
        } else {
            $decoratedName = "$playerData->name";
        }
        return $decoratedName;
    }
}
