<?php

declare(strict_types=1);

namespace Tests\League;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use League\LeagueContext;

/**
 * LeagueContextIntegrationTest - Additional integration tests for LeagueContext
 *
 * Tests constants validation, config structure verification, and module lists.
 *
 * @covers \League\LeagueContext
 */
#[AllowMockObjectsWithoutExpectations]
class LeagueContextIntegrationTest extends TestCase
{
    private LeagueContext $leagueContext;

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

    // ============================================
    // CONSTANTS VALIDATION TESTS
    // ============================================

    /**
     * Test LEAGUE_IBL constant value
     */
    public function testLeagueIblConstantValue(): void
    {
        $this->assertEquals('ibl', LeagueContext::LEAGUE_IBL);
    }

    /**
     * Test LEAGUE_OLYMPICS constant value
     */
    public function testLeagueOlympicsConstantValue(): void
    {
        $this->assertEquals('olympics', LeagueContext::LEAGUE_OLYMPICS);
    }

    /**
     * Test COOKIE_NAME constant value
     */
    public function testCookieNameConstantValue(): void
    {
        $this->assertEquals('ibl_league', LeagueContext::COOKIE_NAME);
    }

    /**
     * Test constants are string type
     */
    public function testConstantsAreStringType(): void
    {
        $this->assertIsString(LeagueContext::LEAGUE_IBL);
        $this->assertIsString(LeagueContext::LEAGUE_OLYMPICS);
        $this->assertIsString(LeagueContext::COOKIE_NAME);
    }

    /**
     * Test constants are lowercase
     */
    public function testConstantValuesAreLowercase(): void
    {
        $this->assertEquals(strtolower(LeagueContext::LEAGUE_IBL), LeagueContext::LEAGUE_IBL);
        $this->assertEquals(strtolower(LeagueContext::LEAGUE_OLYMPICS), LeagueContext::LEAGUE_OLYMPICS);
    }

    // ============================================
    // CONFIG STRUCTURE TESTS
    // ============================================

