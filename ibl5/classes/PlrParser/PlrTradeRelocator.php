<?php

declare(strict_types=1);

namespace PlrParser;

/**
 * Moves traded .plr records into the first empty slot of their new team's block.
 *
 * JSB keeps each team's players in a 30-slot ordinal block: team t owns
 * ordinals (t-1)*30+1 .. t*30. A record whose file teamid differs from the
 * database teamid is swapped with the first pid-0 line of the target block.
 * The empty line takes the vacated ordinal, so every line keeps its length and
 * the file keeps its size. Candidates move in ascending source ordinal, and a
 * slot vacated by an earlier move is free for a later one.
 *
 * Findings behind these rules: ibl5/docs/JSB_EXPORT_RUNBOOK.md (F1-F5).
 */
final class PlrTradeRelocator
{
    public const SLOTS_PER_TEAM = 30;
    public const MAX_TEAM_ID = 32;

    /**
     * JSB kept previousTeamIndex on the sim08 to sim09 trade sample (F3).
     */
    public const PRESERVE_PREVIOUS_TEAM_INDEX = true;

    /**
     * @param list<string> $lines .plr lines from PlrFileWriter::splitIntoLines()
     * @param array<int, int> $dbTeamByPid pid => current database teamid
     */
    public function relocate(array $lines, array $dbTeamByPid): PlrRelocationResult
    {
        $lineByOrdinal = $this->mapOrdinals($lines);
        $candidates = $this->findCandidates($lines, $lineByOrdinal, $dbTeamByPid);

        $moves = [];
        $warnings = [];

        foreach ($candidates as $candidate) {
            ['ordinal' => $sourceOrdinal, 'pid' => $pid, 'oldTid' => $oldTid, 'newTid' => $newTid] = $candidate;

            $targetOrdinal = $this->findEmptySlot($lines, $lineByOrdinal, $newTid);
            if ($targetOrdinal === null) {
                $warnings[] = 'WARNING: no empty slot in team ' . $newTid . ' block for pid ' . $pid
                    . '; teamid written in place';
                continue;
            }

            $sourceIndex = $lineByOrdinal[$sourceOrdinal];
            $targetIndex = $lineByOrdinal[$targetOrdinal];
            $record = $lines[$sourceIndex];
            $emptyLine = $lines[$targetIndex];

            $lines[$targetIndex] = $this->moveRecord($record, $targetOrdinal, $newTid);
            $lines[$sourceIndex] = self::writeOrdinal($emptyLine, $sourceOrdinal);

            $moves[] = [
                'pid' => $pid,
                'name' => PlrFileWriter::readPlayerName($record),
                'oldOrdinal' => $sourceOrdinal,
                'newOrdinal' => $targetOrdinal,
                'oldTid' => $oldTid,
                'newTid' => $newTid,
            ];
        }

        return new PlrRelocationResult(array_values($lines), $moves, $warnings);
    }

    /**
     * @param list<string> $lines
     * @return array<int, int> ordinal => line index, for ordinals 1..1440
     */
    private function mapOrdinals(array $lines): array
    {
        $map = [];
        foreach ($lines as $index => $line) {
            if (strlen($line) < PlrFileWriter::WIDTH_ORDINAL) {
                continue;
            }
            $ordinal = self::readOrdinal($line);
            if ($ordinal >= 1 && $ordinal <= PlrFileWriter::MAX_PLAYER_ORDINAL && !isset($map[$ordinal])) {
                $map[$ordinal] = $index;
            }
        }

        return $map;
    }

    /**
     * @param list<string> $lines
     * @param array<int, int> $lineByOrdinal
     * @param array<int, int> $dbTeamByPid
     * @return list<array{ordinal: int, pid: int, oldTid: int, newTid: int}>
     */
    private function findCandidates(array $lines, array $lineByOrdinal, array $dbTeamByPid): array
    {
        $candidates = [];
        foreach ($lineByOrdinal as $ordinal => $index) {
            $line = $lines[$index];
            $pid = self::readPid($line);
            if ($pid === 0 || !isset($dbTeamByPid[$pid])) {
                continue;
            }

            $fileTid = PlrFileWriter::readField($line, 'teamid');
            $dbTid = $dbTeamByPid[$pid];
            if (!self::isRealTeam($fileTid) || !self::isRealTeam($dbTid) || $fileTid === $dbTid) {
                continue;
            }

            $candidates[] = ['ordinal' => $ordinal, 'pid' => $pid, 'oldTid' => $fileTid, 'newTid' => $dbTid];
        }

        usort($candidates, static fn (array $a, array $b): int => $a['ordinal'] <=> $b['ordinal']);

        return $candidates;
    }

    /**
     * @param list<string> $lines
     * @param array<int, int> $lineByOrdinal
     */
    private function findEmptySlot(array $lines, array $lineByOrdinal, int $teamid): ?int
    {
        $first = ($teamid - 1) * self::SLOTS_PER_TEAM + 1;
        $last = $teamid * self::SLOTS_PER_TEAM;

        for ($ordinal = $first; $ordinal <= $last; $ordinal++) {
            if (!isset($lineByOrdinal[$ordinal])) {
                continue;
            }
            if (self::readPid($lines[$lineByOrdinal[$ordinal]]) === 0) {
                return $ordinal;
            }
        }

        return null;
    }

    private function moveRecord(string $record, int $targetOrdinal, int $newTid): string
    {
        $previousTeamIndex = PlrFileWriter::readField($record, 'previousTeamIndex');

        $moved = self::writeOrdinal($record, $targetOrdinal);
        $moved = PlrFileWriter::applyChangesToRecord($moved, ['teamid' => $newTid]);

        if (self::PRESERVE_PREVIOUS_TEAM_INDEX) {
            $moved = PlrFileWriter::applyChangesToRecord($moved, ['previousTeamIndex' => $previousTeamIndex]);
        }

        return $moved;
    }

    private static function writeOrdinal(string $line, int $ordinal): string
    {
        return substr_replace(
            $line,
            PlrFieldSerializer::formatInt($ordinal, PlrFileWriter::WIDTH_ORDINAL),
            PlrFileWriter::OFFSET_ORDINAL,
            PlrFileWriter::WIDTH_ORDINAL,
        );
    }

    private static function readOrdinal(string $line): int
    {
        return (int) trim(substr($line, PlrFileWriter::OFFSET_ORDINAL, PlrFileWriter::WIDTH_ORDINAL));
    }

    private static function readPid(string $line): int
    {
        return (int) trim(substr($line, PlrFileWriter::OFFSET_PID, PlrFileWriter::WIDTH_PID));
    }

    private static function isRealTeam(int $teamid): bool
    {
        return $teamid >= 1 && $teamid <= self::MAX_TEAM_ID;
    }
}
