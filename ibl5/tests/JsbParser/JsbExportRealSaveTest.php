<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\PlbExportService;
use JsbParser\PlbFileParser;
use JsbParser\PlbFileWriter;
use PHPUnit\Framework\TestCase;
use PlrParser\Contracts\PlrExportRepositoryInterface;
use PlrParser\PlrExportService;
use PlrParser\PlrFileWriter;
use PlrParser\PlrTradeRelocator;

/**
 * Checks the JSB export against the real 2008-09 sim08 and sim09 saves.
 *
 * Between sim08 and sim09 JSB applied one trade: Turner (4500) and Cody Martin
 * (6363) to the Grizzlies (tid 20), Kagami (2005) and Pittman (6930) to the
 * Lakers (tid 21). The zips are gitignored, so CI and fresh worktrees skip.
 * Set JSB_SAVES_DIR to the folder that holds them.
 *
 * @covers \JsbParser\PlbFileWriter
 * @covers \JsbParser\PlbExportService
 * @covers \PlrParser\PlrTradeRelocator
 */
class JsbExportRealSaveTest extends TestCase
{
    private const SIM08_ZIP = '08-09_19_reg-sim08.zip';
    private const SIM09_ZIP = '08-09_20_reg-sim09.zip';

    /** @var array<int, int> pid => post-trade teamid */
    private const TRADE = [4500 => 20, 6363 => 20, 2005 => 21, 6930 => 21];

    /** Grizzlies (tid 20) and Lakers (tid 21) ordinal blocks, written out so a wrong block size fails. */
    private const BLOCKS = [20 => [571, 600], 21 => [601, 630]];

    private const DB_TO_PLR_FIELD = [
        'teamid' => 'teamid',
        'bird' => 'bird',
        'cy' => 'cy',
        'cyt' => 'cyt',
        'salary_yr1' => 'salary_yr1',
        'salary_yr2' => 'salary_yr2',
        'salary_yr3' => 'salary_yr3',
        'salary_yr4' => 'salary_yr4',
        'salary_yr5' => 'salary_yr5',
        'salary_yr6' => 'salary_yr6',
        'fa_signing_flag' => 'freeAgentSigningFlag',
    ];

    private string $savesDir;

    protected function setUp(): void
    {
        $env = getenv('JSB_SAVES_DIR');
        $this->savesDir = is_string($env) && $env !== '' ? $env : __DIR__ . '/../../backups/08-09';

        if (!class_exists(\ZipArchive::class)
            || !is_file($this->savesDir . '/' . self::SIM08_ZIP)
            || !is_file($this->savesDir . '/' . self::SIM09_ZIP)) {
            // phpunit-hygiene-allow: integration-availability skip; the real JSB save zips are gitignored and exist only on the commissioner's machine
            self::markTestSkipped('JSB save zips not found in ' . $this->savesDir);
        }
    }

    private function member(string $zipName, string $file): string
    {
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($this->savesDir . '/' . $zipName) === true);
        $index = $zip->locateName($file, \ZipArchive::FL_NODIR | \ZipArchive::FL_NOCASE);
        self::assertIsInt($index);
        $name = $zip->getNameIndex($index);
        self::assertIsString($name);
        $bytes = $zip->getFromName($name);
        $zip->close();
        self::assertIsString($bytes);

