<?php

declare(strict_types=1);

namespace Player;

use Player\Contracts\PlayerNameDecoratorInterface;

/**
 * @see PlayerNameDecoratorInterface
 */
class PlayerNameDecorator implements PlayerNameDecoratorInterface
{
    /**
     * @see PlayerNameDecoratorInterface::decoratePlayerName()
     */
    public function decoratePlayerName(PlayerData $playerData): string
    {
        return (string) $playerData->name;
    }

    /**
     * @see PlayerNameDecoratorInterface::getNameStatusClass()
     */
    public function getNameStatusClass(PlayerData $playerData): string
    {
        if ($playerData->teamID === 0) {
            return '';
        }

        if ($playerData->ordinal > \JSB::WAIVERS_ORDINAL) {
            return 'player-waived';
        }

        if ($playerData->contractCurrentYear === $playerData->contractTotalYears) {
            return 'player-expiring';
        }

        return '';
    }
}
