<?php

declare(strict_types=1);

namespace OnThisDay;

use OnThisDay\Contracts\MomentPosterInterface;

/**
 * Posts "on this day" lines to #general-chat through the shared Discord helper.
 *
 * Production posts to the general-chat webhook, other environments route to the
 * testing webhook, and PHPUnit is a no-op. Config and cURL failures propagate
 * as RuntimeException for the caller to absorb.
 */
final class DiscordMomentPoster implements MomentPosterInterface
{
    private const CHANNEL = '#general-chat';

    public function post(string $message): void
    {
        \Discord\Discord::postToChannel(self::CHANNEL, $message);
    }
}
