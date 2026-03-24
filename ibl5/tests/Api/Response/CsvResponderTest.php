<?php

declare(strict_types=1);

namespace Tests\Api\Response;

use Api\Response\CsvResponder;
use PHPUnit\Framework\TestCase;

class CsvResponderTest extends TestCase
{
    private CsvResponder $responder;

    protected function setUp(): void
    {
        $this->responder = new CsvResponder();
    }

    public function testRenderRowsWritesHeaderAndDataRows(): void
    {
        $handle = fopen('php://memory', 'w+');
        $this->assertNotFalse($handle);

        $rows = [
            ['Name', 'Age', 'Position'],
            ['LeBron James', '39', 'SF'],
            ['Steph Curry', '36', 'PG'],
        ];

        $this->responder->renderRows($handle, $rows);

        rewind($handle);
        $output = stream_get_contents($handle);
        fclose($handle);

        $this->assertIsString($output);
        $lines = explode("\n", trim((string) $output));
        $this->assertCount(3, $lines);
        $this->assertSame('Name,Age,Position', $lines[0]);

        $parsed = str_getcsv($lines[1], ',', '"', '\\');
        $this->assertSame('LeBron James', $parsed[0]);
        $this->assertSame('39', $parsed[1]);
        $this->assertSame('SF', $parsed[2]);
    }

    public function testRenderRowsEscapesCommasInValues(): void
    {
        $handle = fopen('php://memory', 'w+');
        $this->assertNotFalse($handle);

        $rows = [
            ['Name', 'Team'],
            ['James, LeBron', 'Los Angeles Lakers'],
        ];

        $this->responder->renderRows($handle, $rows);

        rewind($handle);
        $output = stream_get_contents($handle);
        fclose($handle);

        $this->assertIsString($output);
        $this->assertStringContainsString('"James, LeBron"', (string) $output);
    }

    public function testRenderRowsHandlesEmptyValues(): void
    {
        $handle = fopen('php://memory', 'w+');
        $this->assertNotFalse($handle);

        $rows = [
            ['Name', 'Team'],
            ['Free Agent', ''],
        ];

        $this->responder->renderRows($handle, $rows);

        rewind($handle);
        $output = stream_get_contents($handle);
        fclose($handle);

        $this->assertIsString($output);
        $lines = explode("\n", trim((string) $output));
        $parsed = str_getcsv($lines[1], ',', '"', '\\');
        $this->assertSame('Free Agent', $parsed[0]);
        $this->assertSame('', $parsed[1]);
    }

    public function testRenderRowsHandlesEmptyRowList(): void
    {
        $handle = fopen('php://memory', 'w+');
        $this->assertNotFalse($handle);

        $this->responder->renderRows($handle, []);

        rewind($handle);
        $output = stream_get_contents($handle);
        fclose($handle);

        $this->assertSame('', $output);
    }
}
