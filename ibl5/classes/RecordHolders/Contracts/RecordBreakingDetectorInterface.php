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
     * Detect and announce any broken or tied records from the given game date.
     *
     * @param string $gameDate The date to check for broken/tied records (YYYY-MM-DD)
     * @return list<string> List of record announcement messages (broken and tied)
     */
    public function detectAndAnnounce(string $gameDate): array;
}
