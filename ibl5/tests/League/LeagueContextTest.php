<?php

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
     * Test getTableName returns original table for IBL league
     */
    public function testGetTableNameIblReturnsOriginal(): void
    {
        $_SESSION['current_league'] = 'ibl';
        
        $this->assertEquals('ibl_team_info', $this->leagueContext->getTableName('ibl_team_info'));
        $this->assertEquals('ibl_standings', $this->leagueContext->getTableName('ibl_standings'));
        $this->assertEquals('ibl_schedule', $this->leagueContext->getTableName('ibl_schedule'));
    }

    /**
     * Test getTableName maps team info table for Olympics
     */
    public function testGetTableNameOlympicsMapsTeamInfo(): void
    {
        $_SESSION['current_league'] = 'olympics';
        
        $this->assertEquals('ibl_olympics_team_info', $this->leagueContext->getTableName('ibl_team_info'));
    }

    /**
     * Test getTableName maps standings table for Olympics
     */
    public function testGetTableNameOlympicsMapsStandings(): void
    {
        $_SESSION['current_league'] = 'olympics';
        
        $this->assertEquals('ibl_olympics_standings', $this->leagueContext->getTableName('ibl_standings'));
    }

    /**
     * Test getTableName maps schedule table for Olympics
     */
    public function testGetTableNameOlympicsMapsSchedule(): void
    {
        $_SESSION['current_league'] = 'olympics';
        
        $this->assertEquals('ibl_olympics_schedule', $this->leagueContext->getTableName('ibl_schedule'));
    }

    /**
     * Test getTableName maps box scores table for Olympics
     */
    public function testGetTableNameOlympicsMapsBoxScores(): void
    {
        $_SESSION['current_league'] = 'olympics';
        
        $this->assertEquals('ibl_olympics_box_scores', $this->leagueContext->getTableName('ibl_box_scores'));
    }

    /**
     * Test getTableName maps box scores teams table for Olympics
     */
    public function testGetTableNameOlympicsMapsBoxScoresTeams(): void
    {
        $_SESSION['current_league'] = 'olympics';
        
        $this->assertEquals('ibl_olympics_box_scores_teams', $this->leagueContext->getTableName('ibl_box_scores_teams'));
    }

    /**
     * Test getTableName returns shared tables unchanged for Olympics
     */
    public function testGetTableNameOlympicsSharedTables(): void
    {
        $_SESSION['current_league'] = 'olympics';
        
        $this->assertEquals('ibl_plr', $this->leagueContext->getTableName('ibl_plr'));
        $this->assertEquals('ibl_hist', $this->leagueContext->getTableName('ibl_hist'));
        $this->assertEquals('nuke_users', $this->leagueContext->getTableName('nuke_users'));
        $this->assertEquals('nuke_authors', $this->leagueContext->getTableName('nuke_authors'));
    }

    /**
     * Test getTableName returns unmapped tables unchanged for Olympics
     */
    public function testGetTableNameOlympicsUnmappedTables(): void
    {
        $_SESSION['current_league'] = 'olympics';
        
        $this->assertEquals('ibl_draft', $this->leagueContext->getTableName('ibl_draft'));
        $this->assertEquals('other_table', $this->leagueContext->getTableName('other_table'));
    }

    /**
     * Test isModuleEnabled returns true for all modules in IBL
     */
    public function testIsModuleEnabledIblAllEnabled(): void
    {
        $_SESSION['current_league'] = 'ibl';
        
        $this->assertTrue($this->leagueContext->isModuleEnabled('Draft'));
        $this->assertTrue($this->leagueContext->isModuleEnabled('Free_Agency'));
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
     * Test isModuleEnabled returns false for Draft_Pick_Locator in Olympics
     */
    public function testIsModuleEnabledOlympicsDisablesDraftPickLocator(): void
    {
        $_SESSION['current_league'] = 'olympics';
        
        $this->assertFalse($this->leagueContext->isModuleEnabled('Draft_Pick_Locator'));
    }

    /**
     * Test isModuleEnabled returns false for Free_Agency in Olympics
     */
    public function testIsModuleEnabledOlympicsDisablesFreeAgency(): void
    {
        $_SESSION['current_league'] = 'olympics';
        
        $this->assertFalse($this->leagueContext->isModuleEnabled('Free_Agency'));
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
     * Test isModuleEnabled returns false for Voting_Results in Olympics
     */
    public function testIsModuleEnabledOlympicsDisablesVotingResults(): void
    {
        $_SESSION['current_league'] = 'olympics';
        
        $this->assertFalse($this->leagueContext->isModuleEnabled('Voting_Results'));
    }

    /**
     * Test isModuleEnabled returns false for Cap_Info in Olympics
     */
    public function testIsModuleEnabledOlympicsDisablesCapInfo(): void
    {
        $_SESSION['current_league'] = 'olympics';
        
        $this->assertFalse($this->leagueContext->isModuleEnabled('Cap_Info'));
    }

    /**
     * Test isModuleEnabled returns false for Franchise_History in Olympics
     */
    public function testIsModuleEnabledOlympicsDisablesFranchiseHistory(): void
    {
        $_SESSION['current_league'] = 'olympics';
        
        $this->assertFalse($this->leagueContext->isModuleEnabled('Franchise_History'));
    }

    /**
     * Test isModuleEnabled returns false for Power_Rankings in Olympics
     */
    public function testIsModuleEnabledOlympicsDisablesPowerRankings(): void
    {
        $_SESSION['current_league'] = 'olympics';
        
        $this->assertFalse($this->leagueContext->isModuleEnabled('Power_Rankings'));
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
}
