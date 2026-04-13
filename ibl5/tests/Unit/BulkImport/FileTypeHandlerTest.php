<?php

declare(strict_types=1);

namespace Tests\Unit\BulkImport;

use Boxscore\BoxscoreProcessor;
use BulkImport\FileTypeHandler;
use BulkImport\ImportEntry;
use BulkImport\JsbFileType;
use JsbParser\Contracts\JsbImportServiceInterface;
use JsbParser\JsbImportResult;
use LeagueConfig\LeagueConfigService;
use PHPUnit\Framework\TestCase;
use PlrParser\Contracts\PlrParserServiceInterface;
use PlrParser\PlrOrdinalMap;
use PlrParser\PlrParseResult;

/**
 * @covers \BulkImport\FileTypeHandler
 */
class FileTypeHandlerTest extends TestCase
{
    /** @var JsbImportServiceInterface&\PHPUnit\Framework\MockObject\Stub */
    private JsbImportServiceInterface $stubJsb;

    /** @var BoxscoreProcessor&\PHPUnit\Framework\MockObject\Stub */
    private BoxscoreProcessor $stubBoxscore;

    /** @var PlrParserServiceInterface&\PHPUnit\Framework\MockObject\Stub */
    private PlrParserServiceInterface $stubPlr;

    /** @var LeagueConfigService&\PHPUnit\Framework\MockObject\Stub */
    private LeagueConfigService $stubLge;

    private FileTypeHandler $handler;

    protected function setUp(): void
    {
        $this->stubJsb = $this->createStub(JsbImportServiceInterface::class);
        $this->stubBoxscore = $this->createStub(BoxscoreProcessor::class);
        $this->stubPlr = $this->createStub(PlrParserServiceInterface::class);
        $this->stubLge = $this->createStub(LeagueConfigService::class);

        $this->handler = new FileTypeHandler(
            $this->stubJsb,
            $this->stubBoxscore,
            $this->stubPlr,
            $this->stubLge,
        );
    }

    private function makeEntry(
        int $year = 2007,
        string $phase = 'Regular Season/Playoffs',
        string $sourceLabel = 'test-archive',
    ): ImportEntry {
        return new ImportEntry(
            path: '/tmp/test',
            label: 'test',
            year: $year,
            phase: $phase,
            archivePath: null,
            sourceLabel: $sourceLabel,
        );
    }

    private function makeResult(int $inserted = 5): JsbImportResult
    {
        $result = new JsbImportResult();
        $result->inserted = $inserted;
        return $result;
    }

    // --- Simple dispatch tests ---

    public function testTrnDispatchesToProcessTrnFile(): void
    {
        $expected = $this->makeResult(10);
        $this->stubJsb->method('processTrnFile')->willReturn($expected);

        $result = $this->handler->process(JsbFileType::Trn, '/tmp/IBL5.trn', $this->makeEntry());

        $this->assertSame(10, $result->inserted);
    }

    public function testCarDispatchesToProcessCarFile(): void
    {
        $expected = $this->makeResult(20);
        $this->stubJsb->method('processCarFile')->willReturn($expected);

        $result = $this->handler->process(JsbFileType::Car, '/tmp/IBL5.car', $this->makeEntry());

        $this->assertSame(20, $result->inserted);
    }

    public function testHisDispatchesToProcessHisFile(): void
    {
        $expected = $this->makeResult(15);
        $this->stubJsb->method('processHisFile')->willReturn($expected);

        $result = $this->handler->process(JsbFileType::His, '/tmp/IBL5.his', $this->makeEntry());

        $this->assertSame(15, $result->inserted);
    }

    public function testAswDispatchesToProcessAswFile(): void
    {
        $expected = $this->makeResult(8);
        $this->stubJsb->method('processAswFile')->willReturn($expected);

        $result = $this->handler->process(JsbFileType::Asw, '/tmp/IBL5.asw', $this->makeEntry());

        $this->assertSame(8, $result->inserted);
    }

    public function testRcbDispatchesToProcessRcbFile(): void
    {
        $expected = $this->makeResult(12);
        $this->stubJsb->method('processRcbFile')->willReturn($expected);

        $result = $this->handler->process(JsbFileType::Rcb, '/tmp/IBL5.rcb', $this->makeEntry());

        $this->assertSame(12, $result->inserted);
    }

    public function testDraDispatchesToProcessDraFile(): void
    {
        $expected = $this->makeResult(30);
        $this->stubJsb->method('processDraFile')->willReturn($expected);

        $result = $this->handler->process(JsbFileType::Dra, '/tmp/IBL5.dra', $this->makeEntry());

        $this->assertSame(30, $result->inserted);
    }

    public function testRetDispatchesToProcessRetFile(): void
    {
        $expected = $this->makeResult(7);
        $this->stubJsb->method('processRetFile')->willReturn($expected);

        $result = $this->handler->process(JsbFileType::Ret, '/tmp/IBL5.ret', $this->makeEntry());

        $this->assertSame(7, $result->inserted);
    }