        return $bytes;
    }

    private static function pidAt(string $line): int
    {
        return (int) trim(substr($line, PlrFileWriter::OFFSET_PID, PlrFileWriter::WIDTH_PID));
    }

    /**
     * @param list<string> $lines
     * @return array<int, int> pid => ordinal for ordinals 1..1440
     */
    private static function ordinalsByPid(array $lines): array
    {
        $map = [];
        for ($ordinal = 1; $ordinal <= PlrFileWriter::MAX_PLAYER_ORDINAL; $ordinal++) {
            $pid = self::pidAt($lines[$ordinal]);
            if ($pid !== 0) {
                $map[$pid] = $ordinal;
            }
        }

        return $map;
    }

    /**
     * @param list<string> $lines
     * @return list<int> sorted pids in the ordinal range
     */
    private static function blockPids(array $lines, int $first, int $last): array
    {
        $pids = [];
        for ($ordinal = $first; $ordinal <= $last; $ordinal++) {
            $pid = self::pidAt($lines[$ordinal]);
            if ($pid !== 0) {
                $pids[] = $pid;
            }
        }
        sort($pids);

        return $pids;
    }

    public function testPlbRoundTripIsByteIdentical(): void
    {
        $content = $this->member(self::SIM08_ZIP, 'IBL5.plb');
        $updates = [];
        foreach (PlbFileParser::parse($content) as $teamLine => $slots) {
            foreach ($slots as $slot) {
                $index = $slot['slot_index'];
                unset($slot['slot_index']);
                $updates[$teamLine][$index] = $slot;
            }
        }

        self::assertCount(32, $updates);
        self::assertSame($content, PlbFileWriter::applySlots($content, $updates));
    }

    public function testRelocationMatchesJsbSim09ForTradedPlayers(): void
    {
        $sim08Content = $this->member(self::SIM08_ZIP, 'IBL5.plr');
        $sim08 = PlrFileWriter::splitIntoLines($sim08Content);
        $sim09 = PlrFileWriter::splitIntoLines($this->member(self::SIM09_ZIP, 'IBL5.plr'));

        $dbTeamByPid = [];
        foreach (PlrFileWriter::indexPlayerRecords($sim08) as $lineIndex => $pid) {
            $dbTeamByPid[$pid] = PlrFileWriter::readField($sim08[$lineIndex], 'teamid');
        }
        $dbTeamByPid = self::TRADE + $dbTeamByPid;

        $result = (new PlrTradeRelocator())->relocate($sim08, $dbTeamByPid);
        self::assertSame([], $result->warnings);
        self::assertCount(4, $result->moves);

        // JSB re-packs the whole block, so slot numbers differ (F5). Each block must hold sim09's players.
        foreach (self::BLOCKS as [$first, $last]) {
            self::assertSame(self::blockPids($sim09, $first, $last), self::blockPids($result->lines, $first, $last));
        }

        $ours = self::ordinalsByPid($result->lines);
        $jsb = self::ordinalsByPid($sim09);
        foreach (self::TRADE as $pid => $teamid) {
            [$first, $last] = self::BLOCKS[$teamid];
            self::assertGreaterThanOrEqual($first, $ours[$pid]);
            self::assertLessThanOrEqual($last, $ours[$pid]);
            foreach (['teamid', 'contractOwnedBy', 'currentTeamIndex', 'previousTeamIndex'] as $field) {
                self::assertSame(
                    PlrFileWriter::readField($sim09[$jsb[$pid]], $field),
                    PlrFileWriter::readField($result->lines[$ours[$pid]], $field),
                    'pid ' . $pid . ' ' . $field,
                );
            }
        }
        self::assertSame(575, $ours[6363]);

        // Every player outside the trade keeps his sim08 slot.
        $before = self::ordinalsByPid($sim08);
        foreach ($before as $pid => $ordinal) {
            if (!isset(self::TRADE[$pid])) {
                self::assertSame($ordinal, $ours[$pid], 'pid ' . $pid);
            }
        }

        self::assertSame(strlen($sim08Content), strlen(PlrFileWriter::assembleFile($result->lines)));
    }

    public function testWritesVmAcceptanceExportFiles(): void
    {
        $outDir = getenv('JSB_VM_EXPORT_DIR');
        if (!is_string($outDir) || $outDir === '' || !is_dir($outDir)) {
            // phpunit-hygiene-allow: integration-availability skip; writes files for the one-time JSB VM acceptance run only when its folder is given
            self::markTestSkipped('JSB_VM_EXPORT_DIR not set');
        }

        $this->writeAcceptanceExport($outDir);
    }

    public function testTradedPlayersGetNewTeamDepthInAcceptanceExport(): void
    {
        $outDir = sys_get_temp_dir() . '/jsb_vm_out_' . bin2hex(random_bytes(6));
        mkdir($outDir);
        try {
            $this->writeAcceptanceExport($outDir);
            $plrLines = PlrFileWriter::splitIntoLines((string) file_get_contents($outDir . '/IBL5_export.plr'));
            $plbSlots = PlbFileParser::parse((string) file_get_contents($outDir . '/IBL5_export.plb'));
        } finally {
            foreach (['IBL5_export.plr', 'IBL5_export.plb'] as $file) {
                if (is_file($outDir . '/' . $file)) {
                    unlink($outDir . '/' . $file);
                }
            }
            rmdir($outDir);
        }

        $ordinals = self::ordinalsByPid($plrLines);
        $expected = [4500 => [[0, 0, 0, 0, 1, 1], 40], 6363 => [[0, 0, 0, 0, 0, 0], 1]];
        foreach ($expected as $pid => [$depth, $minutes]) {
            $line = $plrLines[$ordinals[$pid]];
            self::assertSame(20, PlrFileWriter::readField($line, 'teamid'), 'pid ' . $pid . ' teamid');
            $actual = [];
            foreach (['PGDepth', 'SGDepth', 'SFDepth', 'PFDepth', 'CDepth', 'canPlayInGame'] as $field) {
                $actual[] = PlrFileWriter::readField($line, $field);
            }
            self::assertSame($depth, $actual, 'pid ' . $pid . ' PG,SG,SF,PF,C,canPlayInGame');

            $position = PlbFileWriter::ordinalToTeamSlot($ordinals[$pid]);
            self::assertNotNull($position);
            $slot = null;
            foreach ($plbSlots[$position[0]] ?? [] as $candidate) {
                if ($candidate['slot_index'] === $position[1]) {
                    $slot = $candidate;
                }
            }
            self::assertNotNull($slot, 'pid ' . $pid . ' .plb slot');
            self::assertSame($minutes, $slot['dc_minutes'], 'pid ' . $pid . ' .plb minutes');
        }
    }

    private function writeAcceptanceExport(string $outDir): void
    {
        $plr = $this->member(self::SIM08_ZIP, 'IBL5.plr');
        $plb = $this->member(self::SIM08_ZIP, 'IBL5.plb');
        $tmp = sys_get_temp_dir() . '/jsb_vm_' . bin2hex(random_bytes(6));
        mkdir($tmp);
        $plrIn = $tmp . '/IBL5.plr';
        $plbIn = $tmp . '/IBL5.plb';
        file_put_contents($plrIn, $plr);
        file_put_contents($plbIn, $plb);

        $lines = PlrFileWriter::splitIntoLines($plr);
        $slots = PlbFileParser::parse($plb);
        $changeable = [];
        $charts = [];
        foreach (PlrFileWriter::indexPlayerRecords($lines) as $lineIndex => $pid) {
            $line = $lines[$lineIndex];
            $row = ['pid' => $pid, 'name' => PlrFileWriter::readPlayerName($line)];
            foreach (self::DB_TO_PLR_FIELD as $dbField => $plrField) {
                $row[$dbField] = PlrFileWriter::readField($line, $plrField);
            }
            $row['teamid'] = self::TRADE[$pid] ?? $row['teamid'];
            $changeable[$pid] = $row;

            if ($row['teamid'] !== 20) {
                continue;
            }
            $ordinal = (int) trim(substr($line, 0, 4));
            $position = PlbFileWriter::ordinalToTeamSlot($ordinal);
            $slot = $position === null ? null : ($slots[$position[0]][$position[1]] ?? null);
            $charts[$pid] = [
                'pid' => $pid,
                'teamid' => 20,
                'dc_pg_depth' => PlrFileWriter::readField($line, 'PGDepth'),
                'dc_sg_depth' => PlrFileWriter::readField($line, 'SGDepth'),
                'dc_sf_depth' => PlrFileWriter::readField($line, 'SFDepth'),
                'dc_pf_depth' => PlrFileWriter::readField($line, 'PFDepth'),
                'dc_c_depth' => PlrFileWriter::readField($line, 'CDepth'),
                'dc_can_play_in_game' => PlrFileWriter::readField($line, 'canPlayInGame'),
                'dc_minutes' => $slot['dc_minutes'] ?? 0,
                'dc_of' => $slot['dc_of'] ?? 0,
                'dc_df' => $slot['dc_df'] ?? 0,
                'dc_oi' => $slot['dc_oi'] ?? 0,
                'dc_di' => $slot['dc_di'] ?? 0,
                'dc_bh' => $slot['dc_bh'] ?? 0,
            ];
        }
        $zeroChart = ['dc_pg_depth' => 0, 'dc_sg_depth' => 0, 'dc_sf_depth' => 0, 'dc_pf_depth' => 0, 'dc_c_depth' => 0, 'dc_of' => 0, 'dc_df' => 0, 'dc_oi' => 0, 'dc_di' => 0, 'dc_bh' => 0];
        $charts[4500] = ['pid' => 4500, 'teamid' => 20, 'dc_c_depth' => 1, 'dc_can_play_in_game' => 1, 'dc_minutes' => 40] + $zeroChart;
        $charts[6363] = ['pid' => 6363, 'teamid' => 20, 'dc_can_play_in_game' => 0, 'dc_minutes' => 1] + $zeroChart;

        $repo = self::createStub(PlrExportRepositoryInterface::class);
        $repo->method('getAllPlayerChangeableFields')->willReturn($changeable);
        $repo->method('getAllPlayerDepthCharts')->willReturn($charts);

        $plrOut = $outDir . '/IBL5_export.plr';
        $plbOut = $outDir . '/IBL5_export.plb';
        try {
            $plrResult = (new PlrExportService($repo))->exportPlrFile($plrIn, $plrOut);
            $plbResult = (new PlbExportService($repo))->exportPlbFile($plrIn, $plrOut, $plbIn, $plbOut);
        } finally {
            unlink($plrIn);
            unlink($plbIn);
            rmdir($tmp);
        }

        self::assertSame(0, $plrResult->errors);
        self::assertSame(0, $plbResult->errors);
        self::assertSame(strlen($plr), filesize($plrOut));
        self::assertSame(strlen($plb), filesize($plbOut));
    }
}
