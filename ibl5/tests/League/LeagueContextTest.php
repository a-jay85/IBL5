<?php

declare(strict_types=1);

namespace Tests\League;

use PHPUnit\Framework\TestCase;
use League\LeagueContext;

/**
 * Tests for LeagueContext class
 */
class LeagueContextTest extends TestCase
{
    private $leagueContext;
    
    protected function setUp(): void
    {
        $this->leagueContext = new LeagueContext();
        
        // Clear all sources before each test
        unset($_GET['league']);
        unset($_SESSION['current_league']);
        unset($_COOKIE['ibl_league']);
    }
    
    protected function tearDown(): void
    {
        // Clean up after each test
        unset($_GET['league']);
        unset($_SESSION['current_league']);
        unset($_COOKIE['ibl_league']);
    }

    /**
     * Test getCurrentLeague defaults to 'ibl'
     */
    public function testGetCurrentLeagueDefaultsToIbl(): void
    {
        $result = $this->leagueContext->getCurrentLeague();
        $this->assertEquals('ibl', $result);
    }

    /**
     * Test getCurrentLeague returns value from $_GET (highest priority)
     */
    public function testGetCurrentLeagueFromGet(): void
    {
        $_GET['league'] = 'olympics';
        $_SESSION['current_league'] = 'ibl';
        $_COOKIE['ibl_league'] = 'ibl';
        
        $result = $this->leagueContext->getCurrentLeague();
        $this->assertEquals('olympics', $result);
    }

    /**
     * Test getCurrentLeague returns value from $_SESSION when $_GET not set
     */
    public function testGetCurrentLeagueFromSession(): void
    {
        $_SESSION['current_league'] = 'olympics';
        $_COOKIE['ibl_league'] = 'ibl';
        
        $result = $this->leagueContext->getCurrentLeague();
        $this->assertEquals('olympics', $result);
    }

    /**
     * Test getCurrentLeague returns value from $_COOKIE when $_GET and $_SESSION not set
     */
    public function testGetCurrentLeagueFromCookie(): void
    {
        $_COOKIE['ibl_league'] = 'olympics';
        
        $result = $this->leagueContext->getCurrentLeague();
        $this->assertEquals('olympics', $result);
    }

    /**
     * Test getCurrentLeague ignores invalid values and falls back
     */
    public function testGetCurrentLeagueIgnoresInvalidValues(): void
    {
        $_GET['league'] = 'invalid';
        $_SESSION['current_league'] = 'invalid';
        $_COOKIE['ibl_league'] = 'olympics';
        
        $result = $this->leagueContext->getCurrentLeague();
        $this->assertEquals('olympics', $result);
    }

    /**
     * Test getCurrentLeague defaults to ibl when all sources are invalid
     */
    public function testGetCurrentLeagueDefaultsWhenAllInvalid(): void
    {
        $_GET['league'] = 'invalid';
        $_SESSION['current_league'] = 'invalid';
        $_COOKIE['ibl_league'] = 'invalid';
        
        $result = $this->leagueContext->getCurrentLeague();
        $this->assertEquals('ibl', $result);
    }

    /**
     * Test setLeague with valid IBL league
     */
    public function testSetLeagueIbl(): void
    {
        $this->leagueContext->setLeague('ibl');
        
        $this->assertEquals('ibl', $_SESSION['current_league']);
    }

    /**
     * Test setLeague with valid Olympics league
     */
    public function testSetLeagueOlympics(): void
    {
        $this->leagueContext->setLeague('olympics');
        
        $this->assertEquals('olympics', $_SESSION['current_league']);
    }

    /**
     * Test setLeague throws exception for invalid league
     */
    public function testSetLeagueThrowsExceptionForInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid league: invalid');
        
