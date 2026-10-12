<?php

declare(strict_types=1);

namespace Tests\JsbParser\Importers;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\Importers\TrnImporter;
use JsbParser\TrnFileParser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\Importers\TrnImporter
 */
final class TrnImporterTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $captured = [];

    /**
     * Build a synthetic 64,000-byte .trn file with known records.
     *
     * @param int $recordCount Record count to store in header
     * @param list<string> $records Raw 128-byte records (injected starting at record 0)
     */
    private function buildTrnFile(int $recordCount, array $records = []): string
    {
        // Start with 64,000 bytes of spaces
        $data = str_repeat(' ', TrnFileParser::FILE_SIZE);

        // Insert records at their positions first
        foreach ($records as $index => $record) {
            $offset = $index * TrnFileParser::RECORD_SIZE;
            $data = substr_replace($data, $record, $offset, TrnFileParser::RECORD_SIZE);
        }

        // Write record count in header area AFTER records (so it's not overwritten by record 0)
        $countStr = str_pad((string) $recordCount, TrnFileParser::HEADER_AREA_SIZE, ' ', STR_PAD_LEFT);
        $data = substr_replace($data, $countStr, 0, TrnFileParser::HEADER_AREA_SIZE);

        return $data;
    }

    /**
     * Build an injury record (type 1) at a specific index position.
     *
     * @param int $month Transaction month
     * @param int $day Transaction day
     * @param int $year Transaction year
     * @param int $pid Player ID
     * @param int $teamId Team ID
     * @param int $gamesMissed Games missed
     * @param string $injuryDesc Injury description
     */
    private function buildInjuryRecord(
        int $month,
        int $day,
        int $year,
        int $pid,
        int $teamId,
        int $gamesMissed,
        string $injuryDesc,
    ): string {
        $record = str_repeat(' ', TrnFileParser::RECORD_SIZE);

        // Common header at offsets 17-26
        $record = substr_replace($record, str_pad((string) $month, 2, ' ', STR_PAD_LEFT), 17, 2);
        $record = substr_replace($record, str_pad((string) $day, 2, ' ', STR_PAD_LEFT), 19, 2);
        $record = substr_replace($record, str_pad((string) $year, 4, ' ', STR_PAD_LEFT), 21, 4);
        // offset 25 is a spacer
        $record = substr_replace($record, (string) TrnFileParser::TYPE_INJURY, 26, 1);

        // Injury-specific fields
        $record = substr_replace($record, str_pad((string) $pid, 4, ' ', STR_PAD_LEFT), 29, 4);
        $record = substr_replace($record, str_pad((string) $teamId, 2, ' ', STR_PAD_LEFT), 33, 2);
        $record = substr_replace($record, str_pad((string) $gamesMissed, 4, ' ', STR_PAD_LEFT), 35, 4);
        $record = substr_replace($record, str_pad($injuryDesc, 57), 39, 57);

        return $record;
    }

    /**
     * Build a trade record (type 2) with player move items.
     *
     * @param int $month Transaction month
     * @param int $day Transaction day
     * @param int $year Transaction year
     * @param list<array{from_team: int, to_team: int, player_id: int}> $items Player trade items
     */
    private function buildTradeRecord(int $month, int $day, int $year, array $items): string
    {
        $record = str_repeat(' ', TrnFileParser::RECORD_SIZE);

        // Common header
        $record = substr_replace($record, str_pad((string) $month, 2, ' ', STR_PAD_LEFT), 17, 2);
        $record = substr_replace($record, str_pad((string) $day, 2, ' ', STR_PAD_LEFT), 19, 2);
        $record = substr_replace($record, str_pad((string) $year, 4, ' ', STR_PAD_LEFT), 21, 4);
        $record = substr_replace($record, (string) TrnFileParser::TYPE_TRADE, 26, 1);

        // Trade items starting at offset 27
        $tradeOffset = 27;
        foreach ($items as $item) {
            // marker(1) + from_team(6) + to_team(6) + player_id(6) = 19 bytes
            $itemStr = '0'; // marker = player
            $itemStr .= str_pad((string) $item['from_team'], 6, ' ', STR_PAD_LEFT);
            $itemStr .= str_pad((string) $item['to_team'], 6, ' ', STR_PAD_LEFT);
            $itemStr .= str_pad((string) $item['player_id'], 6, ' ', STR_PAD_LEFT);
            $record = substr_replace($record, $itemStr, $tradeOffset, TrnFileParser::TRADE_ITEM_SIZE);
            $tradeOffset += TrnFileParser::TRADE_ITEM_SIZE;
        }

        return $record;
    }

    /**
     * Build a waiver record (type 3 or 4).
     */
    private function buildWaiverRecord(int $month, int $day, int $year, int $type, int $teamId, int $pid): string
    {
        $record = str_repeat(' ', TrnFileParser::RECORD_SIZE);

        // Common header
        $record = substr_replace($record, str_pad((string) $month, 2, ' ', STR_PAD_LEFT), 17, 2);
        $record = substr_replace($record, str_pad((string) $day, 2, ' ', STR_PAD_LEFT), 19, 2);
        $record = substr_replace($record, str_pad((string) $year, 4, ' ', STR_PAD_LEFT), 21, 4);
        $record = substr_replace($record, (string) $type, 26, 1);

        // Waiver fields: team_id at 27-28, pid at 31-34
        $record = substr_replace($record, str_pad((string) $teamId, 2, ' ', STR_PAD_LEFT), 27, 2);
        $record = substr_replace($record, str_pad((string) $pid, 4, ' ', STR_PAD_LEFT), 31, 4);

        return $record;
    }

    /**
     * Build a trade record with draft pick items (marker=1).
     *
     * @param int $month Transaction month
     * @param int $day Transaction day
     * @param int $year Transaction year
     * @param list<array{draft_year: int, from_team: int, to_team: int}> $items Draft pick trade items
     */
    private function buildDraftPickTradeRecord(int $month, int $day, int $year, array $items): string
    {
        $record = str_repeat(' ', TrnFileParser::RECORD_SIZE);

        // Common header
        $record = substr_replace($record, str_pad((string) $month, 2, ' ', STR_PAD_LEFT), 17, 2);
        $record = substr_replace($record, str_pad((string) $day, 2, ' ', STR_PAD_LEFT), 19, 2);
        $record = substr_replace($record, str_pad((string) $year, 4, ' ', STR_PAD_LEFT), 21, 4);
        $record = substr_replace($record, (string) TrnFileParser::TYPE_TRADE, 26, 1);

        // Draft pick items starting at offset 27
        $tradeOffset = 27;
        foreach ($items as $item) {
            // marker(1) + draft_year(6) + from_team(6) + to_team(6) = 19 bytes
            $itemStr = '1'; // marker = draft pick
            $itemStr .= str_pad((string) $item['draft_year'], 6, ' ', STR_PAD_LEFT);
            $itemStr .= str_pad((string) $item['from_team'], 6, ' ', STR_PAD_LEFT);
            $itemStr .= str_pad((string) $item['to_team'], 6, ' ', STR_PAD_LEFT);
            $record = substr_replace($record, $itemStr, $tradeOffset, TrnFileParser::TRADE_ITEM_SIZE);
            $tradeOffset += TrnFileParser::TRADE_ITEM_SIZE;
        }

        return $record;
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

    private function repositoryWithNames(): JsbImportRepositoryInterface&\PHPUnit\Framework\MockObject\Stub
    {
        $repo = $this->repositoryCapturing('upsertTransaction');
        $repo->method('fetchMaxTradeGroupId')->willReturn(41);
        $repo->method('getPlayerName')->willReturnMap([
            [5678, 'Test Injured'],
            [2345, 'Trade Player One'],
            [6789, 'Trade Player Two'],
            [1111, 'Claimed Player'],
            [2222, 'Released Player'],
        ]);
        return $repo;
    }

    public function testImportSendsExactInjuryRecord(): void
    {
        $data = $this->buildTrnFile(1, [$this->buildInjuryRecord(11, 3, 2006, 5678, 12, 8, 'Torn ACL')]);

        $result = (new TrnImporter($this->repositoryWithNames()))->import($data, 'trn.trn');

        $this->assertSame(1, $result->inserted);
        $this->assertCount(1, $this->captured);
        $this->assertSame([
            'season_year' => 2007,
            'transaction_month' => 11,
            'transaction_day' => 3,
            'transaction_type' => TrnFileParser::TYPE_INJURY,
            'pid' => 5678,
            'player_name' => 'Test Injured',
            'from_teamid' => 12,
            'to_teamid' => 0,
            'injury_games_missed' => 8,
            'injury_description' => 'Torn ACL',
            'trade_group_id' => null,
            'is_draft_pick' => 0,
            'draft_pick_year' => null,
            'source_file' => 'trn.trn',
        ], $this->captured[0]);
    }

    public function testImportSendsExactTradeRecordsWithSharedGroupId(): void
    {
        $data = $this->buildTrnFile(1, [$this->buildTradeRecord(11, 3, 2006, [
            ['from_team' => 5, 'to_team' => 10, 'player_id' => 2345],
            ['from_team' => 10, 'to_team' => 5, 'player_id' => 6789],
        ])]);

        (new TrnImporter($this->repositoryWithNames()))->import($data, 'trn.trn');

        $this->assertCount(2, $this->captured);
        $this->assertSame([
            'season_year' => 2007,
            'transaction_month' => 11,
            'transaction_day' => 3,
            'transaction_type' => TrnFileParser::TYPE_TRADE,
            'pid' => 2345,
            'player_name' => 'Trade Player One',
            'from_teamid' => 5,
            'to_teamid' => 10,
            'injury_games_missed' => null,
            'injury_description' => null,
            'trade_group_id' => 42,
            'is_draft_pick' => 0,
            'draft_pick_year' => null,
            'source_file' => 'trn.trn',
        ], $this->captured[0]);
        $this->assertSame([
            'season_year' => 2007,
            'transaction_month' => 11,
            'transaction_day' => 3,
            'transaction_type' => TrnFileParser::TYPE_TRADE,
            'pid' => 6789,
            'player_name' => 'Trade Player Two',
            'from_teamid' => 10,
            'to_teamid' => 5,
            'injury_games_missed' => null,
            'injury_description' => null,
            'trade_group_id' => 42,
            'is_draft_pick' => 0,
            'draft_pick_year' => null,
            'source_file' => 'trn.trn',
        ], $this->captured[1]);
    }

    public function testImportFlagsDraftPickTradeItems(): void
    {
        $data = $this->buildTrnFile(1, [$this->buildDraftPickTradeRecord(11, 3, 2006, [
            ['draft_year' => 2008, 'from_team' => 5, 'to_team' => 10],
            ['draft_year' => 2009, 'from_team' => 10, 'to_team' => 5],
        ])]);

        (new TrnImporter($this->repositoryWithNames()))->import($data, 'trn.trn');

        $this->assertCount(2, $this->captured);
        $this->assertSame([
            'season_year' => 2007,
            'transaction_month' => 11,
            'transaction_day' => 3,
            'transaction_type' => TrnFileParser::TYPE_TRADE,
            'pid' => 0,
            'player_name' => null,
            'from_teamid' => 5,
            'to_teamid' => 10,
            'injury_games_missed' => null,
            'injury_description' => null,
            'trade_group_id' => 42,
            'is_draft_pick' => 1,
            'draft_pick_year' => 2008,
            'source_file' => 'trn.trn',
        ], $this->captured[0]);
        $this->assertSame(1, $this->captured[1]['is_draft_pick']);
        $this->assertSame(2009, $this->captured[1]['draft_pick_year']);
        $this->assertSame(42, $this->captured[1]['trade_group_id']);
    }

    public function testImportSendsWaiverClaimToTeamAndReleaseFromTeam(): void
    {
        $data = $this->buildTrnFile(2, [
            $this->buildWaiverRecord(11, 3, 2006, TrnFileParser::TYPE_WAIVER_CLAIM, 7, 1111),
            $this->buildWaiverRecord(11, 3, 2006, TrnFileParser::TYPE_WAIVER_RELEASE, 9, 2222),
        ]);

        (new TrnImporter($this->repositoryWithNames()))->import($data, 'trn.trn');

        $this->assertCount(2, $this->captured);
        $this->assertSame([
            'season_year' => 2007,
            'transaction_month' => 11,
            'transaction_day' => 3,
            'transaction_type' => TrnFileParser::TYPE_WAIVER_CLAIM,
            'pid' => 1111,
            'player_name' => 'Claimed Player',
            'from_teamid' => 0,
            'to_teamid' => 7,
            'injury_games_missed' => null,
            'injury_description' => null,
            'trade_group_id' => null,
            'is_draft_pick' => 0,
            'draft_pick_year' => null,
            'source_file' => 'trn.trn',
        ], $this->captured[0]);
        $this->assertSame([
            'season_year' => 2007,
            'transaction_month' => 11,
            'transaction_day' => 3,
            'transaction_type' => TrnFileParser::TYPE_WAIVER_RELEASE,
            'pid' => 2222,
            'player_name' => 'Released Player',
            'from_teamid' => 9,
            'to_teamid' => 0,
            'injury_games_missed' => null,
            'injury_description' => null,
            'trade_group_id' => null,
            'is_draft_pick' => 0,
            'draft_pick_year' => null,
            'source_file' => 'trn.trn',
        ], $this->captured[1]);
    }

    public function testImportStartsTradeGroupAtOneWhenMaxLookupThrows(): void
    {
        $repo = $this->repositoryCapturing('upsertTransaction');
        $repo->method('fetchMaxTradeGroupId')->willThrowException(new \RuntimeException('no table'));
        $repo->method('getPlayerName')->willReturn(null);
        $data = $this->buildTrnFile(1, [$this->buildTradeRecord(11, 3, 2006, [
            ['from_team' => 5, 'to_team' => 10, 'player_id' => 2345],
            ['from_team' => 10, 'to_team' => 5, 'player_id' => 6789],
        ])]);

        (new TrnImporter($repo))->import($data, 'trn.trn');

        $this->assertCount(2, $this->captured);
        $this->assertSame(1, $this->captured[0]['trade_group_id']);
        $this->assertSame(1, $this->captured[1]['trade_group_id']);
    }

    public function testImportLeavesPlayerNameNullWhenPlayerUnknown(): void
    {
        $repo = $this->repositoryCapturing('upsertTransaction');
        $repo->method('fetchMaxTradeGroupId')->willReturn(0);
        $repo->method('getPlayerName')->willReturn(null);
        $data = $this->buildTrnFile(1, [$this->buildInjuryRecord(11, 3, 2006, 5678, 12, 8, 'Torn ACL')]);

        (new TrnImporter($repo))->import($data, 'trn.trn');

        $this->assertCount(1, $this->captured);
        $this->assertNull($this->captured[0]['player_name']);
        $this->assertSame(5678, $this->captured[0]['pid']);
    }

    public function testImportReportsParseFailureAndSkipsRepository(): void
    {
        $result = (new TrnImporter($this->repositoryWithNames()))->import('short');

        $this->assertSame(1, $result->errors);
        $this->assertStringContainsString('TRN parse failed: ', implode("\n", $result->messages));
        $this->assertSame([], $this->captured);
    }

    public function testImportConvertsRepositoryExceptionToError(): void
    {
        $repo = self::createStub(JsbImportRepositoryInterface::class);
        $repo->method('fetchMaxTradeGroupId')->willReturn(0);
        $repo->method('getPlayerName')->willReturn(null);
        $repo->method('upsertTransaction')->willThrowException(new \RuntimeException('db down'));
        $data = $this->buildTrnFile(1, [$this->buildInjuryRecord(11, 3, 2006, 5678, 12, 8, 'Torn ACL')]);

        $result = (new TrnImporter($repo))->import($data, 'trn.trn');

        $this->assertSame(1, $result->errors);
        $this->assertSame(0, $result->inserted);
        $this->assertStringContainsString('Injury transaction upsert failed: db down', implode("\n", $result->messages));
    }

    public function testImportFileReportsMissingFile(): void
    {
        $result = (new TrnImporter(self::createStub(JsbImportRepositoryInterface::class)))->importFile('/nonexistent/x.trn');

        $this->assertSame(1, $result->errors);
        $this->assertStringContainsString('TRN file not found: /nonexistent/x.trn', implode("\n", $result->messages));
    }
}
