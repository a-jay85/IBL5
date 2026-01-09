<?php

declare(strict_types=1);

namespace Tests\TeamSchedule;

use PHPUnit\Framework\TestCase;
use TeamSchedule\TeamScheduleView;
use TeamSchedule\Contracts\TeamScheduleViewInterface;

/**
 * TeamScheduleViewTest - Tests for TeamScheduleView HTML rendering
 *
 * @covers \TeamSchedule\TeamScheduleView
 */
class TeamScheduleViewTest extends TestCase
{
    private TeamScheduleViewInterface $view;

    /** @var \Team&\PHPUnit\Framework\MockObject\MockObject */
    private \Team $mockTeam;

    protected function setUp(): void
    {
        $this->view = new TeamScheduleView();

        $this->mockTeam = $this->createMock(\Team::class);
        $this->mockTeam->teamID = 1;
        $this->mockTeam->name = 'Celtics';
        $this->mockTeam->city = 'Boston';
        $this->mockTeam->color1 = '007A33';
        $this->mockTeam->color2 = 'FFFFFF';
    }

    public function testImplementsTeamScheduleViewInterface(): void
    {
        $this->assertInstanceOf(TeamScheduleViewInterface::class, $this->view);
    }

    public function testRenderReturnsString(): void
    {
        $result = $this->view->render($this->mockTeam, [], 7);

        $this->assertIsString($result);
    }

    public function testRenderContainsTableStructure(): void
    {
        $result = $this->view->render($this->mockTeam, [], 7);

        $this->assertStringContainsString('<table', $result);
        $this->assertStringContainsString('</table>', $result);
    }

    public function testRenderContainsTableHeaders(): void
    {
        $result = $this->view->render($this->mockTeam, [], 7);

        // Check for schedule-related elements
        $this->assertStringContainsString('Schedule', $result);
    }
}
