<?php

declare(strict_types=1);

namespace Tests\Unit\BulkImport;

use BulkImport\ImportEntry;
use PHPUnit\Framework\TestCase;
use PlrParser\PlrOrdinalMap;

/**
 * @covers \BulkImport\ImportEntry
 */
class ImportEntryTest extends TestCase
{
    public function testConstructsWithRequiredFields(): void
    {
        $entry = new ImportEntry(
            path: '/tmp/season-dir',
            label: '06-07',
            year: 2007,
            phase: 'Regular Season/Playoffs',
            archivePath: '/tmp/archive.zip',
            sourceLabel: 'season-2007',
        );

        self::assertSame('/tmp/season-dir', $entry->path);
        self::assertSame('06-07', $entry->label);
        self::assertSame(2007, $entry->year);
        self::assertSame('Regular Season/Playoffs', $entry->phase);
        self::assertSame('/tmp/archive.zip', $entry->archivePath);
        self::assertSame('season-2007', $entry->sourceLabel);
        self::assertNull($entry->plrMap);
        self::assertNull($entry->simNumber);
    }

    public function testConstructsWithPlbFields(): void
    {
        $entry = new ImportEntry(
            path: '/tmp/season-dir',
            label: 'sim001',
            year: 2007,
            phase: 'Regular Season/Playoffs',
            archivePath: '/tmp/sim001.zip',
            sourceLabel: 'sim001',
            plrMap: PlrOrdinalMap::empty(),
            simNumber: 1,
        );

        self::assertSame(1, $entry->simNumber);
        self::assertNotNull($entry->plrMap);
    }

    public function testArchivePathCanBeNull(): void
    {
        $entry = new ImportEntry(
            path: '/tmp/pre-extracted',
            label: 'local-dir',
            year: 2007,
            phase: 'HEAT',
            archivePath: null,
            sourceLabel: 'local',
        );

        self::assertNull($entry->archivePath);
    }
}
