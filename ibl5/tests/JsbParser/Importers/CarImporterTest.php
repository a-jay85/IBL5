<?php

declare(strict_types=1);

namespace Tests\JsbParser\Importers;

use JsbParser\CarFileParser;
use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\Importers\CarImporter;
use JsbParser\PlayerIdResolver;
use PHPUnit\Framework\TestCase;

/**
 * Pins the exact arguments CarImporter hands to PlayerIdResolver::resolve.
 *
 * @covers \JsbParser\Importers\CarImporter
 */
final class CarImporterTest extends TestCase
{
    /** @var list<array<int, mixed>> */
    private array $resolverCalls = [];

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

    private function buildPlayerBlock(int $seasonCount, int $jsbId, string $name, string $seasonRecord): string
    {
        $header = str_pad((string) $seasonCount, 3, ' ', STR_PAD_LEFT);
        $header .= str_pad((string) $jsbId, 5, ' ', STR_PAD_LEFT);
        $header .= str_pad($name, 16);
        $block = $header . $seasonRecord;
        return str_pad($block, CarFileParser::BLOCK_SIZE, ' ');
    }

    private function buildCarFile(int $playerCount, string $playerBlock = ''): string
    {
        $header = str_pad((string) $playerCount, 4, ' ', STR_PAD_LEFT);
        $header = str_pad($header, CarFileParser::BLOCK_SIZE, ' ');
        return $header . $playerBlock;
    }

    private function buildFixture(): string
    {
        $seasons = $this->buildSeasonRecord(2005, 'Celtics', 'Test Guard')
            . $this->buildSeasonRecord(2006, 'Celtics', 'Test Guard');

        return $this->buildCarFile(1, $this->buildPlayerBlock(2, 77, 'Test Guard', $seasons));
    }

    private function repositoryResolvingCeltics(?int $teamId): JsbImportRepositoryInterface&\PHPUnit\Framework\MockObject\Stub
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('resolveTeamIdByName')->willReturnMap([['Celtics', $teamId]]);
        return $repo;
    }

    private function resolverReturning(?int $pid): PlayerIdResolver&\PHPUnit\Framework\MockObject\Stub
    {
        $resolver = self::createStub(PlayerIdResolver::class);
        $resolver->method('resolve')->willReturnCallback(function () use ($pid): ?int {
            $this->resolverCalls[] = func_get_args();
            return $pid;
        });
        return $resolver;
    }

    public function testImportPassesExactArgumentsToResolver(): void
    {
        $importer = new CarImporter($this->repositoryResolvingCeltics(2), $this->resolverReturning(4242));

        $result = $importer->import($this->buildFixture());

        $this->assertSame([
            ['Test Guard', 'Celtics', 2005, 2],
            ['Test Guard', 'Celtics', 2006, 2],
        ], $this->resolverCalls);
        $this->assertSame(2, $result->inserted);
        $this->assertSame(0, $result->skipped);
    }

    public function testImportFilterYearProcessesOnlyMatchingSeason(): void
    {
        $importer = new CarImporter($this->repositoryResolvingCeltics(2), $this->resolverReturning(4242));

        $result = $importer->import($this->buildFixture(), 2006);

        $this->assertSame([['Test Guard', 'Celtics', 2006, 2]], $this->resolverCalls);
        $this->assertSame(1, $result->inserted);
    }

    public function testImportCountsUnresolvedPlayerAsSkipped(): void
    {
        $importer = new CarImporter($this->repositoryResolvingCeltics(2), $this->resolverReturning(null));

        $result = $importer->import($this->buildFixture());

        $this->assertSame(2, $result->skipped);
        $this->assertSame(0, $result->inserted);
    }

    public function testImportPassesNullTeamIdWhenTeamUnresolved(): void
    {
        $importer = new CarImporter($this->repositoryResolvingCeltics(null), $this->resolverReturning(4242));

        $importer->import($this->buildFixture());

        $this->assertSame([
            ['Test Guard', 'Celtics', 2005, null],
            ['Test Guard', 'Celtics', 2006, null],
        ], $this->resolverCalls);
    }

    public function testImportFileReportsMissingFile(): void
    {
        $importer = new CarImporter(
            self::createStub(JsbImportRepositoryInterface::class),
            self::createStub(PlayerIdResolver::class),
        );

        $result = $importer->importFile('/nonexistent/x.car');

        $this->assertSame(1, $result->errors);
        $this->assertStringContainsString('CAR file not found: /nonexistent/x.car', implode("\n", $result->messages));
    }
}
