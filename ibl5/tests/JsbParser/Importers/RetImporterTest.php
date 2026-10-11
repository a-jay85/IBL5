<?php

declare(strict_types=1);

namespace Tests\JsbParser\Importers;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\Importers\RetImporter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\Importers\RetImporter
 */
final class RetImporterTest extends TestCase
{
    /** @var list<array{jsb_pid: int, retirement_year: int, player_name: string, pid: int|null}> */
    private array $upserts = [];

    /** @var list<int> */
    private array $marked = [];

    /** @var list<int> */
    private array $nameLookups = [];

    /**
     * Build a synthetic .ret file from structured data.
     *
     * @param list<array{name: string, pid: int}> $entries
     * @param int $totalSlots Total padded slots (default 40)
     */
    private function buildRetFile(array $entries, int $totalSlots = 40): string
    {
        $content = '';
        foreach ($entries as $entry) {
            $content .= "{$entry['name']} {$entry['pid']}\r\n";
        }
        // Pad remaining slots with " 0"
        $remaining = $totalSlots - count($entries);
        for ($i = 0; $i < $remaining; $i++) {
            $content .= " 0\r\n";
        }
        // Trailing whitespace
        $content .= str_repeat(' ', 60) . "\r\n";
        return $content;
    }

    /**
     * @param array<int, string> $known         jsb_pid => player name for getPlayerName()
     * @param list<int>          $upsertReturns affected-row values consumed in call order (last value repeats)
     */
    private function repository(
        array $known,
        array $upsertReturns = [1],
        ?\RuntimeException $upsertThrows = null,
        ?\RuntimeException $markThrows = null,
    ): JsbImportRepositoryInterface {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('getPlayerName')->willReturnCallback(function (int $jsbPid) use ($known): ?string {
            $this->nameLookups[] = $jsbPid;
            return $known[$jsbPid] ?? null;
        });
        $repo->method('upsertRetiredPlayer')->willReturnCallback(
            function (array $record) use (&$upsertReturns, $upsertThrows): int {
                /** @var array{jsb_pid: int, retirement_year: int, player_name: string, pid: int|null} $record */
                $this->upserts[] = $record;
                if ($upsertThrows !== null) {
                    throw $upsertThrows;
                }
                return count($upsertReturns) > 1 ? (int) array_shift($upsertReturns) : $upsertReturns[0];
            }
        );
        $repo->method('markPlayerRetired')->willReturnCallback(
            function (int $pid) use ($markThrows): int {
                $this->marked[] = $pid;
                if ($markThrows !== null) {
                    throw $markThrows;
                }
                return 1;
            }
        );
        return $repo;
    }

    public function testUnknownPlayerUpsertsWithNullPidAndSkipsMarkPlayerRetired(): void
    {
        $repo = $this->repository([]);
        $data = $this->buildRetFile([['name' => 'Unknown Retiree', 'pid' => 202]]);

        $result = (new RetImporter($repo))->import($data, 2031);

        $this->assertSame(
            [['jsb_pid' => 202, 'retirement_year' => 2031, 'player_name' => 'Unknown Retiree', 'pid' => null]],
            $this->upserts
        );
        $this->assertSame([], $this->marked);
        $this->assertSame(1, $result->inserted);
        $this->assertSame(0, $result->errors);
    }

    public function testKnownPlayerUpsertsWithPidAndMarksPlayerRetired(): void
    {
        $repo = $this->repository([101 => 'Known Retiree']);
        $data = $this->buildRetFile([['name' => 'Known Retiree', 'pid' => 101]]);

        $result = (new RetImporter($repo))->import($data, 2031);

        $this->assertCount(1, $this->upserts);
        $this->assertSame(101, $this->upserts[0]['pid']);
        $this->assertSame(101, $this->upserts[0]['jsb_pid']);
        $this->assertSame([101], $this->marked);
        $this->assertSame(1, $result->inserted);
        $this->assertSame(0, $result->errors);
    }

    public function testUpsertFailureAddsErrorAndStillMarksPlayerRetired(): void
    {
        $repo = $this->repository([101 => 'Known Retiree'], [1], new \RuntimeException('db down'));
        $data = $this->buildRetFile([['name' => 'Known Retiree', 'pid' => 101]]);

        $result = (new RetImporter($repo))->import($data, 2031);

        $this->assertSame(1, $result->errors);
        $this->assertSame('ERROR: Retired player upsert failed for Known Retiree: db down', $result->messages[0]);
        $this->assertSame(0, $result->inserted);
        $this->assertSame(0, $result->updated);
        $this->assertSame([101], $this->marked);
    }

    public function testMarkPlayerRetiredFailureAddsError(): void
    {
        $repo = $this->repository([101 => 'Known Retiree'], [1], null, new \RuntimeException('flag boom'));
        $data = $this->buildRetFile([['name' => 'Known Retiree', 'pid' => 101]]);

        $result = (new RetImporter($repo))->import($data, 2031);

        $this->assertSame(1, $result->errors);
        $this->assertSame('ERROR: Retired flag update failed for Known Retiree: flag boom', $result->messages[0]);
        $this->assertSame(1, $result->inserted);
    }

    public function testPaddingOnlyInputMakesNoRepositoryCalls(): void
    {
        $repo = $this->repository([101 => 'Known Retiree']);

        $result = (new RetImporter($repo))->import($this->buildRetFile([]), 2031);

        $this->assertSame([], $this->nameLookups);
        $this->assertSame([], $this->upserts);
        $this->assertSame([], $this->marked);
        $this->assertSame(0, $result->inserted);
        $this->assertSame(0, $result->updated);
        $this->assertSame(0, $result->skipped);
        $this->assertSame(0, $result->errors);
    }

    public function testRecordUpsertCountsInsertAndUpdate(): void
    {
        $repo = $this->repository([101 => 'Known Retiree'], [1, 2]);
        $data = $this->buildRetFile([
            ['name' => 'Known Retiree', 'pid' => 101],
            ['name' => 'Unknown Retiree', 'pid' => 202],
        ]);

        $result = (new RetImporter($repo))->import($data, 2031);

        $this->assertSame(1, $result->inserted);
        $this->assertSame(1, $result->updated);
    }

    public function testImportFileReturnsErrorForMissingFile(): void
    {
        $repo = $this->repository([101 => 'Known Retiree']);
        $path = sys_get_temp_dir() . '/ret-missing-' . uniqid('', true) . '.ret';

        $result = (new RetImporter($repo))->importFile($path, 2031);

        $this->assertSame(1, $result->errors);
        $this->assertSame('ERROR: RET file not found: ' . $path, $result->messages[0]);
        $this->assertSame([], $this->upserts);
    }

    public function testImportFileReadsTempFileAndImportsEntries(): void
    {
        $repo = $this->repository([101 => 'Known Retiree']);
        $path = tempnam(sys_get_temp_dir(), 'ret');
        $this->assertIsString($path);
        file_put_contents($path, $this->buildRetFile([['name' => 'Known Retiree', 'pid' => 101]]));

        try {
            $result = (new RetImporter($repo))->importFile($path, 2031);
        } finally {
            unlink($path);
        }

        $this->assertSame(1, $result->inserted);
        $this->assertSame(2031, $this->upserts[0]['retirement_year']);
        $this->assertSame([101], $this->marked);
    }
}
