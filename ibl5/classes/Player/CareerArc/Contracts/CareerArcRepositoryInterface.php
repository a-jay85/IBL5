<?php

declare(strict_types=1);

namespace Player\CareerArc\Contracts;

/**
 * Read-only queries behind the player career arc timeline.
 *
 * Every method is keyed on pid, or guarded by name uniqueness where the
 * source table (ibl_awards) carries no pid.
 */
interface CareerArcRepositoryInterface
{
    /**
     * One ibl_hist row per season, oldest first.
     *
     * @return list<array{year:int, teamid:int, team:string, games:int, pts:int, age:int,
     *   r_oo:int, r_drive_off:int, r_po:int, r_trans_off:int, r_od:int, r_dd:int, r_pd:int, r_td:int}>
     */
    public function getSeasonRows(int $pid): array;

    /**
     * Trades (type 2) and waiver claims (type 3) for the pid, with joined team names.
     *
     * @return list<array{season_year:int, transaction_month:int, transaction_day:int,
     *   transaction_type:int, from_teamid:int, to_teamid:int,
     *   from_team_name:?string, to_team_name:?string}>
     */
    public function getMoves(int $pid): array;

    /**
     * @return array{name:string, draftyear:int, draftround:int, draftpickno:int, draftedby:string}|null
     */
    public function getPlayerFacts(int $pid): ?array;

    /**
     * @return list<array{year:int, award:string}>|null null when the name is not held by exactly one ibl_plr row
     */
    public function getAwardsForUniqueName(string $name): ?array;
}
