<?php

declare(strict_types=1);

namespace SavedDepthChart;

use SavedDepthChart\Contracts\SlotAssignmentResolverInterface;

/**
 * @phpstan-import-type SlotSettings from Contracts\SlotAssignmentResolverInterface
 *
 * @see SlotAssignmentResolverInterface
 */
class SlotAssignmentResolver implements SlotAssignmentResolverInterface
{
    /** Number of player rows the depth-chart form submits. */
    private const MAX_SLOTS = 15;

    /**
     * @see SlotAssignmentResolverInterface::resolveSlotSettings()
     * @param array<string, mixed> $player
     * @param array<string, mixed> $postData
     * @return SlotSettings|null
     */
    public function resolveSlotSettings(array $player, array $postData, int $ordinal): ?array
    {
        $slot = $this->findPidIndex($player, $postData, $ordinal);
        if ($slot === 0) {
            return null;
        }

        return [
            'pg' => $this->extractIntFromPost($postData, 'pg' . $slot),
            'sg' => $this->extractIntFromPost($postData, 'sg' . $slot),
            'sf' => $this->extractIntFromPost($postData, 'sf' . $slot),
            'pf' => $this->extractIntFromPost($postData, 'pf' . $slot),
            'c' => $this->extractIntFromPost($postData, 'c' . $slot),
            'canPlayInGame' => $this->extractIntFromPost($postData, 'canPlayInGame' . $slot),
            'min' => $this->extractIntFromPost($postData, 'min' . $slot),
            'of' => $this->extractIntFromPost($postData, 'OF' . $slot),
            'df' => $this->extractIntFromPost($postData, 'DF' . $slot),
            'oi' => $this->extractIntFromPost($postData, 'OI' . $slot),
            'di' => $this->extractIntFromPost($postData, 'DI' . $slot),
            'bh' => $this->extractIntFromPost($postData, 'BH' . $slot),
        ];
    }

    /**
     * Find the form index for a player by matching pid fields in POST data.
     *
     * Falls back to name match, then to ordinal position. Returns 0 for no match.
     *
     * @param array<string, mixed> $player
     * @param array<string, mixed> $postData
     */
    private function findPidIndex(array $player, array $postData, int $ordinal): int
    {
        $playerPid = $this->toInt($player['pid'] ?? 0);

        // Try to match by pid hidden field
        for ($i = 1; $i <= self::MAX_SLOTS; $i++) {
            $pidField = 'pid' . $i;
            if (isset($postData[$pidField]) && $this->toInt($postData[$pidField]) === $playerPid) {
                return $i;
            }
        }

        // Fall back to matching by name (existing pattern) or ordinal
        $playerName = $this->toString($player['name'] ?? '');
        for ($i = 1; $i <= self::MAX_SLOTS; $i++) {
            $nameField = 'Name' . $i;
            $postName = isset($postData[$nameField]) ? $this->toString($postData[$nameField]) : '';
            if ($postName !== '' && trim(strip_tags($postName)) === $playerName) {
                return $i;
            }
        }

        // Last resort: use ordinal position
        if (isset($postData['Name' . $ordinal])) {
            return $ordinal;
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $postData
     */
    private function extractIntFromPost(array $postData, string $key): int
    {
        $value = $postData[$key] ?? 0;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        return 0;
    }

    /**
     * Safely convert mixed value to int
     */
    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        return 0;
    }

    /**
     * Safely convert mixed value to string
     */
    private function toString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return '';
    }
}
