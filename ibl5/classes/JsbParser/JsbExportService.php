<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\JsbExportRepositoryInterface;
use JsbParser\Contracts\JsbExportServiceInterface;
use PlrParser\PlrFileWriter;
use PlrParser\PlrWriteResult;

/**
 * Appends the site's completed trades to JSB's own IBL5.trn, in the record layout
 * JSB writes on Save, and skips trades the file already holds.
 * The .plr export lives in PlrParser\PlrExportService.
 */
class JsbExportService implements JsbExportServiceInterface
{
    private JsbExportRepositoryInterface $repository;

    /**
     * Maps current database team names to JSB team IDs.
     * For renamed franchises, uses current DB names (see JsbParser\Repositories\JsbLookupRepository::TEAM_NAME_ALIASES).
     *
     * @var array<string, int>
     */
    private const TEAM_NAME_TO_JSB_ID = [
        'Free Agents' => 0,
        'Celtics' => 1,
        'Heat' => 2,
        'Knicks' => 3,
        'Nets' => 4,
        'Magic' => 5,
        'Bucks' => 6,
        'Bulls' => 7,
        'Pelicans' => 8,
        'Hawks' => 9,
        'Sting' => 10,
        'Pacers' => 11,
        'Raptors' => 12,
        'Jazz' => 13,
        'Timberwolves' => 14,
        'Nuggets' => 15,
        'Aces' => 16,
        'Rockets' => 17,
        'Trailblazers' => 18,
        'Clippers' => 19,
        'Grizzlies' => 20,
        'Lakers' => 21,
        'Braves' => 22,
        'Suns' => 23,
        'Warriors' => 24,
        'Pistons' => 25,
        'Kings' => 26,
        'Bullets' => 27,
        'Mavericks' => 28,
    ];