    public function testHofDispatchesToProcessHofFile(): void
    {
        $expected = $this->makeResult(3);
        $this->stubJsb->method('processHofFile')->willReturn($expected);

        $result = $this->handler->process(JsbFileType::Hof, '/tmp/IBL5.hof', $this->makeEntry());

        $this->assertSame(3, $result->inserted);
    }

    // --- .awa special case ---

    public function testAwaReturnsErrorWhenCompanionCarMissing(): void
    {
        // /tmp/nonexistent/IBL5.car won't exist
        $result = $this->handler->process(
            JsbFileType::Awa,
            '/tmp/nonexistent/IBL5.awa',
            $this->makeEntry(),
        );

        $this->assertSame(1, $result->errors);
        $this->assertStringContainsString('Companion IBL5.car not found', $result->messages[0]);
    }

    // --- .sco special case ---

    public function testScoCallsProcessScoFile(): void
    {
        $this->stubBoxscore->method('processScoFile')->willReturn([
            'success' => true,
            'gamesInserted' => 14,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'linesProcessed' => 100,
            'messages' => [],
        ]);
        $this->stubBoxscore->method('processAllStarGames')->willReturn([
            'success' => true,
            'messages' => [],
        ]);

        $result = $this->handler->process(
            JsbFileType::Sco,
            '/tmp/IBL5.sco',
            $this->makeEntry(),
        );

        $this->assertSame(14, $result->inserted);
    }

    public function testScoProcessesAllStarGamesForSeasonEnd(): void
    {
        $this->stubBoxscore->method('processScoFile')->willReturn([
            'success' => true,
            'gamesInserted' => 0,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'linesProcessed' => 0,
            'messages' => [],
        ]);
        $this->stubBoxscore->method('processAllStarGames')->willReturn([
            'success' => true,
            'messages' => ['Rising Stars: inserted (20 lines).'],
        ]);

        $result = $this->handler->process(
            JsbFileType::Sco,
            '/tmp/IBL5.sco',
            $this->makeEntry(phase: 'Regular Season/Playoffs'),
        );

        $this->assertCount(1, $result->messages);
        $this->assertStringContainsString('All-Star:', $result->messages[0]);
    }

    public function testScoSkipsAllStarGamesForHeatPhase(): void
    {
        $this->stubBoxscore->method('processScoFile')->willReturn([
            'success' => true,
            'gamesInserted' => 5,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'linesProcessed' => 50,
            'messages' => [],
        ]);

        $result = $this->handler->process(
            JsbFileType::Sco,
            '/tmp/IBL5.sco',
            $this->makeEntry(phase: 'HEAT'),
        );

        $this->assertSame(5, $result->inserted);
        // No All-Star messages since HEAT phase
        $this->assertSame([], $result->messages);
    }

    // --- .lge special case ---

    public function testLgeBridgesSuccessResult(): void
    {
        $this->stubLge->method('processLgeFile')->willReturn([
            'success' => true,
            'season_ending_year' => 2007,
            'teams_stored' => 28,
            'messages' => [],
        ]);

        $result = $this->handler->process(JsbFileType::Lge, '/tmp/IBL5.lge', $this->makeEntry());

        $this->assertSame(28, $result->inserted);
    }

    public function testLgeBridgesFailureResult(): void
    {
        $this->stubLge->method('processLgeFile')->willReturn([
            'success' => false,
            'season_ending_year' => 0,
            'teams_stored' => 0,
            'messages' => [],
            'error' => 'Parse failed',
        ]);

        $result = $this->handler->process(JsbFileType::Lge, '/tmp/IBL5.lge', $this->makeEntry());

        $this->assertSame(1, $result->errors);
        $this->assertStringContainsString('Parse failed', $result->messages[0]);
    }

    // --- .plr special case ---

    public function testPlrDispatchesToPlrServiceInSnapshotMode(): void
    {
        $plrResult = new PlrParseResult();
        $plrResult->playersUpserted = 450;
        $this->stubPlr->method('processPlrFileForYear')->willReturn($plrResult);

        $result = $this->handler->process(JsbFileType::Plr, '/tmp/IBL5.plr', $this->makeEntry());

        $this->assertSame(450, $result->inserted);
    }

    // --- .plb special case ---

    public function testPlbUsesPlrOrdinalMapFromEntry(): void
    {
        $map = PlrOrdinalMap::empty();
        $entry = new ImportEntry(
            path: '/tmp',
            label: 'test',
            year: 2007,
            phase: 'Regular Season/Playoffs',
            archivePath: null,
            sourceLabel: 'test-archive',
            plrMap: $map,
            simNumber: 5,
        );

        $expected = $this->makeResult(100);
        $this->stubJsb->method('processPlbFile')->willReturn($expected);

        $result = $this->handler->process(JsbFileType::Plb, '/tmp/IBL5.plb', $entry);

        $this->assertSame(100, $result->inserted);
    }

    public function testPlbUsesEmptyMapWhenEntryHasNone(): void
    {
        $expected = $this->makeResult(50);
        $this->stubJsb->method('processPlbFile')->willReturn($expected);

        $result = $this->handler->process(JsbFileType::Plb, '/tmp/IBL5.plb', $this->makeEntry());

        $this->assertSame(50, $result->inserted);
    }
}