    /**
     * Test IBL config has all required keys
     */
    public function testIblConfigHasAllRequiredKeys(): void
    {
        $_SESSION['current_league'] = 'ibl';

        $config = $this->leagueContext->getConfig();

        $requiredKeys = ['title', 'short_name', 'primary_color', 'logo_path', 'images_path'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $config, "Missing key: {$key}");
        }
    }

    /**
     * Test Olympics config has all required keys
     */
    public function testOlympicsConfigHasAllRequiredKeys(): void
    {
        $_SESSION['current_league'] = 'olympics';

        $config = $this->leagueContext->getConfig();

        $requiredKeys = ['title', 'short_name', 'primary_color', 'logo_path', 'images_path'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $config, "Missing key: {$key}");
        }
    }

    /**
     * Test IBL config values are not empty
     */
    public function testIblConfigValuesAreNotEmpty(): void
    {
        $_SESSION['current_league'] = 'ibl';

        $config = $this->leagueContext->getConfig();

        foreach ($config as $key => $value) {
            $this->assertNotEmpty($value, "Config value for '{$key}' should not be empty");
        }
    }

    /**
     * Test Olympics config values are not empty
     */
    public function testOlympicsConfigValuesAreNotEmpty(): void
    {
        $_SESSION['current_league'] = 'olympics';

        $config = $this->leagueContext->getConfig();

        foreach ($config as $key => $value) {
            $this->assertNotEmpty($value, "Config value for '{$key}' should not be empty");
        }
    }

    /**
     * Test IBL primary color is valid hex
     */
    public function testIblPrimaryColorIsValidHex(): void
    {
        $_SESSION['current_league'] = 'ibl';

        $config = $this->leagueContext->getConfig();

        $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $config['primary_color']);
    }

    /**
     * Test Olympics primary color is valid hex
     */
    public function testOlympicsPrimaryColorIsValidHex(): void
    {
        $_SESSION['current_league'] = 'olympics';

        $config = $this->leagueContext->getConfig();

        $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $config['primary_color']);
    }

    /**
     * Test IBL logo path is a valid path
     */
    public function testIblLogoPathIsValidPath(): void
    {
        $_SESSION['current_league'] = 'ibl';

        $config = $this->leagueContext->getConfig();

        $this->assertStringContainsString('images/', $config['logo_path']);
        $this->assertStringContainsString('.png', $config['logo_path']);
    }

    /**
     * Test Olympics logo path is a valid path
     */
    public function testOlympicsLogoPathIsValidPath(): void
    {
        $_SESSION['current_league'] = 'olympics';

        $config = $this->leagueContext->getConfig();

        $this->assertStringContainsString('images/', $config['logo_path']);
        $this->assertStringContainsString('.png', $config['logo_path']);
    }

    /**
     * Test IBL specific color value
     */
    public function testIblSpecificColorValue(): void
    {
        $_SESSION['current_league'] = 'ibl';

        $config = $this->leagueContext->getConfig();

        $this->assertEquals('#1a365d', $config['primary_color']);
    }

    /**
     * Test Olympics specific color value
     */
    public function testOlympicsSpecificColorValue(): void
    {
        $_SESSION['current_league'] = 'olympics';

        $config = $this->leagueContext->getConfig();

        $this->assertEquals('#c53030', $config['primary_color']);
    }

    /**
     * Test IBL images path
     */
    public function testIblImagesPath(): void
    {
        $_SESSION['current_league'] = 'ibl';

        $config = $this->leagueContext->getConfig();

        $this->assertEquals('images/', $config['images_path']);
    }

    /**
     * Test Olympics images path
     */
    public function testOlympicsImagesPath(): void
    {
        $_SESSION['current_league'] = 'olympics';

        $config = $this->leagueContext->getConfig();

        $this->assertEquals('images/olympics/', $config['images_path']);
    }

    // ============================================
    // IBL-ONLY MODULES LIST TESTS
    // ============================================

    /**
     * Test complete IBL-only modules list for Olympics
     */
    public function testCompleteIblOnlyModulesListForOlympics(): void
    {
        $_SESSION['current_league'] = 'olympics';

        $iblOnlyModules = [
            'Draft',
            'Draft_Pick_Locator',
            'Free_Agency',
            'Waivers',
            'Trading',
            'Voting',
            'Voting_Results',
            'Cap_Info',
            'Franchise_History',
        ];

        foreach ($iblOnlyModules as $module) {
            $this->assertFalse(
                $this->leagueContext->isModuleEnabled($module),
                "Module '{$module}' should be disabled in Olympics"
            );
        }
    }

    /**
     * Test all IBL-only modules are enabled for IBL
     */
    public function testAllIblOnlyModulesEnabledForIbl(): void
    {
        $_SESSION['current_league'] = 'ibl';

        $iblOnlyModules = [
            'Draft',
            'Draft_Pick_Locator',
            'Free_Agency',
            'Waivers',
            'Trading',
            'Voting',
            'Voting_Results',
            'Cap_Info',
            'Franchise_History',
        ];

        foreach ($iblOnlyModules as $module) {
            $this->assertTrue(
                $this->leagueContext->isModuleEnabled($module),
                "Module '{$module}' should be enabled in IBL"
            );
        }
    }

    /**
     * Test common modules are enabled for both leagues
     */
    public function testCommonModulesEnabledForBothLeagues(): void
    {
        $commonModules = [
            'Standings',
            'Schedule',
            'Injuries',
            'Player',
            'Team',
            'Stats',
            'History',
            'Awards',
        ];

        // Test IBL
        $_SESSION['current_league'] = 'ibl';
        foreach ($commonModules as $module) {
            $this->assertTrue(
                $this->leagueContext->isModuleEnabled($module),
                "Module '{$module}' should be enabled in IBL"
            );
        }

        // Test Olympics
        $_SESSION['current_league'] = 'olympics';
        foreach ($commonModules as $module) {
            $this->assertTrue(
                $this->leagueContext->isModuleEnabled($module),
                "Module '{$module}' should be enabled in Olympics"
            );
        }
    }

    /**
     * Test IBL-only modules count is exactly 9
     */
    public function testIblOnlyModulesCountIsNine(): void
    {
        $_SESSION['current_league'] = 'olympics';

        $iblOnlyModules = [
            'Draft',
            'Draft_Pick_Locator',
            'Free_Agency',
            'Waivers',
            'Trading',
            'Voting',
            'Voting_Results',
            'Cap_Info',
            'Franchise_History',
        ];

        $disabledCount = 0;
        foreach ($iblOnlyModules as $module) {
            if (!$this->leagueContext->isModuleEnabled($module)) {
                $disabledCount++;
            }
        }

        $this->assertEquals(9, $disabledCount, "Should have exactly 9 IBL-only modules");
    }

    // ============================================
    // SET LEAGUE BEHAVIOR TESTS
    // ============================================

    /**
     * Test setLeague updates session
     */
    public function testSetLeagueUpdatesSession(): void
    {
        $this->leagueContext->setLeague('olympics');

        $this->assertEquals('olympics', $_SESSION['current_league']);
    }

    /**
     * Test setLeague with IBL updates session
     */
    public function testSetLeagueWithIblUpdatesSession(): void
    {
        $this->leagueContext->setLeague('ibl');

        $this->assertEquals('ibl', $_SESSION['current_league']);
    }

    /**
     * Test setLeague throws for numeric input
     */
    public function testSetLeagueThrowsForNumericInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->leagueContext->setLeague('123');
    }

    /**
     * Test setLeague throws for mixed case
     */
    public function testSetLeagueThrowsForMixedCase(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->leagueContext->setLeague('IBL');
    }

    /**
     * Test setLeague throws for Olympics with capital O
     */
    public function testSetLeagueThrowsForOlympicsCapitalized(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->leagueContext->setLeague('Olympics');
    }

    /**
     * Test setLeague throws for empty string
     */
    public function testSetLeagueThrowsForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->leagueContext->setLeague('');
    }

    /**
     * Test setLeague throws for whitespace
     */
    public function testSetLeagueThrowsForWhitespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->leagueContext->setLeague(' ibl ');
    }

    // ============================================
    // GET CURRENT LEAGUE EDGE CASES
    // ============================================

    /**
     * Test getCurrentLeague with all sources set returns GET priority
     */
    public function testGetCurrentLeagueWithAllSourcesReturnsGetPriority(): void
    {
        $_GET['league'] = 'ibl';
        $_SESSION['current_league'] = 'olympics';
        $_COOKIE['ibl_league'] = 'olympics';

        $result = $this->leagueContext->getCurrentLeague();

        $this->assertEquals('ibl', $result);
    }

    /**
     * Test getCurrentLeague skips invalid GET and uses SESSION
     */
    public function testGetCurrentLeagueSkipsInvalidGetUsesSession(): void
    {
        $_GET['league'] = 'bogus';
        $_SESSION['current_league'] = 'olympics';
        $_COOKIE['ibl_league'] = 'ibl';

        $result = $this->leagueContext->getCurrentLeague();

        $this->assertEquals('olympics', $result);
    }

    /**
     * Test getCurrentLeague skips invalid GET and SESSION uses COOKIE
     */
    public function testGetCurrentLeagueSkipsInvalidGetAndSessionUsesCookie(): void
    {
        $_GET['league'] = 'bogus';
        $_SESSION['current_league'] = 'bogus';
        $_COOKIE['ibl_league'] = 'olympics';

        $result = $this->leagueContext->getCurrentLeague();

        $this->assertEquals('olympics', $result);
    }

    /**
     * Test getCurrentLeague handles null values in superglobals
     */
    public function testGetCurrentLeagueHandlesNullValues(): void
    {
        $_GET['league'] = null;
        $_SESSION['current_league'] = null;
        $_COOKIE['ibl_league'] = null;

        $result = $this->leagueContext->getCurrentLeague();

        $this->assertEquals('ibl', $result); // Default
    }

    /**
     * Test getCurrentLeague handles empty strings
     */
    public function testGetCurrentLeagueHandlesEmptyStrings(): void
    {
        $_GET['league'] = '';
        $_SESSION['current_league'] = '';
        $_COOKIE['ibl_league'] = '';

        $result = $this->leagueContext->getCurrentLeague();

        $this->assertEquals('ibl', $result); // Default
    }

    // ============================================
    // ISMODULEENABLED EDGE CASES
    // ============================================

    /**
     * Test isModuleEnabled with empty module name
     */
    public function testIsModuleEnabledWithEmptyModuleName(): void
    {
        $_SESSION['current_league'] = 'ibl';

        // Empty string module should be enabled (not in disabled list)
        $result = $this->leagueContext->isModuleEnabled('');

        $this->assertTrue($result);
    }

    /**
     * Test isModuleEnabled is case sensitive for module names
     */
    public function testIsModuleEnabledIsCaseSensitiveForModuleNames(): void
    {
        $_SESSION['current_league'] = 'olympics';

        // 'draft' lowercase should be enabled (only 'Draft' is in disabled list)
        $this->assertTrue($this->leagueContext->isModuleEnabled('draft'));
        $this->assertFalse($this->leagueContext->isModuleEnabled('Draft'));
    }

    /**
     * Test isModuleEnabled with non-existent module
     */
    public function testIsModuleEnabledWithNonExistentModule(): void
    {
        $_SESSION['current_league'] = 'olympics';

        // Non-existent modules should be enabled
        $result = $this->leagueContext->isModuleEnabled('NonExistent_Module_XYZ');

        $this->assertTrue($result);
    }

    /**
     * Test isModuleEnabled respects getCurrentLeague
     */
    public function testIsModuleEnabledRespectsGetCurrentLeague(): void
    {
        // Set via GET to override session
        $_GET['league'] = 'olympics';
        $_SESSION['current_league'] = 'ibl';

        // GET takes priority, so Olympics rules apply
        $result = $this->leagueContext->isModuleEnabled('Draft');

        $this->assertFalse($result); // Draft disabled in Olympics
    }
}
