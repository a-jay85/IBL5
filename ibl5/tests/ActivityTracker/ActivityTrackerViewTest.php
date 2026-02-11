<?php

declare(strict_types=1);

namespace Tests\ActivityTracker;

use ActivityTracker\ActivityTrackerView;
use ActivityTracker\Contracts\ActivityTrackerViewInterface;
use PHPUnit\Framework\TestCase;

class ActivityTrackerViewTest extends TestCase
{
    private ActivityTrackerView $view;

    protected function setUp(): void
    {
        $this->view = new ActivityTrackerView();
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(ActivityTrackerViewInterface::class, $this->view);
    }

    public function testRenderReturnsHtmlWithTitle(): void
    {
        $html = $this->view->render([]);

        $this->assertStringContainsString('Activity Tracker', $html);
        $this->assertStringContainsString('ibl-data-table', $html);
    }

    public function testRenderShowsTableHeaders(): void
    {
        $html = $this->view->render([]);

        $this->assertStringContainsString('Team', $html);
        $this->assertStringContainsString('Sim Depth Chart', $html);
        $this->assertStringContainsString('Last Depth Chart', $html);
        $this->assertStringContainsString('ASG Ballot', $html);
        $this->assertStringContainsString('EOY Ballot', $html);
    }

    public function testRenderShowsTeamData(): void
    {
        $teams = [
            [
                'teamid' => 1,
                'team_name' => 'Hawks',
                'team_city' => 'Atlanta',
                'color1' => 'E03A3E',
                'color2' => 'C1D32F',
                'depth' => '2025-01-15',
                'sim_depth' => '2025-01-14',
                'asg_vote' => 'Yes',
                'eoy_vote' => 'No',
            ],
        ];

        $html = $this->view->render($teams);

        $this->assertStringContainsString('2025-01-15', $html);
        $this->assertStringContainsString('2025-01-14', $html);
        $this->assertStringContainsString('Yes', $html);
        $this->assertStringContainsString('data-team-id="1"', $html);
    }
}
