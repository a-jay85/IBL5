<?php

declare(strict_types=1);

namespace PlrParser;

use PlrParser\Contracts\PlrExportRepositoryInterface;
use PlrParser\Contracts\PlrExportServiceInterface;

/**
 * Writes current database player state into an existing .plr file.
 *
 * Read-modify-write: reads the baseline .plr, overwrites only the changeable
 * fields whose database value differs, and writes a same-size file via PlrFileWriter.
 */
class PlrExportService implements PlrExportServiceInterface
{
    private PlrExportRepositoryInterface $repository;

    /**
     * Map of database field names to PlrFileWriter field names.
     *
     * Depth chart fields come from DEPTH_TO_PLR_FIELD_MAP, and only for teams that
     * PlrDepthChartSelector marks as charted.
     *
     * @var array<string, string>
     */
    private const DB_TO_PLR_FIELD_MAP = [
        'teamid' => 'teamid',
        'bird' => 'bird',
        'cy' => 'cy',
        'cyt' => 'cyt',
        'salary_yr1' => 'salary_yr1',
        'salary_yr2' => 'salary_yr2',
        'salary_yr3' => 'salary_yr3',
        'salary_yr4' => 'salary_yr4',
        'salary_yr5' => 'salary_yr5',
        'salary_yr6' => 'salary_yr6',
        'fa_signing_flag' => 'freeAgentSigningFlag',
    ];

    /**
     * Map of GM depth-chart columns to PlrFileWriter field names.
     *
     * @var array<string, string>
     */
    private const DEPTH_TO_PLR_FIELD_MAP = [
        'dc_pg_depth' => 'PGDepth',
        'dc_sg_depth' => 'SGDepth',
        'dc_sf_depth' => 'SFDepth',
        'dc_pf_depth' => 'PFDepth',
        'dc_c_depth' => 'CDepth',
        'dc_can_play_in_game' => 'canPlayInGame',
    ];

    public function __construct(PlrExportRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see PlrExportServiceInterface::exportPlrFile()
     */
    public function exportPlrFile(string $inputPath, string $outputPath): PlrWriteResult
    {
        $result = new PlrWriteResult();

        // Step 1: Read existing .plr file
        $content = PlrFileWriter::readFile($inputPath);
        $inputSize = strlen($content);
        $lines = PlrFileWriter::splitIntoLines($content);

        // Step 3: Query DB for all changeable fields
        $dbPlayers = $this->repository->getAllPlayerChangeableFields();

        $sourceTeamByPid = PlrDepthChartSelector::sourceTeamByPid(array_values($lines));

        // Step 3b: Move traded players into their new team's slot block
        $teamByPid = [];
        foreach ($dbPlayers as $pid => $dbPlayer) {
            $teamByPid[$pid] = $dbPlayer['teamid'];
        }
        $relocation = (new PlrTradeRelocator())->relocate(array_values($lines), $teamByPid);
        $lines = $relocation->lines;

        // Step 2: Index player records (line index → pid), after relocation moved lines
        $playerIndex = PlrFileWriter::indexPlayerRecords($lines);
        foreach ($relocation->warnings as $warning) {
            $result->addMessage($warning);
        }
        foreach ($relocation->moves as $move) {
            $result->addPlayerChanges($move['pid'], $move['name'], [
                ['field' => 'ordinal', 'old' => $move['oldOrdinal'], 'new' => $move['newOrdinal']],
                ['field' => 'teamid', 'old' => $move['oldTid'], 'new' => $move['newTid']],
            ]);
        }
        $result->addMessage('Loaded ' . count($dbPlayers) . ' players from database');
        $result->addMessage('Found ' . count($playerIndex) . ' player records in .plr file');

        $charted = PlrDepthChartSelector::chartedByPid($this->repository->getAllPlayerDepthCharts(), $sourceTeamByPid);
        $result->addMessage('Depth charts applied for ' . PlrDepthChartSelector::countTeams($charted) . ' teams');

        // Step 4: For each player, compare and build change set
        foreach ($playerIndex as $lineIndex => $pid) {
            if (!isset($dbPlayers[$pid])) {
                continue;
            }

            $dbPlayer = $dbPlayers[$pid];
            $line = $lines[$lineIndex];
            $changes = $this->buildChangeSet($line, $dbPlayer) + $this->buildDepthChangeSet($line, $charted[$pid] ?? null);

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
        /** @infection-ignore-all Equivalent: $lines is already a list and assembleFile implodes it, so array_values changes nothing. */
        $output = PlrFileWriter::assembleFile(array_values($lines));

        // File size assertion
        /** @infection-ignore-all Unreachable: applyChangesToRecord throws on a length change and split/assemble are exact inverses. */
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
     * Compare database values to file values and build change set.
     *
     * @param string $line The current player record from the .plr file
     * @param array{pid: int, name: string, teamid: int, bird: int, cy: int, cyt: int, salary_yr1: int, salary_yr2: int, salary_yr3: int, salary_yr4: int, salary_yr5: int, salary_yr6: int, fa_signing_flag: int} $dbPlayer
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
     * Build the depth and active changes for a record of a charted team.
     *
     * Returns no changes when the record sits outside its DB team's slot block or
     * its file teamid differs from the DB teamid (a trade with no free slot).
     *
     * @param array{pid: int, teamid: int, dc_pg_depth: int, dc_sg_depth: int, dc_sf_depth: int, dc_pf_depth: int, dc_c_depth: int, dc_can_play_in_game: int, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}|null $row
     * @return array<string, int>
     */
    private function buildDepthChangeSet(string $line, ?array $row): array
    {
        if ($row === null) {
            return [];
        }

        $ordinal = (int) trim(substr($line, PlrFileWriter::OFFSET_ORDINAL, PlrFileWriter::WIDTH_ORDINAL));
        $blockTeam = intdiv($ordinal - 1, PlrTradeRelocator::SLOTS_PER_TEAM) + 1;
        if ($blockTeam !== $row['teamid'] || PlrFileWriter::readField($line, 'teamid') !== $row['teamid']) {
            return [];
        }

        $changes = [];
        foreach (self::DEPTH_TO_PLR_FIELD_MAP as $dbField => $plrField) {
            $dbValue = $row[$dbField];
            if ($dbValue !== PlrFileWriter::readField($line, $plrField)) {
                $changes[$plrField] = $dbValue;
            }
        }

        return $changes;
    }
}
