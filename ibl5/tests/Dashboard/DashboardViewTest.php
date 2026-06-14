<?php

declare(strict_types=1);

namespace Tests\Dashboard;

use Dashboard\DashboardView;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Dashboard\DashboardView
 */
class DashboardViewTest extends TestCase
{
    /**
     * Build a fully-populated dashboard data array, overlaying any overrides.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function dashboardData(array $overrides = []): array
    {
        $base = [
            'teamId' => 1,
            'teamName' => 'Metros',
            'pendingTrades' => ['count' => 1, 'offers' => [
                ['oppositeTeam' => 'Stars', 'approval' => 'Pending', 'hasHammer' => true],
            ]],
            'nextSim' => ['opponent' => 'Stars', 'location' => 'vs', 'tier' => 'Contender', 'date' => '2026-01-15'],
            'cap' => ['headroom' => 3500],
            'upcomingFreeAgents' => [
                ['pid' => 10, 'name' => 'FA Guard', 'pos' => 'PG', 'teamid' => 1],
            ],
            'injuries' => [
                ['playerID' => 5, 'name' => 'Hurt Forward', 'position' => 'SF', 'daysRemaining' => 7, 'teamid' => 1],
            ],
            'news' => [
                ['sid' => 6, 'title' => 'Big Trade Shakes League', 'catTitle' => 'News'],
            ],
        ];

        return array_merge($base, $overrides);
    }

    public function testRendersAllSectionHeadings(): void
    {
        $view = new DashboardView();
        /** @phpstan-ignore argument.type */
        $html = $view->render($this->dashboardData());

        foreach (['Pending Trades', 'Next Sim', 'Cap Space', 'Upcoming Free Agents', 'Injuries', 'League News'] as $heading) {
            $this->assertStringContainsString($heading, $html, "Missing section heading: {$heading}");
        }
    }

    public function testEscapesPlayerNames(): void
    {
        $view = new DashboardView();
        /** @phpstan-ignore argument.type */
        $html = $view->render($this->dashboardData([
            'injuries' => [
                ['playerID' => 5, 'name' => '<script>x</script>', 'position' => 'SF', 'daysRemaining' => 7, 'teamid' => 1],
            ],
        ]));

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>x</script>', $html);
    }

    public function testRendersEmptyStateForInjuries(): void
    {
        $view = new DashboardView();
        /** @phpstan-ignore argument.type */
        $html = $view->render($this->dashboardData(['injuries' => []]));

        $this->assertStringContainsString('No injured players on your roster.', $html);
    }
}
