<?php

declare(strict_types=1);

namespace Tests\League;

use PHPUnit\Framework\TestCase;
use League\LeagueContext;

/**
 * Tests for BaseMysqliRepository::resolveTable() centralization
 * and league-aware repository construction.
 */
class LeagueContextTableResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        unset($_GET['league']);
        unset($_SESSION['current_league']);
        unset($_COOKIE['ibl_league']);
    }

    protected function tearDown(): void
    {
        unset($_GET['league']);
        unset($_SESSION['current_league']);
        unset($_COOKIE['ibl_league']);
    }

    public function testGetTableNameReturnsIblTableWhenIblContext(): void
    {
        $context = new LeagueContext();
        // Default is IBL — should return table unchanged
        $this->assertSame('ibl_standings', $context->getTableName('ibl_standings'));
        $this->assertSame('ibl_team_info', $context->getTableName('ibl_team_info'));
        $this->assertSame('ibl_box_scores', $context->getTableName('ibl_box_scores'));
    }

    public function testGetTableNameReturnsOlympicsTableWhenOlympicsContext(): void
    {
        $_GET['league'] = 'olympics';
        $context = new LeagueContext();

        $this->assertSame('ibl_olympics_standings', $context->getTableName('ibl_standings'));
        $this->assertSame('ibl_olympics_team_info', $context->getTableName('ibl_team_info'));
        $this->assertSame('ibl_olympics_box_scores', $context->getTableName('ibl_box_scores'));
        $this->assertSame('ibl_olympics_box_scores_teams', $context->getTableName('ibl_box_scores_teams'));
        $this->assertSame('ibl_olympics_schedule', $context->getTableName('ibl_schedule'));
        $this->assertSame('ibl_olympics_power', $context->getTableName('ibl_power'));
        $this->assertSame('ibl_olympics_league_config', $context->getTableName('ibl_league_config'));
    }

    public function testGetTableNameReturnsUnmappedTablesUnchanged(): void
    {
        $_GET['league'] = 'olympics';
        $context = new LeagueContext();

        // Tables not in the mapping should be returned unchanged
        $this->assertSame('ibl_plr', $context->getTableName('ibl_plr'));
        $this->assertSame('ibl_hist', $context->getTableName('ibl_hist'));
        $this->assertSame('ibl_awards', $context->getTableName('ibl_awards'));
        $this->assertSame('ibl_settings', $context->getTableName('ibl_settings'));
    }

    public function testIsOlympicsReturnsTrueForOlympicsContext(): void
    {
        $_GET['league'] = 'olympics';
        $context = new LeagueContext();
        $this->assertTrue($context->isOlympics());
    }

    public function testIsOlympicsReturnsFalseForIblContext(): void
    {
        $context = new LeagueContext();
        $this->assertFalse($context->isOlympics());
    }

    public function testIblOnlyModulesDisabledInOlympicsContext(): void
    {
        $_GET['league'] = 'olympics';
        $context = new LeagueContext();

        $this->assertFalse($context->isModuleEnabled('Draft'));
        $this->assertFalse($context->isModuleEnabled('FreeAgency'));
        $this->assertFalse($context->isModuleEnabled('Trading'));
        $this->assertFalse($context->isModuleEnabled('Waivers'));
        $this->assertFalse($context->isModuleEnabled('Voting'));
    }

    public function testSharedModulesEnabledInOlympicsContext(): void
    {
        $_GET['league'] = 'olympics';
        $context = new LeagueContext();

        $this->assertTrue($context->isModuleEnabled('Standings'));
        $this->assertTrue($context->isModuleEnabled('Team'));
        $this->assertTrue($context->isModuleEnabled('Player'));
        $this->assertTrue($context->isModuleEnabled('SeasonLeaderboards'));
    }

    public function testAllModulesEnabledInIblContext(): void
    {
        $context = new LeagueContext();

        $this->assertTrue($context->isModuleEnabled('Draft'));
        $this->assertTrue($context->isModuleEnabled('FreeAgency'));
        $this->assertTrue($context->isModuleEnabled('Trading'));
        $this->assertTrue($context->isModuleEnabled('Standings'));
        $this->assertTrue($context->isModuleEnabled('Team'));
    }
}
