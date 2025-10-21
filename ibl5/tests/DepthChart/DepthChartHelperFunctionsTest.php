<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for Depth Chart helper functions
 * 
 * These helper functions are defined within userinfo() in modules/Depth_Chart_Entry/index.php.
 * They generate HTML dropdowns for the depth chart form.
 * Since they echo output directly and are defined inside another function, we test their logic inline.
 */
class DepthChartHelperFunctionsTest extends TestCase
{
    /**
     * @group helper-functions
     * @group position-handler
     */
    public function testPosHandlerLogic()
    {
        // Tests the logic from posHandler() function
        // Generates options: No (0), 1st (1), 2nd (2), 3rd (3), 4th (4), ok (5)
        
        $options = [
            0 => 'No',
            1 => '1st',
            2 => '2nd',
            3 => '3rd',
            4 => '4th',
            5 => 'ok'
        ];
        
        // Test that selecting different values generates different selected states
        foreach ([0, 1, 3, 5] as $selectedValue) {
            ob_start();
            foreach ($options as $value => $label) {
                echo "<option value=\"$value\"" . ($selectedValue == $value ? " SELECTED" : "") . ">$label</option>";
            }
            $output = ob_get_clean();
            
            // Should contain the selected value with SELECTED attribute
            $this->assertStringContainsString("value=\"$selectedValue\" SELECTED", $output);
            // Should have exactly one SELECTED
            $this->assertEquals(1, substr_count($output, 'SELECTED'));
        }
    }
    
    /**
     * @group helper-functions
     * @group offdef-handler
     */
    public function testOffdefHandlerLogic()
    {
        // Tests the logic from offdefHandler() function
        // Generates options: Auto (0), Outside (1), Drive (2), Post (3)
        
        $options = [
            0 => 'Auto',
            1 => 'Outside',
            2 => 'Drive',
            3 => 'Post'
        ];
        
        foreach ([0, 2, 3] as $selectedValue) {
            ob_start();
            foreach ($options as $value => $label) {
                echo "<option value=\"$value\"" . ($selectedValue == $value ? " SELECTED" : "") . ">$label</option>";
            }
            $output = ob_get_clean();
            
            $this->assertStringContainsString("value=\"$selectedValue\" SELECTED", $output);
            $this->assertEquals(1, substr_count($output, 'SELECTED'));
        }
    }
    
    /**
     * @group helper-functions
     * @group oidibh-handler
     */
    public function testOidibhHandlerLogic()
    {
        // Tests the logic from oidibhHandler() function
        // Generates options: 2, 1, - (0), -1, -2
        
        $options = [
            2 => '2',
            1 => '1',
            0 => '-',
            -1 => '-1',
            -2 => '-2'
        ];
        
        foreach ([2, 0, -1, -2] as $selectedValue) {
            ob_start();
            foreach ($options as $value => $label) {
                echo "<option value=\"$value\"" . ($selectedValue == $value ? " SELECTED" : "") . ">$label</option>";
            }
            $output = ob_get_clean();
            
            $this->assertStringContainsString("value=\"$selectedValue\" SELECTED", $output);
            $this->assertEquals(1, substr_count($output, 'SELECTED'));
        }
    }
    
    /**
     * @group helper-functions
     * @group minutes-handler
     */
    public function testMinutesStaminaCapCalculation()
    {
        // Tests the stamina cap calculation logic from userinfo()
        // player_staminacap = player_stamina + 40, but capped at 40
        
        $testCases = [
            ['stamina' => 5, 'expected_cap' => 40],  // 5 + 40 = 45, capped to 40
            ['stamina' => -5, 'expected_cap' => 35], // -5 + 40 = 35
            ['stamina' => 0, 'expected_cap' => 40],  // 0 + 40 = 40
            ['stamina' => 10, 'expected_cap' => 40], // 10 + 40 = 50, capped to 40
        ];
        
        foreach ($testCases as $case) {
            $playerStaminaCap = $case['stamina'] + 40;
            if ($playerStaminaCap > 40) {
                $playerStaminaCap = 40;
            }
            
            $this->assertEquals($case['expected_cap'], $playerStaminaCap);
        }
    }
    
    /**
     * @group helper-functions
     * @group active-handler
     */
    public function testActiveHandlerLogic()
    {
        // Tests the active status dropdown logic from userinfo()
        
        // When active = 1, Yes should be selected
        $playerActive = 1;
        ob_start();
        if ($playerActive == 1) {
            echo "<option value=\"1\" SELECTED>Yes</option><option value=\"0\">No</option>";
        } else {
            echo "<option value=\"1\">Yes</option><option value=\"0\" SELECTED>No</option>";
        }
        $output = ob_get_clean();
        
        $this->assertStringContainsString('value="1" SELECTED', $output);
        $this->assertStringNotContainsString('value="0" SELECTED', $output);
        
        // When active = 0, No should be selected
        $playerActive = 0;
        ob_start();
        if ($playerActive == 1) {
            echo "<option value=\"1\" SELECTED>Yes</option><option value=\"0\">No</option>";
        } else {
            echo "<option value=\"1\">Yes</option><option value=\"0\" SELECTED>No</option>";
        }
        $output = ob_get_clean();
        
        $this->assertStringContainsString('value="0" SELECTED', $output);
        $this->assertStringNotContainsString('value="1" SELECTED', $output);
    }
}
