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
        $player = $this->createStub(Player::class);
        $player->name = self::XSS_PAYLOAD;
        $player->nickname = '';
        $player->position = 'PG';
        $player->teamName = 'Test Team';
        $player->teamid = 1;
        $player->age = 25;
        $player->heightFeet = 6;
        $player->heightInches = 3;
        $player->weightPounds = 200;
        $player->collegeName = 'Test U';
        $player->draftRound = 1;
        $player->draftPickNumber = 1;
        $player->draftTeamOriginalName = 'Test';
        $player->draftYear = 2020;

        $data = CardBaseStyles::preparePlayerData($player, 1);

        $this->assertStringNotContainsString(self::XSS_PAYLOAD, $data['name']);
        $this->assertStringContainsString(self::XSS_ENCODED, $data['name']);
    }

    public function testPreparePlayerDataEscapesNickname(): void
    {
        $player = $this->createStub(Player::class);
        $player->name = 'Normal Name';
        $player->nickname = self::XSS_PAYLOAD;
        $player->position = 'PG';
        $player->teamName = 'Test Team';
        $player->teamid = 1;
        $player->age = 25;
        $player->heightFeet = 6;
        $player->heightInches = 3;
        $player->weightPounds = 200;
        $player->collegeName = 'Test U';
        $player->draftRound = 1;
        $player->draftPickNumber = 1;
        $player->draftTeamOriginalName = 'Test';
        $player->draftYear = 2020;

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
