<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\PlrFieldSerializer;
use JsbParser\PlrFileWriter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\PlrFileWriter
 */
class PlrFileWriterTest extends TestCase
{
    /**
     * Build a synthetic 607-byte player record with known field values.
     *
     * @param int $ordinal Player ordinal (offset 0, width 4)
     * @param int $pid Player ID (offset 38, width 6)
     * @param int $tid Team ID (offset 44, width 2)
     * @param int $bird Bird rights (offset 288, width 2)
     */
    private function buildSyntheticRecord(
        int $ordinal = 1,
        int $pid = 12345,
        int $tid = 5,
        int $bird = 3,
    ): string {
        $record = str_repeat(' ', PlrFileWriter::PLAYER_RECORD_LENGTH);

        // Set identification fields
        $record = substr_replace($record, PlrFieldSerializer::formatInt($ordinal, 4), 0, 4);
        $record = substr_replace($record, PlrFieldSerializer::formatInt($pid, 6), 38, 6);
        $record = substr_replace($record, PlrFieldSerializer::formatInt($tid, 2), 44, 2);

        // Set bird rights
        $record = substr_replace($record, PlrFieldSerializer::formatInt($bird, 2), 288, 2);

        // Set derived fields to consistent values
        $record = substr_replace($record, PlrFieldSerializer::formatInt(0, 1), 330, 1); // freeAgentSigningFlag
        $record = substr_replace($record, PlrFieldSerializer::formatInt($tid, 2), 331, 2); // contractOwnedBy
        $currentIndex = $tid === 0 ? -1 : $tid - 1;
        $record = substr_replace($record, PlrFieldSerializer::formatInt($currentIndex, 2), 333, 2);
        $record = substr_replace($record, PlrFieldSerializer::formatInt($currentIndex, 2), 335, 2);

        return $record;
    }

    /**
     * Build a minimal multi-line .plr file content with CRLF.
     *
     * @param list<string> $records 607-byte records
     */
    private function buildPlrContent(array $records): string
    {
        return implode("\r\n", $records) . "\r\n";
    }

    public function testSplitAndAssembleAreInverses(): void
    {
        $record1 = $this->buildSyntheticRecord(1, 100, 5);
        $record2 = $this->buildSyntheticRecord(2, 200, 10);
        $content = $this->buildPlrContent([$record1, $record2]);

        $lines = PlrFileWriter::splitIntoLines($content);
        $reassembled = PlrFileWriter::assembleFile($lines);

        $this->assertSame($content, $reassembled, 'splitIntoLines + assembleFile must be identity');
    }

    public function testIndexPlayerRecordsFindsValidPlayers(): void
    {
        $record1 = $this->buildSyntheticRecord(1, 12345, 5);
        $record2 = $this->buildSyntheticRecord(2, 67890, 10);
        $content = $this->buildPlrContent([$record1, $record2]);
        $lines = PlrFileWriter::splitIntoLines($content);

        $index = PlrFileWriter::indexPlayerRecords($lines);

        $this->assertSame(12345, $index[0]);
        $this->assertSame(67890, $index[1]);
    }

    public function testIndexPlayerRecordsSkipsPidZero(): void
    {
        $record = $this->buildSyntheticRecord(1, 0, 5);
        $content = $this->buildPlrContent([$record]);
        $lines = PlrFileWriter::splitIntoLines($content);

        $index = PlrFileWriter::indexPlayerRecords($lines);

        $this->assertEmpty($index);
    }

    public function testIndexPlayerRecordsSkipsHighOrdinals(): void
    {
        $record = $this->buildSyntheticRecord(1441, 12345, 5);
        $content = $this->buildPlrContent([$record]);
        $lines = PlrFileWriter::splitIntoLines($content);

        $index = PlrFileWriter::indexPlayerRecords($lines);

        $this->assertEmpty($index);
    }

