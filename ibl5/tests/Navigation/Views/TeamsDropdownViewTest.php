<?php

declare(strict_types=1);

namespace Tests\Navigation\Views;

use Navigation\Views\TeamsDropdownView;
use PHPUnit\Framework\TestCase;

class TeamsDropdownViewTest extends TestCase
{
    private TeamsDropdownView $view;

    /** @var array<string, array<string, list<array{teamid: int, team_name: string, team_city: string}>>> */
    private array $teamsData;

    protected function setUp(): void
    {
        $this->view = new TeamsDropdownView();
        $this->teamsData = [
            'Eastern' => [
                'Atlantic' => [
                    ['teamid' => 1, 'team_name' => 'Celtics', 'team_city' => 'Boston'],
                    ['teamid' => 2, 'team_name' => 'Nets', 'team_city' => 'Brooklyn'],
                ],
                'Central' => [
                    ['teamid' => 3, 'team_name' => 'Bulls', 'team_city' => 'Chicago'],
                ],
            ],
            'Western' => [
                'Pacific' => [
                    ['teamid' => 4, 'team_name' => 'Lakers', 'team_city' => 'Los Angeles'],
                ],
            ],
        ];
    }

    public function testDesktopRendersConferenceHeaders(): void
    {
        $html = $this->view->renderDesktop($this->teamsData);

        $this->assertStringContainsString('Western Conference', $html);
        $this->assertStringContainsString('Eastern Conference', $html);
    }

    public function testDesktopRendersTeamLinksWithLogos(): void
    {
        $html = $this->view->renderDesktop($this->teamsData);

        $this->assertStringContainsString('Boston Celtics', $html);
        $this->assertStringContainsString('new1.png', $html);
        $this->assertStringContainsString('teamID=1', $html);
        $this->assertStringContainsString('Los Angeles Lakers', $html);
        $this->assertStringContainsString('new4.png', $html);
    }

    public function testDesktopRendersDivisionHeaders(): void
    {
        $html = $this->view->renderDesktop($this->teamsData);

        $this->assertStringContainsString('Atlantic', $html);
        $this->assertStringContainsString('Central', $html);
        $this->assertStringContainsString('Pacific', $html);
    }

    public function testMobileRendersTeamLinks(): void
    {
        $html = $this->view->renderMobile($this->teamsData, null);

        $this->assertStringContainsString('Boston Celtics', $html);
        $this->assertStringContainsString('Chicago Bulls', $html);
        $this->assertStringContainsString('Los Angeles Lakers', $html);
    }

    public function testMobileRendersUserConferenceFirst(): void
    {
        // Team 4 is in Western conference
        $html = $this->view->renderMobile($this->teamsData, 4);

        $westernPos = strpos($html, 'Western Conference');
        $easternPos = strpos($html, 'Eastern Conference');

        $this->assertNotFalse($westernPos);
        $this->assertNotFalse($easternPos);
        $this->assertLessThan($easternPos, $westernPos, 'User conference (Western) should appear before Eastern');
    }

    public function testMobileUsesNavTeamLogoCssClasses(): void
    {
        $html = $this->view->renderMobile($this->teamsData, null);

        $this->assertStringContainsString('nav-team-logo-container', $html);
        $this->assertStringContainsString('nav-team-logo-img', $html);
    }
}
