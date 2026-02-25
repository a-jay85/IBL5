<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\PlrFileWriterInterface;

/**
 * Read-modify-write engine for JSB .plr (Player) files.
 *
 * Reads an existing .plr file as a baseline and modifies only the fields that
 * change via the website (trades, waivers, depth charts, contracts, etc.).
 * All JSB-internal data (NBA stats, morale, awards, engine state) is preserved.
 *
 * Player records are 607 bytes of data. Lines in the file are CRLF-terminated.
 * Numeric fields are right-justified and space-padded.
 *
 * Field offsets extracted from plrParser.php lines 124-288.
 */
class PlrFileWriter implements PlrFileWriterInterface
{
    // ── Record structure ───────────────────────────────────────────
    public const PLAYER_RECORD_LENGTH = 607;
    public const MAX_PLAYER_ORDINAL = 1440;

    // ── Identification fields (read-only, used for indexing) ──────
    public const OFFSET_ORDINAL = 0;
    public const WIDTH_ORDINAL = 4;
    public const OFFSET_PID = 38;
    public const WIDTH_PID = 6;

    // ── Changeable fields ──────────────────────────────────────────
    // Team assignment
    public const OFFSET_TID = 44;
    public const WIDTH_TID = 2;

    // Depth chart positions (1-char each)
    public const OFFSET_PG_DEPTH = 132;
    public const OFFSET_SG_DEPTH = 133;
    public const OFFSET_SF_DEPTH = 134;
    public const OFFSET_PF_DEPTH = 135;
    public const OFFSET_C_DEPTH = 136;
    public const WIDTH_DEPTH = 1;

    // Active status
    public const OFFSET_ACTIVE = 137;
    public const WIDTH_ACTIVE = 1;

    // Injury
    public const OFFSET_INJURY_DAYS_LEFT = 140;
    public const WIDTH_INJURY_DAYS_LEFT = 4;

    // Experience and bird rights
    public const OFFSET_EXP = 286;
    public const WIDTH_EXP = 2;
    public const OFFSET_BIRD = 288;
    public const WIDTH_BIRD = 2;

    // Contract
    public const OFFSET_CY = 290;
    public const WIDTH_CY = 2;
    public const OFFSET_CYT = 292;
    public const WIDTH_CYT = 2;

    // Contract year salaries (4 chars each, offsets 298-321)
    public const OFFSET_CY1 = 298;
    public const OFFSET_CY2 = 302;
    public const OFFSET_CY3 = 306;
    public const OFFSET_CY4 = 310;
    public const OFFSET_CY5 = 314;
    public const OFFSET_CY6 = 318;
    public const WIDTH_CY_SALARY = 4;

    // Derived fields (auto-updated when related fields change)
    public const OFFSET_FA_SIGNING_FLAG = 330;
    public const WIDTH_FA_SIGNING_FLAG = 1;
    public const OFFSET_CONTRACT_OWNED_BY = 331;
    public const WIDTH_CONTRACT_OWNED_BY = 2;
    public const OFFSET_CURRENT_TEAM_INDEX = 333;
    public const WIDTH_CURRENT_TEAM_INDEX = 2;
    public const OFFSET_PREVIOUS_TEAM_INDEX = 335;
    public const WIDTH_PREVIOUS_TEAM_INDEX = 2;