    public function testApplyChangesPreservesLength(): void
    {
        $record = $this->buildSyntheticRecord(1, 12345, 5);
        $originalLength = strlen($record);

        $modified = PlrFileWriter::applyChangesToRecord($record, ['tid' => 10]);

        $this->assertSame($originalLength, strlen($modified));
    }

    public function testApplyChangesUpdatesTid(): void
    {
        $record = $this->buildSyntheticRecord(1, 12345, 5);

        $modified = PlrFileWriter::applyChangesToRecord($record, ['tid' => 10]);

        $this->assertSame(10, PlrFileWriter::readField($modified, 'tid'));
    }

    public function testApplyChangesUpdatesDepthChart(): void
    {
        $record = $this->buildSyntheticRecord();

        $modified = PlrFileWriter::applyChangesToRecord($record, [
            'PGDepth' => 1,
            'SGDepth' => 2,
            'SFDepth' => 0,
            'PFDepth' => 0,
            'CDepth' => 0,
            'active' => 1,
        ]);

        $this->assertSame(1, PlrFileWriter::readField($modified, 'PGDepth'));
        $this->assertSame(2, PlrFileWriter::readField($modified, 'SGDepth'));
        $this->assertSame(0, PlrFileWriter::readField($modified, 'SFDepth'));
        $this->assertSame(1, PlrFileWriter::readField($modified, 'active'));
    }

    public function testApplyChangesUpdatesContract(): void
    {
        $record = $this->buildSyntheticRecord();

        $modified = PlrFileWriter::applyChangesToRecord($record, [
            'cy' => 1,
            'cyt' => 4,
            'cy1' => 1500,
            'cy2' => 1600,
            'cy3' => 1700,
            'cy4' => 1800,
            'cy5' => 0,
            'cy6' => 0,
        ]);

        $this->assertSame(1, PlrFileWriter::readField($modified, 'cy'));
        $this->assertSame(4, PlrFileWriter::readField($modified, 'cyt'));
        $this->assertSame(1500, PlrFileWriter::readField($modified, 'cy1'));
        $this->assertSame(1800, PlrFileWriter::readField($modified, 'cy4'));
    }

    public function testDerivedFieldsWhenTidChanges(): void
    {
        $record = $this->buildSyntheticRecord(1, 12345, 5);

        $modified = PlrFileWriter::applyChangesToRecord($record, ['tid' => 10]);

        // contractOwnedBy should match new tid
        $this->assertSame(10, PlrFileWriter::readField($modified, 'contractOwnedBy'));
        // currentTeamIndex = tid - 1 = 9
        $this->assertSame(9, PlrFileWriter::readField($modified, 'currentTeamIndex'));
        // previousTeamIndex = old tid - 1 = 4
        $this->assertSame(4, PlrFileWriter::readField($modified, 'previousTeamIndex'));
    }

    public function testDerivedFieldsWhenTidChangesToFreeAgent(): void
    {
        $record = $this->buildSyntheticRecord(1, 12345, 5);

        $modified = PlrFileWriter::applyChangesToRecord($record, ['tid' => 0]);

        $this->assertSame(0, PlrFileWriter::readField($modified, 'contractOwnedBy'));
        // Free agent: currentTeamIndex = -1
        $this->assertSame(-1, PlrFileWriter::readField($modified, 'currentTeamIndex'));
        // previousTeamIndex = old tid - 1 = 4
        $this->assertSame(4, PlrFileWriter::readField($modified, 'previousTeamIndex'));
    }

    public function testDerivedFreeAgentSigningFlagWhenBirdIsOne(): void
    {
        $record = $this->buildSyntheticRecord(1, 12345, 5, 3);

        $modified = PlrFileWriter::applyChangesToRecord($record, ['bird' => 1]);

        $this->assertSame(1, PlrFileWriter::readField($modified, 'freeAgentSigningFlag'));
    }

