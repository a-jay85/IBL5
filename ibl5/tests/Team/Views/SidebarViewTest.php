<?php

declare(strict_types=1);

namespace Tests\Team\Views;

use PHPUnit\Framework\TestCase;
use Team\Views\SidebarView;

class SidebarViewTest extends TestCase
{
    private SidebarView $view;

    protected function setUp(): void
    {
        $this->view = new SidebarView();
    }

    public function testRendersCurrentSeasonCard(): void
    {
        $html = $this->view->renderCurrentSeasonCard(
            '<div class="team-info-list">content</div>',
            '--team-color-primary: #FF0000;'
        );

        $this->assertStringContainsString('team-card', $html);
        $this->assertStringContainsString('Current Season', $html);
        $this->assertStringContainsString('team-info-list', $html);
        $this->assertStringContainsString('--team-color-primary: #FF0000;', $html);
    }

    public function testRendersAwardsCardWithBothSections(): void
    {
        $html = $this->view->renderAwardsCard(
            '<ul class="team-awards-list"><li>GM tenure</li></ul>',
            '<ul class="team-awards-list"><li>Best Record</li></ul>',
            '--team-color-primary: #FF0000;'
        );

        $this->assertStringContainsString('Awards', $html);
        $this->assertStringContainsString('GM History', $html);
        $this->assertStringContainsString('Team Accomplishments', $html);
        $this->assertStringContainsString('GM tenure', $html);
        $this->assertStringContainsString('Best Record', $html);
    }

    public function testOmitsAwardsCardWhenEmpty(): void
    {
        $html = $this->view->renderAwardsCard('', '', '--team-color-primary: #FF0000;');

        $this->assertSame('', $html);
    }

    public function testRendersAwardsCardWithOnlyGmHistory(): void
    {
        $html = $this->view->renderAwardsCard(
            '<ul>GM data</ul>',
            '',
            '--team-color-primary: #FF0000;'
        );

        $this->assertStringContainsString('GM History', $html);
        $this->assertStringNotContainsString('Team Accomplishments', $html);
    }

    public function testRendersFranchiseHistoryCard(): void
    {
        $html = $this->view->renderFranchiseHistoryCard(
            '<ul>HEAT data</ul>',
            '<ul>Regular Season data</ul>',
            '<div>Playoff data</div>',
            '--team-color-primary: #FF0000;'
        );

        $this->assertStringContainsString('Franchise History', $html);
        $this->assertStringContainsString('H.E.A.T.', $html);
        $this->assertStringContainsString('Regular Season', $html);
        $this->assertStringContainsString('Playoffs', $html);
        $this->assertStringContainsString('franchise-history-columns', $html);
        $this->assertStringContainsString('HEAT data', $html);
        $this->assertStringContainsString('Regular Season data', $html);
        $this->assertStringContainsString('Playoff data', $html);
    }
}
