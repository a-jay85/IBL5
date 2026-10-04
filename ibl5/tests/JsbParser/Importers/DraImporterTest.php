<?php

declare(strict_types=1);

namespace Tests\JsbParser\Importers;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\Importers\DraImporter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\Importers\DraImporter
 */
final class DraImporterTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $captured = [];

    /**
     * Build a synthetic .dra file from structured draft data.
     *
     * @param list<array{year: int, picks: list<array{round: int, pick: int, team: string, pos: string, name: string}>}> $drafts
     */
    private function buildDraFile(array $drafts): string
    {
        $content = '';
        foreach ($drafts as $draft) {
            $content .= " ***** {$draft['year']} rookie draft *****\r\n";
            // Add some lottery odds lines
            $content .= "Celtics (250) chances\r\n";
            $content .= "Heat (200) chances\r\n";

            $currentRound = 0;
            foreach ($draft['picks'] as $pick) {
                if ($pick['round'] !== $currentRound) {
                    if ($currentRound > 0) {
                        $content .= "* End of Round {$currentRound} *\r\n";
                    }
                    $currentRound = $pick['round'];
                }
                $content .= "Round {$pick['round']} Pick {$pick['pick']}\r\n";
                // Position is left-padded to 2 chars (e.g., " C" for center, "PF" for power forward)
                $pos = str_pad($pick['pos'], 2, ' ', STR_PAD_LEFT);
                $content .= "{$pick['team']}: {$pos} {$pick['name']}\r\n";
            }
            if ($currentRound > 0) {
                $content .= "* End of Round {$currentRound} *\r\n";
            }
            $content .= "* End of Draft *\r\n";
            // Add padding between drafts
            $content .= str_repeat(' ', 40) . "\r\n";
        }
        return $content;
    }

    private function buildFixture(): string
    {
        return $this->buildDraFile([
            ['year' => 1988, 'picks' => [
                ['round' => 1, 'pick' => 1, 'team' => 'Celtics', 'pos' => 'PF', 'name' => 'John Smith'],
                ['round' => 1, 'pick' => 2, 'team' => 'Heat', 'pos' => 'SG', 'name' => 'Jane Doe'],
                ['round' => 2, 'pick' => 1, 'team' => 'Celtics', 'pos' => 'C', 'name' => 'Bob Jones'],
            ]],
        ]);
    }

    private function repositoryCapturing(string $method): JsbImportRepositoryInterface&\PHPUnit\Framework\MockObject\Stub
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method($method)->willReturnCallback(function (array $record): int {
            $this->captured[] = $record;
            return 1;
        });
        return $repo;
    }

    public function testImportSendsExactDraftRecordPerPick(): void
    {
        $result = (new DraImporter($this->repositoryCapturing('upsertDraftResult')))->import($this->buildFixture());

        $this->assertSame(3, $result->inserted);
        $this->assertSame([
            [
                'draft_year' => 1988,
                'round' => 1,
                'pick' => 1,
                'team_name' => 'Celtics',
                'pos' => 'PF',
                'player_name' => 'John Smith',
                'pid' => null,
            ],
            [
                'draft_year' => 1988,
                'round' => 1,
                'pick' => 2,
                'team_name' => 'Heat',
                'pos' => 'SG',
                'player_name' => 'Jane Doe',
                'pid' => null,
            ],
            [
                'draft_year' => 1988,
                'round' => 2,
                'pick' => 1,
                'team_name' => 'Celtics',
                'pos' => 'C',
                'player_name' => 'Bob Jones',
                'pid' => null,
            ],
        ], $this->captured);
    }

    public function testImportAlwaysSendsNullPid(): void
    {
        (new DraImporter($this->repositoryCapturing('upsertDraftResult')))->import($this->buildFixture());

        $this->assertSame([null, null, null], array_column($this->captured, 'pid'));
    }

    public function testImportOfEmptyDataMakesNoRepositoryCall(): void
    {
        $result = (new DraImporter($this->repositoryCapturing('upsertDraftResult')))->import('');

        $this->assertSame([], $this->captured);
        $this->assertSame(0, $result->inserted);
        $this->assertSame(0, $result->errors);
    }

    public function testImportConvertsRepositoryExceptionToError(): void
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('upsertDraftResult')->willThrowException(new \RuntimeException('db down'));

        $result = (new DraImporter($repo))->import($this->buildFixture());

        $this->assertSame(3, $result->errors);
        $this->assertSame(0, $result->inserted);
        $this->assertStringContainsString('Draft upsert failed for John Smith: db down', implode("\n", $result->messages));
    }

    public function testImportFileReportsMissingFile(): void
    {
        $result = (new DraImporter(self::createStub(JsbImportRepositoryInterface::class)))->importFile('/nonexistent/x.dra');

        $this->assertSame(1, $result->errors);
        $this->assertStringContainsString('DRA file not found: /nonexistent/x.dra', implode("\n", $result->messages));
    }
}
