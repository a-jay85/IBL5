<?php

declare(strict_types=1);

namespace Tests\PowerRankings;

use PHPUnit\Framework\TestCase;
use PowerRankings\PowerRankingsView;
use PowerRankings\Contracts\PowerRankingsViewInterface;

/**
 * PowerRankingsViewTest - Tests for PowerRankingsView HTML rendering
 *
 * @covers \PowerRankings\PowerRankingsView
 */
class PowerRankingsViewTest extends TestCase
{
    private PowerRankingsViewInterface $view;

    protected function setUp(): void
    {
        $this->view = new PowerRankingsView();
    }

    public function testImplementsPowerRankingsViewInterface(): void
    {
        $this->assertInstanceOf(PowerRankingsViewInterface::class, $this->view);
    }

    public function testRenderReturnsString(): void
    {
        $result = $this->view->render([], 2025);

        $this->assertIsString($result);
    }

    public function testRenderContainsTableStructure(): void
    {
        $result = $this->view->render([], 2025);

        $this->assertStringContainsString('<table', $result);
        $this->assertStringContainsString('</table>', $result);
    }

    public function testRenderContainsTableHeaders(): void
    {
        $result = $this->view->render([], 2025);

        $this->assertStringContainsString('Rank', $result);
        $this->assertStringContainsString('Team', $result);
    }

    public function testRenderContainsTitle(): void
    {
        $result = $this->view->render([], 2025);

        $this->assertStringContainsString('Power Rankings', $result);
    }

    public function testRenderEscapesHtmlEntities(): void
    {
        $rankings = [
            [
                'teamid' => 1,
                'team_city' => 'Test<script>',
                'team_name' => 'Team&Name',
                'color1' => '000000',
                'color2' => 'FFFFFF',
                'ranking' => 1,
                'prev' => 1,
            ],
        ];

        $result = $this->view->render($rankings, 2025);

        $this->assertStringNotContainsString('<script>', $result);
    }
}
