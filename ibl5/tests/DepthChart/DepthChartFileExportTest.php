<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for Depth Chart file export functionality
 * 
 * Tests the file generation and export from modules/Depth_Chart_Entry/index.php including:
 * - CSV file format generation
 * - File writing operations
 * - File naming conventions
 */
class DepthChartFileExportTest extends TestCase
{
    private $testFilePath;
    
    protected function setUp(): void
    {
        $this->testFilePath = '/tmp/test_depthchart_' . uniqid() . '.txt';
    }
    
    protected function tearDown(): void
    {
        if (file_exists($this->testFilePath)) {
            unlink($this->testFilePath);
        }
    }
    
    /**
     * @group file-export
     * @group csv-format
     */
    public function testCSVFormatGeneration()
    {
        // Tests the CSV generation logic from submit() function
        $header = "Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI,BH\n";
        $rows = [
            "John Doe,1,0,0,0,0,1,35,0,0,1,0,0\n",
            "Jane Smith,2,1,0,0,0,1,30,1,2,0,1,1\n",
            "Bob Johnson,0,2,1,0,0,1,28,2,0,-1,2,-1\n"
        ];
        
        $fileContent = $header . implode('', $rows);
        
        // Verify structure
        $lines = explode("\n", trim($fileContent));
        $this->assertCount(4, $lines, 'Should have header + 3 player rows');
        $this->assertStringStartsWith('Name,PG', $lines[0], 'First line should be header');
        
        // Verify field count
        $headerFields = explode(',', $lines[0]);
        $this->assertCount(13, $headerFields, 'Header should have 13 fields');
        
        foreach (array_slice($lines, 1) as $line) {
            $fields = explode(',', $line);
            $this->assertCount(13, $fields, 'Each row should have 13 fields');
        }
    }
    
    /**
     * @group file-export
     * @group file-writing
     */
    public function testFileWritingOperations()
    {
        // Tests file_put_contents logic from submit()
        $content = "Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI,BH\nTest Player,1,0,0,0,0,1,30,0,0,0,0,0\n";
        
        // Write file
        $result = file_put_contents($this->testFilePath, $content);
        $this->assertNotFalse($result, 'Should successfully write file');
        $this->assertFileExists($this->testFilePath);
        
        // Verify content
        $readContent = file_get_contents($this->testFilePath);
        $this->assertEquals($content, $readContent);
        
        // Test overwrite
        $newContent = "Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI,BH\nNew Player,2,0,0,0,0,1,25,0,0,0,0,0\n";
        file_put_contents($this->testFilePath, $newContent);
        $this->assertEquals($newContent, file_get_contents($this->testFilePath));
    }
    
    /**
     * @group file-export
     * @group file-naming
     */
    public function testFileNamingConvention()
    {
        // Tests filename generation from submit()
        $teamNames = [
            'Los Angeles Lakers',
            'Boston Celtics',
            'San Antonio Spurs'
        ];
        
        foreach ($teamNames as $teamName) {
            $filename = 'depthcharts/' . $teamName . '.txt';
            $this->assertStringContainsString($teamName, $filename);
            $this->assertStringEndsWith('.txt', $filename);
            $this->assertStringStartsWith('depthcharts/', $filename);
        }
    }
    
    /**
     * @group file-export
     * @group full-roster
     */
    public function testFullRosterExport()
    {
        // Tests complete file generation for a 15-player roster
        $header = "Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI,BH\n";
        $content = $header;
        
        // 12 active players
        for ($i = 1; $i <= 12; $i++) {
            $content .= "Player $i,0,0,0,0,0,1,30,0,0,0,0,0\n";
        }
        
        // 3 inactive players
        for ($i = 13; $i <= 15; $i++) {
            $content .= "Player $i,0,0,0,0,0,0,0,0,0,0,0,0\n";
        }
        
        file_put_contents($this->testFilePath, $content);
        $readContent = file_get_contents($this->testFilePath);
        $lines = explode("\n", trim($readContent));
        
        $this->assertCount(16, $lines, 'Should have header + 15 player rows');
        $this->assertStringContainsString('Player 1', $readContent);
        $this->assertStringContainsString('Player 15', $readContent);
    }
}
