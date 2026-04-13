<?php

declare(strict_types=1);

namespace Tests\Unit\BulkImport;

use BulkImport\BulkImportRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BulkImport\BulkImportRunner
 */
class BulkImportRunnerTest extends TestCase
{
    // --- parseFolderName() ---

    public function testParseFolderNameDetectsSimDirectory(): void
    {
        $result = BulkImportRunner::parseFolderName('IBL0102Sim15');

        $this->assertSame(2002, $result['year']);
        $this->assertSame('Regular Season/Playoffs', $result['phase']);
    }

    public function testParseFolderNameDetectsHeatEnd(): void
    {
        $result = BulkImportRunner::parseFolderName('IBL0607HEATend');

        $this->assertSame(2007, $result['year']);
        $this->assertSame('HEAT', $result['phase']);
    }

    public function testParseFolderNameDetectsPreseason(): void
    {
        $result = BulkImportRunner::parseFolderName('IBL0506Preseason');

        $this->assertSame(2006, $result['year']);
        $this->assertSame('Preseason', $result['phase']);
    }

    public function testParseFolderNameDetectsPlayoffs(): void
    {
        $result = BulkImportRunner::parseFolderName('IBL0203Playoffs');

        $this->assertSame(2003, $result['year']);
        $this->assertSame('Regular Season/Playoffs', $result['phase']);
    }

    public function testParseFolderNameDetectsFinals(): void
    {
        $result = BulkImportRunner::parseFolderName('IBL9900Finals');

        $this->assertSame(2000, $result['year']);
        $this->assertSame('Regular Season/Playoffs', $result['phase']);
    }

    public function testParseFolderNameDetectsSeason(): void
    {
        $result = BulkImportRunner::parseFolderName('IBL0405SeasonEnd');

        $this->assertSame(2005, $result['year']);
        $this->assertSame('Regular Season/Playoffs', $result['phase']);
    }

    public function testParseFolderNameHandles1900sYears(): void
    {
        $result = BulkImportRunner::parseFolderName('IBL8889Sim10');

        $this->assertSame(1989, $result['year']);
        $this->assertSame('Regular Season/Playoffs', $result['phase']);
    }

    public function testParseFolderNameReturnsNullForUnrecognizedPhase(): void
    {
        $result = BulkImportRunner::parseFolderName('IBL0506Unknown');

        $this->assertSame(2006, $result['year']);
        $this->assertNull($result['phase']);
    }

    public function testParseFolderNameReturnsNullForNoYear(): void
    {
        $result = BulkImportRunner::parseFolderName('Readme');

        $this->assertNull($result['year']);
        $this->assertNull($result['phase']);
    }

    #[DataProvider('preseasonPriorityProvider')]
    public function testPreseasonTakesPriorityOverHeatAndSim(string $name): void
    {
        $result = BulkImportRunner::parseFolderName($name);

        $this->assertSame('Preseason', $result['phase']);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function preseasonPriorityProvider(): array
    {
        return [
            'preseason keyword' => ['IBL0506Preseason'],
            'preseason with number' => ['IBL0506Preseason2'],
        ];
    }

    #[DataProvider('heatPriorityProvider')]
    public function testHeatTakesPriorityOverSimKeyword(string $name, string $expectedPhase): void
    {
        $result = BulkImportRunner::parseFolderName($name);

        $this->assertSame($expectedPhase, $result['phase']);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function heatPriorityProvider(): array
    {
        return [
            'heat-end' => ['IBL0607HEATend', 'HEAT'],
            'heat-finals' => ['IBL0607HEATfinals', 'HEAT'],
        ];
    }
}
