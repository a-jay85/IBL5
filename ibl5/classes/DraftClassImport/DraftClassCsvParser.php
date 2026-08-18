<?php

declare(strict_types=1);

namespace DraftClassImport;

final class DraftClassCsvParser
{
    /**
     * 1-based field index → column name mapping.
     *
     * @var array<int, string>
     */
    private const COLUMNS = [
        1  => 'name',
        2  => 'pos',
        3  => 'age',
        4  => 'team',
        5  => 'fga',
        6  => 'fgp',
        7  => 'fta',
        8  => 'ftp',
        9  => 'r_3ga',
        10 => 'r_3gp',
        11 => 'orb',
        12 => 'drb',
        13 => 'ast',
        14 => 'stl',
        15 => 'tvr',
        16 => 'blk',
        17 => 'oo',
        18 => 'r_drive_off',
        19 => 'po',
        20 => 'r_trans_off',
        21 => 'od',
        22 => 'dd',
        23 => 'pd',
        24 => 'td',
        25 => 'talent',
        26 => 'skill',
        27 => 'intangibles',
    ];

    /**
     * DB column → the label the league reads.
     *
     * The DB names are SQL-safe spellings (r_3ga, r_drive_off, r_trans_off) that no
     * commissioner recognises; these are the headers the ratings table shows
     * (classes/UI/Tables/Ratings.php), so an error message names a rating the reader
     * can find in their export. Talent/skill/intangibles are absent from that table,
     * so their labels come from the contracts table (classes/UI/Tables/Contracts.php).
     *
     * Public: uploadDraftClass.php renders its preview header from this same map, so
     * the header and the error text can never drift apart.
     *
     * @var array<string, string>
     */
    public const COLUMN_LABELS = [
        'name' => 'Player',
        'pos' => 'Pos',
        'age' => 'Age',
        'team' => 'Team',
        'fga' => '2ga',
        'fgp' => '2g%',
        'fta' => 'fta',
        'ftp' => 'ft%',
        'r_3ga' => '3ga',
        'r_3gp' => '3g%',
        'orb' => 'orb',
        'drb' => 'drb',
        'ast' => 'ast',
        'stl' => 'stl',
        'tvr' => 'tvr',
        'blk' => 'blk',
        'oo' => 'oo',
        'r_drive_off' => 'do',
        'po' => 'po',
        'r_trans_off' => 'to',
        'od' => 'od',
        'dd' => 'dd',
        'pd' => 'pd',
        'td' => 'td',
        'talent' => 'Tal',
        'skill' => 'Skl',
        'intangibles' => 'Int',
    ];

    /** @var list<string> */
    private const VALID_POSITIONS = ['PG', 'SG', 'SF', 'PF', 'C', 'G', 'F', 'GF'];

    /**
     * 1-based field indices that carry a tinyint unsigned value (0–255).
     *
     * @var list<int>
     */
    private const TINYINT_FIELDS = [5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24];

    /**
     * 1-based field indices for unrestricted integer columns.
     *
     * @var list<int>
     */
    private const UNBOUNDED_INT_FIELDS = [3, 25, 26, 27];

