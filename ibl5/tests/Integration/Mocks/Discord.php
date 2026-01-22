<?php

namespace Tests\Integration\Mocks;

/**
 * Mock Discord class for testing
 */
class Discord
{
    public function getDiscordIDFromTeamname(string $teamname): string
    {
        return '123456789';
    }
    
    public static function postToChannel($channel, $message)
    {
        return true;
    }
}
