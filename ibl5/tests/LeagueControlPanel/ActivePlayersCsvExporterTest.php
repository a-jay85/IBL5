<?php

declare(strict_types=1);

namespace Tests\LeagueControlPanel;

use LeagueControlPanel\ActivePlayersCsvExporter;
use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LeagueControlPanel\ActivePlayersCsvExporter
 */
class ActivePlayersCsvExporterTest extends TestCase
{
    private string $exportDir;

    protected function setUp(): void
    {
        $this->exportDir = sys_get_temp_dir() . '/ibl5-exports-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $files = glob($this->exportDir . '/*');
        foreach ($files !== false ? $files : [] as $file) {
            unlink($file);
        }
        if (is_dir($this->exportDir)) {
            rmdir($this->exportDir);
        }
    }

    public function testBuildCsvQuotesEveryNameOnItsOwnLine(): void
    {
        $csv = ActivePlayersCsvExporter::buildCsv(["A'Ja Wilson", 'Aaron Nesmith']);

        $this->assertSame("\"A'Ja Wilson\"\n\"Aaron Nesmith\"\n", $csv);
    }

    public function testBuildCsvEscapesEmbeddedQuotes(): void
    {
        $this->assertSame("\"Bob \"\"Big\"\" Jones\"\n", ActivePlayersCsvExporter::buildCsv(['Bob "Big" Jones']));
    }

    public function testBuildCsvEmptyListIsEmptyString(): void
    {
        $this->assertSame('', ActivePlayersCsvExporter::buildCsv([]));
    }

    public function testExportWritesTimestampedFileWithPlayerNames(): void
    {
        $stub = self::createStub(LeagueControlPanelRepositoryInterface::class);
        $stub->method('getActivePlayerNames')->willReturn(['Aaron Nesmith', 'Zion Williamson']);
        $exporter = new ActivePlayersCsvExporter($stub, $this->exportDir);

        $filename = $exporter->export(new \DateTimeImmutable('2026-09-17 20:16:09'));

        $this->assertSame('iblhoops_ibl5_2026-09-17_20-16-09.csv', $filename);
        $path = $exporter->resolvePath($filename);
        $this->assertNotNull($path);
        $this->assertSame("\"Aaron Nesmith\"\n\"Zion Williamson\"\n", file_get_contents($path));
    }

    public function testResolvePathReturnsNullForMissingFile(): void
    {
        $exporter = new ActivePlayersCsvExporter(self::createStub(LeagueControlPanelRepositoryInterface::class), $this->exportDir);

        $this->assertNull($exporter->resolvePath('iblhoops_ibl5_2026-01-01_00-00-00.csv'));
    }

    #[DataProvider('invalidFilenameProvider')]
    public function testResolvePathRejectsNamesOutsideTheExportPattern(string $filename): void
    {
        $exporter = new ActivePlayersCsvExporter(self::createStub(LeagueControlPanelRepositoryInterface::class), $this->exportDir);

        $this->assertNull($exporter->resolvePath($filename));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidFilenameProvider(): array
    {
        return [
            'path traversal' => ['../../etc/passwd'],
            'traversal with valid suffix' => ['../iblhoops_ibl5_2026-01-01_00-00-00.csv'],
            'other csv' => ['players.csv'],
            'wrong extension' => ['iblhoops_ibl5_2026-01-01_00-00-00.php'],
            'empty' => [''],
        ];
    }
}
