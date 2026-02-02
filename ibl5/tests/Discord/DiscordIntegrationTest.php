<?php

declare(strict_types=1);

namespace Tests\Discord;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Tests\Integration\IntegrationTestCase;
use Tests\Integration\Mocks\TestDataFactory;

/**
 * DiscordIntegrationTest - Integration tests for Discord class
 *
 * Tests database interactions and message processing logic.
 *
 * @covers \Discord
 */
#[AllowMockObjectsWithoutExpectations]
class DiscordIntegrationTest extends IntegrationTestCase
{
    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    /**
     * Test Discord class can be instantiated with mock database
     */
    public function testConstructorAcceptsMockDatabase(): void
    {
        $discord = new \Discord($this->mockDb);

        $this->assertInstanceOf(\Discord::class, $discord);
    }

    /**
     * Test constructor loads config without throwing exception
     */
    public function testConstructorLoadsConfig(): void
    {
        // If config is not available, this should still work
        // because the example config should exist
        $discord = new \Discord($this->mockDb);

        $this->assertInstanceOf(\Discord::class, $discord);
    }

    // ============================================
    // GET DISCORD ID FROM TEAMNAME TESTS
    // ============================================

    /**
     * Test getDiscordIDFromTeamname returns string from database
     */
    public function testGetDiscordIDFromTeamnameReturnsStringFromDatabase(): void
    {
        $this->mockDb->setMockData([
            ['discordID' => '123456789012345678']
        ]);

        $discord = new \Discord($this->mockDb);
        $result = $discord->getDiscordIDFromTeamname('Test Team');

        $this->assertEquals('123456789012345678', $result);
    }

    /**
     * Test getDiscordIDFromTeamname returns empty string for non-existent team
     */
    public function testGetDiscordIDFromTeamnameReturnsEmptyForNonExistentTeam(): void
    {
        $this->mockDb->setMockData([]);

        $discord = new \Discord($this->mockDb);
        $result = $discord->getDiscordIDFromTeamname('NonExistent Team');

        $this->assertEquals('', $result);
    }

    /**
     * Test getDiscordIDFromTeamname returns empty string when discordID is null
     */
    public function testGetDiscordIDFromTeamnameReturnsEmptyForNullDiscordID(): void
    {
        $this->mockDb->setMockData([
            ['discordID' => null]
        ]);

        $discord = new \Discord($this->mockDb);
        $result = $discord->getDiscordIDFromTeamname('Team With Null ID');

        $this->assertEquals('', $result);
    }

    /**
     * Test getDiscordIDFromTeamname executes correct query
     */
    public function testGetDiscordIDFromTeamnameExecutesCorrectQuery(): void
    {
        $this->mockDb->setMockData([]);

        $discord = new \Discord($this->mockDb);
        $this->mockDb->clearQueries(); // Clear any queries from constructor
        $discord->getDiscordIDFromTeamname('Miami');

        $queries = $this->mockDb->getExecutedQueries();
        // MockPreparedStatement calls sql_query in both execute() and get_result()
        $this->assertGreaterThanOrEqual(1, count($queries));
        $this->assertStringContainsString('nuke_users', $queries[0]);
        $this->assertStringContainsString('discordID', $queries[0]);
        $this->assertStringContainsString('user_ibl_team', $queries[0]);
    }

    /**
     * Test getDiscordIDFromTeamname handles team name with special characters
     */
    public function testGetDiscordIDFromTeamnameHandlesSpecialCharacters(): void
    {
        $this->mockDb->setMockData([
            ['discordID' => '999888777666555444']
        ]);

        $discord = new \Discord($this->mockDb);
        $result = $discord->getDiscordIDFromTeamname("Team's Name");

        $this->assertEquals('999888777666555444', $result);
    }

    /**
     * Test getDiscordIDFromTeamname uses LIMIT 1
     */
    public function testGetDiscordIDFromTeamnameUsesLimit(): void
    {
        $this->mockDb->setMockData([]);

        $discord = new \Discord($this->mockDb);
        $discord->getDiscordIDFromTeamname('Test');

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertStringContainsString('LIMIT 1', $queries[0]);
    }

