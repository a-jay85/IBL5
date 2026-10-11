<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class ContractsEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->onQuery('ibl_settings', [['value' => 'Regular Season']]);
        $this->mockDb->onQuery('ibl_sim_dates', []);
    }

    public function testDefaultTabRendersCapSpaceContent(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Contracts');

        $this->assertStringContainsString('Cap Info', $output);
        $this->assertStringContainsString('<a class="ibl-tab ibl-tab--active" href="modules.php?name=Contracts&amp;tab=teams"', $output);
        $this->assertStringNotContainsString('Master Contract List', $output);
    }

    public function testPlayersTabRendersContractListContent(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Contracts', ['tab' => 'players']);

        $this->assertStringContainsString('Master Contract List', $output);
        $this->assertStringContainsString('<a class="ibl-tab ibl-tab--active" href="modules.php?name=Contracts&amp;tab=players"', $output);
        $this->assertQueryExecuted('ibl_plr');
        $this->assertStringNotContainsString('Cap Info', $output);
    }

    public function testUnknownTabFallsBackToTeams(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Contracts', ['tab' => 'bogus']);

        $this->assertStringContainsString('Cap Info', $output);
        $this->assertStringNotContainsString('Master Contract List', $output);
        $this->assertStringNotContainsString('bogus', $output);
    }

    public function testArrayTabParamFallsBackToTeams(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Contracts', ['tab' => ['players']]);

        $this->assertStringContainsString('Cap Info', $output);
        $this->assertStringNotContainsString('Master Contract List', $output);
    }
}
