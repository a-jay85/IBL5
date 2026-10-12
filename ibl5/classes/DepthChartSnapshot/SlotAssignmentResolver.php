<?php

declare(strict_types=1);

namespace DepthChartSnapshot;

use BasketballStats\StatsSanitizer;
use DepthChartSnapshot\Contracts\SlotAssignmentResolverInterface;

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
            'pg' => StatsSanitizer::intFromArray($postData, 'pg' . $slot),
            'sg' => StatsSanitizer::intFromArray($postData, 'sg' . $slot),
            'sf' => StatsSanitizer::intFromArray($postData, 'sf' . $slot),
            'pf' => StatsSanitizer::intFromArray($postData, 'pf' . $slot),
            'c' => StatsSanitizer::intFromArray($postData, 'c' . $slot),
            'canPlayInGame' => StatsSanitizer::intFromArray($postData, 'canPlayInGame' . $slot),
            'min' => StatsSanitizer::intFromArray($postData, 'min' . $slot),
            'of' => StatsSanitizer::intFromArray($postData, 'OF' . $slot),
            'df' => StatsSanitizer::intFromArray($postData, 'DF' . $slot),
            'oi' => StatsSanitizer::intFromArray($postData, 'OI' . $slot),
            'di' => StatsSanitizer::intFromArray($postData, 'DI' . $slot),
            'bh' => StatsSanitizer::intFromArray($postData, 'BH' . $slot),
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
        $playerPid = StatsSanitizer::toInt($player['pid'] ?? 0);

        // Try to match by pid hidden field
        for ($i = 1; $i <= self::MAX_SLOTS; $i++) {
            $pidField = 'pid' . $i;
            if (isset($postData[$pidField]) && StatsSanitizer::toInt($postData[$pidField]) === $playerPid) {
                return $i;
            }
        }

        // Fall back to matching by name (existing pattern) or ordinal
        $playerName = StatsSanitizer::scalarToString($player['name'] ?? '');
        for ($i = 1; $i <= self::MAX_SLOTS; $i++) {
            $nameField = 'Name' . $i;
            $postName = isset($postData[$nameField]) ? StatsSanitizer::scalarToString($postData[$nameField]) : '';
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
}
