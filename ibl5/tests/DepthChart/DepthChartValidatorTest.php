<?php

use PHPUnit\Framework\TestCase;
use DepthChart\DepthChartValidator;

class DepthChartValidatorTest extends TestCase
{
    private $validator;
    
    protected function setUp(): void
    {
        $this->validator = new DepthChartValidator();
    }
    
    public function testValidatesSuccessfullyWithValidRegularSeasonData()
    {
        $depthChartData = [
            'activePlayers' => 12,
            'pos_1' => 3,
            'pos_2' => 3,
            'pos_3' => 3,
            'pos_4' => 3,
            'pos_5' => 3,
            'hasStarterAtMultiplePositions' => false,
            'nameOfProblemStarter' => ''
        ];
        
        $result = $this->validator->validate($depthChartData, 'Regular Season');
        
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }
    
    public function testValidatesSuccessfullyWithValidPlayoffsData()
    {
        $depthChartData = [
            'activePlayers' => 10,
            'pos_1' => 2,
            'pos_2' => 2,
            'pos_3' => 2,
            'pos_4' => 2,
            'pos_5' => 2,
            'hasStarterAtMultiplePositions' => false,
            'nameOfProblemStarter' => ''
        ];
        
        $result = $this->validator->validate($depthChartData, 'Playoffs');
        
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }
    
    public function testFailsValidationWithTooFewActivePlayers()
    {
        $depthChartData = [
            'activePlayers' => 10,
            'pos_1' => 3,
            'pos_2' => 3,
            'pos_3' => 3,
            'pos_4' => 3,
            'pos_5' => 3,
            'hasStarterAtMultiplePositions' => false,
            'nameOfProblemStarter' => ''
        ];
        
        $result = $this->validator->validate($depthChartData, 'Regular Season');
        
        $this->assertFalse($result);
        $this->assertNotEmpty($this->validator->getErrors());
        $this->assertEquals('active_players_min', $this->validator->getErrors()[0]['type']);
    }
    
    public function testFailsValidationWithTooManyActivePlayers()
    {
        $depthChartData = [
            'activePlayers' => 13,
            'pos_1' => 3,
            'pos_2' => 3,
            'pos_3' => 3,
            'pos_4' => 3,
            'pos_5' => 3,
            'hasStarterAtMultiplePositions' => false,
            'nameOfProblemStarter' => ''
        ];
        
        $result = $this->validator->validate($depthChartData, 'Regular Season');
        
        $this->assertFalse($result);
        $this->assertNotEmpty($this->validator->getErrors());
        $this->assertEquals('active_players_max', $this->validator->getErrors()[0]['type']);
    }
    
    public function testFailsValidationWithInsufficientPositionDepth()
    {
        $depthChartData = [
            'activePlayers' => 12,
            'pos_1' => 2,  // Insufficient for Regular Season
            'pos_2' => 3,
            'pos_3' => 3,
            'pos_4' => 3,
            'pos_5' => 3,
            'hasStarterAtMultiplePositions' => false,
            'nameOfProblemStarter' => ''
        ];
        
        $result = $this->validator->validate($depthChartData, 'Regular Season');
        
        $this->assertFalse($result);
        $this->assertNotEmpty($this->validator->getErrors());
        $this->assertEquals('position_depth', $this->validator->getErrors()[0]['type']);
    }
    
    public function testFailsValidationWithMultipleStartingPositions()
    {
        $depthChartData = [
            'activePlayers' => 12,
            'pos_1' => 3,
            'pos_2' => 3,
            'pos_3' => 3,
            'pos_4' => 3,
            'pos_5' => 3,
            'hasStarterAtMultiplePositions' => true,
            'nameOfProblemStarter' => 'John Doe'
        ];
        
        $result = $this->validator->validate($depthChartData, 'Regular Season');
        
        $this->assertFalse($result);
        $this->assertNotEmpty($this->validator->getErrors());
        $this->assertEquals('multiple_starting_positions', $this->validator->getErrors()[0]['type']);
    }
    
    public function testReturnsFormattedErrorMessages()
    {
        $depthChartData = [
            'activePlayers' => 10,
            'pos_1' => 3,
            'pos_2' => 3,
            'pos_3' => 3,
            'pos_4' => 3,
            'pos_5' => 3,
            'hasStarterAtMultiplePositions' => false,
            'nameOfProblemStarter' => ''
        ];
        
        $this->validator->validate($depthChartData, 'Regular Season');
        $errorHtml = $this->validator->getErrorMessagesHtml();
        
        $this->assertStringContainsString('<font color=red>', $errorHtml);
        $this->assertStringContainsString('at least 12 active players', $errorHtml);
    }
}
