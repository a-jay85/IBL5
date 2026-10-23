<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

final class RecordsEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->setMockData([]);
        $this->mockDb->onQuery('cache', []);
        $this->mockDb->onQuery('ibl_settings', [['value' => 'Regular Season']]);
        $this->mockDb->onQuery('ibl_sim_dates', []);
        $this->mockDb->onQuery('ibl_schedule', []);
    }

    public function testDefaultTabRendersAllTimeRecords(): void
    {
        $output = $this->runModule('Records', [], [], $this->dbGlobals());

        $this->assertStringContainsString('Regular Season', $output);
        $this->assertStringContainsString('Playoffs', $output);
        $this->assertStringContainsString('H.E.A.T.', $output);
        $this->assertStringContainsString('class="ibl-tab-panel" data-tab="alltime"', $output);
        $this->assertSame(1, substr_count($output, 'ibl-tab--active'));
    }

    public function testByFranchiseTabRendersOnlyTheFranchiseRenderer(): void
    {
        $output = $this->runModule('Records', ['tab' => 'byfranchise', 'teamid' => '1'], [], $this->dbGlobals());

        $this->assertStringContainsString('class="ibl-tab-panel" data-tab="byfranchise"', $output);
        $this->assertQueryNotExecuted('ibl_awards');
    }

    public function testThisSeasonTabRendersExplicitPhase(): void
    {
        $output = $this->runModule('Records', ['tab' => 'thisseason', 'seasonPhase' => 'Playoffs'], [], $this->dbGlobals());

        $this->assertStringContainsString('class="ibl-tab-panel" data-tab="thisseason"', $output);
        $this->assertStringContainsString('Playoffs', $output);
        $this->assertQueryNotExecuted('ibl_awards');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unknownTabProvider')]
    public function testUnknownTabFallsBackToAllTime(mixed $tab): void
    {
        $output = $this->runModule('Records', ['tab' => $tab], [], $this->dbGlobals());

        $this->assertStringContainsString('class="ibl-tab-panel" data-tab="alltime"', $output);
        $this->assertStringNotContainsString('class="ibl-tab-panel" data-tab="byfranchise"', $output);
        $this->assertStringNotContainsString('class="ibl-tab-panel" data-tab="thisseason"', $output);
        $this->assertStringContainsString('Regular Season', $output);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unknownTabProvider(): array
    {
        return [
            'unknown key' => ['bogus'],
            'wrong case' => ['ALLTIME'],
            'array' => [['byfranchise']],
        ];
    }

    public function testNonStringSeasonPhaseFallsBackToCurrentPhase(): void
    {
        $output = $this->runModule('Records', ['tab' => 'thisseason', 'seasonPhase' => ['Playoffs']], [], $this->dbGlobals());

        $this->assertStringContainsString('class="ibl-tab-panel" data-tab="thisseason"', $output);
    }

    public function testZeroStringSeasonPhaseFallsBackToCurrentPhase(): void
    {
        $output = $this->runModule('Records', ['tab' => 'thisseason', 'seasonPhase' => '0'], [], $this->dbGlobals());

        $this->assertStringContainsString('class="ibl-tab-panel" data-tab="thisseason"', $output);
        $this->assertStringNotContainsString("Players' 0 Highs", $output);
    }
}