        $this->leagueContext->setLeague('invalid');
    }

    /**
     * Test isModuleEnabled returns true for all modules in IBL
     */
    public function testIsModuleEnabledIblAllEnabled(): void
    {
        $_SESSION['current_league'] = 'ibl';
        
        $this->assertTrue($this->leagueContext->isModuleEnabled('Draft'));
        $this->assertTrue($this->leagueContext->isModuleEnabled('FreeAgency'));
        $this->assertTrue($this->leagueContext->isModuleEnabled('Trading'));
        $this->assertTrue($this->leagueContext->isModuleEnabled('Other_Module'));
    }

    /**
     * Test isModuleEnabled returns false for Draft in Olympics
     */
    public function testIsModuleEnabledOlympicsDisablesDraft(): void
    {
        $_SESSION['current_league'] = 'olympics';
        
        $this->assertFalse($this->leagueContext->isModuleEnabled('Draft'));
    }

    /**
     * Test isModuleEnabled returns false for DraftPickLocator in Olympics
     */
    public function testIsModuleEnabledOlympicsDisablesDraftPickLocator(): void
    {
        $_SESSION['current_league'] = 'olympics';

        $this->assertFalse($this->leagueContext->isModuleEnabled('DraftPickLocator'));
    }

    /**
     * Test isModuleEnabled returns false for FreeAgency in Olympics
     */
    public function testIsModuleEnabledOlympicsDisablesFreeAgency(): void
    {
        $_SESSION['current_league'] = 'olympics';

        $this->assertFalse($this->leagueContext->isModuleEnabled('FreeAgency'));
    }

    /**
     * Test isModuleEnabled returns false for Waivers in Olympics
     */
    public function testIsModuleEnabledOlympicsDisablesWaivers(): void
    {
        $_SESSION['current_league'] = 'olympics';
        
        $this->assertFalse($this->leagueContext->isModuleEnabled('Waivers'));
    }

    /**
     * Test isModuleEnabled returns false for Trading in Olympics
     */
    public function testIsModuleEnabledOlympicsDisablesTrading(): void
    {
        $_SESSION['current_league'] = 'olympics';
        
        $this->assertFalse($this->leagueContext->isModuleEnabled('Trading'));
    }

    /**
     * Test isModuleEnabled returns false for Voting in Olympics
     */
    public function testIsModuleEnabledOlympicsDisablesVoting(): void
    {
        $_SESSION['current_league'] = 'olympics';
        
        $this->assertFalse($this->leagueContext->isModuleEnabled('Voting'));
    }

    /**
     * Test isModuleEnabled returns false for VotingResults in Olympics
     */
    public function testIsModuleEnabledOlympicsDisablesVotingResults(): void
    {
        $_SESSION['current_league'] = 'olympics';

        $this->assertFalse($this->leagueContext->isModuleEnabled('VotingResults'));
    }

    /**
     * Test isModuleEnabled returns false for CapSpace in Olympics
     */
    public function testIsModuleEnabledOlympicsDisablesCapSpace(): void
    {
        $_SESSION['current_league'] = 'olympics';

        $this->assertFalse($this->leagueContext->isModuleEnabled('CapSpace'));
    }

    /**
     * Test isModuleEnabled returns false for FranchiseHistory in Olympics
     */
    public function testIsModuleEnabledOlympicsDisablesFranchiseHistory(): void
    {
        $_SESSION['current_league'] = 'olympics';

        $this->assertFalse($this->leagueContext->isModuleEnabled('FranchiseHistory'));
    }

    /**
     * Test isModuleEnabled returns true for non-restricted modules in Olympics
     */
    public function testIsModuleEnabledOlympicsEnablesOtherModules(): void
    {
        $_SESSION['current_league'] = 'olympics';
        
        $this->assertTrue($this->leagueContext->isModuleEnabled('Schedule'));
        $this->assertTrue($this->leagueContext->isModuleEnabled('Standings'));
        $this->assertTrue($this->leagueContext->isModuleEnabled('Other_Module'));
    }

    /**
     * Test getConfig returns IBL configuration
     */
    public function testGetConfigIbl(): void
    {
        $_SESSION['current_league'] = 'ibl';
        
        $config = $this->leagueContext->getConfig();
        
        $this->assertEquals('Internet Basketball League', $config['title']);
        $this->assertEquals('IBL', $config['short_name']);
        $this->assertEquals('#1a365d', $config['primary_color']);
        $this->assertEquals('images/ibl/logo.png', $config['logo_path']);
    }

    /**
     * Test getConfig returns Olympics configuration
     */
    public function testGetConfigOlympics(): void
    {
        $_SESSION['current_league'] = 'olympics';
        
        $config = $this->leagueContext->getConfig();
        
        $this->assertEquals('IBL Olympics', $config['title']);
        $this->assertEquals('Olympics', $config['short_name']);
        $this->assertEquals('#c53030', $config['primary_color']);
        $this->assertEquals('images/olympics/logo.png', $config['logo_path']);
    }

    /**
     * Test getConfig returns default (IBL) configuration when league unknown
     */
    public function testGetConfigDefaultsToIbl(): void
    {
        // Don't set any league, should default to ibl
        $config = $this->leagueContext->getConfig();

        $this->assertEquals('Internet Basketball League', $config['title']);
        $this->assertEquals('IBL', $config['short_name']);
    }

    // ---- isOlympics() tests ----

    public function testIsOlympicsReturnsFalseForIblContext(): void
    {
        $_SESSION['current_league'] = 'ibl';
        $this->assertFalse($this->leagueContext->isOlympics());
    }

    public function testIsOlympicsReturnsTrueForOlympicsContext(): void
    {
        $_SESSION['current_league'] = 'olympics';
        $this->assertTrue($this->leagueContext->isOlympics());
    }

    public function testIsOlympicsReturnsFalseByDefault(): void
    {
        $this->assertFalse($this->leagueContext->isOlympics());
    }

    // ---- getTableName() tests ----

    public function testGetTableNameReturnsIblTableNamesForIblContext(): void
    {
        $_SESSION['current_league'] = 'ibl';

        $this->assertSame('ibl_box_scores', $this->leagueContext->getTableName('ibl_box_scores'));
        $this->assertSame('ibl_box_scores_teams', $this->leagueContext->getTableName('ibl_box_scores_teams'));
        $this->assertSame('ibl_schedule', $this->leagueContext->getTableName('ibl_schedule'));
        $this->assertSame('ibl_standings', $this->leagueContext->getTableName('ibl_standings'));
        $this->assertSame('ibl_power', $this->leagueContext->getTableName('ibl_power'));
        $this->assertSame('ibl_team_info', $this->leagueContext->getTableName('ibl_team_info'));
    }

    public function testGetTableNameReturnsOlympicsTableNamesForOlympicsContext(): void
    {
        $_SESSION['current_league'] = 'olympics';

        $this->assertSame('ibl_olympics_box_scores', $this->leagueContext->getTableName('ibl_box_scores'));
        $this->assertSame('ibl_olympics_box_scores_teams', $this->leagueContext->getTableName('ibl_box_scores_teams'));
        $this->assertSame('ibl_olympics_schedule', $this->leagueContext->getTableName('ibl_schedule'));
        $this->assertSame('ibl_olympics_standings', $this->leagueContext->getTableName('ibl_standings'));
        $this->assertSame('ibl_olympics_power', $this->leagueContext->getTableName('ibl_power'));
        $this->assertSame('ibl_olympics_team_info', $this->leagueContext->getTableName('ibl_team_info'));
        $this->assertSame('ibl_olympics_league_config', $this->leagueContext->getTableName('ibl_league_config'));
    }

    public function testGetTableNameReturnsInputUnchangedForUnmappedTables(): void
    {
        $_SESSION['current_league'] = 'olympics';

        $this->assertSame('some_other_table', $this->leagueContext->getTableName('some_other_table'));
    }

    public function testGetTableNameReturnsIblTableNamesByDefault(): void
    {
        // No league set — defaults to IBL
        $this->assertSame('ibl_schedule', $this->leagueContext->getTableName('ibl_schedule'));
        $this->assertSame('ibl_power', $this->leagueContext->getTableName('ibl_power'));
    }

    public function testGetTableNameReturnsOlympicsJsbTableMappings(): void
    {
        $_SESSION['current_league'] = 'olympics';

        $this->assertSame('ibl_olympics_plr', $this->leagueContext->getTableName('ibl_plr'));
        $this->assertSame('ibl_olympics_hist', $this->leagueContext->getTableName('ibl_hist'));
        $this->assertSame('ibl_olympics_hist', $this->leagueContext->getTableName('ibl_hist_archive'));
        $this->assertSame('ibl_olympics_jsb_history', $this->leagueContext->getTableName('ibl_jsb_history'));
        $this->assertSame('ibl_olympics_jsb_transactions', $this->leagueContext->getTableName('ibl_jsb_transactions'));
        $this->assertSame('ibl_olympics_rcb_alltime_records', $this->leagueContext->getTableName('ibl_rcb_alltime_records'));
        $this->assertSame('ibl_olympics_rcb_season_records', $this->leagueContext->getTableName('ibl_rcb_season_records'));
    }

    public function testGetTableNameReturnsIblJsbTableNamesForIblContext(): void
    {
        $_SESSION['current_league'] = 'ibl';

        $this->assertSame('ibl_plr', $this->leagueContext->getTableName('ibl_plr'));
        $this->assertSame('ibl_hist', $this->leagueContext->getTableName('ibl_hist'));
        $this->assertSame('ibl_jsb_history', $this->leagueContext->getTableName('ibl_jsb_history'));
        $this->assertSame('ibl_jsb_transactions', $this->leagueContext->getTableName('ibl_jsb_transactions'));
        $this->assertSame('ibl_rcb_alltime_records', $this->leagueContext->getTableName('ibl_rcb_alltime_records'));
        $this->assertSame('ibl_rcb_season_records', $this->leagueContext->getTableName('ibl_rcb_season_records'));
    }

    // ---- getFilePrefix() tests ----

    public function testGetFilePrefixReturnsIbl5ForIblContext(): void
    {
        $_SESSION['current_league'] = 'ibl';
        $this->assertSame('IBL5', $this->leagueContext->getFilePrefix());
    }

    public function testGetFilePrefixReturnsOlympicsForOlympicsContext(): void
    {
        $_SESSION['current_league'] = 'olympics';
        $this->assertSame('Olympics', $this->leagueContext->getFilePrefix());
    }

    public function testGetFilePrefixReturnsIbl5ByDefault(): void
    {
        $this->assertSame('IBL5', $this->leagueContext->getFilePrefix());
    }

    // --- Merged from LeagueContextTableResolutionTest ---

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

        // Original read-path tables
        $this->assertSame('ibl_olympics_standings', $context->getTableName('ibl_standings'));
        $this->assertSame('ibl_olympics_team_info', $context->getTableName('ibl_team_info'));
        $this->assertSame('ibl_olympics_box_scores', $context->getTableName('ibl_box_scores'));
        $this->assertSame('ibl_olympics_box_scores_teams', $context->getTableName('ibl_box_scores_teams'));
        $this->assertSame('ibl_olympics_schedule', $context->getTableName('ibl_schedule'));
        $this->assertSame('ibl_olympics_power', $context->getTableName('ibl_power'));
        $this->assertSame('ibl_olympics_league_config', $context->getTableName('ibl_league_config'));

        // JSB import tables
        $this->assertSame('ibl_olympics_plr', $context->getTableName('ibl_plr'));
        $this->assertSame('ibl_olympics_hist', $context->getTableName('ibl_hist'));
        $this->assertSame('ibl_olympics_hist', $context->getTableName('ibl_hist_archive'));
        $this->assertSame('ibl_olympics_jsb_history', $context->getTableName('ibl_jsb_history'));
        $this->assertSame('ibl_olympics_jsb_transactions', $context->getTableName('ibl_jsb_transactions'));
        $this->assertSame('ibl_olympics_rcb_alltime_records', $context->getTableName('ibl_rcb_alltime_records'));
        $this->assertSame('ibl_olympics_rcb_season_records', $context->getTableName('ibl_rcb_season_records'));

        // Saved depth chart tables
        $this->assertSame('ibl_olympics_saved_depth_charts', $context->getTableName('ibl_saved_depth_charts'));
        $this->assertSame('ibl_olympics_saved_depth_chart_players', $context->getTableName('ibl_saved_depth_chart_players'));
    }

    public function testGetTableNameReturnsUnmappedTablesUnchanged(): void
    {
        $_GET['league'] = 'olympics';
        $context = new LeagueContext();

        // Tables not in the mapping should be returned unchanged
        $this->assertSame('ibl_awards', $context->getTableName('ibl_awards'));
        $this->assertSame('ibl_settings', $context->getTableName('ibl_settings'));
        $this->assertSame('ibl_sim_dates', $context->getTableName('ibl_sim_dates'));
    }

    public function testGetFilePrefixReturnsCorrectPrefix(): void
    {
        $iblContext = new LeagueContext();
        $this->assertSame('IBL5', $iblContext->getFilePrefix());

        $_GET['league'] = 'olympics';
        $olympicsContext = new LeagueContext();
        $this->assertSame('Olympics', $olympicsContext->getFilePrefix());
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
