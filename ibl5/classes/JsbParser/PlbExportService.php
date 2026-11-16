<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\PlbExportServiceInterface;
use PlrParser\Contracts\PlrExportRepositoryInterface;
use PlrParser\PlrDepthChartSelector;
use PlrParser\PlrFileWriter;
use PlrParser\PlrWriteResult;

/**
 * Writes IBL5_export.plb in the slot order of IBL5_export.plr.
 *
 * Updates are layered, later layers overwriting earlier ones:
 *  (a) every player whose ordinal changed leaves VACATED_SLOT at his source slot;
 *  (b) the same player carries his source slot values to his new slot, as JSB does;
 *  (c) every player of a charted team gets his DB dc_* values at his export slot,
 *      when that slot sits in his DB team's block.
 */
class PlbExportService implements PlbExportServiceInterface
{
    /**
     * A slot JSB leaves with no player holds six zeros (F4 in JSB_EXPORT_RUNBOOK.md).
     *
     * @var array{dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}
     */
    public const VACATED_SLOT = ['dc_minutes' => 0, 'dc_of' => 0, 'dc_df' => 0, 'dc_oi' => 0, 'dc_di' => 0, 'dc_bh' => 0];

    /** @var array<string, string> */
    private const LOG_FIELDS = [
        'dc_minutes' => 'plb_minutes',
        'dc_of' => 'plb_of',
        'dc_df' => 'plb_df',
        'dc_oi' => 'plb_oi',
        'dc_di' => 'plb_di',
        'dc_bh' => 'plb_bh',
    ];

    public function __construct(private readonly PlrExportRepositoryInterface $repository)
    {
    }

    /**
     * @see PlbExportServiceInterface::exportPlbFile()
     */
    public function exportPlbFile(
        string $sourcePlrPath,
        string $exportPlrPath,
        string $sourcePlbPath,
        string $outputPlbPath,
    ): PlrWriteResult {
        $result = new PlrWriteResult();

        foreach (['source .plb' => $sourcePlbPath, 'export .plr' => $exportPlrPath, 'source .plr' => $sourcePlrPath] as $label => $path) {
            if (!file_exists($path)) {
                $result->addError($label . ' not found at ' . $path);
                return $result;
            }
        }

        $content = file_get_contents($sourcePlbPath);
        if ($content === false) {
            $result->addError('failed to read source .plb at ' . $sourcePlbPath);
            return $result;
        }
        $current = PlbFileParser::parse($content);

        $source = $this->indexOrdinals($sourcePlrPath);
        $export = $this->indexOrdinals($exportPlrPath);

        /** @var array<int, array<int, array{pid: int, values: array{dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}}>> $updates */
        $updates = [];

        // (a) vacate, then (b) carry
        $moved = [];
        foreach ($export['ordinals'] as $pid => $newOrdinal) {
            $oldOrdinal = $source['ordinals'][$pid] ?? null;
            if ($oldOrdinal !== null && $oldOrdinal !== $newOrdinal) {
                $moved[$pid] = [$oldOrdinal, $newOrdinal];
            }
        }
        foreach ($moved as $pid => [$oldOrdinal]) {
            $position = PlbFileWriter::ordinalToTeamSlot($oldOrdinal);
            if ($position !== null) {
                $updates[$position[0]][$position[1]] = ['pid' => $pid, 'values' => self::VACATED_SLOT];
            }
        }
        foreach ($moved as $pid => [$oldOrdinal, $newOrdinal]) {
            $from = PlbFileWriter::ordinalToTeamSlot($oldOrdinal);
            $to = PlbFileWriter::ordinalToTeamSlot($newOrdinal);
            if ($from === null || $to === null || !isset($current[$from[0]][$from[1]])) {
                continue;
            }
            $updates[$to[0]][$to[1]] = ['pid' => $pid, 'values' => self::slotValues($current[$from[0]][$from[1]])];
        }

        // (c) charted teams
        $charted = PlrDepthChartSelector::chartedByPid($this->repository->getAllPlayerDepthCharts());
        foreach ($charted as $pid => $row) {
            $ordinal = $export['ordinals'][$pid] ?? null;
            if ($ordinal === null) {
                continue;
            }
            $position = PlbFileWriter::ordinalToTeamSlot($ordinal);
            if ($position === null || $position[0] + 1 !== $row['teamid']) {
                continue;
            }
            $updates[$position[0]][$position[1]] = ['pid' => $pid, 'values' => self::slotValues($row)];
        }

        $writes = [];
        $logByPid = [];
        foreach ($updates as $teamLine => $slots) {
            foreach ($slots as $slot => $update) {
                $existing = $current[$teamLine][$slot] ?? null;
                $old = $existing === null ? self::VACATED_SLOT : self::slotValues($existing);
                if ($old === $update['values']) {
                    continue;
                }
                $writes[$teamLine][$slot] = $update['values'];
                foreach (self::LOG_FIELDS as $field => $logField) {
                    if ($old[$field] !== $update['values'][$field]) {
                        $logByPid[$update['pid']][] = ['field' => $logField, 'old' => $old[$field], 'new' => $update['values'][$field]];
                    }
                }
            }
        }

        $output = PlbFileWriter::applySlots($content, $writes);

        foreach ($logByPid as $pid => $changes) {
            $result->addPlayerChanges($pid, $export['names'][$pid] ?? '', $changes);
        }

        PlrFileWriter::writeFile($output, $outputPlbPath);
        $result->addMessage('Wrote ' . strlen($output) . ' bytes to ' . $outputPlbPath);

        return $result;
    }

    /**
     * @return array{ordinals: array<int, int>, names: array<int, string>} pid-keyed
     */
    private function indexOrdinals(string $plrPath): array
    {
        $lines = PlrFileWriter::splitIntoLines(PlrFileWriter::readFile($plrPath));
        $ordinals = [];
        $names = [];
        foreach (PlrFileWriter::indexPlayerRecords($lines) as $lineIndex => $pid) {
            $line = $lines[$lineIndex];
            $ordinal = (int) trim(substr($line, PlrFileWriter::OFFSET_ORDINAL, PlrFileWriter::WIDTH_ORDINAL));
            if ($ordinal < 1 || isset($ordinals[$pid])) {
                continue;
            }
            $ordinals[$pid] = $ordinal;
            $names[$pid] = PlrFileWriter::readPlayerName($line);
        }

        return ['ordinals' => $ordinals, 'names' => $names];
    }

    /**
     * @param array{dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int} $row
     * @return array{dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}
     */
    private static function slotValues(array $row): array
    {
        return [
            'dc_minutes' => $row['dc_minutes'],
            'dc_of' => $row['dc_of'],
            'dc_df' => $row['dc_df'],
            'dc_oi' => $row['dc_oi'],
            'dc_di' => $row['dc_di'],
            'dc_bh' => $row['dc_bh'],
        ];
    }
}
