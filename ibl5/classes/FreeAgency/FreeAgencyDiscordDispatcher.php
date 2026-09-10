<?php

declare(strict_types=1);

namespace FreeAgency;

use Discord\Discord;
use FreeAgency\Contracts\FreeAgencyDiscordDispatcherInterface;

/** Posts free agency signings summaries to the #free-agency Discord channel. */
final class FreeAgencyDiscordDispatcher implements FreeAgencyDiscordDispatcherInterface
{
    public function dispatch(string $message): void
    {
        Discord::postToChannel('#free-agency', $message);
    }
}
