<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PHPUnit\Framework\TestCase;
use PlrParser\PlrFieldSerializer;
use PlrParser\PlrFileWriter;
use PlrParser\PlrLineParser;

/**
 * Seeded property round-trip tests for the .plr field code.
 *
 * Each looped property runs the fixed boundary records first, then one case per seed
 * from base .. base + n - 1. Defaults: seed 20261117, 300 iterations. Override with
 * PLR_PROPERTY_SEED and PLR_PROPERTY_ITERATIONS. A failure message prints the base
 * seed, the per-case seed, a one-line reproduce command, and the input.
 *
 * @covers \PlrParser\PlrFieldSerializer
 * @covers \PlrParser\PlrLineParser
 */
final class PlrRoundTripPropertyTest extends TestCase
{
    protected function tearDown(): void
    {
        // Do not leak the fixed seed into later tests.
        mt_srand();
    }

    /**
     * @return list<int>
     */
    private function seeds(): array
    {
        return range(
            $this->baseSeed(),
            $this->baseSeed() + $this->iterations() - 1
        );
    }

    private function baseSeed(): int
    {
        return PlrRecordGenerator::resolveSeed(getenv(PlrRecordGenerator::SEED_ENV));
    }

    private function iterations(): int
    {
        return PlrRecordGenerator::resolveIterations(getenv(PlrRecordGenerator::ITERATIONS_ENV));
    }

    private function describe(int $seed, mixed $input): string
    {
        if (!is_array($input)) {
            $input = ['value' => $input];
        }

        /** @var array<string, mixed> $input */
        return PlrRecordGenerator::describeFailure($this->baseSeed(), $this->iterations(), $seed, $input);
    }

