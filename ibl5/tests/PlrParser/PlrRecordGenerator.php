<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PlrParser\PlrFieldSerializer;
use PlrParser\PlrFileWriter;

/**
 * Seeded generator for property round-trip tests of the .plr field code.
 *
 * Builds 607-byte player records from generated integers and names, writing each
 * field through PlrFileWriter::FIELD_MAP offsets and widths with
 * PlrFieldSerializer::formatInt(). It writes fields directly and never calls
 * PlrFileWriter::applyChangesToRecord(), because that method rewrites derived team
 * fields when teamid changes and would overwrite generated values.
 *
 * Like SyntheticPlrCorpus, this is a helper: the name does not end in "Test", so
 * PHPUnit does not collect it.
 */
final class PlrRecordGenerator
{
    public const DEFAULT_SEED = 20261117;
    public const DEFAULT_ITERATIONS = 300;
    public const SEED_ENV = 'PLR_PROPERTY_SEED';
    public const ITERATIONS_ENV = 'PLR_PROPERTY_ITERATIONS';

    public const ORDINAL_OFFSET = 0; // PlrLineParser.php:23
    public const ORDINAL_WIDTH = 4;
    public const PID_OFFSET = 38; // PlrLineParser.php:24
    public const PID_WIDTH = 6;
    public const NAME_OFFSET = 4; // PlrLineParser.php:48
    public const NAME_WIDTH = 32;
    public const MAX_ORDINAL = 1440; // PlrLineParser.php:26

    /**
     * CP1252 byte => Unicode code point, the 27 defined slots in 0x80-0x9F.
     *
     * @var array<int, int>
     */
    public const CP1252_HIGH = [
        0x80 => 0x20AC, 0x82 => 0x201A, 0x83 => 0x0192, 0x84 => 0x201E,
        0x85 => 0x2026, 0x86 => 0x2020, 0x87 => 0x2021, 0x88 => 0x02C6, 0x89 => 0x2030,
        0x8A => 0x0160, 0x8B => 0x2039, 0x8C => 0x0152, 0x8E => 0x017D, 0x91 => 0x2018,
        0x92 => 0x2019, 0x93 => 0x201C, 0x94 => 0x201D, 0x95 => 0x2022, 0x96 => 0x2013,
        0x97 => 0x2014, 0x98 => 0x02DC, 0x99 => 0x2122, 0x9A => 0x0161, 0x9B => 0x203A,
        0x9C => 0x0153, 0x9E => 0x017E, 0x9F => 0x0178,
    ];

