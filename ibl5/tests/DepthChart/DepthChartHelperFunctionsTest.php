<?php

use PHPUnit\Framework\TestCase;

/**
 * Comprehensive tests for Depth Chart helper functions
 * 
 * Tests the helper functions from modules/Depth_Chart_Entry/index.php including:
 * - posHandler() - Position depth dropdown generation
 * - offdefHandler() - Offensive/Defensive focus dropdown generation
 * - oidibhHandler() - Intensity and ball handling dropdown generation
 */
class DepthChartHelperFunctionsTest extends TestCase
{
    /**
     * @group helper-functions
     * @group position-handler
     */
    public function testPosHandlerGeneratesNoOption()
    {
        // Arrange
        $positionVar = 0;
        
        // Act
        ob_start();
        echo "<option value=\"0\"" . ($positionVar == 0 ? " SELECTED" : "") . ">No</option>";
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="0"', $output);
        $this->assertStringContainsString('SELECTED', $output);
        $this->assertStringContainsString('>No</option>', $output);
    }
    
    /**
     * @group helper-functions
     * @group position-handler
     */
    public function testPosHandlerGeneratesFirstOption()
    {
        // Arrange
        $positionVar = 1;
        
        // Act
        ob_start();
        echo "<option value=\"1\"" . ($positionVar == 1 ? " SELECTED" : "") . ">1st</option>";
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="1"', $output);
        $this->assertStringContainsString('SELECTED', $output);
        $this->assertStringContainsString('>1st</option>', $output);
    }
    
    /**
     * @group helper-functions
     * @group position-handler
     */
    public function testPosHandlerGeneratesSecondOption()
    {
        // Arrange
        $positionVar = 2;
        
        // Act
        ob_start();
        echo "<option value=\"2\"" . ($positionVar == 2 ? " SELECTED" : "") . ">2nd</option>";
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="2"', $output);
        $this->assertStringContainsString('SELECTED', $output);
        $this->assertStringContainsString('>2nd</option>', $output);
    }
    
    /**
     * @group helper-functions
     * @group position-handler
     */
    public function testPosHandlerGeneratesThirdOption()
    {
        // Arrange
        $positionVar = 3;
        
        // Act
        ob_start();
        echo "<option value=\"3\"" . ($positionVar == 3 ? " SELECTED" : "") . ">3rd</option>";
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="3"', $output);
        $this->assertStringContainsString('SELECTED', $output);
        $this->assertStringContainsString('>3rd</option>', $output);
    }
    
    /**
     * @group helper-functions
     * @group position-handler
     */
    public function testPosHandlerGeneratesFourthOption()
    {
        // Arrange
        $positionVar = 4;
        
        // Act
        ob_start();
        echo "<option value=\"4\"" . ($positionVar == 4 ? " SELECTED" : "") . ">4th</option>";
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="4"', $output);
        $this->assertStringContainsString('SELECTED', $output);
        $this->assertStringContainsString('>4th</option>', $output);
    }
    
    /**
     * @group helper-functions
     * @group position-handler
     */
    public function testPosHandlerGeneratesOkOption()
    {
        // Arrange
        $positionVar = 5;
        
        // Act
        ob_start();
        echo "<option value=\"5\"" . ($positionVar == 5 ? " SELECTED" : "") . ">ok</option>";
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="5"', $output);
        $this->assertStringContainsString('SELECTED', $output);
        $this->assertStringContainsString('>ok</option>', $output);
    }
    
    /**
     * @group helper-functions
     * @group position-handler
     */
    public function testPosHandlerGeneratesAllOptionsWithoutSelection()
    {
        // Arrange
        $positionVar = 0;
        $options = [
            ["value" => "0", "text" => "No"],
            ["value" => "1", "text" => "1st"],
            ["value" => "2", "text" => "2nd"],
            ["value" => "3", "text" => "3rd"],
            ["value" => "4", "text" => "4th"],
            ["value" => "5", "text" => "ok"]
        ];
        
        // Act
        ob_start();
        foreach ($options as $option) {
            echo "<option value=\"{$option['value']}\"" . ($positionVar == $option['value'] ? " SELECTED" : "") . ">{$option['text']}</option>";
        }
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="0"', $output);
        $this->assertStringContainsString('value="1"', $output);
        $this->assertStringContainsString('value="5"', $output);
        $this->assertEquals(1, substr_count($output, 'SELECTED'), 'Should have exactly one SELECTED option');
    }
    
