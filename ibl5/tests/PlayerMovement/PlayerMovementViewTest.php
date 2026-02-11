<?php

declare(strict_types=1);

namespace Tests\PlayerMovement;

use PlayerMovement\PlayerMovementView;
use PlayerMovement\Contracts\PlayerMovementViewInterface;
use PHPUnit\Framework\TestCase;

class PlayerMovementViewTest extends TestCase
{
    private PlayerMovementView $view;

    protected function setUp(): void
    {
        $this->view = new PlayerMovementView();
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(PlayerMovementViewInterface::class, $this->view);
    }

    public function testRenderReturnsHtmlWithTitle(): void
    {
        $html = $this->view->render([]);

        $this->assertStringContainsString('Player Movement', $html);
        $this->assertStringContainsString('ibl-data-table', $html);
    }

    public function testRenderShowsTableHeaders(): void
    {
        $html = $this->view->render([]);

        $this->assertStringContainsString('Player', $html);
        $this->assertStringContainsString('Old', $html);
        $this->assertStringContainsString('New', $html);
    }

    public function testRenderShowsPlayerData(): void
    {
        $movements = [
            [
                'pid' => 100,
                'name' => 'Test Player',
                'old_teamid' => 1,
                'old_team' => 'Hawks',
                'new_teamid' => 2,
                'new_team' => 'Celtics',
                'old_city' => 'Atlanta',
                'old_color1' => 'E03A3E',
                'old_color2' => 'C1D32F',
                'new_city' => 'Boston',
                'new_color1' => '007A33',
                'new_color2' => 'BA9653',
            ],
        ];

        $html = $this->view->render($movements);

        $this->assertStringContainsString('Test Player', $html);
        $this->assertStringContainsString('data-team-ids=', $html);
    }
}
