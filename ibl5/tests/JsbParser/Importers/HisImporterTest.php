<?php

declare(strict_types=1);

namespace Tests\JsbParser\Importers;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\Importers\HisImporter;
use PHPUnit\Framework\TestCase;

/**
 * Pins what HisImporter.php sends to the repository for each team row.
 *
 * @covers \JsbParser\Importers\HisImporter
 */
final class HisImporterTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $captured = [];

    /**
     * Build a synthetic .his file with known season data.
     *
     * @param list<array{lines: list<string>}> $seasons Each season's team lines
     */
    private function buildHisFile(array $seasons): string
    {
        $content = '';
        foreach ($seasons as $season) {
            foreach ($season['lines'] as $line) {
                $content .= $line . "\r\n";
            }
            $content .= "\r\n"; // blank line between seasons
        }
        return $content;
    }

    private function buildFixture(): string
    {
        return $this->buildHisFile([['lines' => [
            'Clippers (62-20)  defeat the  Raptors (59-23) 4 games to 3 in the championship (1988)',
            'Celtics (24-58) (1988)',
        ]]]);
    }

    private function repositoryCapturing(string $method): JsbImportRepositoryInterface&\PHPUnit\Framework\MockObject\Stub
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method($method)->willReturnCallback(function (array $record): int {
            $this->captured[] = $record;
            return 1;
        });
        $repo->method('resolveTeamIdByName')->willReturnMap([['Clippers', 12], ['Celtics', null]]);
        return $repo;
    }

    public function testImportSendsExactHistoryRecordForChampionTeam(): void
    {
        $repo = $this->repositoryCapturing('upsertHistoryRecord');

        $result = (new HisImporter($repo))->import($this->buildFixture(), 'his-1989.his');

        $this->assertSame(2, $result->inserted);
        $this->assertSame([
            'season_year' => 1989,
            'team_name' => 'Clippers',
            'teamid' => 12,
            'wins' => 62,
            'losses' => 20,
            'made_playoffs' => 1,
            'playoff_result' => 'defeat the  Raptors (59-23) 4 games to 3 in the championship',
            'playoff_round_reached' => 'championship',
            'won_championship' => 1,
            'source_file' => 'his-1989.his',
        ], $this->captured[0]);
    }

    public function testImportMapsEmptyPlayoffFieldsToNullForNonPlayoffTeam(): void
    {
        $repo = $this->repositoryCapturing('upsertHistoryRecord');

        (new HisImporter($repo))->import($this->buildFixture(), 'his-1989.his');

        $this->assertSame([
            'season_year' => 1989,
            'team_name' => 'Celtics',
            'teamid' => null,
            'wins' => 24,
            'losses' => 58,
            'made_playoffs' => 0,
            'playoff_result' => null,
            'playoff_round_reached' => null,
            'won_championship' => 0,
            'source_file' => 'his-1989.his',
        ], $this->captured[1]);
    }

    public function testImportPassesNullSourceFileWhenLabelOmitted(): void
    {
        $repo = $this->repositoryCapturing('upsertHistoryRecord');

        (new HisImporter($repo))->import($this->buildFixture());

        $this->assertCount(2, $this->captured);
        $this->assertNull($this->captured[0]['source_file']);
        $this->assertNull($this->captured[1]['source_file']);
    }

    public function testImportConvertsRepositoryExceptionToError(): void
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('resolveTeamIdByName')->willReturn(null);
        $repo->method('upsertHistoryRecord')->willThrowException(new \RuntimeException('db down'));

        $result = (new HisImporter($repo))->import($this->buildFixture(), 'his-1989.his');

        $this->assertSame(2, $result->errors);
        $this->assertSame(0, $result->inserted);
        $this->assertStringContainsString('History upsert failed for Clippers (1989): db down', implode("\n", $result->messages));
    }

    public function testImportFileReportsMissingFile(): void
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);

        $result = (new HisImporter($repo))->importFile('/nonexistent/x.his');

        $this->assertSame(1, $result->errors);
        $this->assertStringContainsString('HIS file not found: /nonexistent/x.his', implode("\n", $result->messages));
    }
}
