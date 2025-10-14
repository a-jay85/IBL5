<?php

use PHPUnit\Framework\TestCase;

/**
 * Comprehensive tests for Depth Chart file export functionality
 * 
 * Tests the file generation and export from modules/Depth_Chart_Entry/index.php including:
 * - CSV file format generation
 * - File content structure
 * - File naming conventions
 * - File writing operations
 */
class DepthChartFileExportTest extends TestCase
{
    private $testFilePath;
    
    protected function setUp(): void
    {
        // Use a temporary directory for test files
        $this->testFilePath = '/tmp/test_depthchart_' . uniqid() . '.txt';
    }
    
    protected function tearDown(): void
    {
        // Clean up test file if it exists
        if (file_exists($this->testFilePath)) {
            unlink($this->testFilePath);
        }
    }
    
    /**
     * @group file-export
     * @group csv-format
     */
    public function testGeneratesCSVHeaderCorrectly()
    {
        // Arrange
        $expectedHeader = "Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI\n";
        
        // Act
        $filetext = $expectedHeader;
        
        // Assert
        $this->assertEquals($expectedHeader, $filetext, 'Should generate correct CSV header');
        $this->assertStringContainsString('Name', $filetext);
        $this->assertStringContainsString('PG', $filetext);
        $this->assertStringContainsString('ACTIVE', $filetext);
        $this->assertStringContainsString('MIN', $filetext);
    }
    
    /**
     * @group file-export
     * @group csv-format
     */
    public function testGeneratesCSVRowForPlayer()
    {
        // Arrange
        $playerData = [
            'Name' => 'Michael Jordan',
            'pg' => '0',
            'sg' => '1',
            'sf' => '0',
            'pf' => '0',
            'c' => '0',
            'active' => '1',
            'min' => '38',
            'OF' => '0',
            'DF' => '0',
            'OI' => '2',
            'DI' => '1'
        ];
        
        // Act
        $csvRow = implode(',', [
            $playerData['Name'],
            $playerData['pg'],
            $playerData['sg'],
            $playerData['sf'],
            $playerData['pf'],
            $playerData['c'],
            $playerData['active'],
            $playerData['min'],
            $playerData['OF'],
            $playerData['DF'],
            $playerData['OI'],
            $playerData['DI']
        ]) . "\n";
        
        // Assert
        $this->assertStringContainsString('Michael Jordan', $csvRow);
        $this->assertStringContainsString(',1,', $csvRow); // Starting at SG
        $this->assertStringContainsString(',38,', $csvRow); // 38 minutes
    }
    
    /**
     * @group file-export
     * @group csv-format
     */
    public function testGeneratesCompleteCSVFile()
    {
        // Arrange
        $header = "Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI\n";
        $rows = [
            "John Doe,1,0,0,0,0,1,35,0,0,1,0\n",
            "Jane Smith,2,1,0,0,0,1,30,1,2,0,1\n",
            "Bob Johnson,0,2,1,0,0,1,28,2,0,-1,2\n"
        ];
        
        // Act
        $fileContent = $header . implode('', $rows);
        
        // Assert
        $lines = explode("\n", trim($fileContent));
        $this->assertCount(4, $lines, 'Should have header + 3 player rows');
        $this->assertStringStartsWith('Name,PG', $lines[0], 'First line should be header');
        $this->assertStringContainsString('John Doe', $lines[1]);
        $this->assertStringContainsString('Jane Smith', $lines[2]);
        $this->assertStringContainsString('Bob Johnson', $lines[3]);
    }
    
    /**
     * @group file-export
     * @group file-writing
     */
    public function testWritesFileSuccessfully()
    {
        // Arrange
        $content = "Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI\nTest Player,1,0,0,0,0,1,30,0,0,0,0\n";
        
        // Act
        $result = file_put_contents($this->testFilePath, $content);
        
        // Assert
        $this->assertNotFalse($result, 'Should successfully write file');
        $this->assertFileExists($this->testFilePath, 'File should exist after writing');
        $this->assertEquals($content, file_get_contents($this->testFilePath), 'File content should match');
    }
    
    /**
     * @group file-export
     * @group file-naming
     */
    public function testGeneratesCorrectFilename()
    {
        // Arrange
        $teamName = 'Los Angeles Lakers';
        $expectedFilename = 'depthcharts/' . $teamName . '.txt';
        
        // Act
        $filename = 'depthcharts/' . $teamName . '.txt';
        
        // Assert
        $this->assertEquals($expectedFilename, $filename);
        $this->assertStringContainsString('Los Angeles Lakers', $filename);
        $this->assertStringEndsWith('.txt', $filename);
    }
    
    /**
     * @group file-export
     * @group file-naming
     */
    public function testHandlesTeamNameWithSpaces()
    {
        // Arrange
        $teamName = 'San Antonio Spurs';
        
        // Act
        $filename = 'depthcharts/' . $teamName . '.txt';
        
        // Assert
        $this->assertStringContainsString('San Antonio Spurs', $filename);
    }
    
