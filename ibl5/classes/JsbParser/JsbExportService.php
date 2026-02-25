<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\JsbExportServiceInterface;

/**
 * Orchestrator for JSB file export operations.
 *
 * Coordinates reading database state via JsbExportRepository and writing
 * it to .plr and .trn files via PlrFileWriter and TrnFileWriter.
 */
class JsbExportService implements JsbExportServiceInterface
{
    private JsbExportRepository $repository;

    /**
     * Map of database field names to PlrFileWriter field names.
     * The dc_ prefix fields in the DB map to non-prefixed depth chart fields in the .plr file.
     *
     * @var array<string, string>
     */
    private const DB_TO_PLR_FIELD_MAP = [
        'tid' => 'tid',
        'dc_PGDepth' => 'PGDepth',
        'dc_SGDepth' => 'SGDepth',
        'dc_SFDepth' => 'SFDepth',
        'dc_PFDepth' => 'PFDepth',
        'dc_CDepth' => 'CDepth',
        'dc_active' => 'active',
        'injured' => 'injuryDaysLeft',
        'exp' => 'exp',
        'bird' => 'bird',
        'cy' => 'cy',
        'cyt' => 'cyt',
        'cy1' => 'cy1',
        'cy2' => 'cy2',
        'cy3' => 'cy3',
        'cy4' => 'cy4',
        'cy5' => 'cy5',
        'cy6' => 'cy6',
    ];

    /**
     * Reverse mapping of JSB team names to their IDs.
     * Built from JsbImportRepository::JSB_TEAM_NAMES for resolving team names to JSB IDs.
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

    public function __construct(JsbExportRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see JsbExportServiceInterface::exportPlrFile()
     */
    public function exportPlrFile(string $inputPath, string $outputPath): PlrWriteResult
    {
        $result = new PlrWriteResult();

        // Step 1: Read existing .plr file
        $content = PlrFileWriter::readFile($inputPath);
        $inputSize = strlen($content);
        $lines = PlrFileWriter::splitIntoLines($content);

        // Step 2: Index player records (line index → pid)
        $playerIndex = PlrFileWriter::indexPlayerRecords($lines);

        // Step 3: Query DB for all changeable fields
        $dbPlayers = $this->repository->getAllPlayerChangeableFields();
        $result->addMessage('Loaded ' . count($dbPlayers) . ' players from database');
        $result->addMessage('Found ' . count($playerIndex) . ' player records in .plr file');

        // Step 4: For each player, compare and build change set
        foreach ($playerIndex as $lineIndex => $pid) {
            if (!isset($dbPlayers[$pid])) {
                continue;
            }

            $dbPlayer = $dbPlayers[$pid];
            $line = $lines[$lineIndex];
            $changes = $this->buildChangeSet($line, $dbPlayer);

            if ($changes === []) {
                continue;
            }

            // Track old values for audit log
            $changeDetails = [];
            foreach ($changes as $field => $newValue) {
                $oldValue = PlrFileWriter::readField($line, $field);
                $changeDetails[] = [
                    'field' => $field,
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }

            // Apply changes
            $lines[$lineIndex] = PlrFileWriter::applyChangesToRecord($line, $changes);

            $playerName = PlrFileWriter::readPlayerName($line);
            $result->addPlayerChanges($pid, $playerName, $changeDetails);
        }

        // Step 5: Reassemble and write
        $output = PlrFileWriter::assembleFile(array_values($lines));

        // File size assertion
        if (strlen($output) !== $inputSize) {
            $result->addError(
                'Output size (' . strlen($output) . ') does not match input size (' . $inputSize . ')'
            );
            return $result;
        }

        PlrFileWriter::writeFile($output, $outputPath);
        $result->addMessage('Wrote ' . strlen($output) . ' bytes to ' . $outputPath);

        return $result;
    }

    /**
     * @see JsbExportServiceInterface::exportTrnFile()
     */
    public function exportTrnFile(string $outputPath): PlrWriteResult
    {
        $result = new PlrWriteResult();

        $tradeItems = $this->repository->getCompletedTradeItems();
        $result->addMessage('Found ' . count($tradeItems) . ' trade items in database');

        // Group trade items by tradeofferid to build trade records
        $tradeGroups = $this->groupTradeItems($tradeItems);

        $records = [];
        foreach ($tradeGroups as $group) {
            $trnItems = [];
            $dateStr = '';
            foreach ($group as $item) {
                $dateStr = $item['created_at'];

                if ($item['itemtype'] === '1') {
                    // Player trade
                    $fromJsbId = $this->resolveTeamToJsbId($item['from']);
                    $toJsbId = $this->resolveTeamToJsbId($item['to']);
                    $trnItems[] = [
                        'marker' => TrnFileParser::TRADE_MARKER_PLAYER,
                        'from_team' => $fromJsbId,
                        'to_team' => $toJsbId,
                        'player_id' => $item['itemid'],
                    ];
                } elseif ($item['itemtype'] === '0') {
                    // Draft pick trade
                    $fromJsbId = $this->resolveTeamToJsbId($item['from']);
                    $toJsbId = $this->resolveTeamToJsbId($item['to']);
                    $trnItems[] = [
                        'marker' => TrnFileParser::TRADE_MARKER_DRAFT_PICK,
                        'from_team' => $fromJsbId,
                        'to_team' => $toJsbId,
                        'draft_year' => $item['itemid'],
                    ];
                }
                // itemtype 'cash' is not represented in .trn format
            }

            if ($trnItems !== [] && $dateStr !== '') {
                $date = new \DateTimeImmutable($dateStr);
                $records[] = TrnFileWriter::buildTradeRecord(
                    (int) $date->format('n'),
                    (int) $date->format('j'),
                    (int) $date->format('Y'),
                    $trnItems,
                );
            }
        }

        $result->addMessage('Generated ' . count($records) . ' trade records');

        $output = TrnFileWriter::generate($records);

        // Size assertion
        if (strlen($output) !== TrnFileParser::FILE_SIZE) {
            $result->addError(
                'Output size (' . strlen($output) . ') does not match expected '
                . TrnFileParser::FILE_SIZE . ' bytes'
            );
            return $result;
        }

        PlrFileWriter::writeFile($output, $outputPath);
        $result->addMessage('Wrote ' . strlen($output) . ' bytes to ' . $outputPath);

        return $result;
    }

    /**
     * Compare database values to file values and build change set.
     *
     * @param string $line The current player record from the .plr file
     * @param array{pid: int, name: string, tid: int, dc_PGDepth: int, dc_SGDepth: int, dc_SFDepth: int, dc_PFDepth: int, dc_CDepth: int, dc_active: int, exp: int, bird: int, cy: int, cyt: int, cy1: int, cy2: int, cy3: int, cy4: int, cy5: int, cy6: int, injured: int} $dbPlayer
     * @return array<string, int> Map of PlrFileWriter field name → new value (only fields that differ)
     */
    private function buildChangeSet(string $line, array $dbPlayer): array
    {
        $changes = [];

        foreach (self::DB_TO_PLR_FIELD_MAP as $dbField => $plrField) {
            $dbValue = $dbPlayer[$dbField];
            $fileValue = PlrFileWriter::readField($line, $plrField);

            if ($dbValue !== $fileValue) {
                $changes[$plrField] = $dbValue;
            }
        }

        return $changes;
    }

    /**
     * Group trade items by tradeofferid.
     *
     * @param list<array{tradeofferid: int, itemid: int, itemtype: string, from: string, to: string, created_at: string}> $items
     * @return array<int, list<array{tradeofferid: int, itemid: int, itemtype: string, from: string, to: string, created_at: string}>>
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
