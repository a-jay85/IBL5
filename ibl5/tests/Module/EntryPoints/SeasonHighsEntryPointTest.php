<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

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

    // Redirect targets (seasonPhase passthrough, omission when empty, URL encoding) are
    // pinned by RecordsControllerTest::testLegacyRedirectUrl*. The Location header
    // cannot be captured in the CLI PHPUnit harness.

    public function testZeroStringPhaseFallsBackToCurrent(): void
    {
        $_GET['league'] = 'olympics';
        $ctx = new \League\LeagueContext();

        $output = $this->runModule(
            'SeasonHighs',
            ['league' => 'olympics', 'seasonPhase' => '0'],
            [],
            ['leagueContext' => $ctx] + $this->dbGlobals()
        );

        $this->assertNotSame('', $output);
        $this->assertStringContainsString('Regular Season', $output);
    }
}