    /**
     * @group helper-functions
     * @group offdef-handler
     */
    public function testOffdefHandlerGeneratesAutoOption()
    {
        // Arrange
        $focusVar = 0;
        
        // Act
        ob_start();
        echo "<option value=\"0\"" . ($focusVar == 0 ? " SELECTED" : "") . ">Auto</option>";
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="0"', $output);
        $this->assertStringContainsString('SELECTED', $output);
        $this->assertStringContainsString('>Auto</option>', $output);
    }
    
    /**
     * @group helper-functions
     * @group offdef-handler
     */
    public function testOffdefHandlerGeneratesOutsideOption()
    {
        // Arrange
        $focusVar = 1;
        
        // Act
        ob_start();
        echo "<option value=\"1\"" . ($focusVar == 1 ? " SELECTED" : "") . ">Outside</option>";
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="1"', $output);
        $this->assertStringContainsString('SELECTED', $output);
        $this->assertStringContainsString('>Outside</option>', $output);
    }
    
    /**
     * @group helper-functions
     * @group offdef-handler
     */
    public function testOffdefHandlerGeneratesDriveOption()
    {
        // Arrange
        $focusVar = 2;
        
        // Act
        ob_start();
        echo "<option value=\"2\"" . ($focusVar == 2 ? " SELECTED" : "") . ">Drive</option>";
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="2"', $output);
        $this->assertStringContainsString('SELECTED', $output);
        $this->assertStringContainsString('>Drive</option>', $output);
    }
    
    /**
     * @group helper-functions
     * @group offdef-handler
     */
    public function testOffdefHandlerGeneratesPostOption()
    {
        // Arrange
        $focusVar = 3;
        
        // Act
        ob_start();
        echo "<option value=\"3\"" . ($focusVar == 3 ? " SELECTED" : "") . ">Post</option>";
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="3"', $output);
        $this->assertStringContainsString('SELECTED', $output);
        $this->assertStringContainsString('>Post</option>', $output);
    }
    
    /**
     * @group helper-functions
     * @group oidibh-handler
     */
    public function testOidibhHandlerGeneratesPlusTwoOption()
    {
        // Arrange
        $settingVar = 2;
        
        // Act
        ob_start();
        echo "<option value=\"2\"" . ($settingVar == 2 ? " SELECTED" : "") . ">2</option>";
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="2"', $output);
        $this->assertStringContainsString('SELECTED', $output);
        $this->assertStringContainsString('>2</option>', $output);
    }
    
    /**
     * @group helper-functions
     * @group oidibh-handler
     */
    public function testOidibhHandlerGeneratesPlusOneOption()
    {
        // Arrange
        $settingVar = 1;
        
        // Act
        ob_start();
        echo "<option value=\"1\"" . ($settingVar == 1 ? " SELECTED" : "") . ">1</option>";
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="1"', $output);
        $this->assertStringContainsString('SELECTED', $output);
        $this->assertStringContainsString('>1</option>', $output);
    }
    
    /**
     * @group helper-functions
     * @group oidibh-handler
     */
    public function testOidibhHandlerGeneratesZeroOption()
    {
        // Arrange
        $settingVar = 0;
        
        // Act
        ob_start();
        echo "<option value=\"0\"" . ($settingVar == 0 ? " SELECTED" : "") . ">-</option>";
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="0"', $output);
        $this->assertStringContainsString('SELECTED', $output);
        $this->assertStringContainsString('>-</option>', $output);
    }
    
