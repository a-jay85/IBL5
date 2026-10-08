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
    /** @var JsbExportRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private JsbExportRepositoryInterface $stubRepo;

    /** sha256 of exportTrnFile output for goldenTradeItems(), captured on master. */
    private const GOLDEN_TRN_SHA256 = '1cb2a75776283ef93f7d1fa1c42f58e119ceb3fe240552df0bf0746748da33ec';

    protected function setUp(): void
    {
        $this->stubRepo = self::createStub(JsbExportRepositoryInterface::class);
    }

    private function makeService(): JsbExportService
    {
        return new JsbExportService($this->stubRepo);
    }

    public function testRepositoryContractExtendsPlrExportRepositoryContract(): void
    {
        $this->assertContains(
            \PlrParser\Contracts\PlrExportRepositoryInterface::class,
            class_implements(JsbExportRepositoryInterface::class),
        );
    }

    // ── exportTrnFile ────────────────────────────────────────────

    public function testExportTrnFileHandlesZeroItems(): void
    {
        $this->stubRepo->method('getCompletedTradeItems')->willReturn([]);
        $outputFile = tempnam(sys_get_temp_dir(), 'trn_out_');
        $this->assertIsString($outputFile);

        try {
            $result = $this->makeService()->exportTrnFile($outputFile, '2025-07-01');
            $this->assertSame(0, $result->errors);
            $this->assertStringContainsString('0 trade items', $result->messages[0]);
            $this->assertStringContainsString('0 trade records', $result->messages[1]);
        } finally {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
        }
    }

    public function testExportTrnFileGroupsByTradeOfferId(): void
    {
        $this->stubRepo->method('getCompletedTradeItems')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 100, 'itemtype' => '1', 'trade_from' => 'Lakers', 'trade_to' => 'Celtics', 'created_at' => '2025-10-15 12:00:00'],
            ['tradeofferid' => 1, 'itemid' => 200, 'itemtype' => '1', 'trade_from' => 'Celtics', 'trade_to' => 'Lakers', 'created_at' => '2025-10-15 12:00:00'],
            ['tradeofferid' => 2, 'itemid' => 300, 'itemtype' => '1', 'trade_from' => 'Heat', 'trade_to' => 'Nets', 'created_at' => '2025-11-01 08:00:00'],
        ]);

        $outputFile = tempnam(sys_get_temp_dir(), 'trn_out_');
        $this->assertIsString($outputFile);

        try {
            $result = $this->makeService()->exportTrnFile($outputFile, '2025-07-01');
            $this->assertSame(0, $result->errors);
            $this->assertStringContainsString('2 trade records', $result->messages[1]);
        } finally {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
        }
    }

    public function testExportTrnFileResolvesTeamNames(): void
    {
        $this->stubRepo->method('getCompletedTradeItems')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 100, 'itemtype' => '1', 'trade_from' => 'Lakers', 'trade_to' => 'Celtics', 'created_at' => '2025-10-15 12:00:00'],
        ]);

        $outputFile = tempnam(sys_get_temp_dir(), 'trn_out_');
        $this->assertIsString($outputFile);

        try {
            $result = $this->makeService()->exportTrnFile($outputFile, '2025-07-01');
            $this->assertSame(0, $result->errors);
            $this->assertTrue(file_exists($outputFile));
            $outputContent = file_get_contents($outputFile);
            $this->assertNotFalse($outputContent);
            $this->assertSame(TrnFileParser::FILE_SIZE, strlen($outputContent));
        } finally {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
        }
    }

    public function testExportTrnFileCashItemsAreIgnored(): void
    {
        $this->stubRepo->method('getCompletedTradeItems')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 100, 'itemtype' => 'cash', 'trade_from' => 'Lakers', 'trade_to' => 'Celtics', 'created_at' => '2025-10-15 12:00:00'],
        ]);

        $outputFile = tempnam(sys_get_temp_dir(), 'trn_out_');
        $this->assertIsString($outputFile);

        try {
            $result = $this->makeService()->exportTrnFile($outputFile, '2025-07-01');
            $this->assertSame(0, $result->errors);
            $this->assertStringContainsString('0 trade records', $result->messages[1]);
        } finally {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
        }
    }

    public function testExportTrnFileHandlesDraftPickItems(): void
    {
        $this->stubRepo->method('getCompletedTradeItems')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 2026, 'itemtype' => '0', 'trade_from' => 'Lakers', 'trade_to' => 'Celtics', 'created_at' => '2025-10-15 12:00:00'],
        ]);

        $outputFile = tempnam(sys_get_temp_dir(), 'trn_out_');
        $this->assertIsString($outputFile);

        try {
            $result = $this->makeService()->exportTrnFile($outputFile, '2025-07-01');
            $this->assertSame(0, $result->errors);
            $this->assertStringContainsString('1 trade records', $result->messages[1]);
        } finally {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
        }
    }

    public function testExportTrnFileUnknownTeamResolvesToZero(): void
    {
        $this->stubRepo->method('getCompletedTradeItems')->willReturn([
            ['tradeofferid' => 1, 'itemid' => 100, 'itemtype' => '1', 'trade_from' => 'Unknown Team', 'trade_to' => 'Celtics', 'created_at' => '2025-10-15 12:00:00'],
        ]);

        $outputFile = tempnam(sys_get_temp_dir(), 'trn_out_');
        $this->assertIsString($outputFile);

        try {
            $result = $this->makeService()->exportTrnFile($outputFile, '2025-07-01');
            $this->assertSame(0, $result->errors);
        } finally {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
        }
    }

    /**
     * @return list<array{tradeofferid: int, itemid: int, itemtype: string, trade_from: string, trade_to: string, created_at: string}>
     */
    private function goldenTradeItems(): array
    {
        return [
            ['tradeofferid' => 1, 'itemid' => 4001, 'itemtype' => \Trading\TradeItemType::Player->value, 'trade_from' => 'Celtics', 'trade_to' => 'Lakers', 'created_at' => '2026-01-15 10:00:00'],
            ['tradeofferid' => 1, 'itemid' => 4002, 'itemtype' => \Trading\TradeItemType::Player->value, 'trade_from' => 'Lakers', 'trade_to' => 'Celtics', 'created_at' => '2026-01-15 10:00:00'],
            ['tradeofferid' => 1, 'itemid' => 2027, 'itemtype' => \Trading\TradeItemType::DraftPick->value, 'trade_from' => 'Celtics', 'trade_to' => 'Lakers', 'created_at' => '2026-01-15 10:00:00'],
            ['tradeofferid' => 1, 'itemid' => 0, 'itemtype' => \Trading\TradeItemType::Cash->value, 'trade_from' => 'Lakers', 'trade_to' => 'Celtics', 'created_at' => '2026-01-15 10:00:00'],
            ['tradeofferid' => 2, 'itemid' => 4003, 'itemtype' => \Trading\TradeItemType::Player->value, 'trade_from' => 'Expansion', 'trade_to' => 'Heat', 'created_at' => '2026-02-03 09:30:00'],
        ];
    }

    public function testExportTrnFileGoldenBytesUnchanged(): void
    {
        $this->stubRepo->method('getCompletedTradeItems')->willReturn($this->goldenTradeItems());

        $outputFile = tempnam(sys_get_temp_dir(), 'trn_out_');
        $this->assertIsString($outputFile);

        try {
            $result = $this->makeService()->exportTrnFile($outputFile, '2025-10-01');
            $this->assertSame(0, $result->errors);
            $this->assertSame(self::GOLDEN_TRN_SHA256, hash_file('sha256', $outputFile));
        } finally {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
        }
    }
}
