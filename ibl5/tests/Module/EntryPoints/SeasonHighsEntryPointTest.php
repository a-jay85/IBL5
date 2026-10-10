<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use Module\ModuleRedirect;
use Records\RecordsController;

class SeasonHighsEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->onQuery('ibl_settings', [['setting_value' => 'Regular Season']]);
        $this->mockDb->onQuery('ibl_sim_dates', []);
        $this->mockDb->onQuery('ibl_schedule', []);
        $this->mockDb->setMockData([]);
    }

    /**
     * @param array<string, mixed> $query
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('iblQueryProvider')]
    public function testIblRedirectStubEmitsNoBody(array $query): void
    {
        $output = $this->runModule('SeasonHighs', $query);

        $this->assertSame('', $output);
        $this->assertQueryNotExecuted('ibl_settings');
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function iblQueryProvider(): array
    {
        return [
            'default phase' => [[]],
            'explicit phase' => [['seasonPhase' => 'Playoffs']],
            'empty phase' => [['seasonPhase' => '']],
        ];
    }

    public function testOlympicsRendersStandaloneSeasonHighs(): void
    {
        $_GET['league'] = 'olympics';
        $ctx = new \League\LeagueContext();

        $output = $this->runModule(
            'SeasonHighs',
            ['league' => 'olympics', 'seasonPhase' => 'Playoffs'],
            [],
            ['leagueContext' => $ctx] + $this->dbGlobals()
        );

        $this->assertStringContainsString('Playoffs', $output);
    }

    public function testRedirectUrlWithSeasonPhaseIncludesPhase(): void
    {
        $url = ModuleRedirect::passthroughUrl(
            'modules.php?name=Records&tab=' . RecordsController::TAB_THISSEASON,
            ['seasonPhase'],
            ['seasonPhase' => 'Playoffs']
        );
        $this->assertSame('modules.php?name=Records&tab=thisseason&seasonPhase=Playoffs', $url);
    }

    public function testRedirectUrlWithoutSeasonPhaseOmitsParam(): void
    {
        $url = ModuleRedirect::passthroughUrl(
            'modules.php?name=Records&tab=' . RecordsController::TAB_THISSEASON,
            ['seasonPhase'],
            []
        );
        $this->assertSame('modules.php?name=Records&tab=thisseason', $url);
    }

    public function testRedirectUrlWithEmptySeasonPhaseOmitsParam(): void
    {
        $url = ModuleRedirect::passthroughUrl(
            'modules.php?name=Records&tab=' . RecordsController::TAB_THISSEASON,
            ['seasonPhase'],
            ['seasonPhase' => '']
        );
        $this->assertSame('modules.php?name=Records&tab=thisseason', $url);
    }

    public function testRedirectUrlEncodesSpecialCharsInSeasonPhase(): void
    {
        $url = ModuleRedirect::passthroughUrl(
            'modules.php?name=Records&tab=' . RecordsController::TAB_THISSEASON,
            ['seasonPhase'],
            ['seasonPhase' => 'Regular Season']
        );
        $this->assertSame('modules.php?name=Records&tab=thisseason&seasonPhase=Regular%20Season', $url);
    }
}
