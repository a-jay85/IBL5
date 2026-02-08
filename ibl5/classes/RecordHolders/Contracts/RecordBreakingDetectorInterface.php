<?php

declare(strict_types=1);

namespace RecordHolders\Contracts;

/**
 * Interface for detecting broken all-time IBL records.
 *
 * Compares new sim results against current records and sends Discord notifications.
 */
interface RecordBreakingDetectorInterface
{
    /**
     * Detect and announce any broken records from the given game date.
     *
     * @param string $gameDate The date to check for broken records (YYYY-MM-DD)
     * @return list<string> List of broken record announcement messages
     */
    public function detectAndAnnounce(string $gameDate): array;
}
