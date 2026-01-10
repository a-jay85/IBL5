<?php

declare(strict_types=1);

namespace Tests\DraftPickLocator;

use PHPUnit\Framework\TestCase;
use DraftPickLocator\DraftPickLocatorView;
use DraftPickLocator\Contracts\DraftPickLocatorViewInterface;

/**
 * DraftPickLocatorViewTest - Tests for DraftPickLocatorView HTML rendering
 *
 * @covers \DraftPickLocator\DraftPickLocatorView
 */
class DraftPickLocatorViewTest extends TestCase
{
    private DraftPickLocatorViewInterface $view;

    protected function setUp(): void
    {
        $this->view = new DraftPickLocatorView();
    }

    public function testImplementsDraftPickLocatorViewInterface(): void
    {
        $this->assertInstanceOf(DraftPickLocatorViewInterface::class, $this->view);
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

    public function testRenderContainsTitle(): void
    {
        $result = $this->view->render([], 2025);

        $this->assertStringContainsString('Where', $result);
        $this->assertStringContainsString('Pick', $result);
    }

    public function testRenderContainsYearHeaders(): void
    {
        $currentYear = 2025;
        $result = $this->view->render([], $currentYear);

        $this->assertStringContainsString((string) $currentYear, $result);
        $this->assertStringContainsString((string) ($currentYear + 1), $result);
        $this->assertStringContainsString((string) ($currentYear + 2), $result);
    }

    public function testRenderContainsRoundHeaders(): void
    {
        $result = $this->view->render([], 2025);

        $this->assertStringContainsString('Round 1', $result);
        $this->assertStringContainsString('Round 2', $result);
    }

    public function testRenderEscapesHtmlEntities(): void
    {
        $teams = [
            [
                'teamID' => 1,
                'teamId' => 1,
                'teamCity' => 'Test<script>',
                'teamName' => 'Team&Name',
                'color1' => '000000',
                'color2' => 'FFFFFF',
                'picks' => [],
            ],
        ];

        $result = $this->view->render($teams, 2025);

        // Should escape HTML entities - verify the escaped versions appear
        $this->assertStringContainsString('Test&lt;script&gt;', $result);
        $this->assertStringContainsString('Team&amp;Name', $result);
        // Should NOT contain the raw dangerous characters
        $this->assertStringNotContainsString('<script>', $result);
    }
}
