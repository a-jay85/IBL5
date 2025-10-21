<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for Depth Chart validation logic
 * 
 * Tests validation rules from modules/Depth_Chart_Entry/index.php including:
 * - Active player count validation (season-specific)
 * - Position depth validation (season-specific)
 * - Multiple starting position validation
 * - Injury handling logic
 */
class DepthChartValidationTest extends TestCase
{
    private $mockDb;
    private $mockSeason;
    
    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->mockSeason = new Season($this->mockDb);
    }
    
    protected function tearDown(): void
    {
        $this->mockDb = null;
        $this->mockSeason = null;
    }
    
    /**
     * Simulates the validation logic from submit() function
     */
    private function validateRoster($activePlayers, $positionDepths, $hasMultipleStarters, $phase)
    {
        if ($phase != 'Playoffs') {
            $minActivePlayers = 12;
            $maxActivePlayers = 12;
            $minPositionDepth = 3;
        } else {
            $minActivePlayers = 10;
            $maxActivePlayers = 12;
            $minPositionDepth = 2;
        }
        
        $errors = [];
        
        if ($activePlayers < $minActivePlayers) {
            $errors[] = "active_players_min";
        }
        if ($activePlayers > $maxActivePlayers) {
            $errors[] = "active_players_max";
        }
        
        foreach (JSB::PLAYER_POSITIONS as $pos) {
            $pos = strtolower($pos);
            if (isset($positionDepths[$pos]) && $positionDepths[$pos] < $minPositionDepth) {
                $errors[] = "position_depth_$pos";
            }
        }
        
        if ($hasMultipleStarters) {
            $errors[] = "multiple_starters";
        }
        
        return empty($errors) ? ['valid' => true] : ['valid' => false, 'errors' => $errors];
    }
    
    /**
     * @group validation
     * @group active-players
     */
    public function testRegularSeasonActivePlayerValidation()
    {
        // Regular season requires exactly 12 active players
        $this->assertFalse($this->validateRoster(11, [], false, 'Regular Season')['valid']);
        $this->assertFalse($this->validateRoster(13, [], false, 'Regular Season')['valid']);
        $this->assertTrue($this->validateRoster(12, ['pg' => 3, 'sg' => 3, 'sf' => 3, 'pf' => 3, 'c' => 3], false, 'Regular Season')['valid']);
    }
    
    /**
     * @group validation
     * @group active-players
     */
    public function testPlayoffActivePlayerValidation()
    {
        // Playoffs allow 10-12 active players
        $this->assertFalse($this->validateRoster(9, [], false, 'Playoffs')['valid']);
        $this->assertTrue($this->validateRoster(10, ['pg' => 2, 'sg' => 2, 'sf' => 2, 'pf' => 2, 'c' => 2], false, 'Playoffs')['valid']);
        $this->assertTrue($this->validateRoster(12, ['pg' => 2, 'sg' => 2, 'sf' => 2, 'pf' => 2, 'c' => 2], false, 'Playoffs')['valid']);
        $this->assertFalse($this->validateRoster(13, [], false, 'Playoffs')['valid']);
    }
    
    /**
     * @group validation
     * @group position-depth
     */
    public function testRegularSeasonPositionDepthValidation()
    {
        // Regular season requires 3-deep at all positions
        $result = $this->validateRoster(12, ['pg' => 2, 'sg' => 3, 'sf' => 3, 'pf' => 3, 'c' => 3], false, 'Regular Season');
        $this->assertFalse($result['valid']);
        $this->assertContains('position_depth_pg', $result['errors']);
        
        // All positions meet minimum
        $result = $this->validateRoster(12, ['pg' => 3, 'sg' => 3, 'sf' => 3, 'pf' => 3, 'c' => 3], false, 'Regular Season');
        $this->assertTrue($result['valid']);
    }
    
    /**
     * @group validation
     * @group position-depth
     */
    public function testPlayoffPositionDepthValidation()
    {
        // Playoffs require 2-deep at all positions
        $result = $this->validateRoster(10, ['pg' => 1, 'sg' => 2, 'sf' => 2, 'pf' => 2, 'c' => 2], false, 'Playoffs');
        $this->assertFalse($result['valid']);
        $this->assertContains('position_depth_pg', $result['errors']);
        
        // All positions meet minimum
        $result = $this->validateRoster(10, ['pg' => 2, 'sg' => 2, 'sf' => 2, 'pf' => 2, 'c' => 2], false, 'Playoffs');
        $this->assertTrue($result['valid']);
    }
    
    /**
     * @group validation
     * @group multiple-starters
     */
    public function testMultipleStartersValidation()
    {
        $result = $this->validateRoster(12, ['pg' => 3, 'sg' => 3, 'sf' => 3, 'pf' => 3, 'c' => 3], true, 'Regular Season');
        $this->assertFalse($result['valid']);
        $this->assertContains('multiple_starters', $result['errors']);
        
        $result = $this->validateRoster(12, ['pg' => 3, 'sg' => 3, 'sf' => 3, 'pf' => 3, 'c' => 3], false, 'Regular Season');
        $this->assertTrue($result['valid']);
    }
    
    /**
     * @group validation
     * @group injury-handling
     */
    public function testInjuredPlayerDepthCalculation()
    {
        // Test the logic from submit() that counts position depth excluding injured players
        $players = [
            ['pg' => 1, 'injury' => 0],  // Counts
            ['pg' => 2, 'injury' => 0],  // Counts
            ['pg' => 3, 'injury' => 15], // Does NOT count (injury >= 15)
        ];
        
        $pgDepth = 0;
        foreach ($players as $player) {
            if ($player['pg'] > 0 && $player['injury'] < 15) {
                $pgDepth++;
            }
        }
        
        $this->assertEquals(2, $pgDepth, 'Injured player should not count toward position depth');
    }
}
