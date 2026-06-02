<?php

declare(strict_types=1);

namespace Tests\RookieOption;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Player\Player;
use RookieOption\RookieOptionFormView;

#[AllowMockObjectsWithoutExpectations]
final class RookieOptionFormViewXssTest extends TestCase
{
    public function testRenderFormEscapesXssInPlayerNamePositionAndTeamName(): void
    {
        $xss = '<script>alert(1)</script>';
        $escaped = '&lt;script&gt;';

        $player = $this->createMock(Player::class);
        $player->method('getPlayerID')->willReturn(1);
        $player->method('getName')->willReturn($xss);
        $player->method('getPosition')->willReturn($xss);

        $view = new RookieOptionFormView();
        $output = $view->renderForm($player, $xss, 100);

        $this->assertStringContainsString($escaped, $output);
        $this->assertStringNotContainsString($xss, $output);
    }
}
