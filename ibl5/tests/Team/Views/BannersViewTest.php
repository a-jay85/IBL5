<?php

declare(strict_types=1);

namespace Tests\Team\Views;

use PHPUnit\Framework\TestCase;
use Team\Views\BannersView;

class BannersViewTest extends TestCase
{
    private BannersView $view;

    protected function setUp(): void
    {
        $this->view = new BannersView();
    }

    public function testRendersAllBannerGroups(): void
    {
        $data = [
            'teamName' => 'Miami Heat',
            'color1' => 'FF0000',
            'color2' => 'FFFFFF',
            'championships' => [
                'banners' => [
                    ['year' => 2006, 'name' => 'Miami Heat', 'label' => 'IBL Champions', 'bgImage' => './images/banners/banner1.gif'],
                ],
                'textSummary' => '2006',
            ],
            'conferenceTitles' => [
                'banners' => [
                    ['year' => 2006, 'name' => 'Miami Heat', 'label' => 'Eastern Conf. Champions', 'bgImage' => './images/banners/banner2.gif'],
                ],
                'textSummary' => '2006',
            ],
            'divisionTitles' => [
                'banners' => [
                    ['year' => 2005, 'name' => 'Miami Heat', 'label' => 'Atlantic Div. Champions', 'bgImage' => null],
                ],
                'textSummary' => '2005',
            ],
        ];

        $html = $this->view->render($data);

        $this->assertStringContainsString('Miami Heat Banners', $html);
        $this->assertStringContainsString('IBL Champions', $html);
        $this->assertStringContainsString('Eastern Conf. Champions', $html);
        $this->assertStringContainsString('Atlantic Div. Champions', $html);
    }

    public function testReturnsEmptyWhenNoData(): void
    {
        $data = [
            'teamName' => 'Miami Heat',
            'color1' => 'FF0000',
            'color2' => 'FFFFFF',
            'championships' => ['banners' => [], 'textSummary' => ''],
            'conferenceTitles' => ['banners' => [], 'textSummary' => ''],
            'divisionTitles' => ['banners' => [], 'textSummary' => ''],
        ];

        $html = $this->view->render($data);
        $this->assertSame('', $html);
    }

    public function testRendersRowsOfFive(): void
    {
        $banners = [];
        for ($i = 1; $i <= 7; $i++) {
            $banners[] = ['year' => 2000 + $i, 'name' => 'Team', 'label' => 'IBL Champions', 'bgImage' => './images/banners/banner1.gif'];
        }

        $data = [
            'teamName' => 'Team',
            'color1' => '000000',
            'color2' => 'FFFFFF',
            'championships' => ['banners' => $banners, 'textSummary' => ''],
            'conferenceTitles' => ['banners' => [], 'textSummary' => ''],
            'divisionTitles' => ['banners' => [], 'textSummary' => ''],
        ];

        $html = $this->view->render($data);

        // 7 banners should produce 2 rows (5 + 2)
        $this->assertSame(2, substr_count($html, '<tr><td align="center"><table><tr>'));
    }
}