    public function __construct(JsbExportRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * JSB packs at most this many items into one trade record.
     */
    private const ITEMS_PER_RECORD = 5;

    /**
     * @see JsbExportServiceInterface::exportTrnFile()
     */
    public function exportTrnFile(
        string $inputPath,
        string $outputPath,
        string $seasonStartDate,
        int $seasonBeginningYear,
        \DateTimeImmutable $jsbDate,
    ): PlrWriteResult {
        $result = new PlrWriteResult();

        $existing = is_file($inputPath) ? file_get_contents($inputPath) : false;
        if ($existing === false) {
            $result->addError('IBL5.trn could not be read from ' . $inputPath);
            return $result;
        }
        if (strlen($existing) !== TrnFileParser::FILE_SIZE) {
            $result->addError('IBL5.trn is ' . strlen($existing) . ' bytes, expected ' . TrnFileParser::FILE_SIZE);
            return $result;
        }
        try {
            $existingCount = TrnFileWriter::readJsbRecordCount($existing);
        } catch (\RuntimeException $e) {
            $result->addError('IBL5.trn: ' . $e->getMessage());
            return $result;
        }
        $result->addMessage('IBL5.trn holds ' . $existingCount . ' records');

        $knownKeys = self::existingTradeKeys($existing, $existingCount);

        $tradeItems = $this->repository->getCompletedTradeItems($seasonStartDate);
        $result->addMessage('Found ' . count($tradeItems) . ' trade items in database');

        $month = (int) $jsbDate->format('n');
        $day = (int) $jsbDate->format('j');

        $records = [];
        $appendedTrades = 0;
        $skippedTrades = 0;
        foreach ($this->groupTradeItems($tradeItems) as $offerId => $group) {
            $chunks = $this->buildTradeChunks($offerId, $group, $result);
            if ($chunks === null || $chunks === []) {
                continue;
            }

            $keys = array_map(
                static fn (array $chunk): string => self::tradeKey($seasonBeginningYear, $chunk),
                $chunks,
            );
            $present = array_filter($keys, static fn (string $key): bool => isset($knownKeys[$key]));
            if (count($present) === count($keys)) {
                $skippedTrades++;
                continue;
            }
            if ($present !== []) {
                $result->addMessage(
                    'WARNING: trade offer ' . $offerId . ' is only partly in IBL5.trn, so it was not appended. Check it in JSB by hand.'
                );
                continue;
            }

            foreach ($chunks as $index => $chunk) {
                $records[] = TrnFileWriter::buildTradeRecord($month, $day, $seasonBeginningYear, $chunk);
                $knownKeys[$keys[$index]] = true;
            }
            $appendedTrades++;
        }

        try {
            $output = TrnFileWriter::appendRecords($existing, $records);
        } catch (\RuntimeException $e) {
            $result->addError($e->getMessage());
            return $result;
        }

        $result->addMessage('Skipped ' . $skippedTrades . ' trades already in IBL5.trn');
        $result->addMessage(
            'Appended ' . count($records) . ' trade records for ' . $appendedTrades . ' trades, dated '
            . $jsbDate->format('Y-m-d') . '; IBL5.trn now holds ' . ($existingCount + count($records)) . ' records'
        );

        PlrFileWriter::writeFile($output, $outputPath);
        $result->addMessage('Wrote ' . strlen($output) . ' bytes to ' . $outputPath);

        return $result;
    }

    /**
     * Split one trade into JSB's record items: one list per giving team, in chunks of five.
     *
     * JSB writes a record per (from, to) side, player items first, then draft picks.
     * A side with more than five items continues in later records, and JSB interleaves
     * the sides chunk by chunk (96-97 sim15, records 172-177).
     *
     * @param list<array{tradeofferid: int, itemid: int, itemtype: string, trade_from: string, trade_to: string, created_at: string, pick_year: int|null, pick_round: int|null, pick_teamid: int|null}> $group
     * @return list<list<array{marker: int, from_team?: int, to_team?: int, player_id?: int, draft_year?: int, round?: int, original_team?: int}>>|null Null when an item cannot be written
     */
    private function buildTradeChunks(int $offerId, array $group, PlrWriteResult $result): ?array
    {
        /** @var array<string, array{players: list<array{marker: int, from_team: int, to_team: int, player_id: int}>, picks: list<array{marker: int, draft_year: int, round: int, original_team: int}>}> $sides */
        $sides = [];
        foreach ($group as $item) {
            if ($item['itemtype'] === \Trading\TradeItemType::Cash->value) {
                continue; // .trn has no cash item
            }
            $fromId = $this->resolveTeamToJsbId($item['trade_from']);
            $toId = $this->resolveTeamToJsbId($item['trade_to']);
            if ($fromId === 0 || $toId === 0) {
                $result->addMessage(
                    'WARNING: trade offer ' . $offerId . ' names a team JSB does not know ('
                    . $item['trade_from'] . ' to ' . $item['trade_to'] . '), so it was not appended'
                );
                return null;
            }
            $sideKey = $fromId . '>' . $toId;
            $sides[$sideKey] ??= ['players' => [], 'picks' => []];

            if ($item['itemtype'] === \Trading\TradeItemType::Player->value) {
                $sides[$sideKey]['players'][] = [
                    'marker' => TrnFileParser::TRADE_MARKER_PLAYER,
                    'from_team' => $fromId,
                    'to_team' => $toId,
                    'player_id' => $item['itemid'],
                ];
            } elseif ($item['itemtype'] === \Trading\TradeItemType::DraftPick->value) {
                if ($item['pick_year'] === null || $item['pick_round'] === null || $item['pick_teamid'] === null) {
                    $result->addMessage(
                        'WARNING: trade offer ' . $offerId . ' has draft pick ' . $item['itemid']
                        . ' with no ibl_draft_picks row, so it was not appended'
                    );
                    return null;
                }
                $sides[$sideKey]['picks'][] = [
                    'marker' => TrnFileParser::TRADE_MARKER_DRAFT_PICK,
                    'draft_year' => $item['pick_year'],
                    'round' => $item['pick_round'],
                    'original_team' => $item['pick_teamid'],
                ];
            }
        }

        $sideChunks = [];
        foreach ($sides as $side) {
            $items = array_merge($side['players'], $side['picks']);
            if ($items !== []) {
                $sideChunks[] = array_chunk($items, self::ITEMS_PER_RECORD);
            }
        }

        $chunks = [];
        $maxChunks = $sideChunks === [] ? 0 : max(array_map('count', $sideChunks));
        for ($i = 0; $i < $maxChunks; $i++) {
            foreach ($sideChunks as $sideList) {
                if (isset($sideList[$i])) {
                    $chunks[] = $sideList[$i];
                }
            }
        }

        return $chunks;
    }

    /**
     * Dedupe key for one new trade record: season year plus its items, in any order.
     * Month and day are left out because JSB stamps its own league date.
     *
     * @param list<array{marker: int, from_team?: int, to_team?: int, player_id?: int, draft_year?: int, round?: int, original_team?: int}> $items
     */
    private static function tradeKey(int $seasonBeginningYear, array $items): string
    {
        $record = TrnFileWriter::buildTradeRecord(1, 1, $seasonBeginningYear, $items);
        return self::recordKey(substr($record, TrnFileParser::JSB_HEADER_SIZE));
    }

    /**
     * Dedupe key for a JSB-layout trade record: year bytes plus the sorted 19-byte items.
     */
    private static function recordKey(string $jsbRecord): string
    {
        $items = [];
        for ($i = 0; $i < self::ITEMS_PER_RECORD; $i++) {
            $item = substr($jsbRecord, 11 + $i * TrnFileParser::TRADE_ITEM_SIZE, TrnFileParser::TRADE_ITEM_SIZE);
            if (trim($item) !== '') {
                $items[] = $item;
            }
        }
        sort($items);
        return substr($jsbRecord, 5, 4) . '|' . implode('|', $items);
    }

    /**
     * Keys of every trade record already in a JSB .trn file.
     *
     * @return array<string, true>
     */
    private static function existingTradeKeys(string $data, int $count): array
    {
        $keys = [];
        for ($n = 0; $n < $count; $n++) {
            $jsbRecord = substr(
                $data,
                TrnFileParser::JSB_HEADER_SIZE + $n * TrnFileParser::RECORD_SIZE,
                TrnFileParser::RECORD_SIZE,
            );
            if (substr($jsbRecord, 10, 1) === (string) TrnFileParser::TYPE_TRADE) {
                $keys[self::recordKey($jsbRecord)] = true;
            }
        }
        return $keys;
    }

    /**
     * Group trade items by tradeofferid.
     *
     * @param list<array{tradeofferid: int, itemid: int, itemtype: string, trade_from: string, trade_to: string, created_at: string, pick_year: int|null, pick_round: int|null, pick_teamid: int|null}> $items
     * @return array<int, list<array{tradeofferid: int, itemid: int, itemtype: string, trade_from: string, trade_to: string, created_at: string, pick_year: int|null, pick_round: int|null, pick_teamid: int|null}>>
     */
    private function groupTradeItems(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $groups[$item['tradeofferid']][] = $item;
        }
        return $groups;
    }

    /**
     * Resolve a database team name to a JSB team ID.
     */
    private function resolveTeamToJsbId(string $teamName): int
    {
        return self::TEAM_NAME_TO_JSB_ID[$teamName] ?? 0;
    }
}