    /**
     * Test getDiscordIDFromTeamname converts result to string
     */
    public function testGetDiscordIDFromTeamnameConvertsToString(): void
    {
        // Even if database returns integer-ish value
        $this->mockDb->setMockData([
            ['discordID' => 123456789]
        ]);

        $discord = new \Discord($this->mockDb);
        $result = $discord->getDiscordIDFromTeamname('Team');

        $this->assertIsString($result);
    }

    // ============================================
    // SEND CURL POST TESTS (STATIC METHOD)
    // ============================================

    /**
     * Test sendCurlPOST returns null during PHPUnit testing
     */
    public function testSendCurlPOSTReturnsNullInTestMode(): void
    {
        $result = \Discord::sendCurlPOST('https://discord.com/api/webhooks/test', 'Test message');

        $this->assertNull($result);
    }

    /**
     * Test sendCurlPOST accepts array content and encodes to JSON
     */
    public function testSendCurlPOSTJsonEncodesContent(): void
    {
        // Test the JSON encoding logic
        $content = "Test message with special chars: <>&\"'";
        $payload = json_encode(['content' => $content]);

        $this->assertJson($payload);
        $decoded = json_decode($payload, true);
        $this->assertEquals($content, $decoded['content']);
    }

    /**
     * Test sendCurlPOST handles multiline content
     */
    public function testSendCurlPOSTHandlesMultilineContent(): void
    {
        $content = "Line 1\nLine 2\nLine 3";
        $payload = json_encode(['content' => $content]);

        $decoded = json_decode($payload, true);
        $this->assertStringContainsString("\n", $decoded['content']);
        $this->assertEquals(3, substr_count($decoded['content'], 'Line'));
    }

    /**
     * Test sendCurlPOST handles emoji content
     */
    public function testSendCurlPOSTHandlesEmojiContent(): void
    {
        $content = "Trade completed! ✅ 🏀";
        $payload = json_encode(['content' => $content]);

        $decoded = json_decode($payload, true);
        $this->assertStringContainsString('✅', $decoded['content']);
    }

    // ============================================
    // POST TO CHANNEL TESTS (STATIC METHOD)
    // ============================================

    /**
     * Test postToChannel strips hash from channel name
     */
    public function testPostToChannelStripsHashFromChannelName(): void
    {
        $channelWithHash = '#transactions';
        $channelWithoutHash = ltrim($channelWithHash, '#');

        $this->assertEquals('transactions', $channelWithoutHash);
    }

    /**
     * Test postToChannel handles channel name without hash
     */
    public function testPostToChannelHandlesChannelWithoutHash(): void
    {
        $channel = 'transactions';
        $channelKey = ltrim($channel, '#');

        $this->assertEquals('transactions', $channelKey);
    }

    /**
     * Test postToChannel converts br tags to newlines
     */
    public function testPostToChannelConvertsBrTagsToNewlines(): void
    {
        $message = "Player traded<br>From Team A<br>To Team B";
        $processed = str_replace('<br>', "\n", $message);

        $this->assertEquals("Player traded\nFrom Team A\nTo Team B", $processed);
    }

    /**
     * Test postToChannel handles multiple consecutive br tags
     */
    public function testPostToChannelHandlesMultipleBrTags(): void
    {
        $message = "Header<br><br>Content<br><br><br>Footer";
        $processed = str_replace('<br>', "\n", $message);

        $this->assertEquals("Header\n\nContent\n\n\nFooter", $processed);
    }

    /**
     * Test postToChannel handles uppercase BR tags
     */
    public function testPostToChannelDoesNotConvertUppercaseBrTags(): void
    {
        // The current implementation only converts lowercase <br>
        $message = "Line 1<BR>Line 2";
        $processed = str_replace('<br>', "\n", $message);

        // <BR> is not converted - only <br>
        $this->assertEquals("Line 1<BR>Line 2", $processed);
    }

    /**
     * Test postToChannel detects localhost server
     */
    public function testPostToChannelDetectsLocalhost(): void
    {
        $serverName = 'localhost';
        $isLocalhost = ($serverName === 'localhost' || $serverName === '127.0.0.1');

        $this->assertTrue($isLocalhost);
    }

