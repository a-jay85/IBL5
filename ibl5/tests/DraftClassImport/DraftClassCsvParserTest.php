<?php

declare(strict_types=1);

namespace Tests\DraftClassImport;

use DraftClassImport\DraftClassCsvParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DraftClassImport\DraftClassCsvParser
 */
class DraftClassCsvParserTest extends TestCase
{
    private DraftClassCsvParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DraftClassCsvParser();
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /**
     * Build a single semicolon-delimited CSV row (27 fields + 5 empty trailing).
     *
     * @param array<string, string> $overrides Column name → raw string value.
     */
    private function buildSemiRow(array $overrides = []): string
    {
        $defaults = [
            'name'         => 'Test Player',
            'pos'          => 'PG',
            'age'          => '25',
            'team'         => '',
            'fga'          => '50',
            'fgp'          => '50',
            'fta'          => '50',
            'ftp'          => '50',
            'r_3ga'        => '50',
            'r_3gp'        => '50',
            'orb'          => '20',
            'drb'          => '40',
            'ast'          => '30',
            'stl'          => '20',
            'tvr'          => '60',
            'blk'          => '10',
            'oo'           => '5',
            'r_drive_off'  => '5',
            'po'           => '5',
            'r_trans_off'  => '5',
            'od'           => '5',
            'dd'           => '5',
            'pd'           => '5',
            'td'           => '5',
            'talent'       => '5',
            'skill'        => '5',
            'intangibles'  => '5',
        ];

        $columns = [
            'name', 'pos', 'age', 'team',
            'fga', 'fgp', 'fta', 'ftp', 'r_3ga', 'r_3gp',
            'orb', 'drb', 'ast', 'stl', 'tvr', 'blk', 'oo',
            'r_drive_off', 'po', 'r_trans_off',
            'od', 'dd', 'pd', 'td',
            'talent', 'skill', 'intangibles',
        ];

        $merged = array_merge($defaults, $overrides);
        $fields = array_map(
            static fn(string $col): string => '"' . $merged[$col] . '"',
            $columns
        );

        // Append 5 empty trailing fields (fields 28–32, matching the fixture).
        $fields[] = '""';
        $fields[] = '""';
        $fields[] = '""';
        $fields[] = '""';
        $fields[] = '""';

        return implode(';', $fields);
    }

    /**
     * Build a single comma-delimited CSV row (27 fields).
     *
     * @param array<string, string> $overrides
     */
    private function buildCommaRow(array $overrides = []): string
    {
        $defaults = [
            'name'         => 'Test Player',
            'pos'          => 'PG',
            'age'          => '25',
            'team'         => '',
            'fga'          => '50',
            'fgp'          => '50',
            'fta'          => '50',
            'ftp'          => '50',
            'r_3ga'        => '50',
            'r_3gp'        => '50',
            'orb'          => '20',
            'drb'          => '40',
            'ast'          => '30',
            'stl'          => '20',
            'tvr'          => '60',
            'blk'          => '10',
            'oo'           => '5',
            'r_drive_off'  => '5',
            'po'           => '5',
            'r_trans_off'  => '5',
            'od'           => '5',
            'dd'           => '5',
            'pd'           => '5',
            'td'           => '5',
            'talent'       => '5',
            'skill'        => '5',
            'intangibles'  => '5',
        ];

        $columns = [
            'name', 'pos', 'age', 'team',
            'fga', 'fgp', 'fta', 'ftp', 'r_3ga', 'r_3gp',
            'orb', 'drb', 'ast', 'stl', 'tvr', 'blk', 'oo',
            'r_drive_off', 'po', 'r_trans_off',
            'od', 'dd', 'pd', 'td',
            'talent', 'skill', 'intangibles',
        ];

        $merged = array_merge($defaults, $overrides);
        $fields = array_map(
            static fn(string $col): string => '"' . $merged[$col] . '"',
            $columns
        );

        return implode(',', $fields);
    }

    // -------------------------------------------------------------------------
    // Matrix row 1
    // -------------------------------------------------------------------------

    public function testParsesSampleFileWithSixtySevenRowsAndNoErrors(): void
    {
        $raw    = file_get_contents(__DIR__ . '/fixtures/98rookies.csv');
        $this->assertNotFalse($raw, 'Fixture file could not be read');

        $result = $this->parser->parse($raw);

        $this->assertSame([], $result['errors'], 'Expected zero parse errors');
        $this->assertCount(67, $result['rows']);

        // Accented names must round-trip byte-identical.
        $names = array_column($result['rows'], 'name');
        $this->assertContains('Juan Antonio Corbalán', $names);
        $this->assertContains('Greivis Vásquez', $names);
        $this->assertContains('Kevin Séraphin', $names);
    }