    private function describeBoundary(int $index): string
    {
        $inputs = PlrRecordGenerator::boundaryRecordInputs();

        return 'boundary case ' . $index . ' input='
            . json_encode($inputs[$index], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, int|string>
     */
    private function parseLine(string $line, string $message): array
    {
        $parsed = PlrLineParser::parse($line);
        self::assertNotNull($parsed, $message);

        return $parsed;
    }

    public function testGeneratedRecordsRoundTripThroughParser(): void
    {
        $check = function (array $input, string $message): void {
            $parsed = $this->parseLine(PlrRecordGenerator::buildLine($input), $message);

            self::assertSame($input['ordinal'], $parsed['ordinal'], $message);
            self::assertSame($input['pid'], $parsed['pid'], $message);
            self::assertSame($input['name'], $parsed['name'], $message);
            foreach ($input['fields'] as $key => $value) {
                $parserKey = PlrRecordGenerator::WRITER_TO_PARSER_KEY[$key];
                self::assertSame($value, $parsed[$parserKey], $message . ' field=' . $key);
            }
        };

        foreach (PlrRecordGenerator::boundaryRecordInputs() as $index => $input) {
            $check($input, $this->describeBoundary($index));
        }
        foreach ($this->seeds() as $seed) {
            $input = PlrRecordGenerator::generateRecordInput($seed);
            $check($input, $this->describe($seed, $input));
        }
    }

    public function testGeneratedRecordLeavesUnwrittenFieldsBlank(): void
    {
        $writtenParserKeys = array_values(PlrRecordGenerator::WRITER_TO_PARSER_KEY);
        $identityKeys = ['ordinal', 'pid', 'name'];

        $check = function (array $input, string $message) use ($writtenParserKeys, $identityKeys): void {
            $parsed = $this->parseLine(PlrRecordGenerator::buildLine($input), $message);

            foreach ($parsed as $key => $value) {
                if (in_array($key, $identityKeys, true) || in_array($key, $writtenParserKeys, true)) {
                    continue;
                }
                $expected = $key === 'pos' ? '' : 0;
                self::assertSame($expected, $value, $message . ' unwritten field=' . $key);
            }
        };

        foreach (PlrRecordGenerator::boundaryRecordInputs() as $index => $input) {
            $check($input, $this->describeBoundary($index));
        }
        foreach ($this->seeds() as $seed) {
            $input = PlrRecordGenerator::generateRecordInput($seed);
            $check($input, $this->describe($seed, $input));
        }
    }

    public function testGeneratedRecordsHaveFixedLengthAndNoLineBreaks(): void
    {
        $check = static function (array $input, string $message): void {
            $line = PlrRecordGenerator::buildLine($input);

            self::assertSame(SyntheticPlrCorpus::LINE_LENGTH, strlen($line), $message);
            self::assertStringNotContainsString("\r", $line, $message);
            self::assertStringNotContainsString("\n", $line, $message);
        };

        foreach (PlrRecordGenerator::boundaryRecordInputs() as $index => $input) {
            $check($input, $this->describeBoundary($index));
        }
        foreach ($this->seeds() as $seed) {
            $input = PlrRecordGenerator::generateRecordInput($seed);
            $check($input, $this->describe($seed, $input));
        }
    }

    public function testCp1252NamesRoundTripThroughEncoding(): void
    {
        $alphabet = PlrRecordGenerator::encodingAlphabet();
        self::assertCount(251, $alphabet);

        $check = static function (string $name, string $message): void {
            $encoded = PlrFieldSerializer::toCP1252($name);

            self::assertSame($name, PlrFieldSerializer::toUtf8($encoded), $message);
            self::assertSame(mb_strlen($name, 'UTF-8'), strlen($encoded), $message . ' (one byte per character)');
        };

        $fixed = '';
        foreach ($alphabet as $codePoint) {
            $char = mb_chr($codePoint, 'UTF-8');
            self::assertNotFalse($char);
            $fixed .= $char;
        }
        $check($fixed, 'all 251 CP1252 characters in order nameHex=' . bin2hex($fixed));

        foreach ($this->seeds() as $seed) {
            mt_srand($seed);
            $name = PlrRecordGenerator::generateName(1, 64, false);
            $check($name, $this->describe($seed, ['name' => $name]));
        }
    }

    public function testCp1252NamesRoundTripThroughRecordNameField(): void
    {
        $zeroFields = array_fill_keys(array_keys(PlrFileWriter::FIELD_MAP), 0);

        $check = function (string $name, string $message) use ($zeroFields): void {
            $line = PlrRecordGenerator::buildLine([
                'fields' => $zeroFields,
                'ordinal' => 1,
                'pid' => 1,
                'name' => $name,
            ]);

            self::assertSame($name, $this->parseLine($line, $message)['name'], $message);
        };

        foreach (PlrRecordGenerator::boundaryRecordInputs() as $index => $input) {
            $check($input['name'], $this->describeBoundary($index));
        }
        foreach ($this->seeds() as $seed) {
            mt_srand($seed);
            $name = PlrRecordGenerator::generateName(0, PlrRecordGenerator::NAME_WIDTH, true);
            $check($name, $this->describe($seed, ['name' => $name]));
        }
    }

    public function testFormatIntRejectsGeneratedOverflowValues(): void
    {
        $attempts = 0;
        $caught = 0;

        foreach ($this->seeds() as $seed) {
            mt_srand($seed);
            foreach (PlrFileWriter::FIELD_MAP as $key => [, $width]) {
                $value = mt_rand(PlrRecordGenerator::fieldMax($width) + 1, 10 ** ($width + 1) - 1);
                $attempts++;
                try {
                    PlrFieldSerializer::formatInt($value, $width);
                } catch (\OverflowException) {
                    $caught++;
                    continue;
                }
                self::fail($this->describe($seed, ['field' => $key, 'width' => $width, 'value' => $value]));
            }
        }

        self::assertSame($attempts, $caught);
    }

    public function testFormatRightStringRejectsOverWidthNames(): void
    {
        $alphabet = PlrRecordGenerator::recordNameAlphabet();
        $attempts = 0;
        $caught = 0;

        foreach ($this->seeds() as $seed) {
            mt_srand($seed);
            $length = mt_rand(PlrRecordGenerator::NAME_WIDTH + 1, 64);
            $name = '';
            for ($i = 0; $i < $length; $i++) {
                $char = mb_chr($alphabet[mt_rand(0, count($alphabet) - 1)], 'UTF-8');
                self::assertNotFalse($char);
                $name .= $char;
            }

            $attempts++;
            try {
                PlrFieldSerializer::formatRightString(
                    PlrFieldSerializer::toCP1252($name),
                    PlrRecordGenerator::NAME_WIDTH
                );
            } catch (\OverflowException) {
                $caught++;
                continue;
            }
            self::fail($this->describe($seed, ['name' => $name]));
        }

        self::assertSame($attempts, $caught);
    }

    public function testParseReturnsNullForOutOfDomainOrdinalOrPid(): void
    {
        $boundary = PlrRecordGenerator::boundaryRecordInputs()[0];

        $boundary['ordinal'] = 1441;
        self::assertNull(PlrLineParser::parse(PlrRecordGenerator::buildLine($boundary)), 'ordinal 1441 must parse to null');

        $boundary['ordinal'] = 1440;
        self::assertNotNull(PlrLineParser::parse(PlrRecordGenerator::buildLine($boundary)), 'ordinal 1440 must parse');

        foreach ($this->seeds() as $seed) {
            $input = PlrRecordGenerator::generateRecordInput($seed);
            $message = $this->describe($seed, $input);

            $badOrdinal = $input;
            $badOrdinal['ordinal'] = mt_rand(1441, 9999);
            self::assertNull(PlrLineParser::parse(PlrRecordGenerator::buildLine($badOrdinal)), $message . ' badOrdinal=' . $badOrdinal['ordinal']);

            $badPid = $input;
            $badPid['pid'] = 0;
            self::assertNull(PlrLineParser::parse(PlrRecordGenerator::buildLine($badPid)), $message . ' badPid=0');
        }
    }

    public function testSameSeedGeneratesIdenticalRecords(): void
    {
        foreach ($this->seeds() as $seed) {
            $first = PlrRecordGenerator::buildLine(PlrRecordGenerator::generateRecordInput($seed));
            $second = PlrRecordGenerator::buildLine(PlrRecordGenerator::generateRecordInput($seed));
            $next = PlrRecordGenerator::buildLine(PlrRecordGenerator::generateRecordInput($seed + 1));

            self::assertSame($first, $second, 'seed=' . $seed . ' must generate identical records');
            self::assertNotSame($first, $next, 'seeds ' . $seed . ' and ' . ($seed + 1) . ' must differ');
        }
    }

    public function testResolveSeedTreatsUnsetAndEmptyAsDefault(): void
    {
        self::assertSame(PlrRecordGenerator::DEFAULT_SEED, PlrRecordGenerator::resolveSeed(false));
        self::assertSame(PlrRecordGenerator::DEFAULT_SEED, PlrRecordGenerator::resolveSeed(''));
        self::assertSame(42, PlrRecordGenerator::resolveSeed('42'));
        self::assertSame(0, PlrRecordGenerator::resolveSeed('0'));
    }

    public function testResolveSeedRejectsNonNumericValue(): void
    {
        foreach ([' 42', '42 ', '42abc', '-1', '4.2', '0x10', '1234567890'] as $raw) {
            try {
                PlrRecordGenerator::resolveSeed($raw);
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('PLR_PROPERTY_SEED', $e->getMessage(), 'raw="' . $raw . '"');
                continue;
            }
            self::fail('resolveSeed("' . $raw . '") must throw');
        }
    }

    public function testResolveIterationsRejectsZeroAndNonNumeric(): void
    {
        self::assertSame(PlrRecordGenerator::DEFAULT_ITERATIONS, PlrRecordGenerator::resolveIterations(false));
        self::assertSame(PlrRecordGenerator::DEFAULT_ITERATIONS, PlrRecordGenerator::resolveIterations(''));
        self::assertSame(5, PlrRecordGenerator::resolveIterations('5'));

        foreach (['0', '-3', 'abc', '12345678'] as $raw) {
            try {
                PlrRecordGenerator::resolveIterations($raw);
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('PLR_PROPERTY_ITERATIONS', $e->getMessage(), 'raw="' . $raw . '"');
                continue;
            }
            self::fail('resolveIterations("' . $raw . '") must throw');
        }
    }

    public function testFailureMessageCarriesSeedAndInput(): void
    {
        $input = ['pid' => 424242, 'name' => 'Zoë'];
        $message = PlrRecordGenerator::describeFailure(7, 3, 9, $input);

        self::assertStringContainsString('PLR_PROPERTY_SEED=7', $message);
        self::assertStringContainsString('seed=9', $message);
        self::assertStringContainsString('PLR_PROPERTY_SEED=9 PLR_PROPERTY_ITERATIONS=1', $message);
        self::assertStringContainsString('"pid":424242', $message);
    }

    public function testWriterToParserKeyMapCoversEveryFieldMapEntry(): void
    {
        $mapKeys = array_keys(PlrRecordGenerator::WRITER_TO_PARSER_KEY);
        $fieldMapKeys = array_keys(PlrFileWriter::FIELD_MAP);

        $missing = array_values(array_diff($fieldMapKeys, $mapKeys));
        $extra = array_values(array_diff($mapKeys, $fieldMapKeys));

        sort($mapKeys);
        sort($fieldMapKeys);

        self::assertSame(
            $fieldMapKeys,
            $mapKeys,
            'FIELD_MAP keys missing from WRITER_TO_PARSER_KEY: [' . implode(', ', $missing)
            . ']; keys in the map that FIELD_MAP lacks: [' . implode(', ', $extra) . ']'
        );
    }

    public function testWriterToParserKeyMapTargetsExistingDistinctParserKeys(): void
    {
        $parsed = $this->parseLine(
            PlrRecordGenerator::buildLine(PlrRecordGenerator::boundaryRecordInputs()[0]),
            'boundary record must parse'
        );

        foreach (PlrRecordGenerator::WRITER_TO_PARSER_KEY as $writerKey => $parserKey) {
            self::assertArrayHasKey($parserKey, $parsed, 'writer key ' . $writerKey . ' maps to a parser key parse() does not return');
        }

        self::assertCount(
            count(PlrRecordGenerator::WRITER_TO_PARSER_KEY),
            array_unique(PlrRecordGenerator::WRITER_TO_PARSER_KEY),
            'two writer keys read back from one parser key'
        );
    }
}
