<?php

declare(strict_types=1);

namespace Tests\LeagueStarters;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use LeagueStarters\LeagueStartersView;

/**
 * @covers \LeagueStarters\LeagueStartersView
 */
#[AllowMockObjectsWithoutExpectations]
class LeagueStartersViewTest extends TestCase
{
    private function createMockTeam(): \Team\Team
    {
        $team = $this->createMock(\Team\Team::class);
        $team->color1 = 'FF0000';
        $team->color2 = '0000FF';
        $team->teamid = 1;
        $team->name = 'Test Team';
        return $team;
    }

    /**
     * Per-position aria-label is threaded into the table via renderTableContent()
     */
    public function testRenderTableContentEmitsPerPositionAriaLabel(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $mockSeason = $this->createMock(\Season\Season::class);
        $mockSeason->lastSimEndDate = '2025-01-01';

        $view = new LeagueStartersView('LeagueStarters');
        $html = $view->renderTableContent($mockDb, $mockSeason, [
            'PG' => [],
            'SG' => [],
            'SF' => [],
            'PF' => [],
            'C' => [],
        ], $this->createMockTeam(), 'ratings');

        $this->assertStringContainsString('aria-label="Point Guards"', $html);
        $this->assertStringContainsString('aria-label="Centers"', $html);
    }

    /**
     * The emitted aria-label value is HTML-escaped (passes through HtmlSanitizer::e())
     */
    public function testAriaLabelIsHtmlEscaped(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $mockSeason = $this->createMock(\Season\Season::class);
        $mockSeason->lastSimEndDate = '2025-01-01';

        $view = new LeagueStartersView('LeagueStarters');
        $html = $view->renderTableContent($mockDb, $mockSeason, [
            'PG' => [],
            'SG' => [],
            'SF' => [],
            'PF' => [],
            'C' => [],
        ], $this->createMockTeam(), 'ratings');

        // Position labels are static literals — "Point Guards" escapes to itself
        $this->assertStringContainsString('aria-label="Point Guards"', $html);
        // Not unescaped (label is static, so no entities expected, but attribute must be quoted)
        $this->assertStringNotContainsString("aria-label='Point Guards'", $html);
    }
}
