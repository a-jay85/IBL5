<?php

declare(strict_types=1);

namespace Waivers\Contracts;

/**
 * WaiversServiceInterface - Contract for waiver wire read-path orchestration
 *
 * Assembles all data needed by the waiver form: team info, player lists
 * with contract display and wait times, roster spot counts, and player
 * table data for the stat view switcher.
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 *
 * @phpstan-type WaiverFormData array{
 *     team: \Team\Team,
 *     players: list<string>,
 *     openRosterSpots: int,
 *     healthyOpenRosterSpots: int,
 *     tableResult: array<int, array<string, mixed>|\Player\Player>,
 *     styleTeam: \Team\Team,
 *     season: \Season\Season
 * }
 */
interface WaiversServiceInterface
{
    /**
     * Assemble all data needed by the waiver form for a given user/action.
     *
     * @return WaiverFormData
     */
    public function getWaiverFormData(string $username, string $action): array;
}
