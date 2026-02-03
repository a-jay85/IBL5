<?php

declare(strict_types=1);

namespace Tests\CapSpace;

use PHPUnit\Framework\TestCase;
use CapSpace\CapSpaceView;
use CapSpace\Contracts\CapSpaceViewInterface;

/**
 * CapSpaceViewTest - Tests for CapSpaceView HTML rendering
 *
 * @covers \CapSpace\CapSpaceView
 */
class CapSpaceViewTest extends TestCase
{
    private CapSpaceViewInterface $view;

    protected function setUp(): void
    {
        $this->view = new CapSpaceView();
    }

    public function testImplementsCapSpaceViewInterface(): void
    {
        $this->assertInstanceOf(CapSpaceViewInterface::class, $this->view);
    }

    public function testRenderReturnsString(): void
    {
        $result = $this->view->render([], 2024, 2025);

        $this->assertIsString($result);
    }

    public function testRenderContainsTableStructure(): void
    {
        $result = $this->view->render([], 2024, 2025);

        $this->assertStringContainsString('<table', $result);
        $this->assertStringContainsString('</table>', $result);
    }

    public function testRenderContainsTableHeaders(): void
    {
        $result = $this->view->render([], 2024, 2025);

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
                'teamId' => 1,
                'teamCity' => 'Test<script>',
                'teamName' => 'Team&Name',
                'color1' => '000000',
                'color2' => 'FFFFFF',
                'mle' => false,
                'lle' => false,
                'hasMLE' => false,
                'hasLLE' => false,
                'freeAgencySlots' => 0,
                'availableSalary' => [
                    'year1' => 0,
                    'year2' => 0,
                    'year3' => 0,
                    'year4' => 0,
                    'year5' => 0,
                    'year6' => 0,
                ],
                'positionSalaries' => [
                    'PG' => 0,
                    'SG' => 0,
                    'SF' => 0,
                    'PF' => 0,
                    'C' => 0,
                ],
            ],
        ];

        $result = $this->view->render($teams, 2024, 2025);

        // Should escape HTML entities - verify the escaped versions appear
        $this->assertStringContainsString('Team&amp;Name', $result);
        // City is no longer displayed, so only team name escaping matters
        // Should NOT contain the raw dangerous characters
        $this->assertStringNotContainsString('<script>', $result);
    }
}
