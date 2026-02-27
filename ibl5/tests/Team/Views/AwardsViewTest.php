<?php

declare(strict_types=1);

namespace Tests\Team\Views;

use PHPUnit\Framework\TestCase;
use Team\Views\AwardsView;

class AwardsViewTest extends TestCase
{
    private AwardsView $view;

    protected function setUp(): void
    {
        $this->view = new AwardsView();
    }

    public function testGmHistoryRendersBothSections(): void
    {
        $tenures = [
            ['id' => 1, 'franchise_id' => 1, 'gm_username' => 'JohnGM', 'start_season_year' => 2020, 'end_season_year' => null, 'is_mid_season_start' => 0, 'is_mid_season_end' => 0],
        ];
        $awards = [
            ['year' => 2022, 'Award' => 'GM of the Year', 'name' => 'JohnGM', 'table_ID' => 1],
        ];

        $html = $this->view->renderGmHistory($tenures, $awards);

        $this->assertStringContainsString('2020-Present', $html);
        $this->assertStringContainsString('JohnGM', $html);
        $this->assertStringContainsString('GM of the Year', $html);
        $this->assertStringContainsString('team-awards-list', $html);
    }

    public function testGmHistoryReturnsEmptyWhenNoData(): void
    {
        $html = $this->view->renderGmHistory([], []);
        $this->assertSame('', $html);
    }

    public function testGmHistoryRendersOnlyTenuresWhenNoAwards(): void
    {
        $tenures = [
            ['id' => 1, 'franchise_id' => 1, 'gm_username' => 'JohnGM', 'start_season_year' => 2020, 'end_season_year' => 2023, 'is_mid_season_start' => 0, 'is_mid_season_end' => 0],
        ];

        $html = $this->view->renderGmHistory($tenures, []);

        $this->assertStringContainsString('2020-2023', $html);
        $this->assertSame(1, substr_count($html, 'team-awards-list'));
    }

    public function testTeamAccomplishmentsRendersList(): void
    {
        $awards = [
            ['year' => 2020, 'Award' => 'Best Record'],
            ['year' => 2019, 'Award' => 'Division Champions'],
        ];

        $html = $this->view->renderTeamAccomplishments($awards);

        $this->assertStringContainsString('team-awards-list', $html);
        $this->assertStringContainsString('Best Record', $html);
        $this->assertStringContainsString('Division Champions', $html);
        $this->assertSame(2, substr_count($html, 'award-year'));
    }

    public function testTeamAccomplishmentsReturnsEmptyWhenNoData(): void
    {
        $html = $this->view->renderTeamAccomplishments([]);
        $this->assertSame('', $html);
    }

    public function testEscapesHtmlInAwardNames(): void
    {
        $awards = [
            ['year' => 2020, 'Award' => '<b>Hacked</b>'],
        ];

        $html = $this->view->renderTeamAccomplishments($awards);

        $this->assertStringNotContainsString('<b>Hacked</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
    }
}