    /**
     * Map of field names to [offset, width] pairs for all changeable fields.
     *
     * @var array<string, array{int, int}>
     */
    public const FIELD_MAP = [
        'tid' => [self::OFFSET_TID, self::WIDTH_TID],
        'PGDepth' => [self::OFFSET_PG_DEPTH, self::WIDTH_DEPTH],
        'SGDepth' => [self::OFFSET_SG_DEPTH, self::WIDTH_DEPTH],
        'SFDepth' => [self::OFFSET_SF_DEPTH, self::WIDTH_DEPTH],
        'PFDepth' => [self::OFFSET_PF_DEPTH, self::WIDTH_DEPTH],
        'CDepth' => [self::OFFSET_C_DEPTH, self::WIDTH_DEPTH],
        'active' => [self::OFFSET_ACTIVE, self::WIDTH_ACTIVE],
        'injuryDaysLeft' => [self::OFFSET_INJURY_DAYS_LEFT, self::WIDTH_INJURY_DAYS_LEFT],
        'exp' => [self::OFFSET_EXP, self::WIDTH_EXP],
        'bird' => [self::OFFSET_BIRD, self::WIDTH_BIRD],
        'cy' => [self::OFFSET_CY, self::WIDTH_CY],
        'cyt' => [self::OFFSET_CYT, self::WIDTH_CYT],
        'cy1' => [self::OFFSET_CY1, self::WIDTH_CY_SALARY],
        'cy2' => [self::OFFSET_CY2, self::WIDTH_CY_SALARY],
        'cy3' => [self::OFFSET_CY3, self::WIDTH_CY_SALARY],
        'cy4' => [self::OFFSET_CY4, self::WIDTH_CY_SALARY],
        'cy5' => [self::OFFSET_CY5, self::WIDTH_CY_SALARY],
        'cy6' => [self::OFFSET_CY6, self::WIDTH_CY_SALARY],
        'freeAgentSigningFlag' => [self::OFFSET_FA_SIGNING_FLAG, self::WIDTH_FA_SIGNING_FLAG],
        'contractOwnedBy' => [self::OFFSET_CONTRACT_OWNED_BY, self::WIDTH_CONTRACT_OWNED_BY],
        'currentTeamIndex' => [self::OFFSET_CURRENT_TEAM_INDEX, self::WIDTH_CURRENT_TEAM_INDEX],
        'previousTeamIndex' => [self::OFFSET_PREVIOUS_TEAM_INDEX, self::WIDTH_PREVIOUS_TEAM_INDEX],
    ];

    /**
     * @see PlrFileWriterInterface::readFile()
     */
    public static function readFile(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException('PLR file not found: ' . $filePath);
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException('Failed to read PLR file: ' . $filePath);
        }

