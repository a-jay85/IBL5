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
        // Cold cache → single-flight rebuild: the request must win GET_LOCK to build inline.
        $this->mockDb->onQuery('GET_LOCK', [['got' => 1]]);
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
        $this->assertStringContainsString('data-tab="alltime"', $output);
        $this->assertSame(1, substr_count($output, 'ibl-tab--active'));
    }

    public function testByFranchiseTabRendersOnlyTheFranchiseRenderer(): void
    {
        $output = $this->runModule('Records', ['tab' => 'byfranchise', 'teamid' => '1'], [], $this->dbGlobals());

        $this->assertStringContainsString('data-tab="byfranchise"', $output);
        $this->assertQueryNotExecuted('ibl_awards');
    }

    public function testThisSeasonTabRendersExplicitPhase(): void
    {
        $output = $this->runModule('Records', ['tab' => 'thisseason', 'seasonPhase' => 'Playoffs'], [], $this->dbGlobals());

        $this->assertStringContainsString('data-tab="thisseason"', $output);
        $this->assertStringContainsString('Playoffs', $output);
        $this->assertQueryNotExecuted('ibl_awards');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unknownTabProvider')]
    public function testUnknownTabFallsBackToAllTime(mixed $tab): void
    {
        $output = $this->runModule('Records', ['tab' => $tab], [], $this->dbGlobals());

        $this->assertStringContainsString('data-tab="alltime"', $output);
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

        $this->assertStringContainsString('data-tab="thisseason"', $output);
    }
}
