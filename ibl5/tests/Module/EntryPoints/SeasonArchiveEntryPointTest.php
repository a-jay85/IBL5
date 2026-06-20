<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * Routing characterization tests for modules/SeasonArchive/index.php.
 *
 * Pins the three routing branches (index, detail, null-fall-through) without
 * asserting on byte-exact output — that is owned by SeasonArchiveGoldenMasterTest.
 */
class SeasonArchiveEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->setMockData([]);
    }

    public function testYearZeroRoutesToIndex(): void
    {
        $output = $this->runModule('SeasonArchive', get: ['year' => '0']);

        $this->assertStringContainsString('IBL Season Archive', $output);
        $this->assertStringNotContainsString('season-archive-nav', $output);
    }

    public function testYearAbsentRoutesToIndex(): void
    {
        $output = $this->runModule('SeasonArchive');

        $this->assertStringContainsString('IBL Season Archive', $output);
        $this->assertStringNotContainsString('season-archive-nav', $output);
    }

    public function testYearPresentWithSeasonDataRoutesToDetail(): void
    {
        // Award row → getSeasonDetail(1989) returns non-null → detail page renders.
        $this->mockDb->onQuery('ibl_awards', [
            ['year' => 1989, 'award' => 'Most Valuable Player (1st)', 'name' => 'Arvydas Sabonis', 'table_id' => 1],
        ]);

        $output = $this->runModule('SeasonArchive', get: ['year' => '1989']);

        $this->assertStringContainsString('season-archive-nav', $output);
        $this->assertStringContainsString('Back to Season Archive', $output);
        $this->assertStringNotContainsString('IBL Season Archive', $output);
    }

    public function testYearPresentWithNullSeasonDataFallsThroughToIndex(): void
    {
        // Empty data → getAwardsByYear(9999) returns [] → getSeasonDetail returns null
        // → code falls through to renderIndex() → index title renders.
        $output = $this->runModule('SeasonArchive', get: ['year' => '9999']);

        $this->assertStringContainsString('IBL Season Archive', $output);
        $this->assertStringNotContainsString('season-archive-nav', $output);
    }

    public function testNonNumericYearCastsToZeroAndRoutesToIndex(): void
    {
        $output = $this->runModule('SeasonArchive', get: ['year' => 'garbage']);

        $this->assertStringContainsString('IBL Season Archive', $output);
        $this->assertStringNotContainsString('season-archive-nav', $output);
    }
}
