<?php

declare(strict_types=1);

namespace Tests\Module;

use League\LeagueContext;
use Module\ModuleAccessControl;
use PHPUnit\Framework\TestCase;

class ModuleAccessControlTest extends TestCase
{
    private function createAccessControl(string $phase, string $triviaMode = 'Off'): ModuleAccessControl
    {
        $season = $this->createStub(\Season::class);
        $season->phase = $phase;

        $leagueContext = $this->createStub(LeagueContext::class);
        $leagueContext->method('isModuleEnabled')->willReturn(true);

        $mockDb = new \MockDatabase();
        $mockDb->setMockData([
            ['value' => $triviaMode],
        ]);

        return new ModuleAccessControl($season, $leagueContext, $mockDb);
    }

    private function createAccessControlWithDisabledModule(string $phase, string $disabledModule): ModuleAccessControl
    {
        $season = $this->createStub(\Season::class);
        $season->phase = $phase;

        $leagueContext = $this->createStub(LeagueContext::class);
        $leagueContext->method('isModuleEnabled')->willReturnCallback(
            static fn (string $module): bool => $module !== $disabledModule
        );

        $mockDb = new \MockDatabase();
        $mockDb->setMockData([
            ['value' => 'Off'],
        ]);

        return new ModuleAccessControl($season, $leagueContext, $mockDb);
    }

    // ============================================
    // GENERAL ACCESS TESTS
    // ============================================

    public function testNonRestrictedModuleIsAccessible(): void
    {
        $control = $this->createAccessControl('Regular Season');

        $this->assertTrue($control->isModuleAccessible('Standings'));
    }

    public function testNonRestrictedModuleAccessibleDuringDraft(): void
    {
        $control = $this->createAccessControl('Draft');

        $this->assertTrue($control->isModuleAccessible('Standings'));
    }

    // ============================================
    // PHASE RESTRICTION TESTS
    // ============================================

    public function testDraftModuleAccessibleDuringDraftPhase(): void
    {
        $control = $this->createAccessControl('Draft');

        $this->assertTrue($control->isModuleAccessible('Draft'));
    }

    public function testDraftModuleNotAccessibleDuringRegularSeason(): void
    {
        $control = $this->createAccessControl('Regular Season');

        $this->assertFalse($control->isModuleAccessible('Draft'));
    }

    public function testFreeAgencyModuleAccessibleDuringFreeAgencyPhase(): void
    {
        $control = $this->createAccessControl('Free Agency');

        $this->assertTrue($control->isModuleAccessible('FreeAgency'));
    }

    public function testFreeAgencyModuleNotAccessibleDuringPlayoffs(): void
    {
        $control = $this->createAccessControl('Playoffs');

        $this->assertFalse($control->isModuleAccessible('FreeAgency'));
    }

    public function testDraftModuleNotAccessibleDuringFreeAgency(): void
    {
        $control = $this->createAccessControl('Free Agency');

        $this->assertFalse($control->isModuleAccessible('Draft'));
    }

    // ============================================
    // TRIVIA MODE TESTS
    // ============================================

    public function testPlayerModuleHiddenWhenTriviaModeOn(): void
    {
        $control = $this->createAccessControl('Regular Season', 'On');

        $this->assertFalse($control->isModuleAccessible('Player'));
    }

    public function testCareerLeaderboardsHiddenWhenTriviaModeOn(): void
    {
        $control = $this->createAccessControl('Regular Season', 'On');

        $this->assertFalse($control->isModuleAccessible('CareerLeaderboards'));
    }

    public function testSeasonLeaderboardsHiddenWhenTriviaModeOn(): void
    {
        $control = $this->createAccessControl('Regular Season', 'On');

        $this->assertFalse($control->isModuleAccessible('SeasonLeaderboards'));
    }

    public function testPlayerModuleVisibleWhenTriviaModeOff(): void
    {
        $control = $this->createAccessControl('Regular Season', 'Off');

        $this->assertTrue($control->isModuleAccessible('Player'));
    }

    public function testNonTriviaModuleAccessibleWhenTriviaModeOn(): void
    {
        $control = $this->createAccessControl('Regular Season', 'On');

        $this->assertTrue($control->isModuleAccessible('Standings'));
    }

    // ============================================
    // LEAGUE CONTEXT TESTS
    // ============================================

    public function testModuleBlockedByLeagueContext(): void
    {
        $control = $this->createAccessControlWithDisabledModule('Regular Season', 'Trading');

        $this->assertFalse($control->isModuleAccessible('Trading'));
    }

    public function testOtherModulesNotBlockedByLeagueContext(): void
    {
        $control = $this->createAccessControlWithDisabledModule('Regular Season', 'Trading');

        $this->assertTrue($control->isModuleAccessible('Standings'));
    }
}
