<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\PlbFileWriterInterface;

/**
 * Writer for JSB .plb (Depth Chart) text files.
 *
 * Rewrites slots in place on a source file's bytes. Untouched slots, bytes past
 * char 360, lines past the 32 team lines, and the line separators stay identical.
 * Layout copied from PlbFileParser::parse().
 */
class PlbFileWriter implements PlbFileWriterInterface
{
    public const TEAMS_PER_FILE = 32;
    public const SLOTS_PER_TEAM = 30;
    public const CHARS_PER_SLOT = 12;
    public const MIN_LINE_LENGTH = 360; // 30 × 12

    /**
     * JSB pads each 2-char value with a leading space (F4 in JSB_EXPORT_RUNBOOK.md).
     */
    public const SLOT_FORMAT = '%2d';

    private const MIN_VALUE = -9;
    private const MAX_VALUE = 99;

    private const SLOT_FIELDS = ['dc_minutes', 'dc_of', 'dc_df', 'dc_oi', 'dc_di', 'dc_bh'];

    /**
     * @see PlbFileWriterInterface::formatSlot()
     */
    public static function formatSlot(array $slot): string
    {
        $formatted = '';
        foreach (self::SLOT_FIELDS as $field) {
            $value = $slot[$field];
            if ($value < self::MIN_VALUE || $value > self::MAX_VALUE) {
                throw new \InvalidArgumentException(
                    'PLB value ' . $value . ' for ' . $field . ' does not fit in two chars'
                );
            }
            $formatted .= sprintf(self::SLOT_FORMAT, $value);
        }

        return $formatted;
    }

    /**
     * @see PlbFileWriterInterface::applySlots()
     */
    public static function applySlots(string $content, array $updates): string
    {
        $sep = str_contains($content, "\r\n") ? "\r\n" : "\n";
        $lines = explode($sep, $content);

        foreach ($updates as $teamLine => $slots) {
            if ($teamLine < 0 || $teamLine >= self::TEAMS_PER_FILE) {
                throw new \InvalidArgumentException('PLB team line out of range: ' . $teamLine);
            }
            if (!isset($lines[$teamLine]) || strlen($lines[$teamLine]) < self::MIN_LINE_LENGTH) {
                throw new \RuntimeException(
                    'PLB team line ' . $teamLine . ' is shorter than ' . self::MIN_LINE_LENGTH . ' chars'
                );
            }

            foreach ($slots as $slot => $values) {
                if ($slot < 0 || $slot >= self::SLOTS_PER_TEAM) {
                    throw new \InvalidArgumentException('PLB slot out of range: ' . $slot);
                }
                $lines[$teamLine] = substr_replace(
                    $lines[$teamLine],
                    self::formatSlot($values),
                    $slot * self::CHARS_PER_SLOT,
                    self::CHARS_PER_SLOT,
                );
            }
        }

        $output = implode($sep, $lines);
        if (strlen($output) !== strlen($content)) {
            throw new \RuntimeException(
                'PLB length changed from ' . strlen($content) . ' to ' . strlen($output)
            );
        }

        return $output;
    }

    /**
     * @see PlbFileWriterInterface::ordinalToTeamSlot()
     */
    public static function ordinalToTeamSlot(int $ordinal): ?array
    {
        if ($ordinal < 1 || $ordinal > self::TEAMS_PER_FILE * self::SLOTS_PER_TEAM) {
            return null;
        }

        return [intdiv($ordinal - 1, self::SLOTS_PER_TEAM), ($ordinal - 1) % self::SLOTS_PER_TEAM];
    }
}