    /**
     * PlrFileWriter::FIELD_MAP key => PlrLineParser::parse() key.
     *
     * Every key is spelled out. An identity fallback would silently cover a new
     * FIELD_MAP field whose parser key happens to match.
     *
     * @var array<string, string>
     */
    public const WRITER_TO_PARSER_KEY = [
        'teamid' => 'teamid',
        'PGDepth' => 'PGDepth',
        'SGDepth' => 'SGDepth',
        'SFDepth' => 'SFDepth',
        'PFDepth' => 'PFDepth',
        'CDepth' => 'CDepth',
        'canPlayInGame' => 'canPlayInGame',
        'bird' => 'bird',
        'cy' => 'currentContractYear',
        'cyt' => 'totalContractYears',
        'salary_yr1' => 'contractYear1',
        'salary_yr2' => 'contractYear2',
        'salary_yr3' => 'contractYear3',
        'salary_yr4' => 'contractYear4',
        'salary_yr5' => 'contractYear5',
        'salary_yr6' => 'contractYear6',
        'freeAgentSigningFlag' => 'freeAgentSigningFlag',
        'contractOwnedBy' => 'unk_331',
        'currentTeamIndex' => 'unk_333',
        'previousTeamIndex' => 'unk_335',
        'seasonGamesStarted' => 'seasonGamesStarted',
        'seasonGamesPlayed' => 'seasonGamesPlayed',
        'seasonMIN' => 'seasonMIN',
        'season2GM' => 'season2GM',
        'season2GA' => 'season2GA',
        'seasonFTM' => 'seasonFTM',
        'seasonFTA' => 'seasonFTA',
        'season3GM' => 'season3GM',
        'season3GA' => 'season3GA',
        'seasonORB' => 'seasonORB',
        'seasonDRB' => 'seasonDRB',
        'seasonAST' => 'seasonAST',
        'seasonSTL' => 'seasonSTL',
        'seasonTVR' => 'seasonTVR',
        'seasonBLK' => 'seasonBLK',
        'seasonPF' => 'seasonPF',
        'playoffSeasonGP' => 'playoffSeasonGP',
        'playoffSeasonMIN' => 'playoffSeasonMIN',
        'playoffSeason2GM' => 'playoffSeason2GM',
        'playoffSeason2GA' => 'playoffSeason2GA',
        'playoffSeasonFTM' => 'playoffSeasonFTM',
        'playoffSeasonFTA' => 'playoffSeasonFTA',
        'playoffSeason3GM' => 'playoffSeason3GM',
        'playoffSeason3GA' => 'playoffSeason3GA',
        'playoffSeasonORB' => 'playoffSeasonORB',
        'playoffSeasonDRB' => 'playoffSeasonDRB',
        'playoffSeasonAST' => 'playoffSeasonAST',
        'playoffSeasonSTL' => 'playoffSeasonSTL',
        'playoffSeasonTVR' => 'playoffSeasonTVR',
        'playoffSeasonBLK' => 'playoffSeasonBLK',
        'playoffSeasonPF' => 'playoffSeasonPF',
        'seasonHighPTS' => 'seasonHighPTS',
        'seasonHighREB' => 'seasonHighREB',
        'seasonHighAST' => 'seasonHighAST',
        'seasonHighSTL' => 'seasonHighSTL',
        'seasonHighBLK' => 'seasonHighBLK',
        'seasonHighDoubleDoubles' => 'seasonHighDoubleDoubles',
        'seasonHighTripleDoubles' => 'seasonHighTripleDoubles',
        'seasonPlayoffHighPTS' => 'seasonPlayoffHighPTS',
        'seasonPlayoffHighREB' => 'seasonPlayoffHighREB',
        'seasonPlayoffHighAST' => 'seasonPlayoffHighAST',
        'seasonPlayoffHighSTL' => 'seasonPlayoffHighSTL',
        'seasonPlayoffHighBLK' => 'seasonPlayoffHighBLK',
        'careerSeasonHighPTS' => 'careerSeasonHighPTS',
        'careerSeasonHighREB' => 'careerSeasonHighREB',
        'careerSeasonHighAST' => 'careerSeasonHighAST',
        'careerSeasonHighSTL' => 'careerSeasonHighSTL',
        'careerSeasonHighBLK' => 'careerSeasonHighBLK',
        'careerSeasonHighDoubleDoubles' => 'careerSeasonHighDoubleDoubles',
        'careerSeasonHighTripleDoubles' => 'careerSeasonHighTripleDoubles',
        'careerPlayoffHighPTS' => 'careerPlayoffHighPTS',
        'careerPlayoffHighREB' => 'careerPlayoffHighREB',
        'careerPlayoffHighAST' => 'careerPlayoffHighAST',
        'careerPlayoffHighSTL' => 'careerPlayoffHighSTL',
        'careerPlayoffHighBLK' => 'careerPlayoffHighBLK',
    ];

    /**
     * Resolve the base seed from the PLR_PROPERTY_SEED environment value.
     *
     * @param string|false $raw getenv() result: false when unset
     */
    public static function resolveSeed(string|false $raw): int
    {
        // Unset variable.
        if ($raw === false) {
            return self::DEFAULT_SEED;
        }
        // Set but empty: CI and shells export empty variables, so this is legal too.
        if ($raw === '') {
            return self::DEFAULT_SEED;
        }
        if (!ctype_digit($raw) || strlen($raw) > 9) {
            throw new \InvalidArgumentException(
                self::SEED_ENV . ' must be 1 to 9 digits, got "' . $raw . '"'
            );
        }

        return (int) $raw;
    }

