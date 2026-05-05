<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class SeasonHighsEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->onQuery('ibl_settings', [['value' => 'Regular Season']]);
        $this->mockDb->onQuery('ibl_sim_dates', []);
        $this->mockDb->onQuery('ibl_schedule', []);
        $this->mockDb->setMockData([]);
    }

    public function testRendersWithDefaultPhase(): void
    {
        $output = $this->runModule('SeasonHighs');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Regular Season', $output);
    }

    public function testRendersWithExplicitPhase(): void
    {
        $output = $this->runModule('SeasonHighs', ['seasonPhase' => 'Playoffs']);

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Playoffs', $output);
    }

    public function testRendersWithEmptyPhaseFallsBackToCurrent(): void
    {
        $output = $this->runModule('SeasonHighs', ['seasonPhase' => '']);

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Regular Season', $output);
    }
}
