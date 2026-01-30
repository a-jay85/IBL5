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

        // Round headers are abbreviated as R1, R2
        $this->assertStringContainsString('R1', $result);
        $this->assertStringContainsString('R2', $result);
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
        $this->assertStringContainsString('Team&amp;Name', $result);
        // City is no longer displayed, so only team name escaping matters
        // Should NOT contain the raw dangerous characters
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testTradedPickUsesOwningTeamColors(): void
    {
        $teams = [
            [
                'teamId' => 1,
                'teamCity' => 'Miami',
                'teamName' => 'Heat',
                'color1' => '98002E',
                'color2' => 'F9A01B',
                'picks' => [
                    ['ownerofpick' => 'Celtics', 'year' => '2025', 'round' => '1'],
                ],
            ],
            [
                'teamId' => 2,
                'teamCity' => 'Boston',
                'teamName' => 'Celtics',
                'color1' => '007A33',
                'color2' => 'FFFFFF',
                'picks' => [
                    ['ownerofpick' => 'Celtics', 'year' => '2025', 'round' => '1'],
                ],
            ],
        ];

        $result = $this->view->render($teams, 2025);

        // Heat's row should show Celtics' colors for the traded pick
        $this->assertStringContainsString('background-color: #007A33', $result);
        $this->assertStringContainsString('color: #FFFFFF', $result);
    }

    public function testOwnPickDoesNotUseInlineColors(): void
    {
        $teams = [
            [
                'teamId' => 1,
                'teamCity' => 'Miami',
                'teamName' => 'Heat',
                'color1' => '98002E',
                'color2' => 'F9A01B',
                'picks' => [
                    ['ownerofpick' => 'Heat', 'year' => '2025', 'round' => '1'],
                ],
            ],
        ];

        $result = $this->view->render($teams, 2025);

        // Own pick should use draft-pick-own class, not inline team colors
        $this->assertStringContainsString('draft-pick-own', $result);
        $this->assertStringNotContainsString('draft-pick-traded', $result);
    }

    public function testPickCellsLinkToTeamPage(): void
    {
        $teams = [
            [
                'teamId' => 1,
                'teamCity' => 'Miami',
                'teamName' => 'Heat',
                'color1' => '98002E',
                'color2' => 'F9A01B',
                'picks' => [
                    ['ownerofpick' => 'Heat', 'year' => '2025', 'round' => '1'],
                    ['ownerofpick' => 'Celtics', 'year' => '2025', 'round' => '2'],
                ],
            ],
            [
                'teamId' => 2,
                'teamCity' => 'Boston',
                'teamName' => 'Celtics',
                'color1' => '007A33',
                'color2' => 'FFFFFF',
                'picks' => [
                    ['ownerofpick' => 'Celtics', 'year' => '2025', 'round' => '1'],
                ],
            ],
        ];

        $result = $this->view->render($teams, 2025);

        // Own pick links to own team page
        $this->assertStringContainsString('href="modules.php?name=Team&amp;op=team&amp;teamID=1"', $result);
        // Traded pick links to owning team page
        $this->assertStringContainsString('href="modules.php?name=Team&amp;op=team&amp;teamID=2"', $result);
        // Links should have no underline
        $this->assertStringContainsString('text-decoration: none', $result);
    }
}