    /**
     * Resolve the iteration count from the PLR_PROPERTY_ITERATIONS environment value.
     *
     * @param string|false $raw getenv() result: false when unset
     */
    public static function resolveIterations(string|false $raw): int
    {
        // Unset variable.
        if ($raw === false) {
            return self::DEFAULT_ITERATIONS;
        }
        // Set but empty: CI and shells export empty variables, so this is legal too.
        if ($raw === '') {
            return self::DEFAULT_ITERATIONS;
        }
        if (!ctype_digit($raw) || strlen($raw) > 7 || (int) $raw < 1) {
            throw new \InvalidArgumentException(
                self::ITERATIONS_ENV . ' must be 1 to 7 digits and at least 1, got "' . $raw . '"'
            );
        }

        return (int) $raw;
    }

    /**
     * Lowest value formatInt() accepts for a width: a leading minus sign takes one column.
     */
    public static function fieldMin(int $width): int
    {
        if ($width < 2) {
            return 0;
        }

        return -(10 ** ($width - 1) - 1);
    }

    /**
     * Highest value formatInt() accepts for a width.
     */
    public static function fieldMax(int $width): int
    {
        return 10 ** $width - 1;
    }

    /**
     * @return array{fields: array<string, int>, ordinal: int, pid: int, name: string}
     */
    public static function generateRecordInput(int $seed): array
    {
        mt_srand($seed);

        $fields = [];
        foreach (PlrFileWriter::FIELD_MAP as $key => [, $width]) {
            $fields[$key] = mt_rand(self::fieldMin($width), self::fieldMax($width));
        }

        $ordinal = mt_rand(1, self::MAX_ORDINAL);
        $pid = mt_rand(1, 999999);
        $name = self::generateName(0, self::NAME_WIDTH, true);

        return ['fields' => $fields, 'ordinal' => $ordinal, 'pid' => $pid, 'name' => $name];
    }

    /**
     * Two fixed inputs with no RNG: every field at its maximum, then at its minimum.
     *
     * @return list<array{fields: array<string, int>, ordinal: int, pid: int, name: string}>
     */
    public static function boundaryRecordInputs(): array
    {
        $maxFields = [];
        $minFields = [];
        foreach (PlrFileWriter::FIELD_MAP as $key => [, $width]) {
            $maxFields[$key] = self::fieldMax($width);
            $minFields[$key] = self::fieldMin($width);
        }

        $maxName = '';
        foreach (self::CP1252_HIGH as $codePoint) {
            $maxName .= self::codePointToUtf8($codePoint);
        }

        return [
            [
                'fields' => $maxFields,
                'ordinal' => self::MAX_ORDINAL,
                'pid' => 999999,
                'name' => $maxName,
            ],
            [
                'fields' => $minFields,
                'ordinal' => 1,
                'pid' => 1,
                'name' => str_repeat(self::codePointToUtf8(0xFF), self::NAME_WIDTH),
            ],
        ];
    }

    /**
     * Build a 607-byte record with no line terminator.
     *
     * @param array{fields: array<string, int>, ordinal: int, pid: int, name: string} $input
     */
    public static function buildLine(array $input): string
    {
        $line = str_repeat(' ', SyntheticPlrCorpus::LINE_LENGTH);

        $line = substr_replace(
            $line,
            PlrFieldSerializer::formatInt($input['ordinal'], self::ORDINAL_WIDTH),
            self::ORDINAL_OFFSET,
            self::ORDINAL_WIDTH
        );
        $line = substr_replace(
            $line,
            PlrFieldSerializer::formatInt($input['pid'], self::PID_WIDTH),
            self::PID_OFFSET,
            self::PID_WIDTH
        );
        $line = substr_replace(
            $line,
            PlrFieldSerializer::formatRightString(
                PlrFieldSerializer::toCP1252($input['name']),
                self::NAME_WIDTH
            ),
            self::NAME_OFFSET,
            self::NAME_WIDTH
        );

        foreach ($input['fields'] as $key => $value) {
            [$offset, $width] = PlrFileWriter::FIELD_MAP[$key];
            $line = substr_replace($line, PlrFieldSerializer::formatInt($value, $width), $offset, $width);
        }

        return $line;
    }

