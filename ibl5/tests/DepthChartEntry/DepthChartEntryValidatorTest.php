<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use PHPUnit\Framework\TestCase;
use DepthChartEntry\DepthChartEntryValidator;

class DepthChartEntryValidatorTest extends TestCase
{
    private $validator;
    
    protected function setUp(): void
    {
        $this->validator = new DepthChartEntryValidator();
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
        
        $this->assertStringContainsString('color: red', $errorHtml);
        $this->assertStringContainsString('at least 12 active players', $errorHtml);
    }
    
    public function testValidatesActivePlayerCountOnly()
    {
        $depthChartData = [
            'activePlayers' => 8,  // Too few for Regular Season (min 12)
            'pos_1' => 1,
            'pos_2' => 1,
            'pos_3' => 3,
            'pos_4' => 3,
            'pos_5' => 3,
            'hasStarterAtMultiplePositions' => true,
            'nameOfProblemStarter' => 'John Doe'
        ];

        $result = $this->validator->validate($depthChartData, 'Regular Season');

        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals('active_players_min', $errors[0]['type']);
    }
    
    public function testEdgeCaseExactlyAtMinimumRequirements()
    {
        $depthChartData = [
            'activePlayers' => 12,  // Exactly minimum
            'pos_1' => 3,  // Exactly minimum
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
    
    public function testEdgeCaseExactlyAtMaximumActivePlayers()
    {
        $depthChartData = [
            'activePlayers' => 12,  // Exactly maximum
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
    
    public function testPlayoffsAllowsFewerActivePlayers()
    {
        $depthChartData = [
            'activePlayers' => 11,  // Valid for playoffs
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
    
    public function testPassesValidationRegardlessOfPositionDepth()
    {
        $depthChartData = [
            'activePlayers' => 12,
            'pos_1' => 3,
            'pos_2' => 3,
            'pos_3' => 1,  // Low position depth is no longer validated
            'pos_4' => 3,
            'pos_5' => 3,
            'hasStarterAtMultiplePositions' => false,
            'nameOfProblemStarter' => ''
        ];

        $result = $this->validator->validate($depthChartData, 'Regular Season');

        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }
}
