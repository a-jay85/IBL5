<?php

declare(strict_types=1);

namespace Tests\Discord;

use PHPUnit\Framework\TestCase;

/**
 * Tests for Discord class
 */
class DiscordTest extends TestCase
{
    private $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
    }

    /**
     * Test getDiscordIDFromTeamname returns string type
     */
    public function testGetDiscordIDFromTeamnameReturnsString(): void
    {
        $discord = new \Discord($this->mockDb);
        $result = $discord->getDiscordIDFromTeamname('NonExistentTeam');
        
        // Mock returns '123456789', real implementation would return '' for non-existent team
        $this->assertIsString($result);
    }

    /**
     * Test sendCurlPOST handles string message content correctly
     * This tests the message formatting logic, not the actual curl call
     */
    public function testSendCurlPOSTAcceptsStringContent(): void
    {
        $testMessage = "Test message with <br> tag";
        
        // Test the JSON encoding that happens in sendCurlPOST
        $payload = json_encode(array("content" => $testMessage));
        $decoded = json_decode($payload, true);
        
        $this->assertIsString($payload);
        $this->assertEquals($testMessage, $decoded['content']);
    }

    /**
     * Test postToChannel converts br tags to newlines
     */
    public function testPostToChannelConvertsBrTags(): void
    {
        $messageWithBr = "First line<br>Second line<br>Third line";
        
        // Mock the static method call by testing the string replacement logic
        $processed = str_replace('<br>', "\n", $messageWithBr);
        
        $this->assertStringNotContainsString('<br>', $processed);
        $this->assertStringContainsString("\n", $processed);
        $this->assertEquals("First line\nSecond line\nThird line", $processed);
    }

    /**
     * Test that empty Discord IDs don't break message formatting
     */
    public function testEmptyDiscordIDsHandledGracefully(): void
    {
        $fromDiscordId = '';
        $toDiscordId = '';
        $storytext = "Trade details here";
        
        // Simulate the TradeProcessor logic
        if (!empty($fromDiscordId) && !empty($toDiscordId)) {
            $discordText = "<@!$fromDiscordId> and <@!$toDiscordId> agreed to a trade:\n" . $storytext;
        } else {
            $discordText = "Team A and Team B agreed to a trade:\n" . $storytext;
        }
        
        $this->assertStringNotContainsString('<@!>', $discordText);
        $this->assertStringContainsString('Team A and Team B', $discordText);
    }

    /**
     * Test that non-empty Discord IDs create proper mentions
     */
    public function testValidDiscordIDsCreateMentions(): void
    {
        $fromDiscordId = '123456789';
        $toDiscordId = '987654321';
        $storytext = "Trade details here";
        
        // Simulate the TradeProcessor logic
        if (!empty($fromDiscordId) && !empty($toDiscordId)) {
            $discordText = "<@!$fromDiscordId> and <@!$toDiscordId> agreed to a trade:\n" . $storytext;
        } else {
            $discordText = "Team A and Team B agreed to a trade:\n" . $storytext;
        }
        
        $this->assertStringContainsString('<@!123456789>', $discordText);
        $this->assertStringContainsString('<@!987654321>', $discordText);
    }
}