    /**
     * The 251 defined CP1252 characters as Unicode code points: 0x00-0x7F, the 27
     * defined slots of 0x80-0x9F, and 0xA0-0xFF. The five undefined slots 0x81, 0x8D,
     * 0x8F, 0x90 and 0x9D are absent because CP1252 assigns them no character.
     *
     * @return list<int>
     */
    public static function encodingAlphabet(): array
    {
        return [
            ...range(0x00, 0x7F),
            ...array_values(self::CP1252_HIGH),
            ...range(0xA0, 0xFF),
        ];
    }

    /**
     * Code points a record name can carry: 0x20-0x7E, the 27 defined CP1252 slots of
     * 0x80-0x9F, and 0xA0-0xFF (218 code points).
     *
     * Control bytes 0x00-0x1F and 0x7F are out of the record domain because records
     * are CRLF-delimited (PlrFileWriter::splitIntoLines explodes on "\r\n") and trim()
     * in PlrLineParser::parseIdentityFields strips "\t \n \r \0 \x0B".
     *
     * @return list<int>
     */
    public static function recordNameAlphabet(): array
    {
        return [
            ...range(0x20, 0x7E),
            ...array_values(self::CP1252_HIGH),
            ...range(0xA0, 0xFF),
        ];
    }

    /**
     * Draw a name of $minLength to $maxLength characters from the matching alphabet.
     *
     * When $recordSafe, the first and last character are never U+0020. PlrLineParser
     * trims the name field, so a name cannot carry edge spaces through the format.
     * That is a property of the field layout, so the generator stays out of that input
     * range on purpose.
     */
    public static function generateName(int $minLength, int $maxLength, bool $recordSafe): string
    {
        $alphabet = $recordSafe ? self::recordNameAlphabet() : self::encodingAlphabet();
        $last = count($alphabet) - 1;
        $length = mt_rand($minLength, $maxLength);

        $codePoints = [];
        for ($i = 0; $i < $length; $i++) {
            $codePoints[] = $alphabet[mt_rand(0, $last)];
        }

        if ($recordSafe && $length >= 1) {
            foreach ([0, $length - 1] as $edge) {
                while ($codePoints[$edge] === 0x20) {
                    $codePoints[$edge] = $alphabet[mt_rand(0, $last)];
                }
            }
        }

        $name = '';
        foreach ($codePoints as $codePoint) {
            $name .= self::codePointToUtf8($codePoint);
        }

        return $name;
    }

    /**
     * One-line failure description. Iteration i uses seed base + i, so the printed
     * seed alone reproduces the case.
     *
     * @param array<string, mixed> $input
     */
    public static function describeFailure(int $baseSeed, int $iterations, int $seed, array $input): string
    {
        $json = json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $message = self::SEED_ENV . '=' . $baseSeed
            . ' ' . self::ITERATIONS_ENV . '=' . $iterations
            . ' seed=' . $seed
            . ' reproduce: ' . self::SEED_ENV . '=' . $seed
            . ' ' . self::ITERATIONS_ENV . '=1'
            . ' input=' . $json;

        if (isset($input['name']) && is_string($input['name'])) {
            $message .= ' nameHex=' . bin2hex(PlrFieldSerializer::toCP1252($input['name']));
        }

        return $message;
    }

    private static function codePointToUtf8(int $codePoint): string
    {
        $char = mb_chr($codePoint, 'UTF-8');
        if ($char === false) {
            throw new \InvalidArgumentException('Invalid code point ' . $codePoint);
        }

        return $char;
    }
}
