<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\PlrFieldSerializer;
use JsbParser\PlrFileWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\PlrFileWriter
 *
 * Guards the season-stat entries that were added to FIELD_MAP for PlrReconstructionService.
 * These guards exist so a rogue copy-paste can't silently reintroduce an offset collision
 * between contract fields (298-321) and the season-stat block (144-207).
 */
class PlrFileWriterExpandedFieldsTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function expectedSeasonStatFieldsProvider(): array
    {
        return [
            'seasonGamesStarted at 144' => ['seasonGamesStarted', 144],
            'seasonGamesPlayed at 148' => ['seasonGamesPlayed', 148],
            'seasonMIN at 152' => ['seasonMIN', 152],
            'season2GM at 156' => ['season2GM', 156],
            'season2GA at 160' => ['season2GA', 160],
            'seasonFTM at 164' => ['seasonFTM', 164],
            'seasonFTA at 168' => ['seasonFTA', 168],
            'season3GM at 172' => ['season3GM', 172],
            'season3GA at 176' => ['season3GA', 176],
            'seasonORB at 180' => ['seasonORB', 180],
            'seasonDRB at 184' => ['seasonDRB', 184],
            'seasonAST at 188' => ['seasonAST', 188],
            'seasonSTL at 192' => ['seasonSTL', 192],
            'seasonTVR at 196' => ['seasonTVR', 196],
            'seasonBLK at 200' => ['seasonBLK', 200],
            'seasonPF at 204' => ['seasonPF', 204],
        ];
    }

    #[DataProvider('expectedSeasonStatFieldsProvider')]
    public function testSeasonStatFieldsArePresentAtExpectedOffsets(string $fieldName, int $expectedOffset): void
    {
        $this->assertArrayHasKey($fieldName, PlrFileWriter::FIELD_MAP);
        [$offset, $width] = PlrFileWriter::FIELD_MAP[$fieldName];
        $this->assertSame($expectedOffset, $offset);
        $this->assertSame(4, $width, 'all season stats are 4-byte fields');
    }

    public function testNoFieldMapEntriesHaveOverlappingOffsetRanges(): void
    {
        $ranges = [];
        foreach (PlrFileWriter::FIELD_MAP as $field => [$offset, $width]) {
            $ranges[] = ['field' => $field, 'start' => $offset, 'end' => $offset + $width];
        }

        foreach ($ranges as $a) {
            foreach ($ranges as $b) {
                if ($a['field'] === $b['field']) {
                    continue;
                }
                $overlap = $a['start'] < $b['end'] && $b['start'] < $a['end'];
                $this->assertFalse(
                    $overlap,
                    sprintf('FIELD_MAP entries "%s" and "%s" overlap', $a['field'], $b['field']),
                );
            }
        }
    }

    public function testSeasonStatFieldsRoundTripThroughReadFieldAndApplyChanges(): void
    {
        $record = str_repeat(' ', PlrFileWriter::PLAYER_RECORD_LENGTH);
        $record = substr_replace($record, PlrFieldSerializer::formatInt(1, 4), 0, 4);
        $record = substr_replace($record, PlrFieldSerializer::formatInt(12345, 6), 38, 6);

        $updated = PlrFileWriter::applyChangesToRecord($record, [
            'seasonGamesPlayed' => 42,
            'seasonMIN' => 1337,
            'season2GM' => 99,
            'seasonPF' => 7,
        ]);

        $this->assertSame(42, PlrFileWriter::readField($updated, 'seasonGamesPlayed'));
        $this->assertSame(1337, PlrFileWriter::readField($updated, 'seasonMIN'));
        $this->assertSame(99, PlrFileWriter::readField($updated, 'season2GM'));
        $this->assertSame(7, PlrFileWriter::readField($updated, 'seasonPF'));
        $this->assertSame(
            PlrFileWriter::PLAYER_RECORD_LENGTH,
            strlen($updated),
            'record length must be preserved after season-stat writes',
        );
    }

    public function testSeasonStatOffsetsDoNotClobberContractFields(): void
    {
        $record = str_repeat(' ', PlrFileWriter::PLAYER_RECORD_LENGTH);
        $record = substr_replace($record, PlrFieldSerializer::formatInt(1, 4), 0, 4);
        $record = substr_replace($record, PlrFieldSerializer::formatInt(12345, 6), 38, 6);
        $record = substr_replace($record, PlrFieldSerializer::formatInt(8500, 4), 298, 4); // cy1

        $updated = PlrFileWriter::applyChangesToRecord($record, [
            'seasonGamesPlayed' => 82,
            'seasonMIN' => 2900,
        ]);

        $this->assertSame(8500, PlrFileWriter::readField($updated, 'cy1'));
    }
}
