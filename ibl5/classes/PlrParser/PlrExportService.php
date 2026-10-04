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
     * Depth chart fields (pg_depth–c_depth, dc_can_play_in_game) are intentionally excluded —
     * those are managed by DepthChartEntry and not exported to the .plr file.
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

    public function __construct(PlrExportRepositoryInterface $repository)
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
}
