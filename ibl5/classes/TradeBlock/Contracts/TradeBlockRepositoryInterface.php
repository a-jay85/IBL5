<?php

declare(strict_types=1);

namespace TradeBlock\Contracts;

/**
 * TradeBlockRepositoryInterface - Contract for trade-block data access.
 *
 * Reads derive the current team at read time by JOINing gm_trade_block.pid to
 * ibl_plr (live teamid/name), so a traded/waived player self-corrects and no
 * redundant teamid is stored. All writes go through prepared statements.
 *
 * @phpstan-type AvailablePlayerRow array{
 *     pid: int,
 *     note: string,
 *     name: string,
 *     teamid: int,
 *     team_name: string,
 *     team_city: string,
 *     color1: string,
 *     color2: string
 * }
 */
interface TradeBlockRepositoryInterface
{
    /**
     * Browse query: every on-block player whose live row is active, with the
     * current owning team derived via JOIN (not stored).
     *
     * @return list<AvailablePlayerRow>
     */
    public function getAllAvailable(): array;

    /**
     * pid => note map of on-block players currently rostered by $teamId
     * (live teamid, retired = 0) — backs the edit form's checkbox pre-check.
     *
     * @return array<int, string>
     */
    public function getBlockPidsForTeam(int $teamId): array;

    /**
     * teamid => seeking_note map for every team that has set a note.
     *
     * @return array<int, string>
     */
    public function getSeekingNotesByTeam(): array;

    /**
     * The seeking note for one team, or '' when none set.
     */
    public function getSeekingNoteForTeam(int $teamId): string;

    /**
     * Mark a player on the block (upsert the note).
     */
    public function setOnBlock(int $pid, string $note): bool;

    /**
     * Remove a player from the block.
     */
    public function removeFromBlock(int $pid): bool;

    /**
     * Upsert a team's free-text seeking note.
     */
    public function upsertSeekingNote(int $teamId, string $note): bool;
}
