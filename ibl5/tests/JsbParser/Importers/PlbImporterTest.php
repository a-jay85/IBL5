<?php

declare(strict_types=1);

namespace Tests\JsbParser\Importers;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\Importers\PlbImporter;
use PHPUnit\Framework\TestCase;
use PlrParser\PlrOrdinalMap;

/**
 * @covers \JsbParser\Importers\PlbImporter
 */
final class PlbImporterTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $captured = [];

    /**
     * Build a single PLB line (360 chars) with specified slot values.
     *
     * @param array<int, array{minutes: int, of: int, df: int, oi: int, di: int, bh: int}> $slots
     *        Key = slot index, value = field values
     */
    private function buildPlbLine(array $slots = []): string
    {
        $line = str_repeat('00', 180); // 30 slots × 6 fields × 2 chars = 360 zeros

        foreach ($slots as $slotIndex => $values) {
            $offset = $slotIndex * 12;
            $slotStr = sprintf(
                '%02d%02d%02d%02d%02d%02d',
                $values['minutes'],
                $values['of'],
                $values['df'],
                $values['oi'],
                $values['di'],
                $values['bh']
            );
            $line = substr_replace($line, $slotStr, $offset, 12);
        }

        return $line;
    }

    /**
     * Build a full PLB file with 32 lines.
     *
     * @param array<int, string> $lineOverrides Key = line index, value = line content
     */
    private function buildPlbFile(array $lineOverrides = []): string
    {
        $lines = [];
        $defaultLine = $this->buildPlbLine();

        for ($i = 0; $i < 32; $i++) {
            $lines[] = $lineOverrides[$i] ?? $defaultLine;
        }

        return implode("\n", $lines);
    }

    /**
     * Build a populated ordinal map through the public .plr loader.
     *
     * @param array<int, array{pid: int, name: string}> $players Key = ordinal ((teamid - 1) * 30 + slotIndex + 1)
     */
    private function mapWith(array $players): PlrOrdinalMap
    {
        $plrFile = tempnam(sys_get_temp_dir(), 'plb_importer_plr_');
        $lines = '';
        foreach ($players as $ordinal => $player) {
            $lines .= str_pad((string) $ordinal, 4, ' ', STR_PAD_LEFT)
                . str_pad($player['name'], 34)
                . str_pad((string) $player['pid'], 6, ' ', STR_PAD_LEFT)
                . "\n";
        }
        file_put_contents($plrFile, $lines);

        try {
            return PlrOrdinalMap::fromPlrFile($plrFile);
        } finally {
            unlink($plrFile);
        }
    }

    private function buildFixture(): string
    {
        return $this->buildPlbFile([
            0 => $this->buildPlbLine([
                0 => ['minutes' => 34, 'of' => 2, 'df' => 3, 'oi' => 1, 'di' => 2, 'bh' => 4],
                1 => ['minutes' => 12, 'of' => 1, 'df' => 1, 'oi' => 0, 'di' => 0, 'bh' => 0],
            ]),
            28 => $this->buildPlbLine([
                0 => ['minutes' => 40, 'of' => 1, 'df' => 1, 'oi' => 1, 'di' => 1, 'bh' => 1],
            ]),
        ]);
    }

    private function repositoryCapturing(string $method): JsbImportRepositoryInterface&\PHPUnit\Framework\MockObject\Stub
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method($method)->willReturnCallback(function (array $record): int {
            $this->captured[] = $record;
            return 1;
        });
        return $repo;
    }

    private function importFixture(PlrOrdinalMap $map): \JsbParser\JsbImportResult
    {
        return (new PlbImporter($this->repositoryCapturing('upsertPlbSnapshot')))
            ->import($this->buildFixture(), $map, 2025, 7, 'IBL5_2025_sim07.zip');
    }

    public function testImportSendsExactSnapshotRecordForMappedSlot(): void
    {
        $this->importFixture($this->mapWith([1 => ['pid' => 9001, 'name' => 'Slot Zero']]));

        $this->assertSame([
            'season_year' => 2025,
            'sim_number' => 7,
            'source_archive' => 'IBL5_2025_sim07.zip',
            'teamid' => 1,
            'slot_index' => 0,
            'pid' => 9001,
            'player_name' => 'Slot Zero',
            'dc_minutes' => 34,
            'dc_of' => 2,
            'dc_df' => 3,
            'dc_oi' => 1,
            'dc_di' => 2,
            'dc_bh' => 4,
        ], $this->captured[0]);
    }

    public function testImportSetsNullPidAndNameForUnmappedSlot(): void
    {
        $this->importFixture($this->mapWith([1 => ['pid' => 9001, 'name' => 'Slot Zero']]));

        $this->assertSame([
            'season_year' => 2025,
            'sim_number' => 7,
            'source_archive' => 'IBL5_2025_sim07.zip',
            'teamid' => 1,
            'slot_index' => 1,
            'pid' => null,
            'player_name' => null,
            'dc_minutes' => 12,
            'dc_of' => 1,
            'dc_df' => 1,
            'dc_oi' => 0,
            'dc_di' => 0,
            'dc_bh' => 0,
        ], $this->captured[1]);
    }

    public function testImportSendsNullPidForEveryRecordWithEmptyMap(): void
    {
        $this->importFixture(PlrOrdinalMap::empty());

        $this->assertCount(2, $this->captured);
        $this->assertNull($this->captured[0]['pid']);
        $this->assertNull($this->captured[0]['player_name']);
        $this->assertSame(34, $this->captured[0]['dc_minutes']);
    }

    public function testImportSkipsTeamsAbove28(): void
    {
        $this->importFixture($this->mapWith([1 => ['pid' => 9001, 'name' => 'Slot Zero']]));

        $this->assertCount(2, $this->captured);
        foreach ($this->captured as $record) {
            $this->assertLessThanOrEqual(28, $record['teamid']);
        }
    }

    public function testImportCountsZeroMinuteSlotsAsSkipped(): void
    {
        $result = $this->importFixture($this->mapWith([1 => ['pid' => 9001, 'name' => 'Slot Zero']]));

        $this->assertSame(28 * 30 - 2, $result->skipped);
        $this->assertSame(2, $result->inserted);
    }

    public function testImportConvertsRepositoryExceptionToError(): void
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('upsertPlbSnapshot')->willThrowException(new \RuntimeException('db down'));

        $result = (new PlbImporter($repo))->import($this->buildFixture(), PlrOrdinalMap::empty(), 2025, 7, 'a.zip');

        $this->assertSame(2, $result->errors);
        $this->assertSame(0, $result->inserted);
        $this->assertStringContainsString('PLB upsert failed for teamid=1 slot=0: db down', implode("\n", $result->messages));
    }

    public function testImportFileReportsMissingFile(): void
    {
        $result = (new PlbImporter(self::createStub(JsbImportRepositoryInterface::class)))
            ->importFile('/nonexistent/x.plb', PlrOrdinalMap::empty(), 2025, 7, 'a.zip');

        $this->assertSame(1, $result->errors);
        $this->assertStringContainsString('PLB file not found: /nonexistent/x.plb', implode("\n", $result->messages));
    }
}