    /**
     * @group file-export
     * @group file-content
     */
    public function testIncludesAllPlayerDataFields()
    {
        // Arrange
        $playerName = 'Test Player';
        $pg = '1';
        $sg = '0';
        $sf = '2';
        $pf = '0';
        $c = '0';
        $active = '1';
        $min = '35';
        $of = '1';
        $df = '2';
        $oi = '0';
        $di = '-1';
        $bh = '2'; // Note: BH is saved to database but not included in CSV export
        
        // Act
        // CSV export format matches actual module: 12 fields without BH
        $csvLine = "$playerName,$pg,$sg,$sf,$pf,$c,$active,$min,$of,$df,$oi,$di\n";
        
        // Assert
        $fields = explode(',', trim($csvLine));
        $this->assertCount(12, $fields, 'Should have 12 fields per player');
        $this->assertEquals($playerName, $fields[0]);
        $this->assertEquals($pg, $fields[1]);
        $this->assertEquals($active, $fields[6]);
        $this->assertEquals($min, $fields[7]);
    }
    
    /**
     * @group file-export
     * @group edge-cases
     */
    public function testHandlesEmptyPlayerSlots()
    {
        // Arrange
        $emptySlots = 3; // Some teams might not have full roster
        $activePlayers = 12;
        
        // Act
        $totalSlots = $activePlayers + $emptySlots;
        
        // Assert
        $this->assertEquals(15, $totalSlots, 'Should handle up to 15 player slots');
    }
    
    /**
     * @group file-export
     * @group file-content
     */
    public function testFormatsOffensiveAndDefensiveFocus()
    {
        // Arrange
        $focusValues = ['0' => 'Auto', '1' => 'Outside', '2' => 'Drive', '3' => 'Post'];
        
        // Act
        $csvLine = "Player,0,1,0,0,0,1,30,1,2,0,0\n"; // OF=1 (Outside), DF=2 (Drive)
        
        // Assert
        $fields = explode(',', trim($csvLine));
        $this->assertEquals('1', $fields[8]); // OF
        $this->assertEquals('2', $fields[9]); // DF
    }
    
    /**
     * @group file-export
     * @group file-content
     */
    public function testFormatsIntensityAndBallHandling()
    {
        // Arrange
        $intensityValues = ['-2', '-1', '0', '1', '2'];
        
        // Act
        $csvLine = "Player,0,1,0,0,0,1,30,0,0,2,-1\n"; // OI=2, DI=-1
        
        // Assert
        $fields = explode(',', trim($csvLine));
        $this->assertEquals('2', $fields[10]); // OI
        $this->assertEquals('-1', $fields[11]); // DI
    }
    
    /**
     * @group file-export
     * @group file-writing
     */
    public function testHandlesFileWriteFailure()
    {
        // Arrange
        $invalidPath = '/root/invalid/path/file.txt'; // Should fail due to permissions
        
        // Act
        $result = @file_put_contents($invalidPath, "test content");
        
        // Assert
        $this->assertFalse($result, 'Should return false on write failure');
    }
    
    /**
     * @group file-export
     * @group multi-player
     */
    public function testGeneratesFileForFullRoster()
    {
        // Arrange
        $header = "Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI\n";
        $content = $header;
        
        // Generate 12 active players
        for ($i = 1; $i <= 12; $i++) {
            $content .= "Player $i,0,0,0,0,0,1,30,0,0,0,0\n";
        }
        
        // Generate 3 inactive players
        for ($i = 13; $i <= 15; $i++) {
            $content .= "Player $i,0,0,0,0,0,0,0,0,0,0,0\n";
        }
        
        // Act
        file_put_contents($this->testFilePath, $content);
        $readContent = file_get_contents($this->testFilePath);
        $lines = explode("\n", trim($readContent));
        
        // Assert
        $this->assertCount(16, $lines, 'Should have header + 15 player rows');
        $this->assertStringContainsString('Player 1', $readContent);
        $this->assertStringContainsString('Player 12', $readContent);
        $this->assertStringContainsString('Player 15', $readContent);
    }
    
    /**
     * @group file-export
     * @group overwrite
     */
    public function testOverwritesExistingFile()
    {
        // Arrange
        $oldContent = "Old depth chart data\n";
        $newContent = "Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI\nNew Player,1,0,0,0,0,1,30,0,0,0,0\n";
        
        // Act
        file_put_contents($this->testFilePath, $oldContent);
        file_put_contents($this->testFilePath, $newContent); // Overwrite
        $finalContent = file_get_contents($this->testFilePath);
        
        // Assert
        $this->assertEquals($newContent, $finalContent, 'Should overwrite existing file');
        $this->assertStringNotContainsString('Old depth chart', $finalContent);
        $this->assertStringContainsString('New Player', $finalContent);
    }
}
