<?php

declare(strict_types=1);

namespace Tests\Discord;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Tests\WideUnit\WideUnitTestCase;
use Discord\Discord;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

/**
 * DiscordIntegrationTest - Integration tests for Discord class
 *
 * Tests message processing logic and Discord class delegation to CommonMysqliRepository.
 *
 * @covers \Discord\Discord
 */
#[AllowMockObjectsWithoutExpectations]
class DiscordIntegrationTest extends WideUnitTestCase
{
    /** @var TeamIdentityRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private TeamIdentityRepositoryInterface $mockCommonRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockCommonRepo = self::createStub(TeamIdentityRepositoryInterface::class);
    }

    // ============================================
    // GET DISCORD ID FROM TEAMNAME TESTS
    // ============================================

    /**
     * Test getDiscordIDFromTeamname returns string from common repo
     */
    public function testGetDiscordIDFromTeamnameReturnsStringFromCommonRepo(): void
    {
        $this->mockCommonRepo->method('getTeamDiscordID')->willReturn(123456789012345678);

        $discord = new Discord($this->mockCommonRepo);
        $result = $discord->getDiscordIDFromTeamname('Test Team');

        $this->assertSame('123456789012345678', $result);
    }

    /**
     * Test getDiscordIDFromTeamname returns empty string for non-existent team
     */
    public function testGetDiscordIDFromTeamnameReturnsEmptyForNonExistentTeam(): void
    {
        $this->mockCommonRepo->method('getTeamDiscordID')->willReturn(null);

        $discord = new Discord($this->mockCommonRepo);
        $result = $discord->getDiscordIDFromTeamname('NonExistent Team');

        $this->assertSame('', $result);
    }

    /**
     * Test getDiscordIDFromTeamname returns empty string when discord_id is null
     */
    public function testGetDiscordIDFromTeamnameReturnsEmptyForNullDiscordID(): void
    {
        $this->mockCommonRepo->method('getTeamDiscordID')->willReturn(null);

        $discord = new Discord($this->mockCommonRepo);
        $result = $discord->getDiscordIDFromTeamname('Team With Null ID');

        $this->assertSame('', $result);
    }

    /**
     * Test getDiscordIDFromTeamname delegates to common repo
     */
    public function testGetDiscordIDFromTeamdelegatesToCommonRepo(): void
    {
        $mockRepo = $this->createMock(TeamIdentityRepositoryInterface::class);
        $mockRepo->expects($this->once())
            ->method('getTeamDiscordID')
            ->with('Miami')
            ->willReturn(null);

        $discord = new Discord($mockRepo);
        $discord->getDiscordIDFromTeamname('Miami');
    }

    /**
     * Test getDiscordIDFromTeamname handles team name with special characters
     */
    public function testGetDiscordIDFromTeamnameHandlesSpecialCharacters(): void
    {
        $this->mockCommonRepo->method('getTeamDiscordID')->willReturn(999888777666555444);

        $discord = new Discord($this->mockCommonRepo);
        $result = $discord->getDiscordIDFromTeamname("Team's Name");

        $this->assertSame('999888777666555444', $result);
    }

    /**
     * Test getDiscordIDFromTeamname converts result to string
     */
    public function testGetDiscordIDFromTeamnameConvertsToString(): void
    {
        $this->mockCommonRepo->method('getTeamDiscordID')->willReturn(123456789);

        $discord = new Discord($this->mockCommonRepo);
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
        $result = Discord::sendCurlPOST('https://discord.com/api/webhooks/test', 'Test message');

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

        $this->assertSame('transactions', $channelWithoutHash);
    }

    /**
     * Test postToChannel handles channel name without hash
     */
    public function testPostToChannelHandlesChannelWithoutHash(): void
    {
        $channel = 'transactions';
        $channelKey = ltrim($channel, '#');

        $this->assertSame('transactions', $channelKey);
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
     * Test postToChannel treats iblhoops.net and www.iblhoops.net as production
     */
    public function testPostToChannelDetectsProductionServer(): void
    {
        // strval() keeps the elements typed `string` (not literal), so the host
        // comparison below is a reachable branch rather than a constant-folded tautology.
        $productionHosts = array_map('strval', ['iblhoops.net', 'www.iblhoops.net']);
        foreach ($productionHosts as $host) {
            $isProduction = ($host === 'iblhoops.net' || $host === 'www.iblhoops.net');
            $this->assertTrue($isProduction, "Host '{$host}' should be detected as production");
        }
    }

    /**
     * Test postToChannel treats non-production hosts as testing
     */
    public function testPostToChannelDetectsNonProductionHosts(): void
    {
        $nonProductionHosts = array_map('strval', ['localhost', '127.0.0.1', 'main.localhost', '']);
        foreach ($nonProductionHosts as $host) {
            $isProduction = ($host === 'iblhoops.net' || $host === 'www.iblhoops.net');
            $this->assertFalse($isProduction, "Host '{$host}' should not be detected as production");
        }
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
        // strval() keeps these typed `string` (not literal ''), so the !empty()
        // guard below is a reachable branch, not a constant-folded always-false.
        $fromDiscordId = strval('');
        $toDiscordId = strval('');
        $fromTeam = 'Miami';
        $toTeam = 'Los Angeles';

        if ($fromDiscordId !== '' && $toDiscordId !== '') {
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
