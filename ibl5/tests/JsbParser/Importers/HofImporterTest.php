<?php

declare(strict_types=1);

namespace Tests\JsbParser\Importers;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\Importers\HofImporter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\Importers\HofImporter
 */
final class HofImporterTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $captured = [];

    private const BLOCK_SIZE = 500;

    /**
     * Build a synthetic .hof file with entries placed into 500-byte blocks.
     *
     * @param list<list<array{pos: string, name: string, pid: int, year: int}>> $blocks Each inner list = entries for one block
     */
    private function buildHofFile(array $blocks): string
    {
        $content = '';
        for ($i = 0; $i < 14; $i++) {
            $blockEntries = $blocks[$i] ?? [];
            $blockContent = '';
            foreach ($blockEntries as $entry) {
                // Format: "{pos:2} {name}{pid} {year:4} " + CRLF
                $pos = str_pad($entry['pos'], 2, ' ', STR_PAD_LEFT);
                $line = sprintf('%s %s %d %d ', $pos, $entry['name'], $entry['pid'], $entry['year']);
                $blockContent .= $line . "\r\n";
            }
            // Pad to exactly 500 bytes
            $blockContent = str_pad($blockContent, self::BLOCK_SIZE, ' ');
            $content .= $blockContent;
        }
        return $content;
    }

    private function buildFixture(): string
    {
        return $this->buildHofFile([[
            ['pos' => 'C', 'name' => 'Known Legend', 'pid' => 501, 'year' => 1995],
            ['pos' => 'PG', 'name' => 'Unknown Legend', 'pid' => 502, 'year' => 1996],
        ]]);
    }

    private function repositoryCapturing(string $method): JsbImportRepositoryInterface&\PHPUnit\Framework\MockObject\Stub
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method($method)->willReturnCallback(function (array $record): int {
            $this->captured[] = $record;
            return 1;
        });
        $repo->method('getPlayerName')->willReturnMap([[501, 'Known Legend'], [502, null]]);
        return $repo;
    }

    public function testImportSendsExactInducteeRecordWithResolvedPid(): void
    {
        $result = (new HofImporter($this->repositoryCapturing('upsertHofInductee')))->import($this->buildFixture());

        $this->assertSame(2, $result->inserted);
        $this->assertSame([
            'jsb_pid' => 501,
            'player_name' => 'Known Legend',
            'pos' => 'C',
            'induction_year' => 1995,
            'pid' => 501,
        ], $this->captured[0]);
    }

    public function testImportSetsNullPidWhenPlayerUnknown(): void
    {
        (new HofImporter($this->repositoryCapturing('upsertHofInductee')))->import($this->buildFixture());

        $this->assertSame([
            'jsb_pid' => 502,
            'player_name' => 'Unknown Legend',
            'pos' => 'PG',
            'induction_year' => 1996,
            'pid' => null,
        ], $this->captured[1]);
    }

    public function testImportReportsParseFailureAndSkipsRepository(): void
    {
        $result = (new HofImporter($this->repositoryCapturing('upsertHofInductee')))->import('short');

        $this->assertSame(1, $result->errors);
        $this->assertStringContainsString('HOF parse failed: ', implode("\n", $result->messages));
        $this->assertSame([], $this->captured);
    }

    public function testImportConvertsRepositoryExceptionToError(): void
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('getPlayerName')->willReturn(null);
        $repo->method('upsertHofInductee')->willThrowException(new \RuntimeException('db down'));

        $result = (new HofImporter($repo))->import($this->buildFixture());

        $this->assertSame(2, $result->errors);
        $this->assertSame(0, $result->inserted);
        $this->assertStringContainsString('HoF upsert failed for Known Legend: db down', implode("\n", $result->messages));
    }

    public function testImportFileReportsMissingFile(): void
    {
        $result = (new HofImporter(self::createStub(JsbImportRepositoryInterface::class)))->importFile('/nonexistent/x.hof');

        $this->assertSame(1, $result->errors);
        $this->assertStringContainsString('HOF file not found: /nonexistent/x.hof', implode("\n", $result->messages));
    }
}