        return $content;
    }

    /**
     * @see PlrFileWriterInterface::splitIntoLines()
     */
    public static function splitIntoLines(string $content): array
    {
        // Split on CRLF, preserving all segments (including trailing empty)
        return explode("\r\n", $content);
    }

    /**
     * @see PlrFileWriterInterface::indexPlayerRecords()
     */
    public static function indexPlayerRecords(array $lines): array
    {
        $index = [];

        foreach ($lines as $lineIndex => $line) {
            if (strlen($line) < self::OFFSET_PID + self::WIDTH_PID) {
                continue;
            }

            $ordinal = (int) trim(substr($line, self::OFFSET_ORDINAL, self::WIDTH_ORDINAL));
            $pid = (int) trim(substr($line, self::OFFSET_PID, self::WIDTH_PID));

            if ($ordinal <= self::MAX_PLAYER_ORDINAL && $pid !== 0) {
                $index[$lineIndex] = $pid;
            }
        }

        return $index;
    }

    /**
     * @see PlrFileWriterInterface::applyChangesToRecord()
     */
    public static function applyChangesToRecord(string $line, array $changes): string
    {
        $originalLength = strlen($line);
        $modified = $line;

        // Read old tid before applying changes (needed for derived fields)
        $oldTid = (int) trim(substr($line, self::OFFSET_TID, self::WIDTH_TID));

        foreach ($changes as $field => $value) {
            if (!isset(self::FIELD_MAP[$field])) {
                throw new \RuntimeException('Unknown field: ' . $field);
            }

            [$offset, $width] = self::FIELD_MAP[$field];
            $formatted = PlrFieldSerializer::formatInt($value, $width);
            $modified = substr_replace($modified, $formatted, $offset, $width);
        }

        // Auto-update derived fields when tid changes
        if (isset($changes['tid'])) {
            $newTid = $changes['tid'];
            $modified = self::applyDerivedTidFields($modified, $newTid, $oldTid);
        }

        // Auto-update freeAgentSigningFlag when bird changes
        if (isset($changes['bird']) && !isset($changes['freeAgentSigningFlag'])) {
            $faFlag = $changes['bird'] === 1 ? 1 : 0;
            $formatted = PlrFieldSerializer::formatInt($faFlag, self::WIDTH_FA_SIGNING_FLAG);
            $modified = substr_replace($modified, $formatted, self::OFFSET_FA_SIGNING_FLAG, self::WIDTH_FA_SIGNING_FLAG);
        }

        if (strlen($modified) !== $originalLength) {
            throw new \RuntimeException(
                'Record length changed from ' . $originalLength . ' to ' . strlen($modified)
                . ' after applying changes'
            );
        }

        return $modified;
    }

    /**
     * @see PlrFileWriterInterface::assembleFile()
     */
    public static function assembleFile(array $lines): string
    {
        return implode("\r\n", $lines);
    }

    /**
     * @see PlrFileWriterInterface::writeFile()
     */
    public static function writeFile(string $content, string $outputPath): void
    {
        $dir = dirname($outputPath);
        $tmpFile = tempnam($dir, 'plr_export_');
        if ($tmpFile === false) {
            throw new \RuntimeException('Failed to create temporary file in ' . $dir);
        }

        $written = file_put_contents($tmpFile, $content);
        if ($written === false) {
            unlink($tmpFile);
            throw new \RuntimeException('Failed to write to temporary file: ' . $tmpFile);
        }

        if (!rename($tmpFile, $outputPath)) {
            unlink($tmpFile);
            throw new \RuntimeException('Failed to rename temporary file to ' . $outputPath);
        }
    }

    /**
     * Read the current value of a field from a record line.
     *
     * @param string $line The player record
     * @param string $field Field name from FIELD_MAP
     * @return int The current integer value of the field
     */
    public static function readField(string $line, string $field): int
    {
        if (!isset(self::FIELD_MAP[$field])) {
            throw new \RuntimeException('Unknown field: ' . $field);
        }

        [$offset, $width] = self::FIELD_MAP[$field];
        return (int) trim(substr($line, $offset, $width));
    }

    /**
     * Read the player name from a record line (for logging/audit).
     *
     * @param string $line The player record
     * @return string Player name (UTF-8 converted from CP1252)
     */
    public static function readPlayerName(string $line): string
    {
        $nameRaw = trim(substr($line, 4, 32));
        $converted = iconv('CP1252', 'UTF-8//IGNORE', $nameRaw);
        return is_string($converted) ? $converted : $nameRaw;
    }

    /**
     * Apply derived field updates when tid changes.
     *
     * - contractOwnedBy = new tid
     * - currentTeamIndex = tid - 1 (or -1 for free agents when tid=0)
     * - previousTeamIndex = old tid - 1
     */
    private static function applyDerivedTidFields(string $line, int $newTid, int $oldTid): string
    {
        // contractOwnedBy = same as tid
        $line = substr_replace(
            $line,
            PlrFieldSerializer::formatInt($newTid, self::WIDTH_CONTRACT_OWNED_BY),
            self::OFFSET_CONTRACT_OWNED_BY,
            self::WIDTH_CONTRACT_OWNED_BY,
        );

        // currentTeamIndex = tid - 1, or -1 for free agents
        $currentIndex = $newTid === 0 ? -1 : $newTid - 1;
        $line = substr_replace(
            $line,
            PlrFieldSerializer::formatInt($currentIndex, self::WIDTH_CURRENT_TEAM_INDEX),
            self::OFFSET_CURRENT_TEAM_INDEX,
            self::WIDTH_CURRENT_TEAM_INDEX,
        );

        // previousTeamIndex = old tid - 1
        $previousIndex = $oldTid === 0 ? -1 : $oldTid - 1;
        $line = substr_replace(
            $line,
            PlrFieldSerializer::formatInt($previousIndex, self::WIDTH_PREVIOUS_TEAM_INDEX),
            self::OFFSET_PREVIOUS_TEAM_INDEX,
            self::WIDTH_PREVIOUS_TEAM_INDEX,
        );

        return $line;
    }
}
