<?php

declare(strict_types=1);

namespace Tests\WideUnit\Mocks;

/**
 * Mock Discord class for testing
 */
class Discord
{
    public function getDiscordIDFromTeamname(string $teamname): string
    {
        return '123456789';
    }
    
    public static function postToChannel(string $channel, string $message): bool
    {
        return true;
    }
}
