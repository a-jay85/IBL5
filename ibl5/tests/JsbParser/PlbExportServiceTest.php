<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\PlbExportService;
use JsbParser\PlbFileParser;
use JsbParser\PlbFileWriter;
use PHPUnit\Framework\TestCase;
use PlrParser\Contracts\PlrExportRepositoryInterface;
use Tests\PlrParser\JsbExportFixtures;

/**
 * @covers \JsbParser\PlbExportService
 */
class PlbExportServiceTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/plb_export_' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->dir . '/*') as $file) {
            if (is_string($file)) {
                unlink($file);
            }
        }
        rmdir($this->dir);
    }

    /**
     * @param array<int, array{pid: int, teamid: int, dc_pg_depth: int, dc_sg_depth: int, dc_sf_depth: int, dc_pf_depth: int, dc_c_depth: int, dc_can_play_in_game: int, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}> $charts
     */
    private function service(array $charts = []): PlbExportService
    {
        $repo = self::createStub(PlrExportRepositoryInterface::class);
        $repo->method('getAllPlayerDepthCharts')->willReturn($charts);

        return new PlbExportService($repo);
    }

    /**
     * @param array<int, array{pid: int, teamid: int}> $source ordinal => player
     * @param array<int, array{pid: int, teamid: int}> $export ordinal => player
     * @return array{0: string, 1: string, 2: string, 3: string} source plr, export plr, source plb, output plb
     */
    private function files(array $source, array $export, string $plb): array
    {
        $paths = [$this->dir . '/IBL5.plr', $this->dir . '/IBL5_export.plr', $this->dir . '/IBL5.plb', $this->dir . '/IBL5_export.plb'];
        file_put_contents($paths[0], JsbExportFixtures::content(32, $source));
        file_put_contents($paths[1], JsbExportFixtures::content(32, $export));
        file_put_contents($paths[2], $plb);

        return $paths;
    }

    /**
     * Source .plb with slot (19, 2) = 30 min and (19, 0) = 20 min; every other slot six zeros.
     */
    private static function sourcePlb(): string
    {
        return PlbFileWriter::applySlots(JsbExportFixtures::plb(), [19 => [
            0 => ['dc_minutes' => 20, 'dc_of' => 0, 'dc_df' => 0, 'dc_oi' => 0, 'dc_di' => 0, 'dc_bh' => 0],
            2 => ['dc_minutes' => 30, 'dc_of' => 1, 'dc_df' => 2, 'dc_oi' => -1, 'dc_di' => 0, 'dc_bh' => 1],
        ]]);
    }

    public function testMovedPlayerCarriesSourceSlotAndVacatedSlotCleared(): void
    {
        // pid 503 moves from 573 (team 20, slot 2) to 604 (team 21, slot 3).
        [$srcPlr, $expPlr, $srcPlb, $outPlb] = $this->files(
            [571 => ['pid' => 501, 'teamid' => 20], 573 => ['pid' => 503, 'teamid' => 20]],
            [571 => ['pid' => 501, 'teamid' => 20], 604 => ['pid' => 503, 'teamid' => 21]],
            self::sourcePlb(),
        );

        $result = $this->service()->exportPlbFile($srcPlr, $expPlr, $srcPlb, $outPlb);
        $parsed = PlbFileParser::parse((string) file_get_contents($outPlb));

        self::assertSame(0, $result->errors);
        self::assertSame(['slot_index' => 2] + PlbExportService::VACATED_SLOT, $parsed[19][2]);
        self::assertSame(
            ['slot_index' => 3, 'dc_minutes' => 30, 'dc_of' => 1, 'dc_df' => 2, 'dc_oi' => -1, 'dc_di' => 0, 'dc_bh' => 1],
            $parsed[20][3],
        );
        self::assertSame(20, $parsed[19][0]['dc_minutes']);
        self::assertSame(strlen(self::sourcePlb()), filesize($outPlb));
        self::assertContains('Wrote ' . strlen(self::sourcePlb()) . ' bytes to ' . $outPlb, $result->messages);
    }

    public function testChartedTeamSlotsWrittenFromDb(): void
    {
        [$srcPlr, $expPlr, $srcPlb, $outPlb] = $this->files(
            [571 => ['pid' => 501, 'teamid' => 20], 572 => ['pid' => 502, 'teamid' => 20]],
            [571 => ['pid' => 501, 'teamid' => 20], 572 => ['pid' => 502, 'teamid' => 20]],
            self::sourcePlb(),
        );
        $charts = [
            501 => JsbExportFixtures::chartRow(501, 20, ['dc_minutes' => 40, 'dc_oi' => 2, 'dc_bh' => -1]),
            502 => JsbExportFixtures::chartRow(502, 20),
        ];

        $result = $this->service($charts)->exportPlbFile($srcPlr, $expPlr, $srcPlb, $outPlb);
        $parsed = PlbFileParser::parse((string) file_get_contents($outPlb));

        self::assertSame(
            ['slot_index' => 0, 'dc_minutes' => 40, 'dc_of' => 0, 'dc_df' => 0, 'dc_oi' => 2, 'dc_di' => 0, 'dc_bh' => -1],
            $parsed[19][0],
        );
        self::assertSame(0, $parsed[19][1]['dc_minutes']);
        self::assertSame(
            [
                'pid' => 501,
                'name' => 'Player 501',
                'changes' => [
                    ['field' => 'plb_minutes', 'old' => 20, 'new' => 40],
                    ['field' => 'plb_oi', 'old' => 0, 'new' => 2],
                    ['field' => 'plb_bh', 'old' => 0, 'new' => -1],
                ],
            ],
            $result->changeLog[0],
        );
        self::assertCount(1, $result->changeLog);
    }

    public function testUnchartedTeamLineUnchanged(): void
    {
        [$srcPlr, $expPlr, $srcPlb, $outPlb] = $this->files(
            [571 => ['pid' => 501, 'teamid' => 20], 541 => ['pid' => 401, 'teamid' => 19]],
            [571 => ['pid' => 501, 'teamid' => 20], 541 => ['pid' => 401, 'teamid' => 19]],
            self::sourcePlb(),
        );
        $charts = [
            501 => JsbExportFixtures::chartRow(501, 20),
            401 => JsbExportFixtures::chartRow(401, 19, ['dc_minutes' => 25]),
        ];

        $this->service($charts)->exportPlbFile($srcPlr, $expPlr, $srcPlb, $outPlb);

        $sourceLines = explode("\r\n", self::sourcePlb());
        $outputLines = explode("\r\n", (string) file_get_contents($outPlb));
        self::assertSame($sourceLines[19], $outputLines[19]);
        self::assertNotSame($sourceLines[18], $outputLines[18]);
    }

    public function testOutOfRangeChartValueAddsErrorAndWritesNothing(): void
    {
        [$srcPlr, $expPlr, $srcPlb, $outPlb] = $this->files(
            [571 => ['pid' => 501, 'teamid' => 20]],
            [571 => ['pid' => 501, 'teamid' => 20]],
            self::sourcePlb(),
        );
        $charts = [501 => JsbExportFixtures::chartRow(501, 20, ['dc_minutes' => 100])];

        $result = $this->service($charts)->exportPlbFile($srcPlr, $expPlr, $srcPlb, $outPlb);

        self::assertSame(1, $result->errors);
        self::assertSame(
            ['ERROR: PLB write stopped: PLB value 100 for dc_minutes does not fit in two chars'],
            $result->messages,
        );
        self::assertFileDoesNotExist($outPlb);
    }

    public function testMissingSourcePlbAddsErrorAndWritesNothing(): void
    {
        [$srcPlr, $expPlr, , $outPlb] = $this->files([], [], self::sourcePlb());
        $missing = $this->dir . '/missing.plb';

        $result = $this->service()->exportPlbFile($srcPlr, $expPlr, $missing, $outPlb);

        self::assertSame(1, $result->errors);
        self::assertSame(['ERROR: source .plb not found at ' . $missing], $result->messages);
        self::assertFileDoesNotExist($outPlb);
    }

    public function testSourceFilesAreNotModified(): void
    {
        [$srcPlr, $expPlr, $srcPlb, $outPlb] = $this->files(
            [573 => ['pid' => 503, 'teamid' => 20]],
            [604 => ['pid' => 503, 'teamid' => 21]],
            self::sourcePlb(),
        );
        $before = array_map(static fn (string $p): string => (string) hash_file('sha256', $p), [$srcPlr, $expPlr, $srcPlb]);

        $this->service([503 => JsbExportFixtures::chartRow(503, 21, ['dc_minutes' => 10])])
            ->exportPlbFile($srcPlr, $expPlr, $srcPlb, $outPlb);

        $after = array_map(static fn (string $p): string => (string) hash_file('sha256', $p), [$srcPlr, $expPlr, $srcPlb]);
        self::assertSame($before, $after);
        self::assertFileExists($outPlb);
    }
}
