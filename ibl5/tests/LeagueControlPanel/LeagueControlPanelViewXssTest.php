<?php

declare(strict_types=1);

namespace Tests\LeagueControlPanel;

use LeagueControlPanel\LeagueControlPanelView;
use PHPUnit\Framework\TestCase;

final class LeagueControlPanelViewXssTest extends TestCase
{
    private LeagueControlPanelView $view;

    protected function setUp(): void
    {
        $this->view = new LeagueControlPanelView();
    }

    public function testResultMessageIsEscaped(): void
    {
        $xss = '<script>alert(1)</script>';
        $escaped = '&lt;script&gt;';

        $output = $this->view->render(
            ['short_name' => 'ibl', 'full_name' => 'Internet Basketball League'],
            'ibl',
            [
                'phase' => 'Regular Season',
                'allowTrades' => 'Yes',
                'allowWaivers' => 'No',
                'showDraftLink' => 'Off',
                'freeAgencyNotifications' => 'Off',
                'triviaMode' => 'Off',
                'simLengthInDays' => 3,
                'seasonEndingYear' => 2026,
                'hasFinalsMvp' => false,
            ],
            $xss,
            false,
        );

        $this->assertStringContainsString($escaped, $output);
        $this->assertStringNotContainsString($xss, $output);
    }
}
