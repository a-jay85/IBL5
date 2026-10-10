<?php

declare(strict_types=1);

namespace Player;

/**
 * Thrown by PlayerRepository::loadByID() when no ibl_plr row matches the pid.
 * Subclasses \RuntimeException so every existing caller that catches
 * \RuntimeException (ExtensionService, NextSimService, FreeAgencyService,
 * NegotiationService, RookieOptionController, PlayerActionController) keeps working.
 */
final class PlayerNotFoundException extends \RuntimeException
{
}