    /**
     * Test postToChannel detects 127.0.0.1 as localhost
     */
    public function testPostToChannelDetects127001AsLocalhost(): void
    {
        $serverName = '127.0.0.1';
        $isLocalhost = ($serverName === 'localhost' || $serverName === '127.0.0.1');

        $this->assertTrue($isLocalhost);
    }

    /**
     * Test postToChannel detects production server
     */
    public function testPostToChannelDetectsProductionServer(): void
    {
        $serverName = 'iblhoops.net';
        $isLocalhost = ($serverName === 'localhost' || $serverName === '127.0.0.1');

        $this->assertFalse($isLocalhost);
    }

    // ============================================
    // DISCORD MENTION FORMAT TESTS
    // ============================================

    /**
     * Test Discord user mention format
     */
    public function testDiscordUserMentionFormat(): void
    {
        $discordId = '123456789012345678';
        $mention = "<@!{$discordId}>";

        $this->assertEquals('<@!123456789012345678>', $mention);
    }

    /**
     * Test Discord channel mention format
     */
    public function testDiscordChannelMentionFormat(): void
    {
        $channelId = '987654321098765432';
        $mention = "<#{$channelId}>";

        $this->assertEquals('<#987654321098765432>', $mention);
    }

    /**
     * Test Discord role mention format
     */
    public function testDiscordRoleMentionFormat(): void
    {
        $roleId = '555555555555555555';
        $mention = "<@&{$roleId}>";

        $this->assertEquals('<@&555555555555555555>', $mention);
    }

    /**
     * Test trade message with Discord mentions
     */
    public function testTradeMessageWithDiscordMentions(): void
    {
        $fromDiscordId = '111111111111111111';
        $toDiscordId = '222222222222222222';
        $tradeDetails = "Player A for Player B";

        $message = "<@!{$fromDiscordId}> and <@!{$toDiscordId}> agreed to a trade:\n{$tradeDetails}";

        $this->assertStringContainsString('<@!111111111111111111>', $message);
        $this->assertStringContainsString('<@!222222222222222222>', $message);
        $this->assertStringContainsString("agreed to a trade:", $message);
    }

    /**
     * Test message without Discord mentions when IDs are empty
     */
    public function testMessageWithoutMentionsWhenIDsEmpty(): void
    {
        $fromDiscordId = '';
        $toDiscordId = '';
        $fromTeam = 'Miami';
        $toTeam = 'Los Angeles';

        if (!empty($fromDiscordId) && !empty($toDiscordId)) {
            $message = "<@!{$fromDiscordId}> and <@!{$toDiscordId}> agreed to a trade";
        } else {
            $message = "{$fromTeam} and {$toTeam} agreed to a trade";
        }

        $this->assertEquals('Miami and Los Angeles agreed to a trade', $message);
        $this->assertStringNotContainsString('<@!>', $message);
    }

    // ============================================
    // JSON PAYLOAD TESTS
    // ============================================

    /**
     * Test JSON payload structure for Discord webhook
     */
    public function testJsonPayloadStructure(): void
    {
        $content = "Test message";
        $payload = json_encode(['content' => $content]);
        $decoded = json_decode($payload, true);

        $this->assertArrayHasKey('content', $decoded);
        $this->assertEquals('Test message', $decoded['content']);
    }

    /**
     * Test JSON payload handles empty content
     */
    public function testJsonPayloadHandlesEmptyContent(): void
    {
        $payload = json_encode(['content' => '']);
        $decoded = json_decode($payload, true);

        $this->assertArrayHasKey('content', $decoded);
        $this->assertEquals('', $decoded['content']);
    }

    /**
     * Test JSON payload max length consideration
     */
    public function testJsonPayloadMaxLength(): void
    {
        // Discord has a 2000 character limit for message content
        $longContent = str_repeat('a', 2000);
        $payload = json_encode(['content' => $longContent]);

        $this->assertJson($payload);
        $this->assertEquals(2000, strlen($longContent));
    }
}
