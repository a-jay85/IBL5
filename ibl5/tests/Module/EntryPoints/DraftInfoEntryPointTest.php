<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use Cache\PageCache;
use Module\ModuleRegistry;


/**
 * Integration tests for modules/DraftInfo/index.php entry point.
 *
 * Ports the 15 methods from the three old entry-point tests, then adds 9 new methods
 * for tab resolution, save_order, drag script, cache, and registry.
 */
class DraftInfoEntryPointTest extends ModuleEntryPointTestCase
{
    // --- Ported from DraftHistoryEntryPointTest (tab: history) ---

    public function testNoParamsShowsLatestDraftYear(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftInfo', ['tab' => 'history']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('draftyear');
    }

    public function testValidYearParam(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftInfo', ['tab' => 'history', 'year' => '2020']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('draftyear');
    }

    public function testYearZeroPassedThroughAsZero(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftInfo', ['tab' => 'history', 'year' => '0']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('draftyear');
    }

    public function testNegativeYearParam(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftInfo', ['tab' => 'history', 'year' => '-5']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('draftyear');
    }

    public function testNonNumericYearParam(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftInfo', ['tab' => 'history', 'year' => 'abc']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('draftyear');
    }

    public function testValidTeamIdShowsTeamHistory(): void
    {
        $this->mockDb->setMockTeamData([self::fullTeamData(['teamid' => 3, 'team_name' => 'TestTeam'])]);
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftInfo', ['tab' => 'history', 'teamid' => '3']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_team_info');
    }

    public function testTeamIdTakesPriorityOverYear(): void
    {
        $this->mockDb->setMockTeamData([self::fullTeamData(['teamid' => 3, 'team_name' => 'TestTeam'])]);
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftInfo', ['tab' => 'history', 'teamid' => '3', 'year' => '2020']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_team_info');
    }

    public function testTeamIdZeroShowsYearView(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftInfo', ['tab' => 'history', 'teamid' => '0']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('draftyear');
    }

    public function testOpApiReturnsHtmlFragment(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftInfo', ['op' => 'api']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('draftyear');
    }

    public function testOpApiWithValidYearReturnsHtml(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftInfo', ['op' => 'api', 'year' => '2020']);

        $this->assertNotEmpty($output);
    }

    public function testOpApiWithNonNumericYearFallsBackToLatest(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftInfo', ['op' => 'api', 'year' => 'garbage']);

        $this->assertNotEmpty($output);
    }

    public function testNonNumericTeamIdCastsToZero(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('DraftInfo', ['tab' => 'history', 'teamid' => 'garbage']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('draftyear');
    }

    // --- Ported from DraftPickLocatorEntryPointTest (tab: picks) ---

    public function testRendersDraftPickLocator(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DraftInfo', ['tab' => 'picks']);

        $this->assertNotEmpty($output);
    }

    // --- Ported from ProjectedDraftOrderEntryPointTest (no tab — default order) ---

    public function testRendersProjectedOrderWhenNotFinalized(): void
    {
        $this->mockDb->onQuery('Draft Order Finalized', [['value' => 'No']]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DraftInfo');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Projected Draft Order', $output);
    }

    public function testRendersFinalizedOrderWhenFinalized(): void
    {
        $this->mockDb->onQuery('Draft Order Finalized', [['value' => 'Yes']]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DraftInfo');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Draft Order', $output);
        $this->assertStringNotContainsString('Projected Draft Order', $output);
    }

    // --- New methods ---

    public function testNoTabDefaultsToOrderTab(): void
    {
        $this->mockDb->onQuery('Draft Order Finalized', [['value' => 'No']]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DraftInfo');

        $this->assertStringContainsString('class="ibl-tab-panel" data-tab="order"', $output);
        $this->assertStringContainsString('data-tab="order" aria-current="page"', $output);
    }

    public function testUnknownTabFallsBackToOrderTab(): void
    {
        $this->mockDb->onQuery('Draft Order Finalized', [['value' => 'No']]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DraftInfo', ['tab' => 'bogus']);

        $this->assertStringContainsString('class="ibl-tab-panel" data-tab="order"', $output);
    }

    public function testNonStringTabFallsBackToOrderTab(): void
    {
        $this->mockDb->onQuery('Draft Order Finalized', [['value' => 'No']]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DraftInfo', ['tab' => ['picks']]);

        $this->assertStringContainsString('class="ibl-tab-panel" data-tab="order"', $output);
    }

    public function testPicksTabRendersOnlyPicksBody(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DraftInfo', ['tab' => 'picks']);

        $this->assertStringContainsString('class="ibl-tab-panel" data-tab="picks"', $output);
        $this->assertStringNotContainsString('Projected Draft Order', $output);
    }

    public function testHistoryTabWithTeamIdRendersTeamHistory(): void
    {
        $this->mockDb->setMockTeamData([self::fullTeamData(['teamid' => 1, 'team_name' => 'TestTeam'])]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DraftInfo', ['tab' => 'history', 'teamid' => '1']);

        $this->assertQueryExecuted('ibl_team_info');
        $this->assertStringContainsString('class="ibl-tab-panel" data-tab="history"', $output);
    }

    public function testSaveOrderRejectsNonAdmin(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DraftInfo', ['op' => 'save_order']);

        $this->assertStringContainsString('"error":"Unauthorized"', $output);
        $this->assertStringNotContainsString('ibl-tabs', $output);
    }

    public function testOrderTabOmitsDragScriptForAnonymous(): void
    {
        $this->mockDb->onQuery('Draft Order Finalized', [['value' => 'No']]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DraftInfo');

        $this->assertStringNotContainsString('draft-order-drag.js', $output);
    }

    public function testDraftInfoAndDraftHistoryAreNotPageCacheable(): void
    {
        $this->assertFalse(PageCache::isCacheable('DraftInfo'));
        $this->assertFalse(PageCache::isCacheable('DraftHistory'));
    }

    public function testDraftInfoIsRegisteredModule(): void
    {
        $this->assertTrue(ModuleRegistry::isValid('DraftInfo'));
    }
}
