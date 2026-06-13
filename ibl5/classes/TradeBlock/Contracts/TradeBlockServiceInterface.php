<?php

declare(strict_types=1);

namespace TradeBlock\Contracts;

/**
 * TradeBlockServiceInterface - Read-path orchestration for the trade block.
 *
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 *
 * @phpstan-type BrowseTeamGroup array{
 *     teamid: int,
 *     team_name: string,
 *     team_city: string,
 *     color1: string,
 *     color2: string,
 *     players: list<array{pid: int, name: string, note: string}>,
 *     seekingNote: string
 * }
 * @phpstan-type BrowseData array{teams: list<BrowseTeamGroup>}
 * @phpstan-type EditFormData array{
 *     team: \Team\Team,
 *     roster: list<PlayerRow>,
 *     blockPids: array<int, string>,
 *     seekingNote: string
 * }
 */
interface TradeBlockServiceInterface
{
    /**
     * League-wide read-only board data, grouped by current owning team.
     *
     * @return BrowseData
     */
    public function getBrowseData(): array;

    /**
     * Everything the owner's bulk edit form needs.
     *
     * @return EditFormData
     */
    public function getEditFormData(int $teamId): array;
}