    /**
     * @return array{rows: list<array<string, int|string>>, errors: list<string>}
     */
    public function parse(string $raw): array
    {
        // Step 1: encoding check.
        if (mb_check_encoding($raw, 'UTF-8') === false) {
            return [
                'rows'   => [],
                'errors' => [
                    'This file is not saved as UTF-8. Re-export it as UTF-8 '
                    . '(in Excel: Save As → CSV UTF-8) and try again.',
                ],
            ];
        }

        // Step 2: strip BOM.
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        // Step 3: delimiter auto-detect.
        $firstNewline = strpos($raw, "\n");
        $firstLine    = $firstNewline !== false ? substr($raw, 0, $firstNewline) : $raw;
        $delimiter    = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        // Step 4: write to memory handle.
        $handle = fopen('php://memory', 'r+');
        if ($handle === false) {
            return ['rows' => [], 'errors' => ['Failed to open memory buffer.']];
        }
        fwrite($handle, $raw);
        rewind($handle);

        $errors = [];
        /** @var list<array<string, int|string>> $rows */
        $rows = [];
        $lineNumber = 0;
        /** @var list<int> $rowLineNumbers */
        $rowLineNumbers = [];

        // Step 5: loop fgetcsv.
        while (true) {
            $record = fgetcsv($handle, 0, $delimiter, '"', '');
            if ($record === false) {
                break;
            }
            $lineNumber++;

            // Skip blank records.
            if ($record === [null]) {
                continue;
            }

            $count = count($record);

            // Field-count rule.
            if ($count < 27) {
                $errors[] = "Line {$lineNumber}: this row has {$count} columns but the "
                    . 'importer needs at least 27. Send the file to A-Jay before retrying.';
                continue;
            }

            // Extra-column check: any field at 1-based index >= 28 must be blank.
            for ($i = 28; $i <= $count; $i++) {
                $value = trim($record[$i - 1] ?? '');
                if ($value !== '') {
                    $errors[] = "Line {$lineNumber}, field {$i} contains '{$value}'. "
                        . 'This importer reads only the first 27 columns — your export '
                        . 'format may have changed. Send the file to A-Jay before retrying.';
                    // Report only the first non-blank extra column.
                    break;
                }
            }

            // Build a raw string map for the first 27 columns (cast null to '').
            /** @var array<string, string> $rawRow */
            $rawRow = [];
            foreach (self::COLUMNS as $index => $column) {
                $rawRow[$column] = $record[$index - 1] ?? '';
            }

            // Per-field validation (raw strings, helper casts internally).
            $fieldErrors = $this->validateFields($rawRow, $lineNumber);
            $errors      = array_merge($errors, $fieldErrors);

            // Build the typed row from the raw string map.
            /** @var array<string, int|string> $row */
            $row = [
                'name' => trim($rawRow['name']),
                'pos'  => trim($rawRow['pos']),
                'team' => trim($rawRow['team']),
                'age'  => (int) trim($rawRow['age']),
            ];
            foreach (self::TINYINT_FIELDS as $fieldIndex) {
                $column       = self::COLUMNS[$fieldIndex];
                $row[$column] = (int) trim($rawRow[$column]);
            }
            foreach (self::UNBOUNDED_INT_FIELDS as $fieldIndex) {
                if ($fieldIndex === 3) {
                    // age is already set above.
                    continue;
                }
                $column       = self::COLUMNS[$fieldIndex];
                $row[$column] = (int) trim($rawRow[$column]);
            }

            $rowLineNumbers[] = $lineNumber;
            $rows[]           = $row;
        }

        fclose($handle);

        // Step 6: zero data rows.
        if ($rows === []) {
            $errors[] = 'This file contains no data rows.';
            return ['rows' => [], 'errors' => $errors];
        }

        // Step 7: duplicate-name pass.
        $duplicateErrors = $this->checkDuplicateNames($rows, $rowLineNumbers);
        $errors          = array_merge($errors, $duplicateErrors);

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @param list<array<string, int|string>> $rows
     * @return list<string>
     */
    public function validateRows(array $rows): array
    {
        $errors = [];

        foreach ($rows as $position => $row) {
            $lineNumber  = $position + 1;
            $fieldErrors = $this->validateFields($row, $lineNumber);
            $errors      = array_merge($errors, $fieldErrors);
        }

        // Duplicate-name pass uses list-position+1 as line numbers.
        /** @var list<int> $rowLineNumbers */
        $rowLineNumbers = [];
        foreach ($rows as $position => $_row) {
            $rowLineNumbers[] = $position + 1;
        }

        $duplicateErrors = $this->checkDuplicateNames($rows, $rowLineNumbers);
        $errors          = array_merge($errors, $duplicateErrors);

        return $errors;
    }

    /**
     * Validate all per-field rules for a single row.
     *
     * Accepts int|string values (casts to string internally) so it works on both
     * raw parse-time string maps and stored typed row arrays from validateRows().
     *
     * @param array<string, int|string> $row
     * @param int $lineNumber 1-based line number for error messages
     * @return list<string>
     */
    private function validateFields(array $row, int $lineNumber): array
    {
        $errors = [];

        // Field 1: name.
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            $errors[] = "Line {$lineNumber}: the player name is blank. Every row needs a name.";
        } elseif (mb_strlen($name) > 32) {
            $errors[] = "Line {$lineNumber}: the player name '{$name}' is longer than 32 "
                . 'characters. Shorten it and try again.';
        }

        // Field 2: pos.
        $pos = trim((string) ($row['pos'] ?? ''));
        if ($pos === '') {
            $errors[] = "Line {$lineNumber}: the position is blank. "
                . 'Use one of: PG, SG, SF, PF, C, G, F, GF.';
        } elseif (!in_array($pos, self::VALID_POSITIONS, true)) {
            $errors[] = "Line {$lineNumber}: '{$pos}' is not a valid position. "
                . 'Use one of: PG, SG, SF, PF, C, G, F, GF.';
        }

        // Field 3: age (integer, no range constraint).
        $errors = array_merge($errors, $this->validateInteger(
            (string) ($row['age'] ?? ''),
            self::COLUMN_LABELS['age'],
            $lineNumber,
            null,
            null
        ));

        // Field 4: team — blank is correct, no validation needed.

        // Fields 5–24: tinyint unsigned (0–255).
        foreach (self::TINYINT_FIELDS as $fieldIndex) {
            $column = self::COLUMNS[$fieldIndex];
            $errors = array_merge($errors, $this->validateInteger(
                (string) ($row[$column] ?? ''),
                self::COLUMN_LABELS[$column],
                $lineNumber,
                0,
                255
            ));
        }

        // Fields 25–27: integer, no range constraint.
        foreach ([25, 26, 27] as $fieldIndex) {
            $column = self::COLUMNS[$fieldIndex];
            $errors = array_merge($errors, $this->validateInteger(
                (string) ($row[$column] ?? ''),
                self::COLUMN_LABELS[$column],
                $lineNumber,
                null,
                null
            ));
        }

        return $errors;
    }

