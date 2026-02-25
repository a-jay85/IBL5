<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

/**
 * Interface for database queries needed for JSB file export.
 *
 * Provides data from the database that needs to be written back to .plr and .trn files.
 */
interface JsbExportRepositoryInterface
{
    /**
     * Get all changeable player fields for PLR export, keyed by pid.
     *
     * Returns dc_ prefixed depth chart fields (the GM's intended values from the website),
     * NOT the non-prefixed fields (which are the values currently IN the .plr file from last parse).
     *
     * @return array<int, array{
     *     pid: int,
     *     name: string,
     *     tid: int,
     *     dc_PGDepth: int,
     *     dc_SGDepth: int,
     *     dc_SFDepth: int,
     *     dc_PFDepth: int,
     *     dc_CDepth: int,
     *     dc_active: int,
     *     exp: int,
     *     bird: int,
     *     cy: int,
     *     cyt: int,
     *     cy1: int,
     *     cy2: int,
     *     cy3: int,
     *     cy4: int,
     *     cy5: int,
     *     cy6: int,
     *     injured: int
     * }> Keyed by pid
     */
    public function getAllPlayerChangeableFields(): array;

    /**
     * Get completed trade transactions for TRN export.
     *
     * Returns trade items grouped by trade offer ID, for the current season.
     *
     * @return list<array{
     *     tradeofferid: int,
     *     itemid: int,
     *     itemtype: string,
     *     from: string,
     *     to: string,
     *     created_at: string
     * }>
     */
    public function getCompletedTradeItems(): array;
}
