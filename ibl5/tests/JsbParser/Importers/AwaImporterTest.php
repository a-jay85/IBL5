<?php

declare(strict_types=1);

namespace Tests\JsbParser\Importers;

use JsbParser\AwaFileParser;
use JsbParser\CarFileParser;
use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\Importers\AwaImporter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\Importers\AwaImporter
 */
final class AwaImporterTest extends TestCase
{
    /** @var list<array{int, string, string}> */
    private array $upserts = [];

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->tempFiles = [];
        parent::tearDown();
    }

    /**
     * Write a right-justified, space-padded 4-char ASCII integer into data.
     */
    private function writeAsciiInt(string $data, int $offset, int $value): string
    {
        $ascii = str_pad((string) $value, 4, ' ', STR_PAD_LEFT);
        return substr_replace($data, $ascii, $offset, 4);
    }

    private function buildSeasonRecord(
        int $year = 2006,
        string $team = 'Lakers',
        string $name = 'Test Player',
    ): string {
        $record = str_pad((string) $year, 4);
        $record .= str_pad($team, 16);
        $record .= str_pad($name, 16);
        $record .= str_pad('SF', 2);
        $record .= '0 ';
        $record .= str_pad('82', 2, ' ', STR_PAD_LEFT);   // gp
        $record .= str_pad('3200', 4, ' ', STR_PAD_LEFT);  // min
        $record .= str_pad('400', 4, ' ', STR_PAD_LEFT);   // 2gm
        $record .= str_pad('800', 4, ' ', STR_PAD_LEFT);   // 2ga
        $record .= str_pad('200', 4, ' ', STR_PAD_LEFT);   // ftm
        $record .= str_pad('250', 4, ' ', STR_PAD_LEFT);   // fta
        $record .= str_pad('100', 4, ' ', STR_PAD_LEFT);   // 3gm
        $record .= str_pad('300', 4, ' ', STR_PAD_LEFT);   // 3ga
        $record .= str_pad('80', 4, ' ', STR_PAD_LEFT);    // orb
        $record .= str_pad('320', 4, ' ', STR_PAD_LEFT);   // drb
        $record .= str_pad('250', 4, ' ', STR_PAD_LEFT);   // ast
        $record .= str_pad('100', 4, ' ', STR_PAD_LEFT);   // stl
        $record .= str_pad('150', 4, ' ', STR_PAD_LEFT);   // to
        $record .= str_pad('40', 4, ' ', STR_PAD_LEFT);    // blk
        $record .= str_pad('200', 4, ' ', STR_PAD_LEFT);   // pf
        $record .= '  ';
        return $record;
    }

    /** AWA block: pid at offset 32 activates the block and is the rank-1 Scoring Leader. */
    private function buildAwaBlock(int $pid, int $rank = 1): string
    {
        $block = $this->writeAsciiInt(str_repeat(' ', AwaFileParser::BLOCK_SIZE), 32, $pid);
        $sectionOffsets = [1 => 0, 3 => 268, 4 => 402, 5 => 536];
        if ($rank !== 1) {
            $block = $this->writeAsciiInt($block, $sectionOffsets[$rank] + 32, $pid);
        }
        return $block;
    }

    /** @param list<string> $blocks season blocks; block i (1-based) has year $startYear + i */
    private function buildAwaFile(int $startYear, array $blocks): string
    {
        $header = $this->writeAsciiInt(str_repeat(' ', AwaFileParser::BLOCK_SIZE), 0, $startYear);
        return $header . implode('', $blocks);
    }

    /** @param list<string|null> $names CAR block 1, 2, ...; null leaves that block blank (skipped by the parser) */
    private function buildCarFile(array $names): string
    {
        $file = str_pad((string) count($names), 4, ' ', STR_PAD_LEFT);
        $file = str_pad($file, CarFileParser::BLOCK_SIZE, ' ');
        foreach ($names as $i => $name) {
            if ($name === null) {
                $file .= str_repeat(' ', CarFileParser::BLOCK_SIZE);
                continue;
            }
            $header = str_pad('1', 3, ' ', STR_PAD_LEFT)
                . str_pad((string) (500 + $i), 5, ' ', STR_PAD_LEFT)
                . str_pad($name, 16);
            $file .= str_pad($header . $this->buildSeasonRecord(2001, 'Celtics', $name), CarFileParser::BLOCK_SIZE, ' ');
        }
        return $file;
    }

    private function twoSeasonAwa(): string
    {
        return $this->buildAwaFile(2000, [$this->buildAwaBlock(1), $this->buildAwaBlock(2)]);
    }

    private function twoSeasonCar(): string
    {
        return $this->buildCarFile(['Player One', 'Player Two']);
    }

    private function repository(?int $throwOnYear = null): JsbImportRepositoryInterface
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('upsertAward')->willReturnCallback(
            function (int $year, string $awardName, string $playerName) use ($throwOnYear): int {
                $this->upserts[] = [$year, $awardName, $playerName];
                if ($year === $throwOnYear) {
                    throw new \RuntimeException('db down');
                }
                return 1;
            }
        );
        return $repo;
    }

    private function writeTempFile(string $data): string
    {
        $path = tempnam(sys_get_temp_dir(), 'awa');
        $this->assertIsString($path);
        file_put_contents($path, $data);
        $this->tempFiles[] = $path;
        return $path;
    }

    public function testAwaParseFailureAddsErrorAndMakesNoUpserts(): void
    {
        $result = (new AwaImporter($this->repository()))->import(str_repeat(' ', 1999), $this->twoSeasonCar());

        $this->assertSame(1, $result->errors);
        $this->assertStringStartsWith('ERROR: AWA parse failed: ', $result->messages[0]);
        $this->assertStringContainsString('need at least 2000 bytes', $result->messages[0]);
        $this->assertSame([], $this->upserts);
    }

    public function testCarParseFailureAddsErrorAndMakesNoUpserts(): void
    {
        $result = (new AwaImporter($this->repository()))->import($this->twoSeasonAwa(), str_repeat(' ', 2499));

        $this->assertSame(1, $result->errors);
        $this->assertStringStartsWith('ERROR: CAR parse failed (for AWA name resolution): ', $result->messages[0]);
        $this->assertStringContainsString('too small', $result->messages[0]);
        $this->assertSame([], $this->upserts);
    }

    public function testScoringLeaderRankOneUpsertsAwardNamedFromCarBlockIndex(): void
    {
        $awa = $this->buildAwaFile(2000, [$this->buildAwaBlock(2)]);
        $car = $this->buildCarFile([null, 'Indexed Scorer']);

        $result = (new AwaImporter($this->repository()))->import($awa, $car);

        $this->assertSame([[2001, 'Scoring Leader (1st)', 'Indexed Scorer']], $this->upserts);
        $this->assertSame(1, $result->inserted);
    }

    public function testRankFiveLeaderUsesFifthSuffix(): void
    {
        $awa = $this->buildAwaFile(2000, [$this->buildAwaBlock(1, 5)]);
        $car = $this->buildCarFile(['Player One']);

        (new AwaImporter($this->repository()))->import($awa, $car);

        $this->assertSame(
            [
                [2001, 'Scoring Leader (1st)', 'Player One'],
                [2001, 'Scoring Leader (5th)', 'Player One'],
            ],
            $this->upserts
        );
    }

    public function testUnknownCarBlockIndexIsSkippedWithoutUpsert(): void
    {
        $awa = $this->buildAwaFile(2000, [$this->buildAwaBlock(9)]);
        $car = $this->buildCarFile(['Only Player']);

        $result = (new AwaImporter($this->repository()))->import($awa, $car);

        $this->assertSame(1, $result->skipped);
        $this->assertSame(0, $result->inserted);
        $this->assertSame(0, $result->errors);
        $this->assertSame([], $this->upserts);
    }

    public function testFilterYearKeepsOnlyMatchingSeason(): void
    {
        (new AwaImporter($this->repository()))->import($this->twoSeasonAwa(), $this->twoSeasonCar(), 2002);

        $this->assertSame([[2002, 'Scoring Leader (1st)', 'Player Two']], $this->upserts);
    }

    public function testNullFilterYearProcessesAllSeasons(): void
    {
        $result = (new AwaImporter($this->repository()))->import($this->twoSeasonAwa(), $this->twoSeasonCar());

        $this->assertSame(
            [
                [2001, 'Scoring Leader (1st)', 'Player One'],
                [2002, 'Scoring Leader (1st)', 'Player Two'],
            ],
            $this->upserts
        );
        $this->assertSame(2, $result->inserted);
    }

    public function testFilterYearWithNoMatchingSeasonMakesNoUpserts(): void
    {
        $result = (new AwaImporter($this->repository()))->import($this->twoSeasonAwa(), $this->twoSeasonCar(), 1999);

        $this->assertSame([], $this->upserts);
        $this->assertSame(0, $result->inserted);
        $this->assertSame(0, $result->updated);
        $this->assertSame(0, $result->skipped);
        $this->assertSame(0, $result->errors);
    }

    public function testUpsertAwardFailureAddsErrorAndContinuesWithNextSeason(): void
    {
        $result = (new AwaImporter($this->repository(throwOnYear: 2001)))
            ->import($this->twoSeasonAwa(), $this->twoSeasonCar());

        $this->assertSame(1, $result->errors);
        $this->assertSame('ERROR: Award upsert failed for Player One: db down', $result->messages[0]);
        $this->assertSame(1, $result->inserted);
        $this->assertCount(2, $this->upserts);
    }

    public function testImportFilesReturnsErrorWhenAwaFileMissing(): void
    {
        $car = $this->writeTempFile($this->twoSeasonCar());
        $awa = sys_get_temp_dir() . '/awa-missing-' . uniqid('', true);

        $result = (new AwaImporter($this->repository()))->importFiles($awa, $car);

        $this->assertSame(1, $result->errors);
        $this->assertSame('ERROR: AWA or CAR file not found', $result->messages[0]);
        $this->assertSame([], $this->upserts);
    }

    public function testImportFilesReturnsErrorWhenCarFileMissing(): void
    {
        $awa = $this->writeTempFile($this->twoSeasonAwa());
        $car = sys_get_temp_dir() . '/car-missing-' . uniqid('', true);

        $result = (new AwaImporter($this->repository()))->importFiles($awa, $car);

        $this->assertSame(1, $result->errors);
        $this->assertSame('ERROR: AWA or CAR file not found', $result->messages[0]);
        $this->assertSame([], $this->upserts);
    }

    public function testImportFilesReadsTempFilesAndForwardsFilterYear(): void
    {
        $awa = $this->writeTempFile($this->twoSeasonAwa());
        $car = $this->writeTempFile($this->twoSeasonCar());

        $result = (new AwaImporter($this->repository()))->importFiles($awa, $car, 2001);

        $this->assertSame([[2001, 'Scoring Leader (1st)', 'Player One']], $this->upserts);
        $this->assertSame(0, $result->errors);
    }
}
