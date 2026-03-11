<?php

declare(strict_types=1);

namespace HistArchiver\Contracts;

interface HistArchiverRepositoryInterface
{
    /**
     * Check if a champion has been crowned for the given season year.
     */
    public function hasChampionForYear(int $year): bool;

    /**
     * Get aggregated regular season stats from box scores for a given year.
     *
     * @return list<array<string, mixed>>
     */
    public function getRegularSeasonTotals(int $year): array;

    /**
     * Get player ratings, contract info, and team ID from ibl_plr.
     *
     * @return array{tid: int, r_2ga: int, r_2gp: int, r_fta: int, r_ftp: int, r_3ga: int, r_3gp: int, r_orb: int, r_drb: int, r_ast: int, r_stl: int, r_blk: int, r_tvr: int, r_oo: int, r_od: int, r_do: int, r_dd: int, r_po: int, r_pd: int, r_to: int, r_td: int, salary: int}|null
     */
    public function getPlayerRatingsAndContract(int $pid): ?array;

    /**
     * Upsert a row into ibl_hist.
     *
     * @param array<string, int|string> $data
     */
    public function upsertHistRow(array $data): int;

    /**
     * Get side-by-side comparison of ibl_hist game stats vs box score aggregates.
     *
     * @return list<array<string, mixed>>
     */
    public function getValidationComparison(int $year): array;
}
