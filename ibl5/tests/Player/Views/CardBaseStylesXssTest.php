<?php

declare(strict_types=1);

namespace Tests\Player\Views;

use PHPUnit\Framework\TestCase;
use Player\Player;
use Player\Views\CardBaseStyles;

/**
 * @covers \Player\Views\CardBaseStyles
 */
class CardBaseStylesXssTest extends TestCase
{
    private const XSS_PAYLOAD = '<script>alert("xss")</script>';
    private const XSS_ENCODED = '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;';

    public function testPreparePlayerDataEscapesPlayerName(): void
    {
        $player = self::createStub(Player::class);
        $player->method('getName')->willReturn(self::XSS_PAYLOAD);
        $player->method('getNickname')->willReturn('');
        $player->method('getPosition')->willReturn('PG');
        $player->method('getTeamName')->willReturn('Test Team');
        $player->method('getTeamid')->willReturn(1);
        $player->method('getAge')->willReturn(25);
        $player->method('getHeightFeet')->willReturn(6);
        $player->method('getHeightInches')->willReturn(3);
        $player->method('getWeightPounds')->willReturn(200);
        $player->method('getCollegeName')->willReturn('Test U');
        $player->method('getDraftRound')->willReturn(1);
        $player->method('getDraftPickNumber')->willReturn(1);
        $player->method('getDraftTeamOriginalName')->willReturn('Test');
        $player->method('getDraftYear')->willReturn(2020);

        $data = CardBaseStyles::preparePlayerData($player, 1);

        $this->assertStringNotContainsString(self::XSS_PAYLOAD, $data['name']);
        $this->assertStringContainsString(self::XSS_ENCODED, $data['name']);
    }

    public function testPreparePlayerDataEscapesNickname(): void
    {
        $player = self::createStub(Player::class);
        $player->method('getName')->willReturn('Normal Name');
        $player->method('getNickname')->willReturn(self::XSS_PAYLOAD);
        $player->method('getPosition')->willReturn('PG');
        $player->method('getTeamName')->willReturn('Test Team');
        $player->method('getTeamid')->willReturn(1);
        $player->method('getAge')->willReturn(25);
        $player->method('getHeightFeet')->willReturn(6);
        $player->method('getHeightInches')->willReturn(3);
        $player->method('getWeightPounds')->willReturn(200);
        $player->method('getCollegeName')->willReturn('Test U');
        $player->method('getDraftRound')->willReturn(1);
        $player->method('getDraftPickNumber')->willReturn(1);
        $player->method('getDraftTeamOriginalName')->willReturn('Test');
        $player->method('getDraftYear')->willReturn(2020);

        $data = CardBaseStyles::preparePlayerData($player, 1);

        $this->assertStringNotContainsString(self::XSS_PAYLOAD, $data['nickname']);
        $this->assertStringContainsString(self::XSS_ENCODED, $data['nickname']);
    }

    public function testRenderCardTopDoesNotContainRawScriptTag(): void
    {
        $playerData = [
            'name' => self::XSS_ENCODED,
            'nickname' => '',
            'position' => 'PG',
            'teamName' => 'Test Team',
            'teamid' => 1,
            'age' => '25',
            'height' => "6'3\"",
            'weight' => '200',
            'college' => 'Test U',
            'draftYear' => 2020,
            'draftRound' => '1',
            'draftPick' => '1',
            'draftTeam' => 'Test',
            'imageUrl' => 'images/player/1.png',
        ];

        $html = CardBaseStyles::renderCardTop($playerData);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
