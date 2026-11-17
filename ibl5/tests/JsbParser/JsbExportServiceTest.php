<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\Contracts\JsbExportRepositoryInterface;
use JsbParser\JsbExportService;
use JsbParser\TrnFileParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\JsbExportService
 */
class JsbExportServiceTest extends TestCase
{
    /**
     * Records copied byte for byte from JSB's own 08-09 sim09 IBL5.trn (right-padded to 128).
     * 80 and 81 are the Kagami/Pittman <-> Turner/Martin trade JSB wrote on Jan 5.
     */
    private const SIM09_TRADE_NOV_A = ' 11 22008 20    10    15  1998';
    private const SIM09_TRADE_NOV_B = ' 11 22008 20    15    10  6337';
    private const SIM09_WAIVER = ' 11 22008 3 7  2993';
    private const SIM09_INJURY = '  1 42008 1  450714   4            Left shoulder strain';
    private const SIM09_REC_80 = '  1 52008 20    20    21  20050    20    21  69301  2009     1    20';
    private const SIM09_REC_81 = '  1 52008 20    21    20  63630    21    20  4500';

    /** @var JsbExportRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private JsbExportRepositoryInterface $stubRepo;

    /** @var list<string> */
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        $this->stubRepo = self::createStub(JsbExportRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testRepositoryContractExtendsPlrExportRepositoryContract(): void
    {
        $this->assertContains(
            \PlrParser\Contracts\PlrExportRepositoryInterface::class,
            class_implements(JsbExportRepositoryInterface::class),
        );
    }

    // ── exportTrnFile ────────────────────────────────────────────

    public function testExistingRecordsStayByteIdenticalWhenATradeIsAppended(): void
    {
        $existing = $this->sim08Source();
        $this->stubRepo->method('getCompletedTradeItems')->willReturn($this->kagamiTurnerTrade());

        [$result, $output] = $this->export($existing, '2009-01-05');

        $this->assertSame(0, $result->errors);
        $this->assertSame(TrnFileParser::FILE_SIZE, strlen($output));
        // Header is the only change before the first new record.
        $this->assertSame(substr($existing, 16, 4 * 128), substr($output, 16, 4 * 128));
        // The bytes after the two new records stay as JSB left them.
        $this->assertSame(substr($existing, 16 + 6 * 128), substr($output, 16 + 6 * 128));
    }

    public function testNewTradeAppendsJsbShapedRecordsAndBumpsTheCount(): void
    {
        $this->stubRepo->method('getCompletedTradeItems')->willReturn($this->kagamiTurnerTrade());

        [$result, $output] = $this->export($this->sim08Source(), '2009-01-05');

        $this->assertSame(0, $result->errors);
        $this->assertSame(str_pad('6', 16), substr($output, 0, 16));
        $this->assertSame(str_pad(self::SIM09_REC_80, 128), substr($output, 16 + 4 * 128, 128));
        $this->assertSame(str_pad(self::SIM09_REC_81, 128), substr($output, 16 + 5 * 128, 128));

        $parsed = TrnFileParser::parse($output);
        $trades = array_values(array_filter(
            $parsed['transactions'],
            static fn (array $t): bool => $t['type'] === TrnFileParser::TYPE_TRADE && $t['day'] === 5,
        ));
        $this->assertCount(2, $trades);
        $this->assertSame(2009, $trades[0]['year']);
    }

    public function testTradeAlreadyInJsbLogIsNotAppendedAgain(): void
    {
        // JSB entered the same trade on Jan 4 and wrote record 81's items in the other order.
        $rec81Swapped = '  1 42008 20    21    20  45000    21    20  6363';
        $existing = $this->trnFile([
            self::SIM09_TRADE_NOV_A,
            str_replace('  1 52008', '  1 42008', self::SIM09_REC_80),
            $rec81Swapped,
        ]);
        $this->stubRepo->method('getCompletedTradeItems')->willReturn($this->kagamiTurnerTrade());

        [$result, $output] = $this->export($existing, '2009-01-05');

        $this->assertSame(0, $result->errors);
        $this->assertSame($existing, $output);
        $this->assertContains('Skipped 1 trades already in IBL5.trn', $result->messages);
    }

    public function testExportedTradeIsNotAppendedOnTheNextExport(): void
    {
        $this->stubRepo->method('getCompletedTradeItems')->willReturn($this->kagamiTurnerTrade());

        [, $first] = $this->export($this->sim08Source(), '2009-01-05');
        [$result, $second] = $this->export($first, '2009-01-12');

        $this->assertSame($first, $second);
        $this->assertSame(str_pad('6', 16), substr($second, 0, 16));
        $this->assertSame(0, $result->errors);
    }

    public function testSameSeasonDifferentYearIsAppended(): void
    {
        $this->stubRepo->method('getCompletedTradeItems')->willReturn($this->kagamiTurnerTrade());

        [, $output] = $this->export(
            $this->trnFile([str_replace('2008', '2007', self::SIM09_REC_80)]),
            '2009-01-05',
        );

        $this->assertSame(str_pad('3', 16), substr($output, 0, 16));
    }

    public function testRepeatTradeWithTheSameItemsIsAppendedWhenTheFileHoldsOnlyOneCopy(): void
    {
        $first = $this->kagamiTurnerTrade();
        $repeat = array_map(
            static fn (array $row): array => ['tradeofferid' => 8] + $row,
            $first,
        );
        $this->stubRepo->method('getCompletedTradeItems')->willReturn(array_merge($first, $repeat));

        [$result, $output] = $this->export(
            $this->trnFile([
                str_replace('  1 52008', '  1 42008', self::SIM09_REC_80),
                self::SIM09_REC_81,
            ]),
            '2009-01-05',
        );

        $this->assertSame(0, $result->errors);
        $this->assertSame(str_pad('4', 16), substr($output, 0, 16));
        $this->assertContains('Skipped 1 trades already in IBL5.trn', $result->messages);
    }

    public function testPartlyLoggedTradeWarnsAndIsNotAppended(): void
    {
        $existing = $this->trnFile([self::SIM09_REC_80]);
        $this->stubRepo->method('getCompletedTradeItems')->willReturn($this->kagamiTurnerTrade());

        [$result, $output] = $this->export($existing, '2009-01-05');

        $this->assertSame($existing, $output);
        $this->assertNotSame([], array_filter(
            $result->messages,
            static fn (string $m): bool => str_starts_with($m, 'WARNING: trade offer 7 is only partly'),
        ));
    }

    public function testSideWithMoreThanFiveItemsInterleavesChunksLikeJsb(): void
    {
        $rows = [];
        for ($pid = 101; $pid <= 106; $pid++) {
            $rows[] = $this->row(9, $pid, '1', 'Bucks', 'Magic');
        }
        $rows[] = $this->row(9, 200, '1', 'Magic', 'Bucks');
        $this->stubRepo->method('getCompletedTradeItems')->willReturn($rows);

        [$result, $output] = $this->export($this->trnFile([]), '1997-02-12');

        $this->assertSame(0, $result->errors);
        $this->assertSame(str_pad('3', 16), substr($output, 0, 16));
        $this->assertSame(
            '  2121996 20     6     5   1010     6     5   1020     6     5   1030     6     5   1040     6     5   105',
            rtrim(substr($output, 16, 128)),
        );
        $this->assertSame('  2121996 20     5     6   200', rtrim(substr($output, 16 + 128, 128)));
        $this->assertSame('  2121996 20     6     5   106', rtrim(substr($output, 16 + 256, 128)));
    }

    public function testCashItemsAreLeftOut(): void
    {
        $this->stubRepo->method('getCompletedTradeItems')->willReturn([
            $this->row(1, 0, 'cash', 'Lakers', 'Celtics'),
            $this->row(1, 100, '1', 'Celtics', 'Lakers'),
        ]);

        [, $output] = $this->export($this->trnFile([]), '2009-01-05');

        $this->assertSame(str_pad('1', 16), substr($output, 0, 16));
        $this->assertSame('  1 52008 20     1    21   100', rtrim(substr($output, 16, 128)));
    }

    public function testPickWithoutDraftPickRowWarnsAndIsNotAppended(): void
    {
        $this->stubRepo->method('getCompletedTradeItems')->willReturn([
            $this->row(3, 555, '0', 'Lakers', 'Celtics'),
        ]);

        [$result, $output] = $this->export($this->trnFile([]), '2009-01-05');

        $this->assertSame(str_pad('0', 16), substr($output, 0, 16));
        $this->assertContains(
            'WARNING: trade offer 3 has draft pick 555 with no ibl_draft_picks row, so it was not appended',
            $result->messages,
        );
    }

    public function testUnknownTeamWarnsAndIsNotAppended(): void
    {
        $this->stubRepo->method('getCompletedTradeItems')->willReturn([
            $this->row(4, 100, '1', 'Expansion', 'Heat'),
        ]);

        [$result, $output] = $this->export($this->trnFile([]), '2009-01-05');

        $this->assertSame(str_pad('0', 16), substr($output, 0, 16));
        $this->assertSame(0, $result->errors);
        $this->assertNotSame([], array_filter(
            $result->messages,
            static fn (string $m): bool => str_starts_with($m, 'WARNING: trade offer 4 names a team'),
        ));
    }

    public function testJunkSourceIsAnErrorAndWritesNothing(): void
    {
        $this->stubRepo->method('getCompletedTradeItems')->willReturn($this->kagamiTurnerTrade());

        [$result, $output] = $this->export(str_repeat('Z', TrnFileParser::FILE_SIZE), '2009-01-05');

        $this->assertSame(1, $result->errors);
        $this->assertSame('', $output);
    }

    public function testMissingSourceIsAnError(): void
    {
        $outputFile = $this->tmpPath();
        unlink($outputFile);

        $result = (new JsbExportService($this->stubRepo))->exportTrnFile(
            '/nonexistent/IBL5.trn',
            $outputFile,
            '2008-07-01',
            2008,
            new \DateTimeImmutable('2009-01-05'),
        );

        $this->assertSame(1, $result->errors);
        $this->assertFileDoesNotExist($outputFile);
    }

    // ── helpers ──────────────────────────────────────────────────

    /**
     * Run the export and return the result plus the bytes written ('' when nothing was written).
     *
     * @return array{0: \PlrParser\PlrWriteResult, 1: string}
     */
    private function export(string $source, string $jsbDate): array
    {
        $inputFile = $this->tmpPath();
        file_put_contents($inputFile, $source);
        $outputFile = $this->tmpPath();
        unlink($outputFile);

        $result = (new JsbExportService($this->stubRepo))->exportTrnFile(
            $inputFile,
            $outputFile,
            '2008-07-01',
            (int) substr($jsbDate, 0, 4) - ((int) substr($jsbDate, 5, 2) >= 7 ? 0 : 1),
            new \DateTimeImmutable($jsbDate),
        );

        $output = file_exists($outputFile) ? file_get_contents($outputFile) : '';
        $this->assertIsString($output);
        return [$result, $output];
    }

    /**
     * A JSB-layout .trn: 16-byte left-justified count, then 128-byte records at 16 + n * 128.
     *
     * @param list<string> $records Record text, right-padded to 128
     */
    private function trnFile(array $records): string
    {
        $data = str_pad((string) count($records), 16);
        foreach ($records as $record) {
            $data .= str_pad($record, 128);
        }
        return str_pad($data, TrnFileParser::FILE_SIZE);
    }

    /**
     * Four sim09 records before the trade. The last one ends with a non-space byte, so a
     * writer that places records at n * 128 instead of 16 + n * 128 overwrites it.
     */
    private function sim08Source(): string
    {
        $data = $this->trnFile([
            self::SIM09_TRADE_NOV_A,
            self::SIM09_TRADE_NOV_B,
            self::SIM09_WAIVER,
            self::SIM09_INJURY,
        ]);
        return substr_replace($data, 'X', 16 + 4 * 128 - 1, 1);
    }

    /**
     * Site rows for the Grizzlies (20) giving Kagami, Pittman and their 2009 first to the
     * Lakers (21) for Turner and Martin, in the order the site stores them.
     *
     * @return list<array{tradeofferid: int, itemid: int, itemtype: string, trade_from: string, trade_to: string, created_at: string, pick_year: int|null, pick_round: int|null, pick_teamid: int|null}>
     */
    private function kagamiTurnerTrade(): array
    {
        $pick = $this->row(7, 1152, '0', 'Grizzlies', 'Lakers');
        $pick['pick_year'] = 2009;
        $pick['pick_round'] = 1;
        $pick['pick_teamid'] = 20;

        return [
            $this->row(7, 2005, '1', 'Grizzlies', 'Lakers'),
            $pick,
            $this->row(7, 6930, '1', 'Grizzlies', 'Lakers'),
            $this->row(7, 6363, '1', 'Lakers', 'Grizzlies'),
            $this->row(7, 4500, '1', 'Lakers', 'Grizzlies'),
        ];
    }

    /**
     * @return array{tradeofferid: int, itemid: int, itemtype: string, trade_from: string, trade_to: string, created_at: string, pick_year: int|null, pick_round: int|null, pick_teamid: int|null}
     */
    private function row(int $offerId, int $itemId, string $type, string $from, string $to): array
    {
        return [
            'tradeofferid' => $offerId,
            'itemid' => $itemId,
            'itemtype' => $type,
            'trade_from' => $from,
            'trade_to' => $to,
            'created_at' => '2026-11-10 12:00:00',
            'pick_year' => null,
            'pick_round' => null,
            'pick_teamid' => null,
        ];
    }

    private function tmpPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'trn_');
        $this->assertIsString($path);
        $this->tmpFiles[] = $path;
        return $path;
    }
}