    // -------------------------------------------------------------------------
    // Matrix row 2: 26-field row rejected naming column count
    // -------------------------------------------------------------------------

    public function testRowWithTwentySixFieldsIsRejectedNamingTheColumnCount(): void
    {
        // Build a row with only 26 semicolon-separated fields.
        $fields = array_fill(0, 26, '"x"');
        $raw    = implode(';', $fields);

        $result = $this->parser->parse($raw);

        // Expect at least the field-count error (a "no data rows" error also fires since
        // the short row is skipped, but the field-count message is the key assertion).
        $fieldCountErrors = array_filter(
            $result['errors'],
            static fn(string $e): bool => str_contains($e, '26') && str_contains($e, '27')
        );
        $this->assertNotEmpty($fieldCountErrors, 'Expected field-count error naming 26 columns and 27 minimum');
    }

    // -------------------------------------------------------------------------
    // Matrix row 3: exactly 27 fields accepted
    // -------------------------------------------------------------------------

    public function testRowWithExactlyTwentySevenFieldsIsAccepted(): void
    {
        // Build a row with exactly 27 properly-valued fields (no trailing empties).
        $columns = [
            'name', 'pos', 'age', 'team',
            'fga', 'fgp', 'fta', 'ftp', 'r_3ga', 'r_3gp',
            'orb', 'drb', 'ast', 'stl', 'tvr', 'blk', 'oo',
            'r_drive_off', 'po', 'r_trans_off',
            'od', 'dd', 'pd', 'td',
            'talent', 'skill', 'intangibles',
        ];
        $values = array_fill(0, 27, '5');
        $values[0] = 'Test Player'; // name
        $values[1] = 'PG';         // pos
        $values[2] = '25';         // age
        $values[3] = '';           // team

        $fields = array_map(
            static fn(string $v): string => '"' . $v . '"',
            $values
        );
        $raw = implode(';', $fields);

        $result = $this->parser->parse($raw);

        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['rows']);
    }

    // -------------------------------------------------------------------------
    // Matrix row 4: 32 fields with blank trailing columns accepted
    // -------------------------------------------------------------------------

    public function testRowWithThirtyTwoFieldsAndBlankTrailingColumnsIsAccepted(): void
    {
        $raw = $this->buildSemiRow(); // includes 5 blank trailing fields (32 total)

        $result = $this->parser->parse($raw);

        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['rows']);
    }

    // -------------------------------------------------------------------------
    // Matrix row 5: field 30 = '1' rejected, error names field 30 and value '1'
    // -------------------------------------------------------------------------

    public function testNonBlankFieldThirtyIsRejectedNamingFieldAndValue(): void
    {
        // Build 32 fields; field 30 (1-based) = '1', rest blank.
        $fields = array_fill(0, 32, '""');
        // First 27 must be valid.
        $validRow = $this->buildSemiRow();
        $validFields = explode(';', $validRow);
        for ($i = 0; $i < 27; $i++) {
            $fields[$i] = $validFields[$i];
        }
        $fields[29] = '"1"'; // field 30 (0-indexed: 29)
        $raw = implode(';', $fields);

        $result = $this->parser->parse($raw);

        $hasFieldError = false;
        foreach ($result['errors'] as $error) {
            if (str_contains($error, 'field 30') && str_contains($error, "'1'")) {
                $hasFieldError = true;
                break;
            }
        }
        $this->assertTrue($hasFieldError, 'Expected error naming field 30 and value 1');
    }

    // -------------------------------------------------------------------------
    // Matrix row 6: invalid pos rejected naming the 1-based line number
    // -------------------------------------------------------------------------

    public function testInvalidPositionIsRejectedNamingLineNumber(): void
    {
        $raw = $this->buildSemiRow(['pos' => 'QB']);

        $result = $this->parser->parse($raw);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Line 1', $result['errors'][0]);
        $this->assertStringContainsString('QB', $result['errors'][0]);
    }

    // -------------------------------------------------------------------------
    // Matrix row 7: blank pos rejected
    // -------------------------------------------------------------------------

    public function testBlankPositionIsRejected(): void
    {
        $raw = $this->buildSemiRow(['pos' => '']);

        $result = $this->parser->parse($raw);

        $this->assertNotEmpty($result['errors']);
        $posErrors = array_filter(
            $result['errors'],
            static fn(string $e): bool => str_contains($e, 'position is blank')
        );
        $this->assertNotEmpty($posErrors, 'Expected "position is blank" error');
    }

    // -------------------------------------------------------------------------
    // Matrix row 8: 33-character ASCII name rejected
    // -------------------------------------------------------------------------

    public function testNameLongerThanThirtyTwoCharactersIsRejected(): void
    {
        $name = str_repeat('A', 33); // 33 ASCII characters
        $raw  = $this->buildSemiRow(['name' => $name]);

        $result = $this->parser->parse($raw);

        $this->assertNotEmpty($result['errors']);
        $nameErrors = array_filter(
            $result['errors'],
            static fn(string $e): bool => str_contains($e, 'longer than 32 characters')
        );
        $this->assertNotEmpty($nameErrors, 'Expected name-too-long error');
    }

    // -------------------------------------------------------------------------
    // Matrix row 9: 32-character accented name NOT rejected (mb_strlen regression guard)
    // -------------------------------------------------------------------------

    public function testAccentedThirtyTwoCharacterNameIsAccepted(): void
    {
        // 'é' is a 2-byte UTF-8 character. mb_strlen counts 1; strlen counts 2.
        // Build a name that is exactly 32 mb characters but > 32 bytes.
        // 30 ASCII + 'éé' = 32 mb_strlen, 34 strlen.
        $name = str_repeat('A', 30) . 'éé';
        $this->assertSame(32, mb_strlen($name));
        $this->assertGreaterThan(32, strlen($name));

        $raw = $this->buildSemiRow(['name' => $name]);

        $result = $this->parser->parse($raw);

        $nameErrors = array_filter(
            $result['errors'],
            static fn(string $e): bool => str_contains($e, 'longer than 32 characters')
        );
        $this->assertEmpty($nameErrors, 'Accented 32-mb_strlen name should NOT trigger length error');
        $this->assertSame([], $result['errors']);
    }

    // -------------------------------------------------------------------------
    // Matrix row 10: non-numeric rating rejected naming line number
    // -------------------------------------------------------------------------

    public function testNonNumericRatingIsRejectedNamingLineNumber(): void
    {
        $raw = $this->buildSemiRow(['fga' => 'abc']);

        $result = $this->parser->parse($raw);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Line 1', $result['errors'][0]);
        // The label the league reads ('2ga'), not the DB column name ('fga') — the
        // commissioner matches the message against their export, not the schema.
        $this->assertStringContainsString('2ga', $result['errors'][0]);
        $this->assertStringNotContainsString('fga', $result['errors'][0]);
        $this->assertStringContainsString("'abc'", $result['errors'][0]);
    }

    public function testSqlSafeColumnNamesNeverReachAnErrorMessage(): void
    {
        // r_3ga / r_drive_off / r_trans_off exist only to dodge reserved words; a
        // reader would not find them anywhere in their export.
        $raw = $this->buildSemiRow([
            'r_3ga' => 'x',
            'r_drive_off' => 'y',
            'r_trans_off' => 'z',
        ]);

        $result = $this->parser->parse($raw);

        $this->assertNotEmpty($result['errors']);
        $joined = implode("\n", $result['errors']);
        $this->assertStringNotContainsString('r_', $joined);
        $this->assertStringContainsString('3ga', $joined);
        $this->assertStringContainsString('column do', $joined);
        $this->assertStringContainsString('column to', $joined);
    }

    // -------------------------------------------------------------------------
    // Matrix row 11: rating of 256 rejected as out of range
    // -------------------------------------------------------------------------

    public function testRatingAboveTwoHundredFiftyFiveIsRejected(): void
    {
        $raw = $this->buildSemiRow(['fga' => '256']);

        $result = $this->parser->parse($raw);

        $this->assertNotEmpty($result['errors']);
        $rangeErrors = array_filter(
            $result['errors'],
            static fn(string $e): bool => str_contains($e, 'outside the allowed range')
                && str_contains($e, '256')
        );
        $this->assertNotEmpty($rangeErrors, 'Expected out-of-range error for 256');
    }

    // -------------------------------------------------------------------------
    // Matrix row 12: negative rating rejected
    // -------------------------------------------------------------------------

    public function testNegativeRatingIsRejected(): void
    {
        $raw = $this->buildSemiRow(['fga' => '-1']);

        $result = $this->parser->parse($raw);

        $this->assertNotEmpty($result['errors']);
        $rangeErrors = array_filter(
            $result['errors'],
            static fn(string $e): bool => str_contains($e, 'outside the allowed range')
                && str_contains($e, '-1')
        );
        $this->assertNotEmpty($rangeErrors, 'Expected out-of-range error for -1');
    }

    // -------------------------------------------------------------------------
    // Matrix row 13: blank name rejected
    // -------------------------------------------------------------------------

    public function testBlankNameIsRejected(): void
    {
        $raw = $this->buildSemiRow(['name' => '']);

        $result = $this->parser->parse($raw);

        $this->assertNotEmpty($result['errors']);
        $nameErrors = array_filter(
            $result['errors'],
            static fn(string $e): bool => str_contains($e, 'player name is blank')
        );
        $this->assertNotEmpty($nameErrors, 'Expected blank-name error');
    }

    // -------------------------------------------------------------------------
    // Matrix row 14: two rows sharing a name rejected with one error naming both line numbers
    // -------------------------------------------------------------------------

    public function testDuplicateNamesAreRejectedNamingBothLineNumbers(): void
    {
        $row1 = $this->buildSemiRow(['name' => 'Same Name']);
        $row2 = $this->buildSemiRow(['name' => 'Same Name']);
        $raw  = $row1 . "\n" . $row2;

        $result = $this->parser->parse($raw);

        $dupErrors = array_filter(
            $result['errors'],
            static fn(string $e): bool => str_contains($e, 'Same Name')
                && str_contains($e, '1')
                && str_contains($e, '2')
        );
        $this->assertNotEmpty($dupErrors, 'Expected duplicate-name error naming lines 1 and 2');
    }

    // -------------------------------------------------------------------------
    // Matrix row 15: Latin-1 bytes rejected with re-export message
    // -------------------------------------------------------------------------

    public function testLatinOneEncodedInputIsRejectedWithReExportMessage(): void
    {
        // Build a Latin-1 string: chr(0xE9) is 'é' in ISO-8859-1, and is an invalid lone
        // continuation byte in UTF-8, so mb_check_encoding should reject it.
        $latin1 = 'Ren' . chr(0xE9) . ' Player;PG;25;;50;50;50;50;50;50;20;40;30;20;60;10;5;5;5;5;5;5;5;5;5;5;5';

        $result = $this->parser->parse($latin1);

        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('UTF-8', $result['errors'][0]);
        $this->assertStringContainsString('Re-export', $result['errors'][0]);
    }

    // -------------------------------------------------------------------------
    // Matrix row 16: BOM-prefixed input parses clean (BOM does not corrupt first name)
    // -------------------------------------------------------------------------

    public function testByteOrderMarkPrefixedInputIsAccepted(): void
    {
        $raw = "\xEF\xBB\xBF" . $this->buildSemiRow(['name' => 'BOM Player']);

        $result = $this->parser->parse($raw);

        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('BOM Player', $result['rows'][0]['name']);
    }

    // -------------------------------------------------------------------------
    // Matrix row 17: comma-delimited input parses clean (delimiter auto-detect)
    // -------------------------------------------------------------------------

    public function testCommaDelimitedInputIsAccepted(): void
    {
        $raw = $this->buildCommaRow(['name' => 'Comma Player']);

        $result = $this->parser->parse($raw);

        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('Comma Player', $result['rows'][0]['name']);
    }

    // -------------------------------------------------------------------------
    // Matrix row 18: zero data rows rejected
    // -------------------------------------------------------------------------

    public function testInputWithZeroDataRowsIsRejected(): void
    {
        // A file that is entirely blank lines has zero data rows.
        $raw = "\n\n\n";

        $result = $this->parser->parse($raw);

        $this->assertNotEmpty($result['errors']);
        $zeroErrors = array_filter(
            $result['errors'],
            static fn(string $e): bool => str_contains($e, 'no data rows')
        );
        $this->assertNotEmpty($zeroErrors, 'Expected "no data rows" error');
    }

    // -------------------------------------------------------------------------
    // Matrix row 19: validateRows() rejects stored payload with blanked position
    // -------------------------------------------------------------------------

    public function testValidateRowsRejectsStoredPayloadWithBlankedPosition(): void
    {
        // Parse a valid single-row input to get a properly typed row.
        $raw    = $this->buildSemiRow(['name' => 'Stored Player', 'pos' => 'SG']);
        $parsed = $this->parser->parse($raw);
        $this->assertSame([], $parsed['errors'], 'Pre-condition: initial parse must be clean');
        $this->assertCount(1, $parsed['rows']);

        // Blank the pos (simulating post-storage mutation or direct API write).
        $rows         = $parsed['rows'];
        $rows[0]['pos'] = '';

        $errors = $this->parser->validateRows($rows);

        $posErrors = array_filter(
            $errors,
            static fn(string $e): bool => str_contains($e, 'position is blank')
        );
        $this->assertNotEmpty($posErrors, 'validateRows() must reject a stored row with blank pos');
    }
}