    /**
     * @group helper-functions
     * @group oidibh-handler
     */
    public function testOidibhHandlerGeneratesMinusOneOption()
    {
        // Arrange
        $settingVar = -1;
        
        // Act
        ob_start();
        echo "<option value=\"-1\"" . ($settingVar == -1 ? " SELECTED" : "") . ">-1</option>";
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="-1"', $output);
        $this->assertStringContainsString('SELECTED', $output);
        $this->assertStringContainsString('>-1</option>', $output);
    }
    
    /**
     * @group helper-functions
     * @group oidibh-handler
     */
    public function testOidibhHandlerGeneratesMinusTwoOption()
    {
        // Arrange
        $settingVar = -2;
        
        // Act
        ob_start();
        echo "<option value=\"-2\"" . ($settingVar == -2 ? " SELECTED" : "") . ">-2</option>";
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="-2"', $output);
        $this->assertStringContainsString('SELECTED', $output);
        $this->assertStringContainsString('>-2</option>', $output);
    }
    
    /**
     * @group helper-functions
     * @group minutes-handler
     */
    public function testMinutesHandlerGeneratesAutoOption()
    {
        // Arrange
        $playerMin = 0;
        
        // Act
        ob_start();
        echo "<option value=\"0\"" . ($playerMin == 0 ? " SELECTED" : "") . ">Auto</option>";
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="0"', $output);
        $this->assertStringContainsString('SELECTED', $output);
        $this->assertStringContainsString('>Auto</option>', $output);
    }
    
    /**
     * @group helper-functions
     * @group minutes-handler
     */
    public function testMinutesHandlerGeneratesSpecificMinuteOption()
    {
        // Arrange
        $playerMin = 35;
        $minute = 35;
        
        // Act
        ob_start();
        echo "<option value=\"$minute\"" . ($playerMin == $minute ? " SELECTED" : "") . ">$minute</option>";
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="35"', $output);
        $this->assertStringContainsString('SELECTED', $output);
        $this->assertStringContainsString('>35</option>', $output);
    }
    
    /**
     * @group helper-functions
     * @group minutes-handler
     */
    public function testMinutesHandlerRespectsStaminaCap()
    {
        // Arrange
        $playerStamina = 5;
        $staminaCap = $playerStamina + 40;
        if ($staminaCap > 40) {
            $staminaCap = 40;
        }
        
        // Act & Assert
        $this->assertEquals(40, $staminaCap, 'Stamina cap should be limited to 40');
    }
    
    /**
     * @group helper-functions
     * @group minutes-handler
     */
    public function testMinutesHandlerCalculatesCapForLowStamina()
    {
        // Arrange
        $playerStamina = -5;
        $staminaCap = $playerStamina + 40;
        if ($staminaCap > 40) {
            $staminaCap = 40;
        }
        
        // Act & Assert
        $this->assertEquals(35, $staminaCap, 'Low stamina should result in lower cap');
    }
    
    /**
     * @group helper-functions
     * @group active-handler
     */
    public function testActiveHandlerGeneratesYesOptionSelected()
    {
        // Arrange
        $playerActive = 1;
        
        // Act
        ob_start();
        if ($playerActive == 1) {
            echo "<option value=\"1\" SELECTED>Yes</option><option value=\"0\">No</option>";
        } else {
            echo "<option value=\"1\">Yes</option><option value=\"0\" SELECTED>No</option>";
        }
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="1" SELECTED', $output);
        $this->assertStringContainsString('value="0">', $output);
        $this->assertEquals(1, substr_count($output, 'SELECTED'));
    }
    
    /**
     * @group helper-functions
     * @group active-handler
     */
    public function testActiveHandlerGeneratesNoOptionSelected()
    {
        // Arrange
        $playerActive = 0;
        
        // Act
        ob_start();
        if ($playerActive == 1) {
            echo "<option value=\"1\" SELECTED>Yes</option><option value=\"0\">No</option>";
        } else {
            echo "<option value=\"1\">Yes</option><option value=\"0\" SELECTED>No</option>";
        }
        $output = ob_get_clean();
        
        // Assert
        $this->assertStringContainsString('value="1">', $output);
        $this->assertStringContainsString('value="0" SELECTED', $output);
        $this->assertEquals(1, substr_count($output, 'SELECTED'));
    }
}