    public function testDerivedFreeAgentSigningFlagWhenBirdIsNotOne(): void
    {
        $record = $this->buildSyntheticRecord(1, 12345, 5, 1);

        $modified = PlrFileWriter::applyChangesToRecord($record, ['bird' => 3]);

        $this->assertSame(0, PlrFileWriter::readField($modified, 'freeAgentSigningFlag'));
    }

    public function testApplyChangesThrowsForUnknownField(): void
    {
        $record = $this->buildSyntheticRecord();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown field');

        PlrFileWriter::applyChangesToRecord($record, ['nonexistent' => 1]);
    }

    public function testReadFieldReadsCorrectValues(): void
    {
        $record = $this->buildSyntheticRecord(1, 12345, 5);

        $this->assertSame(5, PlrFileWriter::readField($record, 'tid'));
    }

    public function testReadPlayerName(): void
    {
        $record = $this->buildSyntheticRecord();
        // Write a name at offset 4, width 32
        $name = str_pad('John Smith', 32);
        $record = substr_replace($record, $name, 4, 32);

        $this->assertSame('John Smith', PlrFileWriter::readPlayerName($record));
    }

    public function testApplyChangesUpdatesExpAndBird(): void
    {
        $record = $this->buildSyntheticRecord();

        $modified = PlrFileWriter::applyChangesToRecord($record, [
            'exp' => 10,
            'bird' => 4,
        ]);

        $this->assertSame(10, PlrFileWriter::readField($modified, 'exp'));
        $this->assertSame(4, PlrFileWriter::readField($modified, 'bird'));
        // bird != 1, so freeAgentSigningFlag should be 0
        $this->assertSame(0, PlrFileWriter::readField($modified, 'freeAgentSigningFlag'));
    }

    public function testApplyChangesUpdatesInjuryDaysLeft(): void
    {
        $record = $this->buildSyntheticRecord();

        $modified = PlrFileWriter::applyChangesToRecord($record, ['injuryDaysLeft' => 15]);

        $this->assertSame(15, PlrFileWriter::readField($modified, 'injuryDaysLeft'));
    }

    public function testReadFileThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PLR file not found');

        PlrFileWriter::readFile('/nonexistent/file.plr');
    }

    public function testWriteFileCreatesFile(): void
    {
        $tmpDir = sys_get_temp_dir();
        $outputPath = $tmpDir . '/plr_write_test_' . uniqid() . '.plr';

        try {
            PlrFileWriter::writeFile('test content', $outputPath);
            $this->assertFileExists($outputPath);
            $this->assertSame('test content', file_get_contents($outputPath));
        } finally {
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }
        }
    }

    public function testNoChangesProducesIdenticalRecord(): void
    {
        $record = $this->buildSyntheticRecord(1, 12345, 5, 3);

        $modified = PlrFileWriter::applyChangesToRecord($record, []);

        $this->assertSame($record, $modified);
    }

    public function testMultipleFieldChangesInOneCall(): void
    {
        $record = $this->buildSyntheticRecord(1, 12345, 5, 3);

        $modified = PlrFileWriter::applyChangesToRecord($record, [
            'tid' => 10,
            'PGDepth' => 1,
            'active' => 1,
            'cy' => 2,
            'cyt' => 5,
            'bird' => 4,
        ]);

        $this->assertSame(10, PlrFileWriter::readField($modified, 'tid'));
        $this->assertSame(1, PlrFileWriter::readField($modified, 'PGDepth'));
        $this->assertSame(1, PlrFileWriter::readField($modified, 'active'));
        $this->assertSame(2, PlrFileWriter::readField($modified, 'cy'));
        $this->assertSame(5, PlrFileWriter::readField($modified, 'cyt'));
        $this->assertSame(4, PlrFileWriter::readField($modified, 'bird'));
        $this->assertSame(PlrFileWriter::PLAYER_RECORD_LENGTH, strlen($modified));
    }
}
