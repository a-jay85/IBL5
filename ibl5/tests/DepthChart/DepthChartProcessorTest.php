<?php

use PHPUnit\Framework\TestCase;
use DepthChart\DepthChartProcessor;

class DepthChartProcessorTest extends TestCase
{
    private $processor;
    
    protected function setUp(): void
    {
        $this->processor = new DepthChartProcessor();
    }
    
    public function testProcessesSubmissionCorrectly()
    {
        $postData = [
            'Name1' => 'Player One',
            'pg1' => '1',
            'sg1' => '0',
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'active1' => '1',
            'min1' => '30',
            'OF1' => '0',
            'DF1' => '1',
            'OI1' => '0',
            'DI1' => '0',
            'BH1' => '0',
            'Injury1' => '0',
            'Name2' => 'Player Two',
            'pg2' => '2',
            'sg2' => '0',
            'sf2' => '0',
            'pf2' => '0',
            'c2' => '0',
            'active2' => '1',
            'min2' => '25',
            'OF2' => '0',
            'DF2' => '0',
            'OI2' => '1',
            'DI2' => '-1',
            'BH2' => '0',
            'Injury2' => '0'
        ];
        
        $result = $this->processor->processSubmission($postData, 15);
        
        $this->assertEquals(2, count($result['playerData']));
        $this->assertEquals(2, $result['activePlayers']);
        $this->assertEquals(2, $result['pos_1']);  // Two players at PG
        $this->assertFalse($result['hasStarterAtMultiplePositions']);
    }
    
    public function testDetectsMultipleStartingPositions()
    {
        $postData = [
            'Name1' => 'Player One',
            'pg1' => '1',  // Starting at PG
            'sg1' => '1',  // Also starting at SG - INVALID
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'active1' => '1',
            'min1' => '30',
            'OF1' => '0',
            'DF1' => '0',
            'OI1' => '0',
            'DI1' => '0',
            'BH1' => '0',
            'Injury1' => '0'
        ];
        
        $result = $this->processor->processSubmission($postData, 15);
        
        $this->assertTrue($result['hasStarterAtMultiplePositions']);
        $this->assertEquals('Player One', $result['nameOfProblemStarter']);
    }
    
    public function testExcludesInjuredPlayersFromPositionCount()
    {
        $postData = [
            'Name1' => 'Player One',
            'pg1' => '1',
            'sg1' => '0',
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'active1' => '1',
            'min1' => '30',
            'OF1' => '0',
            'DF1' => '0',
            'OI1' => '0',
            'DI1' => '0',
            'BH1' => '0',
            'Injury1' => '15'  // Injured
        ];
        
        $result = $this->processor->processSubmission($postData, 15);
        
        $this->assertEquals(0, $result['pos_1']);  // Injured player not counted
        $this->assertEquals(1, $result['activePlayers']);  // Still counts as active
    }
    
    public function testGeneratesCsvContentCorrectly()
    {
        $playerData = [
            [
                'name' => 'Player One',
                'pg' => '1',
                'sg' => '0',
                'sf' => '0',
                'pf' => '0',
                'c' => '0',
                'active' => '1',
                'min' => '30',
                'of' => '0',
                'df' => '1',
                'oi' => '0',
                'di' => '0',
                'bh' => '0'
            ]
        ];
        
        $csv = $this->processor->generateCsvContent($playerData);
        
        $this->assertStringContainsString('Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI', $csv);
        $this->assertStringContainsString('Player One,1,0,0,0,0,1,30,0,1,0,0,0', $csv);
    }
    
    public function testGetsPositionValueCorrectly()
    {
        $this->assertEquals(1, $this->processor->getPositionValue('PG'));
        $this->assertEquals(3, $this->processor->getPositionValue('SG'));
        $this->assertEquals(5, $this->processor->getPositionValue('SF'));
        $this->assertEquals(7, $this->processor->getPositionValue('PF'));
        $this->assertEquals(9, $this->processor->getPositionValue('C'));
        $this->assertEquals(0, $this->processor->getPositionValue('INVALID'));
    }
    
    public function testCanPlayAtPositionChecksEligibility()
    {
        // PG can play at PG position (1-9 range)
        $this->assertTrue($this->processor->canPlayAtPosition('PG', 1, 9, 0));
        
        // C cannot play at PG position if range is restricted
        $this->assertFalse($this->processor->canPlayAtPosition('C', 1, 3, 0));
        
        // Injured player cannot play
        $this->assertFalse($this->processor->canPlayAtPosition('PG', 1, 9, 15));
        
        // Healthy player with eligible position can play
        $this->assertTrue($this->processor->canPlayAtPosition('SF', 1, 9, 5));
    }
}
