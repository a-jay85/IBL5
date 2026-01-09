<?php

declare(strict_types=1);

namespace Tests\CapInfo;

use PHPUnit\Framework\TestCase;
use CapInfo\CapInfoView;
use CapInfo\Contracts\CapInfoViewInterface;

/**
 * CapInfoViewTest - Tests for CapInfoView HTML rendering
 *
 * @covers \CapInfo\CapInfoView
 */
class CapInfoViewTest extends TestCase
{
    private CapInfoViewInterface $view;

    protected function setUp(): void
    {
        $this->view = new CapInfoView();
    }

    public function testImplementsCapInfoViewInterface(): void
    {
        $this->assertInstanceOf(CapInfoViewInterface::class, $this->view);
    }

    public function testRenderReturnsString(): void
    {
        $result = $this->view->render([], 2024, 2025, null);

        $this->assertIsString($result);
    }

    public function testRenderContainsTableStructure(): void
    {
        $result = $this->view->render([], 2024, 2025, null);

        $this->assertStringContainsString('<table', $result);
        $this->assertStringContainsString('</table>', $result);
    }

    public function testRenderContainsTableHeaders(): void
    {
        $result = $this->view->render([], 2024, 2025, null);

        $this->assertStringContainsString('Team', $result);
        $this->assertStringContainsString('MLE', $result);
        $this->assertStringContainsString('LLE', $result);
    }

    public function testRenderIncludesYearHeaders(): void
    {
        $beginningYear = 2024;
        $endingYear = 2025;
        $result = $this->view->render([], $beginningYear, $endingYear, null);

        $this->assertStringContainsString((string) $endingYear, $result);
        $this->assertStringContainsString((string) ($endingYear + 1), $result);
    }

    public function testRenderEscapesHtmlEntities(): void
    {
        $teams = [
            [
                'teamID' => 1,
                'teamCity' => 'Test<script>',
                'teamName' => 'Team&Name',
                'color1' => '000000',
                'color2' => 'FFFFFF',
                'mle' => false,
                'lle' => false,
                'capData' => [],
            ],
        ];

        $result = $this->view->render($teams, 2024, 2025, null);

        // Should escape HTML entities
        $this->assertStringNotContainsString('<script>', $result);
    }
}
