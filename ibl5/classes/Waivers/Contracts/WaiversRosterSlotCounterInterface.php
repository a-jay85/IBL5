<?php

declare(strict_types=1);

namespace Waivers\Contracts;

use Season\Season;

/**
 * Server-side count of a team's open roster spots, the single definition shared by the
 * Waivers form display and the submit path (backlog#1538). Counts come from the roster
 * rows the TeamQueryRepository already filters (retired = 0, ordinal <= WAIVERS_ORDINAL,
 * offseason next-year-salary filter), never from client input.
 */
interface WaiversRosterSlotCounterInterface
{
    /**
     * Both values are Team::ROSTER_SPOTS_MAX minus a row count and are NOT clamped at zero:
     * an over-full roster yields a negative "open" so WaiversValidator::validateAdd()'s
     * "< 1" rule still rejects it.
     *
     * @return array{open: int, healthy: int}  open = all active players; healthy = injured = '0' only
     */
    public function countOpenSlots(int $teamID, Season $season): array;
}