    /**
     * Validate that a field value is a whole number and (when given) within range.
     *
     * @param string   $raw        Raw string value; will be trimmed here.
     * @param string   $label      Reader-facing column label for error messages (COLUMN_LABELS).
     * @param int      $lineNumber 1-based line number.
     * @param int|null $min        Minimum allowed value, or null for no lower bound.
     * @param int|null $max        Maximum allowed value, or null for no upper bound.
     * @return list<string>
     */
    private function validateInteger(
        string $raw,
        string $label,
        int $lineNumber,
        ?int $min,
        ?int $max
    ): array {
        $value = trim($raw);
        if (preg_match('/^-?\d+$/', $value) !== 1) {
            return [
                "Line {$lineNumber}: column {$label} should be a whole number "
                . "but contains '{$value}'.",
            ];
        }

        if ($min !== null && $max !== null) {
            $intValue = (int) $value;
            if ($intValue < $min || $intValue > $max) {
                return [
                    "Line {$lineNumber}: column {$label} is {$value}, which is "
                    . "outside the allowed range {$min}-{$max}.",
                ];
            }
        }

        return [];
    }

    /**
     * Check for duplicate player names across collected rows.
     *
     * @param list<array<string, int|string>> $rows
     * @param list<int>                       $rowLineNumbers 1-based line number for each row (parallel array).
     * @return list<string>
     */
    private function checkDuplicateNames(array $rows, array $rowLineNumbers): array
    {
        /** @var array<string, list<int>> $nameToLines */
        $nameToLines = [];
        foreach ($rows as $position => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $lineNumber           = $rowLineNumbers[$position];
            $nameToLines[$name][] = $lineNumber;
        }

        $errors = [];
        foreach ($nameToLines as $name => $lines) {
            if (count($lines) < 2) {
                continue;
            }
            $last     = array_pop($lines);
            $joined   = implode(', ', $lines) . ' and ' . $last;
            $errors[] = "The name '{$name}' appears on lines {$joined}. "
                . 'Each player must appear once.';
        }

        return $errors;
    }
}
